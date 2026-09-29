<?php
$pageTitle = 'Vendors & Suppliers — KhelSutra';
$activePage = 'vendors';
$orgId = current_organization_id();

$vendorService = new \App\Services\Vendor\VendorService();
$page = (int)($_GET['page'] ?? 1);
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$vendorType = trim($_GET['vendor_type'] ?? '');

$result = $vendorService->listVendors($orgId, $page, 15, $search ?: null, $status ?: null, $vendorType ?: null);
$vendors = $result['data'] ?? [];
$total = $result['total'] ?? 0;
$totalPages = $result['total_pages'] ?? 1;

$db = \App\Services\BaseService::getDatabaseConnection();

// KPI Stats
$statStmt = $db->prepare("
    SELECT 
        COUNT(*) as total_vendors,
        SUM(CASE WHEN v.status = 'active' THEN 1 ELSE 0 END) as active_vendors,
        (SELECT COALESCE(SUM(vi.total_amount), 0) FROM vendor_invoices vi WHERE vi.organization_id = :org_id) as total_invoiced,
        (SELECT COUNT(*) FROM vendor_invoices vi WHERE vi.organization_id = :org_id AND vi.payment_status = 'unpaid') as unpaid_invoices_count
    FROM vendors v
    WHERE v.organization_id = :org_id AND v.deleted_at IS NULL
");
$statStmt->execute([':org_id' => $orgId]);
$stats = $statStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_vendors' => 0, 'active_vendors' => 0, 'total_invoiced' => 0, 'unpaid_invoices_count' => 0];

// Distinct vendor types for filter dropdown
$typesStmt = $db->prepare("
    SELECT DISTINCT vendor_type 
    FROM vendors 
    WHERE organization_id = :org_id AND vendor_type IS NOT NULL AND vendor_type != '' AND deleted_at IS NULL
    ORDER BY vendor_type ASC
");
$typesStmt->execute([':org_id' => $orgId]);
$vendorTypes = $typesStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

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

    <!-- Page Header Standard -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/inventory" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Inventory</a>
                <span class="text-muted small">/</span>
                <span class="text-dark small fw-semibold">Vendors</span>
            </div>
            <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Vendors & Suppliers</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="/inventory" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 16px;">
                <i class="bi bi-boxes"></i> Stock Inventory
            </a>
            <a href="/vendors/create" class="btn btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 18px;">
                <i class="bi bi-plus-lg"></i> Add Vendor
            </a>
        </div>
    </div>

    <!-- Quick Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Total Vendors</span>
                    <span class="badge bg-light text-primary border"><i class="bi bi-truck"></i></span>
                </div>
                <div class="h3 fw-bold mb-0" style="color: var(--ks-navy);"><?= number_format($stats['total_vendors'] ?? 0) ?></div>
                <span class="text-muted" style="font-size: 12px;">Registered supplier partners</span>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Active Suppliers</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check2-circle"></i></span>
                </div>
                <div class="h3 fw-bold mb-0 text-success"><?= number_format($stats['active_vendors'] ?? 0) ?></div>
                <span class="text-muted" style="font-size: 12px;">Authorized for procurement</span>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Total Invoiced</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-receipt"></i></span>
                </div>
                <div class="h3 fw-bold mb-0" style="color: var(--ks-navy);">₹<?= number_format((float)($stats['total_invoiced'] ?? 0), 2) ?></div>
                <span class="text-muted" style="font-size: 12px;">Lifetime vendor billing</span>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Unpaid Invoices</span>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-clock-history"></i></span>
                </div>
                <div class="h3 fw-bold mb-0 text-warning"><?= number_format($stats['unpaid_invoices_count'] ?? 0) ?></div>
                <span class="text-muted" style="font-size: 12px;">Pending payment settlement</span>
            </div>
        </div>
    </div>

    <!-- Search & Filters Toolbar -->
    <div class="card p-3 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
        <form method="GET" action="/vendors" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button) 0 0 var(--ks-radius-button);">
                        <i class="bi bi-search text-muted" style="font-size: 13px;"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search company name, code, contact person, city, GSTIN..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" style="border-color: var(--ks-border); border-radius: 0 var(--ks-radius-button) var(--ks-radius-button) 0; font-size: 13px;">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button); font-size: 13px;">
                    <option value="">All Vendor Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="blacklisted" <?= $status === 'blacklisted' ? 'selected' : '' ?>>Blacklisted</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="vendor_type" class="form-select" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button); font-size: 13px;">
                    <option value="">All Categories</option>
                    <?php foreach ($vendorTypes as $vt): ?>
                        <option value="<?= htmlspecialchars($vt, ENT_QUOTES, 'UTF-8') ?>" <?= $vendorType === $vt ? 'selected' : '' ?>>
                            <?= htmlspecialchars($vt, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 500; font-size: 13px;">
                    Filter
                </button>
                <?php if ($search || $status || $vendorType): ?>
                    <a href="/vendors" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;" title="Reset filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Vendors Table -->
    <div class="card" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff; overflow: hidden;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                    <tr>
                        <th class="py-3 px-3 text-muted fw-semibold" style="width: 270px;">Company / Vendor</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Contact Person</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Phone & Email</th>
                        <th class="py-3 px-3 text-muted fw-semibold">City & State</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Invoices & Billing</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Status</th>
                        <th class="py-3 px-3 text-muted fw-semibold text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($vendors)): ?>
                        <?php foreach ($vendors as $v): ?>
                            <tr>
                                <td class="py-3 px-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-light p-2 rounded text-primary border d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                            <i class="bi bi-building"></i>
                                        </div>
                                        <div>
                                            <a href="/vendors/<?= (int)$v['id'] ?>" class="fw-semibold text-dark text-decoration-none">
                                                <?= htmlspecialchars($v['company_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <div class="text-muted" style="font-size: 11px;">
                                                Code: <span class="fw-medium text-dark"><?= htmlspecialchars($v['vendor_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if (!empty($v['vendor_type'])): ?>
                                                    &bull; <span class="badge bg-light text-secondary border"><?= htmlspecialchars($v['vendor_type'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="text-dark fw-medium"><?= htmlspecialchars($v['contact_person'] ?: 'N/A', ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($v['gst_number'])): ?>
                                        <div class="text-muted" style="font-size: 11px;">GST: <?= htmlspecialchars($v['gst_number'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-muted">
                                    <?php if (!empty($v['phone'])): ?>
                                        <div><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($v['phone'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($v['email'])): ?>
                                        <div style="font-size: 11px;"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($v['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (empty($v['phone']) && empty($v['email'])): ?>
                                        <span class="fst-italic">Not provided</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-muted">
                                    <i class="bi bi-geo-alt me-1 text-secondary"></i>
                                    <?= htmlspecialchars(trim(($v['city'] ?? '') . ', ' . ($v['state'] ?? '')) ?: 'India', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="fw-semibold text-dark">₹<?= number_format((float)($v['total_invoiced_amount'] ?? 0), 2) ?></div>
                                    <div class="text-muted" style="font-size: 11px;">
                                        <?= (int)($v['total_invoices_count'] ?? 0) ?> invoice(s)
                                        <?php if ((int)($v['unpaid_invoices_count'] ?? 0) > 0): ?>
                                            &bull; <span class="text-warning fw-semibold"><?= (int)$v['unpaid_invoices_count'] ?> unpaid</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <?php
                                    $vBadge = match($v['status']) {
                                        'active' => 'bg-success-subtle text-success border border-success-subtle',
                                        'inactive' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                        'blacklisted' => 'bg-danger text-white',
                                        default => 'bg-light text-dark border'
                                    };
                                    ?>
                                    <span class="badge <?= $vBadge ?> fw-semibold" style="font-size: 11px;">
                                        <?= htmlspecialchars(ucfirst($v['status']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="/vendors/<?= (int)$v['id'] ?>" class="btn btn-sm btn-light border" style="font-size: 12px;" title="View Profile & Invoices">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="/vendors/<?= (int)$v['id'] ?>/edit" class="btn btn-sm btn-light border" style="font-size: 12px;" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <!-- Status Toggle Form -->
                                        <form method="POST" action="/vendors/<?= (int)$v['id'] ?>/status" style="display:inline;">
                                            <?php if ($v['status'] === 'active'): ?>
                                                <input type="hidden" name="status" value="inactive">
                                                <button type="submit" class="btn btn-sm btn-light border text-warning" style="font-size: 12px;" title="Deactivate Vendor">
                                                    <i class="bi bi-pause-circle"></i>
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="status" value="active">
                                                <button type="submit" class="btn btn-sm btn-light border text-success" style="font-size: 12px;" title="Activate Vendor">
                                                    <i class="bi bi-play-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <form method="POST" action="/vendors/<?= (int)$v['id'] ?>/delete" onsubmit="return confirm('Are you sure you want to remove this vendor?');" style="display:inline;">
                                            <button type="submit" class="btn btn-sm btn-light border text-danger" style="font-size: 12px;" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-truck d-block fs-1 mb-2 opacity-50"></i>
                                <p class="mb-2 fw-medium">No vendors found matching your criteria.</p>
                                <p class="text-muted small mb-3">Add equipment suppliers, merchandise vendors, and service contractors to manage procurement.</p>
                                <a href="/vendors/create" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); font-size: 12px;">
                                    <i class="bi bi-plus-lg me-1"></i> Add First Vendor
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="d-flex align-items-center justify-content-between p-3 border-top" style="font-size: 13px;">
                <span class="text-muted">Showing page <?= $page ?> of <?= $totalPages ?> (<?= $total ?> items)</span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&vendor_type=<?= urlencode($vendorType) ?>">&laquo;</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&vendor_type=<?= urlencode($vendorType) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&vendor_type=<?= urlencode($vendorType) ?>">&raquo;</a>
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
