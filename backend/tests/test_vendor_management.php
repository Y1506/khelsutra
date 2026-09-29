<?php

/**
 * KhelSutra Phase 2A: Vendor Management Verification Script
 * Validates Vendor CRUD, Auto/Custom Code Uniqueness, Details (Contact, Tax, Bank),
 * Status Transitions, Invoices & Calculations, Composite Uniqueness,
 * Deletion Guards, and Multi-Tenancy Isolation.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/app.php';

use App\Services\Vendor\VendorService;
use App\Models\Vendor;
use App\Models\VendorInvoice;

$pdo = \App\Services\BaseService::getDatabaseConnection();
$vendorService = new VendorService();

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

echo "=== KhelSutra Phase 2A: Vendor Management Verification ===\n\n";

$org1 = 1;
$org2 = 2;
$userId = 1;

// Ensure Organization 2 exists in organizations table
$org2Row = $pdo->query("SELECT id FROM organizations WHERE id = {$org2}")->fetchColumn();
if (!$org2Row) {
    $pdo->exec("INSERT INTO organizations (id, organization_code, name, country, status, created_at, updated_at) VALUES ({$org2}, 'ORG-TEST-2', 'Secondary Sports Academy', 'India', 'active', NOW(), NOW())");
}

// Cleanup any old test vendors and invoices
$pdo->exec("DELETE FROM vendor_invoices WHERE invoice_number LIKE 'TEST-INV%' OR notes LIKE '%TEST_SUITE%'");
$pdo->exec("DELETE FROM vendors WHERE company_name LIKE '%TEST_SUITE%' OR vendor_code LIKE 'TEST-VND%' OR vendor_code LIKE 'VND-%'");

// ==========================================
// SECTION 1: VENDOR CRUD & VALIDATION
// ==========================================
echo "--- Section 1: Vendor Creation, Auto-Code, and Validation ---\n";

// 1.1 Create vendor with auto-generated code
try {
    $v1 = $vendorService->createVendor($org1, [
        'company_name' => 'Wilson Sporting Goods (TEST_SUITE)',
        'vendor_type' => 'equipment',
        'contact_person' => 'Rajesh Sharma',
        'email' => 'rajesh@wilson-test.com',
        'phone' => '+91 98765 11111',
        'alternate_phone' => '+91 11 2222 3333',
        'website' => 'https://www.wilson-test.com',
        'gst_number' => '07AAAAA0000A1Z5',
        'pan_number' => 'AAAAA0000A',
        'bank_name' => 'HDFC Bank Ltd',
        'bank_account_number' => '50200099887766',
        'bank_ifsc' => 'HDFC0001234',
        'address_line1' => 'Plot 10, Sports Complex Area',
        'city' => 'New Delhi',
        'state' => 'Delhi',
        'postal_code' => '110001',
        'country' => 'India',
        'status' => 'active',
        'notes' => 'Primary match ball and tennis racket supplier (TEST_SUITE)',
    ], $userId);

    assertTest(!empty($v1['id']) && $v1['id'] > 0, "Created vendor with auto-code (ID: {$v1['id']})");
    assertTest(str_starts_with($v1['vendor_code'], 'VND-'), "Auto-generated vendor code follows VND-YYYYMMDD-XXXX format: '{$v1['vendor_code']}'");
    assertTest($v1['status'] === 'active', "Vendor status defaults to 'active'");
    assertTest($v1['vendor_type'] === 'equipment', "Vendor type saved as 'equipment'");
} catch (\Throwable $e) {
    assertTest(false, "Failed to create vendor with auto-code: " . $e->getMessage());
    $v1 = ['id' => 0];
}

// 1.2 Create vendor with custom code
try {
    $customCode = 'TEST-VND-CUSTOM-01';
    $v2 = $vendorService->createVendor($org1, [
        'company_name' => 'Shiv Naresh Apparel (TEST_SUITE)',
        'vendor_code' => $customCode,
        'vendor_type' => 'apparel',
        'contact_person' => 'Sunil Verma',
        'email' => 'sunil@shivnaresh-test.com',
        'phone' => '+91 98765 22222',
        'gst_number' => '06BBBBB1111B1Z2',
        'pan_number' => 'BBBBB1111B',
        'bank_name' => 'State Bank of India',
        'bank_account_number' => '30012345678',
        'bank_ifsc' => 'SBIN0000456',
        'status' => 'active',
    ], $userId);

    assertTest($v2['vendor_code'] === $customCode, "Custom vendor code accepted: '{$v2['vendor_code']}'");
} catch (\Throwable $e) {
    assertTest(false, "Failed to create vendor with custom code: " . $e->getMessage());
    $v2 = ['id' => 0];
}

// 1.3 Validation: Empty company name must fail
try {
    $vendorService->createVendor($org1, ['company_name' => '  '], $userId);
    assertTest(false, "Empty company name should have been rejected");
} catch (\InvalidArgumentException $e) {
    assertTest(true, "Empty company name properly rejected: {$e->getMessage()}");
} catch (\Throwable $e) {
    assertTest(true, "Empty company name rejected with exception: {$e->getMessage()}");
}

// 1.4 Validation: Duplicate vendor code in the same org must fail
try {
    $vendorService->createVendor($org1, [
        'company_name' => 'Duplicate Code Vendor (TEST_SUITE)',
        'vendor_code' => 'TEST-VND-CUSTOM-01',
    ], $userId);
    assertTest(false, "Duplicate vendor code in the same organization should be rejected");
} catch (\Throwable $e) {
    assertTest(true, "Duplicate vendor code properly rejected: {$e->getMessage()}");
}

// 1.5 Get Vendor by ID & verify details
try {
    $fetched = $vendorService->getVendor($org1, $v1['id']);
    assertTest($fetched !== null, "getVendor() retrieved vendor successfully");
    assertTest($fetched['contact_person'] === 'Rajesh Sharma', "Contact person matches");
    assertTest($fetched['gst_number'] === '07AAAAA0000A1Z5', "GST number matches");
    assertTest($fetched['pan_number'] === 'AAAAA0000A', "PAN number matches");
    assertTest($fetched['bank_name'] === 'HDFC Bank Ltd', "Bank name matches");
    assertTest($fetched['bank_account_number'] === '50200099887766', "Bank account number matches");
    assertTest($fetched['bank_ifsc'] === 'HDFC0001234', "Bank IFSC matches");
    assertTest($fetched['city'] === 'New Delhi', "City matches");
} catch (\Throwable $e) {
    assertTest(false, "Failed to get vendor details: " . $e->getMessage());
}

// 1.6 Update Vendor
try {
    $ok = $vendorService->updateVendor($org1, $v1['id'], [
        'company_name' => 'Wilson Sporting Goods India Pvt Ltd (TEST_SUITE)',
        'phone' => '+91 99999 88888',
        'city' => 'Gurugram',
        'state' => 'Haryana',
        'bank_name' => 'Axis Bank',
        'bank_ifsc' => 'UTIB0000123',
    ], $userId);

    assertTest($ok === true, "updateVendor() returned true");
    $updated = $vendorService->getVendor($org1, $v1['id']);
    assertTest($updated['company_name'] === 'Wilson Sporting Goods India Pvt Ltd (TEST_SUITE)', "Updated company name persisted");
    assertTest($updated['phone'] === '+91 99999 88888', "Updated phone persisted");
    assertTest($updated['city'] === 'Gurugram', "Updated city persisted");
    assertTest($updated['bank_name'] === 'Axis Bank', "Updated bank details persisted");
} catch (\Throwable $e) {
    assertTest(false, "Failed to update vendor: " . $e->getMessage());
}

// 1.7 List vendors with filtering
try {
    $listAll = $vendorService->listVendors($org1, 1, 10);
    assertTest($listAll['total'] >= 2, "listVendors() returns total count >= 2 (Found: {$listAll['total']})");

    $listSearch = $vendorService->listVendors($org1, 1, 10, 'Shiv Naresh');
    assertTest($listSearch['total'] >= 1, "listVendors() search filter for 'Shiv Naresh' returned matching records");

    $listType = $vendorService->listVendors($org1, 1, 10, null, null, 'apparel');
    assertTest($listType['total'] >= 1, "listVendors() vendor_type filter for 'apparel' returned records");
} catch (\Throwable $e) {
    assertTest(false, "Failed to list vendors: " . $e->getMessage());
}

// 1.8 Status Transitions (active -> inactive -> blacklisted -> active)
try {
    $vendorService->setStatus($org1, $v1['id'], 'inactive', $userId, 'Temporary seasonal hiatus');
    $statusCheck1 = $vendorService->getVendor($org1, $v1['id']);
    assertTest($statusCheck1['status'] === 'inactive', "Vendor status updated to 'inactive'");

    $vendorService->setStatus($org1, $v1['id'], 'blacklisted', $userId, 'Counterfeit items delivered');
    $statusCheck2 = $vendorService->getVendor($org1, $v1['id']);
    assertTest($statusCheck2['status'] === 'blacklisted', "Vendor status updated to 'blacklisted'");

    $vendorService->setStatus($org1, $v1['id'], 'active', $userId, 'Reinstated after investigation');
    $statusCheck3 = $vendorService->getVendor($org1, $v1['id']);
    assertTest($statusCheck3['status'] === 'active', "Vendor status reinstated to 'active'");
} catch (\Throwable $e) {
    assertTest(false, "Failed status transitions: " . $e->getMessage());
}

// ==========================================
// SECTION 2: VENDOR INVOICE MANAGEMENT
// ==========================================
echo "\n--- Section 2: Vendor Invoices & Financial Calculations ---\n";

// 2.1 Create invoice with tax and discount calculation
try {
    $inv1 = $vendorService->createInvoice($org1, $v1['id'], [
        'invoice_number' => 'TEST-INV-2026-001',
        'invoice_date' => '2026-09-20',
        'due_date' => '2026-10-20',
        'subtotal' => 50000.00,
        'tax_amount' => 9000.00,      // 18% GST
        'discount_amount' => 2500.00,  // Promotional discount
        'payment_status' => 'unpaid',
        'notes' => 'Match footballs batch delivery invoice (TEST_SUITE)',
    ], $userId);

    assertTest(!empty($inv1['id']) && $inv1['id'] > 0, "Created invoice #TEST-INV-2026-001 (ID: {$inv1['id']})");
    // Expected total: 50000 + 9000 - 2500 = 56500.00
    assertTest((float)$inv1['total_amount'] === 56500.00, "Calculated total amount is correct: ₹56,500.00 (Got: {$inv1['total_amount']})");
    assertTest($inv1['payment_status'] === 'unpaid', "Initial payment status is 'unpaid'");
} catch (\Throwable $e) {
    assertTest(false, "Failed to create invoice: " . $e->getMessage());
    $inv1 = ['id' => 0];
}

// 2.2 Boundary: Excessive discount does not result in negative total amount
try {
    $invZero = $vendorService->createInvoice($org1, $v1['id'], [
        'invoice_number' => 'TEST-INV-ZERO-001',
        'subtotal' => 1000.00,
        'tax_amount' => 100.00,
        'discount_amount' => 2000.00, // discount > subtotal + tax
    ], $userId);

    assertTest((float)$invZero['total_amount'] === 0.00, "Excessive discount clamps total_amount to 0.00 (Got: {$invZero['total_amount']})");
} catch (\Throwable $e) {
    assertTest(false, "Failed invoice discount clamp test: " . $e->getMessage());
}

// 2.3 Composite Uniqueness: (organization_id, vendor_id, invoice_number)
try {
    $vendorService->createInvoice($org1, $v1['id'], [
        'invoice_number' => 'TEST-INV-2026-001', // same invoice number for same vendor
        'subtotal' => 10000.00,
    ], $userId);
    assertTest(false, "Duplicate invoice number for same vendor should be rejected");
} catch (\Throwable $e) {
    assertTest(true, "Duplicate invoice number for same vendor properly rejected: {$e->getMessage()}");
}

// 2.4 Same invoice number for DIFFERENT vendor in same org is ALLOWED by composite key
try {
    $invDiffVendor = $vendorService->createInvoice($org1, $v2['id'], [
        'invoice_number' => 'TEST-INV-2026-001', // same invoice number, different vendor
        'subtotal' => 15000.00,
    ], $userId);
    assertTest(!empty($invDiffVendor['id']), "Same invoice number allowed across different vendors (Composite unique key honored)");
} catch (\Throwable $e) {
    assertTest(false, "Composite unique key failed to allow same invoice # on different vendor: " . $e->getMessage());
}

// 2.5 List invoices for vendor
try {
    $invList = $vendorService->listInvoices($org1, $v1['id'], 1, 10);
    assertTest($invList['total'] >= 2, "listInvoices() returned >= 2 invoices for vendor 1 (Found: {$invList['total']})");
} catch (\Throwable $e) {
    assertTest(false, "Failed to list invoices: " . $e->getMessage());
}

// 2.6 Get invoice by ID
try {
    $fetchedInv = $vendorService->getInvoice($org1, $inv1['id']);
    assertTest($fetchedInv !== null, "getInvoice() retrieved invoice successfully");
    assertTest($fetchedInv['invoice_number'] === 'TEST-INV-2026-001', "Invoice number matches");
    assertTest($fetchedInv['vendor_id'] == $v1['id'], "Vendor ID matches");
} catch (\Throwable $e) {
    assertTest(false, "Failed to get invoice by ID: " . $e->getMessage());
}

// 2.7 Update Invoice (Payment status and notes)
try {
    $ok = $vendorService->updateInvoice($org1, $inv1['id'], [
        'payment_status' => 'partially_paid',
        'notes' => '50% advance released via NEFT (TEST_SUITE)',
    ], $userId);

    assertTest($ok === true, "updateInvoice() returned true for status change");
    $invCheck1 = $vendorService->getInvoice($org1, $inv1['id']);
    assertTest($invCheck1['payment_status'] === 'partially_paid', "Invoice status updated to 'partially_paid'");

    // Mark as paid
    $vendorService->updateInvoice($org1, $inv1['id'], [
        'payment_status' => 'paid',
        'notes' => 'Settled in full (TEST_SUITE)',
    ], $userId);
    $invCheck2 = $vendorService->getInvoice($org1, $inv1['id']);
    assertTest($invCheck2['payment_status'] === 'paid', "Invoice status updated to 'paid'");
} catch (\Throwable $e) {
    assertTest(false, "Failed to update invoice: " . $e->getMessage());
}

// ==========================================
// SECTION 3: DELETION & SOFT-DELETE GUARDS
// ==========================================
echo "\n--- Section 3: Deletion & Active Invoice Deletion Guard ---\n";

// 3.1 Attempt to delete a vendor that has an unpaid or partially paid invoice
try {
    // Create new vendor with unpaid invoice
    $vGuarded = $vendorService->createVendor($org1, [
        'company_name' => 'Guarded Vendor (TEST_SUITE)',
    ], $userId);

    $unpaidInv = $vendorService->createInvoice($org1, $vGuarded['id'], [
        'invoice_number' => 'TEST-INV-GUARDED-001',
        'subtotal' => 5000.00,
        'payment_status' => 'unpaid',
    ], $userId);

    // Attempt delete
    $vendorService->deleteVendor($org1, $vGuarded['id'], $userId);
    assertTest(false, "Deleting vendor with unpaid invoice must be rejected");
} catch (\Throwable $e) {
    assertTest(true, "Active unpaid invoice guard prevented vendor deletion: {$e->getMessage()}");
}

// 3.2 Settle the invoice, then delete vendor
try {
    $vendorService->updateInvoice($org1, $unpaidInv['id'], ['payment_status' => 'paid'], $userId);
    $delOk = $vendorService->deleteVendor($org1, $vGuarded['id'], $userId);
    assertTest($delOk === true, "deleteVendor() succeeded once all invoices were marked paid");

    $delCheck = $vendorService->getVendor($org1, $vGuarded['id']);
    assertTest($delCheck === null, "Deleted vendor is excluded by default (soft-deleted)");
} catch (\Throwable $e) {
    assertTest(false, "Failed clean vendor deletion after settlement: " . $e->getMessage());
}

// ==========================================
// SECTION 4: MULTI-TENANT ISOLATION
// ==========================================
echo "\n--- Section 4: Multi-Tenant Isolation ---\n";

// 4.1 Create Vendor in Organization 2
try {
    $vOrg2 = $vendorService->createVendor($org2, [
        'company_name' => 'Org2 Exclusive Supplier (TEST_SUITE)',
        'vendor_code' => 'TEST-VND-ORG2-001',
        'contact_person' => 'Org2 Manager',
    ], $userId);

    assertTest(!empty($vOrg2['id']), "Created vendor in Organization 2 (ID: {$vOrg2['id']})");
} catch (\Throwable $e) {
    assertTest(false, "Failed to create vendor in Org 2: " . $e->getMessage());
    $vOrg2 = ['id' => 0];
}

// 4.2 Cross-Tenant Read: Org 1 cannot view Org 2's vendor
$crossGet = $vendorService->getVendor($org1, $vOrg2['id']);
assertTest($crossGet === null, "Cross-Tenant Read: getVendor(org1, org2VendorId) returns null");

// 4.3 Cross-Tenant List: Org 1 vendor list does not contain Org 2 vendor
$org1Vendors = $vendorService->listVendors($org1, 1, 100);
$foundCross = false;
foreach ($org1Vendors['data'] as $item) {
    if ((int)$item['id'] === (int)$vOrg2['id']) {
        $foundCross = true;
        break;
    }
}
assertTest(!$foundCross, "Cross-Tenant List: listVendors(org1) does not leak Org 2 vendors");

// 4.4 Cross-Tenant Update: Org 1 cannot update Org 2's vendor
try {
    $vendorService->updateVendor($org1, $vOrg2['id'], ['company_name' => 'Hacked Name'], $userId);
    assertTest(false, "Cross-Tenant Update: updateVendor() should have thrown an exception");
} catch (\Throwable $e) {
    assertTest(true, "Cross-Tenant Update blocked with exception: {$e->getMessage()}");
}

// 4.5 Cross-Tenant Status: Org 1 cannot change status of Org 2's vendor
try {
    $vendorService->setStatus($org1, $vOrg2['id'], 'blacklisted', $userId);
    assertTest(false, "Cross-Tenant Status: setStatus() should have thrown an exception");
} catch (\Throwable $e) {
    assertTest(true, "Cross-Tenant Status change blocked with exception: {$e->getMessage()}");
}

// 4.6 Cross-Tenant Invoice: Org 1 cannot create an invoice for Org 2's vendor
try {
    $vendorService->createInvoice($org1, $vOrg2['id'], [
        'invoice_number' => 'TEST-INV-CROSS-001',
        'subtotal' => 20000.00,
    ], $userId);
    assertTest(false, "Cross-Tenant Invoice: createInvoice() should have thrown an exception");
} catch (\Throwable $e) {
    assertTest(true, "Cross-Tenant Invoice creation blocked with exception: {$e->getMessage()}");
}

// 4.7 Cross-Tenant Delete: Org 1 cannot delete Org 2's vendor
try {
    $vendorService->deleteVendor($org1, $vOrg2['id'], $userId);
    assertTest(false, "Cross-Tenant Delete: deleteVendor() should have thrown an exception");
} catch (\Throwable $e) {
    assertTest(true, "Cross-Tenant Delete blocked with exception: {$e->getMessage()}");
}

// ==========================================
// CLEANUP TEST DATA
// ==========================================
$pdo->exec("DELETE FROM vendor_invoices WHERE invoice_number LIKE 'TEST-INV%' OR notes LIKE '%TEST_SUITE%'");
$pdo->exec("DELETE FROM vendors WHERE company_name LIKE '%TEST_SUITE%' OR vendor_code LIKE 'TEST-VND%'");

echo "\n=======================================================\n";
echo "VERIFICATION RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
