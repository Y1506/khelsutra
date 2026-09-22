<?php

namespace App\Services\Audit;

use App\Services\BaseService;
use PDO;

/**
 * Provides audit logging for tracking user actions and data changes.
 */
class AuditLogService extends BaseService
{
    private static array $sensitiveKeys = [
        'password', 'password_confirmation', 'token', 'remember_token', 'secret', 'api_key'
    ];

    /**
     * Create an audit log entry for a user action.
     *
     * Sanitizes sensitive fields from old/new values before storing.
     * Records IP address and user agent for security tracking.
     *
     * @param int|null $orgId Organization ID context
     * @param int|null $userId User who performed the action
     * @param string $action Action type (e.g., CREATE, UPDATE, DELETE)
     * @param string $module Module name (e.g., Auth, User, Organization)
     * @param string|null $tableName Database table affected
     * @param int|null $recordId ID of the affected record
     * @param array|null $oldValues Previous values before change
     * @param array|null $newValues New values after change
     * @param string|null $description Human-readable description
     * @return bool True on success, false on failure
     */
    public function log(
        ?int $orgId,
        ?int $userId,
        string $action,
        string $module,
        ?string $tableName = null,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null
    ): bool {
        if (!$this->pdo) {
            return false;
        }

        $sanitizedOld = $oldValues ? self::sanitize($oldValues) : null;
        $sanitizedNew = $newValues ? self::sanitize($newValues) : null;

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Internal/System';

        $stmt = $this->pdo->prepare("
            INSERT INTO audit_logs 
            (organization_id, user_id, action, module, table_name, record_id, old_values, new_values, description, ip_address, user_agent, created_at)
            VALUES 
            (:org_id, :user_id, :action, :module, :table_name, :record_id, :old_values, :new_values, :description, :ip, :ua, NOW())
        ");

        return $stmt->execute([
            ':org_id' => $orgId,
            ':user_id' => $userId,
            ':action' => $action,
            ':module' => $module,
            ':table_name' => $tableName,
            ':record_id' => $recordId,
            ':old_values' => $sanitizedOld ? json_encode($sanitizedOld) : null,
            ':new_values' => $sanitizedNew ? json_encode($sanitizedNew) : null,
            ':description' => $description,
            ':ip' => substr($ipAddress, 0, 45),
            ':ua' => substr($userAgent, 0, 500),
        ]);
    }

    /**
     * Retrieve audit logs with optional organization filtering.
     *
     * Includes user and organization details via joins.
     *
     * @param int|null $orgId Optional organization ID to filter by
     * @param int $limit Maximum number of logs to return
     * @param int $offset Number of logs to skip
     * @return array List of audit log entries with related user/organization data
     */
    public function getLogs(?int $orgId = null, int $limit = 50, int $offset = 0): array
    {
        if (!$this->pdo) return [];

        $sql = "SELECT a.*, u.first_name, u.last_name, u.email as user_email, o.name as organization_name 
                FROM audit_logs a 
                LEFT JOIN users u ON a.user_id = u.id 
                LEFT JOIN organizations o ON a.organization_id = o.id ";
        $params = [];

        if ($orgId !== null) {
            $sql .= " WHERE a.organization_id = :org_id ";
            $params[':org_id'] = $orgId;
        }

        $sql .= " ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset";
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
     * Recursively sanitize sensitive fields from data arrays.
     *
     * Replaces values of sensitive keys (password, token, etc.) with '[REDACTED]'.
     *
     * @param array $data Data to sanitize
     * @return array Sanitized data with sensitive fields redacted
     */
    public static function sanitize(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), self::$sensitiveKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}
