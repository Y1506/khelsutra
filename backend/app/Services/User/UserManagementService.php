<?php

namespace App\Services\User;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;

/**
 * Manages user CRUD operations, role assignments, and status changes with audit logging.
 */
class UserManagementService extends BaseService
{
    protected AuditLogService $auditLog;

    /**
     * Create a new UserManagementService instance.
     *
     * @param PDO|null $pdo Database connection
     * @param AuditLogService|null $auditLog Audit logging service
     */
    public function __construct(?PDO $pdo = null, ?AuditLogService $auditLog = null)
    {
        parent::__construct($pdo);
        $this->auditLog = $auditLog ?? new AuditLogService($this->pdo);
    }

    /**
     * Retrieve a paginated list of users with role and organization details.
     *
     * @param int|null $orgId Optional organization ID to filter by
     * @param int $limit Maximum number of users to return
     * @param int $offset Number of users to skip
     * @return array List of users with joined role and organization data
     */
    public function listUsers(?int $orgId = null, int $limit = 50, int $offset = 0): array
    {
        if (!$this->pdo) return [];

        $sql = "
            SELECT u.id, u.uuid, u.username, u.email, u.first_name, u.last_name, u.phone,
                   u.profile_photo_path, u.status, u.last_login_at, u.created_at,
                   ou.role_id, r.name as role_name, ou.access_status, o.name as organization_name, o.id as organization_id
            FROM users u
            LEFT JOIN organization_users ou ON u.id = ou.user_id
            LEFT JOIN roles r ON ou.role_id = r.id
            LEFT JOIN organizations o ON ou.organization_id = o.id
            WHERE u.deleted_at IS NULL
        ";
        $params = [];

        if ($orgId !== null) {
            $sql .= " AND ou.organization_id = :org_id ";
            $params[':org_id'] = $orgId;
        }

        $sql .= " ORDER BY u.id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Retrieve a specific user by ID with role and organization details.
     *
     * @param int $id User ID
     * @return array|null User data or null if not found
     */
    public function getUser(int $id): ?array
    {
        if (!$this->pdo) return null;
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.uuid, u.username, u.email, u.first_name, u.last_name, u.phone,
                   u.profile_photo_path, u.status, u.last_login_at, u.created_at,
                   ou.role_id, r.name as role_name, ou.organization_id, o.name as organization_name
            FROM users u
            LEFT JOIN organization_users ou ON u.id = ou.user_id
            LEFT JOIN roles r ON ou.role_id = r.id
            LEFT JOIN organizations o ON ou.organization_id = o.id
            WHERE u.id = :id AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create a new user and assign them to an organization with a role.
     *
     * Validates email uniqueness, generates UUID, hashes password, and records
     * the creation in audit logs.
     *
     * @param array $data User data including email, first_name, and optional password/role_id
     * @param int $orgId Organization ID to assign the user to
     * @param int|null $performedBy User ID performing this action
     * @return array|null Created user data or null on failure/duplicate email
     */
    public function createUser(array $data, int $orgId, ?int $performedBy = null): ?array
    {
        if (!$this->pdo) return null;

        $email = trim($data['email'] ?? '');
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $password = $data['password'] ?? 'SecretPassword123';
        $roleId = (int)($data['role_id'] ?? 2);

        if (empty($email) || empty($firstName)) return null;

        // Check uniqueness
        $checkStmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $checkStmt->execute([':email' => $email]);
        if ($checkStmt->fetchColumn()) {
            return null; // Already exists
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $stmt = $this->pdo->prepare("
            INSERT INTO users (uuid, username, email, password, first_name, last_name, phone, status, created_at, updated_at)
            VALUES (:uuid, :username, :email, :password, :fname, :lname, :phone, 'active', NOW(), NOW())
        ");
        $stmt->execute([
            ':uuid' => $uuid,
            ':username' => $data['username'] ?? null,
            ':email' => $email,
            ':password' => $hashed,
            ':fname' => $firstName,
            ':lname' => $lastName,
            ':phone' => $data['phone'] ?? null,
        ]);
        $newUserId = (int)$this->pdo->lastInsertId();

        // Assign to organization
        $ouStmt = $this->pdo->prepare("
            INSERT INTO organization_users (organization_id, user_id, role_id, access_status, assigned_by, assigned_at, created_at, updated_at)
            VALUES (:org_id, :user_id, :role_id, 'active', :by, NOW(), NOW(), NOW())
        ");
        $ouStmt->execute([
            ':org_id' => $orgId,
            ':user_id' => $newUserId,
            ':role_id' => $roleId,
            ':by' => $performedBy,
        ]);

        $this->auditLog->log($orgId, $performedBy, 'USER_CREATE', 'User', 'users', $newUserId, null, ['email' => $email, 'role_id' => $roleId], "Created user {$firstName} {$lastName} ({$email})");

        return $this->getUser($newUserId);
    }

    /**
     * Update an existing user's information and optionally their role.
     *
     * Updates basic profile fields and optionally updates the user's role
     * within their organization. Records changes in audit logs.
     *
     * @param int $id User ID
     * @param array $data Updated user data including optional role_id and organization_id
     * @param int|null $performedBy User ID performing this action
     * @return array|null Updated user data or null on failure
     */
    public function updateUser(int $id, array $data, ?int $performedBy = null): ?array
    {
        if (!$this->pdo) return null;
        $existing = $this->getUser($id);
        if (!$existing) return null;

        $stmt = $this->pdo->prepare("
            UPDATE users SET 
                first_name = :fname,
                last_name = :lname,
                phone = :phone,
                status = :status,
                updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $id,
            ':fname' => $data['first_name'] ?? $existing['first_name'],
            ':lname' => $data['last_name'] ?? $existing['last_name'],
            ':phone' => $data['phone'] ?? $existing['phone'],
            ':status' => $data['status'] ?? $existing['status'],
        ]);

        // If role update is requested
        if (isset($data['role_id']) && isset($data['organization_id'])) {
            $ouStmt = $this->pdo->prepare("
                UPDATE organization_users SET role_id = :role_id, updated_at = NOW()
                WHERE user_id = :user_id AND organization_id = :org_id
            ");
            $ouStmt->execute([
                ':role_id' => (int)$data['role_id'],
                ':user_id' => $id,
                ':org_id' => (int)$data['organization_id']
            ]);
        }

        $updated = $this->getUser($id);
        $this->auditLog->log($existing['organization_id'] ?? null, $performedBy, 'USER_UPDATE', 'User', 'users', $id, $existing, $updated, "Updated user #{$id}");

        return $updated;
    }

    /**
     * Change a user's status and record the action in audit logs.
     *
     * @param int $id User ID
     * @param string $status New status (active, inactive, locked)
     * @param int|null $performedBy User ID performing this action
     * @return bool True on success, false on failure
     */
    public function setUserStatus(int $id, string $status, ?int $performedBy = null): bool
    {
        if (!$this->pdo || !in_array($status, ['active', 'inactive', 'locked'], true)) {
            return false;
        }
        $stmt = $this->pdo->prepare("UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id");
        $ok = $stmt->execute([':status' => $status, ':id' => $id]);

        if ($ok) {
            $this->auditLog->log(null, $performedBy, 'USER_DEACTIVATE', 'User', 'users', $id, null, ['status' => $status], "Changed user #{$id} status to {$status}");
        }
        return $ok;
    }
}
