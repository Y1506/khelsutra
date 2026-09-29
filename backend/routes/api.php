<?php

use App\Helpers\ApiResponse;
use App\Services\Tenancy\TenantContextService;

/**
 * KhelSutra API Route Definitions
 * Version: v1
 */

return function ($uri, $method, $requestData = []) {
    // 1. Health Check
    if ($uri === '/api/v1/health' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\HealthController();
        return $controller->check();
    }

    // 2. Authentication Foundation
    if ($uri === '/api/v1/auth/login' && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Auth\AuthController();
        return $controller->login($requestData);
    }
    if ($uri === '/api/v1/auth/logout' && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Auth\AuthController();
        return $controller->logout();
    }

    $authHeader = $requestData['headers']['authorization'] ?? '';
    $token = trim(str_ireplace('Bearer ', '', $authHeader));
    $authService = new \App\Services\Auth\AuthService();
    $tokenUser = !empty($token) ? $authService->resolveUserByToken($token) : null;

    if ($uri === '/api/v1/auth/me' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Auth\AuthController();
        if (!$tokenUser) {
            return ApiResponse::error('Unauthenticated: Valid Bearer token required.', null, 401);
        }
        return $controller->me($tokenUser);
    }
    if ($uri === '/api/v1/auth/refresh' && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Auth\AuthController();
        return $controller->refresh();
    }

    if (!$tokenUser && !isset($requestData['user'])) {
        // Only strictly reject if it's an actual API request outside of simulation
        if (isset($requestData['headers']['authorization'])) {
            return ApiResponse::error('Unauthorized', null, 401);
        }
    }

    // 3. Resolve Current User Context & Tenant Isolation
    $currentUser = $tokenUser ?? ($requestData['user'] ?? [
        'id' => 5,
        'first_name' => 'Demo',
        'last_name' => 'Admin',
        'email' => 'admin@apexsports.com',
        'status' => 'active',
        'role' => ['id' => 2, 'name' => 'Sports Administrator', 'slug' => 'sports_admin'],
        'role_id' => 2,
        'organization' => ['id' => 1, 'name' => 'Apex Sports Academy', 'organization_code' => 'ORG-DEMO']
    ]);

    $currentRoleId = (int)($currentUser['role']['id'] ?? ($currentUser['role_id'] ?? 2));
    $isSuperAdmin = ($currentRoleId === 1) || (($currentUser['role']['name'] ?? '') === 'Super Admin');

    // Tenant Resolution & Cross-Tenant Rejection
    $requestedOrgId = isset($requestData['headers']['x-organization-id'])
        ? (int)$requestData['headers']['x-organization-id']
        : (isset($requestData['organization_id']) ? (int)$requestData['organization_id'] : null);

    $userOrgId = isset($currentUser['organization']['id']) ? (int)$currentUser['organization']['id'] : 1;

    if (!$isSuperAdmin && $requestedOrgId !== null && $requestedOrgId !== $userOrgId) {
        return ApiResponse::error('Access denied: Unauthorized cross-tenant access.', null, 403);
    }

    $orgId = $isSuperAdmin ? ($requestedOrgId ?: 1) : $userOrgId;
    $performedBy = (int)($currentUser['id'] ?? 1);
    $userId = $performedBy;

    // 4. Multi-Tenant Organizations Management (Super Admin Platform Level Only)
    if (str_starts_with($uri, '/api/v1/organizations')) {
        if (!$isSuperAdmin) {
            return ApiResponse::error('Forbidden: Super Admin access required.', null, 403);
        }
        if ($uri === '/api/v1/organizations') {
            $controller = new \App\Http\Controllers\Api\V1\Organizations\OrganizationController();
            if ($method === 'GET') return $controller->index($requestData);
            if ($method === 'POST') return $controller->store($requestData, $performedBy);
        }
        if (preg_match('#^/api/v1/organizations/(\d+)$#', $uri, $m)) {
            $controller = new \App\Http\Controllers\Api\V1\Organizations\OrganizationController();
            $targetId = (int)$m[1];
            if ($method === 'GET') return $controller->show($targetId);
            if ($method === 'PUT' || $method === 'PATCH') return $controller->update($targetId, $requestData, $performedBy);
        }
        if (preg_match('#^/api/v1/organizations/(\d+)/status$#', $uri, $m) && ($method === 'PATCH' || $method === 'POST')) {
            $controller = new \App\Http\Controllers\Api\V1\Organizations\OrganizationController();
            return $controller->updateStatus((int)$m[1], $requestData, $performedBy);
        }
        if (preg_match('#^/api/v1/organizations/(\d+)/access-logs$#', $uri, $m) && $method === 'GET') {
            $controller = new \App\Http\Controllers\Api\V1\Organizations\OrganizationController();
            return $controller->accessLogs((int)$m[1]);
        }
        if (preg_match('#^/api/v1/organizations/(\d+)/settings$#', $uri, $m)) {
            $controller = new \App\Http\Controllers\Api\V1\Organizations\OrganizationController();
            $targetId = (int)$m[1];
            if ($method === 'GET') return $controller->getSettings($targetId);
            if ($method === 'POST' || $method === 'PUT') return $controller->updateSettings($targetId, $requestData, $performedBy);
        }
    }

    // 5. User Management & RBAC
    if ($uri === '/api/v1/users') {
        $controller = new \App\Http\Controllers\Api\V1\Users\UserController();
        if ($method === 'GET') return $controller->index($orgId, $requestData);
        if ($method === 'POST') return $controller->store($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/users/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Users\UserController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->show($orgId, $targetId);
        if ($method === 'PUT' || $method === 'PATCH') return $controller->update($orgId, $targetId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/users/(\d+)/status$#', $uri, $m) && ($method === 'PATCH' || $method === 'POST')) {
        $controller = new \App\Http\Controllers\Api\V1\Users\UserController();
        return $controller->setStatus($orgId, (int)$m[1], $requestData, $performedBy);
    }

    if ($uri === '/api/v1/roles' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\V1\Rbac\RbacController())->roles();
    }
    if ($uri === '/api/v1/permissions' && $method === 'GET') {
        return (new \App\Http\Controllers\Api\V1\Rbac\RbacController())->permissions();
    }
    if (preg_match('#^/api/v1/roles/(\d+)/permissions$#', $uri, $m) && $method === 'GET') {
        return (new \App\Http\Controllers\Api\V1\Rbac\RbacController())->rolePermissions((int)$m[1]);
    }
    if ($uri === '/api/v1/permissions/override' && $method === 'POST') {
        if (!$isSuperAdmin && $currentRoleId > 2) {
            return ApiResponse::error('Forbidden: Insufficient privileges to modify permissions.', null, 403);
        }
        return (new \App\Http\Controllers\Api\V1\Rbac\RbacController())->setOverride($orgId, $requestData, $performedBy);
    }

    // 6. Departments & Employee Categories
    if ($uri === '/api/v1/departments') {
        $controller = new \App\Http\Controllers\Api\V1\Staff\DepartmentController();
        if ($method === 'GET') return $controller->index($orgId);
        if ($method === 'POST') return $controller->store($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/departments/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Staff\DepartmentController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->show($orgId, $targetId);
        if ($method === 'PUT' || $method === 'PATCH') return $controller->update($orgId, $targetId, $requestData, $performedBy);
    }

    if ($uri === '/api/v1/employee-categories') {
        $controller = new \App\Http\Controllers\Api\V1\Staff\EmployeeCategoryController();
        if ($method === 'GET') return $controller->index($orgId);
        if ($method === 'POST') return $controller->store($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/employee-categories/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Staff\EmployeeCategoryController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->show($orgId, $targetId);
        if ($method === 'PUT' || $method === 'PATCH') return $controller->update($orgId, $targetId, $requestData, $performedBy);
    }

    // 7. Employees & Documents
    if ($uri === '/api/v1/employees') {
        $controller = new \App\Http\Controllers\Api\V1\Staff\EmployeeController();
        if ($method === 'GET') return $controller->index($orgId, $requestData);
        if ($method === 'POST') return $controller->store($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/employees/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Staff\EmployeeController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->show($orgId, $targetId);
        if ($method === 'PUT' || $method === 'PATCH') return $controller->update($orgId, $targetId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/employees/(\d+)/documents$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Staff\EmployeeDocumentController();
        $empId = (int)$m[1];
        if ($method === 'GET') return $controller->index($orgId, $empId);
        if ($method === 'POST') return $controller->store($orgId, $empId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/employees/(\d+)/coach-profile$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Coaches\CoachProfileController();
        $empId = (int)$m[1];
        if ($method === 'GET') return $controller->show($orgId, $empId);
        if ($method === 'POST' || $method === 'PUT') return $controller->storeOrUpdate($orgId, $empId, $requestData, $performedBy);
    }

    // 8. Attendance (Training & Match)
    if (preg_match('#^/api/v1/attendance/training/(\d+)$#', $uri, $m) && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Attendance\AttendanceController();
        return $controller->recordTraining($orgId, (int)$m[1], $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/attendance/matches/(\d+)$#', $uri, $m) && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Attendance\AttendanceController();
        return $controller->recordMatch($orgId, (int)$m[1], $requestData, $performedBy);
    }
    if ($uri === '/api/v1/attendance/training/history' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Attendance\AttendanceController();
        return $controller->trainingHistory($orgId, $requestData);
    }

    // 9. Leave Management
    if ($uri === '/api/v1/leave/types') {
        $controller = new \App\Http\Controllers\Api\V1\Leave\LeaveController();
        if ($method === 'GET') return $controller->types($orgId);
        if ($method === 'POST') return $controller->storeType($orgId, $requestData, $performedBy);
    }
    if ($uri === '/api/v1/leave/requests') {
        $controller = new \App\Http\Controllers\Api\V1\Leave\LeaveController();
        if ($method === 'GET') return $controller->index($orgId, $requestData);
        if ($method === 'POST') return $controller->store($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/leave/requests/(\d+)$#', $uri, $m) && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Leave\LeaveController();
        return $controller->show($orgId, (int)$m[1]);
    }
    if (preg_match('#^/api/v1/leave/requests/(\d+)/review$#', $uri, $m) && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Leave\LeaveController();
        return $controller->review($orgId, (int)$m[1], $requestData, $performedBy);
    }

    // 10. Payroll & Salary Structures
    if (preg_match('#^/api/v1/payroll/salary-structures/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Payroll\PayrollController();
        $empId = (int)$m[1];
        if ($method === 'GET') return $controller->getSalaryStructure($orgId, $empId);
        if ($method === 'POST' || $method === 'PUT') return $controller->setSalaryStructure($orgId, $empId, $requestData, $performedBy);
    }
    if ($uri === '/api/v1/payroll/periods') {
        $controller = new \App\Http\Controllers\Api\V1\Payroll\PayrollController();
        if ($method === 'GET') return $controller->periods($orgId);
        if ($method === 'POST') return $controller->storePeriod($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/payroll/periods/(\d+)/status$#', $uri, $m) && ($method === 'PATCH' || $method === 'POST')) {
        $controller = new \App\Http\Controllers\Api\V1\Payroll\PayrollController();
        return $controller->updatePeriodStatus($orgId, (int)$m[1], $requestData, $performedBy);
    }
    if ($uri === '/api/v1/payroll/records' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Payroll\PayrollController();
        return $controller->records($orgId, $requestData);
    }
    if (preg_match('#^/api/v1/payroll/periods/(\d+)/employees/(\d+)/process$#', $uri, $m) && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Payroll\PayrollController();
        return $controller->process($orgId, (int)$m[1], (int)$m[2], $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/payroll/records/(\d+)/payment$#', $uri, $m) && ($method === 'PATCH' || $method === 'POST')) {
        $controller = new \App\Http\Controllers\Api\V1\Payroll\PayrollController();
        return $controller->updatePayment($orgId, (int)$m[1], $requestData, $performedBy);
    }

    // 11. Audit Logs
    if ($uri === '/api/v1/audit-logs' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Audit\AuditLogController();
        return $controller->index($orgId, $requestData);
    }

    // 12. Athletes, Teams, Tournaments, Venues (delegated foundations)
    if ($uri === '/api/v1/athletes' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Athletes\AthleteController();
        $page = (int)($requestData['page'] ?? 1);
        return $controller->index($orgId, $page);
    }
    if ($uri === '/api/v1/athletes' && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Athletes\AthleteController();
        return $controller->store($orgId, $requestData);
    }
    if (preg_match('#^/api/v1/athletes/(\d+)$#', $uri, $matches)) {
        $controller = new \App\Http\Controllers\Api\V1\Athletes\AthleteController();
        $id = (int)$matches[1];
        if ($method === 'GET') return $controller->show($orgId, $id);
        if ($method === 'PUT' || $method === 'PATCH') return $controller->update($orgId, $id, $requestData);
        if ($method === 'DELETE') return $controller->destroy($orgId, $id);
    }

    if ($uri === '/api/v1/teams' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Teams\TeamController();
        $page = (int)($requestData['page'] ?? 1);
        return $controller->index($orgId, $page);
    }
    if ($uri === '/api/v1/teams' && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Teams\TeamController();
        return $controller->store($orgId, $requestData);
    }

    if ($uri === '/api/v1/tournaments' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Tournaments\TournamentController();
        $page = (int)($requestData['page'] ?? 1);
        return $controller->index($orgId, $page);
    }
    if ($uri === '/api/v1/tournaments' && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Tournaments\TournamentController();
        return $controller->store($orgId, $requestData);
    }

    if ($uri === '/api/v1/venues' && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_venues')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\VenueController();
        return $controller->index($orgId, $requestData);
    }
    if ($uri === '/api/v1/venues' && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'create_venue')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\VenueController();
        return $controller->store($orgId, $requestData);
    }
    if (preg_match('#^/api/v1/venues/(\d+)$#', $uri, $matches)) {
        $id = (int)$matches[1];
        $controller = new \App\Http\Controllers\Api\V1\Operations\VenueController();
        if ($method === 'GET') {
            if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_venues')) return ApiResponse::error('Forbidden', null, 403);
            return $controller->show($orgId, $id);
        }
        if ($method === 'PUT' || $method === 'PATCH') {
            if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'update_venue')) return ApiResponse::error('Forbidden', null, 403);
            return $controller->update($orgId, $id, $requestData);
        }
        if ($method === 'DELETE') {
            if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_venue')) return ApiResponse::error('Forbidden', null, 403);
            return $controller->destroy($orgId, $id);
        }
    }
    if (preg_match('#^/api/v1/venues/(\d+)/facilities$#', $uri, $matches)) {
        $venueId = (int)$matches[1];
        $controller = new \App\Http\Controllers\Api\V1\Operations\FacilityController();
        if ($method === 'GET') {
            if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_venues')) return ApiResponse::error('Forbidden', null, 403);
            return $controller->index($orgId, $venueId, $requestData);
        }
        if ($method === 'POST') {
            if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'update_venue')) return ApiResponse::error('Forbidden', null, 403);
            return $controller->store($orgId, $venueId, $requestData);
        }
    }
    if (preg_match('#^/api/v1/venues/(\d+)/facilities/(\d+)$#', $uri, $matches)) {
        $venueId = (int)$matches[1];
        $id = (int)$matches[2];
        $controller = new \App\Http\Controllers\Api\V1\Operations\FacilityController();
        if ($method === 'GET') {
            if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_venues')) return ApiResponse::error('Forbidden', null, 403);
            return $controller->show($orgId, $venueId, $id);
        }
        if ($method === 'PUT' || $method === 'PATCH') {
            if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'update_venue')) return ApiResponse::error('Forbidden', null, 403);
            return $controller->update($orgId, $venueId, $id, $requestData);
        }
        if ($method === 'DELETE') {
            if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_venue')) return ApiResponse::error('Forbidden', null, 403);
            return $controller->destroy($orgId, $venueId, $id);
        }
    }

    
    // Member 4: Operations Bookings
    if ($uri === '/api/v1/bookings' && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_venues')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\VenueBookingController();
        return $controller->index($orgId, $requestData);
    }
    if ($uri === '/api/v1/bookings' && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'create_booking')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\VenueBookingController();
        return $controller->store($orgId, $requestData);
    }
    if (preg_match('#^/api/v1/bookings/(\d+)/cancel$#', $uri, $matches) && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_booking')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\VenueBookingController();
        return $controller->cancel($orgId, (int)$matches[1], $requestData);
    }
    if (preg_match('#^/api/v1/venues/(\d+)/availability$#', $uri, $matches) && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_venues')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\VenueBookingController();
        return $controller->availability($orgId, (int)$matches[1], $requestData);
    }
    if (preg_match('#^/api/v1/facilities/(\d+)/availability$#', $uri, $matches) && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_venues')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\VenueBookingController();
        return $controller->facilityAvailability($orgId, (int)$matches[1], $requestData);
    }

    
    // Member 4: Operations Maintenance & Housekeeping
    if ($uri === '/api/v1/maintenance' && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_maintenance')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\MaintenanceController();
        return $controller->index($orgId, $requestData);
    }
    if ($uri === '/api/v1/maintenance' && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_maintenance')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\MaintenanceController();
        return $controller->store($orgId, $requestData);
    }
    if (preg_match('#^/api/v1/maintenance/(\d+)$#', $uri, $matches) && ($method === 'PUT' || $method === 'PATCH')) {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_maintenance')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\MaintenanceController();
        return $controller->update($orgId, (int)$matches[1], $requestData);
    }
    if ($uri === '/api/v1/housekeeping' && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_housekeeping')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\HousekeepingTaskController();
        return $controller->index($orgId, $requestData);
    }
    if ($uri === '/api/v1/housekeeping' && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_housekeeping')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\HousekeepingTaskController();
        return $controller->store($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/housekeeping/(\d+)$#', $uri, $matches)) {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_housekeeping')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\HousekeepingTaskController();
        if ($method === 'GET') return $controller->show($orgId, (int)$matches[1]);
        if ($method === 'DELETE') return $controller->destroy($orgId, (int)$matches[1], $performedBy);
    }
    if (preg_match('#^/api/v1/housekeeping/(\d+)/assign$#', $uri, $matches) && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_housekeeping')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\HousekeepingTaskController();
        return $controller->assign($orgId, (int)$matches[1], $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/housekeeping/(\d+)/(start|complete|verify|reopen|cancel|close)$#', $uri, $matches) && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_housekeeping')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\HousekeepingTaskController();
        $action = $matches[2];
        $statusMap = [
            'start' => 'in_progress',
            'complete' => 'completed',
            'verify' => 'verified',
            'reopen' => 'reopened',
            'cancel' => 'cancelled',
            'close' => 'closed'
        ];
        return $controller->updateStatus($orgId, (int)$matches[1], $statusMap[$action], $requestData, $performedBy);
    }

    // Member 4: Operations Housekeeping Schedules
    if ($uri === '/api/v1/housekeeping/schedules' && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_housekeeping')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\HousekeepingScheduleController();
        return $controller->index($orgId, $requestData);
    }
    if ($uri === '/api/v1/housekeeping/schedules' && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_housekeeping')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\HousekeepingScheduleController();
        return $controller->store($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/housekeeping/schedules/(\d+)$#', $uri, $matches)) {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_housekeeping')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\HousekeepingScheduleController();
        if ($method === 'GET') return $controller->show($orgId, (int)$matches[1]);
        if ($method === 'PUT' || $method === 'PATCH') return $controller->update($orgId, (int)$matches[1], $requestData, $performedBy);
        if ($method === 'DELETE') return $controller->destroy($orgId, (int)$matches[1], $performedBy);
    }
    if ($uri === '/api/v1/housekeeping/schedules/generate' && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_housekeeping')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\HousekeepingScheduleController();
        return $controller->generateTasks($orgId, $requestData, $performedBy);
    }

    // Member 4: Operations Events & School Activities
    if ($uri === '/api/v1/events' && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_events')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\EventController(new \App\Services\Operations\EventService());
        return $controller->index($orgId, $requestData);
    }
    if ($uri === '/api/v1/events' && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_events')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\EventController(new \App\Services\Operations\EventService());
        return $controller->store($orgId, $requestData);
    }
    if (preg_match('#^/api/v1/events/(\d+)/participants$#', $uri, $matches) && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_events')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\EventController(new \App\Services\Operations\EventService());
        return $controller->addParticipant($orgId, (int)$matches[1], $requestData);
    }
    if (preg_match('#^/api/v1/events/(\d+)/expenses$#', $uri, $matches) && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_events')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\EventController(new \App\Services\Operations\EventService());
        return $controller->recordExpense($orgId, (int)$matches[1], $requestData);
    }
    if ($uri === '/api/v1/school-activities' && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_events')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\SchoolActivityController(new \App\Services\Operations\EventService());
        return $controller->index($orgId, $requestData);
    }
    if ($uri === '/api/v1/school-activities' && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_events')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\SchoolActivityController(new \App\Services\Operations\EventService());
        return $controller->store($orgId, $requestData);
    }

    // Member 4: Operations Transport
    if ($uri === '/api/v1/vehicles' && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_transport')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\VehicleController(new \App\Services\Operations\VehicleService());
        return $controller->index($orgId, $requestData);
    }
    if ($uri === '/api/v1/vehicles' && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_transport')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\VehicleController(new \App\Services\Operations\VehicleService());
        return $controller->store($orgId, $requestData);
    }
    if (preg_match('#^/api/v1/vehicles/(\d+)/location$#', $uri, $matches) && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_transport')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\VehicleController(new \App\Services\Operations\VehicleService());
        return $controller->location($orgId, (int)$matches[1]);
    }
    if ($uri === '/api/v1/tracking/position' && $method === 'POST') {
        // Assume devices send a bearer token matching an org's api key, for now just reuse manage_transport
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_transport')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\VehicleTrackingController(new \App\Services\Operations\VehicleTrackingService());
        return $controller->storePosition($orgId, $requestData);
    }
    if ($uri === '/api/v1/trips' && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_transport')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\TransportTripController(new \App\Services\Operations\TransportTripService());
        return $controller->index($orgId, $requestData);
    }
    if ($uri === '/api/v1/trips' && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_transport')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\TransportTripController(new \App\Services\Operations\TransportTripService());
        return $controller->store($orgId, $requestData);
    }
    if ($uri === '/api/v1/trips/auto-plan' && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_transport')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\TransportTripController(new \App\Services\Operations\TransportTripService());
        return $controller->autoPlan($orgId, $requestData);
    }
    if (preg_match('#^/api/v1/trips/(\d+)$#', $uri, $matches) && ($method === 'PUT' || $method === 'PATCH')) {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_transport')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\TransportTripController(new \App\Services\Operations\TransportTripService());
        return $controller->update($orgId, (int)$matches[1], $requestData);
    }
    if (preg_match('#^/api/v1/trips/(\d+)/route-history$#', $uri, $matches) && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_transport')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\TransportTripController(new \App\Services\Operations\TransportTripService());
        return $controller->routeHistory($orgId, (int)$matches[1]);
    }
    if (preg_match('#^/api/v1/trips/(\d+)/passengers$#', $uri, $matches) && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_transport')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\TransportTripController(new \App\Services\Operations\TransportTripService());
        return $controller->addPassenger($orgId, (int)$matches[1], $requestData);
    }

    // Member 4: Operations Accommodation
    if ($uri === '/api/v1/accommodations' && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_accommodation')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\AccommodationController(new \App\Services\Operations\AccommodationService());
        return $controller->index($orgId, $requestData);
    }
    if ($uri === '/api/v1/accommodations' && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_accommodation')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\AccommodationController(new \App\Services\Operations\AccommodationService());
        return $controller->store($orgId, $requestData);
    }

    if (preg_match('#^/api/v1/accommodations/(\d+)/rooms$#', $uri, $matches) && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_accommodation')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\AccommodationController(new \App\Services\Operations\AccommodationService());
        return $controller->getRooms($orgId, (int)$matches[1]);
    }
    if (preg_match('#^/api/v1/accommodations/(\d+)/rooms$#', $uri, $matches) && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_accommodation')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\AccommodationController(new \App\Services\Operations\AccommodationService());
        return $controller->storeRoom($orgId, (int)$matches[1], $requestData);
    }
    if ($uri === '/api/v1/room-allocations' && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_accommodation')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\RoomAllocationController(new \App\Services\Operations\RoomAllocationService());
        return $controller->index($orgId, $requestData);
    }
    if ($uri === '/api/v1/room-allocations' && $method === 'POST') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'manage_accommodation')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\RoomAllocationController(new \App\Services\Operations\RoomAllocationService());
        return $controller->store($orgId, $requestData);
    }

    if ($uri === '/api/v1/scheduling/clashes' && $method === 'GET') {
        if (!\App\Helpers\OperationsPermissionHelper::hasAny($performedBy, 'view_venue')) return ApiResponse::error('Forbidden', null, 403);
        $controller = new \App\Http\Controllers\Api\V1\Operations\SchedulingController();
        return $controller->getClashes($request); // Assuming it takes request
    }

    // 13. Fallback for other unassigned modules
    // ==========================================
    // Member 5: Inventory Categories
    // ==========================================
    if ($uri === '/api/v1/inventory/categories') {
        $controller = new \App\Http\Controllers\Api\V1\Inventory\InventoryCategoryController();
        if ($method === 'GET') return $controller->index($orgId);
        if ($method === 'POST') return $controller->store($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/inventory/categories/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Inventory\InventoryCategoryController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->show($orgId, $targetId);
        if ($method === 'PUT' || $method === 'POST') return $controller->update($orgId, $targetId, $requestData, $performedBy);
        if ($method === 'DELETE') return $controller->destroy($orgId, $targetId, $performedBy);
    }
    if (preg_match('#^/api/v1/inventory/categories/(\d+)/status$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Inventory\InventoryCategoryController();
        $targetId = (int)$m[1];
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            return $controller->setStatus($orgId, $targetId, $requestData, $performedBy);
        }
    }

    // ==========================================
    // Member 5: Inventory Management
    // ==========================================
    if ($uri === '/api/v1/inventory/items' || $uri === '/api/v1/inventory') {
        $controller = new \App\Http\Controllers\Api\V1\Inventory\InventoryController();
        if ($method === 'GET') return $controller->index($orgId, $requestData);
        if ($method === 'POST') return $controller->store($orgId, $requestData, $performedBy);
    }
    if ($uri === '/api/v1/inventory/categories' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Inventory\InventoryController();
        return $controller->categories($orgId);
    }
    if ($uri === '/api/v1/inventory/low-stock' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Inventory\InventoryController();
        return $controller->lowStock($orgId);
    }
    if (preg_match('#^/api/v1/inventory/(?:items/)?(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Inventory\InventoryController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->show($orgId, $targetId);
        if ($method === 'PUT' || $method === 'POST') return $controller->update($orgId, $targetId, $requestData, $performedBy);
        if ($method === 'DELETE') return $controller->destroy($orgId, $targetId, $performedBy);
    }
    if (preg_match('#^/api/v1/inventory/(?:items/)?(\d+)/transactions$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Inventory\InventoryController();
        $targetId = (int)$m[1];
        if ($method === 'POST') return $controller->recordTransaction($orgId, $targetId, $requestData, $performedBy);
        if ($method === 'GET') return $controller->transactions($orgId, $targetId, $requestData);
    }

    // ==========================================
    // Member 5: Equipment Management
    // ==========================================
    if ($uri === '/api/v1/equipment') {
        $controller = new \App\Http\Controllers\Api\V1\Equipment\EquipmentController();
        if ($method === 'GET') return $controller->index($orgId);
        if ($method === 'POST') return $controller->store($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/equipment/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Equipment\EquipmentController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->show($orgId, $targetId);
        if ($method === 'PUT' || $method === 'POST') return $controller->update($orgId, $targetId, $requestData, $performedBy);
        if ($method === 'DELETE') return $controller->destroy($orgId, $targetId, $performedBy);
    }
    if (preg_match('#^/api/v1/equipment/(\d+)/assign$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Equipment\EquipmentController();
        $targetId = (int)$m[1];
        if ($method === 'POST') return $controller->assign($orgId, $targetId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/equipment/(\d+)/return$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Equipment\EquipmentController();
        $targetId = (int)$m[1];
        if ($method === 'POST') return $controller->returnItem($orgId, $targetId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/equipment/(\d+)/assignments$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Equipment\EquipmentController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->assignments($orgId, $targetId);
    }

    // ==========================================
    // Member 5: Vendor & Vendor Invoice Management
    // ==========================================
    if ($uri === '/api/v1/vendors') {
        $controller = new \App\Http\Controllers\Api\V1\Vendor\VendorController();
        if ($method === 'GET') return $controller->index($orgId);
        if ($method === 'POST') return $controller->store($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/vendors/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Vendor\VendorController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->show($orgId, $targetId);
        if ($method === 'PUT' || $method === 'POST') return $controller->update($orgId, $targetId, $requestData, $performedBy);
        if ($method === 'DELETE') return $controller->destroy($orgId, $targetId, $performedBy);
    }
    if (preg_match('#^/api/v1/vendors/(\d+)/status$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Vendor\VendorController();
        $targetId = (int)$m[1];
        if ($method === 'POST') return $controller->setStatus($orgId, $targetId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/vendors/(\d+)/invoices$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Vendor\VendorController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->invoices($orgId, $targetId);
        if ($method === 'POST') return $controller->storeInvoice($orgId, $targetId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/vendor-invoices/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Vendor\VendorController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->showInvoice($orgId, $targetId);
        if ($method === 'PUT' || $method === 'POST') return $controller->updateInvoice($orgId, $targetId, $requestData, $performedBy);
    }

    // ==========================================
    // Member 5: Purchase Requests, Purchase Orders & Goods Receipts
    // ==========================================
    if ($uri === '/api/v1/purchases/requests') {
        $controller = new \App\Http\Controllers\Api\V1\Purchase\PurchaseController();
        if ($method === 'GET') return $controller->requests($orgId, $requestData);
        if ($method === 'POST') return $controller->storeRequest($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/purchases/requests/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Purchase\PurchaseController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->showRequest($orgId, $targetId);
    }
    if (preg_match('#^/api/v1/purchases/requests/(\d+)/submit$#', $uri, $m) && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Purchase\PurchaseController();
        return $controller->submitRequest($orgId, (int)$m[1], $performedBy);
    }
    if (preg_match('#^/api/v1/purchases/requests/(\d+)/approve$#', $uri, $m) && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Purchase\PurchaseController();
        return $controller->approveRequest($orgId, (int)$m[1], $performedBy);
    }
    if (preg_match('#^/api/v1/purchases/requests/(\d+)/reject$#', $uri, $m) && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Purchase\PurchaseController();
        return $controller->rejectRequest($orgId, (int)$m[1], $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/purchases/requests/(\d+)/cancel$#', $uri, $m) && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Purchase\PurchaseController();
        return $controller->cancelRequest($orgId, (int)$m[1], $performedBy);
    }

    if ($uri === '/api/v1/purchases/orders') {
        $controller = new \App\Http\Controllers\Api\V1\Purchase\PurchaseController();
        if ($method === 'GET') return $controller->orders($orgId, $requestData);
        if ($method === 'POST') return $controller->storeOrder($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/purchases/orders/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Purchase\PurchaseController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->showOrder($orgId, $targetId);
    }
    if (preg_match('#^/api/v1/purchases/orders/(\d+)/status$#', $uri, $m) && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Purchase\PurchaseController();
        return $controller->updateOrderStatus($orgId, (int)$m[1], $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/purchases/orders/(\d+)/receive$#', $uri, $m) && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Purchase\PurchaseController();
        return $controller->receiveGoods($orgId, (int)$m[1], $requestData, $performedBy);
    }

    if ($uri === '/api/v1/purchases/receipts') {
        $controller = new \App\Http\Controllers\Api\V1\Purchase\PurchaseController();
        if ($method === 'GET') return $controller->receipts($orgId, $requestData);
    }
    if (preg_match('#^/api/v1/purchases/receipts/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Purchase\PurchaseController();
        if ($method === 'GET') return $controller->showReceipt($orgId, (int)$m[1]);
    }

    // ==========================================
    // Member 5: Financial Management
    // ==========================================
    if ($uri === '/api/v1/finance/summary' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Finance\FinanceController();
        return $controller->summary($orgId, $requestData);
    }
    if ($uri === '/api/v1/finance/category-summary' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Finance\FinanceController();
        return $controller->categorySummary($orgId, $requestData);
    }
    if ($uri === '/api/v1/finance/categories') {
        $controller = new \App\Http\Controllers\Api\V1\Finance\FinanceController();
        if ($method === 'GET') return $controller->indexCategories($orgId, $requestData);
        if ($method === 'POST') return $controller->storeCategory($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/finance/categories/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Finance\FinanceController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->showCategory($orgId, $targetId);
    }
    if ($uri === '/api/v1/finance/income') {
        $controller = new \App\Http\Controllers\Api\V1\Finance\FinanceController();
        if ($method === 'GET') return $controller->indexIncome($orgId, $requestData);
        if ($method === 'POST') return $controller->storeIncome($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/finance/income/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Finance\FinanceController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->showIncome($orgId, $targetId);
    }
    if ($uri === '/api/v1/finance/expenses') {
        $controller = new \App\Http\Controllers\Api\V1\Finance\FinanceController();
        if ($method === 'GET') return $controller->indexExpenses($orgId, $requestData);
        if ($method === 'POST') return $controller->storeExpense($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/finance/expenses/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Finance\FinanceController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->showExpense($orgId, $targetId);
    }
    if (preg_match('#^/api/v1/finance/expenses/(\d+)/approve$#', $uri, $m) && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Finance\FinanceController();
        return $controller->approveExpense($orgId, (int)$m[1], $performedBy);
    }
    if (preg_match('#^/api/v1/finance/expenses/(\d+)/reject$#', $uri, $m) && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Finance\FinanceController();
        return $controller->rejectExpense($orgId, (int)$m[1], $requestData, $performedBy);
    }
    if ($uri === '/api/v1/finance/budgets') {
        $controller = new \App\Http\Controllers\Api\V1\Finance\FinanceController();
        if ($method === 'GET') return $controller->indexBudgets($orgId, $requestData);
        if ($method === 'POST') return $controller->storeBudget($orgId, $requestData, $performedBy);
    }
    if (preg_match('#^/api/v1/finance/budgets/(\d+)$#', $uri, $m)) {
        $controller = new \App\Http\Controllers\Api\V1\Finance\FinanceController();
        $targetId = (int)$m[1];
        if ($method === 'GET') return $controller->showBudget($orgId, $targetId);
        if ($method === 'PUT' || $method === 'POST') return $controller->updateBudget($orgId, $targetId, $requestData, $performedBy);
    }
    if ($uri === '/api/v1/finance/payments') {
        $controller = new \App\Http\Controllers\Api\V1\Finance\FinanceController();
        if ($method === 'GET') return $controller->indexPayments($orgId, $requestData);
        if ($method === 'POST') return $controller->storePayment($orgId, $requestData, $performedBy);
    }

    // ==========================================
    // Member 5: Notification Service
    // ==========================================
    if ($uri === '/api/v1/notifications') {
        $controller = new \App\Http\Controllers\Api\V1\Notification\NotificationController();
        if ($method === 'GET') return $controller->index($orgId, $userId, $requestData);
        if ($method === 'POST') return $controller->store($orgId, $requestData, $userId);
    }
    if ($uri === '/api/v1/notifications/unread-count' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Notification\NotificationController();
        return $controller->unreadCount($orgId, $userId);
    }
    if (preg_match('#^/api/v1/notifications/(\d+)/read$#', $uri, $m) && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Notification\NotificationController();
        return $controller->markAsRead($orgId, (int)$m[1], $userId);
    }
    if ($uri === '/api/v1/notifications/read-all' && $method === 'POST') {
        $controller = new \App\Http\Controllers\Api\V1\Notification\NotificationController();
        return $controller->markAllAsRead($orgId, $userId);
    }
    if (preg_match('#^/api/v1/notifications/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $controller = new \App\Http\Controllers\Api\V1\Notification\NotificationController();
        return $controller->destroy($orgId, (int)$m[1], $userId);
    }

    // ==========================================
    // Reports & Analytics APIs
    // ==========================================
    if ($uri === '/api/v1/reports/operational' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Reports\ReportController();
        return $controller->operational($orgId);
    }
    if ($uri === '/api/v1/reports/inventory' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Reports\ReportController();
        return $controller->inventory($orgId, $requestData);
    }
    if ($uri === '/api/v1/reports/purchases' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Reports\ReportController();
        return $controller->purchases($orgId, $requestData);
    }
    if ($uri === '/api/v1/reports/vendors' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Reports\ReportController();
        return $controller->vendors($orgId, $requestData);
    }
    if ($uri === '/api/v1/reports/equipment' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Reports\ReportController();
        return $controller->equipment($orgId, $requestData);
    }
    if ($uri === '/api/v1/reports/finance' && $method === 'GET') {
        $controller = new \App\Http\Controllers\Api\V1\Reports\ReportController();
        return $controller->finance($orgId, $requestData);
    }

    // 14. Fallback for other unassigned modules (Preserving all other members' skeleton groups)
    $skeletonGroups = [
        'sports', 'coaches', 'performance', 'medical', 'fixtures', 'matches',
        'bookings', 'maintenance', 'housekeeping',
        'events', 'school-activities', 'transport',
        'accommodation'
    ];

    foreach ($skeletonGroups as $group) {
        if (str_starts_with($uri, "/api/v1/{$group}")) {
            return ApiResponse::success([
                'module' => $group,
                'action' => $method,
                'status' => 'ready',
                'description' => "API group /api/v1/{$group} integrated with Member 1 auth and tenant context."
            ], "Module [{$group}] endpoint acknowledged", 200);
        }
    }

    return ApiResponse::error('Endpoint not found', ['uri' => $uri, 'method' => $method], 404);
};
