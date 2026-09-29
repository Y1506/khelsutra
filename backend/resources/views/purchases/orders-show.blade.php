<?php
$pageTitle = 'Purchase Order Details — KhelSutra';
$activePage = 'purchases';
$orgId = current_organization_id();

$id = $id ?? ($data['id'] ?? ($_GET['id'] ?? 0));
$purchaseService = new \App\Services\Purchase\PurchaseService();
$po = $purchaseService->getPurchaseOrder($orgId, $id);

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

    <?php if (!$po): ?>
        <div class="card p-5 text-center" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <i class="bi bi-exclamation-circle text-danger fs-1 mb-3"></i>
            <h4 class="fw-bold mb-2">Purchase Order Not Found</h4>
            <p class="text-muted small mb-4">The requested purchase order does not exist or access was denied.</p>
            <div>
                <a href="/purchases?tab=orders" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                    Back to Procurement
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <a href="/purchases?tab=orders" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Purchase Orders</a>
                    <span class="text-muted small">/</span>
                    <span class="text-dark small fw-semibold"><?= htmlspecialchars($po['po_number'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">
                        <?= htmlspecialchars($po['po_number'], ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <?php
                    $sBadge = match($po['status']) {
                        'draft' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                        'sent' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                        'confirmed' => 'bg-primary-subtle text-primary border border-primary-subtle',
                        'partially_received' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                        'received' => 'bg-success-subtle text-success border border-success-subtle',
                        'cancelled' => 'bg-danger-subtle text-danger border border-danger-subtle',
                        default => 'bg-light text-dark'
                    };
                    ?>
                    <span class="badge <?= $sBadge ?> px-3 py-2 fw-semibold" style="font-size: 12px;">
                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $po['status'])), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2">
                <?php if ($po['status'] === 'draft'): ?>
                    <form method="POST" action="/purchases/orders/<?= (int)$po['id'] ?>/status" class="d-inline">
                        <input type="hidden" name="status" value="sent">
                        <button type="submit" class="btn btn-outline-primary d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                            <i class="bi bi-send"></i> Mark as Sent
                        </button>
                    </form>
                    <form method="POST" action="/purchases/orders/<?= (int)$po['id'] ?>/status" class="d-inline" onsubmit="return confirm('Cancel this purchase order?');">
                        <input type="hidden" name="status" value="cancelled">
                        <button type="submit" class="btn btn-outline-danger" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                            Cancel PO
                        </button>
                    </form>
                <?php elseif ($po['status'] === 'sent'): ?>
                    <form method="POST" action="/purchases/orders/<?= (int)$po['id'] ?>/status" class="d-inline">
                        <input type="hidden" name="status" value="confirmed">
                        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-size: 13px;">
                            <i class="bi bi-check2-circle"></i> Confirm Order
                        </button>
                    </form>
                    <form method="POST" action="/purchases/orders/<?= (int)$po['id'] ?>/status" class="d-inline" onsubmit="return confirm('Cancel this purchase order?');">
                        <input type="hidden" name="status" value="cancelled">
                        <button type="submit" class="btn btn-outline-danger" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                            Cancel PO
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (in_array($po['status'], ['sent', 'confirmed', 'partially_received'], true)): ?>
                    <button type="button" class="btn btn-success d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#receiveGoodsModal" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px;">
                        <i class="bi bi-box-arrow-in-down"></i> Receive Goods
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Supplier & Financial Summary Row -->
        <div class="row g-4 mb-4">
            <!-- Supplier Card -->
            <div class="col-md-6 col-xl-4">
                <div class="card h-100" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 14px;">
                            <i class="bi bi-building me-2 text-primary"></i> Supplier Information
                        </h6>
                    </div>
                    <div class="card-body p-3" style="font-size: 13px;">
                        <div class="mb-2">
                            <span class="text-muted d-block small">Vendor Name</span>
                            <a href="/vendors/<?= (int)$po['vendor_id'] ?>" class="fw-semibold text-primary text-decoration-none">
                                <?= htmlspecialchars($po['vendor_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <span class="text-muted small ms-1">(<?= htmlspecialchars($po['vendor_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>)</span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted d-block small">Contact Person</span>
                            <span class="text-dark"><?= htmlspecialchars($po['vendor_contact'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted d-block small">Phone / Email</span>
                            <span class="text-dark"><?= htmlspecialchars($po['vendor_phone'] ?? '—', ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars($po['vendor_email'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="mb-0">
                            <span class="text-muted d-block small">GSTIN</span>
                            <span class="font-monospace text-dark"><?= htmlspecialchars($po['vendor_gst'] ?? 'Unregistered', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dates & Notes Card -->
            <div class="col-md-6 col-xl-4">
                <div class="card h-100" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 14px;">
                            <i class="bi bi-calendar-check me-2 text-primary"></i> Dates & References
                        </h6>
                    </div>
                    <div class="card-body p-3" style="font-size: 13px;">
                        <div class="mb-2">
                            <span class="text-muted d-block small">Order Date</span>
                            <span class="text-dark fw-semibold"><?= !empty($po['order_date']) ? date('d M Y', strtotime($po['order_date'])) : '—' ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted d-block small">Expected Delivery Date</span>
                            <span class="text-dark"><?= !empty($po['expected_delivery_date']) ? date('d M Y', strtotime($po['expected_delivery_date'])) : '—' ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted d-block small">Requisition Link</span>
                            <?php if (!empty($po['purchase_request_id'])): ?>
                                <a href="/purchases/requests/<?= (int)$po['purchase_request_id'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($po['request_reference'] ?? 'View Requisition', ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php else: ?>
                                <em class="text-muted">Direct Purchase Order</em>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($po['notes'])): ?>
                            <div class="mb-0 pt-2 border-top">
                                <span class="text-muted d-block small">Notes</span>
                                <span class="text-dark"><?= htmlspecialchars($po['notes'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Financial Total Card -->
            <div class="col-xl-4">
                <div class="card h-100" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 14px;">
                            <i class="bi bi-cash-stack me-2 text-primary"></i> Financial Commitment
                        </h6>
                    </div>
                    <div class="card-body p-3" style="font-size: 13px;">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal:</span>
                            <span class="font-monospace fw-semibold">₹<?= number_format((float)$po['subtotal'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Tax Amount (+):</span>
                            <span class="font-monospace text-muted">+₹<?= number_format((float)$po['tax_amount'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Discount (-):</span>
                            <span class="font-monospace text-muted">-₹<?= number_format((float)$po['discount_amount'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between pt-2 border-top align-items-center">
                            <span class="fw-bold text-dark h6 mb-0">Total Commitment:</span>
                            <span class="font-monospace fw-bold text-primary h5 mb-0">₹<?= number_format((float)$po['total_amount'], 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ordered Items Table with Receiving Progress -->
        <div class="card mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 14px;">
                    <i class="bi bi-box-seam me-2 text-primary"></i> Order Items & Fulfillment Status (<?= count($po['items']) ?>)
                </h6>
                <?php if (in_array($po['status'], ['sent', 'confirmed', 'partially_received'], true)): ?>
                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#receiveGoodsModal">
                        <i class="bi bi-plus-lg"></i> Receive Shipment
                    </button>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                        <tr>
                            <th class="py-2 px-3 text-muted fw-semibold">Item Name</th>
                            <th class="py-2 px-3 text-muted fw-semibold">Stock Item</th>
                            <th class="py-2 px-3 text-muted fw-semibold text-center">Ordered</th>
                            <th class="py-2 px-3 text-muted fw-semibold text-center">Received</th>
                            <th class="py-2 px-3 text-muted fw-semibold" style="width: 140px;">Fulfillment</th>
                            <th class="py-2 px-3 text-muted fw-semibold text-end">Unit Cost</th>
                            <th class="py-2 px-3 text-muted fw-semibold text-end">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($po['items'] as $item): 
                            $ordered = (float)$item['ordered_quantity'];
                            $received = (float)$item['received_quantity'];
                            $pct = $ordered > 0 ? min(100, round(($received / $ordered) * 100)) : 0;
                            $barClass = $pct >= 100 ? 'bg-success' : ($pct > 0 ? 'bg-warning' : 'bg-secondary');
                        ?>
                            <tr>
                                <td class="py-3 px-3">
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if (!empty($item['description'])): ?>
                                        <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?php if (!empty($item['item_code'])): ?>
                                        <a href="/inventory/<?= (int)$item['inventory_item_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($item['stock_item_name'] ?? $item['item_code'], ENT_QUOTES, 'UTF-8') ?>
                                            <span class="badge bg-light text-dark border ms-1" style="font-size: 10px;">In Stock: <?= (float)($item['current_stock'] ?? 0) ?></span>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Custom Asset</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-center fw-semibold font-monospace">
                                    <?= number_format($ordered, 2) ?>
                                </td>
                                <td class="py-3 px-3 text-center fw-bold font-monospace <?= $received >= $ordered ? 'text-success' : ($received > 0 ? 'text-warning-emphasis' : 'text-muted') ?>">
                                    <?= number_format($received, 2) ?>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar <?= $barClass ?>" role="progressbar" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                        <span class="text-muted small" style="font-size: 11px;"><?= $pct ?>%</span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-end font-monospace text-muted">
                                    ₹<?= number_format((float)$item['unit_cost'], 2) ?>
                                </td>
                                <td class="py-3 px-3 text-end font-monospace fw-bold text-dark">
                                    ₹<?= number_format((float)$item['total_amount'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- History Tabs: Goods Receipts & Linked Invoices -->
        <div class="card" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <div class="card-header bg-white border-bottom p-3">
                <ul class="nav nav-pills card-header-pills" id="poHistoryTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active fw-semibold small" id="grn-tab" data-bs-toggle="tab" data-bs-target="#grn-pane" type="button" role="tab">
                            <i class="bi bi-box-arrow-in-down me-1"></i> Goods Receipts (<?= count($po['receipts']) ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-semibold small" id="inv-tab" data-bs-toggle="tab" data-bs-target="#inv-pane" type="button" role="tab">
                            <i class="bi bi-receipt me-1"></i> Vendor Invoices (<?= count($po['invoices']) ?>)
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-0">
                <div class="tab-content" id="poHistoryContent">
                    <!-- Receipts Pane -->
                    <div class="tab-pane fade show active" id="grn-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                                <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                                    <tr>
                                        <th class="py-2 px-3 text-muted fw-semibold">Receipt Number (GRN)</th>
                                        <th class="py-2 px-3 text-muted fw-semibold">Receipt Date</th>
                                        <th class="py-2 px-3 text-muted fw-semibold">Received By</th>
                                        <th class="py-2 px-3 text-muted fw-semibold">Remarks</th>
                                        <th class="py-2 px-3 text-muted fw-semibold text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($po['receipts'])): ?>
                                        <?php foreach ($po['receipts'] as $gr): ?>
                                            <tr>
                                                <td class="py-3 px-3">
                                                    <a href="/purchases/receipts/<?= (int)$gr['id'] ?>" class="fw-bold font-monospace text-success text-decoration-none">
                                                        <?= htmlspecialchars($gr['receipt_number'], ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                </td>
                                                <td class="py-3 px-3 text-muted">
                                                    <?= !empty($gr['receipt_date']) ? date('d M Y', strtotime($gr['receipt_date'])) : '—' ?>
                                                </td>
                                                <td class="py-3 px-3 text-dark">
                                                    <?= htmlspecialchars($gr['received_by_name'] ?? 'Warehouse Staff', ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="py-3 px-3 text-muted text-truncate" style="max-width: 250px;">
                                                    <?= htmlspecialchars($gr['remarks'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="py-3 px-3 text-end">
                                                    <a href="/purchases/receipts/<?= (int)$gr['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                        <i class="bi bi-eye"></i> View GRN
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                No goods receipt notes recorded for this purchase order yet.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Invoices Pane -->
                    <div class="tab-pane fade" id="inv-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                                <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                                    <tr>
                                        <th class="py-2 px-3 text-muted fw-semibold">Invoice Number</th>
                                        <th class="py-2 px-3 text-muted fw-semibold">Invoice Date</th>
                                        <th class="py-2 px-3 text-muted fw-semibold text-end">Total Amount</th>
                                        <th class="py-2 px-3 text-muted fw-semibold text-center">Status</th>
                                        <th class="py-2 px-3 text-muted fw-semibold text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($po['invoices'])): ?>
                                        <?php foreach ($po['invoices'] as $inv): ?>
                                            <tr>
                                                <td class="py-3 px-3 fw-bold text-dark">
                                                    <?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="py-3 px-3 text-muted">
                                                    <?= !empty($inv['invoice_date']) ? date('d M Y', strtotime($inv['invoice_date'])) : '—' ?>
                                                </td>
                                                <td class="py-3 px-3 text-end fw-bold font-monospace text-dark">
                                                    ₹<?= number_format((float)$inv['total_amount'], 2) ?>
                                                </td>
                                                <td class="py-3 px-3 text-center">
                                                    <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 11px;">
                                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $inv['payment_status'])), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-3 text-end">
                                                    <a href="/vendors/<?= (int)$po['vendor_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                        <i class="bi bi-box-arrow-up-right"></i> View Bill
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                No vendor bills or invoices linked to this purchase order.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receive Goods Modal -->
        <div class="modal fade" id="receiveGoodsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="border-radius: var(--ks-radius-card);">
                    <form method="POST" action="/purchases/orders/<?= (int)$po['id'] ?>/receive">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" style="font-size: 16px;">
                                <i class="bi bi-box-arrow-in-down text-success me-2"></i> Receive Shipment for <?= htmlspecialchars($po['po_number'], ENT_QUOTES, 'UTF-8') ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-dark">Receipt Date <span class="text-danger">*</span></label>
                                    <input type="date" name="receipt_date" class="form-control" value="<?= date('Y-m-d') ?>" required style="font-size: 13px;">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-dark">Receipt Reference (GRN)</label>
                                    <input type="text" name="receipt_number" class="form-control" placeholder="Leave blank to auto-generate" style="font-size: 13px;">
                                    <span class="text-muted" style="font-size: 11px;">e.g. GRN-20260923-0001</span>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold text-dark">Delivery / Inspection Remarks</label>
                                    <input type="text" name="remarks" class="form-control" placeholder="e.g. Shipment received in intact condition, batch inspected by store manager" style="font-size: 13px;">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2 pt-2 border-top">
                                <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 13px;">Item Quantities to Receive:</h6>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fillAllRemaining()">
                                    Fill All Remaining
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0" style="font-size: 13px;">
                                    <thead style="background: var(--ks-page-bg);">
                                        <tr>
                                            <th>Item</th>
                                            <th class="text-center" style="width: 100px;">Ordered</th>
                                            <th class="text-center" style="width: 100px;">Previously Rec.</th>
                                            <th class="text-center" style="width: 100px;">Remaining</th>
                                            <th style="width: 140px;">Receive Now <span class="text-danger">*</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($po['items'] as $item): 
                                            $ord = (float)$item['ordered_quantity'];
                                            $rec = (float)$item['received_quantity'];
                                            $rem = max(0, $ord - $rec);
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php if (!empty($item['inventory_item_id'])): ?>
                                                        <div class="text-success" style="font-size: 11px;"><i class="bi bi-arrow-repeat"></i> Will increment stock inventory</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center font-monospace"><?= number_format($ord, 2) ?></td>
                                                <td class="text-center font-monospace text-muted"><?= number_format($rec, 2) ?></td>
                                                <td class="text-center font-monospace fw-bold text-primary"><?= number_format($rem, 2) ?></td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="<?= $rem ?>" name="items[<?= (int)$item['id'] ?>]" class="form-control font-monospace rec-input" data-remaining="<?= $rem ?>" value="<?= $rem > 0 ? $rem : 0 ?>" <?= $rem <= 0 ? 'readonly' : '' ?> style="font-size: 13px;">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success" style="font-weight: 600;">
                                <i class="bi bi-check-circle"></i> Confirm & Update Inventory
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function fillAllRemaining() {
                document.querySelectorAll('.rec-input').forEach(inp => {
                    const rem = parseFloat(inp.getAttribute('data-remaining')) || 0;
                    inp.value = rem > 0 ? rem.toFixed(2) : '0';
                });
            }
        </script>
    <?php endif; ?>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
