<?php
$pageTitle = 'Goods Receipt Note (GRN) — KhelSutra';
$activePage = 'purchases';
$orgId = current_organization_id();

$id = (int)($id ?? ($data['id'] ?? ($_GET['id'] ?? 0)));
$purchaseService = new \App\Services\Purchase\PurchaseService();
$receipt = $purchaseService->getGoodsReceipt($orgId, $id);

ob_start();
?>

<div class="ks-content">
    <?php if (!$receipt): ?>
        <div class="card p-5 text-center" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <i class="bi bi-exclamation-circle text-danger fs-1 mb-3"></i>
            <h4 class="fw-bold mb-2">Goods Receipt Note Not Found</h4>
            <p class="text-muted small mb-4">The requested shipment receipt does not exist or access was denied.</p>
            <div>
                <a href="/purchases?tab=receipts" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                    Back to Procurement
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <a href="/purchases?tab=receipts" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Goods Receipts</a>
                    <span class="text-muted small">/</span>
                    <span class="text-dark small fw-semibold"><?= htmlspecialchars($receipt['receipt_number'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">
                        <?= htmlspecialchars($receipt['receipt_number'], ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-semibold" style="font-size: 12px;">
                        Shipment Received
                    </span>
                </div>
            </div>
            <div>
                <a href="/purchases/orders/<?= (int)$receipt['purchase_order_id'] ?>" class="btn btn-outline-primary d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                    <i class="bi bi-cart-check"></i> View Linked Order (<?= htmlspecialchars($receipt['po_number'], ENT_QUOTES, 'UTF-8') ?>)
                </a>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card h-100" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 14px;">
                            <i class="bi bi-box-arrow-in-down me-2 text-primary"></i> Receipt Information
                        </h6>
                    </div>
                    <div class="card-body p-3" style="font-size: 13px;">
                        <div class="mb-2">
                            <span class="text-muted d-block small">Receipt Date</span>
                            <span class="fw-semibold text-dark"><?= !empty($receipt['receipt_date']) ? date('d M Y', strtotime($receipt['receipt_date'])) : '—' ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted d-block small">Received By</span>
                            <span class="text-dark"><?= htmlspecialchars($receipt['received_by_name'] ?? 'Warehouse Staff', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="mb-0">
                            <span class="text-muted d-block small">Inspection Remarks</span>
                            <span class="text-dark"><?= !empty($receipt['remarks']) ? htmlspecialchars($receipt['remarks'], ENT_QUOTES, 'UTF-8') : '<em class="text-muted">None specified</em>' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 14px;">
                            <i class="bi bi-building me-2 text-primary"></i> Supplier & Order Reference
                        </h6>
                    </div>
                    <div class="card-body p-3" style="font-size: 13px;">
                        <div class="mb-2">
                            <span class="text-muted d-block small">Supplier</span>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($receipt['vendor_name'] ?? 'Supplier', ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($receipt['vendor_code'])): ?>
                                <span class="text-muted small ms-1">(<?= htmlspecialchars($receipt['vendor_code'], ENT_QUOTES, 'UTF-8') ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted d-block small">Purchase Order #</span>
                            <a href="/purchases/orders/<?= (int)$receipt['purchase_order_id'] ?>" class="text-decoration-none font-monospace text-primary fw-semibold">
                                <?= htmlspecialchars($receipt['po_number'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </div>
                        <div class="mb-0">
                            <span class="text-muted d-block small">Order Status</span>
                            <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 11px;">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $receipt['po_status'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Line Items Received -->
        <div class="card" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <div class="card-header bg-white border-bottom p-3">
                <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 14px;">
                    <i class="bi bi-boxes me-2 text-primary"></i> Order Items & Current Quantities
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                        <tr>
                            <th class="py-2 px-3 text-muted fw-semibold">Item Name</th>
                            <th class="py-2 px-3 text-muted fw-semibold">Inventory Code</th>
                            <th class="py-2 px-3 text-muted fw-semibold text-center">Ordered Quantity</th>
                            <th class="py-2 px-3 text-muted fw-semibold text-center">Total Received Quantity</th>
                            <th class="py-2 px-3 text-muted fw-semibold text-end">Unit Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receipt['po_items'] as $item): ?>
                            <tr>
                                <td class="py-3 px-3 fw-semibold text-dark">
                                    <?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?php if (!empty($item['item_code'])): ?>
                                        <a href="/inventory/<?= (int)$item['inventory_item_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($item['item_code'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Custom Asset</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-center font-monospace">
                                    <?= number_format((float)$item['ordered_quantity'], 2) ?>
                                </td>
                                <td class="py-3 px-3 text-center font-monospace fw-bold text-success">
                                    <?= number_format((float)$item['received_quantity'], 2) ?>
                                </td>
                                <td class="py-3 px-3 text-end font-monospace text-muted">
                                    ₹<?= number_format((float)$item['unit_cost'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
