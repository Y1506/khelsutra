<?php
$pageTitle = 'Record Income — KhelSutra';
$activePage = 'finance';
$orgId = current_organization_id();

$financeService = new \App\Services\Finance\FinanceService();
$categories = $financeService->listCategories($orgId, 'income', 'active');

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
        <a href="/finance?tab=income" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Income Transactions</a>
        <span class="text-muted small">/</span>
        <span class="text-dark small fw-semibold">Record Income</span>
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Record Inward Revenue</h1>
            <p class="text-muted small mb-0">Record sponsorship funds, grants, event participation receipts, or membership fees.</p>
        </div>
    </div>

    <form method="POST" action="/finance/income/create">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card p-4 shadow-sm border-0 mb-4" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px;">Revenue Particulars</h5>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Payer / Source Name <span class="text-danger">*</span></label>
                        <input type="text" name="source_name" class="form-control" placeholder="e.g. State Sports Authority, RedBull Sponsorship, Academy Fees" required style="font-size: 13px;">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Received Amount (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control fw-bold font-monospace" placeholder="0.00" required style="font-size: 13px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Transaction Date <span class="text-danger">*</span></label>
                            <input type="date" name="income_date" class="form-control" value="<?= date('Y-m-d') ?>" required style="font-size: 13px;">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Income Category <span class="text-danger">*</span></label>
                            <select name="finance_category_id" class="form-select" required style="font-size: 13px;">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>">
                                        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Status</label>
                            <select name="status" class="form-select" style="font-size: 13px;">
                                <option value="received" selected>Received & Cleared</option>
                                <option value="pending">Pending Realization</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Description / Purpose</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Additional details or terms of the income transaction..." style="font-size: 13px;"></textarea>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card p-4 shadow-sm border-0 mb-4" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px;">Banking & Proof</h5>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Payment Method</label>
                        <select name="payment_method" class="form-select" style="font-size: 13px;">
                            <option value="Bank Transfer" selected>Bank Transfer (NEFT/RTGS)</option>
                            <option value="UPI">UPI / Net Banking</option>
                            <option value="Cheque">Demand Draft / Cheque</option>
                            <option value="Cash">Cash Receipt</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Transaction / UTR Reference</label>
                        <input type="text" name="payment_reference" class="form-control" placeholder="e.g. UTR-2026-981273" style="font-size: 13px;">
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold text-dark" style="font-size: 13px;">Receipt File Path</label>
                        <input type="text" name="receipt_path" class="form-control" placeholder="e.g. /receipts/income_912.pdf" style="font-size: 13px;">
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success py-2" style="border-radius: var(--ks-radius-button); font-weight: 600;">
                        <i class="bi bi-check-lg me-1"></i> Save Revenue Record
                    </button>
                    <a href="/finance?tab=income" class="btn btn-outline-secondary py-2" style="border-radius: var(--ks-radius-button);">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.blade.php';
