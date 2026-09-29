<?php
$pageTitle = 'Finance Categories — KhelSutra';
$activePage = 'finance';
$orgId = current_organization_id();

$financeService = new \App\Services\Finance\FinanceService();
$categories = $financeService->listCategories($orgId);

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

    <!-- Breadcrumb & Header -->
    <div class="d-flex align-items-center gap-2 mb-2">
        <a href="/finance" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Finance Hub</a>
        <span class="text-muted small">/</span>
        <span class="text-dark small fw-semibold">Categories</span>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Finance & Cost Categories</h1>
            <p class="text-muted small mb-0">Organize revenues and expenditures by assigning categories to transactions and budget lines.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-size: 13px;">
            <i class="bi bi-plus-lg me-1"></i> New Category
        </button>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: var(--ks-radius-card); background: #fff;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-light">
                        <tr>
                            <th class="py-2 px-3 text-muted fw-semibold">Category Name</th>
                            <th class="py-2 px-3 text-muted fw-semibold">Type</th>
                            <th class="py-2 px-3 text-muted fw-semibold">Description</th>
                            <th class="py-2 px-3 text-muted fw-semibold text-center">Status</th>
                            <th class="py-2 px-3 text-muted fw-semibold text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td class="py-3 px-3 fw-bold text-dark">
                                        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-3 px-3">
                                        <?php
                                        $tBadge = match($cat['category_type']) {
                                            'income' => 'bg-success-subtle text-success border border-success-subtle',
                                            'expense' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                            'both' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                            default => 'bg-light text-dark'
                                        };
                                        ?>
                                        <span class="badge <?= $tBadge ?> px-2 py-1" style="font-size: 11px;">
                                            <?= htmlspecialchars(ucfirst($cat['category_type']), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-muted">
                                        <?= !empty($cat['description']) ? htmlspecialchars($cat['description'], ENT_QUOTES, 'UTF-8') : '<em class="text-muted">No description</em>' ?>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="badge <?= $cat['status'] === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' ?> px-2 py-1" style="font-size: 11px;">
                                            <?= htmlspecialchars(ucfirst($cat['status']), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editCategoryModal<?= (int)$cat['id'] ?>" title="Edit Category">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="/finance/categories/<?= (int)$cat['id'] ?>/status" class="d-inline">
                                            <input type="hidden" name="status" value="<?= $cat['status'] === 'active' ? 'inactive' : 'active' ?>">
                                            <button type="submit" class="btn btn-sm <?= $cat['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="<?= $cat['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                                                <i class="bi <?= $cat['status'] === 'active' ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editCategoryModal<?= (int)$cat['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content" style="border-radius: var(--ks-radius-card);">
                                            <form method="POST" action="/finance/categories/<?= (int)$cat['id'] ?>/edit">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold" style="font-size: 16px;">Edit Category: <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body" style="font-size: 13px;">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold text-dark">Category Name <span class="text-danger">*</span></label>
                                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="100" style="font-size: 13px;">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold text-dark">Category Type <span class="text-danger">*</span></label>
                                                        <select name="category_type" class="form-select" style="font-size: 13px;">
                                                            <option value="both" <?= $cat['category_type'] === 'both' ? 'selected' : '' ?>>Both (Income & Expense)</option>
                                                            <option value="expense" <?= $cat['category_type'] === 'expense' ? 'selected' : '' ?>>Expense Only</option>
                                                            <option value="income" <?= $cat['category_type'] === 'income' ? 'selected' : '' ?>>Income Only</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold text-dark">Status</label>
                                                        <select name="status" class="form-select" style="font-size: 13px;">
                                                            <option value="active" <?= $cat['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                            <option value="inactive" <?= $cat['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-0">
                                                        <label class="form-label fw-semibold text-dark">Description</label>
                                                        <textarea name="description" class="form-control" rows="3" style="font-size: 13px;"><?= htmlspecialchars($cat['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
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
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="bi bi-tags text-muted" style="font-size: 2.2rem;"></i>
                                    <p class="text-muted mt-2 mb-2" style="font-size: 13px;">No categories configured.</p>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                        <i class="bi bi-plus-lg me-1"></i> Add First Category
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Category -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: var(--ks-radius-card);">
            <form method="POST" action="/finance/categories/create">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" style="font-size: 16px;">Create Finance Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="font-size: 13px;">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Facilities & Utilities" required maxlength="100" style="font-size: 13px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Category Type <span class="text-danger">*</span></label>
                        <select name="category_type" class="form-select" style="font-size: 13px;">
                            <option value="both">Both (Income & Expense)</option>
                            <option value="expense" selected>Expense Only</option>
                            <option value="income">Income Only</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Status</label>
                        <select name="status" class="form-select" style="font-size: 13px;">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold text-dark">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Category notes..." style="font-size: 13px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue);">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.blade.php';
