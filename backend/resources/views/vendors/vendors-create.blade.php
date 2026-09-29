<?php
$pageTitle = 'Add Vendor / Supplier — KhelSutra';
$activePage = 'vendors';
$orgId = current_organization_id();

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
                <a href="/vendors" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Vendors Directory</a>
                <span class="text-muted small">/</span>
                <span class="text-dark small fw-semibold">Add New Vendor</span>
            </div>
            <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Add Supplier / Vendor</h1>
        </div>
        <div>
            <a href="/vendors" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                Cancel
            </a>
        </div>
    </div>

    <div class="card p-4 mx-auto" style="max-width: 900px; border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
        <form method="POST" action="/vendors/create">
            <!-- 1. Company Information -->
            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                <i class="bi bi-building me-1 text-primary"></i> Company & General Information
            </h5>

            <div class="row g-3 mb-4">
                <div class="col-md-8">
                    <label class="form-label small fw-semibold text-dark">Company / Vendor Name <span class="text-danger">*</span></label>
                    <input type="text" name="company_name" class="form-control" placeholder="e.g. Wilson Sports India Pvt Ltd, Shiv Naresh Apparels" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Vendor Code</label>
                    <input type="text" name="vendor_code" class="form-control" placeholder="Leave blank to auto-generate" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    <span class="text-muted" style="font-size: 11px;">e.g. VND-20260923-0001</span>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Vendor Category / Type</label>
                    <select name="vendor_type" class="form-select" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <option value="equipment">Sports Equipment & Gear</option>
                        <option value="apparel">Team Apparel & Uniforms</option>
                        <option value="nutrition">Nutrition & Supplements</option>
                        <option value="maintenance">Facility Maintenance & Repairs</option>
                        <option value="medical">Sports Medicine & Physiotherapy</option>
                        <option value="transport">Transportation & Logistics</option>
                        <option value="catering">Catering & Event Food Services</option>
                        <option value="general" selected>General Supplies</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Initial Status</label>
                    <select name="status" class="form-select" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <option value="active" selected>Active (Ready for procurement)</option>
                        <option value="inactive">Inactive (Pending approval or dormant)</option>
                        <option value="blacklisted">Blacklisted</option>
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
                    <input type="text" name="contact_person" class="form-control" placeholder="e.g. Rajesh Sharma (Key Account Manager)" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="sales@wilsonsports.in" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Primary Phone</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+91 98765 43210" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Alternate Phone</label>
                    <input type="tel" name="alternate_phone" class="form-control" placeholder="+91 11 2345 6789" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Website URL</label>
                    <input type="url" name="website" class="form-control" placeholder="https://www.wilsonsports.in" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
            </div>

            <!-- 3. Tax & Legal Identifiers -->
            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                <i class="bi bi-file-earmark-text me-1 text-primary"></i> Tax & Statutory Details
            </h5>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">GSTIN / Tax ID</label>
                    <input type="text" name="gst_number" class="form-control text-uppercase font-monospace" placeholder="e.g. 27ABCDE1234F1Z5" maxlength="30" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">PAN Number</label>
                    <input type="text" name="pan_number" class="form-control text-uppercase font-monospace" placeholder="e.g. ABCDE1234F" maxlength="30" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
            </div>

            <!-- 4. Bank Account Details -->
            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                <i class="bi bi-bank me-1 text-primary"></i> Bank Account for Settlements
            </h5>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" placeholder="e.g. HDFC Bank Ltd" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Account Number</label>
                    <input type="text" name="bank_account_number" class="form-control font-monospace" placeholder="e.g. 50200012345678" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">IFSC Code</label>
                    <input type="text" name="bank_ifsc" class="form-control text-uppercase font-monospace" placeholder="e.g. HDFC0000123" maxlength="20" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
            </div>

            <!-- 5. Address & Location -->
            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 8px;">
                <i class="bi bi-geo-alt me-1 text-primary"></i> Address & Office Location
            </h5>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Address Line 1</label>
                    <input type="text" name="address_line1" class="form-control" placeholder="Plot No. 42, Okhla Industrial Area Phase III" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Address Line 2</label>
                    <input type="text" name="address_line2" class="form-control" placeholder="Building B, 2nd Floor" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-dark">City</label>
                    <input type="text" name="city" class="form-control" placeholder="New Delhi" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-dark">State</label>
                    <input type="text" name="state" class="form-control" placeholder="Delhi" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-dark">Postal / PIN Code</label>
                    <input type="text" name="postal_code" class="form-control" placeholder="110020" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-dark">Country</label>
                    <input type="text" name="country" class="form-control" value="India" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
            </div>

            <!-- 6. Additional Notes -->
            <div class="mb-4">
                <label class="form-label small fw-semibold text-dark">Notes / Internal Procurement Remarks</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Authorized dealership, credit terms (Net 30 days), quality feedback, warranty details..." style="font-size: 13px; border-radius: var(--ks-radius-button);"></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <a href="/vendors" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 24px;">
                    <i class="bi bi-check2"></i> Save Vendor
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
