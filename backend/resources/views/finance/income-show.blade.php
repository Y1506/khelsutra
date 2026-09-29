<?php
$pageTitle = 'Income Transaction Details — KhelSutra';
$activePage = 'finance';
$orgId = current_organization_id();

$id = (int)($data['id'] ?? 0);
$financeService = new \App\Services\Finance\FinanceService();
$income = $financeService->getIncome($orgId, $id);
$categories = $financeService->listCategories($orgId, 'income', 'active');

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

    <?php if (!$income): ?>
        <div class="card p-5 text-center shadow-sm border-0" style="border-radius: var(--ks-radius-card); background: #fff;">
            <i class="bi bi-exclamation-circle text-danger fs-1 mb-3"></i>
            <h4 class="fw-bold mb-2">Transaction Not Found</h4>
            <p class="text-muted small mb-4">The requested income transaction does not exist or you do not have permission to view it.</p>
            <div>
                <a href="/finance?tab=income" class="btn btn-outline-secondary" style="font-size: 13px;">Back to Income</a>
            </div>
        </div>
    <?php else: ?>
        <!-- Breadcrumb & Header -->
        <div class="d-flex align-items-center gap-2 mb-2">
            <a href="/finance?tab=income" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Income Transactions</a>
            <span class="text-muted small">/</span>
            <span class="text-dark small fw-semibold"><?= htmlspecialchars($income['income_reference'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">
                    <?= htmlspecialchars($income['income_reference'], ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <?php
                $iBadge = match($income['status']) {
                    'received' => 'bg-success-subtle text-success border border-success-subtle',
                    'pending' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                    'cancelled' => 'bg-secondary text-white',
                    default => 'bg-light text-dark'
                };
                ?>
                <span class="badge <?= $iBadge ?> px-3 py-2" style="font-size: 12px;">
                    <?= htmlspecialchars(ucfirst($income['status']), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <?php if ($income['status'] !== 'cancelled'): ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editIncomeModal" style="font-size: 13px;">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </button>
                    <form method="POST" action="/finance/income/<?= (int)$income['id'] ?>/cancel" class="d-inline" onsubmit="return confirm('Cancel this income record?');">
                        <button type="submit" class="btn btn-outline-danger btn-sm" style="font-size: 13px;">
                            Cancel Record
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card p-4 shadow-sm border-0 mb-4" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px;">Revenue Details</h5>

                    <div class="p-3 bg-light rounded-3 mb-4">
                        <span class="text-muted d-block small">Received Amount</span>
                        <span class="fs-3 fw-bold font-monospace text-success">₹<?= number_format((float)$income['amount'], 2) ?></span>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <span class="text-muted d-block small">Payer / Source</span>
                            <span class="fs-6 fw-bold text-dark"><?= htmlspecialchars($income['source_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block small">Transaction Date</span>
                            <span class="fw-semibold text-dark"><?= date('d F Y', strtotime($income['income_date'])) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block small">Finance Category</span>
                            <span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($income['category_name'] ?? 'Revenue', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block small">Payment Method</span>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($income['payment_method'] ?? 'Bank Transfer', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <?php if (!empty($income['payment_reference'])): ?>
                            <div class="col-sm-6">
                                <span class="text-muted d-block small">Bank / UTR Reference</span>
                                <span class="fw-semibold font-monospace text-dark"><?= htmlspecialchars($income['payment_reference'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($income['description'])): ?>
                        <div class="border-top pt-3">
                            <label class="text-muted small fw-semibold text-uppercase d-block" style="font-size: 11px;">Description / Purpose</label>
                            <p class="text-dark small mb-0"><?= nl2br(htmlspecialchars($income['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card p-4 shadow-sm border-0 mb-4" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <h6 class="fw-bold mb-3 text-uppercase text-muted" style="font-size: 11px; letter-spacing: 0.05em;">Audit Information</h6>

                    <div class="mb-3">
                        <span class="text-muted d-block small">Recorded By</span>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($income['creator_name'] ?? 'Staff', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="mb-0">
                        <span class="text-muted d-block small">Created At</span>
                        <span class="text-dark small"><?= !empty($income['created_at']) ? date('d M Y, h:i A', strtotime($income['created_at'])) : '—' ?></span>
                    </div>
                </div>

                <?php if (!empty($income['receipt_path'])): ?>
                    <div class="card p-3 shadow-sm border-0" style="border-radius: var(--ks-radius-card); background: #fff;">
                        <h6 class="fw-bold mb-2 text-dark" style="font-size: 13px;">Receipt File</h6>
                        <div class="d-flex align-items-center gap-2 text-muted small">
                            <i class="bi bi-file-earmark-check fs-5 text-success"></i>
                            <span class="text-truncate"><?= htmlspecialchars($income['receipt_path'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editIncomeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: var(--ks-radius-card);">
                    <form method="POST" action="/finance/income/<?= (int)$income['id'] ?>/edit">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" style="font-size: 16px;">Edit Income Record</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" style="font-size: 13px;">
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Source Name <span class="text-danger">*</span></label>
                                <input type="text" name="source_name" class="form-control" value="<?= htmlspecialchars($income['source_name'], ENT_QUOTES, 'UTF-8') ?>" required style="font-size: 13px;">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold text-dark">Amount (₹) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= (float)$income['amount'] ?>" required style="font-size: 13px;">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold text-dark">Date <span class="text-danger">*</span></label>
                                    <input type="date" name="income_date" class="form-control" value="<?= htmlspecialchars($income['income_date'], ENT_QUOTES, 'UTF-8') ?>" required style="font-size: 13px;">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Category</label>
                                <select name="finance_category_id" class="form-select" style="font-size: 13px;">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === (int)$income['finance_category_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold text-dark">Payment Method</label>
                                    <input type="text" name="payment_method" class="form-control" value="<?= htmlspecialchars($income['payment_method'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px;">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold text-dark">Payment Ref</label>
                                    <input type="text" name="payment_reference" class="form-control" value="<?= htmlspecialchars($income['payment_reference'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px;">
                                </div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label fw-semibold text-dark">Description</label>
                                <textarea name="description" class="form-control" rows="2" style="font-size: 13px;"><?= htmlspecialchars($income['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-success">Update Record</button>
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
