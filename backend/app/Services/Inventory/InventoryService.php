<?php

namespace App\Services\Inventory;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;
use Exception;
use InvalidArgumentException;

class InventoryService extends BaseService
{
    protected AuditLogService $auditLog;

    public function __construct(?PDO $pdo = null, ?AuditLogService $auditLog = null)
    {
        parent::__construct($pdo);
        $this->auditLog = $auditLog ?? new AuditLogService($this->pdo);
    }

    /**
     * List inventory items with filtering and pagination.
     */
    public function listItems(
        int $organizationId,
        int $page = 1,
        int $limit = 15,
        ?string $search = null,
        ?int $categoryId = null,
        ?string $status = null,
        bool $lowStockOnly = false
    ): array {
        if (!$this->pdo) {
            return ['data' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 0];
        }

        $conditions = ["ii.organization_id = :org_id", "ii.deleted_at IS NULL"];
        $params = [':org_id' => $organizationId];

        if (!empty($search)) {
            $conditions[] = "(ii.item_name LIKE :search OR ii.item_code LIKE :search OR ii.location_name LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        if (!empty($categoryId)) {
            $conditions[] = "ii.category_id = :cat_id";
            $params[':cat_id'] = $categoryId;
        }

        if ($lowStockOnly || $status === 'low_stock') {
            $conditions[] = "ii.quantity <= ii.reorder_level";
        } elseif (!empty($status)) {
            $conditions[] = "ii.status = :status";
            $params[':status'] = $status;
        }

        $whereClause = implode(' AND ', $conditions);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM inventory_items ii WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $limit);

        $sql = "
            SELECT 
                ii.*,
                ic.name as category_name,
                (ii.quantity <= ii.reorder_level) as is_low_stock
            FROM inventory_items ii
            LEFT JOIN inventory_categories ic ON ii.category_id = ic.id
            WHERE {$whereClause}
            ORDER BY ii.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'data' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / max(1, $limit)),
        ];
    }

    /**
     * Get a single inventory item by ID with transactions and equipment units.
     */
    public function getItem(int $organizationId, int $id): ?array
    {
        if (!$this->pdo) return null;

        $stmt = $this->pdo->prepare("
            SELECT ii.*, 
                   ic.name as category_name,
                   (ii.quantity <= ii.reorder_level) as is_low_stock
            FROM inventory_items ii
            LEFT JOIN inventory_categories ic ON ii.category_id = ic.id
            WHERE ii.id = :id AND ii.organization_id = :org_id AND ii.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) return null;

        // Stock Transactions (Up to 50 recent records)
        $txStmt = $this->pdo->prepare("
            SELECT st.*, 
                   COALESCE(CONCAT(e.first_name, ' ', e.last_name), CONCAT(u.first_name, ' ', u.last_name), 'Staff') as performer_name
            FROM stock_transactions st
            LEFT JOIN users u ON st.performed_by = u.id
            LEFT JOIN employees e ON st.performed_by = e.user_id
            WHERE st.inventory_item_id = :item_id AND st.organization_id = :org_id
            ORDER BY st.transaction_date DESC, st.id DESC
            LIMIT 50
        ");
        $txStmt->execute([':item_id' => $id, ':org_id' => $organizationId]);
        $item['transactions'] = $txStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Tracked Individual Equipment Units
        $eqStmt = $this->pdo->prepare("
            SELECT eq.*, ea.status as assignment_status, tm.name as assigned_team_name
            FROM equipment eq
            LEFT JOIN equipment_assignments ea ON eq.id = ea.equipment_id AND ea.status = 'assigned'
            LEFT JOIN teams tm ON ea.team_id = tm.id
            WHERE eq.inventory_item_id = :item_id AND eq.organization_id = :org_id AND eq.deleted_at IS NULL
            ORDER BY eq.asset_code ASC
        ");
        $eqStmt->execute([':item_id' => $id, ':org_id' => $organizationId]);
        $item['equipment_units'] = $eqStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $item;
    }

    /**
     * Get all low-stock items for an organization.
     */
    public function getLowStockItems(int $organizationId): array
    {
        if (!$this->pdo) return [];

        $stmt = $this->pdo->prepare("
            SELECT ii.*, ic.name as category_name, 1 as is_low_stock
            FROM inventory_items ii
            LEFT JOIN inventory_categories ic ON ii.category_id = ic.id
            WHERE ii.organization_id = :org_id 
              AND ii.quantity <= ii.reorder_level 
              AND ii.deleted_at IS NULL
            ORDER BY (ii.quantity - ii.reorder_level) ASC, ii.id DESC
        ");
        $stmt->execute([':org_id' => $organizationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Create a new inventory item with validation, unique item code, and optional opening stock.
     */
    public function createItem(int $organizationId, array $data, ?int $performedBy = null): array
    {
        if (!$this->pdo) return [];

        $name = trim($data['item_name'] ?? '');
        if (empty($name)) {
            throw new InvalidArgumentException('Item name is required.');
        }
        if (mb_strlen($name) > 150) {
            throw new InvalidArgumentException('Item name cannot exceed 150 characters.');
        }

        // Validate or resolve category_id (NOT NULL in schema)
        $categoryId = !empty($data['category_id']) ? (int)$data['category_id'] : null;
        if ($categoryId !== null) {
            $catCheck = $this->pdo->prepare("
                SELECT id FROM inventory_categories 
                WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL 
                LIMIT 1
            ");
            $catCheck->execute([':id' => $categoryId, ':org_id' => $organizationId]);
            if (!$catCheck->fetchColumn()) {
                throw new InvalidArgumentException('Selected category does not exist in this organization.');
            }
        } else {
            // Pick first active category in org or create default
            $catStmt = $this->pdo->prepare("
                SELECT id FROM inventory_categories 
                WHERE organization_id = :org_id AND status = 'active' AND deleted_at IS NULL 
                ORDER BY id ASC LIMIT 1
            ");
            $catStmt->execute([':org_id' => $organizationId]);
            $categoryId = $catStmt->fetchColumn();

            if (!$categoryId) {
                $insCat = $this->pdo->prepare("
                    INSERT INTO inventory_categories (organization_id, name, description, status, created_at, updated_at)
                    VALUES (:org_id, 'General Sports Gear', 'Default category for equipment and supplies', 'active', NOW(), NOW())
                ");
                $insCat->execute([':org_id' => $organizationId]);
                $categoryId = (int)$this->pdo->lastInsertId();
            } else {
                $categoryId = (int)$categoryId;
            }
        }

        $initialQty = (float)($data['quantity'] ?? 0);
        if ($initialQty < 0) {
            throw new InvalidArgumentException('Initial quantity cannot be negative.');
        }

        $minLevel = max(0, (float)($data['minimum_stock_level'] ?? 5));
        $reorderLevel = max(0, (float)($data['reorder_level'] ?? 10));
        $unitCost = max(0, (float)($data['unit_cost'] ?? 0));

        // Code uniqueness check or auto-generation
        $code = !empty($data['item_code']) ? trim((string)$data['item_code']) : null;
        if (!empty($code)) {
            if (mb_strlen($code) > 50) {
                throw new InvalidArgumentException('Item code cannot exceed 50 characters.');
            }
            $dupStmt = $this->pdo->prepare("
                SELECT id FROM inventory_items 
                WHERE organization_id = :org_id AND item_code = :code AND deleted_at IS NULL 
                LIMIT 1
            ");
            $dupStmt->execute([':org_id' => $organizationId, ':code' => $code]);
            if ($dupStmt->fetchColumn()) {
                throw new InvalidArgumentException("An item with code '{$code}' already exists in this organization.");
            }
        } else {
            do {
                $code = 'ITM-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4));
                $codeCheck = $this->pdo->prepare("SELECT id FROM inventory_items WHERE organization_id = :org_id AND item_code = :code LIMIT 1");
                $codeCheck->execute([':org_id' => $organizationId, ':code' => $code]);
            } while ($codeCheck->fetchColumn());
        }

        $status = strtolower(trim($data['status'] ?? 'active'));
        if (!in_array($status, ['active', 'inactive', 'discontinued'], true)) {
            $status = 'active';
        }

        $unit = trim($data['unit'] ?? 'piece');
        if (mb_strlen($unit) > 30) {
            $unit = substr($unit, 0, 30);
        }

        $location = isset($data['location_name']) ? trim((string)$data['location_name']) : 'Main Store Room';
        if (mb_strlen($location) > 150) {
            $location = substr($location, 0, 150);
        }

        $auditUser = $this->resolveAuditUserId($performedBy);

        $this->pdo->beginTransaction();
        try {
            $sql = "
                INSERT INTO inventory_items (
                    organization_id, category_id, item_code, item_name, description,
                    unit, quantity, minimum_stock_level, reorder_level, unit_cost,
                    location_name, status, created_at, updated_at
                ) VALUES (
                    :org_id, :cat_id, :code, :name, :desc,
                    :unit, :qty, :min_lvl, :reorder_lvl, :cost,
                    :loc, :status, NOW(), NOW()
                )
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':org_id' => $organizationId,
                ':cat_id' => $categoryId,
                ':code' => $code,
                ':name' => $name,
                ':desc' => $data['description'] ?? null,
                ':unit' => $unit,
                ':qty' => $initialQty,
                ':min_lvl' => $minLevel,
                ':reorder_lvl' => $reorderLevel,
                ':cost' => $unitCost,
                ':loc' => $location,
                ':status' => $status,
            ]);

            $itemId = (int)$this->pdo->lastInsertId();

            // Record opening stock transaction if quantity > 0
            if ($initialQty > 0) {
                $txSql = "
                    INSERT INTO stock_transactions (
                        organization_id, inventory_item_id, transaction_type, quantity,
                        unit_cost, transaction_date, performed_by, remarks, created_at
                    ) VALUES (
                        :org_id, :item_id, 'opening', :qty,
                        :cost, NOW(), :perf_by, 'Initial stock on item registration', NOW()
                    )
                ";
                $txStmt = $this->pdo->prepare($txSql);
                $txStmt->execute([
                    ':org_id' => $organizationId,
                    ':item_id' => $itemId,
                    ':qty' => $initialQty,
                    ':cost' => $unitCost,
                    ':perf_by' => $auditUser,
                ]);
            }

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $auditUser,
                'INVENTORY_ITEM_CREATE',
                'Inventory',
                'inventory_items',
                $itemId,
                null,
                ['code' => $code, 'name' => $name, 'quantity' => $initialQty],
                "Created inventory item {$name} ({$code})"
            );

            // Automated Low-Stock Alert trigger
            try {
                $notifService = new \App\Services\Notification\NotificationService($this->pdo);
                $notifService->checkAndTriggerLowStock($organizationId, $itemId);
            } catch (\Throwable $e) {}

            return $this->getItem($organizationId, $itemId) ?? [
                'id' => $itemId,
                'item_code' => $code,
                'item_name' => $name,
                'quantity' => $initialQty,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Update an existing inventory item profile.
     */
    public function updateItem(int $organizationId, int $id, array $data, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getItem($organizationId, $id);
        if (!$existing) {
            throw new InvalidArgumentException("Inventory item #{$id} not found or does not belong to this organization.");
        }

        $name = isset($data['item_name']) ? trim((string)$data['item_name']) : $existing['item_name'];
        if (empty($name)) {
            throw new InvalidArgumentException('Item name cannot be empty.');
        }
        if (mb_strlen($name) > 150) {
            throw new InvalidArgumentException('Item name cannot exceed 150 characters.');
        }

        $categoryId = isset($data['category_id']) ? (int)$data['category_id'] : $existing['category_id'];
        if ($categoryId !== (int)$existing['category_id']) {
            $catCheck = $this->pdo->prepare("SELECT id FROM inventory_categories WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL LIMIT 1");
            $catCheck->execute([':id' => $categoryId, ':org_id' => $organizationId]);
            if (!$catCheck->fetchColumn()) {
                throw new InvalidArgumentException('Selected category does not exist in this organization.');
            }
        }

        $minLevel = isset($data['minimum_stock_level']) ? max(0, (float)$data['minimum_stock_level']) : (float)$existing['minimum_stock_level'];
        $reorderLevel = isset($data['reorder_level']) ? max(0, (float)$data['reorder_level']) : (float)$existing['reorder_level'];
        $unitCost = isset($data['unit_cost']) ? max(0, (float)$data['unit_cost']) : (float)$existing['unit_cost'];

        $unit = isset($data['unit']) ? trim((string)$data['unit']) : $existing['unit'];
        $location = isset($data['location_name']) ? trim((string)$data['location_name']) : $existing['location_name'];
        $description = array_key_exists('description', $data) ? $data['description'] : $existing['description'];

        $status = isset($data['status']) ? strtolower(trim((string)$data['status'])) : $existing['status'];
        if (!in_array($status, ['active', 'inactive', 'discontinued'], true)) {
            $status = $existing['status'];
        }

        $sql = "
            UPDATE inventory_items SET
                item_name = :name,
                category_id = :cat_id,
                description = :desc,
                unit = :unit,
                minimum_stock_level = :min_lvl,
                reorder_level = :reorder_lvl,
                unit_cost = :cost,
                location_name = :loc,
                status = :status,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
        ";

        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':name' => $name,
            ':cat_id' => $categoryId,
            ':desc' => $description,
            ':unit' => $unit,
            ':min_lvl' => $minLevel,
            ':reorder_lvl' => $reorderLevel,
            ':cost' => $unitCost,
            ':loc' => $location,
            ':status' => $status,
            ':id' => $id,
            ':org_id' => $organizationId,
        ]);

        if ($ok) {
            $auditUser = $this->resolveAuditUserId($performedBy);
            $this->auditLog->log(
                $organizationId,
                $auditUser,
                'INVENTORY_ITEM_UPDATE',
                'Inventory',
                'inventory_items',
                $id,
                $existing,
                $data,
                "Updated inventory item #{$id} ({$name})"
            );

            // Automated Low-Stock Alert trigger
            try {
                $notifService = new \App\Services\Notification\NotificationService($this->pdo);
                $notifService->checkAndTriggerLowStock($organizationId, $id);
            } catch (\Throwable $e) {}
        }

        return $ok;
    }

    /**
     * Record a stock transaction (Stock In, Stock Out, Stock Adjustment).
     * Strictly enforces non-negative stock and creates transaction history.
     */
    public function recordStockTransaction(int $organizationId, int $itemId, array $data, ?int $performedBy = null): array
    {
        if (!$this->pdo) return [];

        $existing = $this->getItem($organizationId, $itemId);
        if (!$existing) {
            throw new Exception("Inventory item #{$itemId} not found or does not belong to this organization.");
        }

        $type = strtolower(trim($data['transaction_type'] ?? 'adjustment'));
        if ($type === 'adjustment_dec') {
            $type = 'adjustment';
            $data['adjustment_action'] = 'decrease';
        }
        $validTypes = ['opening', 'purchase', 'issue', 'return', 'adjustment', 'damage', 'loss', 'disposal'];
        if (!in_array($type, $validTypes, true)) {
            throw new InvalidArgumentException("Invalid transaction type '{$type}'. Allowed types: " . implode(', ', $validTypes));
        }

        $currentQty = (float)$existing['quantity'];
        $remarks = isset($data['remarks']) ? trim((string)$data['remarks']) : null;
        $unitCost = isset($data['unit_cost']) ? (float)$data['unit_cost'] : (float)$existing['unit_cost'];

        // Compute new quantity and delta based on transaction type
        if ($type === 'adjustment') {
            // Case 1: Target physical count provided
            if (isset($data['new_quantity']) && $data['new_quantity'] !== '') {
                $targetQty = (float)$data['new_quantity'];
                if ($targetQty < 0) {
                    throw new Exception("Adjusted quantity cannot be negative.");
                }
                $diff = $targetQty - $currentQty;
                $qty = abs($diff);
                $newQty = $targetQty;
                if (!$remarks) {
                    $remarks = "Stock count adjusted from {$currentQty} to {$targetQty}";
                }
            } else {
                // Case 2: Delta adjustment quantity
                $rawQty = (float)($data['quantity'] ?? 0);
                if ($rawQty == 0) {
                    throw new Exception("Adjustment quantity must be non-zero.");
                }

                $isDecrease = (!empty($data['adjustment_action']) && $data['adjustment_action'] === 'decrease') || $rawQty < 0;
                $qty = abs($rawQty);

                if ($isDecrease) {
                    if ($currentQty < $qty) {
                        throw new Exception("Insufficient stock: Current quantity is {$currentQty}, cannot adjust below zero.");
                    }
                    $newQty = $currentQty - $qty;
                } else {
                    $newQty = $currentQty + $qty;
                }
            }
        } elseif (in_array($type, ['issue', 'damage', 'loss', 'disposal'], true)) {
            // Stock Out / Deductions
            $qty = (float)($data['quantity'] ?? 0);
            if ($qty <= 0) {
                throw new Exception("Stock out quantity must be greater than zero.");
            }
            if ($currentQty < $qty) {
                throw new Exception("Insufficient stock: Current quantity is {$currentQty}, cannot deduct {$qty}.");
            }
            $newQty = $currentQty - $qty;
        } else {
            // Stock In: purchase, return, opening
            $qty = (float)($data['quantity'] ?? 0);
            if ($qty <= 0) {
                throw new Exception("Stock in quantity must be greater than zero.");
            }
            $newQty = $currentQty + $qty;
        }

        // Final sanity check: quantity must NEVER be negative
        if ($newQty < 0) {
            throw new Exception("Operation rejected: Inventory quantity cannot become negative.");
        }

        $auditUser = $this->resolveAuditUserId($performedBy);

        $this->pdo->beginTransaction();
        try {
            // 1. Insert immutable stock transaction ledger record
            $txSql = "
                INSERT INTO stock_transactions (
                    organization_id, inventory_item_id, transaction_type, quantity,
                    unit_cost, reference_type, reference_id, transaction_date,
                    performed_by, remarks, created_at
                ) VALUES (
                    :org_id, :item_id, :type, :qty,
                    :cost, :ref_type, :ref_id, NOW(),
                    :perf_by, :remarks, NOW()
                )
            ";
            $txStmt = $this->pdo->prepare($txSql);
            $txStmt->execute([
                ':org_id' => $organizationId,
                ':item_id' => $itemId,
                ':type' => $type,
                ':qty' => $qty,
                ':cost' => $unitCost,
                ':ref_type' => $data['reference_type'] ?? null,
                ':ref_id' => !empty($data['reference_id']) ? (int)$data['reference_id'] : null,
                ':perf_by' => $auditUser,
                ':remarks' => $remarks,
            ]);
            $txId = (int)$this->pdo->lastInsertId();

            // 2. Atomically update item stock level
            $upSql = "
                UPDATE inventory_items 
                SET quantity = :new_qty, updated_at = NOW() 
                WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
            ";
            $upStmt = $this->pdo->prepare($upSql);
            $upStmt->execute([':new_qty' => $newQty, ':id' => $itemId, ':org_id' => $organizationId]);

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $auditUser,
                'STOCK_TRANSACTION',
                'Inventory',
                'stock_transactions',
                $txId,
                ['old_quantity' => $currentQty],
                ['type' => $type, 'quantity' => $qty, 'new_quantity' => $newQty],
                "Recorded stock {$type} of {$qty} units for {$existing['item_name']}. New level: {$newQty}"
            );

            // Automated Low-Stock Alert trigger on stock change (quantity <= minimum_stock_level)
            try {
                $notifService = new \App\Services\Notification\NotificationService($this->pdo);
                $notifService->checkAndTriggerLowStock($organizationId, $itemId);
            } catch (\Throwable $e) {}

            return [
                'transaction_id' => $txId,
                'item_id' => $itemId,
                'previous_quantity' => $currentQty,
                'new_quantity' => $newQty,
                'transaction_type' => $type,
                'quantity' => $qty,
                'remarks' => $remarks,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get stock transactions history for an organization or specific item.
     */
    public function getStockTransactions(
        int $organizationId,
        ?int $itemId = null,
        int $page = 1,
        int $limit = 50,
        ?string $type = null
    ): array {
        if (!$this->pdo) {
            return ['data' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 0];
        }

        $conditions = ["st.organization_id = :org_id"];
        $params = [':org_id' => $organizationId];

        if ($itemId !== null) {
            $conditions[] = "st.inventory_item_id = :item_id";
            $params[':item_id'] = $itemId;
        }
        if (!empty($type)) {
            $conditions[] = "st.transaction_type = :type";
            $params[':type'] = $type;
        }

        $where = implode(' AND ', $conditions);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM stock_transactions st WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $limit);

        $sql = "
            SELECT st.*, 
                   ii.item_name, ii.item_code, ii.unit,
                   COALESCE(CONCAT(e.first_name, ' ', e.last_name), CONCAT(u.first_name, ' ', u.last_name), 'Staff') as performer_name
            FROM stock_transactions st
            LEFT JOIN inventory_items ii ON st.inventory_item_id = ii.id
            LEFT JOIN users u ON st.performed_by = u.id
            LEFT JOIN employees e ON st.performed_by = e.user_id
            WHERE {$where}
            ORDER BY st.transaction_date DESC, st.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / max(1, $limit)),
        ];
    }

    /**
     * Soft-delete an inventory item.
     */
    public function deleteItem(int $organizationId, int $id, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getItem($organizationId, $id);
        if (!$existing) {
            throw new InvalidArgumentException("Inventory item #{$id} not found or does not belong to this organization.");
        }

        $stmt = $this->pdo->prepare("
            UPDATE inventory_items 
            SET deleted_at = NOW(), updated_at = NOW() 
            WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
        ");
        $ok = $stmt->execute([':id' => $id, ':org_id' => $organizationId]);

        if ($ok) {
            $auditUser = $this->resolveAuditUserId($performedBy);
            $this->auditLog->log(
                $organizationId,
                $auditUser,
                'INVENTORY_ITEM_DELETE',
                'Inventory',
                'inventory_items',
                $id,
                $existing,
                null,
                "Soft deleted inventory item #{$id} ({$existing['item_name']})"
            );
        }

        return $ok;
    }

    /**
     * Resolve a valid user_id for the audit_logs foreign key constraint, or return null.
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
