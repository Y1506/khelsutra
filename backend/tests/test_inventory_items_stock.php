<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/app.php';

use App\Models\InventoryItem;
use App\Models\StockTransaction;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\InventoryCategoryService;
use Illuminate\Database\Capsule\Manager as DB;

echo "=== KhelSutra Phase 1B-2: Inventory Items & Stock Operations Verification ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $desc, bool $condition) {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $desc\n";
        $passCount++;
    } else {
        echo " [FAIL] $desc\n";
        $failCount++;
    }
}

try {
    $invService = new InventoryService();
    $catService = new InventoryCategoryService();
    $org1 = 1;

    // Check if test org 2 exists, otherwise create a mock or use org 2
    $org2Row = DB::table('organizations')->where('id', 2)->first();
    if (!$org2Row) {
        DB::table('organizations')->insert([
            'id' => 2,
            'organization_code' => 'ORG-TEST-2',
            'name' => 'Secondary Sports Club',
            'country' => 'India',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $createdOrg2 = true;
    } else {
        $createdOrg2 = false;
    }
    $org2 = 2;

    // Ensure categories exist for both orgs
    $cat1 = $catService->createCategory($org1, ['name' => 'Footballs Test Category'], 1);
    $cat2 = $catService->createCategory($org2, ['name' => 'Org 2 Category'], 1);

    // ==========================================
    // 1. Inventory Item CRUD
    // ==========================================
    echo "\n--- 1. Inventory Item CRUD ---\n";
    $item1 = $invService->createItem($org1, [
        'item_name' => 'FIFA Pro Match Ball Size 5',
        'category_id' => $cat1['id'],
        'unit' => 'piece',
        'quantity' => 20, // Opening stock
        'minimum_stock_level' => 5,
        'reorder_level' => 10,
        'unit_cost' => 1500.00,
        'location_name' => 'Rack A-1',
        'status' => 'active',
        'description' => 'Match grade footballs'
    ], 1);

    assertTest("Item created successfully", !empty($item1['id']));
    assertTest("Item belongs to Org 1", (int)$item1['organization_id'] === $org1);
    assertTest("Item initial quantity is 20", (float)$item1['quantity'] === 20.0);
    assertTest("Item code generated with ITM- prefix", str_starts_with($item1['item_code'], 'ITM-'));

    // Check opening stock transaction was automatically created
    $txs = $item1['transactions'] ?? [];
    assertTest("Opening stock transaction created", count($txs) >= 1 && $txs[0]['transaction_type'] === 'opening');
    assertTest("Opening stock transaction quantity is 20", (float)($txs[0]['quantity'] ?? 0) === 20.0);

    // Read item
    $retrieved = $invService->getItem($org1, $item1['id']);
    assertTest("getItem retrieves item matching id", $retrieved !== null && $retrieved['id'] == $item1['id']);
    assertTest("getItem includes category name", !empty($retrieved['category_name']));

    // Update item
    $updateOk = $invService->updateItem($org1, $item1['id'], [
        'item_name' => 'FIFA Pro Match Ball Size 5 (Official)',
        'location_name' => 'Rack A-2',
        'reorder_level' => 12,
    ], 1);
    assertTest("updateItem returns true", $updateOk === true);
    $afterUpdate = $invService->getItem($org1, $item1['id']);
    assertTest("Item name updated in DB", $afterUpdate['item_name'] === 'FIFA Pro Match Ball Size 5 (Official)');
    assertTest("Item location updated in DB", $afterUpdate['location_name'] === 'Rack A-2');
    assertTest("Item reorder level updated in DB", (float)$afterUpdate['reorder_level'] === 12.0);

    // Validation: empty name
    try {
        $invService->createItem($org1, ['item_name' => ''], 1);
        assertTest("Empty item_name rejected", false);
    } catch (\InvalidArgumentException $e) {
        assertTest("Empty item_name rejected", true);
    }

    // Validation: negative initial quantity
    try {
        $invService->createItem($org1, ['item_name' => 'Negative Test', 'quantity' => -10], 1);
        assertTest("Negative initial quantity rejected", false);
    } catch (\InvalidArgumentException $e) {
        assertTest("Negative initial quantity rejected", true);
    }

    // ==========================================
    // 2. Stock In Operations
    // ==========================================
    echo "\n--- 2. Stock In (Purchase & Return) ---\n";
    // Stock In via purchase
    $stockIn1 = $invService->recordStockTransaction($org1, $item1['id'], [
        'transaction_type' => 'purchase',
        'quantity' => 15,
        'remarks' => 'Supplier Delivery PO-101'
    ], 1);
    assertTest("Stock in (purchase) new quantity is 35 (20 + 15)", (float)$stockIn1['new_quantity'] === 35.0);

    $itemAfterIn1 = $invService->getItem($org1, $item1['id']);
    assertTest("Item quantity in DB updated to 35", (float)$itemAfterIn1['quantity'] === 35.0);

    // Stock In via return
    $stockIn2 = $invService->recordStockTransaction($org1, $item1['id'], [
        'transaction_type' => 'return',
        'quantity' => 5,
        'remarks' => 'Returned from Senior Team practice'
    ], 1);
    assertTest("Stock in (return) new quantity is 40 (35 + 5)", (float)$stockIn2['new_quantity'] === 40.0);

    // ==========================================
    // 3. Stock Out Operations
    // ==========================================
    echo "\n--- 3. Stock Out (Issue & Damage) ---\n";
    // Issue stock out
    $stockOut1 = $invService->recordStockTransaction($org1, $item1['id'], [
        'transaction_type' => 'issue',
        'quantity' => 10,
        'remarks' => 'Issued to Academy U-17 squad'
    ], 1);
    assertTest("Stock out (issue) new quantity is 30 (40 - 10)", (float)$stockOut1['new_quantity'] === 30.0);

    // Damage stock out
    $stockOut2 = $invService->recordStockTransaction($org1, $item1['id'], [
        'transaction_type' => 'damage',
        'quantity' => 2,
        'remarks' => 'Punctured during training'
    ], 1);
    assertTest("Stock out (damage) new quantity is 28 (30 - 2)", (float)$stockOut2['new_quantity'] === 28.0);

    // ==========================================
    // 4. Insufficient Stock Safeguard
    // ==========================================
    echo "\n--- 4. Insufficient Stock Safeguard (No Negative Stock) ---\n";
    try {
        // Current quantity is 28, attempt to deduct 50
        $invService->recordStockTransaction($org1, $item1['id'], [
            'transaction_type' => 'issue',
            'quantity' => 50,
            'remarks' => 'Excessive deduction attempt'
        ], 1);
        assertTest("Excessive stock deduction rejected", false);
    } catch (\Exception $e) {
        assertTest("Excessive stock deduction rejected: " . $e->getMessage(), true);
    }

    $itemCheck = $invService->getItem($org1, $item1['id']);
    assertTest("Quantity remained strictly at 28 after rejected deduction", (float)$itemCheck['quantity'] === 28.0);

    // ==========================================
    // 5. Stock Adjustment Operations
    // ==========================================
    echo "\n--- 5. Stock Adjustment ---\n";
    // Case A: Delta positive adjustment
    $adjUp = $invService->recordStockTransaction($org1, $item1['id'], [
        'transaction_type' => 'adjustment',
        'quantity' => 4,
        'remarks' => 'Audit found 4 extra balls'
    ], 1);
    assertTest("Adjustment delta increase (+4) brings quantity to 32", (float)$adjUp['new_quantity'] === 32.0);

    // Case B: Delta decrease adjustment (using adjustment_dec or action=decrease)
    $adjDown = $invService->recordStockTransaction($org1, $item1['id'], [
        'transaction_type' => 'adjustment_dec',
        'quantity' => 7,
        'remarks' => 'Physical recount downward correction'
    ], 1);
    assertTest("Adjustment delta decrease (-7) brings quantity to 25", (float)$adjDown['new_quantity'] === 25.0);

    // Case C: Target exact physical count adjustment
    $adjTarget = $invService->recordStockTransaction($org1, $item1['id'], [
        'transaction_type' => 'adjustment',
        'new_quantity' => 8, // Set exact count to 8
        'remarks' => 'Quarterly physical stocktake'
    ], 1);
    assertTest("Target exact count adjustment sets quantity directly to 8", (float)$adjTarget['new_quantity'] === 8.0);

    // Case D: Target negative adjustment must be rejected
    try {
        $invService->recordStockTransaction($org1, $item1['id'], [
            'transaction_type' => 'adjustment',
            'new_quantity' => -5,
        ], 1);
        assertTest("Negative target adjustment rejected", false);
    } catch (\Exception $e) {
        assertTest("Negative target adjustment rejected", true);
    }

    // ==========================================
    // 6. Low Stock Detection
    // ==========================================
    echo "\n--- 6. Low Stock Detection ---\n";
    // Item quantity is now 8, reorder_level is 12 (8 <= 12 -> low stock!)
    $lowStockItem = $invService->getItem($org1, $item1['id']);
    assertTest("is_low_stock flag is true when quantity (8) <= reorder_level (12)", (bool)$lowStockItem['is_low_stock'] === true);

    $lowStockList = $invService->getLowStockItems($org1);
    $inLowStockList = in_array($item1['id'], array_column($lowStockList, 'id'));
    assertTest("Item appears in getLowStockItems()", $inLowStockList);

    $filterLow = $invService->listItems($org1, 1, 15, null, null, 'low_stock');
    $inFilteredLow = in_array($item1['id'], array_column($filterLow['data'], 'id'));
    assertTest("listItems with status='low_stock' includes low stock item", $inFilteredLow);

    // Replenish item to bring above reorder level
    $replenish = $invService->recordStockTransaction($org1, $item1['id'], [
        'transaction_type' => 'purchase',
        'quantity' => 20, // 8 + 20 = 28 > 12
    ], 1);
    $replenishedItem = $invService->getItem($org1, $item1['id']);
    assertTest("is_low_stock flag is false when quantity (28) > reorder_level (12)", (bool)$replenishedItem['is_low_stock'] === false);

    // ==========================================
    // 7. Stock Transaction History
    // ==========================================
    echo "\n--- 7. Stock Transaction History Ledger ---\n";
    $history = $invService->getStockTransactions($org1, $item1['id'], 1, 50);
    assertTest("Stock transactions ledger retrieved", !empty($history['data']));
    // Expected transaction count: opening(20) + purchase(15) + return(5) + issue(10) + damage(2) + adjUp(4) + adjDown(7) + adjTarget(diff) + replenish(20) = 9
    assertTest("Total transactions count recorded accurately (>= 8)", $history['total'] >= 8);

    // Verify chronological order (most recent first)
    $firstTx = $history['data'][0];
    assertTest("Latest transaction is replenish purchase", $firstTx['transaction_type'] === 'purchase' && (float)$firstTx['quantity'] === 20.0);

    // ==========================================
    // 8. Organization / Tenant Isolation
    // ==========================================
    echo "\n--- 8. Multi-Tenancy & Tenant Isolation ---\n";
    // Create item in Org 2
    $item2 = $invService->createItem($org2, [
        'item_name' => 'Org 2 Badminton Rackets',
        'category_id' => $cat2['id'],
        'unit' => 'piece',
        'quantity' => 15,
        'minimum_stock_level' => 2,
        'reorder_level' => 5,
        'unit_cost' => 800.00,
    ], 1);
    assertTest("Org 2 item created", !empty($item2['id']));

    // Org 1 cannot read Org 2 item
    $crossGet = $invService->getItem($org1, $item2['id']);
    assertTest("Org 1 cannot read Org 2 item (cross-tenant read blocked)", $crossGet === null);

    // Org 1 cannot update Org 2 item
    try {
        $invService->updateItem($org1, $item2['id'], ['item_name' => 'Hacked Item']);
        assertTest("Org 1 cannot update Org 2 item", false);
    } catch (\InvalidArgumentException $e) {
        assertTest("Org 1 cannot update Org 2 item (cross-tenant write blocked)", true);
    }

    // Org 1 cannot record transactions on Org 2 item
    try {
        $invService->recordStockTransaction($org1, $item2['id'], ['transaction_type' => 'issue', 'quantity' => 1]);
        assertTest("Org 1 cannot record transaction on Org 2 item", false);
    } catch (\Exception $e) {
        assertTest("Org 1 cannot record transaction on Org 2 item (cross-tenant stock blocked)", true);
    }

    // Org 1 cannot delete Org 2 item
    try {
        $invService->deleteItem($org1, $item2['id']);
        assertTest("Org 1 cannot delete Org 2 item", false);
    } catch (\InvalidArgumentException $e) {
        assertTest("Org 1 cannot delete Org 2 item (cross-tenant delete blocked)", true);
    }

    // Org 1 cannot view Org 2 transaction history
    $org1CrossHistory = $invService->getStockTransactions($org1, $item2['id']);
    assertTest("Org 1 cannot retrieve Org 2 item transaction history", empty($org1CrossHistory['data']));

    // Org 1 item list does not include Org 2 items
    $org1List = $invService->listItems($org1, 1, 100);
    $inOrg1List = in_array($item2['id'], array_column($org1List['data'], 'id'));
    assertTest("Org 1 list excludes Org 2 items", !$inOrg1List);

    // ==========================================
    // 9. Soft Delete Verification
    // ==========================================
    echo "\n--- 9. Soft Delete Verification ---\n";
    $delOk = $invService->deleteItem($org1, $item1['id'], 1);
    assertTest("deleteItem returns true", $delOk === true);

    $getItemDeleted = $invService->getItem($org1, $item1['id']);
    assertTest("Soft-deleted item excluded from getItem", $getItemDeleted === null);

    $listAfterDel = $invService->listItems($org1, 1, 100);
    $inListAfter = in_array($item1['id'], array_column($listAfterDel['data'], 'id'));
    assertTest("Soft-deleted item excluded from listItems", !$inListAfter);

    $rawItemRow = DB::table('inventory_items')->where('id', $item1['id'])->first();
    assertTest("Row preserved in DB with deleted_at timestamp", $rawItemRow !== null && !empty($rawItemRow->deleted_at));

    // Eloquent soft delete behavior
    $eloquentActive = InventoryItem::find($item1['id']);
    assertTest("Eloquent find() excludes soft-deleted item", $eloquentActive === null);

    $eloquentTrashed = InventoryItem::withTrashed()->find($item1['id']);
    assertTest("Eloquent withTrashed() finds soft-deleted item", $eloquentTrashed !== null);

    // ==========================================
    // Cleaning up test records
    // ==========================================
    echo "\n--- Cleaning up test records ---\n";
    DB::table('stock_transactions')->where('inventory_item_id', $item1['id'])->delete();
    DB::table('stock_transactions')->where('inventory_item_id', $item2['id'])->delete();
    DB::table('inventory_items')->where('id', $item1['id'])->delete();
    DB::table('inventory_items')->where('id', $item2['id'])->delete();
    DB::table('inventory_categories')->where('id', $cat1['id'])->delete();
    DB::table('inventory_categories')->where('id', $cat2['id'])->delete();
    if ($createdOrg2) {
        DB::table('organizations')->where('id', 2)->delete();
    }
    echo "Cleanup complete.\n";

} catch (\Throwable $e) {
    echo " [ERROR] Unexpected exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failCount++;
}

echo "\n=============================================\n";
echo "SUMMARY: Passed: {$passCount}, Failed: {$failCount}\n";
echo "=============================================\n";

exit($failCount > 0 ? 1 : 0);
