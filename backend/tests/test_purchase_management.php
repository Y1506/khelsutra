<?php

/**
 * KhelSutra Phase 2B: Purchase Management & Procurement Workflow Verification Script
 * Validates Purchase Requests (create, items, submit, reject, approve, cancel, multi-tenancy),
 * Purchase Orders (standalone, conversion from approved PR, pricing/taxes/discounts calculations, status transitions),
 * Goods Receipt (partial receipt, full receipt, excess receipt guard, PO status progression),
 * Atomic Inventory Integration (inventory_items.current_quantity increment, stock_transactions record),
 * and Multi-Tenant Isolation.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/app.php';

use App\Services\Purchase\PurchaseService;
use App\Services\Vendor\VendorService;
use App\Services\Inventory\InventoryService;

$pdo = \App\Services\BaseService::getDatabaseConnection();
$purchaseService = new PurchaseService();
$vendorService = new VendorService();
$inventoryService = new InventoryService();

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

echo "=== KhelSutra Phase 2B: Purchase Management Verification ===\n\n";

$org1 = 1;
$org2 = 2;
$userId = 1;

// Ensure Organization 2 exists in organizations table
$org2Row = $pdo->query("SELECT id FROM organizations WHERE id = {$org2}")->fetchColumn();
if (!$org2Row) {
    $pdo->exec("INSERT INTO organizations (id, organization_code, name, country, status, created_at, updated_at) VALUES ({$org2}, 'ORG-TEST-2', 'Secondary Sports Academy', 'India', 'active', NOW(), NOW())");
}

// Cleanup any old test purchase and related records
$pdo->exec("DELETE FROM stock_transactions WHERE inventory_item_id IN (SELECT id FROM inventory_items WHERE item_name LIKE '%TEST_PURCHASE%') OR remarks LIKE '%TEST_PURCHASE%'");
$pdo->exec("DELETE FROM goods_receipts WHERE receipt_number LIKE 'TEST-GRN%' OR receipt_number LIKE 'GRN-%' OR remarks LIKE '%TEST_PURCHASE%'");
$pdo->exec("DELETE FROM purchase_order_items WHERE purchase_order_id IN (SELECT id FROM purchase_orders WHERE po_number LIKE 'TEST-PO%' OR po_number LIKE 'PO-%' OR notes LIKE '%TEST_PURCHASE%')");
$pdo->exec("DELETE FROM purchase_orders WHERE po_number LIKE 'TEST-PO%' OR po_number LIKE 'PO-%' OR notes LIKE '%TEST_PURCHASE%'");
$pdo->exec("DELETE FROM purchase_request_items WHERE purchase_request_id IN (SELECT id FROM purchase_requests WHERE request_reference LIKE 'TEST-PR%' OR request_reference LIKE 'PR-%' OR purpose LIKE '%TEST_PURCHASE%')");
$pdo->exec("DELETE FROM purchase_requests WHERE request_reference LIKE 'TEST-PR%' OR request_reference LIKE 'PR-%' OR purpose LIKE '%TEST_PURCHASE%'");
$pdo->exec("DELETE FROM equipment WHERE inventory_item_id IN (SELECT id FROM inventory_items WHERE item_name LIKE '%TEST_PURCHASE%')");
$pdo->exec("DELETE FROM inventory_items WHERE item_name LIKE '%TEST_PURCHASE%'");
$pdo->exec("DELETE FROM vendors WHERE company_name LIKE '%TEST_PURCHASE%'");

// 0. Setup test vendor and inventory item for Org 1 and vendor for Org 2
$vendor1 = $vendorService->createVendor($org1, [
    'company_name' => 'Apex Sports Suppliers (TEST_PURCHASE)',
    'vendor_type' => 'equipment',
    'contact_person' => 'Sunil Verma',
    'email' => 'sunil@apex-sports-test.com',
    'phone' => '+91 98765 22222',
    'status' => 'active',
], $userId);

$vendor2 = $vendorService->createVendor($org2, [
    'company_name' => 'Org2 Sports Gear (TEST_PURCHASE)',
    'vendor_type' => 'inventory',
    'contact_person' => 'Anil Kapoor',
    'email' => 'anil@org2-gear-test.com',
    'phone' => '+91 98765 33333',
    'status' => 'active',
], $userId);

// Fetch an inventory category for Org 1
$catId = $pdo->query("SELECT id FROM inventory_categories WHERE organization_id = {$org1} AND deleted_at IS NULL LIMIT 1")->fetchColumn();
if (!$catId) {
    $pdo->exec("INSERT INTO inventory_categories (organization_id, name, description, status, created_at, updated_at) VALUES ({$org1}, 'Balls & Gear (TEST)', 'Category description', 'active', NOW(), NOW())");
    $catId = $pdo->lastInsertId();
}

// Create a tracked inventory item for Org 1 with initial quantity 10
$invItem = $inventoryService->createItem($org1, [
    'category_id' => $catId,
    'item_name' => 'Match Basketball Size 7 (TEST_PURCHASE)',
    'item_code' => 'BB-SZ7-PURCH-TEST',
    'unit' => 'piece',
    'unit_cost' => 1200.00,
    'quantity' => 10,
    'minimum_stock_level' => 5,
    'reorder_level' => 10,
    'status' => 'active',
], $userId);
$invItemId = $invItem['id'];

assertTest(!empty($vendor1['id']) && !empty($vendor2['id']), "Created test vendors for Org 1 and Org 2");
assertTest(!empty($invItemId), "Created tracked inventory item with initial quantity 10 (ID: {$invItemId})");

// ==========================================
// SECTION 1: PURCHASE REQUEST MANAGEMENT
// ==========================================
echo "\n--- Section 1: Purchase Request Management ---\n";

// 1.1 Validation: Creation without items must fail
try {
    $purchaseService->createPurchaseRequest($org1, [
        'purpose' => 'Invalid PR without items',
        'items' => [],
    ], $userId);
    assertTest(false, "Creating PR with empty items must throw InvalidArgumentException");
} catch (\InvalidArgumentException $e) {
    assertTest(true, "Validation correctly prevented creating PR with empty items");
}

// 1.2 Create Purchase Request with valid items
$pr1 = $purchaseService->createPurchaseRequest($org1, [
    'purpose' => 'Quarterly Basketball Replenishment (TEST_PURCHASE)',
    'required_date' => date('Y-m-d', strtotime('+14 days')),
    'items' => [
        [
            'inventory_item_id' => $invItemId,
            'item_name' => 'Match Basketball Size 7',
            'quantity' => 20,
            'estimated_unit_cost' => 1200.00,
            'description' => 'Official FIBA approved synthetic leather composite',
        ],
        [
            'inventory_item_id' => null,
            'item_name' => 'Agility Training Cones Set',
            'quantity' => 10,
            'estimated_unit_cost' => 500.00,
            'description' => 'High visibility fluorescent orange 12-inch cones',
        ],
    ],
], $userId);

assertTest(!empty($pr1['id']) && $pr1['id'] > 0, "Created purchase request with 2 items (ID: {$pr1['id']})");
assertTest(str_starts_with($pr1['request_reference'], 'PR-'), "PR reference auto-generated: '{$pr1['request_reference']}'");
assertTest($pr1['status'] === 'draft', "Initial PR status is 'draft'");
assertTest((float)$pr1['total_estimated_cost'] == 29000.00, "Calculated total estimated cost correctly: 20*1200 + 10*500 = 29000.00");

// 1.3 View & List PR
$fetchedPr = $purchaseService->getPurchaseRequest($org1, $pr1['id']);
assertTest(count($fetchedPr['items']) === 2, "Fetched PR contains exactly 2 line items");
assertTest($fetchedPr['items'][0]['inventory_item_id'] == $invItemId, "Item 1 correctly linked to inventory item");

$prList = $purchaseService->listPurchaseRequests($org1, 1, 15, 'Replenishment', 'draft');
assertTest($prList['total'] >= 1, "Listed PRs filtered by status='draft' and search keyword");

// 1.4 Submit PR
$submittedPr = $purchaseService->submitPurchaseRequest($org1, $pr1['id'], $userId);
assertTest($submittedPr['status'] === 'submitted', "PR status transitioned from 'draft' to 'submitted'");

// Attempt to submit again should fail
try {
    $purchaseService->submitPurchaseRequest($org1, $pr1['id'], $userId);
    assertTest(false, "Submitting an already submitted PR must fail");
} catch (\InvalidArgumentException $e) {
    assertTest(true, "Guard blocked re-submitting an already submitted PR");
}

// 1.5 Reject PR with reason
$rejectedPr = $purchaseService->rejectPurchaseRequest($org1, $pr1['id'], 'Budget allocation cap reached for this quarter', $userId);
assertTest($rejectedPr['status'] === 'rejected', "PR transitioned from 'submitted' to 'rejected'");
assertTest(str_contains($rejectedPr['rejection_reason'], 'Budget allocation cap'), "Rejection reason saved correctly: '{$rejectedPr['rejection_reason']}'");

// 1.6 Re-submit rejected PR
$resubmittedPr = $purchaseService->submitPurchaseRequest($org1, $pr1['id'], $userId);
assertTest($resubmittedPr['status'] === 'submitted', "Rejected PR successfully re-submitted to 'submitted'");

// 1.7 Approve PR
$approvedPr = $purchaseService->approvePurchaseRequest($org1, $pr1['id'], $userId);
assertTest($approvedPr['status'] === 'approved', "PR status transitioned to 'approved'");
assertTest($approvedPr['approved_by'] == $userId && !empty($approvedPr['approved_at']), "Recorded approved_by ({$approvedPr['approved_by']}) and approved_at timestamp");

// 1.8 Create second PR and cancel it
$pr2 = $purchaseService->createPurchaseRequest($org1, [
    'purpose' => 'Temporary Supplies Request (TEST_PURCHASE)',
    'items' => [
        ['item_name' => 'Whistles', 'quantity' => 5, 'estimated_unit_cost' => 150.00],
    ],
], $userId);
$cancelledPr = $purchaseService->cancelPurchaseRequest($org1, $pr2['id'], $userId);
assertTest($cancelledPr['status'] === 'cancelled', "Cancelled draft PR successfully");


// ==========================================
// SECTION 2: PURCHASE ORDER MANAGEMENT
// ==========================================
echo "\n--- Section 2: Purchase Order Management ---\n";

// 2.1 Conversion from Approved PR to PO
try {
    // Attempt conversion of cancelled PR should fail
    $purchaseService->createPurchaseOrder($org1, [
        'purchase_request_id' => $pr2['id'],
        'vendor_id' => $vendor1['id'],
    ], $userId);
    assertTest(false, "Converting non-approved PR must fail");
} catch (\InvalidArgumentException $e) {
    assertTest(true, "Guard blocked creating PO from non-approved PR");
}

// Valid conversion of approved PR $pr1
$poFromPr = $purchaseService->createPurchaseOrder($org1, [
    'purchase_request_id' => $pr1['id'],
    'vendor_id' => $vendor1['id'],
    'expected_delivery_date' => date('Y-m-d', strtotime('+10 days')),
    'payment_terms' => 'Net 30',
    'notes' => 'Generated from PR-1 for tournament (TEST_PURCHASE)',
], $userId);

assertTest(!empty($poFromPr['id']) && $poFromPr['id'] > 0, "Created PO from approved PR (ID: {$poFromPr['id']})");
assertTest(str_starts_with($poFromPr['po_number'], 'PO-'), "PO number auto-generated: '{$poFromPr['po_number']}'");
assertTest($poFromPr['status'] === 'draft', "Initial PO status is 'draft'");
assertTest($poFromPr['purchase_request_id'] == $pr1['id'], "PO correctly linked to PR {$pr1['id']}");
assertTest(count($poFromPr['items']) === 2, "Items copied from PR into PO line items (2 items)");

// Verify original PR status transitioned to 'converted'
$refreshedPr = $purchaseService->getPurchaseRequest($org1, $pr1['id']);
assertTest($refreshedPr['status'] === 'converted', "Original PR status automatically transitioned to 'converted'");

// 2.2 Standalone Purchase Order Creation with Pricing & Tax Calculations
$standalonePo = $purchaseService->createPurchaseOrder($org1, [
    'vendor_id' => $vendor1['id'],
    'expected_delivery_date' => date('Y-m-d', strtotime('+7 days')),
    'payment_terms' => '50% Advance, 50% on Delivery',
    'notes' => 'Standalone equipment PO with custom taxes and discounts (TEST_PURCHASE)',
    'items' => [
        [
            'inventory_item_id' => $invItemId,
            'item_name' => 'Match Basketball Size 7',
            'ordered_quantity' => 10,
            'unit' => 'piece',
            'unit_price' => 1000.00,
            'tax_percent' => 18.00,      // Line total: 10000, Tax: 1800, Total: 11800
            'discount_percent' => 0.00,
        ],
        [
            'inventory_item_id' => null,
            'item_name' => 'Badminton Shuttlecock Barrels (Pack of 10)',
            'ordered_quantity' => 5,
            'unit' => 'pack',
            'unit_price' => 2000.00,     // Line total: 10000
            'tax_percent' => 12.00,      // Tax: 1200
            'discount_percent' => 10.00, // Discount: 1000
            // Subtotal: 10000, Tax: 1200, Discount: 1000, Total: 10200
        ],
    ],
], $userId);

assertTest(!empty($standalonePo['id']), "Created standalone PO (ID: {$standalonePo['id']})");
// Expected: Subtotal = 20000.00, Tax = 3000.00, Discount = 1000.00, Total = 22000.00
assertTest((float)$standalonePo['subtotal'] == 20000.00, "Calculated PO subtotal: 20000.00 (Got: {$standalonePo['subtotal']})");
assertTest((float)$standalonePo['tax_amount'] == 3000.00, "Calculated PO tax_amount: 3000.00 (Got: {$standalonePo['tax_amount']})");
assertTest((float)$standalonePo['discount_amount'] == 1000.00, "Calculated PO discount_amount: 1000.00 (Got: {$standalonePo['discount_amount']})");
assertTest((float)$standalonePo['total_amount'] == 22000.00, "Calculated PO total_amount: 22000.00 (Got: {$standalonePo['total_amount']})");

// 2.3 Status Transitions on PO
$sentPo = $purchaseService->updatePoStatus($org1, $standalonePo['id'], 'sent', $userId);
assertTest($sentPo['status'] === 'sent', "PO status transitioned to 'sent'");

$confirmedPo = $purchaseService->updatePoStatus($org1, $standalonePo['id'], 'confirmed', $userId);
assertTest($confirmedPo['status'] === 'confirmed', "PO status transitioned to 'confirmed'");

// View & List POs
$poDetails = $purchaseService->getPurchaseOrder($org1, $standalonePo['id']);
assertTest($poDetails['vendor_name'] === 'Apex Sports Suppliers (TEST_PURCHASE)', "PO details include vendor company name");
assertTest(count($poDetails['items']) === 2, "PO details include 2 items");

$poList = $purchaseService->listPurchaseOrders($org1, 1, 15, null, 'confirmed');
assertTest($poList['total'] >= 1, "Listed POs filtered by status='confirmed'");


// =========================================================================
// SECTION 3: GOODS RECEIPT & ATOMIC INVENTORY INTEGRATION
// =========================================================================
echo "\n--- Section 3: Goods Receipt & Atomic Inventory Integration ---\n";

// Fetch initial inventory item quantity before any goods receipt
$initialInvQty = (float)$pdo->query("SELECT quantity FROM inventory_items WHERE id = {$invItemId}")->fetchColumn();
echo " Initial inventory quantity for item {$invItemId}: {$initialInvQty}\n";

$poItem1 = $poDetails['items'][0]; // Basketball: ordered 10, linked to $invItemId
$poItem2 = $poDetails['items'][1]; // Shuttlecocks: ordered 5, non-inventory item

// 3.1 Validation: Receiving goods with quantity exceeding ordered quantity must fail
try {
    $purchaseService->receiveGoods($org1, $standalonePo['id'], [
        'received_by' => $userId,
        'items' => [
            ['purchase_order_item_id' => $poItem1['id'], 'received_quantity' => 15], // Ordered 10, receiving 15 -> invalid
        ],
    ], $userId);
    assertTest(false, "Receiving quantity exceeding remaining ordered quantity must fail");
} catch (\InvalidArgumentException $e) {
    assertTest(true, "Guard blocked excess receipt quantity: '{$e->getMessage()}'");
}

// 3.2 Partial Receipt: Receive 6 Basketballs and 3 Shuttlecocks
$grn1 = $purchaseService->receiveGoods($org1, $standalonePo['id'], [
    'receipt_date' => date('Y-m-d'),
    'received_by' => $userId,
    'remarks' => 'Partial consignment arrived in good condition (TEST_PURCHASE)',
    'items' => [
        ['purchase_order_item_id' => $poItem1['id'], 'received_quantity' => 6],
        ['purchase_order_item_id' => $poItem2['id'], 'received_quantity' => 3],
    ],
], $userId);

assertTest(!empty($grn1['id']) && $grn1['id'] > 0, "Created Goods Receipt Note (GRN ID: {$grn1['id']})");
assertTest(str_starts_with($grn1['receipt_number'], 'GRN-'), "GRN number auto-generated: '{$grn1['receipt_number']}'");

// Check PO items received_quantity updated
$updatedPo1 = $purchaseService->getPurchaseOrder($org1, $standalonePo['id']);
assertTest((float)$updatedPo1['items'][0]['received_quantity'] == 6.00, "Item 1 received_quantity updated to 6 (Ordered 10)");
assertTest((float)$updatedPo1['items'][1]['received_quantity'] == 3.00, "Item 2 received_quantity updated to 3 (Ordered 5)");
assertTest($updatedPo1['status'] === 'partially_received', "PO status transitioned to 'partially_received'");

// 3.3 Verify Atomic Inventory Update and Stock Transaction
$invQtyAfterGrn1 = (float)$pdo->query("SELECT quantity FROM inventory_items WHERE id = {$invItemId}")->fetchColumn();
$expectedQty1 = $initialInvQty + 6;
assertTest($invQtyAfterGrn1 == $expectedQty1, "Inventory quantity atomically incremented from {$initialInvQty} to {$invQtyAfterGrn1} (+6)");

$stockTx1 = $pdo->query("SELECT * FROM stock_transactions WHERE inventory_item_id = {$invItemId} AND reference_id = {$grn1['id']} AND reference_type = 'goods_receipt'")->fetch(PDO::FETCH_ASSOC);
assertTest(!empty($stockTx1), "Created stock_transactions record linked to GRN {$grn1['id']}");
assertTest($stockTx1['transaction_type'] === 'purchase', "Stock transaction type is 'purchase'");
assertTest((float)$stockTx1['quantity'] == 6.00, "Stock transaction quantity is +6.00");
assertTest($stockTx1['organization_id'] == $org1, "Stock transaction belongs to organization {$org1}");

// 3.4 Second Receipt (Fulfilling the remaining items)
$grn2 = $purchaseService->receiveGoods($org1, $standalonePo['id'], [
    'receipt_date' => date('Y-m-d'),
    'received_by' => $userId,
    'remarks' => 'Final balance shipment received and verified (TEST_PURCHASE)',
    'items' => [
        ['purchase_order_item_id' => $poItem1['id'], 'received_quantity' => 4], // Remaining 4
        ['purchase_order_item_id' => $poItem2['id'], 'received_quantity' => 2], // Remaining 2
    ],
], $userId);

assertTest(!empty($grn2['id']), "Created second Goods Receipt Note (GRN ID: {$grn2['id']})");

$finalPo = $purchaseService->getPurchaseOrder($org1, $standalonePo['id']);
assertTest((float)$finalPo['items'][0]['received_quantity'] == 10.00, "Item 1 received_quantity now 10/10 (100% fulfilled)");
assertTest((float)$finalPo['items'][1]['received_quantity'] == 5.00, "Item 2 received_quantity now 5/5 (100% fulfilled)");
assertTest($finalPo['status'] === 'received', "PO status automatically transitioned to 'received'");

// Verify second inventory increment
$invQtyAfterGrn2 = (float)$pdo->query("SELECT quantity FROM inventory_items WHERE id = {$invItemId}")->fetchColumn();
$expectedQty2 = $expectedQty1 + 4;
assertTest($invQtyAfterGrn2 == $expectedQty2, "Inventory quantity incremented again from {$invQtyAfterGrn1} to {$invQtyAfterGrn2} (+4)");

// 3.5 Attempting to receive further goods when PO is fully received must fail
try {
    $purchaseService->receiveGoods($org1, $standalonePo['id'], [
        'received_by' => $userId,
        'items' => [
            ['purchase_order_item_id' => $poItem1['id'], 'received_quantity' => 1],
        ],
    ], $userId);
    assertTest(false, "Receiving goods on an already fully fulfilled PO must fail");
} catch (\InvalidArgumentException $e) {
    assertTest(true, "Guard blocked receipt on fully received PO: '{$e->getMessage()}'");
}

// 3.6 Goods Receipts List & Detail
$receiptList = $purchaseService->listGoodsReceipts($org1, 1, 15, $standalonePo['id']);
assertTest($receiptList['total'] === 2, "List goods receipts returns exactly 2 GRNs for PO {$standalonePo['id']}");

$fetchedGrn = $purchaseService->getGoodsReceipt($org1, $grn1['id']);
assertTest($fetchedGrn['po_number'] === $standalonePo['po_number'], "Fetched GRN detail links back to PO {$standalonePo['po_number']}");


// ==========================================
// SECTION 4: MULTI-TENANT ISOLATION
// ==========================================
echo "\n--- Section 4: Multi-Tenant Isolation ---\n";

// 4.1 Org 2 cannot view Org 1's Purchase Request
$crossPr = $purchaseService->getPurchaseRequest($org2, $pr1['id']);
assertTest($crossPr === null, "Tenant isolation: Org 2 viewing Org 1's PR returns null");

// 4.2 Org 2 cannot approve Org 1's PR
try {
    $purchaseService->approvePurchaseRequest($org2, $pr1['id'], $userId);
    assertTest(false, "Org 2 approving Org 1's PR must throw exception");
} catch (\InvalidArgumentException $e) {
    assertTest(true, "Tenant isolation prevented Org 2 from approving Org 1's PR");
}

// 4.3 Org 2 cannot view Org 1's Purchase Order
$crossPo = $purchaseService->getPurchaseOrder($org2, $standalonePo['id']);
assertTest($crossPo === null, "Tenant isolation: Org 2 viewing Org 1's PO returns null");

// 4.4 Org 2 cannot receive goods for Org 1's PO
try {
    $purchaseService->receiveGoods($org2, $standalonePo['id'], [
        'received_by' => $userId,
        'items' => [
            ['purchase_order_item_id' => $poItem1['id'], 'received_quantity' => 1],
        ],
    ], $userId);
    assertTest(false, "Org 2 receiving goods against Org 1's PO must throw exception");
} catch (\InvalidArgumentException $e) {
    assertTest(true, "Tenant isolation prevented Org 2 from receiving goods against Org 1's PO");
}

// 4.5 Org 1 cannot create PO using Org 2's vendor
try {
    $purchaseService->createPurchaseOrder($org1, [
        'vendor_id' => $vendor2['id'], // Org 2's vendor
        'items' => [
            ['item_name' => 'Cross-org item', 'ordered_quantity' => 1, 'unit_price' => 100],
        ],
    ], $userId);
    assertTest(false, "Creating PO with vendor belonging to another organization must fail");
} catch (\InvalidArgumentException $e) {
    assertTest(true, "Guard prevented creating PO with vendor from another tenant");
}

// 4.6 Org 2 cannot view Org 1's Goods Receipt
$crossGr = $purchaseService->getGoodsReceipt($org2, $grn1['id']);
assertTest($crossGr === null, "Tenant isolation: Org 2 viewing Org 1's Goods Receipt returns null");

// 4.7 Org 2 list queries do not leak Org 1 records
$org2Prs = $purchaseService->listPurchaseRequests($org2);
$org2Pos = $purchaseService->listPurchaseOrders($org2);
$org2Grns = $purchaseService->listGoodsReceipts($org2);
assertTest($org2Prs['total'] === 0, "Org 2 PR list is completely isolated (0 records)");
assertTest($org2Pos['total'] === 0, "Org 2 PO list is completely isolated (0 records)");
assertTest($org2Grns['total'] === 0, "Org 2 GRN list is completely isolated (0 records)");


// ==========================================
// CLEANUP & FINAL REPORT
// ==========================================
echo "\n--- Cleanup & Final Report ---\n";

$pdo->exec("DELETE FROM stock_transactions WHERE inventory_item_id = {$invItemId} OR remarks LIKE '%TEST_PURCHASE%'");
$pdo->exec("DELETE FROM goods_receipts WHERE receipt_number LIKE 'TEST-GRN%' OR receipt_number LIKE 'GRN-%' OR remarks LIKE '%TEST_PURCHASE%'");
$pdo->exec("DELETE FROM purchase_order_items WHERE purchase_order_id IN (SELECT id FROM purchase_orders WHERE po_number LIKE 'TEST-PO%' OR po_number LIKE 'PO-%' OR notes LIKE '%TEST_PURCHASE%')");
$pdo->exec("DELETE FROM purchase_orders WHERE po_number LIKE 'TEST-PO%' OR po_number LIKE 'PO-%' OR notes LIKE '%TEST_PURCHASE%'");
$pdo->exec("DELETE FROM purchase_request_items WHERE purchase_request_id IN (SELECT id FROM purchase_requests WHERE request_reference LIKE 'TEST-PR%' OR request_reference LIKE 'PR-%' OR purpose LIKE '%TEST_PURCHASE%')");
$pdo->exec("DELETE FROM purchase_requests WHERE request_reference LIKE 'TEST-PR%' OR request_reference LIKE 'PR-%' OR purpose LIKE '%TEST_PURCHASE%'");
$pdo->exec("DELETE FROM equipment WHERE inventory_item_id = {$invItemId}");
$pdo->exec("DELETE FROM inventory_items WHERE id = {$invItemId}");
$pdo->exec("DELETE FROM vendors WHERE id IN ({$vendor1['id']}, {$vendor2['id']})");

echo "\n==========================================\n";
echo "TEST RESULTS: Passed: {$passed}, Failed: {$failed}\n";
echo "==========================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
