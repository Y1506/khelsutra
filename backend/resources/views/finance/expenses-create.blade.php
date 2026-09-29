<?php
$pageTitle = 'Record Expense — KhelSutra';
$activePage = 'finance';
$orgId = current_organization_id();

$financeService = new \App\Services\Finance\FinanceService();
$categories = $financeService->listCategories($orgId, 'expense', 'active');

$db = \App\Services\BaseService::getDatabaseConnection();
$vendors = [];
$departments = [];
$events = [];

if ($db) {
    $vStmt = $db->prepare("SELECT id, company_name, vendor_code FROM vendors WHERE organization_id = :org_id AND status = 'active' AND deleted_at IS NULL ORDER BY company_name ASC");
    $vStmt->execute([':org_id' => $orgId]);
    $vendors = $vStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $dStmt = $db->prepare("SELECT id, name FROM departments WHERE organization_id = :org_id ORDER BY name ASC");
    $dStmt->execute([':org_id' => $orgId]);
    $departments = $dStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $eStmt = $db->prepare("SELECT id, event_name FROM events WHERE organization_id = :org_id ORDER BY id DESC LIMIT 50");
    $eStmt->execute([':org_id' => $orgId]);
    $events = $eStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
        <a href="/finance" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Finance Hub</a>
        <span class="text-muted small">/</span>
        <span class="text-dark small fw-semibold">Record Expense</span>
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Record New Expense</h1>
            <p class="text-muted small mb-0">Submit an organizational expense requisition or direct payment record.</p>
        </div>
    </div>

    <form method="POST" action="/finance/expenses/create">
        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Core Details Card -->
                <div class="card p-4 shadow-sm border-0 mb-4" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px;">Expense Information</h5>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Description / Purpose <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Venue turf repair, transport for state cup, sports kits" required style="font-size: 13px;">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Subtotal Amount (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="amount" id="subtotalInput" class="form-control" placeholder="0.00" required style="font-size: 13px;" oninput="calcTotal()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Tax / GST (₹)</label>
                            <input type="number" step="0.01" min="0" name="tax_amount" id="taxInput" class="form-control" value="0.00" style="font-size: 13px;" oninput="calcTotal()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Total Amount (₹)</label>
                            <input type="text" id="totalDisplay" class="form-control bg-light fw-bold font-monospace text-dark" value="₹0.00" readonly style="font-size: 13px;">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Expense Date <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required style="font-size: 13px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Finance Category <span class="text-danger">*</span></label>
                            <select name="finance_category_id" class="form-select" required style="font-size: 13px;">
                                <option value="">-- Choose Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>">
                                        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Internal Remarks / Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Additional details or justification..." style="font-size: 13px;"></textarea>
                    </div>
                </div>

                <!-- Attribution Card -->
                <div class="card p-4 shadow-sm border-0" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px;">Cost Center & Attribution (Optional)</h5>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Associated Vendor / Supplier</label>
                            <select name="vendor_id" class="form-select" style="font-size: 13px;">
                                <option value="">-- None / Internal --</option>
                                <?php foreach ($vendors as $v): ?>
                                    <option value="<?= (int)$v['id'] ?>">
                                        <?= htmlspecialchars($v['company_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($v['vendor_code'], ENT_QUOTES, 'UTF-8') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Department</label>
                            <select name="department_id" class="form-select" style="font-size: 13px;">
                                <option value="">-- General Organization --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>">
                                        <?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Linked Sports Event</label>
                        <select name="event_id" class="form-select" style="font-size: 13px;">
                            <option value="">-- Not Linked to Event --</option>
                            <?php foreach ($events as $e): ?>
                                <option value="<?= (int)$e['id'] ?>">
                                    <?= htmlspecialchars($e['event_name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Status & Payment Card -->
                <div class="card p-4 shadow-sm border-0 mb-4" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px;">Workflow Status</h5>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Initial Status</label>
                        <select name="payment_status" class="form-select" style="font-size: 13px;">
                            <option value="pending" selected>Pending Review & Approval</option>
                            <option value="approved">Approved</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Payment Method</label>
                        <select name="payment_method" class="form-select" style="font-size: 13px;">
                            <option value="Bank Transfer" selected>Bank Transfer (NEFT/RTGS)</option>
                            <option value="UPI">UPI</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Cash">Cash</option>
                            <option value="Corporate Card">Corporate Card</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Payment Reference / Cheque No.</label>
                        <input type="text" name="payment_reference" class="form-control" placeholder="Optional transaction reference" style="font-size: 13px;">
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Receipt / Invoice File Path</label>
                        <input type="text" name="receipt_path" class="form-control" placeholder="e.g. /receipts/2026/exp_123.pdf" style="font-size: 13px;">
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary py-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600;">
                        <i class="bi bi-check-lg me-1"></i> Submit Expense Record
                    </button>
                    <a href="/finance" class="btn btn-outline-secondary py-2" style="border-radius: var(--ks-radius-button);">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function calcTotal() {
    const sub = parseFloat(document.getElementById('subtotalInput').value) || 0;
    const tax = parseFloat(document.getElementById('taxInput').value) || 0;
    const tot = sub + tax;
    document.getElementById('totalDisplay').value = '₹' + tot.toFixed(2);
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.blade.php';
