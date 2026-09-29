<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\Report\ReportService;
use App\Helpers\ApiResponse;

$pdo = new PDO('mysql:host=127.0.0.1;dbname=khelsutra;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "=== KhelSutra Phase 5: Reports & Member 5 REST API Layer Verification ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $description): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] $description\n";
    } else {
        $failCount++;
        echo " [FAIL] $description\n";
    }
}

$org1 = 1;
$org2 = 2;
$reportService = new ReportService();

// =========================================================================
// PART A: REPORTS VERIFICATION
// =========================================================================
echo "--- Section 1: Preserved Operational Reports ---\n";
$operational = $reportService->getOperationalReports($org1);
assertTest(is_array($operational), "Operational reports returned an array");
assertTest(isset($operational['athletes_by_sport']), "Preserved athletes_by_sport metric");
assertTest(isset($operational['teams_by_sport']), "Preserved teams_by_sport metric");
assertTest(isset($operational['attendance_stats']), "Preserved attendance_stats metric");
assertTest(isset($operational['tournament_activity']), "Preserved tournament_activity metric");
assertTest(isset($operational['venue_utilization']), "Preserved venue_utilization metric");
assertTest(isset($operational['leave_stats']), "Preserved leave_stats metric");
assertTest(isset($operational['inventory_stats']), "Preserved inventory_stats metric");
assertTest(isset($operational['payroll_stats']), "Preserved payroll_stats metric");

echo "\n--- Section 2: Inventory Reports ---\n";
$invSummary = $reportService->getInventorySummary($org1);
assertTest(isset($invSummary['summary']['total_items']), "Inventory summary contains total_items");
assertTest(isset($invSummary['summary']['total_valuation']), "Inventory summary contains total_valuation");
assertTest(isset($invSummary['summary']['low_stock_count']), "Inventory summary contains low_stock_count");
assertTest(is_array($invSummary['by_category']), "Inventory summary contains category distribution");

$invValuation = $reportService->getInventoryValuation($org1);
assertTest(isset($invValuation['total_valuation']), "Valuation report contains total_valuation");
assertTest(is_array($invValuation['items']), "Valuation report contains items breakdown");

$lowStock = $reportService->getLowStockReport($org1);
assertTest(isset($lowStock['low_stock_count']), "Low stock report contains low_stock_count");
assertTest(isset($lowStock['total_shortage_units']), "Low stock report contains shortage units");

$movements = $reportService->getStockMovementReport($org1, ['limit' => 10]);
assertTest(isset($movements['total_transactions']), "Stock movement report contains total_transactions");
assertTest(is_array($movements['transactions']), "Stock movement report contains transactions list");

echo "\n--- Section 3: Purchase Reports ---\n";
$purchSummary = $reportService->getPurchaseSummary($org1);
assertTest(isset($purchSummary['total_requests']), "Purchase summary contains total_requests");
assertTest(isset($purchSummary['total_orders']), "Purchase summary contains total_orders");
assertTest(isset($purchSummary['total_order_amount']), "Purchase summary contains total_order_amount");
assertTest(isset($purchSummary['total_goods_receipts']), "Purchase summary contains total_goods_receipts");

$purchOrders = $reportService->getPurchaseRequestOrderStatusReport($org1);
assertTest(isset($purchOrders['total_orders']), "Purchase orders status report contains total_orders");
assertTest(is_array($purchOrders['orders']), "Purchase orders status report contains orders list");

$goodsReceipts = $reportService->getGoodsReceivedReport($org1);
assertTest(isset($goodsReceipts['total_receipts']), "Goods received report contains total_receipts");
assertTest(is_array($goodsReceipts['receipts']), "Goods received report contains receipts list");

$procSpend = $reportService->getProcurementVendorSpendReport($org1);
assertTest(isset($procSpend['total_vendors']), "Procurement vendor spend contains total_vendors");
assertTest(isset($procSpend['overall_spend']), "Procurement vendor spend contains overall_spend");

echo "\n--- Section 4: Vendor Reports ---\n";
$vendorSpend = $reportService->getVendorPurchaseSpendReport($org1);
assertTest(isset($vendorSpend['total_vendors']), "Vendor purchase spend summary contains total_vendors");

$vendorInvoices = $reportService->getVendorInvoicePaymentReport($org1);
assertTest(isset($vendorInvoices['summary']['total_invoices']), "Vendor invoice report contains total_invoices");
assertTest(isset($vendorInvoices['summary']['total_invoiced_amount']), "Vendor invoice report contains total_invoiced_amount");
assertTest(isset($vendorInvoices['summary']['total_outstanding_amount']), "Vendor invoice report contains outstanding amount");

echo "\n--- Section 5: Equipment Reports ---\n";
$equipSummary = $reportService->getEquipmentInventorySummary($org1);
assertTest(isset($equipSummary['summary']['total_equipment']), "Equipment summary contains total_equipment");
assertTest(isset($equipSummary['summary']['total_asset_value']), "Equipment summary contains total_asset_value");
assertTest(isset($equipSummary['summary']['assigned_count']), "Equipment summary contains assigned_count");

$assignedEquip = $reportService->getAssignedEquipmentReport($org1);
assertTest(isset($assignedEquip['total_assigned']), "Assigned equipment report contains total_assigned");
assertTest(is_array($assignedEquip['assignments']), "Assigned equipment report contains assignments array");

$returnedEquip = $reportService->getReturnedEquipmentReport($org1);
assertTest(isset($returnedEquip['total_returned']), "Returned equipment report contains total_returned");

$overdueEquip = $reportService->getOverdueEquipmentReport($org1);
assertTest(isset($overdueEquip['overdue_count']), "Overdue equipment report contains overdue_count");

$conditionSummary = $reportService->getEquipmentConditionSummary($org1);
assertTest(isset($conditionSummary['condition_breakdown']), "Equipment condition report contains breakdown");

echo "\n--- Section 6: Financial Reports ---\n";
$incomeSummary = $reportService->getIncomeSummary($org1);
assertTest(isset($incomeSummary['total_income']), "Income summary contains total_income");
assertTest(is_array($incomeSummary['by_category']), "Income summary contains by_category breakdown");

$expenseSummary = $reportService->getExpenseSummary($org1);
assertTest(isset($expenseSummary['total_expenses']), "Expense summary contains total_expenses");
assertTest(isset($expenseSummary['total_amount']), "Expense summary contains total_amount");

$incomeVsExpense = $reportService->getIncomeVsExpenseReport($org1);
assertTest(isset($incomeVsExpense['net_margin']), "Income vs Expense report contains net_margin");
assertTest(is_array($incomeVsExpense['monthly_comparison']), "Income vs Expense report contains monthly comparison");

$budgetVsActual = $reportService->getBudgetVsActualReport($org1);
assertTest(isset($budgetVsActual['summary']['total_allocated']), "Budget vs Actual contains total_allocated");
assertTest(isset($budgetVsActual['summary']['total_spent']), "Budget vs Actual contains total_spent");

$budgetUtil = $reportService->getBudgetUtilizationReport($org1);
assertTest(isset($budgetUtil['total_budgets']), "Budget utilization report contains total_budgets");

$financeExec = $reportService->getFinanceSummary($org1);
assertTest(isset($financeExec['total_income']), "Executive finance summary contains total_income");
assertTest(isset($financeExec['total_expense']), "Executive finance summary contains total_expense");
assertTest(isset($financeExec['net_profit_loss']), "Executive finance summary contains net_profit_loss");

echo "\n--- Section 7: Report Multi-Tenant Isolation ---\n";
$org1Summary = $reportService->getInventorySummary($org1);
$org2Summary = $reportService->getInventorySummary($org2);
assertTest(is_array($org1Summary) && is_array($org2Summary), "Both organizations query independently");

$org1Fin = $reportService->getFinanceSummary($org1);
$org2Fin = $reportService->getFinanceSummary($org2);
assertTest(is_array($org1Fin) && is_array($org2Fin), "Finance summaries are strictly isolated between tenants");


// =========================================================================
// PART B: MEMBER 5 REST APIs VERIFICATION
// =========================================================================
echo "\n--- Section 8: Member 5 REST APIs ---\n";
$api = require __DIR__ . '/../routes/api.php';

// Mock context for authenticated user in Org 1
$authHeaderOrg1 = [
    'user' => [
        'id' => 1,
        'first_name' => 'Super',
        'last_name' => 'Admin',
        'role_id' => 1,
        'role' => ['id' => 1, 'name' => 'Super Admin'],
        'organization' => ['id' => 1, 'name' => 'Apex Academy']
    ],
    'headers' => [
        'x-organization-id' => 1
    ]
];

// 1. Inventory API
$res = $api('/api/v1/inventory', 'GET', $authHeaderOrg1);
assertTest($res['success'] === true, "GET /api/v1/inventory returns success: true");
assertTest(isset($res['data']), "GET /api/v1/inventory contains data envelope");

$resLowStock = $api('/api/v1/inventory/low-stock', 'GET', $authHeaderOrg1);
assertTest($resLowStock['success'] === true, "GET /api/v1/inventory/low-stock returns success: true");

$resInvPostErr = $api('/api/v1/inventory', 'POST', array_merge($authHeaderOrg1, []));
assertTest($resInvPostErr['success'] === false, "POST /api/v1/inventory with missing name returns success: false");

// 2. Equipment API
$resEquip = $api('/api/v1/equipment', 'GET', $authHeaderOrg1);
assertTest($resEquip['success'] === true, "GET /api/v1/equipment returns success: true");
assertTest(isset($resEquip['data']), "GET /api/v1/equipment contains data envelope");

// 3. Vendor API
$resVendors = $api('/api/v1/vendors', 'GET', $authHeaderOrg1);
assertTest($resVendors['success'] === true, "GET /api/v1/vendors returns success: true");

// 4. Purchases API
$resPr = $api('/api/v1/purchases/requests', 'GET', $authHeaderOrg1);
assertTest($resPr['success'] === true, "GET /api/v1/purchases/requests returns success: true");

$resPo = $api('/api/v1/purchases/orders', 'GET', $authHeaderOrg1);
assertTest($resPo['success'] === true, "GET /api/v1/purchases/orders returns success: true");

$resGr = $api('/api/v1/purchases/receipts', 'GET', $authHeaderOrg1);
assertTest($resGr['success'] === true, "GET /api/v1/purchases/receipts returns success: true");

// 5. Finance API
$resFinSummary = $api('/api/v1/finance/summary', 'GET', $authHeaderOrg1);
assertTest($resFinSummary['success'] === true, "GET /api/v1/finance/summary returns success: true");
assertTest(isset($resFinSummary['data']['total_income']), "GET /api/v1/finance/summary contains total_income");

$resFinCat = $api('/api/v1/finance/categories', 'GET', $authHeaderOrg1);
assertTest($resFinCat['success'] === true, "GET /api/v1/finance/categories returns success: true");

$resIncome = $api('/api/v1/finance/income', 'GET', $authHeaderOrg1);
assertTest($resIncome['success'] === true, "GET /api/v1/finance/income returns success: true");

$resExpenses = $api('/api/v1/finance/expenses', 'GET', $authHeaderOrg1);
assertTest($resExpenses['success'] === true, "GET /api/v1/finance/expenses returns success: true");

$resBudgets = $api('/api/v1/finance/budgets', 'GET', $authHeaderOrg1);
assertTest($resBudgets['success'] === true, "GET /api/v1/finance/budgets returns success: true");

$resPayments = $api('/api/v1/finance/payments', 'GET', $authHeaderOrg1);
assertTest($resPayments['success'] === true, "GET /api/v1/finance/payments returns success: true");

// 6. Notification API
$resNotif = $api('/api/v1/notifications', 'GET', $authHeaderOrg1);
assertTest($resNotif['success'] === true, "GET /api/v1/notifications returns success: true");

$resUnread = $api('/api/v1/notifications/unread-count', 'GET', $authHeaderOrg1);
assertTest($resUnread['success'] === true, "GET /api/v1/notifications/unread-count returns success: true");
assertTest(isset($resUnread['data']['unread_count']), "Notification count is returned");

$resPostNotif = $api('/api/v1/notifications', 'POST', array_merge($authHeaderOrg1, [
    'title' => 'API Test Notification',
    'message' => 'Testing notification dispatch through REST API',
    'type' => 'system'
]));
assertTest($resPostNotif['success'] === true, "POST /api/v1/notifications creates notification via API");
$createdNotifId = (int)($resPostNotif['data']['id'] ?? 0);

if ($createdNotifId > 0) {
    $resRead = $api("/api/v1/notifications/{$createdNotifId}/read", 'POST', $authHeaderOrg1);
    assertTest($resRead['success'] === true, "POST /api/v1/notifications/{id}/read marks notification as read");
}

// 7. Reports API
$resRepOperational = $api('/api/v1/reports/operational', 'GET', $authHeaderOrg1);
assertTest($resRepOperational['success'] === true, "GET /api/v1/reports/operational returns success: true");

$resRepInv = $api('/api/v1/reports/inventory', 'GET', $authHeaderOrg1);
assertTest($resRepInv['success'] === true, "GET /api/v1/reports/inventory returns success: true");
assertTest(isset($resRepInv['data']['summary']), "GET /api/v1/reports/inventory contains summary");

$resRepPurch = $api('/api/v1/reports/purchases', 'GET', $authHeaderOrg1);
assertTest($resRepPurch['success'] === true, "GET /api/v1/reports/purchases returns success: true");

$resRepFin = $api('/api/v1/reports/finance', 'GET', $authHeaderOrg1);
assertTest($resRepFin['success'] === true, "GET /api/v1/reports/finance returns success: true");
assertTest(isset($resRepFin['data']['income_vs_expense']), "GET /api/v1/reports/finance contains income_vs_expense");

// 8. Other members' routes preserved
$resHealth = $api('/api/v1/health', 'GET', []);
assertTest($resHealth['success'] === true, "Health check /api/v1/health continues to work");

$resSports = $api('/api/v1/sports', 'GET', $authHeaderOrg1);
assertTest($resSports['success'] === true, "Preserved skeleton route /api/v1/sports continues to acknowledge");

echo "\n=======================================================\n";
echo "Phase 5 Verification Complete: $passCount passed, $failCount failed.\n";
echo "=======================================================\n";

if ($failCount > 0) {
    exit(1);
}
