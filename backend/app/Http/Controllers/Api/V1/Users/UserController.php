<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Services\User\UserManagementService;
use App\Helpers\ApiResponse;

/**
 * Manages user CRUD operations, role assignments, and status changes.
 */
class UserController extends Controller
{
    protected UserManagementService $userService;

    /**
     * Create a new UserController instance.
     *
     * @param UserManagementService|null $userService User management service
     */
    public function __construct(?UserManagementService $userService = null)
    {
        $this->userService = $userService ?? new UserManagementService();
    }

    /**
     * List all users for an organization with pagination.
     *
     * @param int $orgId Organization ID
     * @param array $requestData Request parameters including optional limit and offset
     * @return array API response with list of users
     */
    public function index(int $orgId, array $requestData): array
    {
        $limit = (int)($requestData['limit'] ?? 50);
        $offset = (int)($requestData['offset'] ?? 0);
        $users = $this->userService->listUsers($orgId, $limit, $offset);
        return ApiResponse::success($users, 'Users retrieved successfully', 200);
    }

    /**
     * Retrieve details of a specific user within an organization.
     *
     * @param int $orgId Organization ID
     * @param int $id User ID
     * @return array API response with user details or error
     */
    public function show(int $orgId, int $id): array
    {
        $user = $this->userService->getUser($id);
        if (!$user || ($user['organization_id'] && (int)$user['organization_id'] !== $orgId)) {
            return ApiResponse::error('User not found in current organization.', null, 404);
        }
        return ApiResponse::success($user, 'User details retrieved', 200);
    }

    /**
     * Create a new user in an organization with role assignment.
     *
     * Prevents creation of Super Admin users and ensures email and first name are provided.
     *
     * @param int $orgId Organization ID
     * @param array $requestData User data including email, first_name, and optional role_id
     * @param int|null $performedBy User ID performing this action
     * @return array API response with created user or error
     */
    public function store(int $orgId, array $requestData, ?int $performedBy = null): array
    {
        if (empty($requestData['email']) || empty($requestData['first_name'])) {
            return ApiResponse::error('Email and first name are required.', [
                'email' => empty($requestData['email']) ? ['The email field is required.'] : [],
                'first_name' => empty($requestData['first_name']) ? ['The first name field is required.'] : [],
            ], 422);
        }

        // Role assignment protection
        $targetRoleId = (int)($requestData['role_id'] ?? 2);
        if ($targetRoleId === 1) {
            return ApiResponse::error('DENIED: Unauthorized role assignment. Super Admin role cannot be created.', null, 403);
        }

        $newUser = $this->userService->createUser($requestData, $orgId, $performedBy);
        if (!$newUser) {
            return ApiResponse::error('Failed to create user. A user with this email may already exist.', null, 409);
        }
        return ApiResponse::success($newUser, 'User created successfully', 201);
    }

    /**
     * Update an existing user with role and self-modification protection.
     *
     * Prevents users from changing their own role and from elevating anyone to Super Admin.
     *
     * @param int $orgId Organization ID
     * @param int $id User ID to update
     * @param array $requestData Updated user data
     * @param int|null $performedBy User ID performing this action
     * @return array API response with updated user or error
     */
    public function update(int $orgId, int $id, array $requestData, ?int $performedBy = null): array
    {
        $user = $this->userService->getUser($id);
        if (!$user || ($user['organization_id'] && (int)$user['organization_id'] !== $orgId)) {
            return ApiResponse::error('User not found in current organization.', null, 404);
        }

        // Role elevation / self-modification protection
        if (isset($requestData['role_id'])) {
            $targetRoleId = (int)$requestData['role_id'];
            if ($id === $performedBy && $targetRoleId !== (int)$user['role_id']) {
                return ApiResponse::error('DENIED: A user cannot change their own role.', null, 403);
            }
            if ($targetRoleId === 1) {
                return ApiResponse::error('DENIED: Unauthorized role assignment. Super Admin role cannot be assigned.', null, 403);
            }
        }

        $requestData['organization_id'] = $orgId;
        $updated = $this->userService->updateUser($id, $requestData, $performedBy);
        return ApiResponse::success($updated, 'User updated successfully', 200);
    }

    /**
     * Change the status of a user.
     *
     * @param int $orgId Organization ID
     * @param int $id User ID
     * @param array $requestData Status data including status value (active, inactive, locked)
     * @param int|null $performedBy User ID performing this action
     * @return array API response confirming status change or error
     */
    public function setStatus(int $orgId, int $id, array $requestData, ?int $performedBy = null): array
    {
        $status = $requestData['status'] ?? '';
        if (!in_array($status, ['active', 'inactive', 'locked'], true)) {
            return ApiResponse::error('Invalid status value.', ['status' => ['Allowed: active, inactive, locked']], 422);
        }

        $ok = $this->userService->setUserStatus($id, $status, $performedBy);
        if (!$ok) {
            return ApiResponse::error('Failed to update user status.', null, 400);
        }
        return ApiResponse::success(null, "User status updated to {$status}", 200);
    }
}
