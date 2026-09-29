<?php
$pageTitle = 'Inventory Categories — KhelSutra';
$activePage = 'inventory';
$orgId = current_organization_id();

$catService = new \App\Services\Inventory\InventoryCategoryService();
$statusFilter = trim($_GET['status'] ?? '');
$categories = $catService->listCategories($orgId, $statusFilter ?: null);

ob_start();
?>

<div class="ks-content">
    <!-- Top Back Navigation -->
    <div class="mb-3">
        <a href="/inventory" class="text-decoration-none text-muted small fw-medium">
            <i class="bi bi-arrow-left me-1"></i> Back to Inventory
        </a>
    </div>

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Inventory Categories</h1>
            <p class="text-muted small mb-0 mt-1">Classification tiers for sports gear, training equipment, apparel, and consumables.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" onclick="openCreateModal()" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 18px;">
                <i class="bi bi-plus-lg"></i> Add Category
            </button>
        </div>
    </div>

    <!-- Flash Alerts -->
    <?php if (!empty($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4 py-2 px-3 small d-flex align-items-center gap-2" role="alert" style="border-radius: var(--ks-radius-button);">
            <i class="bi bi-check-circle-fill text-success fs-6"></i>
            <div><?= htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') ?></div>
            <button type="button" class="btn-close small p-2" data-bs-dismiss="alert" aria-label="Close" style="top: 50%; transform: translateY(-50%);"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 py-2 px-3 small d-flex align-items-center gap-2" role="alert" style="border-radius: var(--ks-radius-button);">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-6"></i>
            <div><?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
            <button type="button" class="btn-close small p-2" data-bs-dismiss="alert" aria-label="Close" style="top: 50%; transform: translateY(-50%);"></button>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="card p-3 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
        <form method="GET" action="/inventory/categories" class="row g-2 align-items-center">
            <div class="col-md-4">
                <select name="status" class="form-select" onchange="this.form.submit()" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button); font-size: 13px;">
                    <option value="">All Statuses (<?= count($catService->listCategories($orgId)) ?> total)</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </div>
            <?php if ($statusFilter): ?>
                <div class="col-md-2">
                    <a href="/inventory/categories" class="btn btn-outline-secondary btn-sm" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                        <i class="bi bi-x-lg me-1"></i> Clear Filter
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Categories Table Card -->
    <div class="card" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff; overflow: hidden;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                    <tr>
                        <th class="py-3 px-3 text-muted fw-semibold" style="width: 250px;">Category Name</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Description</th>
                        <th class="py-3 px-3 text-muted fw-semibold text-center" style="width: 140px;">Items Linked</th>
                        <th class="py-3 px-3 text-muted fw-semibold text-center" style="width: 120px;">Status</th>
                        <th class="py-3 px-3 text-muted fw-semibold text-end" style="width: 200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td class="py-3 px-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 34px; height: 34px; border-radius: 8px; background: #EEF2FF; color: var(--ks-blue); display: flex; align-items: center; justify-content: center; font-size: 15px;">
                                            <i class="bi bi-tag-fill"></i>
                                        </div>
                                        <div>
                                            <span class="fw-semibold text-dark d-block">
                                                <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <span class="text-muted small" style="font-size: 11px;">
                                                ID #<?= (int)$cat['id'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-muted">
                                    <?= htmlspecialchars($cat['description'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <?php $itemCount = (int)($cat['item_count'] ?? 0); ?>
                                    <?php if ($itemCount > 0): ?>
                                        <a href="/inventory?category_id=<?= (int)$cat['id'] ?>" class="badge bg-light text-dark border text-decoration-none" title="View items in this category" style="font-size: 11px; padding: 5px 9px;">
                                            <i class="bi bi-box me-1"></i> <?= $itemCount ?> <?= $itemCount === 1 ? 'item' : 'items' ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border" style="font-size: 11px; padding: 5px 9px;">
                                            0 items
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <?php if (($cat['status'] ?? 'active') === 'active'): ?>
                                        <span class="badge badge-success" style="border-radius: 12px; font-size: 11px; padding: 4px 10px;">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary" style="border-radius: 12px; font-size: 11px; padding: 4px 10px;">
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- Edit Button -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick='openEditModal(<?= json_encode($cat, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Category" style="border-radius: 6px; font-size: 12px;">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <!-- Toggle Status Form -->
                                        <form method="POST" action="/inventory/categories/<?= (int)$cat['id'] ?>/status" class="d-inline">
                                            <input type="hidden" name="status" value="<?= ($cat['status'] ?? 'active') === 'active' ? 'inactive' : 'active' ?>">
                                            <button type="submit" class="btn btn-sm <?= ($cat['status'] ?? 'active') === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="<?= ($cat['status'] ?? 'active') === 'active' ? 'Deactivate Category' : 'Activate Category' ?>" style="border-radius: 6px; font-size: 12px;">
                                                <i class="bi <?= ($cat['status'] ?? 'active') === 'active' ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                            </button>
                                        </form>

                                        <!-- Delete Form -->
                                        <?php if ($itemCount > 0): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger disabled" title="Cannot delete: <?= $itemCount ?> item(s) currently linked to this category" style="border-radius: 6px; font-size: 12px; opacity: 0.45; cursor: not-allowed;">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" action="/inventory/categories/<?= (int)$cat['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Are you sure you want to delete category \'<?= addslashes(htmlspecialchars($cat['name'])) ?>\'?');">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Category" style="border-radius: 6px; font-size: 12px;">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-5 text-center text-muted">
                                <i class="bi bi-tags text-muted d-block mb-2" style="font-size: 32px; opacity: 0.5;"></i>
                                <span class="fw-semibold">No inventory categories found.</span>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openCreateModal()" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button);">
                                        <i class="bi bi-plus-lg me-1"></i> Create First Category
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Category Modal (Add / Edit) -->
<div id="categoryModal" class="modal" tabindex="-1" style="display: none; background: rgba(14, 30, 59, 0.45); z-index: 1050; position: fixed; inset: 0; align-items: center; justify-content: center;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px; width: 100%; margin: 1.75rem auto;">
        <div class="modal-content" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
            <div class="modal-header py-3 px-4" style="border-bottom: 1px solid var(--ks-border); background: var(--ks-page-bg);">
                <h5 class="modal-title fw-bold" id="modalCategoryTitle" style="color: var(--ks-navy); font-size: 16px;">
                    Add Category
                </h5>
                <button type="button" class="btn-close" onclick="closeCategoryModal()" aria-label="Close"></button>
            </div>
            <form id="categoryForm" method="POST" action="/inventory/categories/create">
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="categoryId">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark">
                            Category Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" id="categoryName" class="form-control" required maxlength="100" placeholder="e.g. Footballs & Balls, Apparel, Medical Supplies" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <div class="form-text small" style="font-size: 11px;">Maximum 100 characters. Must be unique for this organization.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark">
                            Description
                        </label>
                        <textarea name="description" id="categoryDesc" class="form-control" rows="3" maxlength="255" placeholder="Purpose or classification details for this category..." style="font-size: 13px; border-radius: var(--ks-radius-button);"></textarea>
                        <div class="form-text small" style="font-size: 11px;">Optional. Maximum 255 characters.</div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-dark">
                            Status
                        </label>
                        <select name="status" id="categoryStatus" class="form-select" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2 px-4" style="border-top: 1px solid var(--ks-border); background: var(--ks-page-bg);">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeCategoryModal()" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        Cancel
                    </button>
                    <button type="submit" id="categorySubmitBtn" class="btn btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); font-size: 13px; font-weight: 600; border-radius: var(--ks-radius-button);">
                        Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modalCategoryTitle').textContent = 'Add Category';
    document.getElementById('categoryForm').action = '/inventory/categories/create';
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryName').value = '';
    document.getElementById('categoryDesc').value = '';
    document.getElementById('categoryStatus').value = 'active';
    document.getElementById('categorySubmitBtn').textContent = 'Create Category';
    
    const modal = document.getElementById('categoryModal');
    modal.style.display = 'flex';
    setTimeout(() => document.getElementById('categoryName').focus(), 50);
}

function openEditModal(cat) {
    document.getElementById('modalCategoryTitle').textContent = 'Edit Category: ' + cat.name;
    document.getElementById('categoryForm').action = '/inventory/categories/' + cat.id + '/edit';
    document.getElementById('categoryId').value = cat.id;
    document.getElementById('categoryName').value = cat.name || '';
    document.getElementById('categoryDesc').value = cat.description || '';
    document.getElementById('categoryStatus').value = cat.status || 'active';
    document.getElementById('categorySubmitBtn').textContent = 'Save Changes';
    
    const modal = document.getElementById('categoryModal');
    modal.style.display = 'flex';
    setTimeout(() => document.getElementById('categoryName').focus(), 50);
}

function closeCategoryModal() {
    document.getElementById('categoryModal').style.display = 'none';
}

// Close modal when clicking outside the dialog content
window.addEventListener('click', function(e) {
    const modal = document.getElementById('categoryModal');
    if (e.target === modal) {
        closeCategoryModal();
    }
});

// Close modal on Escape key
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCategoryModal();
    }
});
</script>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
