<?php
$pageTitle = 'Financial Management — KhelSutra';
$activePage = 'finance';
$orgId = current_organization_id();

$financeService = new \App\Services\Finance\FinanceService();
$vendorService = new \App\Services\Vendor\VendorService();
$activeTab = trim($_GET['tab'] ?? 'expenses');

$summary = $financeService->getFinancialSummary($orgId);

$page = (int)($_GET['page'] ?? 1);
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$catFilter = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;

// Categories for pickers
$categories = $financeService->listCategories($orgId);

// Data loading based on tab
$expenses = [];
$incomeList = [];
$budgets = [];
$payments = [];
$totalPages = 1;
$total = 0;

if ($activeTab === 'income') {
    $res = $financeService->listIncome($orgId, $page, 15, [
        'search' => $search ?: null,
        'status' => $statusFilter ?: null,
        'finance_category_id' => $catFilter ?: null,
    ]);
    $incomeList = $res['data'];
    $total = $res['total'];
    $totalPages = $res['total_pages'];
} elseif ($activeTab === 'budgets') {
    $res = $financeService->listBudgets($orgId, $page, 15, $statusFilter ?: null);
    $budgets = $res['data'];
    $total = $res['total'];
    $totalPages = $res['total_pages'];
} elseif ($activeTab === 'categories') {
    $activeTab = 'categories';
} elseif ($activeTab === 'payments') {
    $res = $financeService->listPayments($orgId, $page, 15, [
        'search' => $search ?: null,
    ]);
    $payments = $res['data'];
    $total = $res['total'];
    $totalPages = $res['total_pages'];
} else {
    $activeTab = 'expenses';
    $res = $financeService->listExpenses($orgId, $page, 15, [
        'search' => $search ?: null,
        'payment_status' => $statusFilter ?: null,
        'finance_category_id' => $catFilter ?: null,
    ]);
    $expenses = $res['data'];
    $total = $res['total'];
    $totalPages = $res['total_pages'];
}

// Fetch pending expenses for quick payment modal
$db = \App\Services\BaseService::getDatabaseConnection();
$approvedExpenses = [];
$unpaidInvoices = [];
if ($db) {
    $aeStmt = $db->prepare("SELECT id, expense_reference, description, total_amount FROM expenses WHERE organization_id = :org_id AND payment_status IN ('approved', 'pending') AND deleted_at IS NULL ORDER BY id DESC LIMIT 50");
    $aeStmt->execute([':org_id' => $orgId]);
    $approvedExpenses = $aeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $uiStmt = $db->prepare("SELECT vi.id, vi.invoice_number, vi.total_amount, v.company_name FROM vendor_invoices vi JOIN vendors v ON vi.vendor_id = v.id WHERE vi.organization_id = :org_id AND vi.payment_status IN ('unpaid', 'partially_paid') ORDER BY vi.id DESC LIMIT 50");
    $uiStmt->execute([':org_id' => $orgId]);
    $unpaidInvoices = $uiStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

ob_start();
?>

<div class="ks-content">
    <?php if (!empty($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert" style="border-radius: var(--ks-radius-button); font-size: 13px;">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert" style="border-radius: var(--ks-radius-button); font-size: 13px;">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Financial Management</h1>
            <p class="text-muted small mb-0">Unified accounting hub for organization expenses, revenue transactions, department budgets, and disbursements.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addCategoryModal" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                <i class="bi bi-tags me-1"></i> New Category
            </button>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#recordPaymentModal" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                <i class="bi bi-cash-stack me-1"></i> Record Payment
            </button>
            <a href="/finance/income/create" class="btn btn-outline-success" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                <i class="bi bi-arrow-down-left-circle me-1"></i> Record Income
            </a>
            <a href="/finance/expenses/create" class="btn btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-size: 13px;">
                <i class="bi bi-plus-lg me-1"></i> Record Expense
            </a>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card p-3 shadow-sm border-0 h-100" style="border-radius: var(--ks-radius-card); background: #fff; border-left: 4px solid #198754 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.05em; font-size: 11px;">Total Revenue (Received)</div>
                        <div class="h4 fw-bold mb-0 text-success font-monospace mt-1">₹<?= number_format($summary['total_income'], 2) ?></div>
                    </div>
                    <div class="rounded-circle bg-success-subtle p-3 text-success">
                        <i class="bi bi-graph-up-arrow fs-4"></i>
                    </div>
                </div>
                <div class="text-muted small mt-2" style="font-size: 11px;">
                    <i class="bi bi-check-circle me-1"></i> Verified income transactions
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card p-3 shadow-sm border-0 h-100" style="border-radius: var(--ks-radius-card); background: #fff; border-left: 4px solid #dc3545 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.05em; font-size: 11px;">Total Expenses (Paid)</div>
                        <div class="h4 fw-bold mb-0 text-danger font-monospace mt-1">₹<?= number_format($summary['total_expenses'], 2) ?></div>
                    </div>
                    <div class="rounded-circle bg-danger-subtle p-3 text-danger">
                        <i class="bi bi-cash-coin fs-4"></i>
                    </div>
                </div>
                <div class="text-muted small mt-2" style="font-size: 11px;">
                    <i class="bi bi-receipt me-1"></i> Settled operational expenditures
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card p-3 shadow-sm border-0 h-100" style="border-radius: var(--ks-radius-card); background: #fff; border-left: 4px solid var(--ks-blue) !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.05em; font-size: 11px;">Net Cashflow</div>
                        <div class="h4 fw-bold mb-0 font-monospace mt-1 <?= $summary['net_cashflow'] >= 0 ? 'text-primary' : 'text-danger' ?>">
                            ₹<?= number_format($summary['net_cashflow'], 2) ?>
                        </div>
                    </div>
                    <div class="rounded-circle bg-primary-subtle p-3 text-primary">
                        <i class="bi bi-wallet2 fs-4"></i>
                    </div>
                </div>
                <div class="text-muted small mt-2" style="font-size: 11px;">
                    <i class="bi bi-arrow-left-right me-1"></i> Revenue minus paid expenses
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card p-3 shadow-sm border-0 h-100" style="border-radius: var(--ks-radius-card); background: #fff; border-left: 4px solid #fd7e14 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.05em; font-size: 11px;">Pending Approvals</div>
                        <div class="h4 fw-bold mb-0 text-warning-emphasis font-monospace mt-1">
                            <?= $summary['pending_expenses_count'] ?> <span class="fs-6 text-muted fw-normal">(₹<?= number_format($summary['pending_expenses_amount'], 2) ?>)</span>
                        </div>
                    </div>
                    <div class="rounded-circle bg-warning-subtle p-3 text-warning-emphasis">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </div>
                </div>
                <div class="text-muted small mt-2" style="font-size: 11px;">
                    <i class="bi bi-clock-history me-1"></i> Awaiting financial clearance
                </div>
            </div>
        </div>
    </div>

    <!-- Main Navigation Tabs -->
    <div class="card shadow-sm border-0" style="border-radius: var(--ks-radius-card); background: #fff;">
        <div class="card-header bg-white border-bottom p-0">
            <ul class="nav nav-tabs border-0 px-3 pt-2" style="gap: 8px;">
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'expenses' ? 'active fw-bold text-primary border-bottom border-primary border-2' : 'text-muted' ?>" href="/finance?tab=expenses" style="font-size: 13px;">
                        <i class="bi bi-receipt me-1"></i> Expenses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'income' ? 'active fw-bold text-primary border-bottom border-primary border-2' : 'text-muted' ?>" href="/finance?tab=income" style="font-size: 13px;">
                        <i class="bi bi-arrow-down-left-circle me-1"></i> Income Transactions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'budgets' ? 'active fw-bold text-primary border-bottom border-primary border-2' : 'text-muted' ?>" href="/finance?tab=budgets" style="font-size: 13px;">
                        <i class="bi bi-pie-chart me-1"></i> Budgets
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'categories' ? 'active fw-bold text-primary border-bottom border-primary border-2' : 'text-muted' ?>" href="/finance?tab=categories" style="font-size: 13px;">
                        <i class="bi bi-tags me-1"></i> Finance Categories
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'payments' ? 'active fw-bold text-primary border-bottom border-primary border-2' : 'text-muted' ?>" href="/finance?tab=payments" style="font-size: 13px;">
                        <i class="bi bi-credit-card me-1"></i> Payment Ledger
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <!-- ========================================== -->
            <!-- TAB 1: EXPENSES -->
            <!-- ========================================== -->
            <?php if ($activeTab === 'expenses'): ?>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <form method="GET" action="/finance" class="d-flex flex-wrap align-items-center gap-2">
                        <input type="hidden" name="tab" value="expenses">
                        <div class="input-group" style="width: 250px;">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Search reference, vendor..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px;">
                        </div>

                        <select name="status" class="form-select" style="width: 160px; font-size: 13px;">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>

                        <select name="category_id" class="form-select" style="width: 180px; font-size: 13px;">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <?php if ($cat['category_type'] !== 'income'): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= $catFilter === (int)$cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
                        <?php if ($search || $statusFilter || $catFilter): ?>
                            <a href="/finance?tab=expenses" class="btn btn-sm btn-link text-muted">Reset</a>
                        <?php endif; ?>
                    </form>

                    <a href="/finance/expenses/create" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue);">
                        <i class="bi bi-plus-lg me-1"></i> New Expense
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th class="py-2 px-3 text-muted fw-semibold">Reference</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Date</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Description</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Category</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Vendor / Department</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-end">Amount</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-center">Status</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($expenses)): ?>
                                <?php foreach ($expenses as $exp): ?>
                                    <tr>
                                        <td class="py-3 px-3">
                                            <a href="/finance/expenses/<?= (int)$exp['id'] ?>" class="fw-bold text-decoration-none text-primary">
                                                <?= htmlspecialchars($exp['expense_reference'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </td>
                                        <td class="py-3 px-3 text-muted">
                                            <?= !empty($exp['expense_date']) ? date('d M Y', strtotime($exp['expense_date'])) : '—' ?>
                                        </td>
                                        <td class="py-3 px-3">
                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($exp['description'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($exp['description'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3">
                                            <span class="badge bg-light text-dark border">
                                                <?= htmlspecialchars($exp['category_name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-muted">
                                            <?php if (!empty($exp['vendor_name'])): ?>
                                                <i class="bi bi-truck me-1"></i><?= htmlspecialchars($exp['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php elseif (!empty($exp['department_name'])): ?>
                                                <i class="bi bi-building me-1"></i><?= htmlspecialchars($exp['department_name'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php else: ?>
                                                <em class="text-muted">—</em>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3 text-end font-monospace fw-bold text-dark">
                                            ₹<?= number_format((float)$exp['total_amount'], 2) ?>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <?php
                                            $sBadge = match($exp['payment_status']) {
                                                'paid' => 'bg-success-subtle text-success border border-success-subtle',
                                                'approved' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                                                'pending' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                                'rejected' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                                'cancelled' => 'bg-secondary text-white',
                                                default => 'bg-light text-dark'
                                            };
                                            ?>
                                            <span class="badge <?= $sBadge ?> px-2 py-1" style="font-size: 11px;">
                                                <?= htmlspecialchars(ucfirst($exp['payment_status']), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="/finance/expenses/<?= (int)$exp['id'] ?>" class="btn btn-outline-secondary" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($exp['payment_status'] === 'pending'): ?>
                                                    <form method="POST" action="/finance/expenses/<?= (int)$exp['id'] ?>/approve" style="display: contents;" class="d-inline" onsubmit="return confirm('Approve this expense?');">
                                                        <button type="submit" class="btn btn-outline-success" title="Approve">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="bi bi-receipt text-muted" style="font-size: 2.2rem;"></i>
                                        <p class="text-muted mt-2 mb-2" style="font-size: 13px;">No expense records found matching current criteria.</p>
                                        <a href="/finance/expenses/create" class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus-lg me-1"></i> Record First Expense
                                        </a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <!-- ========================================== -->
            <!-- TAB 2: INCOME TRANSACTIONS -->
            <!-- ========================================== -->
            <?php elseif ($activeTab === 'income'): ?>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <form method="GET" action="/finance" class="d-flex flex-wrap align-items-center gap-2">
                        <input type="hidden" name="tab" value="income">
                        <div class="input-group" style="width: 250px;">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Search reference, source..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px;">
                        </div>

                        <select name="status" class="form-select" style="width: 160px; font-size: 13px;">
                            <option value="">All Statuses</option>
                            <option value="received" <?= $statusFilter === 'received' ? 'selected' : '' ?>>Received</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>

                        <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
                        <?php if ($search || $statusFilter): ?>
                            <a href="/finance?tab=income" class="btn btn-sm btn-link text-muted">Reset</a>
                        <?php endif; ?>
                    </form>

                    <a href="/finance/income/create" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-lg me-1"></i> Record Income
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th class="py-2 px-3 text-muted fw-semibold">Reference</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Date</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Source / Donor / Payer</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Category</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Payment Method</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-end">Amount</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-center">Status</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($incomeList)): ?>
                                <?php foreach ($incomeList as $inc): ?>
                                    <tr>
                                        <td class="py-3 px-3">
                                            <a href="/finance/income/<?= (int)$inc['id'] ?>" class="fw-bold text-decoration-none text-success">
                                                <?= htmlspecialchars($inc['income_reference'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </td>
                                        <td class="py-3 px-3 text-muted">
                                            <?= !empty($inc['income_date']) ? date('d M Y', strtotime($inc['income_date'])) : '—' ?>
                                        </td>
                                        <td class="py-3 px-3 fw-semibold text-dark">
                                            <?= htmlspecialchars($inc['source_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="py-3 px-3">
                                            <span class="badge bg-light text-dark border">
                                                <?= htmlspecialchars($inc['category_name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-muted">
                                            <?= htmlspecialchars($inc['payment_method'] ?? 'Bank Transfer', ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($inc['payment_reference'])): ?>
                                                <small class="d-block text-muted font-monospace"><?= htmlspecialchars($inc['payment_reference'], ENT_QUOTES, 'UTF-8') ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3 text-end font-monospace fw-bold text-success">
                                            ₹<?= number_format((float)$inc['amount'], 2) ?>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <?php
                                            $iBadge = match($inc['status']) {
                                                'received' => 'bg-success-subtle text-success border border-success-subtle',
                                                'pending' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                                'cancelled' => 'bg-secondary text-white',
                                                default => 'bg-light text-dark'
                                            };
                                            ?>
                                            <span class="badge <?= $iBadge ?> px-2 py-1" style="font-size: 11px;">
                                                <?= htmlspecialchars(ucfirst($inc['status']), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-end">
                                            <a href="/finance/income/<?= (int)$inc['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="bi bi-arrow-down-left-circle text-muted" style="font-size: 2.2rem;"></i>
                                        <p class="text-muted mt-2 mb-2" style="font-size: 13px;">No income transactions recorded yet.</p>
                                        <a href="/finance/income/create" class="btn btn-sm btn-success">
                                            <i class="bi bi-plus-lg me-1"></i> Record First Income
                                        </a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <!-- ========================================== -->
            <!-- TAB 3: BUDGETS -->
            <!-- ========================================== -->
            <?php elseif ($activeTab === 'budgets'): ?>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 14px;">Organization Budgets & Fiscal Tracking</h6>
                        <small class="text-muted">Track allocated departmental allowances vs actual expenditures in real time.</small>
                    </div>

                    <a href="/finance/budgets/create" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue);">
                        <i class="bi bi-plus-lg me-1"></i> Create Budget
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th class="py-2 px-3 text-muted fw-semibold">Budget Name</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Fiscal Year</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Period</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-end">Total Limit</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-end">Allocated Items</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-end">Actual Spent</th>
                                <th class="py-2 px-3 text-muted fw-semibold" style="width: 140px;">Utilization</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-center">Status</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($budgets)): ?>
                                <?php foreach ($budgets as $b): ?>
                                    <tr>
                                        <td class="py-3 px-3">
                                            <a href="/finance/budgets/<?= (int)$b['id'] ?>" class="fw-bold text-decoration-none text-primary">
                                                <?= htmlspecialchars($b['budget_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </td>
                                        <td class="py-3 px-3 font-monospace">
                                            <?= htmlspecialchars($b['financial_year'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="py-3 px-3 text-muted" style="font-size: 12px;">
                                            <?= date('M Y', strtotime($b['start_date'])) ?> – <?= date('M Y', strtotime($b['end_date'])) ?>
                                        </td>
                                        <td class="py-3 px-3 text-end font-monospace fw-bold text-dark">
                                            ₹<?= number_format((float)$b['total_budget'], 2) ?>
                                        </td>
                                        <td class="py-3 px-3 text-end font-monospace text-muted">
                                            ₹<?= number_format((float)$b['total_allocated_items'], 2) ?>
                                        </td>
                                        <td class="py-3 px-3 text-end font-monospace fw-bold <?= (float)$b['total_spent'] > (float)$b['total_budget'] ? 'text-danger' : 'text-primary' ?>">
                                            ₹<?= number_format((float)$b['total_spent'], 2) ?>
                                        </td>
                                        <td class="py-3 px-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar <?= (float)$b['percentage_spent'] > 90 ? 'bg-danger' : 'bg-primary' ?>" style="width: <?= min(100, $b['percentage_spent']) ?>%;"></div>
                                                </div>
                                                <span class="small font-monospace" style="font-size: 11px;"><?= $b['percentage_spent'] ?>%</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <?php
                                            $bBadge = match($b['status']) {
                                                'active' => 'bg-success-subtle text-success border border-success-subtle',
                                                'draft' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                                'closed' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                                                'cancelled' => 'bg-secondary text-white',
                                                default => 'bg-light text-dark'
                                            };
                                            ?>
                                            <span class="badge <?= $bBadge ?> px-2 py-1" style="font-size: 11px;">
                                                <?= htmlspecialchars(ucfirst($b['status']), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-end">
                                            <a href="/finance/budgets/<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View Budget Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="bi bi-pie-chart text-muted" style="font-size: 2.2rem;"></i>
                                        <p class="text-muted mt-2 mb-2" style="font-size: 13px;">No budgets configured for this organization.</p>
                                        <a href="/finance/budgets/create" class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus-lg me-1"></i> Setup First Budget
                                        </a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <!-- ========================================== -->
            <!-- TAB 4: FINANCE CATEGORIES -->
            <!-- ========================================== -->
            <?php elseif ($activeTab === 'categories'): ?>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 14px;">Finance & Cost Categories</h6>
                        <small class="text-muted">Standard chart of accounts for revenues, operational expenditures, and departmental budgets.</small>
                    </div>

                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal" style="background: var(--ks-blue); border-color: var(--ks-blue);">
                        <i class="bi bi-plus-lg me-1"></i> New Category
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th class="py-2 px-3 text-muted fw-semibold">Category Name</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Category Type</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Description</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-center">Status</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td class="py-3 px-3 fw-bold text-dark">
                                            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="py-3 px-3">
                                            <?php
                                            $tBadge = match($cat['category_type']) {
                                                'income' => 'bg-success-subtle text-success border border-success-subtle',
                                                'expense' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                                'both' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                                default => 'bg-light text-dark'
                                            };
                                            ?>
                                            <span class="badge <?= $tBadge ?> px-2 py-1" style="font-size: 11px;">
                                                <?= htmlspecialchars(ucfirst($cat['category_type']), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-muted">
                                            <?= !empty($cat['description']) ? htmlspecialchars($cat['description'], ENT_QUOTES, 'UTF-8') : '<em class="text-muted">No description</em>' ?>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <span class="badge <?= $cat['status'] === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' ?> px-2 py-1" style="font-size: 11px;">
                                                <?= htmlspecialchars(ucfirst($cat['status']), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editCategoryModal<?= (int)$cat['id'] ?>" title="Edit Category">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="/finance/categories/<?= (int)$cat['id'] ?>/status" style="display: contents;" class="d-inline">
                                                <input type="hidden" name="status" value="<?= $cat['status'] === 'active' ? 'inactive' : 'active' ?>">
                                                <button type="submit" class="btn btn-sm <?= $cat['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="<?= $cat['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                                                    <i class="bi <?= $cat['status'] === 'active' ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- Edit Category Modal -->
                                    <div class="modal fade" id="editCategoryModal<?= (int)$cat['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content" style="border-radius: var(--ks-radius-card);">
                                                <form method="POST" action="/finance/categories/<?= (int)$cat['id'] ?>/edit">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold" style="font-size: 16px;">Edit Category: <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body" style="font-size: 13px;">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold text-dark">Category Name <span class="text-danger">*</span></label>
                                                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="100" style="font-size: 13px;">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold text-dark">Category Type <span class="text-danger">*</span></label>
                                                            <select name="category_type" class="form-select" style="font-size: 13px;">
                                                                <option value="both" <?= $cat['category_type'] === 'both' ? 'selected' : '' ?>>Both (Income & Expense)</option>
                                                                <option value="expense" <?= $cat['category_type'] === 'expense' ? 'selected' : '' ?>>Expense Only</option>
                                                                <option value="income" <?= $cat['category_type'] === 'income' ? 'selected' : '' ?>>Income Only</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold text-dark">Status</label>
                                                            <select name="status" class="form-select" style="font-size: 13px;">
                                                                <option value="active" <?= $cat['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                <option value="inactive" <?= $cat['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-0">
                                                            <label class="form-label fw-semibold text-dark">Description</label>
                                                            <textarea name="description" class="form-control" rows="3" style="font-size: 13px;"><?= htmlspecialchars($cat['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue);">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <i class="bi bi-tags text-muted" style="font-size: 2.2rem;"></i>
                                        <p class="text-muted mt-2 mb-2" style="font-size: 13px;">No finance categories defined yet.</p>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                            <i class="bi bi-plus-lg me-1"></i> Add First Category
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <!-- ========================================== -->
            <!-- TAB 5: PAYMENT LEDGER -->
            <!-- ========================================== -->
            <?php elseif ($activeTab === 'payments'): ?>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <form method="GET" action="/finance" class="d-flex flex-wrap align-items-center gap-2">
                        <input type="hidden" name="tab" value="payments">
                        <div class="input-group" style="width: 250px;">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Search reference..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px;">
                        </div>

                        <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
                    </form>

                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#recordPaymentModal" style="background: var(--ks-blue); border-color: var(--ks-blue);">
                        <i class="bi bi-plus-lg me-1"></i> Record Disbursement
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th class="py-2 px-3 text-muted fw-semibold">Payment Reference</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Date</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Type</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Linked Record</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Method & Txn Ref</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-end">Amount</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($payments)): ?>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td class="py-3 px-3 font-monospace fw-bold text-dark">
                                            <?= htmlspecialchars($p['payment_reference'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="py-3 px-3 text-muted">
                                            <?= !empty($p['payment_date']) ? date('d M Y', strtotime($p['payment_date'])) : '—' ?>
                                        </td>
                                        <td class="py-3 px-3">
                                            <span class="badge bg-light text-dark border">
                                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $p['payment_type'])), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-3">
                                            <?php if ($p['payment_type'] === 'expense' && !empty($p['expense_reference'])): ?>
                                                <a href="/finance/expenses/<?= (int)$p['expense_id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($p['expense_reference'], ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                            <?php elseif ($p['payment_type'] === 'vendor_invoice' && !empty($p['invoice_number'])): ?>
                                                <span class="text-dark fw-semibold">Inv #<?= htmlspecialchars($p['invoice_number'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <small class="text-muted d-block"><?= htmlspecialchars($p['vendor_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                                            <?php else: ?>
                                                <em class="text-muted">—</em>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3 text-muted">
                                            <?= htmlspecialchars($p['payment_method'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($p['transaction_reference'])): ?>
                                                <small class="d-block text-muted font-monospace"><?= htmlspecialchars($p['transaction_reference'], ENT_QUOTES, 'UTF-8') ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3 text-end font-monospace fw-bold text-dark">
                                            ₹<?= number_format((float)$p['amount'], 2) ?>
                                        </td>
                                        <td class="py-3 px-3 text-muted">
                                            <?= htmlspecialchars($p['creator_name'] ?? 'Staff', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="bi bi-credit-card text-muted" style="font-size: 2.2rem;"></i>
                                        <p class="text-muted mt-2 mb-2" style="font-size: 13px;">No payments recorded in the financial ledger.</p>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                                            <i class="bi bi-plus-lg me-1"></i> Record First Payment
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: ADD FINANCE CATEGORY -->
<!-- ========================================== -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: var(--ks-radius-card);">
            <form method="POST" action="/finance/categories/create">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" style="font-size: 16px;">Create Finance Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="font-size: 13px;">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Venue Maintenance, Sports Equipment" required maxlength="100" style="font-size: 13px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Category Type <span class="text-danger">*</span></label>
                        <select name="category_type" class="form-select" style="font-size: 13px;">
                            <option value="both">Both (Income & Expense)</option>
                            <option value="expense" selected>Expense Only</option>
                            <option value="income">Income Only</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Status</label>
                        <select name="status" class="form-select" style="font-size: 13px;">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold text-dark">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Accounting description or ledger code notes..." style="font-size: 13px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue);">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: RECORD PAYMENT -->
<!-- ========================================== -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: var(--ks-radius-card);">
            <form method="POST" action="/finance/payments/create">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" style="font-size: 16px;">Record Disbursement / Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="font-size: 13px;">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Payment Type <span class="text-danger">*</span></label>
                        <select name="payment_type" id="paymentTypeSelect" class="form-select" required style="font-size: 13px;" onchange="togglePaymentTarget(this.value)">
                            <option value="expense">Expense Disbursement</option>
                            <option value="vendor_invoice">Vendor Invoice Settlement</option>
                            <option value="other">Other Direct Payment</option>
                        </select>
                    </div>

                    <div class="mb-3" id="expenseTargetGroup">
                        <label class="form-label fw-semibold text-dark">Select Expense <span class="text-danger">*</span></label>
                        <select name="expense_id" class="form-select" style="font-size: 13px;">
                            <option value="">-- Choose Pending / Approved Expense --</option>
                            <?php foreach ($approvedExpenses as $ae): ?>
                                <option value="<?= (int)$ae['id'] ?>">
                                    <?= htmlspecialchars($ae['expense_reference'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($ae['description'], ENT_QUOTES, 'UTF-8') ?> (₹<?= number_format((float)$ae['total_amount'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="invoiceTargetGroup">
                        <label class="form-label fw-semibold text-dark">Select Vendor Invoice <span class="text-danger">*</span></label>
                        <select name="vendor_invoice_id" class="form-select" style="font-size: 13px;">
                            <option value="">-- Choose Unpaid Vendor Invoice --</option>
                            <?php foreach ($unpaidInvoices as $ui): ?>
                                <option value="<?= (int)$ui['id'] ?>">
                                    Inv #<?= htmlspecialchars($ui['invoice_number'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($ui['company_name'], ENT_QUOTES, 'UTF-8') ?> — ₹<?= number_format((float)$ui['total_amount'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark">Amount (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required style="font-size: 13px;">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required style="font-size: 13px;">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" style="font-size: 13px;">
                                <option value="Bank Transfer" selected>Bank Transfer (NEFT/RTGS)</option>
                                <option value="UPI">UPI / Net Banking</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Cash">Cash</option>
                                <option value="Credit Card">Credit Card</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark">Txn / UTR / Cheque Ref</label>
                            <input type="text" name="transaction_reference" class="form-control" placeholder="e.g. UTR12345678" style="font-size: 13px;">
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold text-dark">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Payment remarks..." style="font-size: 13px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue);">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePaymentTarget(type) {
    const expGroup = document.getElementById('expenseTargetGroup');
    const invGroup = document.getElementById('invoiceTargetGroup');
    if (type === 'expense') {
        expGroup.classList.remove('d-none');
        invGroup.classList.add('d-none');
    } else if (type === 'vendor_invoice') {
        expGroup.classList.add('d-none');
        invGroup.classList.remove('d-none');
    } else {
        expGroup.classList.add('d-none');
        invGroup.classList.add('d-none');
    }
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.blade.php';
