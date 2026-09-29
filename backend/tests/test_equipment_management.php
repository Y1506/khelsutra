<?php

/**
 * KhelSutra Phase 1B-3: Equipment Management Verification Script
 * Validates Equipment CRUD, Assignment across entities, Return workflows,
 * Condition tracking, History ledger, and Multi-tenancy isolation.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/app.php';

use App\Services\Equipment\EquipmentService;
use App\Services\Inventory\InventoryService;
use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\Athlete;
use App\Models\Team;
use App\Models\Employee;
use App\Models\Venue;

$pdo = \App\Services\BaseService::getDatabaseConnection();
$eqService = new EquipmentService();
$invService = new InventoryService();

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$message}\n";
    } else {
        $failed++;
        echo " [FAIL] {$message}\n";
    }
}

echo "=== KhelSutra Phase 1B-3: Equipment Management Verification ===\n\n";

$org1 = 1;
$org2 = 2;
$userId = 1;

// Ensure Organization 2 exists
$org2Row = $pdo->query("SELECT id FROM organizations WHERE id = {$org2}")->fetchColumn();
if (!$org2Row) {
    $pdo->exec("INSERT INTO organizations (id, organization_code, name, country, status, created_at, updated_at) VALUES ({$org2}, 'ORG-TEST-2', 'Secondary Sports Club', 'India', 'active', NOW(), NOW())");
}

// Cleanup any old test equipment
$pdo->exec("DELETE FROM equipment_assignments WHERE notes LIKE '%TEST_SUITE%'");
$pdo->exec("DELETE FROM equipment WHERE equipment_name LIKE '%TEST_SUITE%' OR asset_code LIKE 'TEST-EQP%'");

// Find or create test entities in Org 1
$athlete1 = $pdo->query("SELECT id FROM athletes WHERE organization_id = {$org1} AND deleted_at IS NULL LIMIT 1")->fetchColumn();
if (!$athlete1) {
    $pdo->exec("INSERT INTO athletes (organization_id, athlete_code, first_name, last_name, status, created_at, updated_at) VALUES ({$org1}, 'ATH-TST01', 'Test', 'Athlete', 'active', NOW(), NOW())");
    $athlete1 = (int)$pdo->lastInsertId();
} else {
    $athlete1 = (int)$athlete1;
}

$sportId = $pdo->query("SELECT id FROM sports LIMIT 1")->fetchColumn();
if (!$sportId) {
    $pdo->exec("INSERT INTO sports (name, code, status, is_global, created_at, updated_at) VALUES ('Football', 'FB', 'active', 1, NOW(), NOW())");
    $sportId = (int)$pdo->lastInsertId();
} else {
    $sportId = (int)$sportId;
}
$team1 = $pdo->query("SELECT id FROM teams WHERE organization_id = {$org1} AND deleted_at IS NULL LIMIT 1")->fetchColumn();
if (!$team1) {
    $pdo->exec("INSERT INTO teams (organization_id, sport_id, team_code, name, status, created_at, updated_at) VALUES ({$org1}, {$sportId}, 'TM-TST01', 'Test Team Alpha', 'active', NOW(), NOW())");
    $team1 = (int)$pdo->lastInsertId();
} else {
    $team1 = (int)$team1;
}

$employee1 = $pdo->query("SELECT id FROM employees WHERE organization_id = {$org1} AND deleted_at IS NULL LIMIT 1")->fetchColumn();
if (!$employee1) {
    $pdo->exec("INSERT INTO employees (organization_id, employee_code, first_name, last_name, employment_status, created_at, updated_at) VALUES ({$org1}, 'EMP-TST01', 'Test', 'CoachStaff', 'active', NOW(), NOW())");
    $employee1 = (int)$pdo->lastInsertId();
} else {
    $employee1 = (int)$employee1;
}

$coach1 = $pdo->query("SELECT id FROM coach_profiles WHERE organization_id = {$org1} AND deleted_at IS NULL LIMIT 1")->fetchColumn();
if (!$coach1) {
    $pdo->exec("INSERT INTO coach_profiles (organization_id, employee_id, coach_code, coaching_level, status, created_at, updated_at) VALUES ({$org1}, {$employee1}, 'CCH-TST01', 'Senior', 'active', NOW(), NOW())");
    $coach1 = (int)$pdo->lastInsertId();
} else {
    $coach1 = (int)$coach1;
}

$venue1 = $pdo->query("SELECT id FROM venues WHERE organization_id = {$org1} AND deleted_at IS NULL LIMIT 1")->fetchColumn();
if (!$venue1) {
    $pdo->exec("INSERT INTO venues (organization_id, venue_code, name, status, created_at, updated_at) VALUES ({$org1}, 'VN-TST01', 'Test Arena', 'active', NOW(), NOW())");
    $venue1 = (int)$pdo->lastInsertId();
} else {
    $venue1 = (int)$venue1;
}

// Find or create test entities in Org 2
$athlete2 = $pdo->query("SELECT id FROM athletes WHERE organization_id = {$org2} AND deleted_at IS NULL LIMIT 1")->fetchColumn();
if (!$athlete2) {
    $pdo->exec("INSERT INTO athletes (organization_id, athlete_code, first_name, last_name, status, created_at, updated_at) VALUES ({$org2}, 'ATH-TST02', 'Org2', 'Runner', 'active', NOW(), NOW())");
    $athlete2 = (int)$pdo->lastInsertId();
} else {
    $athlete2 = (int)$athlete2;
}

try {
    // =========================================================================
    // 1. Equipment CRUD
    // =========================================================================
    echo "--- 1. Equipment CRUD ---\n";

    // Create equipment with auto-generated code
    $eq1 = $eqService->createEquipment($org1, [
        'equipment_name' => 'TEST_SUITE Match Football Pro',
        'model_number' => 'MF-2026',
        'manufacturer' => 'Nike',
        'current_location' => 'Store Room Rack A',
        'purchase_cost' => 1250.00,
        'purchase_date' => '2026-01-15',
        'condition_status' => 'new',
        'status' => 'available'
    ], $userId);

    assertTest(!empty($eq1['id']), 'Equipment unit created with ID');
    assertTest(str_starts_with($eq1['asset_code'], 'EQP-'), "Asset code auto-generated with EQP- prefix ({$eq1['asset_code']})");
    assertTest($eq1['status'] === 'available', 'Initial status is available');
    assertTest($eq1['condition_status'] === 'new', 'Initial condition status is new');
    assertTest((int)$eq1['organization_id'] === $org1, 'Equipment belongs to Org 1');

    // Create equipment with explicit custom asset code
    $customCode = 'TEST-EQP-CUSTOM-001';
    $eq2 = $eqService->createEquipment($org1, [
        'equipment_name' => 'TEST_SUITE Yonex Badminton Racket',
        'asset_code' => $customCode,
        'serial_number' => 'SN-YONEX-99881',
        'manufacturer' => 'Yonex',
        'condition_status' => 'good',
        'status' => 'available'
    ], $userId);

    assertTest($eq2['asset_code'] === $customCode, 'Equipment created with explicit asset code');
    assertTest($eq2['serial_number'] === 'SN-YONEX-99881', 'Serial number saved');

    // Reject duplicate asset code in same org
    $duplicateCodeFailed = false;
    try {
        $eqService->createEquipment($org1, [
            'equipment_name' => 'TEST_SUITE Duplicate Asset Code',
            'asset_code' => $customCode
        ], $userId);
    } catch (\InvalidArgumentException $e) {
        $duplicateCodeFailed = true;
    }
    assertTest($duplicateCodeFailed, 'Duplicate asset code rejected within same organization');

    // Reject duplicate serial number in same org
    $duplicateSerialFailed = false;
    try {
        $eqService->createEquipment($org1, [
            'equipment_name' => 'TEST_SUITE Duplicate Serial',
            'serial_number' => 'SN-YONEX-99881'
        ], $userId);
    } catch (\InvalidArgumentException $e) {
        $duplicateSerialFailed = true;
    }
    assertTest($duplicateSerialFailed, 'Duplicate serial number rejected within same organization');

    // Reject empty equipment name
    $emptyNameFailed = false;
    try {
        $eqService->createEquipment($org1, ['equipment_name' => ''], $userId);
    } catch (\InvalidArgumentException $e) {
        $emptyNameFailed = true;
    }
    assertTest($emptyNameFailed, 'Empty equipment name rejected');

    // View equipment
    $view = $eqService->getEquipment($org1, (int)$eq1['id']);
    assertTest($view !== null, 'getEquipment retrieves item');
    assertTest($view['equipment_name'] === 'TEST_SUITE Match Football Pro', 'Retrieved name matches created name');
    assertTest(array_key_exists('active_assignment', $view), 'getEquipment includes active_assignment key');
    assertTest($view['active_assignment'] === null, 'Active assignment is initially null');
    assertTest(is_array($view['assignment_history']), 'Assignment history is array');

    // Update equipment
    $updOk = $eqService->updateEquipment($org1, (int)$eq1['id'], [
        'equipment_name' => 'TEST_SUITE Match Football Pro v2',
        'current_location' => 'Main Gymnasium Cage 4',
        'purchase_cost' => 1399.50
    ], $userId);
    assertTest($updOk === true, 'updateEquipment returned true');

    $viewUpd = $eqService->getEquipment($org1, (int)$eq1['id']);
    assertTest($viewUpd['equipment_name'] === 'TEST_SUITE Match Football Pro v2', 'Updated equipment name persisted');
    assertTest($viewUpd['current_location'] === 'Main Gymnasium Cage 4', 'Updated location persisted');
    assertTest((float)$viewUpd['purchase_cost'] === 1399.50, 'Updated purchase cost persisted');

    // =========================================================================
    // 2. Equipment Assignment
    // =========================================================================
    echo "\n--- 2. Equipment Assignment ---\n";

    // Assign to Athlete
    $assignAthlete = $eqService->assignEquipment($org1, (int)$eq1['id'], [
        'assignee_type' => 'athlete',
        'athlete_id' => $athlete1,
        'assigned_date' => '2026-09-20',
        'expected_return_date' => '2026-09-25',
        'condition_on_issue' => 'new',
        'notes' => 'TEST_SUITE Issued for state tournament training'
    ], $userId);

    assertTest(!empty($assignAthlete['assignment_id']), 'Assignment to athlete created');
    assertTest($assignAthlete['status'] === 'assigned', 'Assignment status is assigned');
    assertTest($assignAthlete['assignee_type'] === 'athlete', 'Assignee type recorded as athlete');

    // Verify equipment status changed to assigned
    $eqAfterAssign = $eqService->getEquipment($org1, (int)$eq1['id']);
    assertTest($eqAfterAssign['status'] === 'assigned', 'Equipment status transitioned to assigned');
    assertTest($eqAfterAssign['active_assignment'] !== null, 'active_assignment is now populated');
    assertTest((int)$eqAfterAssign['active_assignment']['athlete_id'] === $athlete1, 'active_assignment athlete_id matches');

    // Attempting to assign an already assigned equipment should fail
    $doubleAssignFailed = false;
    try {
        $eqService->assignEquipment($org1, (int)$eq1['id'], [
            'assignee_type' => 'team',
            'team_id' => $team1
        ], $userId);
    } catch (\InvalidArgumentException $e) {
        $doubleAssignFailed = true;
    }
    assertTest($doubleAssignFailed, 'Assigning already assigned equipment is rejected');

    // Attempting cross-tenant assignment (assigning Org 2 athlete to Org 1 equipment) should fail
    $crossTenantAssignFailed = false;
    try {
        $eqService->assignEquipment($org1, (int)$eq2['id'], [
            'assignee_type' => 'athlete',
            'athlete_id' => $athlete2
        ], $userId);
    } catch (\InvalidArgumentException $e) {
        $crossTenantAssignFailed = true;
    }
    assertTest($crossTenantAssignFailed, 'Cross-tenant assignment rejected (Org 2 athlete on Org 1 equipment)');

    // Assign eq2 to Team
    $assignTeam = $eqService->assignEquipment($org1, (int)$eq2['id'], [
        'assignee_type' => 'team',
        'team_id' => $team1,
        'condition_on_issue' => 'good',
        'notes' => 'TEST_SUITE Issued to team'
    ], $userId);
    assertTest(!empty($assignTeam['assignment_id']), 'Assignment to team created');
    assertTest($assignTeam['assignee_type'] === 'team', 'Assignee type is team');

    // =========================================================================
    // 3. Equipment Return & Condition Handling
    // =========================================================================
    echo "\n--- 3. Equipment Return & Condition Handling ---\n";

    // Normal Return of eq1 (football)
    $returnNormal = $eqService->returnEquipment($org1, (int)$eq1['id'], [
        'returned_date' => '2026-09-24',
        'condition_on_return' => 'good',
        'status' => 'returned',
        'return_location' => 'Store Room Rack A',
        'notes' => 'TEST_SUITE Returned in good shape after matches'
    ], $userId);

    assertTest($returnNormal['assignment_status'] === 'returned', 'Assignment status transitioned to returned');
    assertTest($returnNormal['equipment_status'] === 'available', 'Equipment status transitioned to available');
    assertTest($returnNormal['condition_status'] === 'good', 'Equipment condition status updated to good');

    $eqAfterReturn = $eqService->getEquipment($org1, (int)$eq1['id']);
    assertTest($eqAfterReturn['status'] === 'available', 'Equipment is available in DB');
    assertTest($eqAfterReturn['active_assignment'] === null, 'Active assignment is null after return');

    // Guard: Returning unassigned equipment should fail
    $returnUnassignedFailed = false;
    try {
        $eqService->returnEquipment($org1, (int)$eq1['id'], ['condition_on_return' => 'good'], $userId);
    } catch (\InvalidArgumentException $e) {
        $returnUnassignedFailed = true;
    }
    assertTest($returnUnassignedFailed, 'Returning unassigned equipment is rejected');

    // Damaged Return flow: Re-assign eq1, then return as Damaged
    $eqService->assignEquipment($org1, (int)$eq1['id'], [
        'assignee_type' => 'coach',
        'coach_id' => $coach1,
        'condition_on_issue' => 'good',
        'notes' => 'TEST_SUITE Practice match'
    ], $userId);

    $returnDamaged = $eqService->returnEquipment($org1, (int)$eq1['id'], [
        'condition_on_return' => 'damaged',
        'status' => 'damaged',
        'notes' => 'TEST_SUITE Punctured bladder during practice'
    ], $userId);

    assertTest($returnDamaged['assignment_status'] === 'damaged', 'Assignment status recorded as damaged');
    assertTest($returnDamaged['equipment_status'] === 'maintenance', 'Damaged equipment status transitioned to maintenance');
    assertTest($returnDamaged['condition_status'] === 'damaged', 'Equipment condition recorded as damaged');

    // Lost Return flow on eq2 (racket): return as Lost
    $returnLost = $eqService->returnEquipment($org1, (int)$eq2['id'], [
        'condition_on_return' => 'lost',
        'status' => 'lost',
        'notes' => 'TEST_SUITE Lost at regional facility'
    ], $userId);

    assertTest($returnLost['assignment_status'] === 'lost', 'Assignment status recorded as lost');
    assertTest($returnLost['equipment_status'] === 'lost', 'Equipment status transitioned to lost');
    assertTest($returnLost['condition_status'] === 'lost', 'Equipment condition recorded as lost');

    // =========================================================================
    // 4. Assignment History Ledger
    // =========================================================================
    echo "\n--- 4. Assignment History Ledger ---\n";

    $history = $eqService->getAssignmentHistory($org1, (int)$eq1['id']);
    assertTest(count($history) === 2, 'History ledger contains 2 distinct assignment lifecycles');
    assertTest($history[0]['status'] === 'damaged', 'Latest assignment was damaged return');
    assertTest($history[1]['status'] === 'returned', 'Earlier assignment was normal return');
    assertTest(!empty($history[0]['assignee_name']), 'Assignee name resolved in history');

    // =========================================================================
    // 5. Multi-Tenancy & Tenant Isolation
    // =========================================================================
    echo "\n--- 5. Multi-Tenancy & Tenant Isolation ---\n";

    // Create equipment in Org 2
    $eqOrg2 = $eqService->createEquipment($org2, [
        'equipment_name' => 'TEST_SUITE Org2 Basketball Unit',
        'status' => 'available'
    ], $userId);

    assertTest(!empty($eqOrg2['id']), 'Org 2 equipment created');

    // Org 1 cannot view Org 2 equipment
    $crossView = $eqService->getEquipment($org1, (int)$eqOrg2['id']);
    assertTest($crossView === null, 'Org 1 cannot read Org 2 equipment');

    // Org 1 cannot update Org 2 equipment
    $crossUpdateFailed = false;
    try {
        $eqService->updateEquipment($org1, (int)$eqOrg2['id'], ['equipment_name' => 'Hacked'], $userId);
    } catch (\InvalidArgumentException $e) {
        $crossUpdateFailed = true;
    }
    assertTest($crossUpdateFailed, 'Org 1 cannot update Org 2 equipment');

    // Org 1 cannot assign Org 2 equipment
    $crossAssignFailed = false;
    try {
        $eqService->assignEquipment($org1, (int)$eqOrg2['id'], ['assignee_type' => 'athlete', 'athlete_id' => $athlete1], $userId);
    } catch (\InvalidArgumentException $e) {
        $crossAssignFailed = true;
    }
    assertTest($crossAssignFailed, 'Org 1 cannot assign Org 2 equipment');

    // Org 1 cannot delete Org 2 equipment
    $crossDeleteFailed = false;
    try {
        $eqService->deleteEquipment($org1, (int)$eqOrg2['id'], $userId);
    } catch (\InvalidArgumentException $e) {
        $crossDeleteFailed = true;
    }
    assertTest($crossDeleteFailed, 'Org 1 cannot delete Org 2 equipment');

    // Org 1 equipment list excludes Org 2 equipment
    $org1List = $eqService->listEquipment($org1, 1, 100);
    $foundOrg2InOrg1 = false;
    foreach ($org1List['data'] as $item) {
        if ((int)$item['id'] === (int)$eqOrg2['id']) {
            $foundOrg2InOrg1 = true;
            break;
        }
    }
    assertTest(!$foundOrg2InOrg1, 'Org 1 list excludes Org 2 equipment');

    // =========================================================================
    // 6. Soft Delete Verification
    // =========================================================================
    echo "\n--- 6. Soft Delete Verification ---\n";

    // Create a new unit to soft-delete
    $eqToDelete = $eqService->createEquipment($org1, [
        'equipment_name' => 'TEST_SUITE Temporary Cones',
        'status' => 'available'
    ], $userId);

    // Delete it
    $delOk = $eqService->deleteEquipment($org1, (int)$eqToDelete['id'], $userId);
    assertTest($delOk === true, 'deleteEquipment returns true');

    // getEquipment returns null
    $getAfterDel = $eqService->getEquipment($org1, (int)$eqToDelete['id']);
    assertTest($getAfterDel === null, 'Soft-deleted equipment excluded from getEquipment');

    // Eloquent checks
    $eloquentFind = Equipment::find($eqToDelete['id']);
    assertTest($eloquentFind === null, 'Eloquent find() excludes soft-deleted equipment');

    $eloquentWithTrashed = Equipment::withTrashed()->find($eqToDelete['id']);
    assertTest($eloquentWithTrashed !== null, 'Eloquent withTrashed() finds soft-deleted equipment');
    assertTest($eloquentWithTrashed->deleted_at !== null, 'deleted_at timestamp is populated in DB');

} catch (\Throwable $e) {
    $failed++;
    echo "\n[EXCEPTION OCCURRED]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    echo "\n--- Cleaning up test records ---\n";
    $pdo->exec("DELETE FROM equipment_assignments WHERE notes LIKE '%TEST_SUITE%'");
    $pdo->exec("DELETE FROM equipment WHERE equipment_name LIKE '%TEST_SUITE%' OR asset_code LIKE 'TEST-EQP%'");
    echo "Cleanup complete.\n";
}

echo "\n=============================================\n";
echo "SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
echo "=============================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
