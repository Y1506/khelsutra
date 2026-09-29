<?php
$pageTitle = 'Inventory — KhelSutra';
$activePage = 'inventory';
$orgId = current_organization_id();

$invService = new \App\Services\Inventory\InventoryService();
$page = (int)($_GET['page'] ?? 1);
$search = trim($_GET['search'] ?? '');
$catId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$status = trim($_GET['status'] ?? '');

$result = $invService->listItems($orgId, $page, 15, $search ?: null, $catId, $status ?: null);
$items = $result['data'] ?? [];
$total = $result['total'] ?? 0;
$totalPages = $result['total_pages'] ?? 1;

$db = \App\Services\BaseService::getDatabaseConnection();
$catStmt = $db->prepare("SELECT id, name FROM inventory_categories WHERE organization_id = :org_id AND status = 'active' AND deleted_at IS NULL ORDER BY name ASC");
$catStmt->execute([':org_id' => $orgId]);
$categories = $catStmt ? $catStmt->fetchAll(PDO::FETCH_ASSOC) : [];

ob_start();
?>

<div class="ks-content">
    <!-- Clean Page Header Standard -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Inventory</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="/equipment" class="btn btn-outline-primary d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 16px;">
                <i class="bi bi-tag-fill"></i> Tracked Equipment
            </a>
            <a href="/vendors" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 16px;">
                <i class="bi bi-truck"></i> Vendors
            </a>
            <a href="/inventory/categories" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 16px;">
                <i class="bi bi-tags"></i> Manage Categories
            </a>
            <a href="/inventory/create" class="btn btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 18px;">
                <i class="bi bi-plus-lg"></i> Add Item
            </a>
        </div>
    </div>

    <!-- Search & Filters Toolbar -->
    <div class="card p-3 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
        <form method="GET" action="/inventory" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button) 0 0 var(--ks-radius-button);">
                        <i class="bi bi-search text-muted" style="font-size: 13px;"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search item name, code, storage location..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" style="border-color: var(--ks-border); border-radius: 0 var(--ks-radius-button) var(--ks-radius-button) 0; font-size: 13px;">
                </div>
            </div>
            <div class="col-md-3">
                <select name="category_id" class="form-select" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button); font-size: 13px;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $catId == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button); font-size: 13px;">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="low_stock" <?= $status === 'low_stock' ? 'selected' : '' ?>>Low Stock Alert</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 500; font-size: 13px;">
                    Filter
                </button>
                <?php if ($search || $catId || $status): ?>
                    <a href="/inventory" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;" title="Reset filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Inventory Table -->
    <div class="card" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff; overflow: hidden;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                    <tr>
                        <th class="py-3 px-3 text-muted fw-semibold" style="width: 270px;">Item / Equipment</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Category</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Current Stock</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Reorder Threshold</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Location</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Status</th>
                        <th class="py-3 px-3 text-muted fw-semibold text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="py-3 px-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 38px; height: 38px; border-radius: 8px; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                                            <i class="bi bi-box-seam"></i>
                                        </div>
                                        <div>
                                            <a href="/inventory/<?= (int)$item['id'] ?>" class="fw-semibold text-decoration-none text-dark d-block">
                                                <?= htmlspecialchars($item['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted small" style="font-family: monospace; font-size: 11px;"><?= htmlspecialchars($item['item_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-dark">
                                    <?= htmlspecialchars($item['category_name'] ?? 'General Equipment', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?php if (!empty($item['is_low_stock'])): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fw-bold">
                                            <?= (float)($item['quantity'] ?? 0) ?> <?= htmlspecialchars($item['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?> (Low Stock)
                                        </span>
                                    <?php else: ?>
                                        <span class="fw-bold text-dark">
                                            <?= (float)($item['quantity'] ?? 0) ?> <?= htmlspecialchars($item['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-muted small">
                                    Min: <?= (float)($item['minimum_stock_level'] ?? 0) ?> &bull; Reorder: <?= (float)($item['reorder_level'] ?? 0) ?>
                                </td>
                                <td class="py-3 px-3 text-dark small">
                                    <?= htmlspecialchars($item['location_name'] ?? 'Store Room', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="badge <?= ($item['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-secondary' ?>" style="border-radius: 12px; font-size: 11px; padding: 4px 10px; text-transform: capitalize;">
                                        <?= htmlspecialchars($item['status'] ?? 'active', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/inventory/<?= (int)$item['id'] ?>" class="btn btn-outline-secondary" style="border-radius: 6px 0 0 6px;" title="View Details & Movement">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="/inventory/<?= (int)$item['id'] ?>/edit" class="btn btn-outline-secondary" style="border-radius: 0;" title="Edit Item">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="/inventory/<?= (int)$item['id'] ?>/delete" method="POST" style="display: contents;" class="d-inline" onsubmit="return confirm('Are you sure you want to delete \'<?= addslashes(htmlspecialchars($item['item_name'])) ?>\'?');">
                                            <button type="submit" class="btn btn-outline-danger" style="border-radius: 0 6px 6px 0; border-left: 0; padding: 4px 8px; font-size: 12px;" title="Delete Item">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted mb-2"><i class="bi bi-box-seam fs-2"></i></div>
                                <h6 class="fw-bold" style="color: var(--ks-navy);">No inventory items found</h6>
                                <p class="text-muted small mb-3">No gear or equipment matches your current filters.</p>
                                <a href="/inventory/create" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 500;">
                                    <i class="bi bi-plus-lg me-1"></i> Add Item
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total > 0): ?>
            <div class="card-footer d-flex align-items-center justify-content-between py-3 px-3 bg-white" style="border-top: 1px solid var(--ks-border);">
                <div class="text-muted small">
                    Showing <strong><?= count($items) ?></strong> of <strong><?= (int)$total ?></strong> items
                </div>
                <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category_id=<?= $catId ?>&status=<?= urlencode($status) ?>">Previous</a>
                            </li>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&category_id=<?= $catId ?>&status=<?= urlencode($status) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category_id=<?= $catId ?>&status=<?= urlencode($status) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
