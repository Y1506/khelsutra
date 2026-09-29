<?php
$pageTitle = 'Budget Details — KhelSutra';
$activePage = 'finance';
$orgId = current_organization_id();

$id = (int)($data['id'] ?? 0);
$financeService = new \App\Services\Finance\FinanceService();
$budget = $financeService->getBudget($orgId, $id);
$categories = $financeService->listCategories($orgId, 'expense', 'active');

$db = \App\Services\BaseService::getDatabaseConnection();
$departments = [];
if ($db) {
    $dStmt = $db->prepare("SELECT id, name FROM departments WHERE organization_id = :org_id ORDER BY name ASC");
    $dStmt->execute([':org_id' => $orgId]);
    $departments = $dStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

    <?php if (!$budget): ?>
        <div class="card p-5 text-center shadow-sm border-0" style="border-radius: var(--ks-radius-card); background: #fff;">
            <i class="bi bi-exclamation-circle text-danger fs-1 mb-3"></i>
            <h4 class="fw-bold mb-2">Budget Not Found</h4>
            <p class="text-muted small mb-4">The requested budget record does not exist or you do not have permission to view it.</p>
            <div>
                <a href="/finance?tab=budgets" class="btn btn-outline-secondary" style="font-size: 13px;">Back to Budgets</a>
            </div>
        </div>
    <?php else: ?>
        <!-- Breadcrumb & Header -->
        <div class="d-flex align-items-center gap-2 mb-2">
            <a href="/finance?tab=budgets" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Budgets</a>
            <span class="text-muted small">/</span>
            <span class="text-dark small fw-semibold"><?= htmlspecialchars($budget['budget_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">
                    <?= htmlspecialchars($budget['budget_name'], ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <?php
                $bBadge = match($budget['status']) {
                    'active' => 'bg-success-subtle text-success border border-success-subtle',
                    'draft' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                    'closed' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                    'cancelled' => 'bg-secondary text-white',
                    default => 'bg-light text-dark'
                };
                ?>
                <span class="badge <?= $bBadge ?> px-3 py-2" style="font-size: 12px;">
                    <?= htmlspecialchars(ucfirst($budget['status']), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addBudgetItemModal" style="font-size: 13px;">
                    <i class="bi bi-plus-lg me-1"></i> Add Line Item
                </button>

                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" style="font-size: 13px;">
                        Status: <?= ucfirst($budget['status']) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 13px;">
                        <li>
                            <form method="POST" action="/finance/budgets/<?= (int)$budget['id'] ?>/status">
                                <input type="hidden" name="status" value="active">
                                <button type="submit" class="dropdown-item text-success"><i class="bi bi-play-circle me-2"></i>Set Active</button>
                            </form>
                        </li>
                        <li>
                            <form method="POST" action="/finance/budgets/<?= (int)$budget['id'] ?>/status">
                                <input type="hidden" name="status" value="closed">
                                <button type="submit" class="dropdown-item text-info-emphasis"><i class="bi bi-check2-circle me-2"></i>Close Budget</button>
                            </form>
                        </li>
                        <li>
                            <form method="POST" action="/finance/budgets/<?= (int)$budget['id'] ?>/status" onsubmit="return confirm('Cancel this budget?');">
                                <input type="hidden" name="status" value="cancelled">
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-x-circle me-2"></i>Cancel Budget</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- KPI Breakdown Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6">
                <div class="card p-3 shadow-sm border-0 h-100" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.05em; font-size: 11px;">Total Authorized Ceiling</div>
                    <div class="h4 fw-bold font-monospace text-dark mt-2 mb-0">₹<?= number_format((float)$budget['total_budget'], 2) ?></div>
                    <div class="text-muted small mt-2">FY: <?= htmlspecialchars($budget['financial_year'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="card p-3 shadow-sm border-0 h-100" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.05em; font-size: 11px;">Allocated Line Items</div>
                    <div class="h4 fw-bold font-monospace text-primary mt-2 mb-0">₹<?= number_format((float)$budget['total_allocated_items'], 2) ?></div>
                    <div class="text-muted small mt-2"><?= count($budget['items']) ?> category allocations</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="card p-3 shadow-sm border-0 h-100" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.05em; font-size: 11px;">Actual Expenditure</div>
                    <div class="h4 fw-bold font-monospace text-danger mt-2 mb-0">₹<?= number_format((float)$budget['total_spent'], 2) ?></div>
                    <div class="text-muted small mt-2"><?= $budget['percentage_spent'] ?>% utilized</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="card p-3 shadow-sm border-0 h-100" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.05em; font-size: 11px;">Remaining Available</div>
                    <div class="h4 fw-bold font-monospace text-success mt-2 mb-0">₹<?= number_format((float)$budget['remaining_budget'], 2) ?></div>
                    <div class="text-muted small mt-2">Until <?= date('d M Y', strtotime($budget['end_date'])) ?></div>
                </div>
            </div>
        </div>

        <!-- Overall Utilization Progress -->
        <div class="card p-4 shadow-sm border-0 mb-4" style="border-radius: var(--ks-radius-card); background: #fff;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-semibold text-dark" style="font-size: 14px;">Overall Budget Utilization</span>
                <span class="fw-bold font-monospace <?= (float)$budget['total_spent'] > (float)$budget['total_budget'] ? 'text-danger' : 'text-primary' ?>"><?= $budget['percentage_spent'] ?>% (₹<?= number_format((float)$budget['total_spent'], 2) ?> / ₹<?= number_format((float)$budget['total_budget'], 2) ?>)</span>
            </div>
            <div class="progress" style="height: 10px;">
                <div class="progress-bar <?= (float)$budget['percentage_spent'] > 90 ? 'bg-danger' : 'bg-primary' ?>" style="width: <?= min(100, $budget['percentage_spent']) ?>%;"></div>
            </div>
        </div>

        <!-- Line Items Table -->
        <div class="card shadow-sm border-0" style="border-radius: var(--ks-radius-card); background: #fff;">
            <div class="card-header bg-white border-bottom p-3">
                <h5 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 15px;">Category & Department Allocation Breakdown</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th class="py-2 px-3 text-muted fw-semibold">Category</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Department</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-end">Allocated</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-end">Actual Spent</th>
                                <th class="py-2 px-3 text-muted fw-semibold text-end">Remaining</th>
                                <th class="py-2 px-3 text-muted fw-semibold" style="width: 140px;">Spent %</th>
                                <th class="py-2 px-3 text-muted fw-semibold">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($budget['items'])): ?>
                                <?php foreach ($budget['items'] as $it): ?>
                                    <tr>
                                        <td class="py-3 px-3 fw-bold text-dark">
                                            <?= htmlspecialchars($it['category_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="py-3 px-3 text-muted">
                                            <?= htmlspecialchars($it['department_name'] ?? 'General Organization', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="py-3 px-3 text-end font-monospace fw-bold text-dark">
                                            ₹<?= number_format((float)$it['allocated_amount'], 2) ?>
                                        </td>
                                        <td class="py-3 px-3 text-end font-monospace fw-bold <?= (float)$it['actual_spent'] > (float)$it['allocated_amount'] ? 'text-danger' : 'text-primary' ?>">
                                            ₹<?= number_format((float)$it['actual_spent'], 2) ?>
                                        </td>
                                        <td class="py-3 px-3 text-end font-monospace text-success fw-semibold">
                                            ₹<?= number_format((float)$it['remaining_amount'], 2) ?>
                                        </td>
                                        <td class="py-3 px-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar <?= (float)$it['percentage_spent'] > 90 ? 'bg-danger' : 'bg-primary' ?>" style="width: <?= min(100, $it['percentage_spent']) ?>%;"></div>
                                                </div>
                                                <span class="small font-monospace" style="font-size: 11px;"><?= $it['percentage_spent'] ?>%</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-muted small">
                                            <?= !empty($it['description']) ? htmlspecialchars($it['description'], ENT_QUOTES, 'UTF-8') : '—' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        No category line items added to this budget yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal: Add Budget Item -->
        <div class="modal fade" id="addBudgetItemModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: var(--ks-radius-card);">
                    <form method="POST" action="/finance/budgets/<?= (int)$budget['id'] ?>/items/add">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" style="font-size: 16px;">Add Budget Allocation</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" style="font-size: 13px;">
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Finance Category <span class="text-danger">*</span></label>
                                <select name="finance_category_id" class="form-select" required style="font-size: 13px;">
                                    <option value="">-- Choose Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Department</label>
                                <select name="department_id" class="form-select" style="font-size: 13px;">
                                    <option value="">General Organization</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Allocated Amount (₹) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" name="allocated_amount" class="form-control" required style="font-size: 13px;">
                            </div>
                            <div class="mb-0">
                                <label class="form-label fw-semibold text-dark">Notes / Purpose</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Line item remarks..." style="font-size: 13px;"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue);">Add Allocation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.blade.php';
