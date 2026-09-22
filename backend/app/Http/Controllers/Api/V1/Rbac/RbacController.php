<?php

namespace App\Http\Controllers\Api\V1\Rbac;

use App\Http\Controllers\Controller;
use App\Services\Rbac\PermissionService;
use App\Helpers\ApiResponse;

/**
 * Manages role-based access control including roles, permissions, and user permission overrides.
 */
class RbacController extends Controller
{
    protected PermissionService $permissionService;

    /**
     * Create a new RbacController instance.
     *
     * @param PermissionService|null $permissionService Permission service instance
     */
    public function __construct(?PermissionService $permissionService = null)
    {
        $this->permissionService = $permissionService ?? new PermissionService();
    }

    /**
     * Retrieve all system roles.
     *
     * @return array API response with list of roles
     */
    public function roles(): array
    {
        $roles = $this->permissionService->getRoles();
        return ApiResponse::success($roles, 'System roles retrieved successfully', 200);
    }

    /**
     * Retrieve all available permissions in the system.
     *
     * @return array API response with permissions catalog
     */
    public function permissions(): array
    {
        $permissions = $this->permissionService->getPermissions();
        return ApiResponse::success($permissions, 'Permissions catalog retrieved', 200);
    }

    /**
     * Retrieve permissions assigned to a specific role.
     *
     * @param int $roleId Role ID
     * @return array API response with role permissions
     */
    public function rolePermissions(int $roleId): array
    {
        $perms = $this->permissionService->getRolePermissions($roleId);
        return ApiResponse::success($perms, "Permissions for role #{$roleId} retrieved", 200);
    }

    /**
     * Set a permission override for a user (grant or deny).
     *
     * @param int $orgId Organization ID
     * @param array $requestData Override data including user_id, permission_id, and override_type
     * @param int|null $performedBy User ID performing this action
     * @return array API response confirming override or error
     */
    public function setOverride(int $orgId, array $requestData, ?int $performedBy = null): array
    {
        $userId = (int)($requestData['user_id'] ?? 0);
        $permId = (int)($requestData['permission_id'] ?? 0);
        $type = $requestData['override_type'] ?? 'grant';

        if (!$userId || !$permId || !in_array($type, ['grant', 'deny'], true)) {
            return ApiResponse::error('Valid user_id, permission_id, and override_type (grant/deny) are required.', null, 422);
        }

        $ok = $this->permissionService->setPermissionOverride($orgId, $userId, $permId, $type, $performedBy);
        if (!$ok) {
            return ApiResponse::error('Failed to set permission override.', null, 400);
        }
        return ApiResponse::success(null, "Permission override set to {$type}", 200);
    }
}
