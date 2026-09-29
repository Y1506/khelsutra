<?php
$pageTitle = 'Procurement & Purchasing — KhelSutra';
$activePage = 'purchases';
$orgId = current_organization_id();

$purchaseService = new \App\Services\Purchase\PurchaseService();
$activeTab = trim($_GET['tab'] ?? 'orders');

$db = \App\Services\BaseService::getDatabaseConnection();

// KPI Stats
$statsStmt = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM purchase_orders WHERE organization_id = :org_id) as total_pos,
        (SELECT COALESCE(SUM(total_amount), 0) FROM purchase_orders WHERE organization_id = :org_id AND status != 'cancelled') as total_spend,
        (SELECT COUNT(*) FROM purchase_requests WHERE organization_id = :org_id) as total_prs,
        (SELECT COUNT(*) FROM purchase_requests WHERE organization_id = :org_id AND status = 'submitted') as pending_prs,
        (SELECT COUNT(*) FROM goods_receipts WHERE organization_id = :org_id) as total_grns
");
$statsStmt->execute([':org_id' => $orgId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_pos' => 0, 'total_spend' => 0, 'total_prs' => 0, 'pending_prs' => 0, 'total_grns' => 0
];

// Fetch data according to tab
$page = (int)($_GET['page'] ?? 1);
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$orders = [];
$requests = [];
$receipts = [];
$totalPages = 1;
$total = 0;

if ($activeTab === 'requests') {
    $res = $purchaseService->listPurchaseRequests($orgId, $page, 15, $search ?: null, $statusFilter ?: null);
    $requests = $res['data'];
    $total = $res['total'];
    $totalPages = $res['total_pages'];
} elseif ($activeTab === 'receipts') {
    $res = $purchaseService->listGoodsReceipts($orgId, $page, 15);
    $receipts = $res['data'];
    $total = $res['total'];
    $totalPages = $res['total_pages'];
} else {
    $activeTab = 'orders';
    $res = $purchaseService->listPurchaseOrders($orgId, $page, 15, $search ?: null, $statusFilter ?: null);
    $orders = $res['data'];
    $total = $res['total'];
    $totalPages = $res['total_pages'];
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

    <!-- Clean Header Standard -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Procurement & Purchasing</h1>
            <p class="text-muted small mb-0">Manage purchase requisitions, orders, vendor disbursements, and goods receipt notes.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/vendors" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-size: 13px; padding: 9px 16px;">
                <i class="bi bi-truck"></i> Vendors Directory
            </a>
            <a href="/purchases/requests/create" class="btn btn-outline-primary d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 16px;">
                <i class="bi bi-file-earmark-plus"></i> New Request
            </a>
            <a href="/purchases/orders/create" class="btn btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 18px;">
                <i class="bi bi-cart-plus"></i> New Purchase Order
            </a>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card p-3 h-100" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <span class="text-muted small fw-medium">Total PO Commitment</span>
                <h3 class="fw-bold mt-1 mb-0" style="color: var(--ks-navy);">₹<?= number_format((float)$stats['total_spend'], 2) ?></h3>
                <span class="text-muted" style="font-size: 11px;"><?= (int)$stats['total_pos'] ?> purchase orders issued</span>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card p-3 h-100" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <span class="text-muted small fw-medium">Purchase Requests</span>
                <h3 class="fw-bold mt-1 mb-0" style="color: var(--ks-navy);"><?= (int)$stats['total_prs'] ?></h3>
                <span class="text-muted" style="font-size: 11px;">Internal requisition requests</span>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card p-3 h-100" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <span class="text-muted small fw-medium">Pending PR Approval</span>
                <h3 class="fw-bold mt-1 mb-0 <?= (int)$stats['pending_prs'] > 0 ? 'text-warning-emphasis' : 'text-success' ?>">
                    <?= (int)$stats['pending_prs'] ?>
                </h3>
                <span class="text-muted" style="font-size: 11px;">Awaiting administrative sign-off</span>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card p-3 h-100" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <span class="text-muted small fw-medium">Goods Receipts (GRN)</span>
                <h3 class="fw-bold mt-1 mb-0 text-success"><?= (int)$stats['total_grns'] ?></h3>
                <span class="text-muted" style="font-size: 11px;">Processed warehouse receipts</span>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="card mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
        <div class="card-header bg-white border-bottom p-3">
            <ul class="nav nav-pills card-header-pills">
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'orders' ? 'active' : '' ?> fw-semibold small" href="/purchases?tab=orders">
                        <i class="bi bi-cart-check me-1"></i> Purchase Orders (<?= (int)$stats['total_pos'] ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'requests' ? 'active' : '' ?> fw-semibold small" href="/purchases?tab=requests">
                        <i class="bi bi-file-earmark-text me-1"></i> Purchase Requests (<?= (int)$stats['total_prs'] ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'receipts' ? 'active' : '' ?> fw-semibold small" href="/purchases?tab=receipts">
                        <i class="bi bi-box-arrow-in-down me-1"></i> Goods Receipts (<?= (int)$stats['total_grns'] ?>)
                    </a>
                </li>
            </ul>
        </div>

        <?php if ($activeTab !== 'receipts'): ?>
            <!-- Filter Toolbar -->
            <div class="p-3 border-bottom bg-light-subtle">
                <form method="GET" action="/purchases" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0" style="border-color: var(--ks-border); font-size: 13px;">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Search reference, supplier, notes..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" style="border-color: var(--ks-border); font-size: 13px;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select" style="border-color: var(--ks-border); font-size: 13px;">
                            <option value="">All Statuses</option>
                            <?php if ($activeTab === 'requests'): ?>
                                <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="submitted" <?= $statusFilter === 'submitted' ? 'selected' : '' ?>>Submitted (Pending)</option>
                                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                <option value="converted" <?= $statusFilter === 'converted' ? 'selected' : '' ?>>Converted to PO</option>
                                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <?php else: ?>
                                <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : '' ?>>Sent to Vendor</option>
                                <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="partially_received" <?= $statusFilter === 'partially_received' ? 'selected' : '' ?>>Partially Received</option>
                                <option value="received" <?= $statusFilter === 'received' ? 'selected' : '' ?>>Fully Received</option>
                                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1" style="background: var(--ks-blue); border-color: var(--ks-blue); font-size: 13px;">Filter</button>
                        <?php if ($search || $statusFilter): ?>
                            <a href="/purchases?tab=<?= urlencode($activeTab) ?>" class="btn btn-outline-secondary" style="font-size: 13px;"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Table View by Active Tab -->
        <div class="table-responsive">
            <?php if ($activeTab === 'orders'): ?>
                <!-- Purchase Orders Table -->
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                        <tr>
                            <th class="py-3 px-3 text-muted fw-semibold">PO Number</th>
                            <th class="py-3 px-3 text-muted fw-semibold">Vendor / Supplier</th>
                            <th class="py-3 px-3 text-muted fw-semibold">Order Date</th>
                            <th class="py-3 px-3 text-muted fw-semibold">Delivery Date</th>
                            <th class="py-3 px-3 text-muted fw-semibold">Items</th>
                            <th class="py-3 px-3 text-muted fw-semibold text-end">Total Amount</th>
                            <th class="py-3 px-3 text-muted fw-semibold text-center">Status</th>
                            <th class="py-3 px-3 text-muted fw-semibold text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $po): ?>
                                <tr>
                                    <td class="py-3 px-3">
                                        <a href="/purchases/orders/<?= (int)$po['id'] ?>" class="fw-bold text-decoration-none text-primary font-monospace">
                                            <?= htmlspecialchars($po['po_number'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <?php if (!empty($po['request_reference'])): ?>
                                            <div class="text-muted" style="font-size: 11px;">via <?= htmlspecialchars($po['request_reference'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="fw-semibold text-dark"><?= htmlspecialchars($po['vendor_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($po['vendor_code'])): ?>
                                            <span class="text-muted" style="font-size: 11px;">(<?= htmlspecialchars($po['vendor_code'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-muted">
                                        <?= !empty($po['order_date']) ? date('d M Y', strtotime($po['order_date'])) : '—' ?>
                                    </td>
                                    <td class="py-3 px-3 text-muted">
                                        <?= !empty($po['expected_delivery_date']) ? date('d M Y', strtotime($po['expected_delivery_date'])) : '—' ?>
                                    </td>
                                    <td class="py-3 px-3 text-muted">
                                        <?= (int)$po['item_count'] ?> item(s)
                                    </td>
                                    <td class="py-3 px-3 text-end fw-bold font-monospace text-dark">
                                        ₹<?= number_format((float)$po['total_amount'], 2) ?>
                                    </td>
                                    <td class="py-3 px-3 text-center">
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
                                        <span class="badge <?= $sBadge ?> px-2 py-1" style="font-size: 11px;">
                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $po['status'])), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-end">
                                        <a href="/purchases/orders/<?= (int)$po['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View Order Details">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-cart-x text-muted" style="font-size: 2.2rem;"></i>
                                    <p class="text-muted mt-2 mb-1" style="font-size: 13px;">No purchase orders found matching the filter criteria.</p>
                                    <a href="/purchases/orders/create" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="bi bi-plus-lg"></i> Issue First Purchase Order
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($activeTab === 'requests'): ?>
                <!-- Purchase Requests Table -->
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                        <tr>
                            <th class="py-3 px-3 text-muted fw-semibold">Request Reference</th>
                            <th class="py-3 px-3 text-muted fw-semibold">Requested By</th>
                            <th class="py-3 px-3 text-muted fw-semibold">Request Date</th>
                            <th class="py-3 px-3 text-muted fw-semibold">Items</th>
                            <th class="py-3 px-3 text-muted fw-semibold text-end">Est. Total Cost</th>
                            <th class="py-3 px-3 text-muted fw-semibold text-center">Status</th>
                            <th class="py-3 px-3 text-muted fw-semibold text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($requests)): ?>
                            <?php foreach ($requests as $pr): ?>
                                <tr>
                                    <td class="py-3 px-3">
                                        <a href="/purchases/requests/<?= (int)$pr['id'] ?>" class="fw-bold text-decoration-none text-primary font-monospace">
                                            <?= htmlspecialchars($pr['request_reference'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <?php if (!empty($pr['purpose'])): ?>
                                            <div class="text-muted text-truncate" style="font-size: 11px; max-width: 250px;" title="<?= htmlspecialchars($pr['purpose'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($pr['purpose'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-dark">
                                        <?= htmlspecialchars($pr['requester_name'] ?? 'Staff', ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-3 px-3 text-muted">
                                        <?= !empty($pr['request_date']) ? date('d M Y', strtotime($pr['request_date'])) : '—' ?>
                                    </td>
                                    <td class="py-3 px-3 text-muted">
                                        <?= (int)$pr['item_count'] ?> item(s)
                                    </td>
                                    <td class="py-3 px-3 text-end fw-semibold font-monospace text-dark">
                                        ₹<?= number_format((float)$pr['total_estimated_cost'], 2) ?>
                                    </td>
                                    <td class="py-3 px-3 text-center">
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
                                        <span class="badge <?= $rBadge ?> px-2 py-1" style="font-size: 11px;">
                                            <?= htmlspecialchars(ucfirst($pr['status']), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-end">
                                        <a href="/purchases/requests/<?= (int)$pr['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-file-earmark-x text-muted" style="font-size: 2.2rem;"></i>
                                    <p class="text-muted mt-2 mb-1" style="font-size: 13px;">No purchase requests found.</p>
                                    <a href="/purchases/requests/create" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="bi bi-plus-lg"></i> Create First Purchase Request
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($activeTab === 'receipts'): ?>
                <!-- Goods Receipts Table -->
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                        <tr>
                            <th class="py-3 px-3 text-muted fw-semibold">Receipt Number (GRN)</th>
                            <th class="py-3 px-3 text-muted fw-semibold">Purchase Order #</th>
                            <th class="py-3 px-3 text-muted fw-semibold">Vendor / Supplier</th>
                            <th class="py-3 px-3 text-muted fw-semibold">Receipt Date</th>
                            <th class="py-3 px-3 text-muted fw-semibold">Received By</th>
                            <th class="py-3 px-3 text-muted fw-semibold">Remarks</th>
                            <th class="py-3 px-3 text-muted fw-semibold text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($receipts)): ?>
                            <?php foreach ($receipts as $gr): ?>
                                <tr>
                                    <td class="py-3 px-3">
                                        <a href="/purchases/receipts/<?= (int)$gr['id'] ?>" class="fw-bold text-decoration-none text-success font-monospace">
                                            <?= htmlspecialchars($gr['receipt_number'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                    <td class="py-3 px-3">
                                        <a href="/purchases/orders/<?= (int)$gr['purchase_order_id'] ?>" class="text-decoration-none font-monospace text-primary">
                                            <?= htmlspecialchars($gr['po_number'] ?? 'PO', ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                    <td class="py-3 px-3 text-dark">
                                        <?= htmlspecialchars($gr['vendor_name'] ?? 'Supplier', ENT_QUOTES, 'UTF-8') ?>
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
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-box-seam text-muted" style="font-size: 2.2rem;"></i>
                                    <p class="text-muted mt-2 mb-0" style="font-size: 13px;">No goods receipts processed yet.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top p-3 d-flex justify-content-between align-items-center">
                <span class="text-muted small">Showing Page <?= $page ?> of <?= $totalPages ?> (<?= $total ?> items)</span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?tab=<?= urlencode($activeTab) ?>&page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">&laquo;</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?tab=<?= urlencode($activeTab) ?>&page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?tab=<?= urlencode($activeTab) ?>&page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
