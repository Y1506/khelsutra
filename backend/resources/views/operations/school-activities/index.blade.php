<?php
$activePage = 'events';
$title = 'Events Management — KhelSutra';
ob_start();
?>
<div class="ks-page-header">
    <div class="ks-header-text">
        <h2 class="ks-page-title">School Activities</h2>
        <p class="ks-page-subtitle">Track outreach programs and sports activities at schools</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-blue"><i class="bi bi-mortarboard"></i></div>
                <div><div class="ks-kpi-label">TOTAL ACTIVITIES</div><div class="ks-kpi-value" id="kpi-total">0</div></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-green"><i class="bi bi-people-fill"></i></div>
                <div><div class="ks-kpi-label">TOTAL PARTICIPANTS</div><div class="ks-kpi-value" id="kpi-participants">0</div></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-amber"><i class="bi bi-calendar-month"></i></div>
                <div><div class="ks-kpi-label">THIS MONTH</div><div class="ks-kpi-value" id="kpi-month">0</div></div>
            </div>
        </div>
    </div>
</div>

<div class="ks-filter-bar mb-3">
    <div class="ks-filter-grid">
        <input type="text" class="ks-form-control" id="filterSearch" placeholder="Search schools..." oninput="loadActivities()">
        <input type="month" class="ks-form-control" id="filterDate" onchange="loadActivities()">
        <button class="ks-btn ks-btn-secondary" onclick="clearFilters()"><i class="bi bi-x-circle"></i> Clear</button>
    </div>
</div>

<div class="ks-content-card">
    <div class="ks-card-header">
        <div class="ks-header-left">
            <i class="bi bi-mortarboard" style="color:var(--ks-primary);font-size:18px;"></i>
            <h3 class="ks-header-title">Activities List</h3>
            <span class="ks-badge ks-badge-scheduled ms-2" id="total-count">0</span>
        </div>
        <button class="ks-btn ks-btn-primary" data-bs-toggle="modal" data-bs-target="#createActivityModal">
            <i class="bi bi-plus-lg"></i> Log Activity
        </button>
    </div>
    <div class="ks-table-responsive">
        <table class="ks-table">
            <thead>
                <tr>
                    <th>School Name</th>
                    <th>Activity Name</th>
                    <th>Date</th>
                    <th>Sport</th>
                    <th>Participants</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody"></tbody>
        </table>
    </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createActivityModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: var(--ks-radius-modal); border: 1px solid var(--ks-border); box-shadow: var(--ks-shadow-modal);">
            <div class="modal-header">
                <h5 class="modal-title">Log School Activity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createActivityForm" onsubmit="handleCreate(event)">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">School Name *</label>
                            <input type="text" class="ks-form-control" name="school_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Activity Name *</label>
                            <input type="text" class="ks-form-control" name="activity_name" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Activity Date *</label>
                            <input type="date" class="ks-form-control" name="activity_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Participant Count *</label>
                            <input type="number" class="ks-form-control" name="participant_count" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Sport</label>
                            <input type="text" class="ks-form-control" name="sport">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notes</label>
                            <input type="text" class="ks-form-control" name="notes">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Venue ID</label>
                            <select  class="ks-form-control" name="venue_id">
<option value="">Select Venue...</option>
</select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Event ID</label>
                            <select  class="ks-form-control" name="event_id">
<option value="">Select Event...</option>
</select>
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="ks-btn ks-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="ks-btn ks-btn-primary">Save Activity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function clearFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterDate').value = '';
    loadActivities();
}

async function loadActivities() {
    try {
        const search = document.getElementById('filterSearch').value;
        const date = document.getElementById('filterDate').value;
        const query = new URLSearchParams({ search, date }).toString();
        
        const res = await fetch(`/api/v1/school-activities?${query}`);
        const json = await res.json();
        
        if (json.success) {
            updateTable(json.data.data);
            updateKPIs(json.data.data);
        }
    } catch (e) {
        ksToast('Failed to load activities', 'error');
    }
}

function updateKPIs(activities) {
    let totalParticipants = 0;
    let thisMonthCount = 0;
    
    const now = new Date();
    const currentMonth = now.getMonth();
    const currentYear = now.getFullYear();

    activities.forEach(a => {
        totalParticipants += parseInt(a.participant_count || 0, 10);
        
        if(a.activity_date) {
            const d = new Date(a.activity_date);
            if(d.getMonth() === currentMonth && d.getFullYear() === currentYear) {
                thisMonthCount++;
            }
        }
    });

    document.getElementById('kpi-total').textContent = activities.length;
    document.getElementById('kpi-participants').textContent = totalParticipants;
    document.getElementById('kpi-month').textContent = thisMonthCount;
    document.getElementById('total-count').textContent = activities.length;
}

function updateTable(activities) {
    const tbody = document.getElementById('tableBody');
    if (activities.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6"><div class="ks-empty-state">No school activities found</div></td></tr>`;
        return;
    }
    
    tbody.innerHTML = activities.map(a => `
        <tr>
            <td><strong>${ksEscape(a.school_name)}</strong></td>
            <td>${ksEscape(a.activity_name)}</td>
            <td>${ksEscape(a.activity_date)}</td>
            <td>${ksEscape(a.sport || '-')}</td>
            <td><span class="ks-badge ks-badge-scheduled">${ksEscape(a.participant_count)}</span></td>
            <td>
                <button class="ks-btn ks-btn-secondary ks-btn-sm text-danger" onclick="deleteActivity(${a.id})"><i class="bi bi-trash"></i></button>
            </td>
        </tr>
    `).join('');
}

async function handleCreate(e) {
    e.preventDefault();
    const form = e.target;
    if (!form.reportValidity()) return;
    
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const res = await fetch('/api/v1/school-activities', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            ksToast('Activity logged successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createActivityModal')).hide();
            form.reset();
            loadActivities();
        } else {
            ksToast('Failed to log activity', 'error');
        }
    } catch (err) {
        ksToast('Error saving activity', 'error');
    }
}

async function deleteActivity(id) {
    if (!confirm('Are you sure you want to delete this activity?')) return;
    try {
        const res = await fetch(`/api/v1/school-activities/${id}`, { method: 'DELETE' });
        const json = await res.json();
        if (json.success) {
            ksToast('Activity deleted', 'success');
            loadActivities();
        }
    } catch (e) {
        ksToast('Error deleting activity', 'error');
    }
}

document.addEventListener('DOMContentLoaded', loadActivities);
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
