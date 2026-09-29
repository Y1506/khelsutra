<?php
$pageTitle = 'Vendor Details — KhelSutra';
$activePage = 'vendors';
$orgId = current_organization_id();

$id = (int)($id ?? ($data['id'] ?? ($_GET['id'] ?? 0)));
$vendorService = new \App\Services\Vendor\VendorService();
$vendor = $vendorService->getVendor($orgId, $id);

$invoices = [];
$stats = [
    'total_invoiced' => 0.0,
    'total_paid' => 0.0,
    'outstanding_balance' => 0.0,
    'invoice_count' => 0,
    'unpaid_count' => 0,
];
$purchaseOrders = [];

if ($vendor) {
    $invoiceResult = $vendorService->listInvoices($orgId, $id, 1, 100);
    $invoices = $invoiceResult['data'] ?? [];

    foreach ($invoices as $inv) {
        $stats['invoice_count']++;
        $tot = (float)$inv['total_amount'];
        $stats['total_invoiced'] += $tot;
        if ($inv['payment_status'] === 'paid') {
            $stats['total_paid'] += $tot;
        } elseif ($inv['payment_status'] === 'unpaid' || $inv['payment_status'] === 'partially_paid') {
            $stats['outstanding_balance'] += $tot;
            $stats['unpaid_count']++;
        }
    }

    $db = \App\Services\BaseService::getDatabaseConnection();
    try {
        $poStmt = $db->prepare("
            SELECT po.*, 
                   COALESCE(u.name, 'Admin') as creator_name
            FROM purchase_orders po
            LEFT JOIN users u ON po.created_by = u.id
            WHERE po.organization_id = :org_id AND po.vendor_id = :vendor_id AND po.deleted_at IS NULL
            ORDER BY po.order_date DESC, po.id DESC
        ");
        $poStmt->execute([':org_id' => $orgId, ':vendor_id' => $id]);
        $purchaseOrders = $poStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        $purchaseOrders = [];
    }
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

    <?php if (!$vendor): ?>
        <div class="card p-5 text-center" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <i class="bi bi-exclamation-circle text-danger fs-1 mb-3"></i>
            <h4 class="fw-bold mb-2">Vendor Not Found</h4>
            <p class="text-muted small mb-4">The requested supplier or vendor does not exist or you do not have permission to view it.</p>
            <div>
                <a href="/vendors" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                    Back to Vendors
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <a href="/vendors" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Vendors Directory</a>
                    <span class="text-muted small">/</span>
                    <span class="text-dark small fw-semibold"><?= htmlspecialchars($vendor['vendor_code'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">
                        <?= htmlspecialchars($vendor['company_name'], ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <?php
                    $sBadge = match($vendor['status']) {
                        'active' => 'bg-success-subtle text-success border border-success-subtle',
                        'inactive' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                        'blacklisted' => 'bg-danger text-white',
                        default => 'bg-light text-dark border'
                    };
                    ?>
                    <span class="badge <?= $sBadge ?> px-3 py-2 fw-semibold" style="font-size: 12px;">
                        <?= htmlspecialchars(ucfirst($vendor['status']), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php if (!empty($vendor['vendor_type'])): ?>
                        <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 11px;">
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $vendor['vendor_type'])), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-size: 13px;" data-bs-toggle="modal" data-bs-target="#statusModal">
                    <i class="bi bi-arrow-repeat"></i> Change Status
                </button>
                <a href="/vendors/<?= (int)$vendor['id'] ?>/edit" class="btn btn-outline-primary d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                    <i class="bi bi-pencil-square"></i> Edit Vendor
                </a>
                <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px;" data-bs-toggle="modal" data-bs-target="#recordInvoiceModal">
                    <i class="bi bi-receipt"></i> Record Invoice
                </button>
            </div>
        </div>

        <!-- KPI Metrics Row -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card p-3 h-100" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <span class="text-muted small fw-medium">Total Invoiced</span>
                    <h3 class="fw-bold mt-1 mb-0" style="color: var(--ks-navy);">₹<?= number_format($stats['total_invoiced'], 2) ?></h3>
                    <span class="text-muted" style="font-size: 11px;"><?= $stats['invoice_count'] ?> recorded invoices</span>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card p-3 h-100" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <span class="text-muted small fw-medium">Total Paid</span>
                    <h3 class="fw-bold mt-1 mb-0 text-success">₹<?= number_format($stats['total_paid'], 2) ?></h3>
                    <span class="text-muted" style="font-size: 11px;">Cleared supplier disbursements</span>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card p-3 h-100" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <span class="text-muted small fw-medium">Outstanding Balance</span>
                    <h3 class="fw-bold mt-1 mb-0 <?= $stats['outstanding_balance'] > 0 ? 'text-danger' : 'text-muted' ?>">
                        ₹<?= number_format($stats['outstanding_balance'], 2) ?>
                    </h3>
                    <span class="text-muted" style="font-size: 11px;"><?= $stats['unpaid_count'] ?> pending or partial bills</span>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card p-3 h-100" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <span class="text-muted small fw-medium">Purchase Orders</span>
                    <h3 class="fw-bold mt-1 mb-0" style="color: var(--ks-navy);"><?= count($purchaseOrders) ?></h3>
                    <span class="text-muted" style="font-size: 11px;">Associated purchase orders</span>
                </div>
            </div>
        </div>

        <!-- Main Body Grid: Tabs on Left, Profile Details on Right -->
        <div class="row g-4">
            <!-- Left Column: Invoices & Purchase History -->
            <div class="col-lg-8">
                <div class="card" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="card-header bg-white border-bottom p-3">
                        <ul class="nav nav-pills card-header-pills" id="vendorTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-semibold small" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoices-pane" type="button" role="tab">
                                    <i class="bi bi-receipt me-1"></i> Invoices & Bills (<?= count($invoices) ?>)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold small" id="purchases-tab" data-bs-toggle="tab" data-bs-target="#purchases-pane" type="button" role="tab">
                                    <i class="bi bi-bag-check me-1"></i> Purchase Orders (<?= count($purchaseOrders) ?>)
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-0">
                        <div class="tab-content" id="vendorTabsContent">
                            <!-- Invoices Pane -->
                            <div class="tab-pane fade show active" id="invoices-pane" role="tabpanel">
                                <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light-subtle">
                                    <div class="text-muted small">All vendor invoices, billings, and payment settlements.</div>
                                    <button type="button" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button);" data-bs-toggle="modal" data-bs-target="#recordInvoiceModal">
                                        <i class="bi bi-plus-lg"></i> Record Invoice
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                                        <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                                            <tr>
                                                <th class="py-2 px-3 text-muted fw-semibold">Invoice #</th>
                                                <th class="py-2 px-3 text-muted fw-semibold">Invoice Date</th>
                                                <th class="py-2 px-3 text-muted fw-semibold">Due Date</th>
                                                <th class="py-2 px-3 text-muted fw-semibold text-end">Subtotal</th>
                                                <th class="py-2 px-3 text-muted fw-semibold text-end">Tax / Disc</th>
                                                <th class="py-2 px-3 text-muted fw-semibold text-end">Total Amount</th>
                                                <th class="py-2 px-3 text-muted fw-semibold text-center">Payment Status</th>
                                                <th class="py-2 px-3 text-muted fw-semibold text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($invoices)): ?>
                                                <?php foreach ($invoices as $inv): ?>
                                                    <tr>
                                                        <td class="py-3 px-3">
                                                            <span class="fw-bold text-dark"><?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES, 'UTF-8') ?></span>
                                                            <?php if (!empty($inv['notes'])): ?>
                                                                <div class="text-muted text-truncate" style="font-size: 11px; max-width: 160px;" title="<?= htmlspecialchars($inv['notes'], ENT_QUOTES, 'UTF-8') ?>">
                                                                    <?= htmlspecialchars($inv['notes'], ENT_QUOTES, 'UTF-8') ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="py-3 px-3 text-muted">
                                                            <?= !empty($inv['invoice_date']) ? date('d M Y', strtotime($inv['invoice_date'])) : '—' ?>
                                                        </td>
                                                        <td class="py-3 px-3 text-muted">
                                                            <?= !empty($inv['due_date']) ? date('d M Y', strtotime($inv['due_date'])) : '—' ?>
                                                        </td>
                                                        <td class="py-3 px-3 text-end font-monospace">
                                                            ₹<?= number_format((float)$inv['subtotal'], 2) ?>
                                                        </td>
                                                        <td class="py-3 px-3 text-end text-muted font-monospace" style="font-size: 11px;">
                                                            +₹<?= number_format((float)$inv['tax_amount'], 2) ?><br>
                                                            -₹<?= number_format((float)$inv['discount_amount'], 2) ?>
                                                        </td>
                                                        <td class="py-3 px-3 text-end fw-bold font-monospace text-dark">
                                                            ₹<?= number_format((float)$inv['total_amount'], 2) ?>
                                                        </td>
                                                        <td class="py-3 px-3 text-center">
                                                            <?php
                                                            $pBadge = match($inv['payment_status']) {
                                                                'paid' => 'bg-success-subtle text-success border border-success-subtle',
                                                                'partially_paid' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                                                                'unpaid' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                                                'cancelled' => 'bg-secondary text-white',
                                                                default => 'bg-light text-dark'
                                                            };
                                                            ?>
                                                            <span class="badge <?= $pBadge ?> px-2 py-1" style="font-size: 11px;">
                                                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $inv['payment_status'])), ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                        </td>
                                                        <td class="py-3 px-3 text-end">
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editInvoiceModal<?= (int)$inv['id'] ?>" title="Edit Status">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <?php if ($inv['payment_status'] !== 'paid' && $inv['payment_status'] !== 'cancelled'): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-success ms-1" data-bs-toggle="modal" data-bs-target="#payInvoiceModal<?= (int)$inv['id'] ?>" title="Record Payment">
                                                                    <i class="bi bi-cash-stack"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>

                                                    <!-- Edit Invoice Modal -->
                                                    <div class="modal fade" id="editInvoiceModal<?= (int)$inv['id'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content" style="border-radius: var(--ks-radius-card);">
                                                                <form method="POST" action="/vendor-invoices/<?= (int)$inv['id'] ?>/edit">
                                                                    <input type="hidden" name="vendor_id" value="<?= (int)$vendor['id'] ?>">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title fw-bold" style="font-size: 16px;">Update Invoice #<?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES, 'UTF-8') ?></h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-semibold text-dark">Payment Status</label>
                                                                            <select name="payment_status" class="form-select" style="font-size: 13px;">
                                                                                <option value="unpaid" <?= $inv['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                                                                <option value="partially_paid" <?= $inv['payment_status'] === 'partially_paid' ? 'selected' : '' ?>>Partially Paid</option>
                                                                                <option value="paid" <?= $inv['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                                                                <option value="cancelled" <?= $inv['payment_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-semibold text-dark">Due Date</label>
                                                                            <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($inv['due_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px;">
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-semibold text-dark">Notes / Settlement Reference</label>
                                                                            <textarea name="notes" class="form-control" rows="3" style="font-size: 13px;"><?= htmlspecialchars($inv['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
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

                                                    <!-- Pay Invoice Modal -->
                                                    <div class="modal fade" id="payInvoiceModal<?= (int)$inv['id'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content" style="border-radius: var(--ks-radius-card);">
                                                                <form method="POST" action="/vendor-invoices/<?= (int)$inv['id'] ?>/pay">
                                                                    <input type="hidden" name="vendor_id" value="<?= (int)$vendor['id'] ?>">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title fw-bold" style="font-size: 16px;">Pay Invoice #<?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES, 'UTF-8') ?></h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body" style="font-size: 13px;">
                                                                        <div class="p-3 bg-light rounded mb-3">
                                                                            <div class="d-flex justify-content-between mb-1">
                                                                                <span class="text-muted">Total Invoice Amount:</span>
                                                                                <span class="fw-bold font-monospace text-dark">₹<?= number_format((float)$inv['total_amount'], 2) ?></span>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between">
                                                                                <span class="text-muted">Current Status:</span>
                                                                                <span class="badge <?= $pBadge ?>"><?= ucfirst(str_replace('_', ' ', $inv['payment_status'])) ?></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="row g-2 mb-3">
                                                                            <div class="col-6">
                                                                                <label class="form-label small fw-semibold text-dark">Amount to Pay (₹) <span class="text-danger">*</span></label>
                                                                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= (float)$inv['total_amount'] ?>" required style="font-size: 13px;">
                                                                            </div>
                                                                            <div class="col-6">
                                                                                <label class="form-label small fw-semibold text-dark">Payment Date <span class="text-danger">*</span></label>
                                                                                <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required style="font-size: 13px;">
                                                                            </div>
                                                                        </div>
                                                                        <div class="row g-2 mb-3">
                                                                            <div class="col-6">
                                                                                <label class="form-label small fw-semibold text-dark">Payment Method <span class="text-danger">*</span></label>
                                                                                <select name="payment_method" class="form-select" style="font-size: 13px;">
                                                                                    <option value="Bank Transfer" selected>Bank Transfer</option>
                                                                                    <option value="UPI">UPI</option>
                                                                                    <option value="Cheque">Cheque</option>
                                                                                    <option value="Cash">Cash</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-6">
                                                                                <label class="form-label small fw-semibold text-dark">Txn / UTR Ref</label>
                                                                                <input type="text" name="transaction_reference" class="form-control" placeholder="UTR..." style="font-size: 13px;">
                                                                            </div>
                                                                        </div>
                                                                        <div class="mb-0">
                                                                            <label class="form-label small fw-semibold text-dark">Notes</label>
                                                                            <textarea name="notes" class="form-control" rows="2" placeholder="Settlement remarks..." style="font-size: 13px;"></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-sm btn-success">Record Payment</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-5">
                                                        <i class="bi bi-receipt text-muted" style="font-size: 2.2rem;"></i>
                                                        <p class="text-muted mt-2 mb-1" style="font-size: 13px;">No invoices recorded for this vendor yet.</p>
                                                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#recordInvoiceModal">
                                                            <i class="bi bi-plus-lg"></i> Record First Invoice
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Purchases Pane -->
                            <div class="tab-pane fade" id="purchases-pane" role="tabpanel">
                                <div class="p-3 border-bottom bg-light-subtle text-muted small">
                                    Historical purchase orders issued to this vendor from the procurement ledger.
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                                        <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                                            <tr>
                                                <th class="py-2 px-3 text-muted fw-semibold">PO #</th>
                                                <th class="py-2 px-3 text-muted fw-semibold">Order Date</th>
                                                <th class="py-2 px-3 text-muted fw-semibold">Status</th>
                                                <th class="py-2 px-3 text-muted fw-semibold text-end">Total Amount</th>
                                                <th class="py-2 px-3 text-muted fw-semibold">Delivery Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($purchaseOrders)): ?>
                                                <?php foreach ($purchaseOrders as $po): ?>
                                                    <tr>
                                                        <td class="py-3 px-3 fw-bold text-dark">
                                                            <?= htmlspecialchars($po['po_number'], ENT_QUOTES, 'UTF-8') ?>
                                                        </td>
                                                        <td class="py-3 px-3 text-muted">
                                                            <?= !empty($po['order_date']) ? date('d M Y', strtotime($po['order_date'])) : '—' ?>
                                                        </td>
                                                        <td class="py-3 px-3">
                                                            <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 11px;">
                                                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $po['status'] ?? 'draft')), ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                        </td>
                                                        <td class="py-3 px-3 text-end fw-semibold font-monospace text-dark">
                                                            ₹<?= number_format((float)($po['total_amount'] ?? 0), 2) ?>
                                                        </td>
                                                        <td class="py-3 px-3 text-muted">
                                                            <?= !empty($po['expected_delivery_date']) ? date('d M Y', strtotime($po['expected_delivery_date'])) : '—' ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-5">
                                                        <i class="bi bi-cart-x text-muted" style="font-size: 2.2rem;"></i>
                                                        <p class="text-muted mt-2 mb-0" style="font-size: 13px;">No purchase orders linked to this vendor.</p>
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
            </div>

            <!-- Right Column: Profile, Contacts, Tax & Banking -->
            <div class="col-lg-4">
                <!-- Contact Information -->
                <div class="card mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 14px;">
                            <i class="bi bi-person-lines-fill me-2 text-primary"></i> Contact Information
                        </h6>
                    </div>
                    <div class="card-body p-3" style="font-size: 13px;">
                        <div class="mb-2">
                            <span class="text-muted d-block small">Contact Person</span>
                            <span class="fw-semibold text-dark"><?= !empty($vendor['contact_person']) ? htmlspecialchars($vendor['contact_person'], ENT_QUOTES, 'UTF-8') : '<em class="text-muted fw-normal">Not provided</em>' ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted d-block small">Primary Phone</span>
                            <?php if (!empty($vendor['phone'])): ?>
                                <a href="tel:<?= htmlspecialchars($vendor['phone'], ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none fw-semibold text-primary">
                                    <i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($vendor['phone'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php else: ?>
                                <em class="text-muted">Not provided</em>
                            <?php endif; ?>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted d-block small">Alternate Phone</span>
                            <span class="text-dark"><?= !empty($vendor['alternate_phone']) ? htmlspecialchars($vendor['alternate_phone'], ENT_QUOTES, 'UTF-8') : '<em class="text-muted">None</em>' ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted d-block small">Email Address</span>
                            <?php if (!empty($vendor['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($vendor['email'], ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none text-primary">
                                    <i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($vendor['email'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php else: ?>
                                <em class="text-muted">Not provided</em>
                            <?php endif; ?>
                        </div>
                        <div class="mb-0">
                            <span class="text-muted d-block small">Website</span>
                            <?php if (!empty($vendor['website'])): ?>
                                <a href="<?= htmlspecialchars($vendor['website'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="text-decoration-none text-primary text-truncate d-inline-block" style="max-width: 250px;">
                                    <i class="bi bi-globe me-1"></i> <?= htmlspecialchars($vendor['website'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php else: ?>
                                <em class="text-muted">None</em>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tax & Statutory Identification -->
                <div class="card mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 14px;">
                            <i class="bi bi-file-earmark-text-fill me-2 text-primary"></i> Tax Identifiers
                        </h6>
                    </div>
                    <div class="card-body p-3" style="font-size: 13px;">
                        <div class="mb-3">
                            <span class="text-muted d-block small">GSTIN / Tax ID</span>
                            <?php if (!empty($vendor['gst_number'])): ?>
                                <span class="badge bg-light text-dark border font-monospace px-2 py-1" style="font-size: 12px; letter-spacing: 0.05em;">
                                    <?= htmlspecialchars($vendor['gst_number'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php else: ?>
                                <em class="text-muted">Not registered / Unspecified</em>
                            <?php endif; ?>
                        </div>
                        <div class="mb-0">
                            <span class="text-muted d-block small">PAN Number</span>
                            <?php if (!empty($vendor['pan_number'])): ?>
                                <span class="badge bg-light text-dark border font-monospace px-2 py-1" style="font-size: 12px; letter-spacing: 0.05em;">
                                    <?= htmlspecialchars($vendor['pan_number'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php else: ?>
                                <em class="text-muted">Not provided</em>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Bank Settlement Details -->
                <div class="card mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 14px;">
                            <i class="bi bi-bank2 me-2 text-primary"></i> Bank Account
                        </h6>
                    </div>
                    <div class="card-body p-3" style="font-size: 13px;">
                        <div class="mb-2">
                            <span class="text-muted d-block small">Bank Name</span>
                            <span class="fw-semibold text-dark"><?= !empty($vendor['bank_name']) ? htmlspecialchars($vendor['bank_name'], ENT_QUOTES, 'UTF-8') : '<em class="text-muted fw-normal">Not configured</em>' ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted d-block small">Account Number</span>
                            <span class="font-monospace text-dark"><?= !empty($vendor['bank_account_number']) ? htmlspecialchars($vendor['bank_account_number'], ENT_QUOTES, 'UTF-8') : '<em class="text-muted">Not configured</em>' ?></span>
                        </div>
                        <div class="mb-0">
                            <span class="text-muted d-block small">IFSC / Routing Code</span>
                            <span class="font-monospace text-dark"><?= !empty($vendor['bank_ifsc']) ? htmlspecialchars($vendor['bank_ifsc'], ENT_QUOTES, 'UTF-8') : '<em class="text-muted">Not configured</em>' ?></span>
                        </div>
                    </div>
                </div>

                <!-- Location & Notes -->
                <div class="card mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 14px;">
                            <i class="bi bi-geo-alt-fill me-2 text-primary"></i> Address & Notes
                        </h6>
                    </div>
                    <div class="card-body p-3" style="font-size: 13px;">
                        <div class="mb-3">
                            <span class="text-muted d-block small">Billing / Shipping Address</span>
                            <div class="text-dark">
                                <?php if (!empty($vendor['address_line1']) || !empty($vendor['city'])): ?>
                                    <?= htmlspecialchars($vendor['address_line1'] ?? '', ENT_QUOTES, 'UTF-8') ?><br>
                                    <?php if (!empty($vendor['address_line2'])): ?>
                                        <?= htmlspecialchars($vendor['address_line2'], ENT_QUOTES, 'UTF-8') ?><br>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($vendor['city'] ?? '', ENT_QUOTES, 'UTF-8') ?><?= !empty($vendor['state']) ? ', ' . htmlspecialchars($vendor['state'], ENT_QUOTES, 'UTF-8') : '' ?> <?= htmlspecialchars($vendor['postal_code'] ?? '', ENT_QUOTES, 'UTF-8') ?><br>
                                    <?= htmlspecialchars($vendor['country'] ?? 'India', ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    <em class="text-muted">No address specified</em>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($vendor['notes'])): ?>
                            <div class="mb-0 pt-2 border-top">
                                <span class="text-muted d-block small">Vendor Notes</span>
                                <div class="text-dark" style="white-space: pre-line;"><?= htmlspecialchars($vendor['notes'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Delete Vendor (Soft-delete Guard) -->
                <div class="card border-danger-subtle bg-danger-subtle" style="border-radius: var(--ks-radius-card);">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-danger mb-1" style="font-size: 13px;">Danger Zone</h6>
                        <p class="text-muted small mb-3" style="font-size: 12px;">Soft-delete this vendor profile. Cannot be deleted if unpaid invoices exist.</p>
                        <form method="POST" action="/vendors/<?= (int)$vendor['id'] ?>/delete" onsubmit="return confirm('Are you sure you want to remove this vendor?');">
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100" style="border-radius: var(--ks-radius-button);">
                                <i class="bi bi-trash"></i> Delete Vendor Profile
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Record Invoice Modal -->
        <div class="modal fade" id="recordInvoiceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="border-radius: var(--ks-radius-card);">
                    <form method="POST" action="/vendors/<?= (int)$vendor['id'] ?>/invoices/create" id="recordInvoiceForm">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" style="font-size: 16px;">
                                <i class="bi bi-receipt text-primary me-2"></i> Record Invoice for <?= htmlspecialchars($vendor['company_name'], ENT_QUOTES, 'UTF-8') ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-dark">Invoice Number <span class="text-danger">*</span></label>
                                    <input type="text" name="invoice_number" class="form-control" placeholder="e.g. INV-2026-0091" required style="font-size: 13px;">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-dark">Invoice Date <span class="text-danger">*</span></label>
                                    <input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>" required style="font-size: 13px;">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-dark">Due Date</label>
                                    <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" style="font-size: 13px;">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-dark">Subtotal (₹) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" name="subtotal" id="invSubtotal" class="form-control font-monospace" placeholder="0.00" required style="font-size: 13px;" oninput="recalcInvoiceTotal()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-dark">Tax Amount (₹)</label>
                                    <input type="number" step="0.01" min="0" name="tax_amount" id="invTax" class="form-control font-monospace" value="0.00" style="font-size: 13px;" oninput="recalcInvoiceTotal()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-dark">Discount Amount (₹)</label>
                                    <input type="number" step="0.01" min="0" name="discount_amount" id="invDiscount" class="form-control font-monospace" value="0.00" style="font-size: 13px;" oninput="recalcInvoiceTotal()">
                                </div>
                            </div>

                            <div class="p-3 mb-3 bg-light rounded d-flex justify-content-between align-items-center">
                                <span class="fw-semibold text-dark small">Calculated Total Amount:</span>
                                <span class="h4 fw-bold mb-0 text-primary font-monospace" id="invTotalDisplay">₹0.00</span>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-dark">Payment Status</label>
                                    <select name="payment_status" class="form-select" style="font-size: 13px;">
                                        <option value="unpaid" selected>Unpaid (Pending Settlement)</option>
                                        <option value="partially_paid">Partially Paid</option>
                                        <option value="paid">Paid (Fully Cleared)</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-dark">Notes / Description</label>
                                    <input type="text" name="notes" class="form-control" placeholder="e.g. Sports uniforms batch delivery billing" style="font-size: 13px;">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue);">Record Invoice</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Status Change Modal -->
        <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: var(--ks-radius-card);">
                    <form method="POST" action="/vendors/<?= (int)$vendor['id'] ?>/status">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" style="font-size: 16px;">Update Vendor Status</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-dark">Status</label>
                                <select name="status" class="form-select" style="font-size: 13px;">
                                    <option value="active" <?= $vendor['status'] === 'active' ? 'selected' : '' ?>>Active (Approved for procurement)</option>
                                    <option value="inactive" <?= $vendor['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Temporarily suspended)</option>
                                    <option value="blacklisted" <?= $vendor['status'] === 'blacklisted' ? 'selected' : '' ?>>Blacklisted (Barred from purchase orders)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-dark">Reason / Remarks</label>
                                <textarea name="reason" class="form-control" rows="3" placeholder="Optional justification for audit trail..." style="font-size: 13px;"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue);">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function recalcInvoiceTotal() {
                const subtotal = parseFloat(document.getElementById('invSubtotal').value) || 0;
                const tax = parseFloat(document.getElementById('invTax').value) || 0;
                const disc = parseFloat(document.getElementById('invDiscount').value) || 0;
                const total = Math.max(0, subtotal + tax - disc);
                document.getElementById('invTotalDisplay').textContent = '₹' + total.toFixed(2);
            }
        </script>
    <?php endif; ?>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
