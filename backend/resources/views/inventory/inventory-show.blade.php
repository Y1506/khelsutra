<?php
$itemId = (int)($id ?? ($_GET['id'] ?? 0));
$orgId = current_organization_id();
$invService = new \App\Services\Inventory\InventoryService();
$item = $invService->getItem($orgId, $itemId);

$pageTitle = $item ? htmlspecialchars($item['item_name'] . ' — Inventory Item Details') : 'Inventory Item Details';
$activePage = 'inventory';

ob_start();
?>

<div class="ks-content">
    <?php if (!$item): ?>
        <div class="card p-5 text-center" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <div class="mb-3"><i class="bi bi-box-seam fs-1 text-muted"></i></div>
            <h4 class="fw-bold" style="color: var(--ks-navy);">Item Not Found</h4>
            <p class="text-muted small">The requested inventory item does not exist or does not belong to your organisation.</p>
            <div class="mt-3">
                <a href="/inventory" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-weight: 500;">
                    <i class="bi bi-arrow-left me-1"></i> Back to Inventory
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Back Navigation -->
        <div class="mb-3">
            <a href="/inventory" class="text-decoration-none text-muted small fw-medium">
                <i class="bi bi-arrow-left me-1"></i> Back to Inventory
            </a>
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

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 52px; height: 52px; border-radius: 12px; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h2 class="h4 fw-bold mb-0" style="color: var(--ks-navy);"><?= htmlspecialchars($item['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
                        <span class="badge <?= ($item['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-secondary' ?>" style="border-radius: 12px; font-size: 11px; padding: 4px 10px; text-transform: capitalize;">
                            <?= htmlspecialchars($item['status'] ?? 'active', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php if (!empty($item['is_low_stock'])): ?>
                            <span class="badge bg-danger-subtle text-danger" style="border-radius: 12px; font-size: 11px; padding: 4px 10px;">
                                <i class="bi bi-exclamation-circle me-1"></i> Low Stock
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small mt-1">
                        Code: <strong style="color: var(--ks-text);"><?= htmlspecialchars($item['item_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong> &bull;
                        Category: <strong style="color: var(--ks-text);"><?= htmlspecialchars($item['category_name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></strong> &bull;
                        Location: <strong style="color: var(--ks-text);"><?= htmlspecialchars($item['location_name'] ?? 'Store Room', ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="/inventory/<?= (int)$item['id'] ?>/edit" class="btn btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 8px 18px;">
                    <i class="bi bi-pencil-square"></i> Edit Item
                </a>
                <form action="/inventory/<?= (int)$item['id'] ?>/delete" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete item \'<?= addslashes(htmlspecialchars($item['item_name'])) ?>\'?');">
                    <button type="submit" class="btn btn-outline-danger d-inline-flex align-items-center gap-1" style="border-radius: var(--ks-radius-button); font-weight: 500; font-size: 13px; padding: 8px 14px;" title="Delete Item">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </form>
            </div>
        </div>

        <div class="row g-3">
            <!-- Left Column: Transactions & Movement Form -->
            <div class="col-lg-8">
                <!-- Stock Overview & Movement Form -->
                <div class="card p-4 mb-3" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <h5 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 15px;">
                            <i class="bi bi-arrow-left-right me-2" style="color: var(--ks-blue);"></i> Stock Movement & Transactions
                        </h5>
                        <div>
                            <span class="text-muted small">Current Level:</span>
                            <strong class="fs-6 <?= !empty($item['is_low_stock']) ? 'text-danger' : 'text-dark' ?> ms-1">
                                <?= (float)($item['quantity'] ?? 0) ?> <?= htmlspecialchars($item['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </strong>
                        </div>
                    </div>

                    <!-- Record Stock Movement Form -->
                    <div class="card p-3 mb-4" style="background: var(--ks-page-bg); border: 1px dashed var(--ks-border); border-radius: var(--ks-radius-button);">
                        <h6 class="fw-bold mb-2" style="color: var(--ks-navy); font-size: 13px;">Record Stock Movement / Transaction</h6>
                        <form action="/inventory/<?= (int)$item['id'] ?>/transactions" method="POST" class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Transaction Type</label>
                                <select name="transaction_type" class="form-select form-select-sm" required style="font-size: 12px;">
                                    <option value="purchase">Stock In: New Purchase (+)</option>
                                    <option value="return">Stock In: Return from Squad (+)</option>
                                    <option value="issue">Stock Out: Issue to Squad (-)</option>
                                    <option value="damage">Stock Out: Damaged / Broken (-)</option>
                                    <option value="loss">Stock Out: Lost / Missing (-)</option>
                                    <option value="disposal">Stock Out: Written Off / Disposed (-)</option>
                                    <option value="adjustment">Stock Adjustment (Increase +)</option>
                                    <option value="adjustment_dec">Stock Adjustment (Decrease -)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Quantity (<?= htmlspecialchars($item['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>)</label>
                                <input type="number" step="0.01" name="quantity" class="form-control form-control-sm" min="0.01" value="1" required style="font-size: 12px;">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">Remarks / Reference</label>
                                <input type="text" name="remarks" class="form-control form-control-sm" placeholder="e.g. Issued for Weekend League match" style="font-size: 12px;">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-sm btn-primary w-100" style="background: var(--ks-blue); border-color: var(--ks-blue); font-size: 12px; height: 31px;">
                                    Record
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Stock Transactions History Table -->
                    <?php if (!empty($item['transactions']) && count($item['transactions']) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 13px;">
                                <thead style="background: var(--ks-page-bg);">
                                    <tr>
                                        <th>Date</th>
                                        <th>Movement</th>
                                        <th>Qty</th>
                                        <th>Recorded By</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($item['transactions'] as $tx): ?>
                                        <?php
                                            $isDeduct = in_array($tx['transaction_type'], ['issue', 'damage', 'loss', 'disposal']);
                                        ?>
                                        <tr>
                                            <td class="text-muted small"><?= date('M d, Y H:i', strtotime($tx['transaction_date'])) ?></td>
                                            <td>
                                                <span class="badge <?= $isDeduct ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?>" style="font-size: 10px; text-transform: capitalize;">
                                                    <?= htmlspecialchars($tx['transaction_type'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold <?= $isDeduct ? 'text-danger' : 'text-success' ?>">
                                                <?= $isDeduct ? '-' : '+' ?><?= (float)$tx['quantity'] ?> <?= htmlspecialchars($item['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="text-muted small">
                                                <?= htmlspecialchars($tx['performer_name'] ?? 'Staff', ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="text-muted small"><?= htmlspecialchars($tx['remarks'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No transaction records found for this item.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Stock Thresholds & Specifications -->
            <div class="col-lg-4">
                <div class="card p-4 mb-3" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 10px;">
                        <i class="bi bi-shield-check me-2" style="color: var(--ks-blue);"></i> Stock Safeguards & Levels
                    </h5>
                    <div class="row g-2 small">
                        <div class="col-6">
                            <div class="text-muted">Minimum Safety Stock</div>
                            <div class="fw-semibold text-dark mt-1"><?= (float)($item['minimum_stock_level'] ?? 0) ?> <?= htmlspecialchars($item['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted">Reorder Trigger Level</div>
                            <div class="fw-semibold text-dark mt-1"><?= (float)($item['reorder_level'] ?? 0) ?> <?= htmlspecialchars($item['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="col-6 mt-2">
                            <div class="text-muted">Unit Cost</div>
                            <div class="fw-semibold text-dark mt-1">₹<?= number_format((float)($item['unit_cost'] ?? 0), 2) ?></div>
                        </div>
                        <div class="col-6 mt-2">
                            <div class="text-muted">Total Stock Value</div>
                            <div class="fw-semibold text-dark mt-1">₹<?= number_format((float)($item['quantity'] * ($item['unit_cost'] ?? 0)), 2) ?></div>
                        </div>
                        <?php if (!empty($item['description'])): ?>
                            <div class="col-12 mt-2">
                                <div class="text-muted">Description</div>
                                <div class="text-dark mt-1" style="line-height: 1.5;"><?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tracked Asset Units -->
                <div class="card p-4 mb-3" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <h5 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 15px;">
                            <i class="bi bi-tag me-2" style="color: var(--ks-blue);"></i> Tracked Serial Assets
                        </h5>
                        <a href="/equipment/create?inventory_item_id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size: 11px; padding: 3px 8px;">
                            <i class="bi bi-plus-lg"></i> Add Unit
                        </a>
                    </div>
                    <?php if (!empty($item['equipment_units']) && count($item['equipment_units']) > 0): ?>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($item['equipment_units'] as $eq): ?>
                                <a href="/equipment/<?= (int)$eq['id'] ?>" class="d-flex align-items-center justify-content-between p-2 border rounded text-decoration-none" style="background: var(--ks-page-bg);">
                                    <div>
                                        <div class="fw-semibold text-dark small"><?= htmlspecialchars($eq['asset_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                        <span class="text-muted" style="font-size: 11px;">Condition: <?= htmlspecialchars(ucfirst($eq['condition_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <span class="badge bg-light text-secondary border small"><?= htmlspecialchars($eq['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No individual units tracked for this item yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
