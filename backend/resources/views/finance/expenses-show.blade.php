<?php
$pageTitle = 'Expense Details — KhelSutra';
$activePage = 'finance';
$orgId = current_organization_id();

$id = (int)($data['id'] ?? 0);
$financeService = new \App\Services\Finance\FinanceService();
$expense = $financeService->getExpense($orgId, $id);

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

    <?php if (!$expense): ?>
        <div class="card p-5 text-center shadow-sm border-0" style="border-radius: var(--ks-radius-card); background: #fff;">
            <i class="bi bi-exclamation-circle text-danger fs-1 mb-3"></i>
            <h4 class="fw-bold mb-2">Expense Not Found</h4>
            <p class="text-muted small mb-4">The requested expense record does not exist or you do not have permission to view it.</p>
            <div>
                <a href="/finance" class="btn btn-outline-secondary" style="font-size: 13px;">Back to Finance</a>
            </div>
        </div>
    <?php else: ?>
        <!-- Breadcrumb & Header -->
        <div class="d-flex align-items-center gap-2 mb-2">
            <a href="/finance?tab=expenses" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Expenses</a>
            <span class="text-muted small">/</span>
            <span class="text-dark small fw-semibold"><?= htmlspecialchars($expense['expense_reference'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">
                    <?= htmlspecialchars($expense['expense_reference'], ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <?php
                $sBadge = match($expense['payment_status']) {
                    'paid' => 'bg-success-subtle text-success border border-success-subtle',
                    'approved' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                    'pending' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                    'rejected' => 'bg-danger-subtle text-danger border border-danger-subtle',
                    'cancelled' => 'bg-secondary text-white',
                    default => 'bg-light text-dark'
                };
                ?>
                <span class="badge <?= $sBadge ?> px-3 py-2" style="font-size: 12px;">
                    <?= htmlspecialchars(ucfirst($expense['payment_status']), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <?php if ($expense['payment_status'] === 'pending'): ?>
                    <form method="POST" action="/finance/expenses/<?= (int)$expense['id'] ?>/approve" class="d-inline" onsubmit="return confirm('Approve this expense requisition?');">
                        <button type="submit" class="btn btn-success btn-sm px-3" style="font-size: 13px;">
                            <i class="bi bi-check-lg me-1"></i> Approve Expense
                        </button>
                    </form>
                    <button type="button" class="btn btn-outline-danger btn-sm px-3" data-bs-toggle="modal" data-bs-target="#rejectExpenseModal" style="font-size: 13px;">
                        <i class="bi bi-x-lg me-1"></i> Reject
                    </button>
                <?php endif; ?>

                <?php if (in_array($expense['payment_status'], ['pending', 'approved'], true)): ?>
                    <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#disbursePaymentModal" style="background: var(--ks-blue); border-color: var(--ks-blue); font-size: 13px;">
                        <i class="bi bi-cash-stack me-1"></i> Record Disbursement
                    </button>
                <?php endif; ?>

                <?php if ($expense['payment_status'] !== 'paid' && $expense['payment_status'] !== 'cancelled'): ?>
                    <form method="POST" action="/finance/expenses/<?= (int)$expense['id'] ?>/cancel" class="d-inline" onsubmit="return confirm('Cancel this expense record?');">
                        <button type="submit" class="btn btn-outline-secondary btn-sm" style="font-size: 13px;">
                            Cancel
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($expense['payment_status'] !== 'paid'): ?>
                    <form method="POST" action="/finance/expenses/<?= (int)$expense['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Permanently delete this record?');">
                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Expense">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Summary Card -->
                <div class="card p-4 shadow-sm border-0 mb-4" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px;">Expense Particulars</h5>

                    <div class="mb-4">
                        <label class="text-muted small fw-semibold text-uppercase d-block" style="font-size: 11px;">Description / Purpose</label>
                        <p class="fs-6 fw-semibold text-dark mb-0"><?= nl2br(htmlspecialchars($expense['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                    </div>

                    <div class="row g-3 p-3 bg-light rounded-3 mb-4">
                        <div class="col-md-4">
                            <span class="text-muted d-block small">Subtotal</span>
                            <span class="fs-5 fw-bold font-monospace text-dark">₹<?= number_format((float)$expense['amount'], 2) ?></span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted d-block small">Tax / GST</span>
                            <span class="fs-5 fw-bold font-monospace text-muted">+₹<?= number_format((float)$expense['tax_amount'], 2) ?></span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted d-block small">Total Amount</span>
                            <span class="fs-4 fw-bold font-monospace text-primary">₹<?= number_format((float)$expense['total_amount'], 2) ?></span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-muted d-block small">Expense Date</span>
                            <span class="fw-semibold text-dark"><?= date('d F Y', strtotime($expense['expense_date'])) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block small">Finance Category</span>
                            <span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($expense['category_name'] ?? 'General Expenditure', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block small">Vendor / Supplier</span>
                            <?php if (!empty($expense['vendor_name'])): ?>
                                <span class="fw-semibold text-dark"><i class="bi bi-truck me-1"></i><?= htmlspecialchars($expense['vendor_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <em class="text-muted">None / Direct Expense</em>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block small">Department</span>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($expense['department_name'] ?? 'General Organization', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>

                    <?php if (!empty($expense['notes'])): ?>
                        <div class="mt-4 pt-3 border-top">
                            <label class="text-muted small fw-semibold text-uppercase d-block" style="font-size: 11px;">Internal Notes & Audit Log</label>
                            <div class="p-3 bg-light rounded text-muted small" style="white-space: pre-wrap; font-size: 12px;"><?= htmlspecialchars($expense['notes'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Linked Payments Card -->
                <div class="card p-4 shadow-sm border-0" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 15px;">Disbursement & Settlement History</h5>
                        <?php if (in_array($expense['payment_status'], ['pending', 'approved'], true)): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#disbursePaymentModal" style="font-size: 12px;">
                                <i class="bi bi-plus-lg me-1"></i> Add Payment
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-2 px-3 text-muted">Payment Ref</th>
                                    <th class="py-2 px-3 text-muted">Date</th>
                                    <th class="py-2 px-3 text-muted">Method</th>
                                    <th class="py-2 px-3 text-muted">Txn Reference</th>
                                    <th class="py-2 px-3 text-muted text-end">Amount</th>
                                    <th class="py-2 px-3 text-muted">Disbursed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($expense['payments'])): ?>
                                    <?php foreach ($expense['payments'] as $p): ?>
                                        <tr>
                                            <td class="py-2 px-3 font-monospace fw-bold text-dark"><?= htmlspecialchars($p['payment_reference'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="py-2 px-3 text-muted"><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                                            <td class="py-2 px-3 text-muted"><?= htmlspecialchars($p['payment_method'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="py-2 px-3 text-muted font-monospace"><?= htmlspecialchars($p['transaction_reference'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="py-2 px-3 text-end font-monospace fw-bold text-success">₹<?= number_format((float)$p['amount'], 2) ?></td>
                                            <td class="py-2 px-3 text-muted"><?= htmlspecialchars($p['creator_name'] ?? 'Staff', ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            No disbursement records attached to this expense yet.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Audit & Governance -->
                <div class="card p-4 shadow-sm border-0 mb-4" style="border-radius: var(--ks-radius-card); background: #fff;">
                    <h6 class="fw-bold mb-3 text-uppercase text-muted" style="font-size: 11px; letter-spacing: 0.05em;">Audit & Governance</h6>

                    <div class="mb-3">
                        <span class="text-muted d-block small">Requisitioned By</span>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($expense['creator_name'] ?? 'Staff Member', ENT_QUOTES, 'UTF-8') ?></span>
                        <small class="text-muted d-block"><?= !empty($expense['created_at']) ? date('d M Y, h:i A', strtotime($expense['created_at'])) : '—' ?></small>
                    </div>

                    <?php if (!empty($expense['approved_by'])): ?>
                        <div class="mb-3">
                            <span class="text-muted d-block small">Approved By</span>
                            <span class="fw-semibold text-success"><i class="bi bi-shield-check me-1"></i><?= htmlspecialchars($expense['approver_name'] ?? 'Authorized Officer', ENT_QUOTES, 'UTF-8') ?></span>
                            <small class="text-muted d-block"><?= !empty($expense['approved_at']) ? date('d M Y, h:i A', strtotime($expense['approved_at'])) : '—' ?></small>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($expense['paid_at'])): ?>
                        <div class="mb-0">
                            <span class="text-muted d-block small">Settlement Completed</span>
                            <span class="fw-semibold text-primary"><i class="bi bi-check-circle-fill me-1"></i>Disbursed on <?= date('d M Y, h:i A', strtotime($expense['paid_at'])) ?></span>
                            <?php if (!empty($expense['payment_method'])): ?>
                                <small class="text-muted d-block">Method: <?= htmlspecialchars($expense['payment_method'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($expense['receipt_path'])): ?>
                    <div class="card p-3 shadow-sm border-0" style="border-radius: var(--ks-radius-card); background: #fff;">
                        <h6 class="fw-bold mb-2 text-dark" style="font-size: 13px;">Attachment</h6>
                        <div class="d-flex align-items-center gap-2 text-muted small">
                            <i class="bi bi-paperclip fs-5"></i>
                            <span class="text-truncate"><?= htmlspecialchars($expense['receipt_path'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Reject Expense -->
<div class="modal fade" id="rejectExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: var(--ks-radius-card);">
            <form method="POST" action="/finance/expenses/<?= (int)($expense['id'] ?? 0) ?>/reject">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" style="font-size: 16px;">Reject Expense Requisition</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="font-size: 13px;">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Provide justification for rejecting this expense..." required style="font-size: 13px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger">Reject Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Disburse Payment -->
<div class="modal fade" id="disbursePaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: var(--ks-radius-card);">
            <form method="POST" action="/finance/payments/create">
                <input type="hidden" name="payment_type" value="expense">
                <input type="hidden" name="expense_id" value="<?= (int)($expense['id'] ?? 0) ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" style="font-size: 16px;">Record Expense Disbursement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="font-size: 13px;">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark">Disbursed Amount (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= (float)($expense['total_amount'] ?? 0) ?>" required style="font-size: 13px;">
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
                                <option value="Bank Transfer" selected>Bank Transfer</option>
                                <option value="UPI">UPI</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Cash">Cash</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark">UTR / Txn Ref</label>
                            <input type="text" name="transaction_reference" class="form-control" placeholder="e.g. UTR987654" style="font-size: 13px;">
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold text-dark">Disbursement Remarks</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Remarks..." style="font-size: 13px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue);">Record & Mark as Paid</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.blade.php';
