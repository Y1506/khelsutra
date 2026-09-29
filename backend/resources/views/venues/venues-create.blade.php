<?php
$pageTitle = 'Add Venue — KhelSutra';
$activePage = 'venues';
$orgId = current_organization_id();

// Fetch sports
$db = \App\Services\BaseService::getDatabaseConnection();
$sportsStmt = $db->query("SELECT id, name FROM sports WHERE status = 'active' ORDER BY name ASC");
$sports = $sportsStmt ? $sportsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

ob_start();
?>

<div class="ks-content">
    <!-- Top Back Navigation -->
    <div class="mb-3">
        <a href="/venues" class="text-decoration-none text-muted small fw-medium">
            <i class="bi bi-arrow-left me-1"></i> Back to Venues
        </a>
    </div>

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0" style="color: var(--ks-navy);">Add Venue</h1>
        </div>
    </div>

    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger mb-4 py-2 px-3 small d-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button);">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <?php endif; ?>

    <form action="/venues/create" method="POST" id="createVenueForm">
        <!-- Section 1: Venue Information -->
        <div class="card p-4 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 10px;">
                1. Venue Profile & Details
            </h5>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Venue Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Apex Olympic Sports Arena" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Venue Type <span class="text-danger">*</span></label>
                    <select name="venue_type" class="form-select" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <option value="Sports Complex">Sports Complex</option>
                        <option value="Stadium">Stadium</option>
                        <option value="Indoor Arena">Indoor Arena</option>
                        <option value="Training Ground">Training Ground</option>
                        <option value="Swimming Pool">Swimming Complex</option>
                        <option value="Badminton Hall">Badminton Hall</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Spectator Capacity</label>
                    <input type="number" name="capacity" class="form-control" placeholder="e.g. 5000" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Opening Time <span class="text-danger">*</span></label>
                    <input type="time" name="opening_time" class="form-control" value="06:00" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Closing Time <span class="text-danger">*</span></label>
                    <input type="time" name="closing_time" class="form-control" value="22:00" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Operating Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="under_maintenance">Under Maintenance</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label small fw-semibold text-dark">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="Main features, floodlights, turf quality" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
            </div>
        </div>

        <!-- Section 2: Address & Location -->
        <div class="card p-4 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 10px;">
                2. Location & Address
            </h5>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label small fw-semibold text-dark">Address Line</label>
                    <input type="text" name="address_line1" class="form-control" placeholder="Plot 10, Sports City Complex" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">City <span class="text-danger">*</span></label>
                    <input type="text" name="city" class="form-control" value="Mumbai" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">State <span class="text-danger">*</span></label>
                    <input type="text" name="state" class="form-control" value="Maharashtra" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Postal Code</label>
                    <input type="text" name="postal_code" class="form-control" value="400001" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
            </div>
        </div>

        <!-- Section 3: Facilities -->
        <div class="card p-4 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <div class="d-flex justify-content-between align-items-center mb-3" style="border-bottom: 1px solid var(--ks-border-light); padding-bottom: 10px;">
                <h5 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 15px;">
                    3. Facilities (Optional)
                </h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addFacilityBtn" style="border-radius: var(--ks-radius-button); font-weight: 600;">
                    <i class="bi bi-plus-lg"></i> Add Facility
                </button>
            </div>

            <div id="facilitiesContainer">
                <div class="row g-3 facility-row mb-3 pb-3 border-bottom">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-dark">Facility Name</label>
                        <input type="text" name="facility_name[]" class="form-control" placeholder="e.g. Main Turf Pitch 1" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-dark">Facility Type</label>
                        <input type="text" name="facility_type[]" class="form-control" placeholder="e.g. Grass Turf" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-dark">Capacity</label>
                        <input type="number" name="facility_capacity[]" class="form-control" placeholder="50" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-dark">Supported Sports</label>
                        <div class="dropdown">
                            <button class="form-select text-start sport-dropdown-btn d-flex align-items-center justify-content-between" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside" style="font-size: 13px; border-radius: var(--ks-radius-button); height: 38px; background-color: #fff;">
                                <span class="btn-text text-muted text-truncate" style="max-width: 90%;">Select Sports...</span>
                            </button>
                            <ul class="dropdown-menu w-100 p-2 shadow-sm" style="font-size: 13px; max-height: 220px; overflow-y: auto; border-radius: var(--ks-radius-card);">
                                <?php foreach ($sports as $sport): ?>
                                    <li>
                                        <div class="form-check m-0 py-1">
                                            <input class="form-check-input sport-cb" type="checkbox" name="facility_sports_0[]" value="<?= $sport['id'] ?>" id="sport_0_<?= $sport['id'] ?>">
                                            <label class="form-check-label w-100" for="sport_0_<?= $sport['id'] ?>" style="cursor: pointer;">
                                                <?= htmlspecialchars($sport['name']) ?>
                                            </label>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label d-block">&nbsp;</label>
                        <button type="button" class="btn btn-outline-danger btn-sm remove-facility-btn w-100 d-flex align-items-center justify-content-center" style="border-radius: var(--ks-radius-button); height: 38px;" disabled title="Remove Facility">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="d-flex align-items-center justify-content-end gap-3 mb-5">
            <a href="/venues" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 10px 24px;">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 10px 24px;">
                <i class="bi bi-check2 me-1"></i> Save Venue
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addFacilityBtn = document.getElementById('addFacilityBtn');
    if (addFacilityBtn) {
        addFacilityBtn.addEventListener('click', function() {
            const container = document.getElementById('facilitiesContainer');
            const firstRow = container.querySelector('.facility-row');
            const newRow = firstRow.cloneNode(true);
            
            // Clear inputs
            newRow.querySelectorAll('input').forEach(input => input.value = '');
            
            // Enable remove button
            const removeBtn = newRow.querySelector('.remove-facility-btn');
            removeBtn.disabled = false;
            removeBtn.addEventListener('click', function() {
                newRow.remove();
            });
            
            container.appendChild(newRow);
        });
    }
});

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('sport-cb')) {
        const dropdown = e.target.closest('.dropdown');
        if (dropdown) {
            const btnText = dropdown.querySelector('.sport-dropdown-btn .btn-text');
            const checked = dropdown.querySelectorAll('.sport-cb:checked');
            if (checked.length === 0) {
                btnText.textContent = 'Select Sports...';
            } else if (checked.length === 1) {
                btnText.textContent = checked[0].nextElementSibling.textContent.trim();
            } else {
                btnText.textContent = checked.length + ' sports selected';
            }
        }
    }
});
</script>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
