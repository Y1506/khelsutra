<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use App\Http\Requests\Auth\LoginRequest;
use App\Helpers\ApiResponse;

class AuthController extends Controller
{
    protected AuthService $authService;

    /**
     * AuthController constructor.
     *
     * @param AuthService|null $authService Authentication service
     */
    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    /**
     * Authenticate user and return session data with token.
     *
     * @param array $requestData Login credentials (email, password, organization_code)
     * @return array API response with user session or error
     */
    public function login(array $requestData): array
    {
        $validator = new LoginRequest($requestData);
        $errors = $validator->validate();
        if (!empty($errors)) {
            return ApiResponse::error('Validation failed', $errors, 422);
        }

        $session = $this->authService->login(
            $validator->email,
            $validator->password,
            $validator->organization_code
        );

        if (!$session) {
            return ApiResponse::error('Invalid credentials or inactive account.', null, 401);
        }

        return ApiResponse::success($session, 'Login successful', 200);
    }

    /**
     * Log out the current user.
     *
     * @return array API response confirming logout
     */
    public function logout(): array
    {
        return ApiResponse::success(null, 'Successfully logged out', 200);
    }

    /**
     * Get current authenticated user profile.
     *
     * @param array $currentUser Current user data from authentication
     * @return array API response with user profile
     */
    public function me(array $currentUser): array
    {
        return ApiResponse::success($currentUser, 'User profile retrieved', 200);
    }

    /**
     * Refresh authentication token.
     *
     * @return array API response with new token
     */
    public function refresh(): array
    {
        $newToken = bin2hex(random_bytes(32));
        return ApiResponse::success(['token' => $newToken], 'Token refreshed successfully', 200);
    }
}
