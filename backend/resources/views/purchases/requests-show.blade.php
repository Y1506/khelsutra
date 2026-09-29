<?php
$pageTitle = 'Purchase Request Details — KhelSutra';
$activePage = 'purchases';
$orgId = current_organization_id();

$id = (int)($id ?? ($data['id'] ?? ($_GET['id'] ?? 0)));
$purchaseService = new \App\Services\Purchase\PurchaseService();
$pr = $purchaseService->getPurchaseRequest($orgId, $id);

$db = \App\Services\BaseService::getDatabaseConnection();
$linkedPo = null;
if ($pr && $pr['status'] === 'converted') {
    $poStmt = $db->prepare("SELECT id, po_number FROM purchase_orders WHERE purchase_request_id = :pr_id AND organization_id = :org_id LIMIT 1");
    $poStmt->execute([':pr_id' => $id, ':org_id' => $orgId]);
    $linkedPo = $poStmt->fetch(PDO::FETCH_ASSOC);
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

    <?php if (!$pr): ?>
        <div class="card p-5 text-center" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <i class="bi bi-exclamation-circle text-danger fs-1 mb-3"></i>
            <h4 class="fw-bold mb-2">Purchase Request Not Found</h4>
            <p class="text-muted small mb-4">The requested purchase requisition does not exist or access was denied.</p>
            <div>
                <a href="/purchases?tab=requests" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                    Back to Procurement
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <a href="/purchases?tab=requests" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Purchase Requests</a>
                    <span class="text-muted small">/</span>
                    <span class="text-dark small fw-semibold"><?= htmlspecialchars($pr['request_reference'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">
                        <?= htmlspecialchars($pr['request_reference'], ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <?php
                    $rBadge = match($pr['status']) {
                        'draft' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                        'submitted' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                        'approved' => 'bg-success-subtle text-success border border-success-subtle',
                        'rejected' => 'bg-danger-subtle text-danger border border-danger-subtle',
                        'converted' => 'bg-primary-subtle text-primary border border-primary-subtle',
                        'cancelled' => 'bg-light text-muted border',
                        default => 'bg-light text-dark'
                    };
                    ?>
                    <span class="badge <?= $rBadge ?> px-3 py-2 fw-semibold" style="font-size: 12px;">
                        <?= htmlspecialchars(ucfirst($pr['status']), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
            </div>

            <!-- Workflow Action Buttons -->
            <div class="d-flex gap-2">
                <?php if ($pr['status'] === 'draft'): ?>
                    <form method="POST" action="/purchases/requests/<?= (int)$pr['id'] ?>/submit" class="d-inline">
                        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px;">
                            <i class="bi bi-send-check"></i> Submit for Approval
                        </button>
                    </form>
                    <form method="POST" action="/purchases/requests/<?= (int)$pr['id'] ?>/cancel" class="d-inline" onsubmit="return confirm('Cancel this requisition request?');">
                        <button type="submit" class="btn btn-outline-danger" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                            Cancel Request
                        </button>
                    </form>
                <?php elseif ($pr['status'] === 'submitted'): ?>
                    <form method="POST" action="/purchases/requests/<?= (int)$pr['id'] ?>/approve" class="d-inline">
                        <button type="submit" class="btn btn-success d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px;">
                            <i class="bi bi-check-circle"></i> Approve Request
                        </button>
                    </form>
                    <button type="button" class="btn btn-outline-danger d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#rejectModal" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                        <i class="bi bi-x-circle"></i> Reject
                    </button>
                <?php elseif ($pr['status'] === 'approved'): ?>
                    <a href="/purchases/orders/create?purchase_request_id=<?= (int)$pr['id'] ?>" class="btn btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px;">
                        <i class="bi bi-cart-plus"></i> Generate Purchase Order
                    </a>
                <?php elseif ($pr['status'] === 'converted' && $linkedPo): ?>
                    <a href="/purchases/orders/<?= (int)$linkedPo['id'] ?>" class="btn btn-outline-primary d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px;">
                        <i class="bi bi-box-arrow-up-right"></i> View Order (<?= htmlspecialchars($linkedPo['po_number'], ENT_QUOTES, 'UTF-8') ?>)
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($pr['status'] === 'rejected' && !empty($pr['rejection_reason'])): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" style="border-radius: var(--ks-radius-card); font-size: 13px;">
                <i class="bi bi-x-octagon-fill fs-5"></i>
                <div>
                    <strong>Rejection Reason:</strong> <?= htmlspecialchars($pr['rejection_reason'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Details Grid -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <!-- Requisition Line Items -->
                <div class="card" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 14px;">
                            <i class="bi bi-list-check me-2 text-primary"></i> Requisition Line Items (<?= count($pr['items']) ?>)
                        </h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                                <tr>
                                    <th class="py-2 px-3 text-muted fw-semibold">Item Name</th>
                                    <th class="py-2 px-3 text-muted fw-semibold">Stock Link</th>
                                    <th class="py-2 px-3 text-muted fw-semibold text-center">Quantity</th>
                                    <th class="py-2 px-3 text-muted fw-semibold text-end">Est. Unit Cost</th>
                                    <th class="py-2 px-3 text-muted fw-semibold text-end">Est. Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalEst = 0;
                                foreach ($pr['items'] as $item): 
                                    $totalEst += (float)$item['estimated_total'];
                                ?>
                                    <tr>
                                        <td class="py-3 px-3">
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if (!empty($item['description'])): ?>
                                                <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3 text-muted">
                                            <?php if (!empty($item['item_code'])): ?>
                                                <a href="/inventory/<?= (int)$item['inventory_item_id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($item['stock_item_name'] ?? $item['item_code'], ENT_QUOTES, 'UTF-8') ?>
                                                    <span class="badge bg-light text-dark border ms-1" style="font-size: 10px;">Stock: <?= (float)($item['in_stock_quantity'] ?? 0) ?></span>
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">Custom Asset</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3 text-center fw-semibold font-monospace">
                                            <?= number_format((float)$item['quantity'], 2) ?>
                                        </td>
                                        <td class="py-3 px-3 text-end font-monospace text-muted">
                                            ₹<?= number_format((float)$item['estimated_unit_cost'], 2) ?>
                                        </td>
                                        <td class="py-3 px-3 text-end font-monospace fw-bold text-dark">
                                            ₹<?= number_format((float)$item['estimated_total'], 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot style="background: #fafafa;">
                                <tr>
                                    <th colspan="4" class="text-end">Total Estimated Cost:</th>
                                    <th class="text-end font-monospace fw-bold text-primary">₹<?= number_format($totalEst, 2) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sidebar: Overview & Audit -->
            <div class="col-lg-4">
                <div class="card mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 14px;">
                            <i class="bi bi-info-circle me-2 text-primary"></i> Request Details
                        </h6>
                    </div>
                    <div class="card-body p-3" style="font-size: 13px;">
                        <div class="mb-2">
                            <span class="text-muted d-block small">Requested By</span>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($pr['requester_name'] ?? 'Staff', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted d-block small">Request Date</span>
                            <span class="text-dark"><?= !empty($pr['request_date']) ? date('d M Y', strtotime($pr['request_date'])) : '—' ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted d-block small">Required By Date</span>
                            <span class="text-dark"><?= !empty($pr['required_date']) ? date('d M Y', strtotime($pr['required_date'])) : '—' ?></span>
                        </div>
                        <?php if (!empty($pr['purpose'])): ?>
                            <div class="mb-2 pt-2 border-top">
                                <span class="text-muted d-block small">Purpose / Justification</span>
                                <div class="text-dark"><?= htmlspecialchars($pr['purpose'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Approval Sign-off Card -->
                <div class="card" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 14px;">
                            <i class="bi bi-shield-check me-2 text-primary"></i> Sign-off Status
                        </h6>
                    </div>
                    <div class="card-body p-3" style="font-size: 13px;">
                        <?php if ($pr['status'] === 'approved' || $pr['status'] === 'converted'): ?>
                            <div class="mb-2 text-success fw-semibold">
                                <i class="bi bi-check-circle-fill me-1"></i> Approved by <?= htmlspecialchars($pr['approver_name'] ?? 'Administrator', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <span class="text-muted small">on <?= !empty($pr['approved_at']) ? date('d M Y, h:i A', strtotime($pr['approved_at'])) : '—' ?></span>
                        <?php elseif ($pr['status'] === 'rejected'): ?>
                            <div class="mb-2 text-danger fw-semibold">
                                <i class="bi bi-x-circle-fill me-1"></i> Rejected
                            </div>
                        <?php elseif ($pr['status'] === 'submitted'): ?>
                            <div class="text-warning-emphasis fw-semibold">
                                <i class="bi bi-clock-history me-1"></i> Pending Administrative Review
                            </div>
                        <?php else: ?>
                            <div class="text-muted">
                                <i class="bi bi-pencil me-1"></i> In Draft Status (Unsubmitted)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: var(--ks-radius-card);">
                    <form method="POST" action="/purchases/requests/<?= (int)$pr['id'] ?>/reject">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" style="font-size: 16px;">Reject Purchase Request</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-dark">Reason for Rejection <span class="text-danger">*</span></label>
                                <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Specify budget limitation, alternate stock availability, or clarification required..." style="font-size: 13px;"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reject Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
