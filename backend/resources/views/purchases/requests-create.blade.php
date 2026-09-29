<?php
$pageTitle = 'Create Purchase Request — KhelSutra';
$activePage = 'purchases';
$orgId = current_organization_id();

$db = \App\Services\BaseService::getDatabaseConnection();
$items = $db->query("SELECT id, item_name, item_code, quantity, unit_cost FROM inventory_items WHERE organization_id = {$orgId} AND deleted_at IS NULL ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$departments = [];
try {
    $departments = $db->query("SELECT id, name FROM departments WHERE organization_id = {$orgId} ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (\Throwable $e) {
    $departments = [];
}

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
                <a href="/purchases?tab=requests" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Procurement</a>
                <span class="text-muted small">/</span>
                <span class="text-dark small fw-semibold">New Purchase Request</span>
            </div>
            <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Requisition Request</h1>
        </div>
        <div>
            <a href="/purchases?tab=requests" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                Cancel
            </a>
        </div>
    </div>

    <div class="card p-4 mx-auto" style="max-width: 960px; border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
        <form method="POST" action="/purchases/requests/create" id="prForm">
            <!-- Header Information -->
            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                <i class="bi bi-file-earmark-text me-1 text-primary"></i> Requisition Overview
            </h5>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Request Date <span class="text-danger">*</span></label>
                    <input type="date" name="request_date" class="form-control" value="<?= date('Y-m-d') ?>" required style="font-size: 13px;">
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Required By Date</label>
                    <input type="date" name="required_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" style="font-size: 13px;">
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Department</label>
                    <select name="department_id" class="form-select" style="font-size: 13px;">
                        <option value="">-- General / Sports Academy --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold text-dark">Purpose / Justification</label>
                    <textarea name="purpose" class="form-control" rows="2" placeholder="e.g. Replenishment of footballs and cones for upcoming state championship camp" style="font-size: 13px;"></textarea>
                </div>
            </div>

            <!-- Requested Line Items -->
            <div class="d-flex justify-content-between align-items-center mb-2 pt-2 border-top">
                <h5 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 15px;">
                    <i class="bi bi-list-check me-1 text-primary"></i> Requested Line Items
                </h5>
                <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" onclick="addRow()">
                    <i class="bi bi-plus-circle"></i> Add Item
                </button>
            </div>
            <p class="text-muted small mb-3">Select an existing inventory catalog item or type a custom item description.</p>

            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle mb-0" id="itemsTable" style="font-size: 13px;">
                    <thead style="background: var(--ks-page-bg);">
                        <tr>
                            <th style="min-width: 260px;">Catalog Item / Custom Description <span class="text-danger">*</span></th>
                            <th style="width: 120px;">Quantity <span class="text-danger">*</span></th>
                            <th style="width: 140px;">Est. Unit Cost (₹)</th>
                            <th style="width: 140px;">Est. Total (₹)</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr class="item-row">
                            <td>
                                <select name="items[0][inventory_item_id]" class="form-select mb-1 item-select" onchange="onItemSelect(this, 0)" style="font-size: 13px;">
                                    <option value="">-- Custom Item (Enter Name Below) --</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?= (int)$item['id'] ?>" data-name="<?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?>" data-cost="<?= (float)$item['unit_cost'] ?>">
                                            <?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?> (Stock: <?= (float)$item['quantity'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="items[0][item_name]" class="form-control item-name" placeholder="Item name..." required style="font-size: 13px;">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0.01" name="items[0][quantity]" class="form-control font-monospace item-qty" value="1.00" required oninput="recalcRow(this)" style="font-size: 13px;">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="items[0][estimated_unit_cost]" class="form-control font-monospace item-cost" value="0.00" oninput="recalcRow(this)" style="font-size: 13px;">
                            </td>
                            <td>
                                <input type="text" class="form-control bg-light font-monospace item-total" readonly value="0.00" style="font-size: 13px;">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)" disabled title="Cannot remove the only item">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot style="background: #fafafa;">
                        <tr>
                            <th colspan="3" class="text-end">Total Estimated Expenditure:</th>
                            <th class="font-monospace fw-bold text-primary" id="grandTotalDisplay">₹0.00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <a href="/purchases?tab=requests" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 24px;">
                    <i class="bi bi-check2"></i> Save Purchase Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let rowIndex = 1;

    function addRow() {
        const tbody = document.getElementById('itemsBody');
        const firstRow = tbody.querySelector('.item-row');
        const newRow = firstRow.cloneNode(true);

        // Update name attributes and reset values
        newRow.querySelectorAll('input, select').forEach(el => {
            const name = el.getAttribute('name');
            if (name) {
                el.setAttribute('name', name.replace(/\[\d+\]/, '[' + rowIndex + ']'));
            }
            if (el.tagName === 'SELECT') {
                el.selectedIndex = 0;
            } else if (el.classList.contains('item-qty')) {
                el.value = '1.00';
            } else if (el.classList.contains('item-cost') || el.classList.contains('item-total')) {
                el.value = '0.00';
            } else {
                el.value = '';
            }
        });

        // Enable delete button on all rows if > 1
        tbody.appendChild(newRow);
        rowIndex++;
        updateDeleteButtons();
        recalcAll();
    }

    function removeRow(btn) {
        const tbody = document.getElementById('itemsBody');
        if (tbody.querySelectorAll('.item-row').length > 1) {
            btn.closest('tr').remove();
            updateDeleteButtons();
            recalcAll();
        }
    }

    function updateDeleteButtons() {
        const rows = document.querySelectorAll('#itemsBody .item-row');
        rows.forEach(r => {
            const btn = r.querySelector('button');
            if (btn) btn.disabled = (rows.length <= 1);
        });
    }

    function onItemSelect(select, idx) {
        const row = select.closest('tr');
        const opt = select.selectedOptions[0];
        const nameInput = row.querySelector('.item-name');
        const costInput = row.querySelector('.item-cost');

        if (select.value && opt) {
            nameInput.value = opt.getAttribute('data-name') || '';
            const cost = parseFloat(opt.getAttribute('data-cost')) || 0;
            if (cost > 0 && parseFloat(costInput.value) === 0) {
                costInput.value = cost.toFixed(2);
            }
        }
        recalcRow(nameInput);
    }

    function recalcRow(el) {
        const row = el.closest('tr');
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const cost = parseFloat(row.querySelector('.item-cost').value) || 0;
        const total = (qty * cost).toFixed(2);
        row.querySelector('.item-total').value = total;
        recalcAll();
    }

    function recalcAll() {
        let sum = 0;
        document.querySelectorAll('#itemsBody .item-row').forEach(r => {
            const qty = parseFloat(r.querySelector('.item-qty').value) || 0;
            const cost = parseFloat(r.querySelector('.item-cost').value) || 0;
            sum += (qty * cost);
        });
        document.getElementById('grandTotalDisplay').textContent = '₹' + sum.toFixed(2);
    }
</script>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
