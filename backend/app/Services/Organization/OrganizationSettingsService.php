<?php

namespace App\Services\Organization;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;

class OrganizationSettingsService extends BaseService
{
    protected AuditLogService $auditLog;

    /**
     * Create a new organization settings service instance.
     *
     * @param PDO|null $pdo Optional PDO instance
     * @param AuditLogService|null $auditLog Optional audit log service instance
     */
    public function __construct(?PDO $pdo = null, ?AuditLogService $auditLog = null)
    {
        parent::__construct($pdo);
        $this->auditLog = $auditLog ?? new AuditLogService($this->pdo);
    }

    /**
     * Get all settings for an organization as a key-value array.
     *
     * @param int $orgId The organization ID
     * @return array Associative array of setting keys to values (with type casting applied)
     */
    public function getAll(int $orgId): array
    {
        if (!$this->pdo) return [];
        $stmt = $this->pdo->prepare("SELECT * FROM organization_settings WHERE organization_id = :org_id ORDER BY setting_key ASC");
        $stmt->execute([':org_id' => $orgId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $this->castValue($row['setting_value'], $row['setting_type']);
        }
        return $settings;
    }

    /**
     * Alias for getAll - get all settings for an organization.
     *
     * @param int $orgId The organization ID
     * @return array Associative array of setting keys to values
     */
    public function getAllSettings(int $orgId): array
    {
        return $this->getAll($orgId);
    }

    /**
     * Get a single setting value by key.
     *
     * @param int $orgId The organization ID
     * @param string $key The setting key
     * @param mixed $default The default value to return if setting not found
     * @return mixed The setting value (type-cast) or default if not found
     */
    public function get(int $orgId, string $key, mixed $default = null): mixed
    {
        if (!$this->pdo) return $default;
        $stmt = $this->pdo->prepare("SELECT setting_value, setting_type FROM organization_settings WHERE organization_id = :org_id AND setting_key = :key LIMIT 1");
        $stmt->execute([':org_id' => $orgId, ':key' => $key]);
        $row = $stmt->fetch();
        if (!$row) return $default;

        return $this->castValue($row['setting_value'], $row['setting_type']);
    }

    /**
     * Alias for get - get a single setting value by key.
     *
     * @param int $orgId The organization ID
     * @param string $key The setting key
     * @param mixed $default The default value to return if setting not found
     * @return mixed The setting value or default if not found
     */
    public function getSetting(int $orgId, string $key, mixed $default = null): mixed
    {
        return $this->get($orgId, $key, $default);
    }

    /**
     * Set or update a setting value.
     *
     * @param int $orgId The organization ID
     * @param string $key The setting key
     * @param mixed $value The setting value
     * @param string $type The data type (string, integer, decimal, boolean, json)
     * @param int|null $performedBy The user ID performing this action (for audit log)
     * @return bool True if setting was saved successfully, false otherwise
     */
    public function set(int $orgId, string $key, mixed $value, string $type = 'string', ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;
        $allowedTypes = ['string', 'integer', 'decimal', 'boolean', 'json'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'string';
        }

        $serializedValue = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string)$value,
        };

        $stmt = $this->pdo->prepare("
            INSERT INTO organization_settings (organization_id, setting_key, setting_value, setting_type, created_at, updated_at)
            VALUES (:org_id, :key, :val, :type, NOW(), NOW())
            ON DUPLICATE KEY UPDATE setting_value = :val2, setting_type = :type2, updated_at = NOW()
        ");

        $ok = $stmt->execute([
            ':org_id' => $orgId,
            ':key' => $key,
            ':val' => $serializedValue,
            ':type' => $type,
            ':val2' => $serializedValue,
            ':type2' => $type,
        ]);

        if ($ok) {
            $this->auditLog->log($orgId, $performedBy, 'SETTINGS_CHANGE', 'Settings', 'organization_settings', null, null, ['key' => $key, 'value' => $serializedValue, 'type' => $type], "Updated setting {$key}");
        }

        return $ok;
    }

    /**
     * Alias for set - set or update a setting value.
     *
     * @param int $orgId The organization ID
     * @param string $key The setting key
     * @param mixed $value The setting value
     * @param string $type The data type (string, integer, decimal, boolean, json)
     * @return bool True if setting was saved successfully, false otherwise
     */
    public function setSetting(int $orgId, string $key, mixed $value, string $type = 'string'): bool
    {
        return $this->set($orgId, $key, $value, $type);
    }

    /**
     * Cast a setting value from string to its appropriate type.
     *
     * @param string|null $val The string value from database
     * @param string $type The data type (integer, decimal, boolean, json, string)
     * @return mixed The type-cast value
     */
    private function castValue(?string $val, string $type): mixed
    {
        if ($val === null) return null;
        return match ($type) {
            'integer' => (int)$val,
            'decimal' => (float)$val,
            'boolean' => in_array(strtolower($val), ['1', 'true', 'yes', 'on'], true),
            'json' => json_decode($val, true),
            default => $val,
        };
    }
}
