<?php
$pageTitle = 'Add Equipment Unit — KhelSutra';
$activePage = 'equipment';
$orgId = current_organization_id();

$db = \App\Services\BaseService::getDatabaseConnection();
$items = $db->query("SELECT id, item_name, item_code FROM inventory_items WHERE organization_id = {$orgId} AND deleted_at IS NULL ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$preselectedItemId = !empty($_GET['inventory_item_id']) ? (int)$_GET['inventory_item_id'] : null;

ob_start();
?>

<div class="ks-content">
    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert" style="border-radius: var(--ks-radius-button); font-size: 13px;">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/equipment" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Equipment List</a>
                <span class="text-muted small">/</span>
                <span class="text-dark small fw-semibold">Add New Asset</span>
            </div>
            <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Add Tracked Equipment</h1>
        </div>
        <div>
            <a href="/equipment" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                Cancel
            </a>
        </div>
    </div>

    <div class="card p-4 mx-auto" style="max-width: 850px; border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
        <form method="POST" action="/equipment/create">
            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                Asset Information
            </h5>

            <div class="row g-3 mb-4">
                <div class="col-md-8">
                    <label class="form-label small fw-semibold text-dark">Equipment Name <span class="text-danger">*</span></label>
                    <input type="text" name="equipment_name" class="form-control" placeholder="e.g. Wilson Match Football #12, Yonex Badminton Racket Pro" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Asset Code</label>
                    <input type="text" name="asset_code" class="form-control" placeholder="Leave blank to auto-generate" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    <span class="text-muted" style="font-size: 11px;">e.g. EQP-20260923-0001</span>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Linked Stock Inventory Item</label>
                    <select name="inventory_item_id" class="form-select" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <option value="">-- Standalone Asset (No Parent Item) --</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?= (int)$item['id'] ?>" <?= $preselectedItemId === (int)$item['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($item['item_code'], ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Storage Location</label>
                    <input type="text" name="current_location" class="form-control" value="Main Storage" placeholder="e.g. Main Equipment Room, Rack C-2" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
            </div>

            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                Identifiers & Specifications
            </h5>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Serial Number</label>
                    <input type="text" name="serial_number" class="form-control" placeholder="e.g. SN-99482910" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Model Number</label>
                    <input type="text" name="model_number" class="form-control" placeholder="e.g. Pro-Match 2026" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Manufacturer</label>
                    <input type="text" name="manufacturer" class="form-control" placeholder="e.g. Nike, Cosco, Yonex" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
            </div>

            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                Condition & Procurement
            </h5>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Condition Status</label>
                    <select name="condition_status" class="form-select" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <option value="new" selected>New</option>
                        <option value="good">Good</option>
                        <option value="damaged">Damaged</option>
                        <option value="under_maintenance">Under Maintenance</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Purchase Cost (₹)</label>
                    <input type="number" step="0.01" min="0" name="purchase_cost" class="form-control" placeholder="0.00" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Purchase Date</label>
                    <input type="date" name="purchase_date" class="form-control" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Warranty Expiry Date</label>
                    <input type="date" name="warranty_expiry_date" class="form-control" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Initial Status</label>
                    <select name="status" class="form-select" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <option value="available" selected>Available (Ready for assignment)</option>
                        <option value="maintenance">Under Maintenance</option>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <a href="/equipment" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px; padding: 9px 18px;">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-size: 13px; font-weight: 600; padding: 9px 24px;">
                    Save Equipment
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
