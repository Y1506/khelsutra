<?php

namespace App\Services\Auth;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;

class AuthService extends BaseService
{
    protected AuditLogService $auditLog;

    /**
     * AuthService constructor.
     *
     * @param PDO|null $pdo Database connection
     * @param AuditLogService|null $auditLog Audit log service
     */
    public function __construct(?PDO $pdo = null, ?AuditLogService $auditLog = null)
    {
        parent::__construct($pdo);
        $this->auditLog = $auditLog ?? new AuditLogService($this->pdo);
    }

    /**
     * Authenticate user and generate access token.
     *
     * @param string $email User email
     * @param string $password User password
     * @param string|null $orgCode Optional organization code
     * @return array|null User data with token and permissions, null if authentication fails
     */
    public function login(string $email, string $password, ?string $orgCode = null): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        // 1. Fetch user by email
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        // Check user account status
        if ($user['status'] !== 'active') {
            return null;
        }

        // 2. Verify password
        $passwordMatches = password_verify($password, $user['password']);
        if (!$passwordMatches) {
            return null;
        }

        // 3. Resolve Organization & Role
        $orgQuery = "SELECT ou.*, o.name as org_name, o.organization_code, o.status as org_status, r.name as role_name 
                     FROM organization_users ou 
                     JOIN organizations o ON ou.organization_id = o.id 
                     JOIN roles r ON ou.role_id = r.id 
                     WHERE ou.user_id = :user_id 
                       AND ou.access_status = 'active'
                       AND o.deleted_at IS NULL";
        
        $params = [':user_id' => $user['id']];
        if ($orgCode) {
            $orgQuery .= " AND o.organization_code = :org_code";
            $params[':org_code'] = $orgCode;
        }
        $orgQuery .= " ORDER BY o.id ASC LIMIT 1";

        $orgStmt = $this->pdo->prepare($orgQuery);
        $orgStmt->execute($params);
        $membership = $orgStmt->fetch();

        $roleId = $membership ? (int)$membership['role_id'] : 2;
        $roleName = $membership['role_name'] ?? 'Sports Administrator';

        $roleSlugMap = [
            1 => 'super_admin',
            2 => 'sports_admin',
            3 => 'hr_finance',
            4 => 'coach',
            5 => 'athlete',
            6 => 'venue_manager',
            7 => 'inventory_manager',
        ];
        $roleSlug = $roleSlugMap[$roleId] ?? 'sports_admin';

        if ($roleId === 1) {
            // Super Admin has platform-level context, no normal tenant lock
            $orgId = null;
            $orgData = [
                'id' => null,
                'name' => 'KhelSutra Platform',
                'organization_code' => 'PLATFORM',
                'status' => 'active'
            ];
        } else {
            $orgId = $membership ? (int)$membership['organization_id'] : 1;
            $orgData = $membership ? [
                'id' => $orgId,
                'name' => $membership['org_name'],
                'organization_code' => $membership['organization_code'],
                'status' => $membership['org_status']
            ] : [
                'id' => 1,
                'name' => 'Apex Sports Academy',
                'organization_code' => 'ORG-DEMO',
                'status' => 'active'
            ];
        }

        // 4. Resolve Base Role Permissions
        $permStmt = $this->pdo->prepare("
            SELECT p.name 
            FROM permissions p 
            JOIN role_permissions rp ON p.id = rp.permission_id 
            WHERE rp.role_id = :role_id
        ");
        $permStmt->execute([':role_id' => $roleId]);
        $basePermissions = $permStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // 5. Apply User Permission Overrides (grant/deny)
        $overrides = [];
        if ($orgId) {
            $overrideStmt = $this->pdo->prepare("
                SELECT p.name, upo.override_type 
                FROM user_permission_overrides upo 
                JOIN permissions p ON upo.permission_id = p.id 
                WHERE upo.user_id = :user_id AND upo.organization_id = :org_id
            ");
            $overrideStmt->execute([':user_id' => $user['id'], ':org_id' => $orgId]);
            $overrides = $overrideStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $effectivePermissions = array_fill_keys($basePermissions, true);
        foreach ($overrides as $ovr) {
            if ($ovr['override_type'] === 'grant') {
                $effectivePermissions[$ovr['name']] = true;
            } elseif ($ovr['override_type'] === 'deny') {
                unset($effectivePermissions[$ovr['name']]);
            }
        }
        $permissionsList = array_keys($effectivePermissions);

        // 6. Update last_login_at
        $updateStmt = $this->pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        $updateStmt->execute([':id' => $user['id']]);

        // 7. Audit log event
        $this->auditLog->log(
            $orgId,
            (int)$user['id'],
            'LOGIN',
            'Auth',
            'users',
            (int)$user['id'],
            null,
            ['email' => $user['email'], 'role' => $roleName, 'organization_id' => $orgId],
            "User {$user['email']} logged in successfully"
        );

        // 8. Generate tamper-proof signed HMAC token
        $secret = env('APP_KEY', 'khelsutra-secret-key-32-chars-required!!');
        $payload = [
            'uid' => (int)$user['id'],
            'oid' => $orgId,
            'rid' => (int)$roleId,
            'slug' => $roleSlug,
            'iat' => time(),
            'exp' => time() + (86400 * 7),
        ];
        $encodedPayload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encodedPayload, $secret);
        $token = $encodedPayload . '.' . $signature;

        return [
            'user' => [
                'id' => (int)$user['id'],
                'uuid' => $user['uuid'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'status' => $user['status'],
                'last_login_at' => date('Y-m-d H:i:s'),
            ],
            'organization' => $orgData,
            'role' => [
                'id' => (int)$roleId,
                'name' => $roleName,
                'slug' => $roleSlug,
            ],
            'permissions' => $permissionsList,
            'token' => $token,
        ];
    }

    /**
     * Log user out and record audit event.
     *
     * @param int|null $userId User ID
     * @param int|null $orgId Organization ID
     * @return bool Always returns true
     */
    public function logout(?int $userId = null, ?int $orgId = null): bool
    {
        if ($userId) {
            $this->auditLog->log($orgId, $userId, 'LOGOUT', 'Auth', 'users', $userId, null, null, "User logged out");
        }
        return true;
    }

    /**
     * Resolve user data from authentication token.
     *
     * @param string $token HMAC-signed authentication token
     * @return array|null User data with role and permissions, null if token is invalid or expired
     */
    public function resolveUserByToken(string $token): ?array
    {
        if (empty($token) || !str_contains($token, '.')) return null;

        list($encodedPayload, $providedSignature) = explode('.', $token, 2);
        $secret = env('APP_KEY', 'khelsutra-secret-key-32-chars-required!!');
        $expectedSignature = hash_hmac('sha256', $encodedPayload, $secret);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return null; // Tampered or invalid token
        }

        $json = base64_decode(strtr($encodedPayload, '-_', '+/'));
        $payload = json_decode($json, true);
        if (!$payload || !isset($payload['uid'])) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null; // Expired token
        }

        $userId = (int)$payload['uid'];
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id AND status = 'active' AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        if (!$user) return null;

        // Resolve organization & role from DB
        $orgStmt = $this->pdo->prepare("
            SELECT ou.*, o.name as org_name, o.organization_code, o.status as org_status, r.name as role_name 
            FROM organization_users ou 
            JOIN organizations o ON ou.organization_id = o.id 
            JOIN roles r ON ou.role_id = r.id 
            WHERE ou.user_id = :user_id 
              AND ou.access_status = 'active'
              AND o.deleted_at IS NULL
            LIMIT 1
        ");
        $orgStmt->execute([':user_id' => $userId]);
        $membership = $orgStmt->fetch();

        $roleId = $membership ? (int)$membership['role_id'] : (int)($payload['rid'] ?? 2);
        $roleName = $membership['role_name'] ?? 'Sports Administrator';

        $roleSlugMap = [
            1 => 'super_admin',
            2 => 'sports_admin',
            3 => 'hr_finance',
            4 => 'coach',
            5 => 'athlete',
            6 => 'venue_manager',
            7 => 'inventory_manager',
        ];
        $roleSlug = $roleSlugMap[$roleId] ?? 'sports_admin';

        if ($roleId === 1) {
            $orgData = [
                'id' => null,
                'name' => 'KhelSutra Platform',
                'organization_code' => 'PLATFORM',
                'status' => 'active'
            ];
            $orgId = null;
        } else {
            $orgId = $membership ? (int)$membership['organization_id'] : 1;
            $orgData = $membership ? [
                'id' => $orgId,
                'name' => $membership['org_name'],
                'organization_code' => $membership['organization_code'],
                'status' => $membership['org_status']
            ] : null;
        }

        // Load permissions
        $permStmt = $this->pdo->prepare("
            SELECT p.name 
            FROM permissions p 
            JOIN role_permissions rp ON p.id = rp.permission_id 
            WHERE rp.role_id = :role_id
        ");
        $permStmt->execute([':role_id' => $roleId]);
        $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return [
            'id' => (int)$user['id'],
            'uuid' => $user['uuid'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'status' => $user['status'],
            'role_id' => $roleId,
            'role' => [
                'id' => $roleId,
                'name' => $roleName,
                'slug' => $roleSlug,
            ],
            'organization' => $orgData,
            'permissions' => $permissions,
        ];
    }
}
