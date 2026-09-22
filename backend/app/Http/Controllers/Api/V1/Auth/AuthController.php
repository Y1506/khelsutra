<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use App\Http\Requests\Auth\LoginRequest;
use App\Helpers\ApiResponse;

/**
 * Handles authentication operations including login, logout, and token management.
 */
class AuthController extends Controller
{
    protected AuthService $authService;

    /**
     * Create a new AuthController instance.
     *
     * @param AuthService|null $authService The authentication service instance
     */
    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    /**
     * Authenticate a user and generate a session token.
     *
     * Validates credentials, verifies organization membership, and returns
     * user session data with permissions and an HMAC-signed token.
     *
     * @param array $requestData The login request data containing email, password, and optional organization_code
     * @return array API response with session data or error
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
     * Retrieve the authenticated user's profile.
     *
     * @param array $currentUser The current authenticated user data
     * @return array API response with user profile data
     */
    public function me(array $currentUser): array
    {
        return ApiResponse::success($currentUser, 'User profile retrieved', 200);
    }

    /**
     * Refresh the authentication token.
     *
     * Generates a new random token for the authenticated session.
     *
     * @return array API response with new token
     */
    public function refresh(): array
    {
        $newToken = bin2hex(random_bytes(32));
        return ApiResponse::success(['token' => $newToken], 'Token refreshed successfully', 200);
    }
}
