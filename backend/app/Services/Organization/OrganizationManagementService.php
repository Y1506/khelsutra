<?php

namespace App\Services\Organization;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;
use Exception;

/**
 * Manages organization CRUD operations, status changes, access logs, and admin user creation.
 */
class OrganizationManagementService extends BaseService
{
    protected AuditLogService $auditLog;

    /**
     * Create a new OrganizationManagementService instance.
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
     * Retrieve a paginated list of all organizations with user and employee counts.
     *
     * @param int $limit Maximum number of organizations to return
     * @param int $offset Number of organizations to skip
     * @return array List of organizations with aggregate counts
     */
    public function listOrganizations(int $limit = 50, int $offset = 0): array
    {
        if (!$this->pdo) return [];
        $stmt = $this->pdo->prepare("
            SELECT o.*, 
                   (SELECT COUNT(*) FROM organization_users WHERE organization_id = o.id AND access_status = 'active') as active_users_count,
                   (SELECT COUNT(*) FROM employees WHERE organization_id = o.id AND employment_status = 'active' AND deleted_at IS NULL) as active_employees_count
            FROM organizations o 
            WHERE o.deleted_at IS NULL 
            ORDER BY o.id DESC 
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Retrieve a specific organization by ID.
     *
     * @param int $id Organization ID
     * @return array|null Organization data or null if not found
     */
    public function getOrganization(int $id): ?array
    {
        if (!$this->pdo) return null;
        $stmt = $this->pdo->prepare("SELECT * FROM organizations WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create a new organization with auto-generated code and access log.
     *
     * Sets default status to 'active' and creates a 1-year access period.
     * Records creation in audit logs and access history.
     *
     * @param array $data Organization data including name and optional contact details
     * @param int|null $performedBy User ID performing this action
     * @return array|null Created organization data or null on failure
     */
    public function createOrganization(array $data, ?int $performedBy = null): ?array
    {
        if (!$this->pdo) return null;

        $orgCode = strtoupper(trim($data['organization_code'] ?? 'ORG-' . strtoupper(bin2hex(random_bytes(3)))));
        $name = trim($data['name'] ?? '');
        if (empty($name)) return null;

        $stmt = $this->pdo->prepare("
            INSERT INTO organizations (
                organization_code, name, legal_name, email, phone, website,
                address_line1, address_line2, city, state, country, postal_code,
                status, access_start_date, access_end_date, plan_name, notes,
                created_by, updated_by, created_at, updated_at
            ) VALUES (
                :code, :name, :legal_name, :email, :phone, :website,
                :addr1, :addr2, :city, :state, :country, :postal,
                :status, :start_date, :end_date, :plan, :notes,
                :created_by, :updated_by, NOW(), NOW()
            )
        ");

        $status = $data['status'] ?? 'active';
        $startDate = $data['access_start_date'] ?? date('Y-m-d');
        $endDate = $data['access_end_date'] ?? date('Y-m-d', strtotime('+1 year'));

        $stmt->execute([
            ':code' => $orgCode,
            ':name' => $name,
            ':legal_name' => $data['legal_name'] ?? null,
            ':email' => $data['email'] ?? null,
            ':phone' => $data['phone'] ?? null,
            ':website' => $data['website'] ?? null,
            ':addr1' => $data['address_line1'] ?? null,
            ':addr2' => $data['address_line2'] ?? null,
            ':city' => $data['city'] ?? null,
            ':state' => $data['state'] ?? null,
            ':country' => $data['country'] ?? 'India',
            ':postal' => $data['postal_code'] ?? null,
            ':status' => $status,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':plan' => $data['plan_name'] ?? 'Standard Sports ERP',
            ':notes' => $data['notes'] ?? null,
            ':created_by' => $performedBy,
            ':updated_by' => $performedBy,
        ]);

        $newOrgId = (int)$this->pdo->lastInsertId();

        // Log access record
        $this->logAccess($newOrgId, 'created', null, $status, $startDate, $endDate, $performedBy, 'Initial organization setup');

        // Audit log
        $this->auditLog->log($newOrgId, $performedBy, 'ORGANIZATION_CREATE', 'Organization', 'organizations', $newOrgId, null, $data, "Created organization {$name} ({$orgCode})");

        return $this->getOrganization($newOrgId);
    }

    /**
     * Update an existing organization's details.
     *
     * Merges new data with existing values and records changes in audit log.
     *
     * @param int $id Organization ID
     * @param array $data Updated organization data
     * @param int|null $performedBy User ID performing this action
     * @return array|null Updated organization data or null on failure
     */
    public function updateOrganization(int $id, array $data, ?int $performedBy = null): ?array
    {
        if (!$this->pdo) return null;
        $existing = $this->getOrganization($id);
        if (!$existing) return null;

        $stmt = $this->pdo->prepare("
            UPDATE organizations SET 
                name = :name,
                legal_name = :legal_name,
                email = :email,
                phone = :phone,
                website = :website,
                address_line1 = :addr1,
                address_line2 = :addr2,
                city = :city,
                state = :state,
                country = :country,
                postal_code = :postal,
                plan_name = :plan,
                notes = :notes,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'] ?? $existing['name'],
            ':legal_name' => $data['legal_name'] ?? $existing['legal_name'],
            ':email' => $data['email'] ?? $existing['email'],
            ':phone' => $data['phone'] ?? $existing['phone'],
            ':website' => $data['website'] ?? $existing['website'],
            ':addr1' => $data['address_line1'] ?? $existing['address_line1'],
            ':addr2' => $data['address_line2'] ?? $existing['address_line2'],
            ':city' => $data['city'] ?? $existing['city'],
            ':state' => $data['state'] ?? $existing['state'],
            ':country' => $data['country'] ?? $existing['country'],
            ':postal' => $data['postal_code'] ?? $existing['postal_code'],
            ':plan' => $data['plan_name'] ?? $existing['plan_name'],
            ':notes' => $data['notes'] ?? $existing['notes'],
            ':updated_by' => $performedBy,
        ]);

        $updated = $this->getOrganization($id);
        $this->auditLog->log($id, $performedBy, 'ORGANIZATION_UPDATE', 'Organization', 'organizations', $id, $existing, $updated, "Updated organization details");

        return $updated;
    }

    /**
     * Change the status of an organization and log the action.
     *
     * Records the status change in both access logs and audit logs.
     *
     * @param int $id Organization ID
     * @param string $newStatus New status (pending, active, suspended, expired, inactive)
     * @param string|null $remarks Optional notes about the status change
     * @param int|null $performedBy User ID performing this action
     * @return bool True on success, false on failure
     */
    public function updateStatus(int $id, string $newStatus, ?string $remarks = null, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;
        $allowed = ['pending', 'active', 'suspended', 'expired', 'inactive'];
        if (!in_array($newStatus, $allowed, true)) return false;

        $existing = $this->getOrganization($id);
        if (!$existing) return false;

        $stmt = $this->pdo->prepare("UPDATE organizations SET status = :status, updated_by = :up_by, updated_at = NOW() WHERE id = :id");
        $ok = $stmt->execute([':status' => $newStatus, ':up_by' => $performedBy, ':id' => $id]);

        if ($ok) {
            $action = match ($newStatus) {
                'active' => 'activated',
                'suspended' => 'suspended',
                'expired' => 'expired',
                default => 'deactivated'
            };

            $this->logAccess($id, $action, $existing['status'], $newStatus, $existing['access_start_date'], $existing['access_end_date'], $performedBy, $remarks);
            $this->auditLog->log($id, $performedBy, 'ORGANIZATION_STATUS_CHANGE', 'Organization', 'organizations', $id, ['status' => $existing['status']], ['status' => $newStatus], "Changed status from {$existing['status']} to {$newStatus}");
        }

        return $ok;
    }

    /**
     * Record an organization access event in the access log.
     *
     * @param int $orgId Organization ID
     * @param string $action Action performed (created, activated, suspended, etc.)
     * @param string|null $prevStatus Previous status
     * @param string|null $newStatus New status
     * @param string|null $start Access start date
     * @param string|null $end Access end date
     * @param int|null $by User ID who performed the action
     * @param string|null $remarks Optional notes
     * @return bool True on success, false on failure
     */
    public function logAccess(int $orgId, string $action, ?string $prevStatus, ?string $newStatus, ?string $start, ?string $end, ?int $by, ?string $remarks): bool
    {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("
            INSERT INTO organization_access_logs (organization_id, action, previous_status, new_status, access_start_date, access_end_date, performed_by, remarks, created_at)
            VALUES (:org_id, :action, :prev, :new, :start, :end, :by, :remarks, NOW())
        ");
        return $stmt->execute([
            ':org_id' => $orgId,
            ':action' => $action,
            ':prev' => $prevStatus,
            ':new' => $newStatus,
            ':start' => $start,
            ':end' => $end,
            ':by' => $by,
            ':remarks' => $remarks
        ]);
    }

    /**
     * Retrieve all access logs for an organization in descending chronological order.
     *
     * @param int $orgId Organization ID
     * @return array List of access log entries
     */
    public function getAccessLogs(int $orgId): array
    {
        if (!$this->pdo) return [];
        $stmt = $this->pdo->prepare("SELECT * FROM organization_access_logs WHERE organization_id = :org_id ORDER BY created_at DESC");
        $stmt->execute([':org_id' => $orgId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Create or assign an initial Sports Administrator user for an organization.
     *
     * Creates a new user if one doesn't exist with the given email, then assigns
     * them the Sports Administrator role (role_id 2) in the organization.
     *
     * @param int $orgId Organization ID
     * @param array $adminData Admin user data including email, first_name, and optional password
     * @param int|null $superAdminId Super admin user ID performing this action
     * @return array|null Created admin user info or null on failure
     */
    public function createInitialSportsAdmin(int $orgId, array $adminData, ?int $superAdminId = null): ?array
    {
        if (!$this->pdo) return null;

        $email = trim($adminData['email'] ?? '');
        $firstName = trim($adminData['first_name'] ?? '');
        $lastName = trim($adminData['last_name'] ?? '');
        $password = $adminData['password'] ?? 'SecretPassword123';

        if (empty($email) || empty($firstName)) return null;

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        // Check if user exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $existingUserId = $stmt->fetchColumn();

        $userId = $existingUserId;
        if (!$userId) {
            $userStmt = $this->pdo->prepare("
                INSERT INTO users (uuid, email, password, first_name, last_name, phone, status, created_at, updated_at)
                VALUES (:uuid, :email, :password, :fname, :lname, :phone, 'active', NOW(), NOW())
            ");
            $userStmt->execute([
                ':uuid' => $uuid,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':fname' => $firstName,
                ':lname' => $lastName,
                ':phone' => $adminData['phone'] ?? null
            ]);
            $userId = (int)$this->pdo->lastInsertId();
        }

        // Bind in organization_users as Sports Administrator (Role ID 2)
        $ouStmt = $this->pdo->prepare("
            INSERT INTO organization_users (organization_id, user_id, role_id, access_status, assigned_by, assigned_at, created_at, updated_at)
            VALUES (:org_id, :user_id, 2, 'active', :assigned_by, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE role_id = 2, access_status = 'active', updated_at = NOW()
        ");
        $ouStmt->execute([
            ':org_id' => $orgId,
            ':user_id' => $userId,
            ':assigned_by' => $superAdminId
        ]);

        $this->auditLog->log($orgId, $superAdminId, 'USER_CREATE', 'User', 'organization_users', $userId, null, ['role_id' => 2, 'email' => $email], "Created initial Sports Administrator for organization #{$orgId}");

        return [
            'user_id' => $userId,
            'email' => $email,
            'role' => 'Sports Administrator',
            'organization_id' => $orgId
        ];
    }
}
