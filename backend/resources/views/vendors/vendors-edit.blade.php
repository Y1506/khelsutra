<?php
$pageTitle = 'Edit Vendor — KhelSutra';
$activePage = 'vendors';
$orgId = current_organization_id();

$id = (int)($id ?? ($data['id'] ?? ($_GET['id'] ?? 0)));
$vendorService = new \App\Services\Vendor\VendorService();
$vendor = $vendorService->getVendor($orgId, $id);

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

    <?php if (!$vendor): ?>
        <div class="card p-5 text-center" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <i class="bi bi-exclamation-circle text-danger fs-1 mb-3"></i>
            <h4 class="fw-bold mb-2">Vendor Not Found</h4>
            <p class="text-muted small mb-4">The vendor profile you are trying to edit does not exist or access was denied.</p>
            <div>
                <a href="/vendors" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                    Back to Vendors
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <a href="/vendors" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Vendors Directory</a>
                    <span class="text-muted small">/</span>
                    <a href="/vendors/<?= (int)$vendor['id'] ?>" class="text-muted text-decoration-none small"><?= htmlspecialchars($vendor['vendor_code'], ENT_QUOTES, 'UTF-8') ?></a>
                    <span class="text-muted small">/</span>
                    <span class="text-dark small fw-semibold">Edit</span>
                </div>
                <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Edit Vendor: <?= htmlspecialchars($vendor['company_name'], ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <div>
                <a href="/vendors/<?= (int)$vendor['id'] ?>" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                    Cancel
                </a>
            </div>
        </div>

        <div class="card p-4 mx-auto" style="max-width: 900px; border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <form method="POST" action="/vendors/<?= (int)$vendor['id'] ?>/edit">
                <!-- 1. Company Information -->
                <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                    <i class="bi bi-building me-1 text-primary"></i> Company & General Information
                </h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold text-dark">Company / Vendor Name <span class="text-danger">*</span></label>
                        <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($vendor['company_name'], ENT_QUOTES, 'UTF-8') ?>" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Vendor Code</label>
                        <input type="text" class="form-control bg-light font-monospace" value="<?= htmlspecialchars($vendor['vendor_code'], ENT_QUOTES, 'UTF-8') ?>" readonly style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <span class="text-muted" style="font-size: 11px;">Fixed identification code</span>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">Vendor Category / Type</label>
                        <select name="vendor_type" class="form-select" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                            <?php
                            $types = [
                                'equipment' => 'Sports Equipment & Gear',
                                'apparel' => 'Team Apparel & Uniforms',
                                'nutrition' => 'Nutrition & Supplements',
                                'maintenance' => 'Facility Maintenance & Repairs',
                                'medical' => 'Sports Medicine & Physiotherapy',
                                'transport' => 'Transportation & Logistics',
                                'catering' => 'Catering & Event Food Services',
                                'general' => 'General Supplies',
                            ];
                            ?>
                            <?php foreach ($types as $k => $label): ?>
                                <option value="<?= $k ?>" <?= ($vendor['vendor_type'] === $k) ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">Status</label>
                        <select name="status" class="form-select" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                            <option value="active" <?= $vendor['status'] === 'active' ? 'selected' : '' ?>>Active (Approved for procurement)</option>
                            <option value="inactive" <?= $vendor['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Suspended)</option>
                            <option value="blacklisted" <?= $vendor['status'] === 'blacklisted' ? 'selected' : '' ?>>Blacklisted (Barred)</option>
                        </select>
                    </div>
                </div>

                <!-- 2. Contact Person & Communication -->
                <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                    <i class="bi bi-person-lines-fill me-1 text-primary"></i> Contact & Communication
                </h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">Primary Contact Person</label>
                        <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($vendor['contact_person'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Rajesh Sharma" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($vendor['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="sales@vendor.com" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Primary Phone</label>
                        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($vendor['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="+91 98765 43210" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Alternate Phone</label>
                        <input type="tel" name="alternate_phone" class="form-control" value="<?= htmlspecialchars($vendor['alternate_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="+91 11 2345 6789" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Website URL</label>
                        <input type="url" name="website" class="form-control" value="<?= htmlspecialchars($vendor['website'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://www.vendor.com" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                </div>

                <!-- 3. Tax & Legal Identifiers -->
                <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                    <i class="bi bi-file-earmark-text me-1 text-primary"></i> Tax & Statutory Details
                </h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">GSTIN / Tax ID</label>
                        <input type="text" name="gst_number" class="form-control text-uppercase font-monospace" value="<?= htmlspecialchars($vendor['gst_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="27ABCDE1234F1Z5" maxlength="30" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">PAN Number</label>
                        <input type="text" name="pan_number" class="form-control text-uppercase font-monospace" value="<?= htmlspecialchars($vendor['pan_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="ABCDE1234F" maxlength="30" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                </div>

                <!-- 4. Bank Account Details -->
                <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                    <i class="bi bi-bank me-1 text-primary"></i> Bank Account for Settlements
                </h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($vendor['bank_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="HDFC Bank" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Account Number</label>
                        <input type="text" name="bank_account_number" class="form-control font-monospace" value="<?= htmlspecialchars($vendor['bank_account_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="50200012345678" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">IFSC Code</label>
                        <input type="text" name="bank_ifsc" class="form-control text-uppercase font-monospace" value="<?= htmlspecialchars($vendor['bank_ifsc'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="HDFC0000123" maxlength="20" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                </div>

                <!-- 5. Address & Location -->
                <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                    <i class="bi bi-geo-alt me-1 text-primary"></i> Address & Office Location
                </h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">Address Line 1</label>
                        <input type="text" name="address_line1" class="form-control" value="<?= htmlspecialchars($vendor['address_line1'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">Address Line 2</label>
                        <input type="text" name="address_line2" class="form-control" value="<?= htmlspecialchars($vendor['address_line2'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-dark">City</label>
                        <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($vendor['city'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-dark">State</label>
                        <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($vendor['state'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-dark">Postal / PIN Code</label>
                        <input type="text" name="postal_code" class="form-control" value="<?= htmlspecialchars($vendor['postal_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-dark">Country</label>
                        <input type="text" name="country" class="form-control" value="<?= htmlspecialchars($vendor['country'] ?? 'India', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                </div>

                <!-- 6. Additional Notes -->
                <div class="mb-4">
                    <label class="form-label small fw-semibold text-dark">Notes / Internal Procurement Remarks</label>
                    <textarea name="notes" class="form-control" rows="3" style="font-size: 13px; border-radius: var(--ks-radius-button);"><?= htmlspecialchars($vendor['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <a href="/vendors/<?= (int)$vendor['id'] ?>" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 24px;">
                        <i class="bi bi-check2"></i> Update Vendor
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
