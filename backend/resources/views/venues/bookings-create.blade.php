<?php
$pageTitle = 'New Venue Booking — KhelSutra';
$activePage = 'venues';
$orgId = current_organization_id();

$db = \App\Services\BaseService::getDatabaseConnection();

// Selected venue or facility from query
$selectedVenueId = (int)($_GET['venue_id'] ?? 0);
$selectedFacilityId = (int)($_GET['facility_id'] ?? 0);

// Venues
$venueStmt = $db->prepare("SELECT id, name FROM venues WHERE organization_id = :org_id AND status = 'active' AND deleted_at IS NULL ORDER BY name ASC");
$venueStmt->execute([':org_id' => $orgId]);
$venues = $venueStmt ? $venueStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Facilities
$facStmt = $db->prepare("SELECT id, venue_id, name FROM venue_facilities WHERE organization_id = :org_id AND status = 'active' AND deleted_at IS NULL ORDER BY name ASC");
$facStmt->execute([':org_id' => $orgId]);
$facilities = $facStmt ? $facStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Teams
$teamStmt = $db->prepare("SELECT id, name FROM teams WHERE organization_id = :org_id AND status = 'active' AND deleted_at IS NULL ORDER BY name ASC");
$teamStmt->execute([':org_id' => $orgId]);
$teams = $teamStmt ? $teamStmt->fetchAll(PDO::FETCH_ASSOC) : [];

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
            <h1 class="h4 fw-bold mb-0" style="color: var(--ks-navy);">New Venue Booking</h1>
        </div>
    </div>

    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger mb-4 py-3 px-3 small d-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button);">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>
                <strong>Booking Failed:</strong> <?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    <?php endif; ?>

    <form action="/venues/bookings/create" method="POST" id="createBookingForm">
        <!-- Section 1. Venue Selection -->
        <div class="card p-4 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 10px;">
                1. Venue Selection
            </h5>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Select Venue <span class="text-danger">*</span></label>
                    <select name="venue_id" class="form-select" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <option value="">Select venue</option>
                        <?php foreach ($venues as $v): ?>
                            <option value="<?= (int)$v['id'] ?>" <?= $selectedVenueId == $v['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>


                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Booking Type <span class="text-danger">*</span></label>
                    <select name="booking_type" class="form-select" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <option value="Training">Squad Training</option>
                        <option value="Match">Official Match</option>
                        <option value="Tournament">Tournament Fixture</option>
                        <option value="Maintenance">Maintenance Window</option>
                        <option value="Private Event">Special Event</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Assigned Squad / Team (Optional)</label>
                    <select name="team_id" class="form-select" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <option value="">No team assigned</option>
                        <?php foreach ($teams as $tm): ?>
                            <option value="<?= (int)$tm['id'] ?>"><?= htmlspecialchars($tm['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold text-dark">Booking Purpose <span class="text-danger">*</span></label>
                    <input type="text" name="purpose" class="form-control" placeholder="e.g. U-18 Evening Tactical Drills & Penalty Practice" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
            </div>
        </div>

        <!-- Section 2: Date & Time Allocation -->
        <div class="card p-4 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <div class="d-flex align-items-center justify-content-between mb-3" style="border-bottom: 1px solid var(--ks-border-light); padding-bottom: 10px;">
                <h5 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 15px;">
                    2. Schedule & Conflict-Validated Slot(s)
                </h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addSlotBtn" style="border-radius: var(--ks-radius-button); font-weight: 600;">
                    <i class="bi bi-plus-lg"></i> Add Slot
                </button>
            </div>

            <div id="slotsContainer">
                <div class="row g-3 slot-row mb-3 pb-3 border-bottom position-relative align-items-start">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-dark">Facility Slot / Court <span class="text-danger">*</span></label>
                        <select name="facility_id[]" class="form-select facility-select" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                            <option value="">Select facility slot</option>
                            <?php foreach ($facilities as $f): ?>
                                <option value="<?= (int)$f['id'] ?>" <?= $selectedFacilityId == $f['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-dark">Booking Date <span class="text-danger">*</span></label>
                        <input type="date" name="booking_date[]" class="form-control" value="<?= date('Y-m-d') ?>" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-dark">Start Time <span class="text-danger">*</span></label>
                        <input type="time" name="start_time[]" class="form-control" value="16:00" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-dark">End Time <span class="text-danger">*</span></label>
                        <input type="time" name="end_time[]" class="form-control" value="18:00" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label d-block">&nbsp;</label>
                        <button type="button" class="btn btn-outline-danger w-100 btn-sm remove-slot-btn d-flex align-items-center justify-content-center" style="border-radius: var(--ks-radius-button); height: 38px;" disabled title="Remove Slot">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-dark">Booking Notes</label>
                        <textarea name="notes[]" class="form-control" rows="1" placeholder="e.g. Floodlights required after 17:30" style="font-size: 13px; border-radius: var(--ks-radius-button);"></textarea>
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
                <i class="bi bi-check2 me-1"></i> Confirm Booking
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('slotsContainer');
    const addBtn = document.getElementById('addSlotBtn');

    if(addBtn && container) {
        addBtn.addEventListener('click', function() {
            const rows = container.querySelectorAll('.slot-row');
            if (rows.length === 0) return;
            const firstRow = rows[0];
            const newRow = firstRow.cloneNode(true);

            // Clear values in new row
            newRow.querySelectorAll('input, select, textarea').forEach(input => {
                if (input.type === 'time' || input.type === 'date') {
                    // Keep default values for date and time or clear them?
                    // Retaining the cloned ones might be convenient, but let's clear them except date.
                    if (input.name === 'booking_date[]') {
                        input.value = firstRow.querySelector('[name="booking_date[]"]').value;
                    } else if (input.name === 'start_time[]') {
                        input.value = firstRow.querySelector('[name="start_time[]"]').value;
                    } else if (input.name === 'end_time[]') {
                        input.value = firstRow.querySelector('[name="end_time[]"]').value;
                    }
                } else {
                    input.value = '';
                }
            });

            // Enable remove button
            const removeBtn = newRow.querySelector('.remove-slot-btn');
            if(removeBtn) {
                removeBtn.removeAttribute('disabled');
            }
            
            // Add spacing between rows
            newRow.classList.add('mt-3', 'pt-3');

            container.appendChild(newRow);
            updateRemoveButtons();
        });

        container.addEventListener('click', function(e) {
            const btn = e.target.closest('.remove-slot-btn');
            if(btn && !btn.hasAttribute('disabled')) {
                const row = btn.closest('.slot-row');
                if(row) {
                    row.remove();
                    updateRemoveButtons();
                }
            }
        });

        function updateRemoveButtons() {
            const rows = container.querySelectorAll('.slot-row');
            rows.forEach((row, index) => {
                const btn = row.querySelector('.remove-slot-btn');
                if(btn) {
                    if(rows.length === 1) {
                        btn.setAttribute('disabled', 'true');
                    } else {
                        btn.removeAttribute('disabled');
                    }
                }
            });
        }
    }
});
</script>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
