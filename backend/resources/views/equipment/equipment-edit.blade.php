<?php
$pageTitle = 'Edit Equipment Unit — KhelSutra';
$activePage = 'equipment';
$orgId = current_organization_id();

$id = (int)($id ?? ($data['id'] ?? ($_GET['id'] ?? 0)));
$eqService = new \App\Services\Equipment\EquipmentService();
$equipment = $eqService->getEquipment($orgId, $id);

$db = \App\Services\BaseService::getDatabaseConnection();
$items = $db->query("SELECT id, item_name, item_code FROM inventory_items WHERE organization_id = {$orgId} AND deleted_at IS NULL ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

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

    <?php if (!$equipment): ?>
        <div class="card p-5 text-center" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <i class="bi bi-exclamation-circle text-danger fs-1 mb-3"></i>
            <h4 class="fw-bold mb-2">Equipment Asset Not Found</h4>
            <p class="text-muted small mb-4">The equipment item you are trying to edit does not exist or access was denied.</p>
            <div>
                <a href="/equipment" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                    Back to Equipment
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <a href="/equipment" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Equipment List</a>
                    <span class="text-muted small">/</span>
                    <a href="/equipment/<?= (int)$equipment['id'] ?>" class="text-muted text-decoration-none small"><?= htmlspecialchars($equipment['asset_code'], ENT_QUOTES, 'UTF-8') ?></a>
                    <span class="text-muted small">/</span>
                    <span class="text-dark small fw-semibold">Edit</span>
                </div>
                <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Edit Equipment: <?= htmlspecialchars($equipment['equipment_name'], ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <div>
                <a href="/equipment/<?= (int)$equipment['id'] ?>" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                    Cancel
                </a>
            </div>
        </div>

        <div class="card p-4 mx-auto" style="max-width: 850px; border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <form method="POST" action="/equipment/<?= (int)$equipment['id'] ?>/edit">
                <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                    Asset Information
                </h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold text-dark">Equipment Name <span class="text-danger">*</span></label>
                        <input type="text" name="equipment_name" class="form-control" value="<?= htmlspecialchars($equipment['equipment_name'], ENT_QUOTES, 'UTF-8') ?>" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Asset Code <span class="text-danger">*</span></label>
                        <input type="text" name="asset_code" class="form-control" value="<?= htmlspecialchars($equipment['asset_code'], ENT_QUOTES, 'UTF-8') ?>" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">Linked Stock Inventory Item</label>
                        <select name="inventory_item_id" class="form-select" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                            <option value="">-- Standalone Asset (No Parent Item) --</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= (int)$item['id'] ?>" <?= (int)$equipment['inventory_item_id'] === (int)$item['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($item['item_code'], ENT_QUOTES, 'UTF-8') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">Storage Location</label>
                        <input type="text" name="current_location" class="form-control" value="<?= htmlspecialchars($equipment['current_location'] ?: 'Main Storage', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                </div>

                <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                    Identifiers & Specifications
                </h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Serial Number</label>
                        <input type="text" name="serial_number" class="form-control" value="<?= htmlspecialchars($equipment['serial_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. SN-99482910" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Model Number</label>
                        <input type="text" name="model_number" class="form-control" value="<?= htmlspecialchars($equipment['model_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Manufacturer</label>
                        <input type="text" name="manufacturer" class="form-control" value="<?= htmlspecialchars($equipment['manufacturer'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                </div>

                <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                    Condition & Procurement
                </h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Condition Status</label>
                        <select name="condition_status" class="form-select" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                            <option value="new" <?= $equipment['condition_status'] === 'new' ? 'selected' : '' ?>>New</option>
                            <option value="good" <?= $equipment['condition_status'] === 'good' ? 'selected' : '' ?>>Good</option>
                            <option value="damaged" <?= $equipment['condition_status'] === 'damaged' ? 'selected' : '' ?>>Damaged</option>
                            <option value="under_maintenance" <?= $equipment['condition_status'] === 'under_maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                            <option value="lost" <?= $equipment['condition_status'] === 'lost' ? 'selected' : '' ?>>Lost</option>
                            <option value="disposed" <?= $equipment['condition_status'] === 'disposed' ? 'selected' : '' ?>>Disposed</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Purchase Cost (₹)</label>
                        <input type="number" step="0.01" min="0" name="purchase_cost" class="form-control" value="<?= htmlspecialchars($equipment['purchase_cost'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control" value="<?= htmlspecialchars($equipment['purchase_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">Warranty Expiry Date</label>
                        <input type="date" name="warranty_expiry_date" class="form-control" value="<?= htmlspecialchars($equipment['warranty_expiry_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">Deployment Status</label>
                        <select name="status" class="form-select" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                            <option value="available" <?= $equipment['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                            <?php if ($equipment['status'] === 'assigned'): ?>
                                <option value="assigned" selected>Assigned (In Use)</option>
                            <?php endif; ?>
                            <option value="maintenance" <?= $equipment['status'] === 'maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                            <option value="lost" <?= $equipment['status'] === 'lost' ? 'selected' : '' ?>>Lost / Missing</option>
                            <option value="disposed" <?= $equipment['status'] === 'disposed' ? 'selected' : '' ?>>Disposed</option>
                        </select>
                        <?php if ($equipment['status'] === 'assigned'): ?>
                            <span class="text-warning small d-block mt-1"><i class="bi bi-info-circle"></i> This equipment is currently assigned. To make it available, process an Equipment Return.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <a href="/equipment/<?= (int)$equipment['id'] ?>" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px; padding: 9px 18px;">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-size: 13px; font-weight: 600; padding: 9px 24px;">
                        Update Equipment
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
