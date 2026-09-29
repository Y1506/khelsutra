<?php

/**
 * KhelSutra Phase 4: Notification Service & Automated Alerts Verification Script
 * Validates:
 * 1. Persistent Notifications (create, store, fetch, unread count, mark as read)
 * 2. Low-Stock Alerts (stock movement integration, de-duplication)
 * 3. Equipment Return Alerts (overdue assignments expected_return_date < current date)
 * 4. Budget Alerts (budget utilization threshold integration)
 * 5. Strict Multi-Tenant Isolation
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/app.php';

use App\Services\Notification\NotificationService;
use App\Services\Inventory\InventoryService;
use App\Services\Equipment\EquipmentService;
use App\Services\Finance\FinanceService;
use App\Models\Notification;

$pdo = \App\Services\BaseService::getDatabaseConnection();
$notifService = new NotificationService();
$invService = new InventoryService();
$eqService = new EquipmentService();
$finService = new FinanceService();

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

echo "=== KhelSutra Phase 4: Notification Service & Automated Alerts Verification ===\n\n";

$org1 = 1;
$org2 = 2;
$user1 = 1;

// Ensure Organization 2 exists
$org2Row = $pdo->query("SELECT id FROM organizations WHERE id = {$org2}")->fetchColumn();
if (!$org2Row) {
    $pdo->exec("INSERT INTO organizations (id, organization_code, name, country, status, created_at, updated_at) VALUES ({$org2}, 'ORG-TEST-2', 'Secondary Sports Academy', 'India', 'active', NOW(), NOW())");
}

// Ensure User 1 has active access in Org 1
$ou1 = $pdo->query("SELECT id FROM organization_users WHERE organization_id = {$org1} AND user_id = {$user1}")->fetchColumn();
if (!$ou1) {
    $pdo->exec("INSERT INTO organization_users (organization_id, user_id, role_id, access_status, created_at, updated_at) VALUES ({$org1}, {$user1}, 2, 'active', NOW(), NOW())");
}

// Clean up any test notifications for clean baseline
$pdo->exec("DELETE FROM notifications WHERE title LIKE '%[TEST]%' OR notification_type IN ('test_alert', 'low_stock', 'equipment_overdue', 'budget_alert')");

// =========================================================================
// SECTION 1: PERSISTENT NOTIFICATIONS (CRUD & Schema Compliance)
// =========================================================================
echo "--- Section 1: Persistent Notifications ---\n";

$initialCount = $notifService->getUnreadCount($org1, $user1);
assertTest($initialCount === 0, "Initial unread count for Org 1 User 1 is 0");

// 1.1 Create Notification
$notif1 = $notifService->createNotification(
    $org1,
    $user1,
    '[TEST] Test Notification 1',
    'This is a persistent test notification message.',
    'test_alert',
    'test_ref',
    101,
    'in_app'
);

assertTest(!empty($notif1['id']), "Notification 1 created with persistent database ID #{$notif1['id']}");
assertTest($notif1['organization_id'] === $org1, "Notification 1 organization_id is {$org1}");
assertTest($notif1['user_id'] === $user1, "Notification 1 user_id is {$user1}");
assertTest($notif1['title'] === '[TEST] Test Notification 1', "Notification 1 title matches");
assertTest($notif1['is_read'] == 0, "Notification 1 is initially unread");
assertTest(!empty($notif1['created_at']), "Notification 1 has created_at timestamp");

// 1.2 Verify persistence via direct SQL
$dbRow = $pdo->query("SELECT * FROM notifications WHERE id = {$notif1['id']}")->fetch(PDO::FETCH_ASSOC);
assertTest($dbRow !== false && $dbRow['title'] === '[TEST] Test Notification 1', "Direct DB query confirms persistent storage");

// 1.3 Backward compatible sendNotification
$notif2 = $notifService->sendNotification(
    $org1,
    $user1,
    '[TEST] Backward Compatible Alert',
    'Checking sendNotification persists properly.'
);
assertTest(!empty($notif2['id']), "sendNotification successfully persists to DB (ID #{$notif2['id']})");

// 1.4 Unread count
$unreadCount = $notifService->getUnreadCount($org1, $user1);
assertTest($unreadCount === 2, "Unread count correctly increments to 2");

// 1.5 Fetch user notifications
$fetched = $notifService->getUserNotifications($org1, $user1, 1, 10);
assertTest($fetched['total'] >= 2, "getUserNotifications returns total >= 2");
assertTest(count($fetched['data']) >= 2, "getUserNotifications data contains >= 2 items");
assertTest($fetched['data'][0]['id'] === $notif2['id'], "Most recent notification appears first");

// 1.6 Mark single as read
$marked = $notifService->markAsRead($org1, $notif1['id'], $user1);
assertTest($marked === true, "markAsRead returned true for Notification 1");

$dbRowAfter = $pdo->query("SELECT is_read, read_at FROM notifications WHERE id = {$notif1['id']}")->fetch(PDO::FETCH_ASSOC);
assertTest((int)$dbRowAfter['is_read'] === 1, "Database column is_read set to 1");
assertTest(!empty($dbRowAfter['read_at']), "Database column read_at timestamp populated");

$unreadCountAfter = $notifService->getUnreadCount($org1, $user1);
assertTest($unreadCountAfter === 1, "Unread count decremented to 1 after marking Notification 1 read");

// 1.7 Mark all as read
$allMarked = $notifService->markAllAsRead($org1, $user1);
assertTest($allMarked >= 1, "markAllAsRead successfully updated remaining unread notifications");
assertTest($notifService->getUnreadCount($org1, $user1) === 0, "Unread count is now 0 after markAllAsRead");

// =========================================================================
// SECTION 2: LOW-STOCK ALERT (Inventory Integration)
// =========================================================================
echo "\n--- Section 2: Low-Stock Alert ---\n";

// Ensure an inventory category exists in Org 1
$catId = $pdo->query("SELECT id FROM inventory_categories WHERE organization_id = {$org1} AND deleted_at IS NULL LIMIT 1")->fetchColumn();
if (!$catId) {
    $pdo->exec("INSERT INTO inventory_categories (organization_id, name, description, created_at, updated_at) VALUES ({$org1}, 'Cricket Equipment', 'Cricket test category', NOW(), NOW())");
    $catId = (int)$pdo->lastInsertId();
}

// Create an inventory item with quantity 20, min stock 5
$itemCode = 'INV-TEST-NOTIF-' . rand(1000, 9999);
$createdItem = $invService->createItem($org1, [
    'item_name' => 'Cricket Match Balls (Alert Test)',
    'category_id' => $catId,
    'unit' => 'piece',
    'quantity' => 20,
    'minimum_stock_level' => 5,
    'reorder_level' => 8,
    'unit_cost' => 250.00,
    'location_name' => 'Equipment Bay A',
    'status' => 'active',
], $user1);

$itemId = (int)$createdItem['id'];
assertTest($itemId > 0, "Created inventory item #{$itemId} with initial stock 20 (min: 5)");

// Stock is 20 > 5, low stock alert should NOT be triggered
$chk1 = $notifService->checkAndTriggerLowStock($org1, $itemId);
assertTest($chk1['triggered'] === false, "checkAndTriggerLowStock returns triggered=false when stock (20) > min (5)");

// Issue stock: deduct 17 units so new quantity is 3 (<= minimum_stock_level 5)
$tx = $invService->recordStockTransaction($org1, $itemId, [
    'transaction_type' => 'issue',
    'quantity' => 17,
    'remarks' => 'Issued 17 match balls for weekend tournament',
], $user1);

assertTest((float)$tx['new_quantity'] === 3.0, "Stock transaction deducted quantity from 20 to 3 (below min 5)");

// The automated trigger inside recordStockTransaction should have triggered low stock notification!
$lowStockNotif = $pdo->query("
    SELECT * FROM notifications 
    WHERE organization_id = {$org1} 
      AND notification_type = 'low_stock' 
      AND reference_type = 'inventory_item' 
      AND reference_id = {$itemId}
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

assertTest($lowStockNotif !== false, "Automated low-stock notification created in DB");
assertTest(str_contains($lowStockNotif['title'], 'Low Stock Alert'), "Notification title contains 'Low Stock Alert'");
assertTest(str_contains($lowStockNotif['message'], '3'), "Notification message accurately reports new quantity (3)");

// Test De-duplication:
// Another stock deduction happens (e.g. deduct 1 more unit -> quantity = 2)
// Since an unread low-stock notification already exists for this item, no duplicate should be created!
$prevNotifCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE organization_id = {$org1} AND reference_id = {$itemId} AND notification_type = 'low_stock'")->fetchColumn();

$tx2 = $invService->recordStockTransaction($org1, $itemId, [
    'transaction_type' => 'issue',
    'quantity' => 1,
    'remarks' => 'Issued 1 ball',
], $user1);

$newNotifCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE organization_id = {$org1} AND reference_id = {$itemId} AND notification_type = 'low_stock'")->fetchColumn();
assertTest($newNotifCount === $prevNotifCount, "De-duplication enforced: No duplicate unread low-stock notification created ({$newNotifCount} === {$prevNotifCount})");

// =========================================================================
// SECTION 3: EQUIPMENT RETURN ALERT (Overdue Return)
// =========================================================================
echo "\n--- Section 3: Equipment Return Alert ---\n";

// Create an equipment item in Org 1
$eqCode = 'EQP-TEST-NOTIF-' . rand(1000, 9999);
$createdEq = $eqService->createEquipment($org1, [
    'equipment_name' => 'High-Speed Bowling Machine (Alert Test)',
    'asset_code' => $eqCode,
    'serial_number' => 'BM-TEST-' . rand(10000, 99999),
    'condition_status' => 'good',
    'status' => 'available',
    'current_location' => 'Main Net 1',
], $user1);
$eqId = (int)$createdEq['id'];
assertTest($eqId > 0, "Created equipment unit #{$eqId} ({$eqCode})");

// Ensure an athlete exists in Org 1
$athId = $pdo->query("SELECT id FROM athletes WHERE organization_id = {$org1} AND deleted_at IS NULL LIMIT 1")->fetchColumn();
if (!$athId) {
    $pdo->exec("INSERT INTO athletes (organization_id, athlete_code, first_name, last_name, status, created_at, updated_at) VALUES ({$org1}, 'ATH-TEST-NOTIF', 'Rohan', 'Verma', 'active', NOW(), NOW())");
    $athId = (int)$pdo->lastInsertId();
}

// Assign equipment with an overdue return date (yesterday)
$yesterday = date('Y-m-d', strtotime('-2 days'));
$assignment = $eqService->assignEquipment($org1, $eqId, [
    'assignee_type' => 'athlete',
    'athlete_id' => $athId,
    'assigned_date' => date('Y-m-d', strtotime('-10 days')),
    'expected_return_date' => $yesterday,
    'condition_on_issue' => 'good',
    'notes' => 'Testing overdue alert trigger',
], $user1);

$assignId = (int)$assignment['assignment_id'];
assertTest($assignId > 0, "Assigned equipment with expected_return_date '{$yesterday}' (overdue)");

// Trigger overdue check
$overdueRes = $notifService->checkAndTriggerOverdueEquipment($org1);
assertTest($overdueRes['triggered'] === true, "checkAndTriggerOverdueEquipment triggered successfully");
assertTest($overdueRes['count'] >= 1, "Detected >= 1 overdue assignment");

// Verify notification in DB
$overdueNotif = $pdo->query("
    SELECT * FROM notifications 
    WHERE organization_id = {$org1} 
      AND notification_type = 'equipment_overdue' 
      AND reference_type = 'equipment_assignment' 
      AND reference_id = {$assignId}
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

assertTest($overdueNotif !== false, "Overdue equipment notification stored in DB");
assertTest(str_contains($overdueNotif['title'], 'Overdue Equipment Return'), "Overdue notification title matches expected format");
assertTest(str_contains($overdueNotif['message'], $yesterday), "Overdue notification message includes expected return date");

// Test De-duplication for overdue equipment
$overdueRes2 = $notifService->checkAndTriggerOverdueEquipment($org1);
$dupOverdueCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE organization_id = {$org1} AND reference_id = {$assignId} AND notification_type = 'equipment_overdue'")->fetchColumn();
assertTest($dupOverdueCount === 1, "De-duplication enforced: Overdue equipment notification not duplicated");

// Return the equipment to clean up state
$eqService->returnEquipment($org1, $eqId, ['condition_on_return' => 'good'], $user1);

// =========================================================================
// SECTION 4: BUDGET ALERT (Finance Integration)
// =========================================================================
echo "\n--- Section 4: Budget Alert ---\n";

// Ensure a finance category exists in Org 1
$finCatId = $pdo->query("SELECT id FROM finance_categories WHERE organization_id = {$org1} AND category_type = 'expense' LIMIT 1")->fetchColumn();
if (!$finCatId) {
    $pdo->exec("INSERT INTO finance_categories (organization_id, name, category_type, status, created_at, updated_at) VALUES ({$org1}, 'Team Travel & Camps', 'expense', 'active', NOW(), NOW())");
    $finCatId = (int)$pdo->lastInsertId();
}

// Create a budget of 10,000 for a test period
$bStart = '2026-11-01';
$bEnd = '2026-11-30';
$createdBudget = $finService->createBudget($org1, [
    'budget_name' => 'Monthly Tour Budget (Alert Test)',
    'financial_year' => '2026-2027',
    'start_date' => $bStart,
    'end_date' => $bEnd,
    'total_budget' => 10000.00,
    'status' => 'active',
    'items' => [
        [
            'finance_category_id' => $finCatId,
            'allocated_amount' => 10000.00,
            'notes' => 'Allocated for tour test',
        ]
    ]
], $user1);

$budgetId = (int)$createdBudget['id'];
assertTest($budgetId > 0, "Created active budget #{$budgetId} of ₹10,000 for period {$bStart} to {$bEnd}");

// Budget utilization initially 0% (< 90%)
$bCheck1 = $notifService->checkAndTriggerBudgetThreshold($org1, $budgetId, 90.0);
assertTest($bCheck1['triggered'] === false, "Budget check initially returns triggered=false (0% < 90%)");

// Record an expense of ₹9,200 (92% utilization)
$exp = $finService->recordExpense($org1, [
    'description' => 'Tour Flight Tickets (Crossed 90% threshold)',
    'amount' => 9200.00,
    'finance_category_id' => $finCatId,
    'expense_date' => '2026-11-15',
    'payment_status' => 'approved',
], $user1);

assertTest(!empty($exp['id']), "Recorded expense #{$exp['id']} of ₹9,200 under budget period");

// Check if automated budget alert was triggered
$budgetNotif = $pdo->query("
    SELECT * FROM notifications 
    WHERE organization_id = {$org1} 
      AND notification_type = 'budget_alert' 
      AND reference_type = 'budget' 
      AND reference_id = {$budgetId}
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

assertTest($budgetNotif !== false, "Automated budget utilization notification created in DB");
assertTest(str_contains($budgetNotif['title'], 'Budget Utilization Alert'), "Notification title contains 'Budget Utilization Alert'");
assertTest(str_contains($budgetNotif['message'], '92%'), "Notification message reports 92% utilization");

// Test De-duplication for Budget Alert
$dupBCheck = $notifService->checkAndTriggerBudgetThreshold($org1, $budgetId, 90.0);
assertTest($dupBCheck['triggered'] === false && str_contains($dupBCheck['reason'], 'already exists'), "De-duplication enforced: active unread budget alert prevented duplicate notification");

// =========================================================================
// SECTION 5: STRICT MULTI-TENANT ISOLATION
// =========================================================================
echo "\n--- Section 5: Strict Multi-Tenant Isolation ---\n";

// Ensure a user exists for Org 2
$user2Id = 992;
$u2Row = $pdo->query("SELECT id FROM users WHERE id = {$user2Id}")->fetchColumn();
if (!$u2Row) {
    $pdo->exec("INSERT INTO users (id, uuid, username, email, password, first_name, last_name, status, created_at, updated_at) VALUES ({$user2Id}, UUID(), 'org2admin', 'org2admin@khelsutra.local', 'hash', 'Org2', 'Admin', 'active', NOW(), NOW())");
}
$ou2 = $pdo->query("SELECT id FROM organization_users WHERE organization_id = {$org2} AND user_id = {$user2Id}")->fetchColumn();
if (!$ou2) {
    $pdo->exec("INSERT INTO organization_users (organization_id, user_id, role_id, access_status, created_at, updated_at) VALUES ({$org2}, {$user2Id}, 2, 'active', NOW(), NOW())");
}

// Create notification in Org 2 for User 2
$notifOrg2 = $notifService->createNotification(
    $org2,
    $user2Id,
    '[ORG2] Org 2 Confidential Alert',
    'Secret data belonging strictly to Org 2.'
);
assertTest(!empty($notifOrg2['id']), "Created notification for Org 2 User 2 (ID #{$notifOrg2['id']})");

// 5.1 User in Org 1 cannot fetch Org 2 notification
$crossFetch = $notifService->getNotification($org1, $notifOrg2['id'], $user1);
assertTest($crossFetch === null, "Org 1 User 1 cannot get Notification #{$notifOrg2['id']} (returns null)");

// 5.2 User in Org 1 cannot mark Org 2 notification as read
$crossMark = $notifService->markAsRead($org1, $notifOrg2['id'], $user1);
assertTest($crossMark === false, "Org 1 User 1 cannot mark Org 2 notification as read (returns false)");

// 5.3 Org 2 notification is NOT in Org 1 user list
$org1List = $notifService->getUserNotifications($org1, $user1, 1, 50);
$leaked = false;
foreach ($org1List['data'] as $item) {
    if ($item['id'] === $notifOrg2['id']) {
        $leaked = true;
        break;
    }
}
assertTest(!$leaked, "Org 2 notification does not leak into Org 1 notification list");

// 5.4 Org 2 unread count is independent
$org2Unread = $notifService->getUnreadCount($org2, $user2Id);
assertTest($org2Unread === 1, "Org 2 User 2 has independent unread count of 1");

// Clean up test data
$pdo->exec("DELETE FROM notifications WHERE id IN ({$notif1['id']}, {$notif2['id']}, {$notifOrg2['id']})");
$pdo->exec("DELETE FROM notifications WHERE organization_id = {$org1} AND notification_type IN ('low_stock', 'equipment_overdue', 'budget_alert')");
$pdo->exec("DELETE FROM stock_transactions WHERE inventory_item_id = {$itemId}");
$pdo->exec("DELETE FROM inventory_items WHERE id = {$itemId}");
$pdo->exec("DELETE FROM equipment_assignments WHERE id = {$assignId}");
$pdo->exec("DELETE FROM equipment WHERE id = {$eqId}");
$pdo->exec("DELETE FROM expenses WHERE id = {$exp['id']}");
$pdo->exec("DELETE FROM budget_items WHERE budget_id = {$budgetId}");
$pdo->exec("DELETE FROM budgets WHERE id = {$budgetId}");

echo "\n=======================================================\n";
echo "Notification Service Verification Complete: {$passed} passed, {$failed} failed.\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
