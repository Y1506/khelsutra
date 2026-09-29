<?php
$pageTitle = 'Create Budget — KhelSutra';
$activePage = 'finance';
$orgId = current_organization_id();

$financeService = new \App\Services\Finance\FinanceService();
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
    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert" style="border-radius: var(--ks-radius-button); font-size: 13px;">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Breadcrumb & Header -->
    <div class="d-flex align-items-center gap-2 mb-2">
        <a href="/finance?tab=budgets" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Budgets</a>
        <span class="text-muted small">/</span>
        <span class="text-dark small fw-semibold">New Budget</span>
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Create Organization Budget</h1>
            <p class="text-muted small mb-0">Set up fiscal year expenditure ceilings and departmental category allocations.</p>
        </div>
    </div>

    <form method="POST" action="/finance/budgets/create" id="budgetForm">
        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Header Info -->
                <div class="card p-4 shadow-sm border-0 mb-4" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px;">Budget Definition</h5>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Budget Name / Title <span class="text-danger">*</span></label>
                        <input type="text" name="budget_name" class="form-control" placeholder="e.g. FY 2026-27 General Operational Budget" required style="font-size: 13px;">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Financial Year <span class="text-danger">*</span></label>
                            <input type="text" name="financial_year" class="form-control font-monospace" placeholder="e.g. 2026-2027" value="<?= date('Y') ?>-<?= date('Y') + 1 ?>" required style="font-size: 13px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Total Budget Limit (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="total_budget" id="totalBudgetInput" class="form-control fw-bold font-monospace" placeholder="0.00" required style="font-size: 13px;" oninput="updateLineTotal()">
                        </div>
                    </div>

                    <div class="row g-3 mb-0">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-04-01') ?>" required style="font-size: 13px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control" value="<?= (date('Y') + 1) . '-03-31' ?>" required style="font-size: 13px;">
                        </div>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div class="card p-4 shadow-sm border-0" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h5 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 15px;">Category & Department Allocations</h5>
                            <small class="text-muted">Allocate funds to specific categories and departments.</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addBudgetRow()">
                            <i class="bi bi-plus-lg me-1"></i> Add Line Item
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="itemsTable" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30%;">Category <span class="text-danger">*</span></th>
                                    <th style="width: 25%;">Department</th>
                                    <th style="width: 25%;">Allocated (₹) <span class="text-danger">*</span></th>
                                    <th style="width: 15%;">Notes</th>
                                    <th style="width: 5%;"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <tr>
                                    <td>
                                        <select name="categories[]" class="form-select form-select-sm" required>
                                            <option value="">-- Choose Category --</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="department_ids[]" class="form-select form-select-sm">
                                            <option value="">General</option>
                                            <?php foreach ($departments as $d): ?>
                                                <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0.01" name="allocated_amounts[]" class="form-control form-control-sm item-amount font-monospace" placeholder="0.00" required oninput="updateLineTotal()">
                                    </td>
                                    <td>
                                        <input type="text" name="descriptions[]" class="form-control form-control-sm" placeholder="Notes...">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="2" class="fw-bold text-end">Total Allocated Across Items:</td>
                                    <td class="fw-bold font-monospace text-primary" id="allocatedSumDisplay">₹0.00</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card p-4 shadow-sm border-0 mb-4" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px;">Budget Status</h5>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Status</label>
                        <select name="status" class="form-select" style="font-size: 13px;">
                            <option value="active" selected>Active & Tracking</option>
                            <option value="draft">Draft (Planning)</option>
                        </select>
                    </div>

                    <div class="alert alert-info py-2 px-3 small mb-0" style="font-size: 12px;">
                        <i class="bi bi-info-circle me-1"></i>
                        Expenses recorded under the allocated categories between the budget dates will automatically count towards this budget's utilization.
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary py-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600;">
                        <i class="bi bi-check-lg me-1"></i> Save & Activate Budget
                    </button>
                    <a href="/finance?tab=budgets" class="btn btn-outline-secondary py-2" style="border-radius: var(--ks-radius-button);">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const categoryOptions = `
    <option value="">-- Choose Category --</option>
    <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
    <?php endforeach; ?>
`;

const departmentOptions = `
    <option value="">General</option>
    <?php foreach ($departments as $d): ?>
        <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?></option>
    <?php endforeach; ?>
`;

function addBudgetRow() {
    const tbody = document.getElementById('itemsBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select name="categories[]" class="form-select form-select-sm" required>${categoryOptions}</select></td>
        <td><select name="department_ids[]" class="form-select form-select-sm">${departmentOptions}</select></td>
        <td><input type="number" step="0.01" min="0.01" name="allocated_amounts[]" class="form-control form-control-sm item-amount font-monospace" placeholder="0.00" required oninput="updateLineTotal()"></td>
        <td><input type="text" name="descriptions[]" class="form-control form-control-sm" placeholder="Notes..."></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
    `;
    tbody.appendChild(tr);
}

function removeRow(btn) {
    const tbody = document.getElementById('itemsBody');
    if (tbody.children.length > 1) {
        btn.closest('tr').remove();
        updateLineTotal();
    }
}

function updateLineTotal() {
    let sum = 0;
    document.querySelectorAll('.item-amount').forEach(el => {
        sum += parseFloat(el.value) || 0;
    });
    document.getElementById('allocatedSumDisplay').innerText = '₹' + sum.toFixed(2);
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.blade.php';
