<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/app.php';

use App\Services\Inventory\InventoryService;
use App\Services\Inventory\InventoryCategoryService;
use App\Services\Equipment\EquipmentService;
use App\Services\Vendor\VendorService;
use App\Services\Purchase\PurchaseService;
use App\Services\Finance\FinanceService;
use App\Services\Notification\NotificationService;
use App\Services\Report\ReportService;
use App\Helpers\ApiResponse;

echo "======================================================================\n";
echo " KhelSutra Phase 6: Member 5 Final Integration & Multi-Tenancy Audit \n";
echo "======================================================================\n\n";

$passCount = 0;
$failCount = 0;
$errors = [];

function assertTest(bool $condition, string $description, ?string $failureDetail = null): void {
    global $passCount, $failCount, $errors;
    if ($condition) {
        $passCount++;
        echo " [PASS] $description\n";
    } else {
        $failCount++;
        echo " [FAIL] $description\n";
        if ($failureDetail) {
            echo "        Detail: $failureDetail\n";
            $errors[] = "$description: $failureDetail";
        }
    }
}

// Track IDs for cleanup
$createdIds = [
    'inventory_categories' => [],
    'inventory_items' => [],
    'stock_transactions' => [],
    'equipment' => [],
    'equipment_assignments' => [],
    'vendors' => [],
    'vendor_invoices' => [],
    'purchase_requests' => [],
    'purchase_orders' => [],
    'goods_receipts' => [],
    'finance_categories' => [],
    'income_transactions' => [],
    'expenses' => [],
    'budgets' => [],
    'finance_payments' => [],
    'notifications' => [],
];

$org1 = 1;
$org2 = 2;
$userId1 = 1;
$userId2 = 2;

// Instantiate all services
$invService = new InventoryService();
$invCatService = new InventoryCategoryService();
$equipService = new EquipmentService();
$vendorService = new VendorService();
$purchaseService = new PurchaseService();
$financeService = new FinanceService();
$notifService = new NotificationService();
$reportService = new ReportService();

$apiHandler = require __DIR__ . '/../routes/api.php';

// Helper for simulated API requests
function callApi(callable $handler, string $uri, string $method, array $data = [], array $headers = [], ?array $user = null): array {
    $requestData = array_merge($data, [
        'headers' => $headers,
        'user' => $user
    ]);
    $response = $handler($uri, $method, $requestData);
    if ($response instanceof \Illuminate\Http\JsonResponse) {
        return json_decode($response->getContent(), true);
    }
    return (array)$response;
}

try {
    // =========================================================================
    // 1. FULL MEMBER 5 FLOW TEST
    // =========================================================================
    echo "\n--- Section 1.1: Inventory & Low-Stock Notifications Flow ---\n";

    // 1.1.1 Category
    $catName = "Final Test Cat " . rand(1000, 9999);
    $cat = $invCatService->createCategory($org1, [
        'category_name' => $catName,
        'description' => 'Integration test category',
        'status' => 'active'
    ], $userId1);
    $createdIds['inventory_categories'][] = $cat['id'];
    assertTest(isset($cat['id']) && $cat['id'] > 0, "Inventory Category created: ID #{$cat['id']}");

    // 1.1.2 Item with opening stock
    $itemCode = "ITM-INT-" . rand(1000, 9999);
    $item = $invService->createItem($org1, [
        'category_id' => $cat['id'],
        'item_code' => $itemCode,
        'item_name' => 'Tournament Match Rugby Ball',
        'quantity' => 25,
        'unit_cost' => 1200.00,
        'minimum_stock_level' => 5,
        'reorder_level' => 10,
        'location_name' => 'Main Warehouse Rack A'
    ], $userId1);
    $createdIds['inventory_items'][] = $item['id'];
    assertTest($item['quantity'] == 25, "Inventory item created with opening stock 25");

    // Check opening stock transaction in ledger
    $history = $invService->getStockTransactions($org1, $item['id']);
    $historyItems = $history['data'] ?? [];
    assertTest(count($historyItems) >= 1 && ($historyItems[0]['transaction_type'] ?? '') === 'opening', "Opening stock transaction automatically recorded in ledger");

    // 1.1.3 Stock In (Purchase)
    $stockIn = $invService->recordStockTransaction($org1, $item['id'], [
        'transaction_type' => 'purchase',
        'quantity' => 10,
        'remarks' => 'Batch replenishment'
    ], $userId1);
    assertTest($stockIn['new_quantity'] == 35, "Stock In (+10) updated quantity from 25 to 35");

    // 1.1.4 Stock Out (Issue)
    $stockOut = $invService->recordStockTransaction($org1, $item['id'], [
        'transaction_type' => 'issue',
        'quantity' => 15,
        'remarks' => 'Issued to Under-19 team'
    ], $userId1);
    assertTest($stockOut['new_quantity'] == 20, "Stock Out (-15) updated quantity from 35 to 20");

    // 1.1.5 Stock Adjustment to critical low level
    $adj = $invService->recordStockTransaction($org1, $item['id'], [
        'transaction_type' => 'adjustment',
        'new_quantity' => 4,
        'remarks' => 'Physical stock count discrepancy: adjusted to 4'
    ], $userId1);
    assertTest($adj['new_quantity'] == 4, "Stock adjustment to target (4) updated quantity to 4");

    // 1.1.6 Low Stock Flag & Detection
    $refreshedItem = $invService->getItem($org1, $item['id']);
    assertTest((bool)$refreshedItem['is_low_stock'] === true, "Low stock flag is active when quantity (4) <= reorder_level (10)");

    // 1.1.7 Low Stock Automated Notification (triggered automatically during stock transaction)
    $userNotifs = $notifService->getUserNotifications($org1, $userId1, 1, 10, true);
    $foundNotif = false;
    $notifId = null;
    foreach ($userNotifs['data'] as $un) {
        if (str_contains($un['title'], 'Low Stock Alert') && str_contains($un['message'], 'Tournament Match Rugby Ball')) {
            $foundNotif = true;
            $notifId = (int)$un['id'];
            $createdIds['notifications'][] = $notifId;
            break;
        }
    }
    assertTest($foundNotif, "Automated low stock notification triggered and recorded in user's persistent alert list");

    // =========================================================================
    echo "\n--- Section 1.2: Equipment Lifecycle & Overdue Return Flow ---\n";

    // Ensure athlete exists in Org 1
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=khelsutra;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $athleteId1 = (int)$pdo->query("SELECT id FROM athletes WHERE organization_id = {$org1} AND deleted_at IS NULL LIMIT 1")->fetchColumn();
    if (!$athleteId1) {
        $pdo->exec("INSERT INTO athletes (organization_id, athlete_code, first_name, last_name, status, created_at, updated_at) VALUES ({$org1}, 'ATH-TST01', 'Test', 'Athlete', 'active', NOW(), NOW())");
        $athleteId1 = (int)$pdo->lastInsertId();
    }

    // 1.2.1 Create Equipment
    $eqp = $equipService->createEquipment($org1, [
        'equipment_name' => 'Pro Bowling Machine Titan-X',
        'equipment_type' => 'training_aid',
        'model_number' => 'TITAN-X-2026',
        'serial_number' => 'SN-TITAN-' . rand(10000, 99999),
        'purchase_cost' => 85000.00,
        'location_name' => 'Cricket Indoor Nets B',
        'condition_status' => 'new'
    ], $userId1);
    $createdIds['equipment'][] = $eqp['id'];
    assertTest(isset($eqp['id']) && $eqp['status'] === 'available', "Equipment created with status 'available' and asset_code: {$eqp['asset_code']}");

    // 1.2.2 Assign Equipment
    $assign = $equipService->assignEquipment($org1, $eqp['id'], [
        'assignee_type' => 'athlete',
        'athlete_id' => $athleteId1,
        'assigned_date' => date('Y-m-d', strtotime('-3 days')),
        'expected_return_date' => date('Y-m-d', strtotime('-1 day')), // overdue!
        'condition_on_issue' => 'new',
        'notes' => 'Assigned for high-speed bowling drill'
    ], $userId1);
    $createdIds['equipment_assignments'][] = $assign['assignment_id'];
    assertTest($assign['status'] === 'assigned', "Equipment transitioned to 'assigned'");

    // 1.2.3 Assignment History
    $eqpDetails = $equipService->getEquipment($org1, $eqp['id']);
    assertTest(!empty($eqpDetails['active_assignment']), "Equipment details show active assignment");
    assertTest(count($eqpDetails['assignment_history']) >= 1, "Assignment recorded in equipment history ledger");

    // 1.2.4 Overdue Alert
    $overdueResult = $notifService->checkAndTriggerOverdueEquipment($org1);
    assertTest($overdueResult['triggered'] === true, "Automated check detected overdue equipment");

    // 1.2.5 Return Equipment
    $return = $equipService->returnEquipment($org1, $eqp['id'], [
        'returned_date' => date('Y-m-d'),
        'condition_on_return' => 'good',
        'return_notes' => 'Returned after drills, thoroughly cleaned'
    ], $userId1);
    assertTest($return['equipment_status'] === 'available', "Equipment returned and status transitioned back to 'available'");

    $eqpDetailsAfter = $equipService->getEquipment($org1, $eqp['id']);
    assertTest($eqpDetailsAfter['active_assignment'] === null, "Active assignment cleared upon return");

    // =========================================================================
    echo "\n--- Section 1.3: Vendors & Vendor Invoices Flow ---\n";

    // 1.3.1 Create Vendor
    $vnd = $vendorService->createVendor($org1, [
        'company_name' => 'Cosco Sports Equipments Pvt Ltd',
        'contact_person' => 'Sunil Gupta',
        'email' => 'sales' . rand(100, 999) . '@coscosports.local',
        'phone' => '+91 9811223344',
        'vendor_type' => 'equipment',
        'gst_number' => '07AAAAA1234A1Z' . rand(1, 9),
        'status' => 'active'
    ], $userId1);
    $createdIds['vendors'][] = $vnd['id'];
    assertTest(isset($vnd['id']) && $vnd['status'] === 'active', "Vendor created with code: {$vnd['vendor_code']}");

    // 1.3.2 Vendor Invoice
    $invNum = "INV-COSCO-" . rand(1000, 9999);
    $vndInv = $vendorService->createInvoice($org1, $vnd['id'], [
        'invoice_number' => $invNum,
        'invoice_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'subtotal' => 50000.00,
        'tax_amount' => 9000.00,
        'discount_amount' => 1000.00,
        'payment_status' => 'unpaid',
        'notes' => 'Quarterly ball supply invoice'
    ], $userId1);
    $createdIds['vendor_invoices'][] = $vndInv['id'];
    assertTest($vndInv['total_amount'] == 58000.00, "Vendor invoice calculated total amount: ₹58,000.00 (50k + 9k - 1k)");
    assertTest($vndInv['payment_status'] === 'unpaid', "Vendor invoice initial status is 'unpaid'");

    // =========================================================================
    echo "\n--- Section 1.4: Procurement Workflow (PR -> PO -> GRN -> Stock) ---\n";

    // Track baseline stock of rugby ball
    $preStock = (int)$invService->getItem($org1, $item['id'])['quantity']; // 4

    // 1.4.1 Purchase Request
    $pr = $purchaseService->createPurchaseRequest($org1, [
        'title' => 'Urgent replenishment of rugby balls',
        'department' => 'Sports Logistics',
        'priority' => 'high',
        'notes' => 'Needed for upcoming inter-academy championship',
        'items' => [
            [
                'inventory_item_id' => $item['id'],
                'item_name' => 'Tournament Match Rugby Ball',
                'quantity' => 20,
                'estimated_unit_cost' => 1200.00
            ]
        ]
    ], $userId1);
    $createdIds['purchase_requests'][] = $pr['id'];
    assertTest(isset($pr['id']) && $pr['status'] === 'draft', "Purchase Request created in 'draft' status with total: ₹{$pr['total_estimated_cost']}");

    // 1.4.2 Submit, Reject, Resubmit & Approve PR
    $purchaseService->submitPurchaseRequest($org1, $pr['id'], $userId1);
    $prSubmitted = $purchaseService->getPurchaseRequest($org1, $pr['id']);
    assertTest($prSubmitted['status'] === 'submitted', "PR status transitioned to 'submitted'");

    $purchaseService->rejectPurchaseRequest($org1, $pr['id'], 'Needs additional quotation verification', $userId1);
    $prRejected = $purchaseService->getPurchaseRequest($org1, $pr['id']);
    assertTest($prRejected['status'] === 'rejected', "PR status transitioned to 'rejected' with reason saved");

    $purchaseService->submitPurchaseRequest($org1, $pr['id'], $userId1);
    $purchaseService->approvePurchaseRequest($org1, $pr['id'], $userId1);
    $prApproved = $purchaseService->getPurchaseRequest($org1, $pr['id']);
    assertTest($prApproved['status'] === 'approved', "PR status transitioned to 'approved'");

    // 1.4.3 Purchase Order from PR
    $po = $purchaseService->createPurchaseOrder($org1, [
        'purchase_request_id' => $pr['id'],
        'vendor_id' => $vnd['id'],
        'expected_delivery_date' => date('Y-m-d', strtotime('+7 days')),
        'tax_amount' => 4320.00,
        'discount_amount' => 500.00,
        'notes' => 'PO generated from PR'
    ], $userId1);
    $createdIds['purchase_orders'][] = $po['id'];
    assertTest(isset($po['id']) && $po['status'] === 'draft', "Purchase Order created linked to PR and Vendor: PO #{$po['po_number']}");

    // PR automatically transitioned to 'converted'
    $prAfterPO = $purchaseService->getPurchaseRequest($org1, $pr['id']);
    assertTest($prAfterPO['status'] === 'converted', "Original PR transitioned to 'converted' upon PO creation");

    // Transition PO to confirmed
    $purchaseService->updatePoStatus($org1, $po['id'], 'sent', $userId1);
    $purchaseService->updatePoStatus($org1, $po['id'], 'confirmed', $userId1);
    $poConfirmed = $purchaseService->getPurchaseOrder($org1, $po['id']);
    assertTest($poConfirmed['status'] === 'confirmed', "PO status confirmed");

    // 1.4.4 Goods Receipt & Atomic Inventory Increase
    $poItem = $poConfirmed['items'][0];
    $grn = $purchaseService->receiveGoods($org1, $po['id'], [
        'receipt_date' => date('Y-m-d'),
        'delivery_note_number' => 'DN-' . rand(1000, 9999),
        'items' => [
            [
                'purchase_order_item_id' => $poItem['id'],
                'received_quantity' => 20,
                'condition' => 'good',
                'remarks' => 'All 20 rugby balls received in flawless condition'
            ]
        ]
    ], $userId1);
    $createdIds['goods_receipts'][] = $grn['id'];
    $receiptNum = $grn['receipt_number'] ?? ($grn['grn_number'] ?? '');
    assertTest(isset($grn['id']), "Goods Receipt Note generated: GRN #{$receiptNum}");

    // PO status should automatically become 'received'
    $poAfterGRN = $purchaseService->getPurchaseOrder($org1, $po['id']);
    assertTest($poAfterGRN['status'] === 'received', "PO status transitioned to 'received' (100% fulfilled)");

    // Inventory stock atomically updated
    $postStock = (int)$invService->getItem($org1, $item['id'])['quantity'];
    assertTest($postStock === ($preStock + 20), "Inventory quantity atomically incremented from {$preStock} to {$postStock} (+20)");

    // Stock transaction created and linked
    $stockTx = $pdo->query("SELECT * FROM stock_transactions WHERE inventory_item_id = {$item['id']} AND reference_id = {$grn['id']} AND reference_type = 'goods_receipt'")->fetch(PDO::FETCH_ASSOC);
    assertTest(!empty($stockTx) && $stockTx['transaction_type'] === 'purchase' && (float)$stockTx['quantity'] == 20.00, "Stock transaction recorded with quantity +20");

    // =========================================================================
    echo "\n--- Section 1.5: Financial Workflow (Category -> Income -> Expense -> Budget -> Payment) ---\n";

    // 1.5.1 Finance Categories
    $finCatExp = $financeService->createCategory($org1, [
        'name' => 'Sports Equipment Procurement ' . rand(100, 999),
        'category_type' => 'expense',
        'description' => 'Procurement expenses for sports gear',
        'is_active' => true
    ]);
    $createdIds['finance_categories'][] = $finCatExp['id'];

    $finCatInc = $financeService->createCategory($org1, [
        'name' => 'Academy Membership & Sponsorship ' . rand(100, 999),
        'category_type' => 'income',
        'description' => 'Revenue from memberships and event sponsors',
        'is_active' => true
    ]);
    $createdIds['finance_categories'][] = $finCatInc['id'];
    assertTest(isset($finCatExp['id']) && isset($finCatInc['id']), "Income and Expense Finance Categories created successfully");

    // 1.5.2 Income Transaction
    $income = $financeService->recordIncome($org1, [
        'finance_category_id' => $finCatInc['id'],
        'income_date' => date('Y-m-d'),
        'source' => 'Elite Academy Term Fee Q4',
        'amount' => 75000.00,
        'payment_method' => 'bank_transfer',
        'reference_number' => 'NEFT-' . rand(10000, 99999),
        'description' => 'Batch subscription fees'
    ], $userId1);
    $createdIds['income_transactions'][] = $income['id'];
    assertTest(isset($income['id']) && $income['amount'] == 75000.00, "Income recorded: ₹75,000.00 (Ref: {$income['income_reference']})");

    // 1.5.3 Budget Creation with Items & Limit Guard
    $currentYear = date('Y') . '-' . (date('Y') + 1);
    $budget = $financeService->createBudget($org1, [
        'budget_name' => 'Annual Equipment Procurement Budget (TEST)',
        'financial_year' => $currentYear,
        'start_date' => date('Y-01-01'),
        'end_date' => date('Y-12-31'),
        'total_budget' => 60000.00,
        'status' => 'active',
        'items' => [
            [
                'finance_category_id' => $finCatExp['id'],
                'allocated_amount' => 50000.00,
                'description' => 'Equipment purchases allocation'
            ]
        ]
    ], $userId1);
    $createdIds['budgets'][] = $budget['id'];
    assertTest(isset($budget['id']) && $budget['total_budget'] == 60000.00, "Budget created with ₹60,000.00 ceiling");

    // Exceeding limit guard
    $limitCaught = false;
    try {
        $financeService->createBudget($org1, [
            'budget_name' => 'Overallocated Budget (TEST)',
            'financial_year' => $currentYear,
            'start_date' => date('Y-01-01'),
            'end_date' => date('Y-12-31'),
            'total_budget' => 20000.00,
            'items' => [
                ['finance_category_id' => $finCatExp['id'], 'allocated_amount' => 25000.00]
            ]
        ], $userId1);
    } catch (\InvalidArgumentException $e) {
        $limitCaught = true;
    }
    assertTest($limitCaught, "Budget limit guard successfully rejected line items exceeding total budget");

    // 1.5.4 Expense Requisition & Approval
    $expense = $financeService->recordExpense($org1, [
        'finance_category_id' => $finCatExp['id'],
        'vendor_id' => $vnd['id'],
        'expense_date' => date('Y-m-d'),
        'amount' => 24000.00,
        'tax_amount' => 4320.00,
        'description' => 'Supply of Tournament Match Rugby Balls',
        'payment_method' => 'bank_transfer'
    ], $userId1);
    $createdIds['expenses'][] = $expense['id'];
    assertTest($expense['total_amount'] == 28320.00 && $expense['payment_status'] === 'pending', "Expense requisition created: ₹28,320.00 (Pending)");

    // Approve expense
    $appOk = $financeService->approveExpense($org1, $expense['id'], $userId1);
    $approvedExpense = $financeService->getExpense($org1, $expense['id']);
    assertTest($appOk && $approvedExpense['payment_status'] === 'approved' && !empty($approvedExpense['approved_at']), "Expense approved successfully");

    // 1.5.5 Budget vs Actual Calculation
    $budgetReport = $financeService->getBudget($org1, $budget['id']);
    assertTest((float)$budgetReport['total_spent'] == 28320.00, "Budget actual spend accurately reflects approved expense: ₹28,320.00");
    assertTest((float)$budgetReport['remaining_budget'] == (60000.00 - 28320.00), "Budget remaining funds calculated accurately: ₹31,680.00");

    // 1.5.6 Payment Settlement
    $expPayment = $financeService->recordPayment($org1, [
        'payment_type' => 'expense',
        'expense_id' => $expense['id'],
        'amount' => 28320.00,
        'payment_date' => date('Y-m-d'),
        'payment_method' => 'bank_transfer',
        'reference_number' => 'UTR-' . rand(100000, 999999),
        'notes' => 'Direct vendor settlement'
    ], $userId1);
    $createdIds['finance_payments'][] = $expPayment['id'];

    $expenseAfterPayment = $financeService->getExpense($org1, $expense['id']);
    assertTest($expenseAfterPayment['payment_status'] === 'paid' && !empty($expenseAfterPayment['paid_at']), "Expense payment_status transitioned to 'paid' and paid_at recorded");

    // Pay vendor invoice
    $vndPayment = $financeService->recordPayment($org1, [
        'payment_type' => 'vendor_invoice',
        'vendor_invoice_id' => $vndInv['id'],
        'amount' => 58000.00,
        'payment_date' => date('Y-m-d'),
        'payment_method' => 'bank_transfer',
        'reference_number' => 'UTR-INV-' . rand(100000, 999999),
        'notes' => 'Settled invoice in full'
    ], $userId1);
    $createdIds['finance_payments'][] = $vndPayment['id'];

    $vndInvAfterPayment = $vendorService->getInvoice($org1, $vndInv['id']);
    assertTest($vndInvAfterPayment['payment_status'] === 'paid', "Vendor invoice payment_status transitioned to 'paid'");

    // =========================================================================
    echo "\n--- Section 1.6: Reports Execution ---\n";

    $invSummaryRep = $reportService->getInventorySummary($org1);
    assertTest(isset($invSummaryRep['summary']['total_items']) && $invSummaryRep['summary']['total_items'] >= 1, "Report: Inventory Summary returned valid total_items");

    $purchSummaryRep = $reportService->getPurchaseSummary($org1);
    assertTest(isset($purchSummaryRep['total_orders']) && $purchSummaryRep['total_orders'] >= 1, "Report: Purchase Summary returned valid orders count");

    $vndSpendRep = $reportService->getVendorPurchaseSpendReport($org1);
    assertTest(isset($vndSpendRep['total_vendors']) && $vndSpendRep['total_vendors'] >= 1, "Report: Vendor Spend Report returned valid total_vendors");

    $equipSummaryRep = $reportService->getEquipmentInventorySummary($org1);
    assertTest(isset($equipSummaryRep['summary']['total_equipment']) && $equipSummaryRep['summary']['total_equipment'] >= 1, "Report: Equipment Summary returned valid equipment count");

    $finSummaryRep = $reportService->getFinanceSummary($org1);
    assertTest(isset($finSummaryRep['total_income']) && isset($finSummaryRep['total_expense']), "Report: Executive Finance Summary returned valid income and expenses");

    // =========================================================================
    echo "\n--- Section 1.7: Member 5 REST APIs & Envelope Standards ---\n";

    // Standard user context
    $apiUser1 = [
        'id' => $userId1,
        'role_id' => 2,
        'role' => ['id' => 2, 'name' => 'Sports Administrator'],
        'organization' => ['id' => $org1, 'name' => 'Apex Sports Academy']
    ];

    // GET /api/v1/inventory
    $resInv = callApi($apiHandler, '/api/v1/inventory', 'GET', [], [], $apiUser1);
    assertTest($resInv['success'] === true && isset($resInv['data']), "REST API: GET /api/v1/inventory conforms to standard envelope");

    // GET /api/v1/equipment
    $resEqp = callApi($apiHandler, '/api/v1/equipment', 'GET', [], [], $apiUser1);
    assertTest($resEqp['success'] === true && isset($resEqp['data']), "REST API: GET /api/v1/equipment conforms to standard envelope");

    // GET /api/v1/vendors
    $resVnd = callApi($apiHandler, '/api/v1/vendors', 'GET', [], [], $apiUser1);
    assertTest($resVnd['success'] === true && isset($resVnd['data']), "REST API: GET /api/v1/vendors conforms to standard envelope");

    // GET /api/v1/purchases/orders
    $resPO = callApi($apiHandler, '/api/v1/purchases/orders', 'GET', [], [], $apiUser1);
    assertTest($resPO['success'] === true && isset($resPO['data']), "REST API: GET /api/v1/purchases/orders conforms to standard envelope");

    // GET /api/v1/finance/summary
    $resFin = callApi($apiHandler, '/api/v1/finance/summary', 'GET', [], [], $apiUser1);
    assertTest($resFin['success'] === true && isset($resFin['data']['total_income']), "REST API: GET /api/v1/finance/summary conforms to standard envelope");

    // GET /api/v1/notifications
    $resNotif = callApi($apiHandler, '/api/v1/notifications', 'GET', [], [], $apiUser1);
    assertTest($resNotif['success'] === true && isset($resNotif['data']), "REST API: GET /api/v1/notifications conforms to standard envelope");

    // GET /api/v1/reports/inventory
    $resRepInv = callApi($apiHandler, '/api/v1/reports/inventory', 'GET', [], [], $apiUser1);
    assertTest($resRepInv['success'] === true && isset($resRepInv['data']['summary']), "REST API: GET /api/v1/reports/inventory conforms to standard envelope");

    // Validation rejection envelope test: POST /api/v1/inventory with empty body
    $resBad = callApi($apiHandler, '/api/v1/inventory', 'POST', [], [], $apiUser1);
    assertTest($resBad['success'] === false && isset($resBad['errors']), "REST API: Validation error returns success: false with errors object");

    // =========================================================================
    // 2. MULTI-TENANCY STRICT TEST
    // =========================================================================
    echo "\n--- Section 2: Multi-Tenancy Strict Isolation Audit ---\n";

    // Org 2 Service Layer Rejections
    $org2InvRead = $invService->getItem($org2, $item['id']);
    assertTest($org2InvRead === null, "Tenant Isolation: Org 2 cannot read Org 1 inventory item");

    $org2EquipRead = $equipService->getEquipment($org2, $eqp['id']);
    assertTest($org2EquipRead === null, "Tenant Isolation: Org 2 cannot read Org 1 equipment");

    $org2VndRead = $vendorService->getVendor($org2, $vnd['id']);
    assertTest($org2VndRead === null, "Tenant Isolation: Org 2 cannot read Org 1 vendor");

    $org2PRRead = $purchaseService->getPurchaseRequest($org2, $pr['id']);
    assertTest($org2PRRead === null, "Tenant Isolation: Org 2 cannot read Org 1 purchase request");

    $org2PORead = $purchaseService->getPurchaseOrder($org2, $po['id']);
    assertTest($org2PORead === null, "Tenant Isolation: Org 2 cannot read Org 1 purchase order");

    $org2GRNRead = $purchaseService->getGoodsReceipt($org2, $grn['id']);
    assertTest($org2GRNRead === null, "Tenant Isolation: Org 2 cannot read Org 1 goods receipt");

    $org2ExpRead = $financeService->getExpense($org2, $expense['id']);
    assertTest($org2ExpRead === null, "Tenant Isolation: Org 2 cannot read Org 1 expense");

    $org2NotifRead = $notifId ? $notifService->getNotification($org2, $notifId, $userId2) : null;
    assertTest($org2NotifRead === null, "Tenant Isolation: Org 2 cannot read Org 1 notification");

    // Cross-tenant write rejection: Org 2 attempting to record payment on Org 1 expense
    $crossPaymentBlocked = false;
    try {
        $financeService->recordPayment($org2, [
            'expense_id' => $expense['id'],
            'amount' => 1000.00
        ], $userId2);
    } catch (\Exception $e) {
        $crossPaymentBlocked = true;
    }
    assertTest($crossPaymentBlocked, "Tenant Isolation: Org 2 cannot record payment against Org 1 expense");

    // Cross-tenant write rejection: Org 2 attempting to receive goods on Org 1 PO
    $crossReceiveBlocked = false;
    try {
        $purchaseService->receiveGoods($org2, $po['id'], [
            'delivery_note_number' => 'DN-CROSS',
            'items' => [['purchase_order_item_id' => $poItem['id'], 'received_quantity' => 1]]
        ], $userId2);
    } catch (\Exception $e) {
        $crossReceiveBlocked = true;
    }
    assertTest($crossReceiveBlocked, "Tenant Isolation: Org 2 cannot receive goods against Org 1 purchase order");

    // REST API Layer Cross-Tenant Header Rejection
    // Org 1 User attempting to spoof Org 2 via X-Organization-Id header
    $resCrossApi = callApi($apiHandler, '/api/v1/inventory', 'GET', [], ['x-organization-id' => '2'], $apiUser1);
    assertTest($resCrossApi['success'] === false && str_contains($resCrossApi['message'], 'Unauthorized cross-tenant access'),
        "REST API: Unauthorized cross-tenant header spoofing is rejected with 403 Forbidden");

    // =========================================================================
    // 3. DATABASE INTEGRITY & BUSINESS GUARDS
    // =========================================================================
    echo "\n--- Section 3: Database Integrity & Constraint Verification ---\n";

    // 3.1 Insufficient Stock / Negative Inventory Safeguard
    $currQty = (int)$invService->getItem($org1, $item['id'])['quantity'];
    $negStockCaught = false;
    try {
        $invService->recordStockTransaction($org1, $item['id'], [
            'transaction_type' => 'issue',
            'quantity' => $currQty + 100, // Excessive!
            'remarks' => 'Illegal negative stock attempt'
        ], $userId1);
    } catch (\Exception $e) {
        $negStockCaught = true;
    }
    assertTest($negStockCaught, "Database Integrity: System rejects negative inventory deductions");

    $qtyAfterAttempt = (int)$invService->getItem($org1, $item['id'])['quantity'];
    assertTest($qtyAfterAttempt === $currQty, "Database Integrity: Inventory quantity remains unchanged after rejected stock deduction");

    // 3.2 Duplicate Unique Code Guard
    $dupVndCaught = false;
    try {
        $vendorService->createVendor($org1, [
            'vendor_code' => $vnd['vendor_code'], // Duplicate!
            'company_name' => 'Duplicate Attempt Inc',
            'email' => 'dup@test.local'
        ], $userId1);
    } catch (\InvalidArgumentException $e) {
        $dupVndCaught = true;
    }
    assertTest($dupVndCaught, "Database Integrity: Duplicate vendor code rejected within same organization");

    // 3.3 Duplicate Equipment Serial Number Guard
    $dupEqpCaught = false;
    try {
        $equipService->createEquipment($org1, [
            'equipment_name' => 'Duplicate Serial Attempt',
            'serial_number' => $eqp['serial_number'] // Duplicate!
        ], $userId1);
    } catch (\InvalidArgumentException $e) {
        $dupEqpCaught = true;
    }
    assertTest($dupEqpCaught, "Database Integrity: Duplicate serial number rejected within same organization");

    // 3.4 Foreign Key Integrity & Orphaned Records Verification
    // Direct PDO check for orphaned records
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=khelsutra;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Check inventory_items with invalid category_id
    $orphanedItems = $pdo->query("SELECT COUNT(*) as c FROM inventory_items WHERE category_id NOT IN (SELECT id FROM inventory_categories)")->fetch()['c'];
    assertTest($orphanedItems == 0, "Database Integrity: 0 orphaned inventory items (all link to valid categories)");

    // Check stock_transactions with invalid item_id
    $orphanedStock = $pdo->query("SELECT COUNT(*) as c FROM stock_transactions WHERE inventory_item_id NOT IN (SELECT id FROM inventory_items)")->fetch()['c'];
    assertTest($orphanedStock == 0, "Database Integrity: 0 orphaned stock transactions");

    // Check equipment_assignments with invalid equipment_id
    $orphanedAssignments = $pdo->query("SELECT COUNT(*) as c FROM equipment_assignments WHERE equipment_id NOT IN (SELECT id FROM equipment)")->fetch()['c'];
    assertTest($orphanedAssignments == 0, "Database Integrity: 0 orphaned equipment assignments");

    // Check purchase_order_items with invalid purchase_order_id
    $orphanedPOItems = $pdo->query("SELECT COUNT(*) as c FROM purchase_order_items WHERE purchase_order_id NOT IN (SELECT id FROM purchase_orders)")->fetch()['c'];
    assertTest($orphanedPOItems == 0, "Database Integrity: 0 orphaned purchase order line items");

    // Check expenses with invalid finance_category_id
    $orphanedExpenses = $pdo->query("SELECT COUNT(*) as c FROM expenses WHERE finance_category_id IS NOT NULL AND finance_category_id NOT IN (SELECT id FROM finance_categories)")->fetch()['c'];
    assertTest($orphanedExpenses == 0, "Database Integrity: 0 orphaned expenses");

    // Check finance_payments with invalid expense_id
    $orphanedPayments = $pdo->query("SELECT COUNT(*) as c FROM finance_payments WHERE expense_id IS NOT NULL AND expense_id NOT IN (SELECT id FROM expenses)")->fetch()['c'];
    assertTest($orphanedPayments == 0, "Database Integrity: 0 orphaned finance payments");

} catch (\Throwable $e) {
    $failCount++;
    echo "\n [UNCAUGHT EXCEPTION]: " . $e->getMessage() . "\n";
    echo "  Location: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "  Trace: " . $e->getTraceAsString() . "\n";
    $errors[] = "Uncaught Exception: " . $e->getMessage();
} finally {
    // =========================================================================
    // CLEANUP TEST DATA
    // =========================================================================
    echo "\n--- Section 4: Cleaning Up Test Artifacts ---\n";
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=khelsutra;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    foreach ($createdIds['notifications'] as $nid) {
        $pdo->exec("DELETE FROM notifications WHERE id = $nid");
    }
    foreach ($createdIds['finance_payments'] as $pid) {
        $pdo->exec("DELETE FROM finance_payments WHERE id = $pid");
    }
    foreach ($createdIds['expenses'] as $eid) {
        $pdo->exec("DELETE FROM expenses WHERE id = $eid");
    }
    foreach ($createdIds['budgets'] as $bid) {
        $pdo->exec("DELETE FROM budget_items WHERE budget_id = $bid");
        $pdo->exec("DELETE FROM budgets WHERE id = $bid");
    }
    foreach ($createdIds['income_transactions'] as $iid) {
        $pdo->exec("DELETE FROM income_transactions WHERE id = $iid");
    }
    foreach ($createdIds['finance_categories'] as $fcid) {
        $pdo->exec("DELETE FROM finance_categories WHERE id = $fcid");
    }
    foreach ($createdIds['goods_receipts'] as $grid) {
        $pdo->exec("DELETE FROM goods_receipts WHERE id = $grid");
    }
    foreach ($createdIds['purchase_orders'] as $poid) {
        $pdo->exec("DELETE FROM purchase_order_items WHERE purchase_order_id = $poid");
        $pdo->exec("DELETE FROM purchase_orders WHERE id = $poid");
    }
    foreach ($createdIds['purchase_requests'] as $prid) {
        $pdo->exec("DELETE FROM purchase_request_items WHERE purchase_request_id = $prid");
        $pdo->exec("DELETE FROM purchase_requests WHERE id = $prid");
    }
    foreach ($createdIds['vendor_invoices'] as $viid) {
        $pdo->exec("DELETE FROM vendor_invoices WHERE id = $viid");
    }
    foreach ($createdIds['vendors'] as $vid) {
        $pdo->exec("DELETE FROM vendors WHERE id = $vid");
    }
    foreach ($createdIds['equipment_assignments'] as $eaid) {
        $pdo->exec("DELETE FROM equipment_assignments WHERE id = $eaid");
    }
    foreach ($createdIds['equipment'] as $eqid) {
        $pdo->exec("DELETE FROM equipment WHERE id = $eqid");
    }
    foreach ($createdIds['inventory_items'] as $itmid) {
        $pdo->exec("DELETE FROM stock_transactions WHERE inventory_item_id = $itmid");
        $pdo->exec("DELETE FROM inventory_items WHERE id = $itmid");
    }
    foreach ($createdIds['inventory_categories'] as $catid) {
        $pdo->exec("DELETE FROM inventory_categories WHERE id = $catid");
    }
    echo " [CLEANUP] Successfully cleaned up all test records.\n";
}

echo "\n======================================================================\n";
echo " Final Integration Results: {$passCount} Passed, {$failCount} Failed.\n";
echo "======================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
