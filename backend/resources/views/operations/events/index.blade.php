<?php
$activePage = 'events';
$title = 'Events Management — KhelSutra';
ob_start();
?>
<div class="ks-page-header">
    <div class="ks-header-text">
        <h2 class="ks-page-title">Events Management</h2>
        <p class="ks-page-subtitle">Plan, coordinate and track sports events and activities</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-blue"><i class="bi bi-calendar2-star"></i></div>
                <div><div class="ks-kpi-label">TOTAL EVENTS</div><div class="ks-kpi-value" id="kpi-total">0</div></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-blue"><i class="bi bi-calendar-check"></i></div>
                <div><div class="ks-kpi-label">PLANNED</div><div class="ks-kpi-value" id="kpi-planned">0</div></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-amber"><i class="bi bi-play-circle-fill"></i></div>
                <div><div class="ks-kpi-label">ONGOING</div><div class="ks-kpi-value" id="kpi-ongoing">0</div></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                <div><div class="ks-kpi-label">COMPLETED</div><div class="ks-kpi-value" id="kpi-completed">0</div></div>
            </div>
        </div>
    </div>
</div>

<div class="ks-filter-bar mb-3">
    <div class="ks-filter-grid">
        <input type="text" class="ks-form-control" id="filterSearch" placeholder="Search..." oninput="loadEvents()">
        <select class="ks-form-select" id="filterStatus" onchange="loadEvents()">
            <option value="">All Status</option>
            <option value="draft">Draft</option>
            <option value="planned">Planned</option>
            <option value="ongoing">Ongoing</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <button class="ks-btn ks-btn-secondary" onclick="clearFilters()"><i class="bi bi-x-circle"></i> Clear</button>
    </div>
</div>

<div class="ks-content-card">
    <div class="ks-card-header">
        <div class="ks-header-left">
            <i class="bi bi-calendar2-star" style="color:var(--ks-primary);font-size:18px;"></i>
            <h3 class="ks-header-title">Events</h3>
            <span class="ks-badge ks-badge-scheduled ms-2" id="total-count">0</span>
        </div>
        <button class="ks-btn ks-btn-primary" data-bs-toggle="modal" data-bs-target="#createEventModal">
            <i class="bi bi-plus-lg"></i> Create Event
        </button>
    </div>
    <div class="ks-table-responsive">
        <table class="ks-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Name & Type</th>
                    <th>Dates</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody"></tbody>
        </table>
    </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createEventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: var(--ks-radius-modal); border: 1px solid var(--ks-border); box-shadow: var(--ks-shadow-modal);">
            <div class="modal-header">
                <h5 class="modal-title">Create New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createEventForm" onsubmit="handleCreate(event)">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Name *</label>
                            <input type="text" class="ks-form-control" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Event Type *</label>
                            <input type="text" class="ks-form-control" name="event_type" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="ks-form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="ks-form-control" name="start_time">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="ks-form-control" name="end_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time</label>
                            <input type="time" class="ks-form-control" name="end_time">
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
                            <label class="form-label">Organizer Employee ID</label>
                            <select  class="ks-form-control" name="organizer_employee_id">
<option value="">Select Employee...</option>
</select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="ks-form-select" name="status">
                                <option value="planned">Planned</option>
                                <option value="draft">Draft</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description</label>
                            <input type="text" class="ks-form-control" name="description">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="ks-form-control" name="notes" rows="2"></textarea>
                    </div>
                    <div class="text-end">
                        <button type="button" class="ks-btn ks-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="ks-btn ks-btn-primary">Create Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Drawer Overlay -->
<div id="eventDrawer-overlay" onclick="ksDrawerClose('eventDrawer')"
     style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1040;opacity:0;visibility:hidden;transition:opacity .3s;"></div>
<!-- Side Drawer -->
<div id="eventDrawer"
     style="position:fixed;top:0;right:0;width:520px;height:100vh;background:#fff;z-index:1050;
            transform:translateX(100%);visibility:hidden;transition:transform .3s ease;
            overflow-y:auto;box-shadow:-4px 0 24px rgba(0,0,0,.12);">
    <div style="padding:24px 24px 0;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--ks-border);padding-bottom:16px;margin-bottom:20px;">
        <div><h5 style="font-weight:700;color:var(--ks-navy);margin:0;" id="drawerTitle">Event Details</h5></div>
        <button class="btn-close" onclick="ksDrawerClose('eventDrawer')"></button>
    </div>
    <div style="padding:0 24px 24px;" id="drawerContent">
        <!-- Content will be injected here -->
    </div>
</div>

<script>
let allEvents = [];
let currentEventId = null;

function clearFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterStatus').value = '';
    loadEvents();
}

async function loadEvents() {
    try {
        const search = document.getElementById('filterSearch').value;
        const status = document.getElementById('filterStatus').value;
        const query = new URLSearchParams({ search, status }).toString();
        
        const res = await fetch(`/api/v1/events?${query}`);
        const json = await res.json();
        
        if (json.success) {
            allEvents = json.data.data;
            updateTable(allEvents);
            updateKPIs(json.data.data); // Or perform another fetch if needed
        }
    } catch (e) {
        ksToast('Failed to load events', 'error');
    }
}

function updateKPIs(events) {
    let planned = 0, ongoing = 0, completed = 0;
    events.forEach(e => {
        if (e.status === 'planned') planned++;
        else if (e.status === 'ongoing') ongoing++;
        else if (e.status === 'completed') completed++;
    });
    document.getElementById('kpi-total').textContent = events.length;
    document.getElementById('kpi-planned').textContent = planned;
    document.getElementById('kpi-ongoing').textContent = ongoing;
    document.getElementById('kpi-completed').textContent = completed;
    document.getElementById('total-count').textContent = events.length;
}

function getBadgeClass(status) {
    switch (status) {
        case 'draft': return 'ks-badge-scheduled';
        case 'planned': return 'ks-badge-scheduled';
        case 'ongoing': return 'ks-badge-pending';
        case 'completed': return 'ks-badge-confirmed';
        case 'cancelled': return 'ks-badge-cancelled';
        default: return 'ks-badge-scheduled';
    }
}

function updateTable(events) {
    const tbody = document.getElementById('tableBody');
    if (events.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5"><div class="ks-empty-state">No events found</div></td></tr>`;
        return;
    }
    
    tbody.innerHTML = events.map(e => `
        <tr>
            <td>${ksEscape(e.event_reference || '-')}</td>
            <td>
                <strong>${ksEscape(e.name)}</strong><br>
                <small class="text-muted">${ksEscape(e.event_type)}</small>
            </td>
            <td>
                ${ksEscape(e.start_date)} ${ksEscape(e.start_time || '')} <br>
                <small class="text-muted">to ${ksEscape(e.end_date)} ${ksEscape(e.end_time || '')}</small>
            </td>
            <td><span class="ks-badge ${getBadgeClass(e.status)}">${ksEscape(e.status).toUpperCase()}</span></td>
            <td>
                <button class="ks-btn ks-btn-secondary ks-btn-sm" onclick="viewDetails(${e.id})"><i class="bi bi-eye"></i></button>
                <button class="ks-btn ks-btn-secondary ks-btn-sm text-danger" onclick="deleteEvent(${e.id})"><i class="bi bi-trash"></i></button>
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
        const res = await fetch('/api/v1/events', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            ksToast('Event created successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createEventModal')).hide();
            form.reset();
            loadEvents();
        } else {
            ksToast('Failed to create event', 'error');
        }
    } catch (err) {
        ksToast('Error creating event', 'error');
    }
}

async function deleteEvent(id) {
    if (!confirm('Are you sure you want to delete this event?')) return;
    try {
        const res = await fetch(`/api/v1/events/${id}`, { method: 'DELETE' });
        const json = await res.json();
        if (json.success) {
            ksToast('Event deleted', 'success');
            loadEvents();
        }
    } catch (e) {
        ksToast('Error deleting event', 'error');
    }
}

function viewDetails(id) {
    currentEventId = id;
    const event = allEvents.find(e => e.id === id);
    if (!event) return;
    
    let statusActions = '';
    if (event.status === 'draft') {
        statusActions = `<button class="ks-btn ks-btn-primary ks-btn-sm mt-2" onclick="updateStatus('planned')">Publish (Plan)</button>`;
    } else if (event.status === 'planned') {
        statusActions = `<button class="ks-btn ks-btn-primary ks-btn-sm mt-2" onclick="updateStatus('ongoing')">Mark Ongoing</button>`;
    } else if (event.status === 'ongoing') {
        statusActions = `
            <button class="ks-btn ks-btn-success ks-btn-sm mt-2 me-2" onclick="updateStatus('completed')">Mark Completed</button>
            <button class="ks-btn ks-btn-danger ks-btn-sm mt-2" onclick="updateStatus('cancelled')">Cancel Event</button>
        `;
    } else {
        statusActions = `<p class="text-muted mt-2">No further actions available.</p>`;
    }

    document.getElementById('drawerContent').innerHTML = `
        <div class="mb-4">
            <h6>Reference: ${ksEscape(event.event_reference || '-')}</h6>
            <p><strong>Name:</strong> ${ksEscape(event.name)}<br>
            <strong>Type:</strong> ${ksEscape(event.event_type)}</p>
            <p><strong>Status:</strong> <span class="ks-badge ${getBadgeClass(event.status)}">${ksEscape(event.status).toUpperCase()}</span></p>
            <p><strong>Description:</strong> ${ksEscape(event.description || '-')}</p>
        </div>
        
        <div class="mb-4 p-3" style="background:#f8f9fa; border-radius:6px;">
            <h6 class="mb-2">Update Status</h6>
            ${statusActions}
        </div>
        
        <div class="mb-4">
            <h6 class="mb-3">Participants</h6>
            <form onsubmit="addParticipant(event)" class="d-flex gap-2 mb-3">
                <select class="ks-form-select" id="part_type" required>
                    <option value="">Type...</option>
                    <option value="athlete">Athlete</option>
                    <option value="employee">Employee</option>
                    <option value="coach">Coach</option>
                    <option value="team">Team</option>
                </select>
                <input type="number" class="ks-form-control" id="part_id" placeholder="ID" required>
                <button type="submit" class="ks-btn ks-btn-primary">Add</button>
            </form>
            <p class="text-muted"><small>Participant listing relies on separate fetch or included data (simplified here).</small></p>
        </div>
    `;
    
    ksDrawerOpen('eventDrawer');
}

async function updateStatus(newStatus) {
    if(!currentEventId) return;
    try {
        const res = await fetch(`/api/v1/events/${currentEventId}`, {
            method: 'PATCH',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ status: newStatus })
        });
        const json = await res.json();
        if(json.success) {
            ksToast('Status updated', 'success');
            loadEvents();
            viewDetails(currentEventId); // reload drawer
        }
    } catch(e) {
        ksToast('Error updating status', 'error');
    }
}

async function addParticipant(e) {
    e.preventDefault();
    if(!currentEventId) return;
    
    const type = document.getElementById('part_type').value;
    const id = document.getElementById('part_id').value;
    
    try {
        const res = await fetch(`/api/v1/events/${currentEventId}/participants`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ participant_type: type, participant_id: id })
        });
        const json = await res.json();
        if(json.success) {
            ksToast('Participant added', 'success');
            document.getElementById('part_type').value = '';
            document.getElementById('part_id').value = '';
        } else {
            ksToast('Failed to add participant', 'error');
        }
    } catch(err) {
        ksToast('Error adding participant', 'error');
    }
}

document.addEventListener('DOMContentLoaded', loadEvents);
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
