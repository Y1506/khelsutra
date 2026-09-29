<?php
$activePage = 'reports';
$title = 'Reports & Analytics — KhelSutra';

$reportService = new \App\Services\Report\ReportService();
$orgId = $_SESSION['current_organization_id'] ?? 1;

$tab = $_GET['tab'] ?? 'operational';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$status = $_GET['status'] ?? '';
$vendorId = !empty($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : null;
$search = $_GET['search'] ?? '';

$filters = [
    'date_from' => $dateFrom ?: null,
    'date_to' => $dateTo ?: null,
    'category_id' => $categoryId,
    'status' => $status ?: null,
    'vendor_id' => $vendorId,
    'search' => $search ?: null,
];

// Preserved Operational Reports
$reports = $reportService->getOperationalReports($orgId);
$athletesBySport = $reports['athletes_by_sport'] ?? [];
$teamsBySport = $reports['teams_by_sport'] ?? [];
$attendance = $reports['attendance_stats'] ?? [];
$tournaments = $reports['tournament_activity'] ?? [];
$venues = $reports['venue_utilization'] ?? [];
$leave = $reports['leave_stats'] ?? [];
$inventory = $reports['inventory_stats'] ?? [];
$payroll = $reports['payroll_stats'] ?? [];

// Member 5 Reports data loading based on tab
$invSummary = null;
$invValuation = null;
$lowStock = null;
$stockMovements = null;

$purchSummary = null;
$purchOrders = null;
$goodsReceipts = null;
$procSpend = null;

$vendorSpend = null;
$vendorInvoices = null;

$equipSummary = null;
$assignedEquip = null;
$returnedEquip = null;
$overdueEquip = null;
$conditionReport = null;

$finSummary = null;
$incomeSummary = null;
$expenseSummary = null;
$incomeVsExpense = null;
$budgetVsActual = null;

if ($tab === 'inventory') {
    $invSummary = $reportService->getInventorySummary($orgId, $filters);
    $invValuation = $reportService->getInventoryValuation($orgId, $filters);
    $lowStock = $reportService->getLowStockReport($orgId, $filters);
    $stockMovements = $reportService->getStockMovementReport($orgId, array_merge($filters, ['limit' => 25]));
} elseif ($tab === 'purchases') {
    $purchSummary = $reportService->getPurchaseSummary($orgId, $filters);
    $purchOrders = $reportService->getPurchaseRequestOrderStatusReport($orgId, $filters);
    $goodsReceipts = $reportService->getGoodsReceivedReport($orgId, $filters);
    $procSpend = $reportService->getProcurementVendorSpendReport($orgId, $filters);
} elseif ($tab === 'vendors') {
    $vendorSpend = $reportService->getVendorPurchaseSpendReport($orgId, $filters);
    $vendorInvoices = $reportService->getVendorInvoicePaymentReport($orgId, $filters);
} elseif ($tab === 'equipment') {
    $equipSummary = $reportService->getEquipmentInventorySummary($orgId, $filters);
    $assignedEquip = $reportService->getAssignedEquipmentReport($orgId, $filters);
    $returnedEquip = $reportService->getReturnedEquipmentReport($orgId, $filters);
    $overdueEquip = $reportService->getOverdueEquipmentReport($orgId, $filters);
    $conditionReport = $reportService->getEquipmentConditionSummary($orgId, $filters);
} elseif ($tab === 'finance') {
    $finSummary = $reportService->getFinanceSummary($orgId, $filters);
    $incomeSummary = $reportService->getIncomeSummary($orgId, $filters);
    $expenseSummary = $reportService->getExpenseSummary($orgId, $filters);
    $incomeVsExpense = $reportService->getIncomeVsExpenseReport($orgId, $filters);
    $budgetVsActual = $reportService->getBudgetVsActualReport($orgId, $filters);
}

ob_start();
?>

<!-- Page Header (Section 8: Page Title + Primary Action) -->
<div class="ks-page-header">
    <div>
        <h1 class="ks-page-title">Reports & Analytics</h1>
        <p class="text-muted small mb-0">Comprehensive operational and financial intelligence across all club departments.</p>
    </div>
    <div class="ks-header-actions">
        <button class="ks-btn ks-btn-secondary" onclick="window.print()">
            <i class="bi bi-printer"></i>
            <span>Print Report</span>
        </button>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs ks-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'operational' ? 'active' : '' ?>" href="/reports?tab=operational">
            <i class="bi bi-speedometer2 me-1"></i> Operational Overview
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'inventory' ? 'active' : '' ?>" href="/reports?tab=inventory">
            <i class="bi bi-boxes me-1"></i> Inventory & Stock
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'purchases' ? 'active' : '' ?>" href="/reports?tab=purchases">
            <i class="bi bi-cart-check me-1"></i> Procurement & Purchases
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'vendors' ? 'active' : '' ?>" href="/reports?tab=vendors">
            <i class="bi bi-building me-1"></i> Vendor Intelligence
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'equipment' ? 'active' : '' ?>" href="/reports?tab=equipment">
            <i class="bi bi-tools me-1"></i> Equipment & Assets
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'finance' ? 'active' : '' ?>" href="/reports?tab=finance">
            <i class="bi bi-cash-stack me-1"></i> Financial Reports
        </a>
    </li>
</ul>

<?php if ($tab !== 'operational'): ?>
<!-- Filter Bar for Member 5 Reports -->
<div class="ks-card p-3 mb-4">
    <form method="GET" action="/reports" class="row g-2 align-items-end">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted mb-1">Date From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted mb-1">Date To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <?php if (in_array($tab, ['inventory', 'purchases', 'vendors', 'finance'])): ?>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <?php if ($tab === 'inventory'): ?>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <?php elseif ($tab === 'purchases'): ?>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
                    <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                <?php elseif ($tab === 'vendors'): ?>
                    <option value="unpaid" <?= $status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                    <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>Partial</option>
                <?php elseif ($tab === 'finance'): ?>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                <?php endif; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="ks-btn ks-btn-primary btn-sm flex-fill">
                <i class="bi bi-funnel-fill me-1"></i> Apply Filter
            </button>
            <a href="/reports?tab=<?= htmlspecialchars($tab) ?>" class="ks-btn ks-btn-secondary btn-sm">
                Reset
            </a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB 1: OPERATIONAL REPORTS (100% PRESERVED EXISTING OPERATIONAL REPORTS)  -->
<!-- ========================================================================= -->
<?php if ($tab === 'operational'): ?>
<div class="row g-3 mb-4">
    <!-- Training Attendance Rate -->
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-blue">
                    <i class="bi bi-calendar-check-fill fs-4"></i>
                </div>
                <div>
                    <div class="ks-kpi-label">Training Attendance</div>
                    <div class="ks-kpi-value"><?= htmlspecialchars((string)($attendance['attendance_rate'] ?? 0)) ?>%</div>
                </div>
            </div>
            <div class="ks-kpi-bottom">
                <span class="ks-trend-text ks-trend-positive"><?= (int)($attendance['present_count'] ?? 0) ?> Present / <?= (int)($attendance['total_records'] ?? 0) ?> Tracked</span>
            </div>
        </div>
    </div>

    <!-- Tournament Activity -->
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-amber">
                    <i class="bi bi-trophy-fill fs-4"></i>
                </div>
                <div>
                    <div class="ks-kpi-label">Tournament Fixtures</div>
                    <div class="ks-kpi-value"><?= (int)($tournaments['total_fixtures'] ?? 0) ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom">
                <span class="ks-trend-text"><?= (int)($tournaments['completed_matches'] ?? 0) ?> Played &bull; <?= (int)($tournaments['pending_matches'] ?? 0) ?> Pending</span>
            </div>
        </div>
    </div>

    <!-- Inventory Valuation -->
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-purple">
                    <i class="bi bi-box-seam-fill fs-4"></i>
                </div>
                <div>
                    <div class="ks-kpi-label">Inventory Assets</div>
                    <div class="ks-kpi-value">₹<?= number_format((float)($inventory['total_valuation'] ?? 0), 2) ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom">
                <span class="ks-trend-text"><?= (int)($inventory['total_items'] ?? 0) ?> Items (<?= (int)($inventory['low_stock_count'] ?? 0) ?> Low Stock)</span>
            </div>
        </div>
    </div>

    <!-- Payroll Disbursed -->
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-green">
                    <i class="bi bi-currency-rupee fs-4"></i>
                </div>
                <div>
                    <div class="ks-kpi-label">Payroll Disbursed</div>
                    <div class="ks-kpi-value">₹<?= number_format((float)($payroll['total_net'] ?? 0), 2) ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom">
                <span class="ks-trend-text ks-trend-positive"><?= (int)($payroll['total_employees'] ?? 0) ?> Employees on Roster</span>
            </div>
        </div>
    </div>
</div>

<div class="ks-reports-grid mb-4">
    <!-- Athletes Distribution by Sport -->
    <div>
        <div class="ks-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="fw-bold text-navy mb-0" style="font-size: 16px;">Athletes by Sport</h3>
                <span class="ks-badge ks-badge-blue"><?= count($athletesBySport) ?> Sports</span>
            </div>
            <?php if (empty($athletesBySport)): ?>
                <p class="text-muted small mb-0">No athletes registered under any sport yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="ks-table">
                        <thead>
                            <tr>
                                <th>Sport</th>
                                <th style="text-align: right;">Athlete Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($athletesBySport as $as): ?>
                                <tr>
                                    <td><span class="fw-semibold text-navy"><?= htmlspecialchars($as['sport_name']) ?></span></td>
                                    <td style="text-align: right;"><span class="ks-badge ks-badge-blue"><?= (int)$as['count'] ?> Athletes</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Teams Distribution by Sport -->
    <div>
        <div class="ks-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="fw-bold text-navy mb-0" style="font-size: 16px;">Teams by Sport</h3>
                <span class="ks-badge ks-badge-purple"><?= count($teamsBySport) ?> Disciplines</span>
            </div>
            <?php if (empty($teamsBySport)): ?>
                <p class="text-muted small mb-0">No teams created yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="ks-table">
                        <thead>
                            <tr>
                                <th>Sport</th>
                                <th style="text-align: right;">Active Squads</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teamsBySport as $ts): ?>
                                <tr>
                                    <td><span class="fw-semibold text-navy"><?= htmlspecialchars($ts['sport_name']) ?></span></td>
                                    <td style="text-align: right;"><span class="ks-badge ks-badge-purple"><?= (int)$ts['count'] ?> Teams</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="ks-reports-grid">
    <!-- Venue Utilization -->
    <div>
        <div class="ks-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="fw-bold text-navy mb-0" style="font-size: 16px;">Venue Utilization</h3>
                <span class="ks-badge ks-badge-cyan"><?= count($venues) ?> Venues</span>
            </div>
            <?php if (empty($venues)): ?>
                <p class="text-muted small mb-0">No venues configured.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="ks-table">
                        <thead>
                            <tr>
                                <th>Venue Name</th>
                                <th style="text-align: right;">Total Bookings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($venues as $v): ?>
                                <tr>
                                    <td><span class="fw-semibold text-navy"><?= htmlspecialchars($v['venue_name']) ?></span></td>
                                    <td style="text-align: right;"><span class="ks-badge ks-badge-cyan"><?= (int)$v['booking_count'] ?> Bookings</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Staff & Leave Status Summary -->
    <div>
        <div class="ks-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="fw-bold text-navy mb-0" style="font-size: 16px;">Staff Leave Summary</h3>
            </div>
            <div class="table-responsive">
                <table class="ks-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th style="text-align: right;">Requests</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="ks-badge ks-badge-amber">Pending Approval</span></td>
                            <td style="text-align: right;" class="fw-bold text-navy"><?= (int)($leave['pending'] ?? 0) ?></td>
                        </tr>
                        <tr>
                            <td><span class="ks-badge ks-badge-green">Approved</span></td>
                            <td style="text-align: right;" class="fw-bold text-navy"><?= (int)($leave['approved'] ?? 0) ?></td>
                        </tr>
                        <tr>
                            <td><span class="ks-badge ks-badge-red">Rejected</span></td>
                            <td style="text-align: right;" class="fw-bold text-navy"><?= (int)($leave['rejected'] ?? 0) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB 2: INVENTORY REPORTS                                                  -->
<!-- ========================================================================= -->
<?php if ($tab === 'inventory' && $invSummary): ?>
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-blue"><i class="bi bi-box-seam fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Total Inventory Items</div>
                    <div class="ks-kpi-value"><?= (int)$invSummary['summary']['total_items'] ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text"><?= (int)$invSummary['summary']['total_units'] ?> Units on hand</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-green"><i class="bi bi-currency-rupee fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Total Stock Valuation</div>
                    <div class="ks-kpi-value">₹<?= number_format((float)$invSummary['summary']['total_valuation'], 2) ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text ks-trend-positive">Asset Book Value</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-amber"><i class="bi bi-exclamation-triangle-fill fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Low-Stock Items</div>
                    <div class="ks-kpi-value"><?= (int)$invSummary['summary']['low_stock_count'] ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text text-warning">Below Minimum Level</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-red"><i class="bi bi-x-circle-fill fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Out of Stock</div>
                    <div class="ks-kpi-value"><?= (int)$invSummary['summary']['out_of_stock_count'] ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text text-danger">Zero Stock Balance</span></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Valuation By Category -->
    <div class="col-lg-6">
        <div class="ks-card p-4 h-100">
            <h3 class="fw-bold text-navy mb-3" style="font-size: 16px;">Stock Valuation by Category</h3>
            <div class="table-responsive">
                <table class="ks-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-center">Items</th>
                            <th class="text-center">Units</th>
                            <th class="text-end">Valuation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invSummary['by_category'] as $cat): ?>
                        <tr>
                            <td class="fw-semibold text-navy"><?= htmlspecialchars($cat['category_name']) ?></td>
                            <td class="text-center"><?= (int)$cat['item_count'] ?></td>
                            <td class="text-center"><?= (int)$cat['total_units'] ?></td>
                            <td class="text-end fw-bold">₹<?= number_format((float)$cat['valuation'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Low-Stock Alerts -->
    <div class="col-lg-6">
        <div class="ks-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="fw-bold text-navy mb-0" style="font-size: 16px;">Low-Stock Action Report</h3>
                <span class="ks-badge ks-badge-red"><?= count($lowStock['items'] ?? []) ?> Items Deficit</span>
            </div>
            <div class="table-responsive">
                <table class="ks-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-center">In Stock</th>
                            <th class="text-center">Min Level</th>
                            <th class="text-end">Est. Restock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lowStock['items'])): ?>
                            <tr><td colspan="4" class="text-muted text-center py-3">All inventory items are currently above minimum threshold.</td></tr>
                        <?php else: ?>
                            <?php foreach (array_slice($lowStock['items'], 0, 8) as $ls): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-navy"><?= htmlspecialchars($ls['item_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($ls['item_code']) ?></small>
                                </td>
                                <td class="text-center text-danger fw-bold"><?= (int)$ls['quantity'] ?></td>
                                <td class="text-center"><?= (int)$ls['minimum_stock_level'] ?></td>
                                <td class="text-end fw-bold">₹<?= number_format((float)$ls['estimated_restock_cost'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Stock Movement History -->
<div class="ks-card p-4">
    <h3 class="fw-bold text-navy mb-3" style="font-size: 16px;">Recent Stock Movements Log</h3>
    <div class="table-responsive">
        <table class="ks-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Type</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Total Cost</th>
                    <th>Performed By</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stockMovements['transactions'])): ?>
                    <tr><td colspan="7" class="text-muted text-center py-3">No stock transactions found.</td></tr>
                <?php else: ?>
                    <?php foreach ($stockMovements['transactions'] as $tx): ?>
                    <tr>
                        <td><small><?= htmlspecialchars(substr($tx['transaction_date'], 0, 10)) ?></small></td>
                        <td>
                            <div class="fw-semibold text-navy"><?= htmlspecialchars($tx['item_name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($tx['item_code']) ?></small>
                        </td>
                        <td>
                            <?php if ($tx['transaction_type'] === 'purchase' || $tx['transaction_type'] === 'in'): ?>
                                <span class="ks-badge ks-badge-green"><?= htmlspecialchars($tx['transaction_type']) ?></span>
                            <?php elseif ($tx['transaction_type'] === 'out' || $tx['transaction_type'] === 'issue'): ?>
                                <span class="ks-badge ks-badge-red"><?= htmlspecialchars($tx['transaction_type']) ?></span>
                            <?php else: ?>
                                <span class="ks-badge ks-badge-amber"><?= htmlspecialchars($tx['transaction_type']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center fw-bold"><?= (int)$tx['quantity'] ?></td>
                        <td class="text-end">₹<?= number_format((float)$tx['total_cost'], 2) ?></td>
                        <td><small><?= htmlspecialchars($tx['performed_by_name'] ?: 'System') ?></small></td>
                        <td><small class="text-muted"><?= htmlspecialchars($tx['remarks'] ?? '—') ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB 3: PROCUREMENT & PURCHASES                                            -->
<!-- ========================================================================= -->
<?php if ($tab === 'purchases' && $purchSummary): ?>
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-blue"><i class="bi bi-file-earmark-text fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Purchase Requests</div>
                    <div class="ks-kpi-value"><?= (int)$purchSummary['total_requests'] ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text"><?= (int)($purchSummary['requests_by_status']['pending'] ?? 0) ?> Pending Approval</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-purple"><i class="bi bi-receipt-cutoff fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Purchase Orders</div>
                    <div class="ks-kpi-value"><?= (int)$purchSummary['total_orders'] ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text"><?= (int)($purchSummary['orders_by_status']['completed']['count'] ?? 0) ?> Fulfilled</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-green"><i class="bi bi-currency-rupee fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Total PO Commitment</div>
                    <div class="ks-kpi-value">₹<?= number_format((float)$purchSummary['total_order_amount'], 2) ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text ks-trend-positive">Procurement Spend</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-amber"><i class="bi bi-truck fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Goods Receipts</div>
                    <div class="ks-kpi-value"><?= (int)$purchSummary['total_goods_receipts'] ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text">Deliveries Recorded</span></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Recent Purchase Orders -->
    <div class="col-lg-7">
        <div class="ks-card p-4 h-100">
            <h3 class="fw-bold text-navy mb-3" style="font-size: 16px;">Purchase Orders Status</h3>
            <div class="table-responsive">
                <table class="ks-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Vendor</th>
                            <th>Date</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($purchOrders['orders'])): ?>
                            <tr><td colspan="5" class="text-muted text-center py-3">No purchase orders found.</td></tr>
                        <?php else: ?>
                            <?php foreach (array_slice($purchOrders['orders'], 0, 10) as $po): ?>
                            <tr>
                                <td class="fw-semibold text-navy"><?= htmlspecialchars($po['po_number']) ?></td>
                                <td><?= htmlspecialchars($po['vendor_name'] ?? '—') ?></td>
                                <td><small><?= htmlspecialchars($po['order_date']) ?></small></td>
                                <td class="text-end fw-bold">₹<?= number_format((float)$po['total_amount'], 2) ?></td>
                                <td>
                                    <?php if ($po['status'] === 'completed'): ?>
                                        <span class="ks-badge ks-badge-green">Completed</span>
                                    <?php elseif ($po['status'] === 'sent' || $po['status'] === 'partial'): ?>
                                        <span class="ks-badge ks-badge-blue"><?= htmlspecialchars($po['status']) ?></span>
                                    <?php elseif ($po['status'] === 'cancelled'): ?>
                                        <span class="ks-badge ks-badge-red">Cancelled</span>
                                    <?php else: ?>
                                        <span class="ks-badge ks-badge-amber">Draft</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Procurement Vendor Spend -->
    <div class="col-lg-5">
        <div class="ks-card p-4 h-100">
            <h3 class="fw-bold text-navy mb-3" style="font-size: 16px;">Vendor Spend Allocation</h3>
            <div class="table-responsive">
                <table class="ks-table">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th class="text-center">POs</th>
                            <th class="text-end">Total Spend</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($procSpend['vendors'])): ?>
                            <tr><td colspan="3" class="text-muted text-center py-3">No vendor orders recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach (array_slice($procSpend['vendors'], 0, 8) as $vs): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-navy"><?= htmlspecialchars($vs['company_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($vs['vendor_code']) ?></small>
                                </td>
                                <td class="text-center"><?= (int)$vs['total_orders'] ?></td>
                                <td class="text-end fw-bold">₹<?= number_format((float)$vs['total_spend'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Goods Received Log -->
<div class="ks-card p-4">
    <h3 class="fw-bold text-navy mb-3" style="font-size: 16px;">Goods Received Delivery Log</h3>
    <div class="table-responsive">
        <table class="ks-table">
            <thead>
                <tr>
                    <th>GRN Reference</th>
                    <th>Date</th>
                    <th>PO Ref</th>
                    <th>Vendor</th>
                    <th>Received By</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($goodsReceipts['receipts'])): ?>
                    <tr><td colspan="6" class="text-muted text-center py-3">No goods receipts registered yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($goodsReceipts['receipts'] as $gr): ?>
                    <tr>
                        <td class="fw-semibold text-navy"><?= htmlspecialchars($gr['receipt_number']) ?></td>
                        <td><small><?= htmlspecialchars($gr['receipt_date']) ?></small></td>
                        <td><span class="ks-badge ks-badge-purple"><?= htmlspecialchars($gr['po_number']) ?></span></td>
                        <td><?= htmlspecialchars($gr['vendor_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($gr['received_by_name'] ?: 'Staff') ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($gr['remarks'] ?? '—') ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB 4: VENDOR INTELLIGENCE                                                -->
<!-- ========================================================================= -->
<?php if ($tab === 'vendors' && $vendorInvoices): ?>
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-blue"><i class="bi bi-building fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Active Vendors</div>
                    <div class="ks-kpi-value"><?= (int)($vendorSpend['total_vendors'] ?? 0) ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text">Partner Network</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-purple"><i class="bi bi-receipt fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Total Invoiced</div>
                    <div class="ks-kpi-value">₹<?= number_format((float)$vendorInvoices['summary']['total_invoiced_amount'], 2) ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text"><?= (int)$vendorInvoices['summary']['total_invoices'] ?> Invoices</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-green"><i class="bi bi-check2-circle fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Total Settled</div>
                    <div class="ks-kpi-value">₹<?= number_format((float)$vendorInvoices['summary']['total_paid_amount'], 2) ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text ks-trend-positive">Paid in Full</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-red"><i class="bi bi-clock-history fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Pending / Overdue</div>
                    <div class="ks-kpi-value">₹<?= number_format((float)$vendorInvoices['summary']['total_outstanding_amount'], 2) ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text text-danger"><?= (int)$vendorInvoices['summary']['overdue_invoices_count'] ?> Overdue</span></div>
        </div>
    </div>
</div>

<div class="ks-card p-4">
    <h3 class="fw-bold text-navy mb-3" style="font-size: 16px;">Vendor Invoices Ledger</h3>
    <div class="table-responsive">
        <table class="ks-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Vendor</th>
                    <th>PO Ref</th>
                    <th>Invoice Date</th>
                    <th>Due Date</th>
                    <th class="text-end">Amount</th>
                    <th>Payment Status</th>
                    <th>Overdue</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vendorInvoices['invoices'])): ?>
                    <tr><td colspan="8" class="text-muted text-center py-3">No vendor invoices found.</td></tr>
                <?php else: ?>
                    <?php foreach ($vendorInvoices['invoices'] as $inv): ?>
                    <tr>
                        <td class="fw-semibold text-navy"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($inv['vendor_name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($inv['vendor_code']) ?></small>
                        </td>
                        <td><span class="ks-badge ks-badge-purple"><?= htmlspecialchars($inv['po_number'] ?? '—') ?></span></td>
                        <td><small><?= htmlspecialchars($inv['invoice_date']) ?></small></td>
                        <td><small><?= htmlspecialchars($inv['due_date']) ?></small></td>
                        <td class="text-end fw-bold">₹<?= number_format((float)$inv['total_amount'], 2) ?></td>
                        <td>
                            <?php if ($inv['payment_status'] === 'paid'): ?>
                                <span class="ks-badge ks-badge-green">Paid</span>
                            <?php elseif ($inv['payment_status'] === 'partial'): ?>
                                <span class="ks-badge ks-badge-amber">Partial</span>
                            <?php else: ?>
                                <span class="ks-badge ks-badge-red">Unpaid</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$inv['days_overdue'] > 0): ?>
                                <span class="badge bg-danger text-white"><?= (int)$inv['days_overdue'] ?> days</span>
                            <?php else: ?>
                                <span class="text-success small"><i class="bi bi-check"></i> On schedule</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB 5: EQUIPMENT & ASSETS                                                 -->
<!-- ========================================================================= -->
<?php if ($tab === 'equipment' && $equipSummary): ?>
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-blue"><i class="bi bi-tools fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Total Equipment</div>
                    <div class="ks-kpi-value"><?= (int)$equipSummary['summary']['total_equipment'] ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text"><?= (int)$equipSummary['summary']['available_count'] ?> Available for assignment</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-green"><i class="bi bi-currency-rupee fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Equipment Asset Value</div>
                    <div class="ks-kpi-value">₹<?= number_format((float)$equipSummary['summary']['total_asset_value'], 2) ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text ks-trend-positive">Capital Investment</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-purple"><i class="bi bi-person-badge fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Currently Assigned</div>
                    <div class="ks-kpi-value"><?= (int)$equipSummary['summary']['assigned_count'] ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text">In Active Deployment</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-red"><i class="bi bi-alarm-fill fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Overdue Returns</div>
                    <div class="ks-kpi-value"><?= (int)($overdueEquip['overdue_count'] ?? 0) ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text text-danger">Past Expected Return</span></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Active Assignments -->
    <div class="col-lg-7">
        <div class="ks-card p-4 h-100">
            <h3 class="fw-bold text-navy mb-3" style="font-size: 16px;">Active Equipment Assignments</h3>
            <div class="table-responsive">
                <table class="ks-table">
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Assignee</th>
                            <th>Assigned Date</th>
                            <th>Expected Return</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignedEquip['assignments'])): ?>
                            <tr><td colspan="4" class="text-muted text-center py-3">No equipment currently assigned.</td></tr>
                        <?php else: ?>
                            <?php foreach (array_slice($assignedEquip['assignments'], 0, 8) as $ea): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-navy"><?= htmlspecialchars($ea['equipment_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($ea['asset_code']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($ea['assignee_name']) ?></div>
                                    <span class="ks-badge ks-badge-blue"><?= htmlspecialchars($ea['assignee_type']) ?></span>
                                </td>
                                <td><small><?= htmlspecialchars($ea['assigned_date']) ?></small></td>
                                <td><small class="fw-bold"><?= htmlspecialchars($ea['expected_return_date'] ?? 'Open-ended') ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Condition Breakdown -->
    <div class="col-lg-5">
        <div class="ks-card p-4 h-100">
            <h3 class="fw-bold text-navy mb-3" style="font-size: 16px;">Condition Status Breakdown</h3>
            <div class="table-responsive">
                <table class="ks-table">
                    <thead>
                        <tr>
                            <th>Condition</th>
                            <th class="text-center">Count</th>
                            <th class="text-end">Asset Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($conditionReport['condition_breakdown'])): ?>
                            <tr><td colspan="3" class="text-muted text-center py-3">No condition records.</td></tr>
                        <?php else: ?>
                            <?php foreach ($conditionReport['condition_breakdown'] as $cb): ?>
                            <tr>
                                <td>
                                    <?php if ($cb['condition_status'] === 'new' || $cb['condition_status'] === 'good'): ?>
                                        <span class="ks-badge ks-badge-green"><?= htmlspecialchars($cb['condition_status']) ?></span>
                                    <?php elseif ($cb['condition_status'] === 'fair'): ?>
                                        <span class="ks-badge ks-badge-amber"><?= htmlspecialchars($cb['condition_status']) ?></span>
                                    <?php else: ?>
                                        <span class="ks-badge ks-badge-red"><?= htmlspecialchars($cb['condition_status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold"><?= (int)$cb['count'] ?></td>
                                <td class="text-end">₹<?= number_format((float)$cb['valuation'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Overdue Returns Alert Table -->
<?php if (!empty($overdueEquip['items'])): ?>
<div class="ks-card p-4 border border-danger">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="fw-bold text-danger mb-0" style="font-size: 16px;"><i class="bi bi-exclamation-octagon-fill me-2"></i>Overdue Equipment Alert</h3>
        <span class="ks-badge ks-badge-red"><?= count($overdueEquip['items']) ?> Overdue</span>
    </div>
    <div class="table-responsive">
        <table class="ks-table">
            <thead>
                <tr>
                    <th>Asset</th>
                    <th>Assignee</th>
                    <th>Due Date</th>
                    <th>Days Overdue</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($overdueEquip['items'] as $od): ?>
                <tr>
                    <td>
                        <div class="fw-semibold text-navy"><?= htmlspecialchars($od['equipment_name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($od['asset_code']) ?></small>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($od['assignee_name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($od['assignee_type']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($od['expected_return_date']) ?></td>
                    <td><span class="badge bg-danger text-white"><?= (int)$od['days_overdue'] ?> Days Overdue</span></td>
                    <td>
                        <a href="/equipment/<?= (int)$od['id'] ?>" class="btn btn-sm btn-outline-danger">Inspect Return</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB 6: FINANCIAL REPORTS                                                  -->
<!-- ========================================================================= -->
<?php if ($tab === 'finance' && $finSummary): ?>
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-green"><i class="bi bi-graph-up-arrow fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Total Income</div>
                    <div class="ks-kpi-value">₹<?= number_format((float)$finSummary['total_income'], 2) ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text ks-trend-positive">Total Revenue Inflows</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-red"><i class="bi bi-graph-down-arrow fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Total Expenses</div>
                    <div class="ks-kpi-value">₹<?= number_format((float)$finSummary['total_expense'], 2) ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text">Operational Outflows</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-purple"><i class="bi bi-wallet2 fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Net Operating Margin</div>
                    <div class="ks-kpi-value <?= $finSummary['net_profit_loss'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        ₹<?= number_format((float)$finSummary['net_profit_loss'], 2) ?>
                    </div>
                </div>
            </div>
            <div class="ks-kpi-bottom">
                <span class="ks-trend-text <?= $finSummary['net_profit_loss'] >= 0 ? 'ks-trend-positive' : 'text-danger' ?>">
                    <?= $finSummary['net_profit_loss'] >= 0 ? 'Operating Surplus' : 'Operating Deficit' ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-amber"><i class="bi bi-pie-chart fs-4"></i></div>
                <div>
                    <div class="ks-kpi-label">Budget Utilization</div>
                    <div class="ks-kpi-value"><?= number_format((float)$finSummary['budget_utilization_rate'], 1) ?>%</div>
                </div>
            </div>
            <div class="ks-kpi-bottom"><span class="ks-trend-text">₹<?= number_format((float)$finSummary['budget_spent'], 2) ?> / ₹<?= number_format((float)$finSummary['budget_allocated'], 2) ?></span></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Income by Category -->
    <div class="col-lg-6">
        <div class="ks-card p-4 h-100">
            <h3 class="fw-bold text-navy mb-3" style="font-size: 16px;">Income by Category</h3>
            <div class="table-responsive">
                <table class="ks-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-center">Transactions</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($incomeSummary['by_category'])): ?>
                            <tr><td colspan="3" class="text-muted text-center py-3">No income recorded for this period.</td></tr>
                        <?php else: ?>
                            <?php foreach ($incomeSummary['by_category'] as $inc): ?>
                            <tr>
                                <td class="fw-semibold text-navy"><?= htmlspecialchars($inc['category_name']) ?></td>
                                <td class="text-center"><?= (int)$inc['transaction_count'] ?></td>
                                <td class="text-end fw-bold text-success">₹<?= number_format((float)$inc['total_amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Expense by Category -->
    <div class="col-lg-6">
        <div class="ks-card p-4 h-100">
            <h3 class="fw-bold text-navy mb-3" style="font-size: 16px;">Expense by Category</h3>
            <div class="table-responsive">
                <table class="ks-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-center">Count</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenseSummary['by_category'])): ?>
                            <tr><td colspan="3" class="text-muted text-center py-3">No expenses recorded for this period.</td></tr>
                        <?php else: ?>
                            <?php foreach ($expenseSummary['by_category'] as $exp): ?>
                            <tr>
                                <td class="fw-semibold text-navy"><?= htmlspecialchars($exp['category_name']) ?></td>
                                <td class="text-center"><?= (int)$exp['expense_count'] ?></td>
                                <td class="text-end fw-bold text-danger">₹<?= number_format((float)$exp['total_amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Budget vs Actual Variance Report -->
<div class="ks-card p-4">
    <h3 class="fw-bold text-navy mb-3" style="font-size: 16px;">Budget vs. Actual Variance Analysis</h3>
    <div class="table-responsive">
        <table class="ks-table">
            <thead>
                <tr>
                    <th>Budget Plan</th>
                    <th>Fiscal Period</th>
                    <th class="text-end">Allocated</th>
                    <th class="text-end">Actual Spent</th>
                    <th class="text-end">Variance</th>
                    <th class="text-center">Utilization</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($budgetVsActual['budgets'])): ?>
                    <tr><td colspan="7" class="text-muted text-center py-3">No active budget allocations found.</td></tr>
                <?php else: ?>
                    <?php foreach ($budgetVsActual['budgets'] as $b): ?>
                    <tr>
                        <td class="fw-semibold text-navy"><?= htmlspecialchars($b['budget_name']) ?></td>
                        <td><small><?= htmlspecialchars($b['start_date']) ?> to <?= htmlspecialchars($b['end_date']) ?></small></td>
                        <td class="text-end">₹<?= number_format((float)$b['allocated_amount'], 2) ?></td>
                        <td class="text-end fw-bold">₹<?= number_format((float)$b['actual_spent'], 2) ?></td>
                        <td class="text-end <?= (float)$b['variance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            ₹<?= number_format((float)$b['variance'], 2) ?>
                        </td>
                        <td class="text-center">
                            <span class="ks-badge <?= (float)$b['utilization_rate'] >= 100 ? 'ks-badge-red' : ((float)$b['utilization_rate'] >= 90 ? 'ks-badge-amber' : 'ks-badge-green') ?>">
                                <?= number_format((float)$b['utilization_rate'], 1) ?>%
                            </span>
                        </td>
                        <td>
                            <span class="ks-badge ks-badge-blue"><?= htmlspecialchars($b['status']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
