<?php

/**
 * KhelSutra Web Form Action Dispatcher
 * Handles full-page web form submissions with CSRF, validation,
 * service execution, audit logging, and clean feedback redirects.
 */

if ($method !== 'POST') {
    return;
}

$currentUser = $_SESSION['auth']['user'] ?? [
    'id' => 5,
    'email' => 'sportsadmin@khelsutra.local',
    'first_name' => 'Rajesh',
    'last_name' => 'Sharma'
];
$orgId = current_organization_id();
$userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

// ==========================================
// 1. ATHLETE ACTIONS
// ==========================================
if ($uri === '/athletes/create') {
    $validator = new \App\Http\Requests\Athletes\StoreAthleteRequest($_POST, $_FILES);
    $errors = $validator->validate();
    if (!empty($errors)) {
        $firstErr = reset($errors)[0];
        header('Location: /athletes/create?error=' . urlencode($firstErr));
        exit;
    }

    $athleteService = new \App\Services\Athlete\AthleteService();
    try {
        $created = $athleteService->registerAthlete($orgId, $_POST, $userId, $_FILES);
        $newId = $created['id'] ?? null;

        // Fetch sport and team names for success summary
        $sportName = 'General Sports';
        if (!empty($created['current_sport_id'])) {
            $db = \App\Services\BaseService::getDatabaseConnection();
            $sStmt = $db->prepare("SELECT name FROM sports WHERE id = :id");
            $sStmt->execute([':id' => $created['current_sport_id']]);
            $sportName = $sStmt->fetchColumn() ?: 'General Sports';
        }
        $teamName = 'No Team Assigned';
        if (!empty($_POST['team_id'])) {
            $db = \App\Services\BaseService::getDatabaseConnection();
            $tStmt = $db->prepare("SELECT name FROM teams WHERE id = :id");
            $tStmt->execute([':id' => (int)$_POST['team_id']]);
            $teamName = $tStmt->fetchColumn() ?: 'No Team Assigned';
        }

        $_SESSION['athlete_created_success'] = [
            'id' => $newId,
            'name' => trim(($created['first_name'] ?? '') . ' ' . ($created['last_name'] ?? '')),
            'athlete_code' => $created['athlete_code'] ?? '',
            'sport_name' => $sportName,
            'team_name' => $teamName,
            'has_account' => !empty($created['created_user']),
            'login_email' => $created['login_email'] ?? null,
            'login_username' => $created['login_username'] ?? null,
            'temp_password' => $created['temp_password'] ?? null,
            'account_status' => $created['account_status'] ?? 'Active',
            'uploaded_docs_count' => $created['uploaded_docs_count'] ?? 0,
        ];

        header('Location: /athletes/create?created=1&id=' . (int)$newId);
        exit;
    } catch (\Throwable $e) {
        header('Location: /athletes/create?error=' . urlencode('Unable to create athlete: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/athletes/(\d+)/documents/upload$#', $uri, $m)) {
    $athleteId = (int)$m[1];
    $athleteService = new \App\Services\Athlete\AthleteService();
    try {
        if (empty($_FILES['document_file']) || ($_FILES['document_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Please select a valid document file to upload.');
        }
        $athleteService->getDocumentService()->addDocument($orgId, $athleteId, $_POST, $_FILES['document_file'], $userId);
        header('Location: /athletes/' . $athleteId . '?success=' . urlencode('Document uploaded successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /athletes/' . $athleteId . '?error=' . urlencode('Failed to upload document: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/athletes/(\d+)/documents/(\d+)/delete$#', $uri, $m)) {
    $athleteId = (int)$m[1];
    $docId = (int)$m[2];
    $athleteService = new \App\Services\Athlete\AthleteService();
    try {
        $athleteService->getDocumentService()->deleteDocument($orgId, $athleteId, $docId, $userId);
        header('Location: /athletes/' . $athleteId . '?success=' . urlencode('Document removed successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /athletes/' . $athleteId . '?error=' . urlencode('Failed to delete document: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/athletes/(\d+)/account/create$#', $uri, $m)) {
    $athleteId = (int)$m[1];
    $athleteService = new \App\Services\Athlete\AthleteService();
    try {
        $acc = $athleteService->createAthleteAccount($orgId, $athleteId, $_POST, $userId);
        $_SESSION['athlete_account_provisioned'] = $acc;
        header('Location: /athletes/' . $athleteId . '?success=' . urlencode('Login account provisioned successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /athletes/' . $athleteId . '?error=' . urlencode('Unable to create account: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/athletes/(\d+)/account/reset-password$#', $uri, $m)) {
    $athleteId = (int)$m[1];
    $athleteService = new \App\Services\Athlete\AthleteService();
    try {
        $newPwd = $athleteService->resetAthletePassword($orgId, $athleteId, $_POST['password'] ?? null, $userId);
        $_SESSION['athlete_password_reset'] = [
            'athlete_id' => $athleteId,
            'temp_password' => $newPwd,
        ];
        header('Location: /athletes/' . $athleteId . '?success=' . urlencode('Password reset successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /athletes/' . $athleteId . '?error=' . urlencode('Unable to reset password: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/athletes/(\d+)/edit$#', $uri, $m)) {
    $athleteId = (int)$m[1];
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $dob = trim($_POST['date_of_birth'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $sportId = (int)($_POST['current_sport_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if (empty($firstName) || empty($lastName)) {
        header('Location: /athletes/' . $athleteId . '/edit?error=' . urlencode('First Name and Last Name are required.'));
        exit;
    }
    if (empty($dob)) {
        header('Location: /athletes/' . $athleteId . '/edit?error=' . urlencode('Date of Birth is required.'));
        exit;
    }
    if (empty($gender)) {
        header('Location: /athletes/' . $athleteId . '/edit?error=' . urlencode('Gender is required.'));
        exit;
    }
    if ($sportId <= 0) {
        header('Location: /athletes/' . $athleteId . '/edit?error=' . urlencode('Primary Sport is required.'));
        exit;
    }
    if (empty($status) || !in_array($status, ['active', 'inactive', 'injured', 'suspended'], true)) {
        header('Location: /athletes/' . $athleteId . '/edit?error=' . urlencode('Valid Athlete Status is required.'));
        exit;
    }

    $athleteService = new \App\Services\Athlete\AthleteService();
    try {
        $ok = $athleteService->updateAthlete($orgId, $athleteId, $_POST, $userId);
        if ($ok) {
            header('Location: /athletes/' . $athleteId . '?success=' . urlencode('Athlete details updated successfully.'));
        } else {
            header('Location: /athletes/' . $athleteId . '/edit?error=' . urlencode('Failed to update athlete record.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /athletes/' . $athleteId . '/edit?error=' . urlencode('Unable to update athlete: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/athletes/(\d+)/status$#', $uri, $m)) {
    $athleteId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    unset($_POST['organization_id']);

    $status = trim($_POST['status'] ?? '');
    if (!in_array($status, ['active', 'inactive'], true)) {
        header('Location: /athletes?error=' . urlencode('Invalid status value.'));
        exit;
    }

    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canUpdate = $permissionService->hasPermission($userPayload, 'athlete.update', $orgId)
              || $permissionService->hasPermission($userPayload, 'athlete.edit', $orgId);

    if (!$canUpdate) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canUpdate = in_array('athlete.update', $userPermissions, true)
                  || in_array('athlete.edit', $userPermissions, true);
    }

    if (!$canUpdate) {
        header('Location: /athletes?error=' . urlencode('Unauthorized: You do not have permission to update athlete status.'));
        exit;
    }

    $athleteService = new \App\Services\Athlete\AthleteService();
    try {
        $ok = $athleteService->updateStatus($orgId, $athleteId, $status, $userId);
        if ($ok) {
            header('Location: /athletes?success=' . urlencode('Athlete status updated to ' . ucfirst($status) . '.'));
        } else {
            header('Location: /athletes?error=' . urlencode('Athlete not found or access denied.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /athletes?error=' . urlencode('Unable to update athlete status: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/athletes/(\d+)/delete$#', $uri, $m)) {
    $athleteId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    unset($_POST['organization_id']);

    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canDelete = $permissionService->hasPermission($userPayload, 'athlete.delete', $orgId)
              || $permissionService->hasPermission($userPayload, 'athlete.update', $orgId);

    if (!$canDelete) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canDelete = in_array('athlete.delete', $userPermissions, true)
                  || in_array('athlete.update', $userPermissions, true);
    }

    if (!$canDelete) {
        header('Location: /athletes?error=' . urlencode('Unauthorized: You do not have permission to delete athletes.'));
        exit;
    }

    $athleteService = new \App\Services\Athlete\AthleteService();
    $existing = $athleteService->getAthlete($orgId, $athleteId);
    if (!$existing) {
        header('Location: /athletes?error=' . urlencode('Athlete not found or access denied.'));
        exit;
    }

    try {
        $ok = $athleteService->deleteAthlete($orgId, $athleteId, $userId);
        if ($ok) {
            header('Location: /athletes?success=' . urlencode('Athlete record removed successfully.'));
        } else {
            header('Location: /athletes?error=' . urlencode('Failed to remove athlete.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /athletes?error=' . urlencode('Unable to delete athlete: ' . $e->getMessage()));
        exit;
    }
}

// ==========================================
// 2. COACH ACTIONS
// ==========================================
if ($uri === '/coaches/create') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $dob = trim($_POST['date_of_birth'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if (empty($firstName) || empty($lastName)) {
        header('Location: /coaches/create?error=' . urlencode('First Name and Last Name are required.'));
        exit;
    }
    if (empty($dob)) {
        header('Location: /coaches/create?error=' . urlencode('Date of Birth is required.'));
        exit;
    }
    if (empty($gender)) {
        header('Location: /coaches/create?error=' . urlencode('Gender is required.'));
        exit;
    }
    if (empty($designation)) {
        header('Location: /coaches/create?error=' . urlencode('Designation is required.'));
        exit;
    }
    if (empty($specialization)) {
        header('Location: /coaches/create?error=' . urlencode('Specialization is required.'));
        exit;
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        header('Location: /coaches/create?error=' . urlencode('Valid Coach Status is required.'));
        exit;
    }
    $_POST['status'] = $status;

    $coachService = new \App\Services\Coach\CoachService();
    try {
        $created = $coachService->createCoach($orgId, $_POST, $userId);
        $coachProfileId = $created['coach_profile_id'] ?? null;
        if ($coachProfileId) {
            header('Location: /coaches/' . $coachProfileId . '?success=' . urlencode('Coach registered successfully.'));
        } else {
            header('Location: /coaches?success=' . urlencode('Coach registered successfully.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /coaches/create?error=' . urlencode('Unable to create coach: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/coaches/(\d+)/edit$#', $uri, $m)) {
    $coachId = (int)$m[1];
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $dob = trim($_POST['date_of_birth'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if (empty($firstName) || empty($lastName)) {
        header('Location: /coaches/' . $coachId . '/edit?error=' . urlencode('First Name and Last Name are required.'));
        exit;
    }
    if (empty($dob)) {
        header('Location: /coaches/' . $coachId . '/edit?error=' . urlencode('Date of Birth is required.'));
        exit;
    }
    if (empty($gender)) {
        header('Location: /coaches/' . $coachId . '/edit?error=' . urlencode('Gender is required.'));
        exit;
    }
    if (empty($designation)) {
        header('Location: /coaches/' . $coachId . '/edit?error=' . urlencode('Designation is required.'));
        exit;
    }
    if (empty($specialization)) {
        header('Location: /coaches/' . $coachId . '/edit?error=' . urlencode('Specialization is required.'));
        exit;
    }
    if (empty($status) || !in_array($status, ['active', 'inactive'], true)) {
        header('Location: /coaches/' . $coachId . '/edit?error=' . urlencode('Coach Status is required.'));
        exit;
    }

    $coachService = new \App\Services\Coach\CoachService();
    try {
        $ok = $coachService->updateCoach($orgId, $coachId, $_POST, $userId);
        if ($ok) {
            header('Location: /coaches/' . $coachId . '?success=' . urlencode('Coach details updated successfully.'));
        } else {
            header('Location: /coaches/' . $coachId . '/edit?error=' . urlencode('Failed to update coach record.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /coaches/' . $coachId . '/edit?error=' . urlencode('Unable to update coach: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/coaches/(\d+)/delete$#', $uri, $m)) {
    $coachId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    unset($_POST['organization_id']);

    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canDelete = $permissionService->hasPermission($userPayload, 'coach.manage', $orgId)
              || $permissionService->hasPermission($userPayload, 'coach.update', $orgId);

    if (!$canDelete) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canDelete = in_array('coach.manage', $userPermissions, true)
                  || in_array('coach.update', $userPermissions, true);
    }

    if (!$canDelete) {
        header('Location: /coaches?error=' . urlencode('Unauthorized: You do not have permission to delete coaches.'));
        exit;
    }

    $coachService = new \App\Services\Coach\CoachService();
    $existing = $coachService->getCoach($orgId, $coachId);
    if (!$existing) {
        header('Location: /coaches?error=' . urlencode('Coach not found or access denied.'));
        exit;
    }

    try {
        $ok = $coachService->deleteCoach($orgId, $coachId, $userId);
        if ($ok) {
            header('Location: /coaches?success=' . urlencode('Coach record removed successfully.'));
        } else {
            header('Location: /coaches?error=' . urlencode('Failed to remove coach.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /coaches?error=' . urlencode('Unable to delete coach: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/coaches/(\d+)/status$#', $uri, $m)) {
    $coachId = (int)$m[1];
    $status = trim($_POST['status'] ?? '');
    if (!in_array($status, ['active', 'inactive'], true)) {
        header('Location: /coaches?error=' . urlencode('Invalid status value.'));
        exit;
    }

    // RBAC Authorization Check using existing PermissionService
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canUpdate = $permissionService->hasPermission($userPayload, 'coach.update', $orgId)
              || $permissionService->hasPermission($userPayload, 'coach.manage', $orgId);

    if (!$canUpdate) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canUpdate = in_array('coach.update', $userPermissions, true)
                  || in_array('coach.manage', $userPermissions, true);
    }

    if (!$canUpdate) {
        header('Location: /coaches?error=' . urlencode('Unauthorized: You do not have permission to update coach status.'));
        exit;
    }

    $coachService = new \App\Services\Coach\CoachService();
    try {
        $ok = $coachService->updateStatus($orgId, $coachId, $status, $userId);
        if ($ok) {
            header('Location: /coaches?success=' . urlencode('Coach status updated to ' . ucfirst($status) . '.'));
        } else {
            header('Location: /coaches?error=' . urlencode('Coach not found or access denied.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /coaches?error=' . urlencode('Unable to update coach status: ' . $e->getMessage()));
        exit;
    }
}

// ==========================================
// 3. TEAM ACTIONS
// ==========================================
if ($uri === '/teams/create') {
    $name = trim($_POST['name'] ?? '');
    $sportId = (int)($_POST['sport_id'] ?? 0);

    if (empty($name) || empty($sportId)) {
        header('Location: /teams/create?error=' . urlencode('Team Name and Sport are required.'));
        exit;
    }

    $teamService = new \App\Services\Team\TeamService();
    try {
        $created = $teamService->createTeam($orgId, $_POST, $userId);
        $teamId = $created['id'] ?? null;
        if ($teamId) {
            header('Location: /teams/' . $teamId . '?success=' . urlencode('Team created successfully.'));
        } else {
            header('Location: /teams?success=' . urlencode('Team created successfully.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /teams/create?error=' . urlencode('Unable to create team: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/teams/(\d+)/edit$#', $uri, $m)) {
    $teamId = (int)$m[1];
    $name = trim($_POST['name'] ?? '');

    if (empty($name)) {
        header('Location: /teams/' . $teamId . '/edit?error=' . urlencode('Team Name is required.'));
        exit;
    }

    $teamService = new \App\Services\Team\TeamService();
    try {
        $ok = $teamService->updateTeam($orgId, $teamId, $_POST, $userId);
        if ($ok) {
            header('Location: /teams/' . $teamId . '?success=' . urlencode('Team updated successfully.'));
        } else {
            header('Location: /teams/' . $teamId . '/edit?error=' . urlencode('Failed to update team record.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /teams/' . $teamId . '/edit?error=' . urlencode('Unable to update team: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/teams/(\d+)/status$#', $uri, $m)) {
    $teamId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    unset($_POST['organization_id']);

    $status = trim($_POST['status'] ?? '');
    if (!in_array($status, ['active', 'inactive'], true)) {
        header('Location: /teams?error=' . urlencode('Invalid status value.'));
        exit;
    }

    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canUpdate = $permissionService->hasPermission($userPayload, 'team.update', $orgId)
              || $permissionService->hasPermission($userPayload, 'team.manage', $orgId);

    if (!$canUpdate) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canUpdate = in_array('team.update', $userPermissions, true)
                  || in_array('team.manage', $userPermissions, true);
    }

    if (!$canUpdate) {
        header('Location: /teams?error=' . urlencode('Unauthorized: You do not have permission to update team status.'));
        exit;
    }

    $teamService = new \App\Services\Team\TeamService();
    try {
        $ok = $teamService->updateStatus($orgId, $teamId, $status, $userId);
        if ($ok) {
            header('Location: /teams?success=' . urlencode('Team status updated to ' . ucfirst($status) . '.'));
        } else {
            header('Location: /teams?error=' . urlencode('Team not found or access denied.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /teams?error=' . urlencode('Unable to update team status: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/teams/(\d+)/delete$#', $uri, $m)) {
    $teamId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    unset($_POST['organization_id']);

    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canDelete = $permissionService->hasPermission($userPayload, 'team.manage', $orgId)
              || $permissionService->hasPermission($userPayload, 'team.update', $orgId);

    if (!$canDelete) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canDelete = in_array('team.manage', $userPermissions, true)
                  || in_array('team.update', $userPermissions, true);
    }

    if (!$canDelete) {
        header('Location: /teams?error=' . urlencode('Unauthorized: You do not have permission to delete teams.'));
        exit;
    }

    $teamService = new \App\Services\Team\TeamService();
    $existing = $teamService->getTeam($orgId, $teamId);
    if (!$existing) {
        header('Location: /teams?error=' . urlencode('Team not found or access denied.'));
        exit;
    }

    try {
        $ok = $teamService->deleteTeam($orgId, $teamId, $userId);
        if ($ok) {
            header('Location: /teams?success=' . urlencode('Team record removed successfully.'));
        } else {
            header('Location: /teams?error=' . urlencode('Failed to remove team.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /teams?error=' . urlencode('Unable to delete team: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/teams/(\d+)/roster/add$#', $uri, $m)) {
    $teamId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    // Untrusted organization_id defense
    unset($_POST['organization_id']);

    // RBAC Authorization check using existing PermissionService semantics
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canManageRoster = $permissionService->hasPermission($userPayload, 'team.members.manage', $orgId)
                    || $permissionService->hasPermission($userPayload, 'team.manage', $orgId);

    if (!$canManageRoster) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canManageRoster = in_array('team.members.manage', $userPermissions, true)
                        || in_array('team.manage', $userPermissions, true);
    }

    if (!$canManageRoster) {
        header('Location: /teams/' . $teamId . '?error=' . urlencode('Unauthorized: You do not have permission to manage team rosters.'));
        exit;
    }

    $athleteId = (int)($_POST['athlete_id'] ?? 0);
    if ($athleteId <= 0) {
        header('Location: /teams/' . $teamId . '?error=' . urlencode('Please select a valid athlete to add.'));
        exit;
    }

    $teamService = new \App\Services\Team\TeamService();
    try {
        $ok = $teamService->addAthlete($orgId, $teamId, $athleteId, $_POST, $userId);
        if ($ok) {
            header('Location: /teams/' . $teamId . '?success=' . urlencode('Athlete added to team roster successfully.'));
        } else {
            header('Location: /teams/' . $teamId . '?error=' . urlencode('Failed to add athlete to team roster.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /teams/' . $teamId . '?error=' . urlencode('Unable to add athlete: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/teams/(\d+)/roster/(\d+)/remove$#', $uri, $m)) {
    $teamId = (int)$m[1];
    $athleteId = (int)$m[2];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    // Untrusted organization_id defense
    unset($_POST['organization_id']);

    // RBAC Authorization check using existing PermissionService semantics
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canManageRoster = $permissionService->hasPermission($userPayload, 'team.members.manage', $orgId)
                    || $permissionService->hasPermission($userPayload, 'team.manage', $orgId);

    if (!$canManageRoster) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canManageRoster = in_array('team.members.manage', $userPermissions, true)
                        || in_array('team.manage', $userPermissions, true);
    }

    if (!$canManageRoster) {
        header('Location: /teams/' . $teamId . '?error=' . urlencode('Unauthorized: You do not have permission to manage team rosters.'));
        exit;
    }

    if ($athleteId <= 0) {
        header('Location: /teams/' . $teamId . '?error=' . urlencode('Invalid athlete specified.'));
        exit;
    }

    $teamService = new \App\Services\Team\TeamService();
    try {
        $ok = $teamService->removeAthlete($orgId, $teamId, $athleteId, $userId);
        if ($ok) {
            header('Location: /teams/' . $teamId . '?success=' . urlencode('Athlete removed from active roster successfully.'));
        } else {
            header('Location: /teams/' . $teamId . '?error=' . urlencode('Failed to remove athlete from roster.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /teams/' . $teamId . '?error=' . urlencode('Unable to remove athlete: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/teams/(\d+)/coaches/assign$#', $uri, $m)) {
    $teamId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    // Untrusted organization_id defense
    unset($_POST['organization_id']);

    // RBAC Authorization check using PermissionService semantics (no role-name bypasses)
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canManageCoaches = $permissionService->hasPermission($userPayload, 'team.coaches.manage', $orgId)
                     || $permissionService->hasPermission($userPayload, 'team.manage', $orgId);

    if (!$canManageCoaches) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canManageCoaches = in_array('team.coaches.manage', $userPermissions, true)
                         || in_array('team.manage', $userPermissions, true);
    }

    if (!$canManageCoaches) {
        header('Location: /teams/' . $teamId . '?error=' . urlencode('Unauthorized: You do not have permission to manage team coaches.'));
        exit;
    }

    $coachId = (int)($_POST['coach_id'] ?? 0);
    if ($coachId <= 0) {
        header('Location: /teams/' . $teamId . '?error=' . urlencode('Please select a valid coach to assign.'));
        exit;
    }

    $role = trim((string)($_POST['coach_role'] ?? 'head_coach'));

    // Primary checkbox semantics:
    // Explicit true => true; Explicit false => false; Omitted/Not passed => null (use service default convention)
    $isPrimary = null;
    if (isset($_POST['is_primary'])) {
        $val = $_POST['is_primary'];
        if (is_array($val)) {
            $val = end($val);
        }
        $isPrimary = in_array((string)$val, ['1', 'true', 'on', 'yes'], true);
    }

    $teamService = new \App\Services\Team\TeamService();
    try {
        $ok = $teamService->assignCoach($orgId, $teamId, $coachId, $role, $isPrimary, $userId);
        if ($ok) {
            header('Location: /teams/' . $teamId . '?success=' . urlencode('Coach assigned to team staff successfully.'));
        } else {
            header('Location: /teams/' . $teamId . '?error=' . urlencode('Failed to assign coach to team staff.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /teams/' . $teamId . '?error=' . urlencode('Unable to assign coach: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/teams/(\d+)/coaches/(\d+)/remove$#', $uri, $m)) {
    $teamId = (int)$m[1];
    $coachId = (int)$m[2];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    // Untrusted organization_id defense
    unset($_POST['organization_id']);

    // RBAC Authorization check using PermissionService semantics (no role-name bypasses)
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canManageCoaches = $permissionService->hasPermission($userPayload, 'team.coaches.manage', $orgId)
                     || $permissionService->hasPermission($userPayload, 'team.manage', $orgId);

    if (!$canManageCoaches) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canManageCoaches = in_array('team.coaches.manage', $userPermissions, true)
                         || in_array('team.manage', $userPermissions, true);
    }

    if (!$canManageCoaches) {
        header('Location: /teams/' . $teamId . '?error=' . urlencode('Unauthorized: You do not have permission to manage team coaches.'));
        exit;
    }

    if ($coachId <= 0) {
        header('Location: /teams/' . $teamId . '?error=' . urlencode('Invalid coach specified.'));
        exit;
    }

    $teamService = new \App\Services\Team\TeamService();
    try {
        $ok = $teamService->removeCoach($orgId, $teamId, $coachId, $userId);
        if ($ok) {
            header('Location: /teams/' . $teamId . '?success=' . urlencode('Coach removed from active coaching staff successfully.'));
        } else {
            header('Location: /teams/' . $teamId . '?error=' . urlencode('Failed to remove coach from team staff.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /teams/' . $teamId . '?error=' . urlencode('Unable to remove coach: ' . $e->getMessage()));
        exit;
    }
}

// ==========================================
// 4. TRAINING ACTIONS
// ==========================================
if ($uri === '/training/create') {
    $title = trim($_POST['title'] ?? '');
    $teamId = (int)($_POST['team_id'] ?? 0);
    $venueId = (int)($_POST['venue_id'] ?? 0);
    $date = trim($_POST['training_date'] ?? '');

    if (empty($title) || empty($teamId) || empty($venueId) || empty($date)) {
        header('Location: /training/create?error=' . urlencode('Session Title, Team, Venue, and Date are required.'));
        exit;
    }

    $trainService = new \App\Services\Training\TrainingService();
    try {
        $created = $trainService->createSession($orgId, $_POST, $userId);
        $sessionId = $created['id'] ?? null;
        if ($sessionId) {
            header('Location: /training/' . $sessionId . '?success=' . urlencode('Training session scheduled successfully.'));
        } else {
            header('Location: /training?success=' . urlencode('Training session scheduled successfully.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /training/create?error=' . urlencode('Unable to schedule session: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/training/(\d+)/edit$#', $uri, $m)) {
    $sessionId = (int)$m[1];
    $title = trim($_POST['title'] ?? '');

    if (empty($title)) {
        header('Location: /training/' . $sessionId . '/edit?error=' . urlencode('Session Title is required.'));
        exit;
    }

    $trainService = new \App\Services\Training\TrainingService();
    try {
        $ok = $trainService->updateSession($orgId, $sessionId, $_POST, $userId);
        if ($ok) {
            header('Location: /training/' . $sessionId . '?success=' . urlencode('Training session updated successfully.'));
        } else {
            header('Location: /training/' . $sessionId . '/edit?error=' . urlencode('Failed to update training session.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /training/' . $sessionId . '/edit?error=' . urlencode('Unable to update session: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/training/(\d+)/attendance$#', $uri, $m)) {
    $sessionId = (int)$m[1];
    $attendanceData = $_POST['attendance'] ?? [];

    $trainService = new \App\Services\Training\TrainingService();
    try {
        $ok = $trainService->recordAttendance($orgId, $sessionId, $attendanceData, $userId);
        header('Location: /training/' . $sessionId . '?success=' . urlencode('Attendance records saved successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /training/' . $sessionId . '?error=' . urlencode('Unable to save attendance: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/training/(\d+)/delete$#', $uri, $m)) {
    $sessionId = (int)$m[1];
    $trainService = new \App\Services\Training\TrainingService();
    try {
        $ok = $trainService->deleteSession($orgId, $sessionId, $userId);
        header('Location: /training?success=' . urlencode('Training session removed successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /training?error=' . urlencode('Unable to delete session: ' . $e->getMessage()));
        exit;
    }
}

// ==========================================
// 5. TOURNAMENT ACTIONS
// ==========================================
if ($uri === '/tournaments/create') {
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    // Untrusted organization_id defense
    unset($_POST['organization_id']);

    // RBAC Authorization using existing PermissionService semantics
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canCreate = $permissionService->hasPermission($userPayload, 'tournament.create', $orgId)
              || $permissionService->hasPermission($userPayload, 'tournament.manage', $orgId);

    if (!$canCreate) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canCreate = in_array('tournament.create', $userPermissions, true)
                  || in_array('tournament.manage', $userPermissions, true);
    }

    if (!$canCreate) {
        header('Location: /tournaments?error=' . urlencode('Unauthorized: You do not have permission to create tournaments.'));
        exit;
    }

    // Input Validation & Sanitization
    $name = trim($_POST['name'] ?? '');
    $sportId = (int)($_POST['sport_id'] ?? 0);
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');
    $status = trim($_POST['status'] ?? 'draft');
    $levelId = !empty($_POST['tournament_level_id']) ? (int)$_POST['tournament_level_id'] : 1;
    $formatId = !empty($_POST['tournament_format_id']) ? (int)$_POST['tournament_format_id'] : 1;
    $venueId = !empty($_POST['venue_id']) ? (int)$_POST['venue_id'] : null;

    if (empty($name)) {
        header('Location: /tournaments/create?error=' . urlencode('Tournament Name is required.'));
        exit;
    }

    if ($sportId <= 0) {
        header('Location: /tournaments/create?error=' . urlencode('Please select a valid sport.'));
        exit;
    }

    if (empty($startDate) || !strtotime($startDate)) {
        header('Location: /tournaments/create?error=' . urlencode('Valid Start Date is required.'));
        exit;
    }

    if (empty($endDate) || !strtotime($endDate)) {
        header('Location: /tournaments/create?error=' . urlencode('Valid End Date is required.'));
        exit;
    }

    if (strtotime($endDate) < strtotime($startDate)) {
        header('Location: /tournaments/create?error=' . urlencode('End Date cannot be earlier than Start Date.'));
        exit;
    }

    $validStatuses = ['draft', 'registration_open', 'registration_closed', 'ongoing', 'completed', 'cancelled'];
    if (!in_array($status, $validStatuses, true)) {
        header('Location: /tournaments/create?error=' . urlencode('Invalid tournament status selected.'));
        exit;
    }

    // Tenant isolation: verify primary venue belongs to current organization
    if (!empty($venueId)) {
        $db = \App\Services\BaseService::getDatabaseConnection();
        $vStmt = $db->prepare("SELECT id FROM venues WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL LIMIT 1");
        $vStmt->execute([':id' => $venueId, ':org_id' => $orgId]);
        if (!$vStmt->fetchColumn()) {
            header('Location: /tournaments/create?error=' . urlencode('Selected venue is invalid or does not belong to your organization.'));
            exit;
        }
    }

    // Sanitize team_ids array
    $teamIds = [];
    if (!empty($_POST['team_ids']) && is_array($_POST['team_ids'])) {
        foreach ($_POST['team_ids'] as $tid) {
            $tInt = (int)$tid;
            if ($tInt > 0) {
                $teamIds[] = $tInt;
            }
        }
    }

    $payload = [
        'name' => $name,
        'sport_id' => $sportId,
        'tournament_level_id' => $levelId,
        'tournament_format_id' => $formatId,
        'status' => $status,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'venue_id' => $venueId,
        'organizer_name' => trim($_POST['organizer_name'] ?? 'Apex Sports Academy'),
        'location_name' => trim($_POST['location_name'] ?? 'Main Stadium Complex'),
        'city' => trim($_POST['city'] ?? 'Mumbai'),
        'state' => trim($_POST['state'] ?? 'Maharashtra'),
        'description' => trim($_POST['description'] ?? ''),
        'rules' => trim($_POST['rules'] ?? ''),
        'team_ids' => $teamIds,
    ];

    $tournService = new \App\Services\Tournament\TournamentService();
    try {
        $created = $tournService->createTournament($orgId, $payload, $userId);
        $tournId = $created['id'] ?? null;
        if ($tournId) {
            header('Location: /tournaments/' . $tournId . '?success=' . urlencode('Tournament created successfully.'));
        } else {
            header('Location: /tournaments?success=' . urlencode('Tournament created successfully.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /tournaments/create?error=' . urlencode('Unable to create tournament: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/tournaments/(\d+)/edit$#', $uri, $m)) {
    $tournId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    // Untrusted organization_id defense
    unset($_POST['organization_id']);

    // RBAC Authorization using existing PermissionService semantics
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canUpdate = $permissionService->hasPermission($userPayload, 'tournament.update', $orgId)
              || $permissionService->hasPermission($userPayload, 'tournament.manage', $orgId);

    if (!$canUpdate) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canUpdate = in_array('tournament.update', $userPermissions, true)
                  || in_array('tournament.manage', $userPermissions, true);
    }

    if (!$canUpdate) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unauthorized: You do not have permission to update tournaments.'));
        exit;
    }

    // Verify tournament exists and belongs to current organization (tenant isolation)
    $tournService = new \App\Services\Tournament\TournamentService();
    $existing = $tournService->getTournament($orgId, $tournId);
    if (!$existing) {
        header('Location: /tournaments?error=' . urlencode('Tournament not found or access denied.'));
        exit;
    }

    // Input Validation & Sanitization
    $name = trim($_POST['name'] ?? '');
    $sportId = (int)($_POST['sport_id'] ?? 0);
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $levelId = !empty($_POST['tournament_level_id']) ? (int)$_POST['tournament_level_id'] : null;
    $formatId = !empty($_POST['tournament_format_id']) ? (int)$_POST['tournament_format_id'] : null;

    if (empty($name)) {
        header('Location: /tournaments/' . $tournId . '/edit?error=' . urlencode('Tournament Name is required.'));
        exit;
    }

    if ($sportId <= 0) {
        header('Location: /tournaments/' . $tournId . '/edit?error=' . urlencode('Please select a valid sport.'));
        exit;
    }

    if (empty($startDate) || !strtotime($startDate)) {
        header('Location: /tournaments/' . $tournId . '/edit?error=' . urlencode('Valid Start Date is required.'));
        exit;
    }

    if (empty($endDate) || !strtotime($endDate)) {
        header('Location: /tournaments/' . $tournId . '/edit?error=' . urlencode('Valid End Date is required.'));
        exit;
    }

    if (strtotime($endDate) < strtotime($startDate)) {
        header('Location: /tournaments/' . $tournId . '/edit?error=' . urlencode('End Date cannot be earlier than Start Date.'));
        exit;
    }

    $validStatuses = ['draft', 'registration_open', 'registration_closed', 'ongoing', 'completed', 'cancelled'];
    if (!empty($status) && !in_array($status, $validStatuses, true)) {
        header('Location: /tournaments/' . $tournId . '/edit?error=' . urlencode('Invalid tournament status selected.'));
        exit;
    }

    $payload = [
        'name' => $name,
        'sport_id' => $sportId,
        'tournament_level_id' => $levelId ?? $existing['tournament_level_id'],
        'tournament_format_id' => $formatId ?? $existing['tournament_format_id'],
        'status' => !empty($status) ? $status : $existing['status'],
        'start_date' => $startDate,
        'end_date' => $endDate,
        'organizer_name' => trim($_POST['organizer_name'] ?? ($existing['organizer_name'] ?? '')),
        'location_name' => trim($_POST['location_name'] ?? ($existing['location_name'] ?? '')),
        'city' => trim($_POST['city'] ?? ($existing['city'] ?? '')),
        'state' => trim($_POST['state'] ?? ($existing['state'] ?? '')),
        'description' => trim($_POST['description'] ?? ($existing['description'] ?? '')),
        'rules' => trim($_POST['rules'] ?? ($existing['rules'] ?? '')),
    ];

    try {
        $ok = $tournService->updateTournament($orgId, $tournId, $payload, $userId);
        if ($ok) {
            header('Location: /tournaments/' . $tournId . '?success=' . urlencode('Tournament updated successfully.'));
        } else {
            header('Location: /tournaments/' . $tournId . '/edit?error=' . urlencode('Failed to update tournament.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /tournaments/' . $tournId . '/edit?error=' . urlencode('Unable to update tournament: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/tournaments/(\d+)/fixtures/generate$#', $uri, $m)) {
    $tournId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    // Untrusted organization_id defense
    unset($_POST['organization_id']);

    // RBAC Authorization using existing PermissionService semantics
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canManage = $permissionService->hasPermission($userPayload, 'tournament.manage', $orgId)
              || $permissionService->hasPermission($userPayload, 'tournament.update', $orgId);

    if (!$canManage) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canManage = in_array('tournament.manage', $userPermissions, true)
                  || in_array('tournament.update', $userPermissions, true);
    }

    if (!$canManage) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unauthorized: You do not have permission to generate fixtures.'));
        exit;
    }

    $tournService = new \App\Services\Tournament\TournamentService();
    try {
        $result = $tournService->generateFixtures($orgId, $tournId, $userId);
        header('Location: /tournaments/' . $tournId . '?success=' . urlencode("Successfully generated {$result['count']} fixtures."));
        exit;
    } catch (\Throwable $e) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unable to generate fixtures: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/tournaments/(\d+)/fixtures/create$#', $uri, $m)) {
    $tournId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    // Untrusted organization_id defense
    unset($_POST['organization_id']);

    // RBAC Authorization using existing PermissionService semantics
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canManage = $permissionService->hasPermission($userPayload, 'tournament.manage', $orgId)
              || $permissionService->hasPermission($userPayload, 'tournament.update', $orgId);

    if (!$canManage) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canManage = in_array('tournament.manage', $userPermissions, true)
                  || in_array('tournament.update', $userPermissions, true);
    }

    if (!$canManage) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unauthorized: You do not have permission to schedule fixtures.'));
        exit;
    }

    // Tenant verification: ensure tournament exists and belongs to current tenant
    $tournService = new \App\Services\Tournament\TournamentService();
    $tournament = $tournService->getTournament($orgId, $tournId);
    if (!$tournament) {
        header('Location: /tournaments?error=' . urlencode('Tournament not found or access denied.'));
        exit;
    }

    $homeId = (int)($_POST['home_team_id'] ?? 0);
    $awayId = (int)($_POST['away_team_id'] ?? 0);
    $venueId = (int)($_POST['venue_id'] ?? 0);
    $roundName = trim($_POST['round_name'] ?? 'Round 1');
    $scheduledDate = trim($_POST['scheduled_date'] ?? '');
    $scheduledStartTime = trim($_POST['scheduled_start_time'] ?? '');

    if ($homeId <= 0 || $awayId <= 0) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Please select both home and away teams.'));
        exit;
    }

    if ($homeId === $awayId) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Home and away teams must be distinct.'));
        exit;
    }

    if ($venueId <= 0) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Please select a valid venue.'));
        exit;
    }

    if (empty($scheduledDate) || !strtotime($scheduledDate)) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Valid match date is required.'));
        exit;
    }

    if (empty($scheduledStartTime)) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Valid start time is required.'));
        exit;
    }

    // Tenant isolation: verify venue belongs to current organization
    $db = \App\Services\BaseService::getDatabaseConnection();
    $vStmt = $db->prepare("SELECT id FROM venues WHERE id = :v_id AND organization_id = :org_id AND deleted_at IS NULL LIMIT 1");
    $vStmt->execute([':v_id' => $venueId, ':org_id' => $orgId]);
    if (!$vStmt->fetchColumn()) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Selected venue is invalid or does not belong to your organization.'));
        exit;
    }

    // Verify venue is assigned to this tournament
    $tvStmt = $db->prepare("SELECT 1 FROM tournament_venues WHERE tournament_id = :tour_id AND venue_id = :v_id LIMIT 1");
    $tvStmt->execute([':tour_id' => $tournId, ':v_id' => $venueId]);
    if (!$tvStmt->fetchColumn()) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Selected venue is not assigned to this tournament. Please assign the venue to the tournament first.'));
        exit;
    }

    // Verify both teams belong to the tournament
    $ttStmt = $db->prepare("SELECT team_id FROM tournament_teams WHERE tournament_id = :tour_id AND team_id IN (:h_id, :a_id)");
    $ttStmt->execute([':tour_id' => $tournId, ':h_id' => $homeId, ':a_id' => $awayId]);
    $enrolledTeams = $ttStmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($enrolledTeams) < 2) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Both teams must be enrolled in this tournament before scheduling a fixture.'));
        exit;
    }

    $payload = [
        'round_name' => !empty($roundName) ? $roundName : 'Round 1',
        'home_team_id' => $homeId,
        'away_team_id' => $awayId,
        'venue_id' => $venueId,
        'scheduled_date' => $scheduledDate,
        'scheduled_start_time' => $scheduledStartTime,
        'group_name' => trim($_POST['group_name'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
    ];

    try {
        $tournService->createFixture($orgId, $tournId, $payload, $userId);
        header('Location: /tournaments/' . $tournId . '?success=' . urlencode('Fixture and match scheduled successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unable to schedule fixture: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/tournaments/(\d+)/matches/(\d+)/result$#', $uri, $m)) {
    $tournId = (int)$m[1];
    $targetId = (int)$m[2];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    // Untrusted organization_id defense
    unset($_POST['organization_id']);

    // RBAC Authorization using existing PermissionService semantics
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canUpdateResult = $permissionService->hasPermission($userPayload, 'tournament.update', $orgId)
                    || $permissionService->hasPermission($userPayload, 'tournament.manage', $orgId);

    if (!$canUpdateResult) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canUpdateResult = in_array('tournament.update', $userPermissions, true)
                        || in_array('tournament.manage', $userPermissions, true);
    }

    if (!$canUpdateResult) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unauthorized: You do not have permission to record match results.'));
        exit;
    }

    // Tenant verification: tournament exists and belongs to current tenant
    $tournService = new \App\Services\Tournament\TournamentService();
    $tournament = $tournService->getTournament($orgId, $tournId);
    if (!$tournament) {
        header('Location: /tournaments?error=' . urlencode('Tournament not found or access denied.'));
        exit;
    }

    // Resolve fixture ID within tenant and tournament boundary (supports fixture ID or match ID)
    $db = \App\Services\BaseService::getDatabaseConnection();
    $fStmt = $db->prepare("SELECT id, tournament_id FROM fixtures WHERE id = :id AND organization_id = :org_id AND tournament_id = :tour_id AND deleted_at IS NULL LIMIT 1");
    $fStmt->execute([':id' => $targetId, ':org_id' => $orgId, ':tour_id' => $tournId]);
    $fixture = $fStmt->fetch(PDO::FETCH_ASSOC);

    if (!$fixture) {
        $mStmt = $db->prepare("
            SELECT f.id as fixture_id, f.tournament_id
            FROM matches m
            JOIN fixtures f ON m.fixture_id = f.id
            WHERE m.id = :id AND f.organization_id = :org_id AND f.tournament_id = :tour_id AND f.deleted_at IS NULL
            LIMIT 1
        ");
        $mStmt->execute([':id' => $targetId, ':org_id' => $orgId, ':tour_id' => $tournId]);
        $matchFixture = $mStmt->fetch(PDO::FETCH_ASSOC);
        if ($matchFixture) {
            $fixtureId = (int)$matchFixture['fixture_id'];
        } else {
            header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Match or fixture not found or access denied.'));
            exit;
        }
    } else {
        $fixtureId = (int)$fixture['id'];
    }

    // Validate score inputs: required, non-negative integers
    if (!isset($_POST['home_score']) || !isset($_POST['away_score']) || $_POST['home_score'] === '' || $_POST['away_score'] === '') {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Both home and away scores are required.'));
        exit;
    }

    $homeScoreRaw = trim((string)$_POST['home_score']);
    $awayScoreRaw = trim((string)$_POST['away_score']);

    if (filter_var($homeScoreRaw, FILTER_VALIDATE_INT) === false || (int)$homeScoreRaw < 0 ||
        filter_var($awayScoreRaw, FILTER_VALIDATE_INT) === false || (int)$awayScoreRaw < 0) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Scores must be non-negative integers.'));
        exit;
    }

    $payload = [
        'home_score' => (int)$homeScoreRaw,
        'away_score' => (int)$awayScoreRaw,
    ];

    try {
        $ok = $tournService->updateMatchResult($orgId, $fixtureId, $payload, $userId);
        if ($ok) {
            header('Location: /tournaments/' . $tournId . '?success=' . urlencode('Match result and standings updated successfully.'));
        } else {
            header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Failed to record match result.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unable to record score: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/tournaments/(\d+)/delete$#', $uri, $m)) {
    $tournId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    // Untrusted organization_id defense
    unset($_POST['organization_id']);

    // RBAC Authorization using existing PermissionService semantics
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canDelete = $permissionService->hasPermission($userPayload, 'tournament.manage', $orgId);

    if (!$canDelete) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canDelete = in_array('tournament.manage', $userPermissions, true);
    }

    if (!$canDelete) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unauthorized: You do not have permission to delete tournaments.'));
        exit;
    }

    // Verify tournament exists and belongs to current organization (tenant isolation)
    $tournService = new \App\Services\Tournament\TournamentService();
    $existing = $tournService->getTournament($orgId, $tournId);
    if (!$existing) {
        header('Location: /tournaments?error=' . urlencode('Tournament not found or access denied.'));
        exit;
    }

    try {
        $ok = $tournService->deleteTournament($orgId, $tournId, $userId);
        if ($ok) {
            header('Location: /tournaments?success=' . urlencode('Tournament removed successfully.'));
        } else {
            header('Location: /tournaments?error=' . urlencode('Failed to remove tournament.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /tournaments?error=' . urlencode('Unable to delete tournament: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/tournaments/(\d+)/teams/add$#', $uri, $m)) {
    $tournId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    // Untrusted organization_id defense
    unset($_POST['organization_id']);

    // RBAC Authorization using existing PermissionService semantics
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canManage = $permissionService->hasPermission($userPayload, 'tournament.manage', $orgId)
              || $permissionService->hasPermission($userPayload, 'tournament.update', $orgId);

    if (!$canManage) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canManage = in_array('tournament.manage', $userPermissions, true)
                  || in_array('tournament.update', $userPermissions, true);
    }

    if (!$canManage) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unauthorized: You do not have permission to register tournament teams.'));
        exit;
    }

    $teamId = (int)($_POST['team_id'] ?? 0);
    if ($teamId <= 0) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Please select a valid team to register.'));
        exit;
    }

    $tournService = new \App\Services\Tournament\TournamentService();
    try {
        $ok = $tournService->addTeam($orgId, $tournId, $teamId, $userId);
        if ($ok) {
            header('Location: /tournaments/' . $tournId . '?success=' . urlencode('Team registered for tournament successfully.'));
        } else {
            header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Failed to register team for tournament.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unable to register team: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/tournaments/(\d+)/teams/(\d+)/remove$#', $uri, $m)) {
    $tournId = (int)$m[1];
    $teamId = (int)$m[2];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    // Untrusted organization_id defense
    unset($_POST['organization_id']);

    // RBAC Authorization using existing PermissionService semantics
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canManage = $permissionService->hasPermission($userPayload, 'tournament.manage', $orgId)
              || $permissionService->hasPermission($userPayload, 'tournament.update', $orgId);

    if (!$canManage) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canManage = in_array('tournament.manage', $userPermissions, true)
                  || in_array('tournament.update', $userPermissions, true);
    }

    if (!$canManage) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unauthorized: You do not have permission to manage tournament teams.'));
        exit;
    }

    if ($teamId <= 0) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Invalid team specified.'));
        exit;
    }

    $tournService = new \App\Services\Tournament\TournamentService();
    try {
        $ok = $tournService->removeTeam($orgId, $tournId, $teamId, $userId);
        if ($ok) {
            header('Location: /tournaments/' . $tournId . '?success=' . urlencode('Team withdrawn from tournament successfully.'));
        } else {
            header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Failed to withdraw team from tournament.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unable to withdraw team: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/tournaments/(\d+)/venues/add$#', $uri, $m)) {
    $tournId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    // Untrusted organization_id defense
    unset($_POST['organization_id']);

    // RBAC Authorization using existing PermissionService semantics
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canManage = $permissionService->hasPermission($userPayload, 'tournament.manage', $orgId)
              || $permissionService->hasPermission($userPayload, 'tournament.update', $orgId);

    if (!$canManage) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canManage = in_array('tournament.manage', $userPermissions, true)
                  || in_array('tournament.update', $userPermissions, true);
    }

    if (!$canManage) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unauthorized: You do not have permission to assign tournament venues.'));
        exit;
    }

    $venueId = (int)($_POST['venue_id'] ?? 0);
    if ($venueId <= 0) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Please select a valid venue to assign.'));
        exit;
    }

    $isPrimary = !empty($_POST['is_primary']);

    $tournService = new \App\Services\Tournament\TournamentService();
    try {
        $ok = $tournService->addVenue($orgId, $tournId, $venueId, $isPrimary, $userId);
        if ($ok) {
            header('Location: /tournaments/' . $tournId . '?success=' . urlencode('Venue assigned to tournament successfully.'));
        } else {
            header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Failed to assign venue to tournament.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unable to assign venue: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/tournaments/(\d+)/venues/(\d+)/remove$#', $uri, $m)) {
    $tournId = (int)$m[1];
    $venueId = (int)$m[2];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    // Untrusted organization_id defense
    unset($_POST['organization_id']);

    // RBAC Authorization using existing PermissionService semantics
    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canManage = $permissionService->hasPermission($userPayload, 'tournament.manage', $orgId)
              || $permissionService->hasPermission($userPayload, 'tournament.update', $orgId);

    if (!$canManage) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canManage = in_array('tournament.manage', $userPermissions, true)
                  || in_array('tournament.update', $userPermissions, true);
    }

    if (!$canManage) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unauthorized: You do not have permission to manage tournament venues.'));
        exit;
    }

    if ($venueId <= 0) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Invalid venue specified.'));
        exit;
    }

    $tournService = new \App\Services\Tournament\TournamentService();
    try {
        $ok = $tournService->removeVenue($orgId, $tournId, $venueId, $userId);
        if ($ok) {
            header('Location: /tournaments/' . $tournId . '?success=' . urlencode('Venue removed from tournament successfully.'));
        } else {
            header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Failed to remove venue from tournament.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /tournaments/' . $tournId . '?error=' . urlencode('Unable to remove venue: ' . $e->getMessage()));
        exit;
    }
}

// ==========================================
// 6. VENUE & BOOKING ACTIONS
// ==========================================
if ($uri === '/venues/create') {
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['venue_type'] ?? '');

    if (empty($name) || empty($type)) {
        header('Location: /venues/create?error=' . urlencode('Venue Name and Type are required.'));
        exit;
    }

    $venueService = new \App\Services\Venue\VenueService();
    try {
        $created = $venueService->createVenue($orgId, $_POST, $userId);
        $venueId = $created['id'] ?? null;
        if ($venueId) {
            header('Location: /venues/' . $venueId . '?success=' . urlencode('Venue created successfully.'));
        } else {
            header('Location: /venues?success=' . urlencode('Venue created successfully.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /venues/create?error=' . urlencode('Unable to create venue: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/venues/(\d+)/edit$#', $uri, $m)) {
    $venueId = (int)$m[1];
    $name = trim($_POST['name'] ?? '');

    if (empty($name)) {
        header('Location: /venues/' . $venueId . '/edit?error=' . urlencode('Venue Name is required.'));
        exit;
    }

    $venueService = new \App\Services\Venue\VenueService();
    try {
        $ok = $venueService->updateVenue($orgId, $venueId, $_POST, $userId);
        if ($ok) {
            header('Location: /venues/' . $venueId . '?success=' . urlencode('Venue updated successfully.'));
        } else {
            header('Location: /venues/' . $venueId . '/edit?error=' . urlencode('Failed to update venue.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /venues/' . $venueId . '/edit?error=' . urlencode('Unable to update venue: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/venues/(\d+)/facilities/create$#', $uri, $m)) {
    $venueId = (int)$m[1];
    $facName = trim($_POST['name'] ?? '');

    if (empty($facName)) {
        header('Location: /venues/' . $venueId . '?error=' . urlencode('Facility Name is required.'));
        exit;
    }

    $venueService = new \App\Services\Venue\VenueService();
    try {
        $venueService->createFacility($orgId, $venueId, $_POST, $userId);
        header('Location: /venues/' . $venueId . '?success=' . urlencode('Facility slot created successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /venues/' . $venueId . '?error=' . urlencode('Unable to add facility slot: ' . $e->getMessage()));
        exit;
    }
}

if ($uri === '/venues/bookings/create') {
    $venueId = (int)($_POST['venue_id'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');

    $facilityIds = $_POST['facility_id'] ?? [];
    $dates = $_POST['booking_date'] ?? [];

    if (empty($venueId) || empty($facilityIds) || !is_array($facilityIds) || empty($dates) || empty($purpose)) {
        header('Location: /venues/bookings/create?error=' . urlencode('Please select Venue, at least one Facility Slot, Date, and Purpose.'));
        exit;
    }

    $venueService = new \App\Services\Venue\VenueService();
    try {
        $venueService->createBookingsBatch($orgId, $_POST, $userId);
        header('Location: /venues/' . $venueId . '?success=' . urlencode('Venue facility slot(s) booked successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /venues/bookings/create?venue_id=' . $venueId . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/venues/(\d+)/status$#', $uri, $m)) {
    $venueId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    unset($_POST['organization_id']);

    $status = trim($_POST['status'] ?? '');
    if (!in_array($status, ['active', 'inactive'], true)) {
        header('Location: /venues?error=' . urlencode('Invalid status value.'));
        exit;
    }

    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canUpdate = $permissionService->hasPermission($userPayload, 'venue.update', $orgId)
              || $permissionService->hasPermission($userPayload, 'venue.manage', $orgId);

    if (!$canUpdate) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canUpdate = in_array('venue.update', $userPermissions, true)
                  || in_array('venue.manage', $userPermissions, true);
    }

    if (!$canUpdate) {
        header('Location: /venues?error=' . urlencode('Unauthorized: You do not have permission to update venue status.'));
        exit;
    }

    $venueService = new \App\Services\Venue\VenueService();
    try {
        $ok = $venueService->updateStatus($orgId, $venueId, $status, $userId);
        if ($ok) {
            header('Location: /venues?success=' . urlencode('Venue status updated to ' . ucfirst($status) . '.'));
        } else {
            header('Location: /venues?error=' . urlencode('Venue not found or access denied.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /venues?error=' . urlencode('Unable to update venue status: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/venues/(\d+)/delete$#', $uri, $m)) {
    $venueId = (int)$m[1];
    $orgId = current_organization_id();
    $userId = current_user_id() ?? (int)($currentUser['id'] ?? 5);

    unset($_POST['organization_id']);

    $permissionService = new \App\Services\Rbac\PermissionService();
    $userPayload = array_merge(
        $_SESSION['auth'] ?? [],
        $_SESSION['auth']['user'] ?? $currentUser,
        [
            'id' => $userId,
            'role_id' => $_SESSION['auth']['role']['id'] ?? ($currentUser['role_id'] ?? 2),
            'role' => $_SESSION['auth']['role'] ?? ($currentUser['role'] ?? ['id' => 2, 'name' => 'Sports Administrator']),
            'permissions' => $_SESSION['auth']['permissions'] ?? ($_SESSION['auth']['user']['permissions'] ?? []),
        ]
    );
    $canDelete = $permissionService->hasPermission($userPayload, 'venue.manage', $orgId)
              || $permissionService->hasPermission($userPayload, 'venue.update', $orgId);

    if (!$canDelete) {
        $userPermissions = $permissionService->getUserPermissions($userId, $orgId);
        $canDelete = in_array('venue.manage', $userPermissions, true)
                  || in_array('venue.update', $userPermissions, true);
    }

    if (!$canDelete) {
        header('Location: /venues?error=' . urlencode('Unauthorized: You do not have permission to delete venues.'));
        exit;
    }

    $venueService = new \App\Services\Venue\VenueService();
    $existing = $venueService->getVenue($orgId, $venueId);
    if (!$existing) {
        header('Location: /venues?error=' . urlencode('Venue not found or access denied.'));
        exit;
    }

    try {
        $ok = $venueService->deleteVenue($orgId, $venueId, $userId);
        if ($ok) {
            header('Location: /venues?success=' . urlencode('Venue removed successfully.'));
        } else {
            header('Location: /venues?error=' . urlencode('Failed to remove venue.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /venues?error=' . urlencode('Unable to delete venue: ' . $e->getMessage()));
        exit;
    }
}

// ==========================================
// 7. INVENTORY ACTIONS
// ==========================================
if ($uri === '/inventory/create') {
    $name = trim($_POST['item_name'] ?? '');

    if (empty($name)) {
        header('Location: /inventory/create?error=' . urlencode('Item Name is required.'));
        exit;
    }

    $invService = new \App\Services\Inventory\InventoryService();
    try {
        $created = $invService->createItem($orgId, $_POST, $userId);
        $itemId = $created['id'] ?? null;
        if ($itemId) {
            header('Location: /inventory/' . $itemId . '?success=' . urlencode('Inventory item created successfully.'));
        } else {
            header('Location: /inventory?success=' . urlencode('Inventory item created successfully.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /inventory/create?error=' . urlencode('Unable to create item: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/inventory/(\d+)/edit$#', $uri, $m)) {
    $itemId = (int)$m[1];
    $name = trim($_POST['item_name'] ?? '');

    if (empty($name)) {
        header('Location: /inventory/' . $itemId . '/edit?error=' . urlencode('Item Name is required.'));
        exit;
    }

    $invService = new \App\Services\Inventory\InventoryService();
    try {
        $ok = $invService->updateItem($orgId, $itemId, $_POST, $userId);
        if ($ok) {
            header('Location: /inventory/' . $itemId . '?success=' . urlencode('Item updated successfully.'));
        } else {
            header('Location: /inventory/' . $itemId . '/edit?error=' . urlencode('Failed to update item.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /inventory/' . $itemId . '/edit?error=' . urlencode('Unable to update item: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/inventory/(\d+)/transactions$#', $uri, $m)) {
    $itemId = (int)$m[1];
    $invService = new \App\Services\Inventory\InventoryService();
    try {
        $res = $invService->recordStockTransaction($orgId, $itemId, $_POST, $userId);
        header('Location: /inventory/' . $itemId . '?success=' . urlencode('Stock movement recorded. New quantity: ' . $res['new_quantity']));
        exit;
    } catch (\Throwable $e) {
        header('Location: /inventory/' . $itemId . '?error=' . urlencode('Stock transaction failed: ' . $e->getMessage()));
        exit;
    }
}

if (preg_match('#^/inventory/(\d+)/delete$#', $uri, $m)) {
    $itemId = (int)$m[1];
    $invService = new \App\Services\Inventory\InventoryService();
    try {
        $ok = $invService->deleteItem($orgId, $itemId, $userId);
        header('Location: /inventory?success=' . urlencode('Inventory item removed successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /inventory?error=' . urlencode('Unable to delete item: ' . $e->getMessage()));
        exit;
    }
}

// Category Actions
if ($uri === '/inventory/categories/create') {
    $catService = new \App\Services\Inventory\InventoryCategoryService();
    try {
        $created = $catService->createCategory($orgId, $_POST, $userId);
        header('Location: /inventory/categories?success=' . urlencode("Category '{$created['name']}' created successfully."));
        exit;
    } catch (\Throwable $e) {
        header('Location: /inventory/categories?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/inventory/categories/(\d+)/edit$#', $uri, $m)) {
    $catId = (int)$m[1];
    $catService = new \App\Services\Inventory\InventoryCategoryService();
    try {
        $updated = $catService->updateCategory($orgId, $catId, $_POST, $userId);
        header('Location: /inventory/categories?success=' . urlencode("Category '{$updated['name']}' updated successfully."));
        exit;
    } catch (\Throwable $e) {
        header('Location: /inventory/categories?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/inventory/categories/(\d+)/status$#', $uri, $m)) {
    $catId = (int)$m[1];
    $status = trim($_POST['status'] ?? 'active');
    $catService = new \App\Services\Inventory\InventoryCategoryService();
    try {
        $updated = $catService->setStatus($orgId, $catId, $status, $userId);
        $statusLabel = ucfirst($status);
        header('Location: /inventory/categories?success=' . urlencode("Category '{$updated['name']}' is now {$statusLabel}."));
        exit;
    } catch (\Throwable $e) {
        header('Location: /inventory/categories?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/inventory/categories/(\d+)/delete$#', $uri, $m)) {
    $catId = (int)$m[1];
    $catService = new \App\Services\Inventory\InventoryCategoryService();
    try {
        $catService->deleteCategory($orgId, $catId, $userId);
        header('Location: /inventory/categories?success=' . urlencode('Category removed successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /inventory/categories?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// ==========================================
// Equipment Actions
// ==========================================
if ($uri === '/equipment/create') {
    $eqService = new \App\Services\Equipment\EquipmentService();
    try {
        $created = $eqService->createEquipment($orgId, $_POST, $userId);
        header('Location: /equipment/' . $created['id'] . '?success=' . urlencode("Equipment '{$created['equipment_name']}' created successfully."));
        exit;
    } catch (\Throwable $e) {
        header('Location: /equipment/create?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/equipment/(\d+)/edit$#', $uri, $m)) {
    $eqId = (int)$m[1];
    $eqService = new \App\Services\Equipment\EquipmentService();
    try {
        $ok = $eqService->updateEquipment($orgId, $eqId, $_POST, $userId);
        if ($ok) {
            header('Location: /equipment/' . $eqId . '?success=' . urlencode('Equipment updated successfully.'));
        } else {
            header('Location: /equipment/' . $eqId . '/edit?error=' . urlencode('Failed to update equipment.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /equipment/' . $eqId . '/edit?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/equipment/(\d+)/delete$#', $uri, $m)) {
    $eqId = (int)$m[1];
    $eqService = new \App\Services\Equipment\EquipmentService();
    try {
        $eqService->deleteEquipment($orgId, $eqId, $userId);
        header('Location: /equipment?success=' . urlencode('Equipment removed successfully.'));
        exit;
    } catch (\Throwable $e) {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/equipment';
        $redirectUrl = str_contains($referer, '/equipment') ? $referer : '/equipment';
        header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/equipment/(\d+)/assign$#', $uri, $m)) {
    $eqId = (int)$m[1];
    $eqService = new \App\Services\Equipment\EquipmentService();
    $referer = $_SERVER['HTTP_REFERER'] ?? ('/equipment/' . $eqId);
    try {
        $res = $eqService->assignEquipment($orgId, $eqId, $_POST, $userId);
        $redirectUrl = str_contains($referer, '/equipment') ? $referer : ('/equipment/' . $eqId);
        header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'success=' . urlencode("Equipment assigned to {$res['assignee_name']}."));
        exit;
    } catch (\Throwable $e) {
        $redirectUrl = str_contains($referer, '/equipment') ? $referer : ('/equipment/' . $eqId);
        header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/equipment/(\d+)/return$#', $uri, $m)) {
    $eqId = (int)$m[1];
    $eqService = new \App\Services\Equipment\EquipmentService();
    $referer = $_SERVER['HTTP_REFERER'] ?? ('/equipment/' . $eqId);
    try {
        $res = $eqService->returnEquipment($orgId, $eqId, $_POST, $userId);
        $statusMsg = $res['equipment_status'] === 'available' ? 'Available' : ucfirst($res['equipment_status']);
        $redirectUrl = str_contains($referer, '/equipment') ? $referer : ('/equipment/' . $eqId);
        header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'success=' . urlencode("Equipment returned. Current status: {$statusMsg}."));
        exit;
    } catch (\Throwable $e) {
        $redirectUrl = str_contains($referer, '/equipment') ? $referer : ('/equipment/' . $eqId);
        header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'error=' . urlencode($e->getMessage()));
        exit;
    }
}

// ==========================================
// Vendor & Supplier Actions
// ==========================================
if ($uri === '/vendors/create') {
    $vendorService = new \App\Services\Vendor\VendorService();
    try {
        $created = $vendorService->createVendor($orgId, $_POST, $userId);
        header('Location: /vendors/' . $created['id'] . '?success=' . urlencode("Vendor '{$created['company_name']}' created successfully."));
        exit;
    } catch (\Throwable $e) {
        header('Location: /vendors/create?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/vendors/(\d+)/edit$#', $uri, $m)) {
    $vendorId = (int)$m[1];
    $vendorService = new \App\Services\Vendor\VendorService();
    try {
        $ok = $vendorService->updateVendor($orgId, $vendorId, $_POST, $userId);
        if ($ok) {
            header('Location: /vendors/' . $vendorId . '?success=' . urlencode('Vendor updated successfully.'));
        } else {
            header('Location: /vendors/' . $vendorId . '/edit?error=' . urlencode('Failed to update vendor.'));
        }
        exit;
    } catch (\Throwable $e) {
        header('Location: /vendors/' . $vendorId . '/edit?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/vendors/(\d+)/delete$#', $uri, $m)) {
    $vendorId = (int)$m[1];
    $vendorService = new \App\Services\Vendor\VendorService();
    try {
        $vendorService->deleteVendor($orgId, $vendorId, $userId);
        header('Location: /vendors?success=' . urlencode('Vendor profile removed successfully.'));
        exit;
    } catch (\Throwable $e) {
        $referer = $_SERVER['HTTP_REFERER'] ?? ('/vendors/' . $vendorId);
        $redirectUrl = str_contains($referer, '/vendors') ? $referer : ('/vendors/' . $vendorId);
        header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/vendors/(\d+)/status$#', $uri, $m)) {
    $vendorId = (int)$m[1];
    $status = trim($_POST['status'] ?? '');
    $reason = trim($_POST['reason'] ?? '') ?: null;
    $vendorService = new \App\Services\Vendor\VendorService();
    $referer = $_SERVER['HTTP_REFERER'] ?? ('/vendors/' . $vendorId);
    try {
        $vendorService->setStatus($orgId, $vendorId, $status, $userId, $reason);
        $redirectUrl = str_contains($referer, '/vendors') ? $referer : ('/vendors/' . $vendorId);
        header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'success=' . urlencode("Vendor status updated to " . ucfirst($status) . "."));
        exit;
    } catch (\Throwable $e) {
        $redirectUrl = str_contains($referer, '/vendors') ? $referer : ('/vendors/' . $vendorId);
        header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/vendors/(\d+)/invoices/create$#', $uri, $m)) {
    $vendorId = (int)$m[1];
    $vendorService = new \App\Services\Vendor\VendorService();
    $referer = $_SERVER['HTTP_REFERER'] ?? ('/vendors/' . $vendorId);
    try {
        $invoice = $vendorService->createInvoice($orgId, $vendorId, $_POST, $userId);
        $redirectUrl = str_contains($referer, '/vendors') ? $referer : ('/vendors/' . $vendorId);
        header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'success=' . urlencode("Invoice '{$invoice['invoice_number']}' recorded successfully."));
        exit;
    } catch (\Throwable $e) {
        $redirectUrl = str_contains($referer, '/vendors') ? $referer : ('/vendors/' . $vendorId);
        header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/vendor-invoices/(\d+)/edit$#', $uri, $m)) {
    $invoiceId = (int)$m[1];
    $vendorId = (int)($_POST['vendor_id'] ?? 0);
    $vendorService = new \App\Services\Vendor\VendorService();
    $defaultRedirect = $vendorId ? ('/vendors/' . $vendorId) : '/vendors';
    $referer = $_SERVER['HTTP_REFERER'] ?? $defaultRedirect;
    try {
        $vendorService->updateInvoice($orgId, $invoiceId, $_POST, $userId);
        $redirectUrl = str_contains($referer, '/vendors') ? $referer : $defaultRedirect;
        header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'success=' . urlencode('Invoice updated successfully.'));
        exit;
    } catch (\Throwable $e) {
        $redirectUrl = str_contains($referer, '/vendors') ? $referer : $defaultRedirect;
        header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'error=' . urlencode($e->getMessage()));
        exit;
    }
}

// ==========================================
// Procurement & Purchase Actions
// ==========================================
if ($uri === '/purchases/requests/create') {
    $purchaseService = new \App\Services\Purchase\PurchaseService();
    try {
        $created = $purchaseService->createPurchaseRequest($orgId, $_POST, $userId);
        header('Location: /purchases/requests/' . $created['id'] . '?success=' . urlencode("Purchase request '{$created['request_reference']}' created successfully."));
        exit;
    } catch (\Throwable $e) {
        header('Location: /purchases/requests/create?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/purchases/requests/(\d+)/submit$#', $uri, $m)) {
    $prId = (int)$m[1];
    $purchaseService = new \App\Services\Purchase\PurchaseService();
    try {
        $purchaseService->submitPurchaseRequest($orgId, $prId, $userId);
        header('Location: /purchases/requests/' . $prId . '?success=' . urlencode('Purchase request submitted for approval.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /purchases/requests/' . $prId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/purchases/requests/(\d+)/approve$#', $uri, $m)) {
    $prId = (int)$m[1];
    $purchaseService = new \App\Services\Purchase\PurchaseService();
    try {
        $purchaseService->approvePurchaseRequest($orgId, $prId, $userId);
        header('Location: /purchases/requests/' . $prId . '?success=' . urlencode('Purchase request approved successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /purchases/requests/' . $prId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/purchases/requests/(\d+)/reject$#', $uri, $m)) {
    $prId = (int)$m[1];
    $reason = trim($_POST['rejection_reason'] ?? '');
    $purchaseService = new \App\Services\Purchase\PurchaseService();
    try {
        $purchaseService->rejectPurchaseRequest($orgId, $prId, $reason ?: null, $userId);
        header('Location: /purchases/requests/' . $prId . '?success=' . urlencode('Purchase request rejected.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /purchases/requests/' . $prId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/purchases/requests/(\d+)/cancel$#', $uri, $m)) {
    $prId = (int)$m[1];
    $purchaseService = new \App\Services\Purchase\PurchaseService();
    try {
        $purchaseService->cancelPurchaseRequest($orgId, $prId, $userId);
        header('Location: /purchases/requests/' . $prId . '?success=' . urlencode('Purchase request cancelled.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /purchases/requests/' . $prId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if ($uri === '/purchases/orders/create') {
    $purchaseService = new \App\Services\Purchase\PurchaseService();
    try {
        $statusMsg = ($created['status'] === 'draft')
            ? "Purchase order '{$created['po_number']}' saved as draft."
            : "Purchase order '{$created['po_number']}' issued successfully.";
        header('Location: /purchases/orders/' . $created['id'] . '?success=' . urlencode($statusMsg));
        exit;
    } catch (\Throwable $e) {
        $refUrl = !empty($_POST['purchase_request_id']) ? ('/purchases/orders/create?purchase_request_id=' . (int)$_POST['purchase_request_id']) : '/purchases/orders/create';
        header('Location: ' . $refUrl . (str_contains($refUrl, '?') ? '&' : '?') . 'error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/purchases/orders/(\d+)/status$#', $uri, $m)) {
    $poId = (int)$m[1];
    $status = trim($_POST['status'] ?? '');
    $purchaseService = new \App\Services\Purchase\PurchaseService();
    try {
        $purchaseService->updatePoStatus($orgId, $poId, $status, $userId);
        header('Location: /purchases/orders/' . $poId . '?success=' . urlencode("Order status updated to " . ucfirst($status) . "."));
        exit;
    } catch (\Throwable $e) {
        header('Location: /purchases/orders/' . $poId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/purchases/orders/(\d+)/receive$#', $uri, $m)) {
    $poId = (int)$m[1];
    $purchaseService = new \App\Services\Purchase\PurchaseService();
    try {
        $res = $purchaseService->receiveGoods($orgId, $poId, $_POST, $userId);
        $grn = $res['goods_receipt']['receipt_number'] ?? 'GRN';
        header('Location: /purchases/orders/' . $poId . '?success=' . urlencode("Goods received via {$grn} and inventory updated."));
        exit;
    } catch (\Throwable $e) {
        header('Location: /purchases/orders/' . $poId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// ==========================================
// 8. LEAVE ACTIONS
// ==========================================
if ($uri === '/leave/create') {
    $leaveService = new \App\Services\Leave\LeaveService();
    try {
        $data = [
            'applicant_type' => $_POST['applicant_type'] ?? 'employee',
            'employee_id' => !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null,
            'athlete_id' => !empty($_POST['athlete_id']) ? (int)$_POST['athlete_id'] : null,
            'leave_type_id' => (int)($_POST['leave_type_id'] ?? 1),
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'total_days' => !empty($_POST['total_days']) ? (float)$_POST['total_days'] : 1,
            'reason' => trim($_POST['reason'] ?? ''),
            'attachment_path' => $_POST['attachment_path'] ?? null,
        ];
        $created = $leaveService->applyLeave($orgId, $data, $userId);
        header('Location: /leave/' . $created['id'] . '?success=' . urlencode('Leave application submitted successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /leave/create?error=' . urlencode('Unable to submit leave: ' . $e->getMessage()));
        exit;
    }
}

// ==========================================
// 9. FINANCE ACTIONS
// ==========================================

// --- Categories ---
if ($uri === '/finance/categories/create') {
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $financeService->createCategory($orgId, $_POST, $userId);
        header('Location: /finance?tab=categories&success=' . urlencode('Finance category created successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance?tab=categories&error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/finance/categories/(\d+)/edit$#', $uri, $m)) {
    $catId = (int)$m[1];
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $financeService->updateCategory($orgId, $catId, $_POST, $userId);
        header('Location: /finance?tab=categories&success=' . urlencode('Category updated successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance?tab=categories&error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/finance/categories/(\d+)/status$#', $uri, $m)) {
    $catId = (int)$m[1];
    $status = trim($_POST['status'] ?? 'active');
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $financeService->setCategoryStatus($orgId, $catId, $status, $userId);
        header('Location: /finance?tab=categories&success=' . urlencode("Category status changed to {$status}."));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance?tab=categories&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// --- Income Transactions ---
if ($uri === '/finance/income/create') {
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $created = $financeService->recordIncome($orgId, $_POST, $userId);
        header('Location: /finance/income/' . $created['id'] . '?success=' . urlencode("Income '{$created['income_reference']}' recorded successfully."));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance/income/create?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/finance/income/(\d+)/edit$#', $uri, $m)) {
    $incId = (int)$m[1];
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $financeService->updateIncome($orgId, $incId, $_POST, $userId);
        header('Location: /finance/income/' . $incId . '?success=' . urlencode('Income record updated successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance/income/' . $incId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/finance/income/(\d+)/cancel$#', $uri, $m)) {
    $incId = (int)$m[1];
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $financeService->cancelIncome($orgId, $incId, $userId);
        header('Location: /finance/income/' . $incId . '?success=' . urlencode('Income transaction cancelled.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance/income/' . $incId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// --- Expenses ---
if ($uri === '/finance/expenses/create') {
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $created = $financeService->recordExpense($orgId, $_POST, $userId);
        header('Location: /finance/expenses/' . $created['id'] . '?success=' . urlencode("Expense '{$created['expense_reference']}' recorded successfully."));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance/expenses/create?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/finance/expenses/(\d+)/edit$#', $uri, $m)) {
    $expId = (int)$m[1];
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $financeService->updateExpense($orgId, $expId, $_POST, $userId);
        header('Location: /finance/expenses/' . $expId . '?success=' . urlencode('Expense updated successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance/expenses/' . $expId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/finance/expenses/(\d+)/approve$#', $uri, $m)) {
    $expId = (int)$m[1];
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $financeService->approveExpense($orgId, $expId, $userId);
        header('Location: /finance/expenses/' . $expId . '?success=' . urlencode('Expense approved successfully.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance/expenses/' . $expId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/finance/expenses/(\d+)/reject$#', $uri, $m)) {
    $expId = (int)$m[1];
    $reason = trim($_POST['rejection_reason'] ?? '');
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $financeService->rejectExpense($orgId, $expId, $reason ?: null, $userId);
        header('Location: /finance/expenses/' . $expId . '?success=' . urlencode('Expense rejected.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance/expenses/' . $expId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/finance/expenses/(\d+)/cancel$#', $uri, $m)) {
    $expId = (int)$m[1];
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $financeService->cancelExpense($orgId, $expId, $userId);
        header('Location: /finance/expenses/' . $expId . '?success=' . urlencode('Expense cancelled.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance/expenses/' . $expId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/finance/expenses/(\d+)/delete$#', $uri, $m)) {
    $expId = (int)$m[1];
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $financeService->deleteExpense($orgId, $expId, $userId);
        header('Location: /finance?tab=expenses&success=' . urlencode('Expense deleted.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance/expenses/' . $expId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// --- Budgets ---
if ($uri === '/finance/budgets/create') {
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $items = [];
        if (!empty($_POST['categories']) && is_array($_POST['categories'])) {
            foreach ($_POST['categories'] as $idx => $catId) {
                $allocated = (float)($_POST['allocated_amounts'][$idx] ?? 0);
                if ($catId && $allocated > 0) {
                    $items[] = [
                        'finance_category_id' => (int)$catId,
                        'department_id' => !empty($_POST['department_ids'][$idx]) ? (int)$_POST['department_ids'][$idx] : null,
                        'allocated_amount' => $allocated,
                        'description' => !empty($_POST['descriptions'][$idx]) ? trim($_POST['descriptions'][$idx]) : null,
                    ];
                }
            }
        }
        $data = $_POST;
        $data['items'] = $items;
        $created = $financeService->createBudget($orgId, $data, $userId);
        header('Location: /finance/budgets/' . $created['id'] . '?success=' . urlencode("Budget '{$created['budget_name']}' created successfully."));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance/budgets/create?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/finance/budgets/(\d+)/items/add$#', $uri, $m)) {
    $budgetId = (int)$m[1];
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $financeService->addBudgetItem($orgId, $budgetId, $_POST);
        header('Location: /finance/budgets/' . $budgetId . '?success=' . urlencode('Budget line item added.'));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance/budgets/' . $budgetId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

if (preg_match('#^/finance/budgets/(\d+)/status$#', $uri, $m)) {
    $budgetId = (int)$m[1];
    $status = trim($_POST['status'] ?? 'active');
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $financeService->setBudgetStatus($orgId, $budgetId, $status, $userId);
        header('Location: /finance/budgets/' . $budgetId . '?success=' . urlencode("Budget status updated to {$status}."));
        exit;
    } catch (\Throwable $e) {
        header('Location: /finance/budgets/' . $budgetId . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// --- Payments ---
if ($uri === '/finance/payments/create') {
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $created = $financeService->recordPayment($orgId, $_POST, $userId);
        $redirectUrl = '/finance?tab=payments';
        if (!empty($_POST['expense_id'])) {
            $redirectUrl = '/finance/expenses/' . (int)$_POST['expense_id'];
        } elseif (!empty($_POST['vendor_invoice_id']) && !empty($_POST['vendor_id'])) {
            $redirectUrl = '/vendors/' . (int)$_POST['vendor_id'];
        }
        header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'success=' . urlencode("Payment '{$created['payment_reference']}' of ₹" . number_format((float)$created['amount'], 2) . " recorded."));
        exit;
    } catch (\Throwable $e) {
        $redirectUrl = '/finance?tab=payments';
        if (!empty($_POST['expense_id'])) {
            $redirectUrl = '/finance/expenses/' . (int)$_POST['expense_id'];
        } elseif (!empty($_POST['vendor_invoice_id']) && !empty($_POST['vendor_id'])) {
            $redirectUrl = '/vendors/' . (int)$_POST['vendor_id'];
        }
        header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Direct vendor invoice payment hook
if (preg_match('#^/vendor-invoices/(\d+)/pay$#', $uri, $m)) {
    $invId = (int)$m[1];
    $financeService = new \App\Services\Finance\FinanceService();
    try {
        $payData = array_merge($_POST, [
            'payment_type' => 'vendor_invoice',
            'vendor_invoice_id' => $invId,
        ]);
        $created = $financeService->recordPayment($orgId, $payData, $userId);
        $vendorId = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
        $redir = $vendorId ? '/vendors/' . $vendorId : '/finance?tab=payments';
        header('Location: ' . $redir . (str_contains($redir, '?') ? '&' : '?') . 'success=' . urlencode("Payment '{$created['payment_reference']}' recorded for invoice."));
        exit;
    } catch (\Throwable $e) {
        $vendorId = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
        $redir = $vendorId ? '/vendors/' . $vendorId : '/finance?tab=payments';
        header('Location: ' . $redir . (str_contains($redir, '?') ? '&' : '?') . 'error=' . urlencode($e->getMessage()));
        exit;
    }
}

// ==========================================
// 12. NOTIFICATION ACTIONS
// ==========================================
if ($uri === '/notifications/read' || preg_match('#^/notifications/(\d+)/read$#', $uri, $m)) {
    $rawInput = file_get_contents('php://input');
    $body = json_decode($rawInput, true) ?? [];
    $notifId = isset($m[1]) ? (int)$m[1] : (int)($_POST['notification_id'] ?? ($body['notification_id'] ?? 0));

    $notifService = new \App\Services\Notification\NotificationService();
    $ok = $notifService->markAsRead($orgId, $notifId, $userId);
    $unreadCount = $notifService->getUnreadCount($orgId, $userId);

    // If AJAX/JSON request
    if ((!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) || !empty($body) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $ok, 'unread_count' => $unreadCount]);
        exit;
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '/notifications';
    header('Location: ' . $referer);
    exit;
}

if ($uri === '/notifications/read-all') {
    $notifService = new \App\Services\Notification\NotificationService();
    $updated = $notifService->markAllAsRead($orgId, $userId);

    $rawInput = file_get_contents('php://input');
    $body = json_decode($rawInput, true) ?? [];

    if ((!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) || !empty($body) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'updated_count' => $updated, 'unread_count' => 0]);
        exit;
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '/notifications';
    header('Location: ' . $referer);
    exit;
}

