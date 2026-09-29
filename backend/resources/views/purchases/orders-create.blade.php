<?php
$pageTitle = 'Issue Purchase Order — KhelSutra';
$activePage = 'purchases';
$orgId = current_organization_id();

$db = \App\Services\BaseService::getDatabaseConnection();
$vendors = $db->query("SELECT id, company_name, vendor_code FROM vendors WHERE organization_id = {$orgId} AND status = 'active' AND deleted_at IS NULL ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$inventoryItems = $db->query("SELECT id, item_name, item_code, quantity, unit_cost FROM inventory_items WHERE organization_id = {$orgId} AND deleted_at IS NULL ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Check if prefilling from approved Purchase Request
$prId = !empty($_GET['purchase_request_id']) ? (int)$_GET['purchase_request_id'] : null;
$preloadedPr = null;
if ($prId) {
    $purchaseService = new \App\Services\Purchase\PurchaseService();
    $preloadedPr = $purchaseService->getPurchaseRequest($orgId, $prId);
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
                <a href="/purchases?tab=orders" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Procurement</a>
                <span class="text-muted small">/</span>
                <span class="text-dark small fw-semibold">New Purchase Order</span>
            </div>
            <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Issue Purchase Order</h1>
        </div>
        <div>
            <a href="/purchases?tab=orders" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                Cancel
            </a>
        </div>
    </div>

    <?php if ($preloadedPr): ?>
        <div class="alert alert-info d-flex align-items-center gap-2 mb-4" style="border-radius: var(--ks-radius-card); font-size: 13px;">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <div>
                Generating Purchase Order from approved request <strong><?= htmlspecialchars($preloadedPr['request_reference'], ENT_QUOTES, 'UTF-8') ?></strong>. Line items have been automatically imported.
            </div>
        </div>
    <?php endif; ?>

    <div class="card p-4 mx-auto" style="max-width: 1000px; border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
        <form method="POST" action="/purchases/orders/create" id="poForm">
            <?php if ($preloadedPr): ?>
                <input type="hidden" name="purchase_request_id" value="<?= (int)$preloadedPr['id'] ?>">
            <?php endif; ?>

            <!-- PO Details Header -->
            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                <i class="bi bi-cart-check me-1 text-primary"></i> Order & Supplier Information
            </h5>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Supplier / Vendor <span class="text-danger">*</span></label>
                    <select name="vendor_id" class="form-select" required style="font-size: 13px;">
                        <option value="">-- Choose Approved Supplier --</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= (int)$v['id'] ?>">
                                <?= htmlspecialchars($v['company_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($v['vendor_code'], ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-dark">Order Date <span class="text-danger">*</span></label>
                    <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>" required style="font-size: 13px;">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-dark">Expected Delivery Date</label>
                    <input type="date" name="expected_delivery_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" style="font-size: 13px;">
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Custom PO Number</label>
                    <input type="text" name="po_number" class="form-control" placeholder="Leave blank to auto-generate" style="font-size: 13px;">
                    <span class="text-muted" style="font-size: 11px;">e.g. PO-20260923-0001</span>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Initial Order Status</label>
                    <select name="status" class="form-select" style="font-size: 13px;">
                        <option value="draft" selected>Draft (Save internally)</option>
                        <option value="sent">Sent to Supplier</option>
                        <option value="confirmed">Confirmed by Supplier</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Linked Requisition</label>
                    <input type="text" class="form-control bg-light" readonly value="<?= $preloadedPr ? htmlspecialchars($preloadedPr['request_reference'], ENT_QUOTES, 'UTF-8') : 'None (Direct Order)' ?>" style="font-size: 13px;">
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold text-dark">Order Notes / Terms</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Delivery instructions, packaging terms, payment conditions..." style="font-size: 13px;"><?= $preloadedPr ? htmlspecialchars($preloadedPr['purpose'] ?? '', ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                </div>
            </div>

            <!-- Ordered Line Items -->
            <div class="d-flex justify-content-between align-items-center mb-2 pt-2 border-top">
                <h5 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 15px;">
                    <i class="bi bi-box-seam me-1 text-primary"></i> Order Line Items
                </h5>
                <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" onclick="addPoRow()">
                    <i class="bi bi-plus-circle"></i> Add Item
                </button>
            </div>
            <p class="text-muted small mb-3">Item prices, GST/tax amounts, and discounts for each ordered asset.</p>

            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle mb-0" id="poItemsTable" style="font-size: 13px;">
                    <thead style="background: var(--ks-page-bg);">
                        <tr>
                            <th style="min-width: 240px;">Item / Inventory Link <span class="text-danger">*</span></th>
                            <th style="width: 110px;">Quantity <span class="text-danger">*</span></th>
                            <th style="width: 130px;">Unit Cost (₹) <span class="text-danger">*</span></th>
                            <th style="width: 110px;">Tax (₹)</th>
                            <th style="width: 110px;">Disc (₹)</th>
                            <th style="width: 140px;" class="text-end">Total (₹)</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="poItemsBody">
                        <?php 
                        $itemsToRender = !empty($preloadedPr['items']) ? $preloadedPr['items'] : [[]];
                        foreach ($itemsToRender as $idx => $prItem):
                            $pQty = (float)($prItem['quantity'] ?? 1.0);
                            $pCost = (float)($prItem['estimated_unit_cost'] ?? 0.0);
                            $pInvId = (int)($prItem['inventory_item_id'] ?? 0);
                            $pName = $prItem['item_name'] ?? '';
                        ?>
                            <tr class="po-item-row">
                                <td>
                                    <select name="items[<?= $idx ?>][inventory_item_id]" class="form-select mb-1 po-item-select" onchange="onPoItemSelect(this)" style="font-size: 13px;">
                                        <option value="">-- Standalone Item (No Inventory Link) --</option>
                                        <?php foreach ($inventoryItems as $inv): ?>
                                            <option value="<?= (int)$inv['id'] ?>" data-name="<?= htmlspecialchars($inv['item_name'], ENT_QUOTES, 'UTF-8') ?>" data-cost="<?= (float)$inv['unit_cost'] ?>" <?= $pInvId === (int)$inv['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($inv['item_name'], ENT_QUOTES, 'UTF-8') ?> (Stock: <?= (float)$inv['quantity'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="items[<?= $idx ?>][item_name]" class="form-control po-item-name" value="<?= htmlspecialchars($pName, ENT_QUOTES, 'UTF-8') ?>" placeholder="Item name..." required style="font-size: 13px;">
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0.01" name="items[<?= $idx ?>][ordered_quantity]" class="form-control font-monospace po-item-qty" value="<?= $pQty ?>" required oninput="recalcPoRow(this)" style="font-size: 13px;">
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="items[<?= $idx ?>][unit_cost]" class="form-control font-monospace po-item-cost" value="<?= $pCost ?>" required oninput="recalcPoRow(this)" style="font-size: 13px;">
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="items[<?= $idx ?>][tax_amount]" class="form-control font-monospace po-item-tax" value="0.00" oninput="recalcPoRow(this)" style="font-size: 13px;">
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="items[<?= $idx ?>][discount_amount]" class="form-control font-monospace po-item-disc" value="0.00" oninput="recalcPoRow(this)" style="font-size: 13px;">
                                </td>
                                <td class="text-end">
                                    <input type="text" class="form-control bg-light font-monospace text-end po-item-total" readonly value="0.00" style="font-size: 13px;">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePoRow(this)" <?= count($itemsToRender) <= 1 ? 'disabled' : '' ?>>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background: #fafafa;">
                        <tr>
                            <th colspan="5" class="text-end">Subtotal:</th>
                            <th class="text-end font-monospace" id="poSubtotalDisplay">₹0.00</th>
                            <th></th>
                        </tr>
                        <tr>
                            <th colspan="5" class="text-end">Total Tax (+):</th>
                            <th class="text-end font-monospace text-muted" id="poTaxDisplay">₹0.00</th>
                            <th></th>
                        </tr>
                        <tr>
                            <th colspan="5" class="text-end">Total Discount (-):</th>
                            <th class="text-end font-monospace text-muted" id="poDiscDisplay">₹0.00</th>
                            <th></th>
                        </tr>
                        <tr style="background: #f1f5f9;">
                            <th colspan="5" class="text-end h6 fw-bold mb-0">Grand Order Total:</th>
                            <th class="text-end font-monospace h5 fw-bold text-primary mb-0" id="poGrandTotalDisplay">₹0.00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <a href="/purchases?tab=orders" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 24px;">
                    <i class="bi bi-check2"></i> Issue Purchase Order
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let poRowIndex = <?= count($itemsToRender) ?>;

    function addPoRow() {
        const tbody = document.getElementById('poItemsBody');
        const firstRow = tbody.querySelector('.po-item-row');
        const newRow = firstRow.cloneNode(true);

        newRow.querySelectorAll('input, select').forEach(el => {
            const name = el.getAttribute('name');
            if (name) {
                el.setAttribute('name', name.replace(/\[\d+\]/, '[' + poRowIndex + ']'));
            }
            if (el.tagName === 'SELECT') {
                el.selectedIndex = 0;
            } else if (el.classList.contains('po-item-qty')) {
                el.value = '1.00';
            } else if (el.classList.contains('po-item-cost') || el.classList.contains('po-item-tax') || el.classList.contains('po-item-disc') || el.classList.contains('po-item-total')) {
                el.value = '0.00';
            } else {
                el.value = '';
            }
        });

        tbody.appendChild(newRow);
        poRowIndex++;
        updatePoDeleteButtons();
        recalcAllPo();
    }

    function removePoRow(btn) {
        const tbody = document.getElementById('poItemsBody');
        if (tbody.querySelectorAll('.po-item-row').length > 1) {
            btn.closest('tr').remove();
            updatePoDeleteButtons();
            recalcAllPo();
        }
    }

    function updatePoDeleteButtons() {
        const rows = document.querySelectorAll('#poItemsBody .po-item-row');
        rows.forEach(r => {
            const btn = r.querySelector('button');
            if (btn) btn.disabled = (rows.length <= 1);
        });
    }

    function onPoItemSelect(select) {
        const row = select.closest('tr');
        const opt = select.selectedOptions[0];
        const nameInput = row.querySelector('.po-item-name');
        const costInput = row.querySelector('.po-item-cost');

        if (select.value && opt) {
            nameInput.value = opt.getAttribute('data-name') || '';
            const cost = parseFloat(opt.getAttribute('data-cost')) || 0;
            if (cost > 0 && parseFloat(costInput.value) === 0) {
                costInput.value = cost.toFixed(2);
            }
        }
        recalcPoRow(nameInput);
    }

    function recalcPoRow(el) {
        const row = el.closest('tr');
        const qty = parseFloat(row.querySelector('.po-item-qty').value) || 0;
        const cost = parseFloat(row.querySelector('.po-item-cost').value) || 0;
        const tax = parseFloat(row.querySelector('.po-item-tax').value) || 0;
        const disc = parseFloat(row.querySelector('.po-item-disc').value) || 0;
        const total = Math.max(0, (qty * cost) + tax - disc);
        row.querySelector('.po-item-total').value = total.toFixed(2);
        recalcAllPo();
    }

    function recalcAllPo() {
        let subtotal = 0;
        let totalTax = 0;
        let totalDisc = 0;

        document.querySelectorAll('#poItemsBody .po-item-row').forEach(r => {
            const qty = parseFloat(r.querySelector('.po-item-qty').value) || 0;
            const cost = parseFloat(r.querySelector('.po-item-cost').value) || 0;
            const tax = parseFloat(r.querySelector('.po-item-tax').value) || 0;
            const disc = parseFloat(r.querySelector('.po-item-disc').value) || 0;

            subtotal += (qty * cost);
            totalTax += tax;
            totalDisc += disc;
        });

        const grandTotal = Math.max(0, subtotal + totalTax - totalDisc);

        document.getElementById('poSubtotalDisplay').textContent = '₹' + subtotal.toFixed(2);
        document.getElementById('poTaxDisplay').textContent = '+₹' + totalTax.toFixed(2);
        document.getElementById('poDiscDisplay').textContent = '-₹' + totalDisc.toFixed(2);
        document.getElementById('poGrandTotalDisplay').textContent = '₹' + grandTotal.toFixed(2);
    }

    // Initial calculation on page load
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('#poItemsBody .po-item-row').forEach(r => {
            recalcPoRow(r.querySelector('.po-item-qty'));
        });
    });
</script>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
