<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/app.php';

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Services\Inventory\InventoryCategoryService;
use Illuminate\Database\Capsule\Manager as DB;

echo "=== KhelSutra Phase 1B: Inventory Category Verification ===\n\n";

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
    $service = new InventoryCategoryService();
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

    // 1. Create Category
    echo "\n--- 1. Category Creation ---\n";
    $cat1 = $service->createCategory($org1, [
        'name' => 'Footballs & Training Balls',
        'description' => 'Official training and match balls',
        'status' => 'active'
    ], 1);

    assertTest("Category created successfully", !empty($cat1['id']));
    assertTest("Category belongs to Org 1", (int)$cat1['organization_id'] === $org1);
    assertTest("Category name matches", $cat1['name'] === 'Footballs & Training Balls');
    assertTest("Category status is active", $cat1['status'] === 'active');
    assertTest("Category item_count is 0 initially", (int)$cat1['item_count'] === 0);

    // 2. Read Category
    echo "\n--- 2. Category Retrieval ---\n";
    $retrieved = $service->getCategory($org1, $cat1['id']);
    assertTest("getCategory returns matching record", $retrieved !== null && $retrieved['id'] == $cat1['id']);

    $list = $service->listCategories($org1);
    $foundInList = false;
    foreach ($list as $item) {
        if ($item['id'] == $cat1['id']) {
            $foundInList = true;
            break;
        }
    }
    assertTest("listCategories includes newly created category", $foundInList);

    // 3. Update Category
    echo "\n--- 3. Category Update ---\n";
    $updated = $service->updateCategory($org1, $cat1['id'], [
        'name' => 'Match & Training Footballs',
        'description' => 'All FIFA approved soccer balls',
        'status' => 'active'
    ], 1);
    assertTest("Category name updated", $updated['name'] === 'Match & Training Footballs');
    assertTest("Category description updated", $updated['description'] === 'All FIFA approved soccer balls');

    // 4. Validation & Duplicate Name
    echo "\n--- 4. Validation & Uniqueness ---\n";
    try {
        $service->createCategory($org1, ['name' => ''], 1);
        assertTest("Empty name rejected", false);
    } catch (\InvalidArgumentException $e) {
        assertTest("Empty name rejected", true);
    }

    try {
        $service->createCategory($org1, ['name' => str_repeat('A', 101)], 1);
        assertTest("Name exceeding 100 chars rejected", false);
    } catch (\InvalidArgumentException $e) {
        assertTest("Name exceeding 100 chars rejected", true);
    }

    try {
        $service->createCategory($org1, ['name' => 'Match & Training Footballs'], 1);
        assertTest("Duplicate name in Org 1 rejected", false);
    } catch (\InvalidArgumentException $e) {
        assertTest("Duplicate name in Org 1 rejected with message: " . $e->getMessage(), true);
    }

    // 5. Tenant Isolation
    echo "\n--- 5. Organization / Tenant Isolation ---\n";
    // Org 2 should be allowed to use the SAME category name
    $cat2 = $service->createCategory($org2, [
        'name' => 'Match & Training Footballs',
        'description' => 'Org 2 balls'
    ], 1);
    assertTest("Org 2 can create category with same name (isolated per tenant)", !empty($cat2['id']));

    // Org 1 cannot read Org 2 category
    $crossRead = $service->getCategory($org1, $cat2['id']);
    assertTest("Org 1 cannot access Org 2 category (tenant read isolated)", $crossRead === null);

    // Org 1 cannot update Org 2 category
    try {
        $service->updateCategory($org1, $cat2['id'], ['name' => 'Hacked Name']);
        assertTest("Org 1 cannot update Org 2 category", false);
    } catch (\InvalidArgumentException $e) {
        assertTest("Org 1 cannot update Org 2 category (tenant write isolated)", true);
    }

    // Org 1 cannot delete Org 2 category
    try {
        $service->deleteCategory($org1, $cat2['id']);
        assertTest("Org 1 cannot delete Org 2 category", false);
    } catch (\InvalidArgumentException $e) {
        assertTest("Org 1 cannot delete Org 2 category (tenant delete isolated)", true);
    }

    // 6. Activate / Deactivate
    echo "\n--- 6. Activate / Deactivate Status Toggle ---\n";
    $deactivated = $service->setStatus($org1, $cat1['id'], 'inactive', 1);
    assertTest("Category status set to inactive", $deactivated['status'] === 'inactive');

    $activeList = $service->listCategories($org1, 'active');
    $inActiveList = in_array($cat1['id'], array_column($activeList, 'id'));
    assertTest("Inactive category excluded from active list", !$inActiveList);

    $inactiveList = $service->listCategories($org1, 'inactive');
    $inInactiveList = in_array($cat1['id'], array_column($inactiveList, 'id'));
    assertTest("Inactive category included in inactive list", $inInactiveList);

    $reactivated = $service->setStatus($org1, $cat1['id'], 'active', 1);
    assertTest("Category reactivated successfully", $reactivated['status'] === 'active');

    // 7. Referential Integrity Safeguard (Linked Items)
    echo "\n--- 7. Referential Integrity Safeguard ---\n";
    // Insert a dummy item assigned to cat1
    $itemId = DB::table('inventory_items')->insertGetId([
        'organization_id' => $org1,
        'category_id' => $cat1['id'],
        'item_code' => 'TEST-ITEM-001',
        'item_name' => 'Test Training Football',
        'unit' => 'piece',
        'quantity' => 10,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    // Check item_count subquery
    $catWithItem = $service->getCategory($org1, $cat1['id']);
    assertTest("item_count correctly reflects linked item", (int)$catWithItem['item_count'] === 1);

    // Attempt to delete category with linked items
    try {
        $service->deleteCategory($org1, $cat1['id']);
        assertTest("Deletion prevented when active items exist", false);
    } catch (\Exception $e) {
        assertTest("Deletion prevented when active items exist (Message: " . $e->getMessage() . ")", true);
    }

    // Remove the test item
    DB::table('inventory_items')->where('id', $itemId)->delete();

    // 8. Soft Delete
    echo "\n--- 8. Soft Delete Verification ---\n";
    $deleted = $service->deleteCategory($org1, $cat1['id'], 1);
    assertTest("Soft delete returns true", $deleted === true);

    $getDeleted = $service->getCategory($org1, $cat1['id']);
    assertTest("Soft-deleted category excluded from getCategory", $getDeleted === null);

    $listAfterDelete = $service->listCategories($org1);
    $inListAfter = in_array($cat1['id'], array_column($listAfterDelete, 'id'));
    assertTest("Soft-deleted category excluded from listCategories", !$inListAfter);

    // Verify row still exists in DB with deleted_at set (Soft delete verified)
    $rawRow = DB::table('inventory_categories')->where('id', $cat1['id'])->first();
    assertTest("Row preserved in DB with deleted_at timestamp", $rawRow !== null && !empty($rawRow->deleted_at));

    // Verify Eloquent model soft-deleting behavior
    $eloquentFind = InventoryCategory::find($cat1['id']);
    assertTest("Eloquent find() excludes soft-deleted category", $eloquentFind === null);

    $eloquentWithTrashed = InventoryCategory::withTrashed()->find($cat1['id']);
    assertTest("Eloquent withTrashed() finds soft-deleted category", $eloquentWithTrashed !== null);

    // 9. Re-creating a category with the same name after soft delete
    echo "\n--- 9. Re-create / Restore after Soft Delete ---\n";
    $recreated = $service->createCategory($org1, [
        'name' => 'Match & Training Footballs',
        'description' => 'Restored soccer balls',
        'status' => 'active'
    ], 1);
    assertTest("Can re-create category with previously soft-deleted name", !empty($recreated['id']));
    assertTest("Re-created category is active and non-deleted", $recreated['status'] === 'active' && $recreated['deleted_at'] === null);

    // Clean up test records
    echo "\n--- Cleaning up test records ---\n";
    DB::table('inventory_categories')->where('id', $recreated['id'])->delete();
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
