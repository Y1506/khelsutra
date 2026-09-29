<?php

namespace App\Services\Inventory;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;
use Exception;
use InvalidArgumentException;

class InventoryCategoryService extends BaseService
{
    protected AuditLogService $auditLog;

    public function __construct(?PDO $pdo = null, ?AuditLogService $auditLog = null)
    {
        parent::__construct($pdo);
        $this->auditLog = $auditLog ?? new AuditLogService($this->pdo);
    }

    /**
     * List all categories for the given organization.
     * Optionally filter by status ('active' | 'inactive').
     *
     * @param int $orgId
     * @param string|null $status
     * @return array
     */
    public function listCategories(int $orgId, ?string $status = null): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "
            SELECT c.*, 
                   (SELECT COUNT(*) FROM inventory_items WHERE category_id = c.id AND deleted_at IS NULL) as item_count
            FROM inventory_categories c 
            WHERE c.organization_id = :org_id AND c.deleted_at IS NULL
        ";
        $params = [':org_id' => $orgId];

        if (!empty($status)) {
            $sql .= " AND c.status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY c.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get a single category by ID with tenant verification.
     *
     * @param int $orgId
     * @param int $id
     * @return array|null
     */
    public function getCategory(int $orgId, int $id): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   (SELECT COUNT(*) FROM inventory_items WHERE category_id = c.id AND deleted_at IS NULL) as item_count
            FROM inventory_categories c
            WHERE c.id = :id AND c.organization_id = :org_id AND c.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':org_id' => $orgId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create a new category with strict validation, uniqueness per org, and audit logging.
     *
     * @param int $orgId
     * @param array $data
     * @param int|null $performedBy
     * @return array|null
     * @throws InvalidArgumentException
     */
    public function createCategory(int $orgId, array $data, ?int $performedBy = null): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        $name = trim($data['name'] ?? ($data['category_name'] ?? ''));
        if (empty($name)) {
            throw new InvalidArgumentException('Category name is required.');
        }

        if (mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Category name cannot exceed 100 characters.');
        }

        $description = isset($data['description']) ? trim((string)$data['description']) : null;
        if ($description !== null && mb_strlen($description) > 255) {
            throw new InvalidArgumentException('Description cannot exceed 255 characters.');
        }

        $status = strtolower(trim($data['status'] ?? 'active'));
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        // Check if category name already exists for this organization (non-deleted)
        $dupStmt = $this->pdo->prepare("
            SELECT id FROM inventory_categories 
            WHERE organization_id = :org_id AND LOWER(name) = LOWER(:name) AND deleted_at IS NULL
            LIMIT 1
        ");
        $dupStmt->execute([':org_id' => $orgId, ':name' => $name]);
        if ($dupStmt->fetchColumn()) {
            throw new InvalidArgumentException("A category named '{$name}' already exists in this organization.");
        }

        // Also check if a soft-deleted record exists with same name (due to unique key on org_id, name)
        // If a soft-deleted row exists with the same name, we can restore/reuse it or update it
        $deletedStmt = $this->pdo->prepare("
            SELECT id FROM inventory_categories 
            WHERE organization_id = :org_id AND name = :name AND deleted_at IS NOT NULL
            LIMIT 1
        ");
        $deletedStmt->execute([':org_id' => $orgId, ':name' => $name]);
        $existingDeletedId = $deletedStmt->fetchColumn();

        if ($existingDeletedId) {
            $stmt = $this->pdo->prepare("
                UPDATE inventory_categories SET
                    description = :description,
                    status = :status,
                    deleted_at = NULL,
                    updated_at = NOW()
                WHERE id = :id AND organization_id = :org_id
            ");
            $stmt->execute([
                ':description' => $description,
                ':status' => $status,
                ':id' => (int)$existingDeletedId,
                ':org_id' => $orgId,
            ]);
            $newId = (int)$existingDeletedId;
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO inventory_categories (organization_id, name, description, status, created_at, updated_at)
                VALUES (:org_id, :name, :description, :status, NOW(), NOW())
            ");
            $stmt->execute([
                ':org_id' => $orgId,
                ':name' => $name,
                ':description' => $description,
                ':status' => $status,
            ]);
            $newId = (int)$this->pdo->lastInsertId();
        }

        $this->auditLog->log(
            $orgId,
            $this->resolveAuditUserId($performedBy),
            'CREATE',
            'InventoryCategory',
            'inventory_categories',
            $newId,
            null,
            ['name' => $name, 'description' => $description, 'status' => $status],
            "Created inventory category {$name}"
        );

        return $this->getCategory($orgId, $newId);
    }

    /**
     * Update an existing category with tenant verification and audit logging.
     *
     * @param int $orgId
     * @param int $id
     * @param array $data
     * @param int|null $performedBy
     * @return array|null
     * @throws InvalidArgumentException
     */
    public function updateCategory(int $orgId, int $id, array $data, ?int $performedBy = null): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        $existing = $this->getCategory($orgId, $id);
        if (!$existing) {
            throw new InvalidArgumentException('Category not found or does not belong to this organization.');
        }

        $name = isset($data['name']) ? trim((string)$data['name']) : (isset($data['category_name']) ? trim((string)$data['category_name']) : $existing['name']);
        if (empty($name)) {
            throw new InvalidArgumentException('Category name cannot be empty.');
        }
        if (mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Category name cannot exceed 100 characters.');
        }

        // If name changed, verify uniqueness within the organization
        if (strcasecmp($name, $existing['name']) !== 0) {
            $dupStmt = $this->pdo->prepare("
                SELECT id FROM inventory_categories 
                WHERE organization_id = :org_id AND LOWER(name) = LOWER(:name) AND id != :id AND deleted_at IS NULL
                LIMIT 1
            ");
            $dupStmt->execute([':org_id' => $orgId, ':name' => $name, ':id' => $id]);
            if ($dupStmt->fetchColumn()) {
                throw new InvalidArgumentException("A category named '{$name}' already exists in this organization.");
            }
        }

        $description = array_key_exists('description', $data) 
            ? (trim((string)$data['description']) ?: null) 
            : $existing['description'];
        if ($description !== null && mb_strlen($description) > 255) {
            throw new InvalidArgumentException('Description cannot exceed 255 characters.');
        }

        $status = isset($data['status']) ? strtolower(trim((string)$data['status'])) : $existing['status'];
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = $existing['status'];
        }

        $stmt = $this->pdo->prepare("
            UPDATE inventory_categories SET
                name = :name,
                description = :description,
                status = :status,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':status' => $status,
            ':id' => $id,
            ':org_id' => $orgId,
        ]);

        $updated = $this->getCategory($orgId, $id);

        $this->auditLog->log(
            $orgId,
            $this->resolveAuditUserId($performedBy),
            'UPDATE',
            'InventoryCategory',
            'inventory_categories',
            $id,
            $existing,
            $updated,
            "Updated inventory category #{$id} ({$name})"
        );

        return $updated;
    }

    /**
     * Activate or deactivate a category.
     *
     * @param int $orgId
     * @param int $id
     * @param string $status 'active' | 'inactive'
     * @param int|null $performedBy
     * @return array|null
     * @throws InvalidArgumentException
     */
    public function setStatus(int $orgId, int $id, string $status, ?int $performedBy = null): ?array
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException("Invalid status: '{$status}'. Allowed values are 'active' or 'inactive'.");
        }

        return $this->updateCategory($orgId, $id, ['status' => $status], $performedBy);
    }

    /**
     * Soft delete a category.
     * Prevents deletion if any active items in inventory_items are linked to it.
     *
     * @param int $orgId
     * @param int $id
     * @param int|null $performedBy
     * @return bool
     * @throws Exception
     */
    public function deleteCategory(int $orgId, int $id, ?int $performedBy = null): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $existing = $this->getCategory($orgId, $id);
        if (!$existing) {
            throw new InvalidArgumentException('Category not found or does not belong to this organization.');
        }

        // Check if items are assigned to this category
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM inventory_items 
            WHERE category_id = :cat_id AND deleted_at IS NULL
        ");
        $countStmt->execute([':cat_id' => $id]);
        $assignedCount = (int)$countStmt->fetchColumn();

        if ($assignedCount > 0) {
            throw new Exception("Cannot delete category '{$existing['name']}': {$assignedCount} active item(s) are currently assigned to it. Please reassign or remove the items first.");
        }

        $stmt = $this->pdo->prepare("
            UPDATE inventory_categories SET
                deleted_at = NOW(),
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id, ':org_id' => $orgId]);

        $this->auditLog->log(
            $orgId,
            $this->resolveAuditUserId($performedBy),
            'DELETE',
            'InventoryCategory',
            'inventory_categories',
            $id,
            $existing,
            null,
            "Soft-deleted inventory category #{$id} ({$existing['name']})"
        );

        return true;
    }

    /**
     * Resolve a valid user_id for the audit_logs foreign key constraint, or return null.
     *
     * @param int|null $userId
     * @return int|null
     */
    protected function resolveAuditUserId(?int $userId): ?int
    {
        if (!$userId || !$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetchColumn() ? (int)$userId : null;
    }
}
