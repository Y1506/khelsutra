<?php
$activePage = 'operations_venues';
$title = 'Operations Venues Management — KhelSutra';
ob_start();
?>
<?php
$title = "Facilities";
$pageHeader = "Venue Facilities";
$pageSubheader = "Manage specific areas within a venue.";
ob_start();
// In real app we'd fetch venue name based on $venueId passed via view data
$venueId = $data['venueId'] ?? 0;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/operations/venues" class="text-decoration-none text-muted mb-2 d-inline-block">
            <i class="bi bi-arrow-left me-1"></i> Back to Venues
        </a>
    </div>
    <div class="d-flex gap-2">
        <button class="ks-btn ks-btn-primary" data-bs-toggle="modal" data-bs-target="#newFacilityModal">
            <i class="bi bi-plus-lg me-1"></i> Add Facility
        </button>
    </div>
</div>

<div class="ks-card" style="padding: 0;">
    <div class="table-responsive">
        <table class="table ks-table mb-0">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 30%;">Facility Name</th>
                    <th style="width: 20%;">Type</th>
                    <th style="width: 15%;">Capacity</th>
                    <th style="width: 15%;">Status</th>
                    <th style="width: 15%; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="facilitiesTableBody">
                <!-- Data loaded via JS -->
            </tbody>
        </table>
    </div>
</div>

<!-- Modal for New Facility -->
<div class="modal fade" id="newFacilityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--ks-radius-modal); border: 1px solid var(--ks-border); box-shadow: var(--ks-shadow-modal);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-navy" style="font-size: 18px;">Add New Facility</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <form id="facilityForm">
                    <div class="mb-3">
                        <label class="ks-form-label">Facility Name *</label>
                        <input type="text" class="ks-form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="ks-form-label">Facility Type</label>
                        <input type="text" class="ks-form-control" name="facility_type" placeholder="e.g. Indoor Court, Swimming Pool">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="ks-form-label">Capacity</label>
                            <input type="number" class="ks-form-control" name="capacity">
                        </div>
                        <div class="col-6">
                            <label class="ks-form-label">Status *</label>
                            <select class="ks-form-select" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="under_maintenance">Under Maintenance</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="ks-btn ks-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="ks-btn ks-btn-primary" onclick="saveFacility()">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
const venueId = <?= json_encode($venueId) ?>;

document.addEventListener('DOMContentLoaded', loadFacilities);

function loadFacilities() {
    fetch('/api/v1/venues/' + venueId + '/facilities')
        .then(res => res.json())
        .then(res => {
            const tbody = document.getElementById('facilitiesTableBody');
            tbody.innerHTML = '';
            if (res.data && res.data.data) {
                const escapeHtml = (unsafe) => {
                    if (unsafe == null) return '';
                    return String(unsafe)
                         .replace(/&/g, "&amp;")
                         .replace(/</g, "&lt;")
                         .replace(/>/g, "&gt;")
                         .replace(/"/g, "&quot;")
                         .replace(/'/g, "&#039;");
                };
                res.data.data.forEach((f, index) => {
                    tbody.innerHTML += `
                        <tr>
                            <td>${index + 1}</td>
                            <td><div class="fw-semibold text-navy">${escapeHtml(f.name)}</div></td>
                            <td>${escapeHtml(f.facility_type) || '-'}</td>
                            <td>${escapeHtml(f.capacity) || '-'}</td>
                            <td>
                                <span class="ks-badge ks-badge-${f.status === 'active' ? 'success' : 'warning'}">
                                    ${escapeHtml(f.status)}
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <button onclick="deleteFacility(${f.id})" class="ks-btn ks-btn-secondary text-danger" style="height: 32px; padding: 0 10px; font-size: 12px;">Del</button>
                            </td>
                        </tr>
                    `;
                });
            }
        });
}

function saveFacility() {
    const form = document.getElementById('facilityForm');
    const data = Object.fromEntries(new FormData(form).entries());
    
    fetch('/api/v1/venues/' + venueId + '/facilities', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(res => res.json()).then(res => {
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('newFacilityModal')).hide();
            form.reset();
            loadFacilities();
        } else {
            alert('Error: ' + JSON.stringify(res.errors || res.message));
        }
    });
}

function deleteFacility(id) {
    if (confirm("Are you sure you want to delete this facility?")) {
        fetch('/api/v1/venues/' + venueId + '/facilities/' + id, { method: 'DELETE' })
            .then(res => res.json())
            .then(res => {
                if (res.success) loadFacilities();
                else alert(res.message);
            });
    }
}
</script>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../../layouts/app.blade.php';
?>

<script>
// Auto-injected Context-Aware Dropdowns
document.addEventListener('DOMContentLoaded', loadGlobalDropdowns);

async function fetchDropdownData(url) {
    try {
        const res = await fetch(url).then(r => r.json());
        if (res.data && res.data.data) return res.data.data;
        if (res.data) return res.data;
        return [];
    } catch (e) {
        console.error('Error fetching ' + url, e);
        return [];
    }
}

async function populateSelect(selector, url, labelFn) {
    const select = document.querySelector(selector);
    if (!select) return;
    const defaultText = select.options[0] ? select.options[0].text : 'Select...';
    select.innerHTML = '<option value="">Loading...</option>';
    const data = await fetchDropdownData(url);
    select.innerHTML = `<option value="">${defaultText}</option>`;
    data.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = labelFn(item);
        select.appendChild(opt);
    });
}

async function loadGlobalDropdowns() {
    const escapeHtml = typeof ksEscape === 'function' ? ksEscape : (s) => String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;");
    
    // Venues
    const venueSels = ['#createVenue', '#createVenueHK', '#bookingVenue', '#eventVenue', '#activityVenue', '[name="venue_id"]'];
    venueSels.forEach(sel => {
        populateSelect(sel, '/api/v1/venues?limit=100', v => escapeHtml(v.name));
    });

    // Employees
    const empSels = ['#createEmployee', '#createEmployeeHK', '#eventOrganizer', '#vehicleDriver', '#tripDriver', '[name="organizer_employee_id"]', '#v_driver', '#pt_driver'];
    empSels.forEach(sel => {
        populateSelect(sel, '/api/v1/employees?limit=200', e => escapeHtml((e.first_name || '') + ' ' + (e.last_name || '')).trim());
    });

    // Vendors
    const vendorSels = ['#createVendor'];
    vendorSels.forEach(sel => {
        populateSelect(sel, '/api/v1/vendors?limit=100', v => escapeHtml(v.vendor_name || v.name));
    });

    // Events
    const eventSels = ['#bookingEvent', '#tripEvent', '#activityEvent', '#pt_event_id', '#bEventId', '[name="event_id"]'];
    eventSels.forEach(sel => {
        populateSelect(sel, '/api/v1/events?limit=100', e => escapeHtml(e.name || e.event_reference));
    });

    // Teams
    const teamSels = ['#bTeamId', '[name="team_id"]'];
    teamSels.forEach(sel => {
        populateSelect(sel, '/api/v2/teams?limit=100', t => escapeHtml(t.name || t.team_name || t.id)); // Using fallback endpoint if needed
    });

    // Tournaments
    const tournSels = ['#bTournamentId', '[name="tournament_id"]'];
    tournSels.forEach(sel => {
        populateSelect(sel, '/api/v1/tournaments?limit=100', t => escapeHtml(t.name || t.tournament_name || t.id)); 
    });

    // Cascading Facilities
    const venueFacilityMap = [
        ['#createVenue', '#createFacility'],
        ['#createVenueHK', '#createFacilityHK'],
        ['#bookingVenue', '#bookingFacility'],
        ['#bVenueId', '#bFacilityId'],
        ['[name="venue_id"]', '[name="facility_id"]']
    ];
    
    for (const [vSel, fSel] of venueFacilityMap) {
        const vSelect = document.querySelector(vSel);
        const fSelect = document.querySelector(fSel);
        if (vSelect && fSelect) {
            vSelect.addEventListener('change', async (e) => {
                const venueId = e.target.value;
                if (!venueId) {
                    fSelect.innerHTML = '<option value="">Select Facility...</option>';
                    return;
                }
                fSelect.innerHTML = '<option value="">Loading...</option>';
                const data = await fetchDropdownData(`/api/v1/venues/${venueId}/facilities`);
                fSelect.innerHTML = '<option value="">Select Facility...</option>';
                data.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f.id;
                    opt.textContent = `${escapeHtml(f.name)} (${escapeHtml(f.facility_type)})`;
                    fSelect.appendChild(opt);
                });
            });
        }
    }
}
</script>
