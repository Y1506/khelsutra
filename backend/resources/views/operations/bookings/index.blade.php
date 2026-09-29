<?php
$activePage = 'bookings';
$title = 'Bookings Management — KhelSutra';
ob_start();
?>

<!-- Alternatives Modal -->
<div class="modal fade" id="alternativesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Booking Conflict</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>The requested slot is already booked. Here are some alternative suggestions:</p>
                <div id="alternativesList" class="list-group">
                </div>
            </div>
        </div>
    </div>
</div>

<?php
ob_start();
?>
<style>
.status-filter-btn {
    padding: 0.375rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
}
.status-filter-btn.active {
    background-color: var(--ks-primary);
    color: white;
    border-color: var(--ks-primary);
}
</style>

<div class="ks-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="ks-page-title mb-1">Venue Bookings</h2>
            <p class="ks-page-subtitle mb-0">Manage facility reservations and event scheduling</p>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-blue"><i class="bi bi-calendar-plus"></i></div>
                <div>
                    <div class="ks-kpi-label">TOTAL BOOKINGS</div>
                    <div class="ks-kpi-value" id="kpi-total">—</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-amber"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="ks-kpi-label">PENDING</div>
                    <div class="ks-kpi-value" id="kpi-pending">—</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-green"><i class="bi bi-calendar-check"></i></div>
                <div>
                    <div class="ks-kpi-label">APPROVED</div>
                    <div class="ks-kpi-value" id="kpi-approved">—</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-red"><i class="bi bi-x-circle"></i></div>
                <div>
                    <div class="ks-kpi-label">CANCELLED</div>
                    <div class="ks-kpi-value" id="kpi-cancelled">—</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="ks-filter-bar mb-3">
    <div class="ks-filter-grid align-items-center">
        <div class="input-group" style="width: auto;">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-calendar3"></i></span>
            <input type="date" class="form-control border-start-0" id="filterDate" onchange="loadBookings()">
        </div>
        
        <div class="btn-group" role="group" id="statusFilterGroup" style="flex: 0 0 auto !important; width: auto !important;">
            <input type="radio" class="btn-check" name="statusFilter" id="statusAll" value="" autocomplete="off" checked onchange="loadBookings()">
            <label class="btn btn-outline-secondary status-filter-btn" for="statusAll">All</label>

            <input type="radio" class="btn-check" name="statusFilter" id="statusPending" value="pending" autocomplete="off" onchange="loadBookings()">
            <label class="btn btn-outline-secondary status-filter-btn" for="statusPending">Pending</label>

            <input type="radio" class="btn-check" name="statusFilter" id="statusApproved" value="approved" autocomplete="off" onchange="loadBookings()">
            <label class="btn btn-outline-secondary status-filter-btn" for="statusApproved">Approved</label>
            
            <input type="radio" class="btn-check" name="statusFilter" id="statusRejected" value="rejected" autocomplete="off" onchange="loadBookings()">
            <label class="btn btn-outline-secondary status-filter-btn" for="statusRejected">Rejected</label>
            
            <input type="radio" class="btn-check" name="statusFilter" id="statusCancelled" value="cancelled" autocomplete="off" onchange="loadBookings()">
            <label class="btn btn-outline-secondary status-filter-btn" for="statusCancelled">Cancelled</label>
        </div>

        <div style="flex: 0 0 auto !important; width: auto !important;"><button class="ks-btn ks-btn-secondary" onclick="clearFilters()"><i class="bi bi-x-circle"></i> Clear</button></div>
        
        <div class="ms-auto">
            <button class="ks-btn ks-btn-primary" onclick="openCreateModal()"><i class="bi bi-plus-lg"></i> New Booking</button>
        </div>
    </div>
</div>

<div class="ks-content-card">
    <div>
        <table class="ks-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Venue / Facility</th>
                    <th>Date</th>
                    <th>Time Slot</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="bookingsTableBody">
                <!-- Data populated here -->
            </tbody>
        </table>
    </div>
</div>

<!-- Create Booking Modal -->
<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: var(--ks-radius-modal); border: 1px solid var(--ks-border); box-shadow: var(--ks-shadow-modal);">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold">New Venue Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="bookingErrorMsg" class="alert alert-danger d-none mb-3"><i class="bi bi-exclamation-triangle me-2"></i><span id="bookingErrorText"></span></div>
                
                <form id="bookingForm" class="needs-validation" novalidate>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Venue *</label>
                            <select class="ks-form-control" id="bVenueId" required placeholder="Venue ID">
<option value="">Select Venue...</option>
</select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Facility</label>
                            <select class="ks-form-control" id="bFacilityId" placeholder="Facility ID (Optional)">
<option value="">Select Facility...</option>
</select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Date *</label>
                            <input type="date" class="ks-form-control" id="bDate" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Time *</label>
                            <input type="time" class="ks-form-control" id="bStartTime" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Time *</label>
                            <input type="time" class="ks-form-control" id="bEndTime" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Purpose *</label>
                            <input type="text" class="ks-form-control" id="bPurpose" required placeholder="e.g. Practice Session">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Booking Type</label>
                            <select class="ks-form-select" id="bType">
                                <option value="practice">Practice</option>
                                <option value="match">Match</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="event">Event</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Team ID</label>
                            <select class="ks-form-control" id="bTeamId">
<option value="">Select Team...</option>
</select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Event ID</label>
                            <select class="ks-form-control" id="bEventId">
<option value="">Select Event...</option>
</select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tournament ID</label>
                            <select class="ks-form-control" id="bTournamentId">
<option value="">Select Tournament...</option>
</select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="ks-form-control" id="bNotes" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn ks-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn ks-btn-primary" onclick="saveBooking()">Save Booking</button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel/Reject Modal -->
<div class="modal fade" id="actionReasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--ks-radius-modal); border: 1px solid var(--ks-border); box-shadow: var(--ks-shadow-modal);">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold" id="actionReasonTitle">Provide Reason</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <form id="actionReasonForm">
                    <input type="hidden" id="actionBookingId">
                    <input type="hidden" id="actionType">
                    <label class="form-label">Reason *</label>
                    <textarea class="ks-form-control" id="actionReasonInput" rows="3" required></textarea>
                </form>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn ks-btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn ks-btn-primary" id="actionReasonBtn" onclick="submitActionReason()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Set active class on status toggle buttons
    document.querySelectorAll('input[name="statusFilter"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            document.querySelectorAll('.status-filter-btn').forEach(l => l.classList.remove('active'));
            if(e.target.checked) {
                document.querySelector(`label[for="${e.target.id}"]`).classList.add('active');
            }
        });
    });
    // Trigger initial styling
    document.querySelector('label[for="statusAll"]').classList.add('active');
    
    loadBookings();
});

async function loadBookings() {
    const date = document.getElementById('filterDate').value;
    const status = document.querySelector('input[name="statusFilter"]:checked').value;
    
    let url = '/api/v1/bookings';
    let params = [];
    if (date) params.push(`date=${encodeURIComponent(date)}`);
    if (status) params.push(`status=${encodeURIComponent(status)}`);
    if (params.length) url += '?' + params.join('&');
    
    try {
        const res = await fetch(url);
        const json = await res.json();
        if (json.success) {
            let data = json.data.data || json.data || [];
            renderTable(data);
            updateKPIs(data); // If the API is filtered, KPIs will reflect filtered data unless we fetch all. This is fine.
        } else {
            ksToast('Failed to load bookings', 'error');
        }
    } catch (e) {
        renderTable([]);
        ksToast('Error loading bookings', 'error');
    }
}

function clearFilters() {
    document.getElementById('filterDate').value = '';
    document.getElementById('statusAll').checked = true;
    document.querySelectorAll('.status-filter-btn').forEach(l => l.classList.remove('active'));
    document.querySelector('label[for="statusAll"]').classList.add('active');
    loadBookings();
}

function updateKPIs(data) {
    document.getElementById('kpi-total').innerText = data.length;
    document.getElementById('kpi-pending').innerText = data.filter(b => b.status === 'pending').length;
    document.getElementById('kpi-approved').innerText = data.filter(b => b.status === 'approved').length;
    document.getElementById('kpi-cancelled').innerText = data.filter(b => b.status === 'cancelled').length;
}

function renderTable(data) {
    const tbody = document.getElementById('bookingsTableBody');
    tbody.innerHTML = '';
    
    if (data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7"><div class="ks-empty-state"><i class="bi bi-calendar-x fs-2 text-muted"></i><p class="mt-2 mb-0">No bookings found</p></div></td></tr>`;
        return;
    }
    
    data.forEach(item => {
        let badgeClass = 'ks-badge-pending';
        if (item.status === 'approved') badgeClass = 'ks-badge-confirmed';
        else if (item.status === 'rejected') badgeClass = 'ks-badge-rejected';
        else if (item.status === 'cancelled') badgeClass = 'ks-badge-cancelled';
        else if (item.status === 'completed') badgeClass = 'ks-badge-scheduled';
        
        let actions = `
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" title="Actions" style="border: none; background: transparent;">
                    <i class="bi bi-three-dots-vertical text-dark"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:13px; min-width:160px; border-radius: var(--ks-radius-md);">
        `;

        if (item.status === 'pending') {
            actions += `
                    <li><a class="dropdown-item py-2 fw-medium text-success" href="#" onclick="updateStatus(${item.id}, 'approved'); return false;"><i class="bi bi-check-lg me-2"></i>Approve</a></li>
                    <li><a class="dropdown-item py-2 fw-medium text-danger" href="#" onclick="openReasonModal(${item.id}, 'rejected'); return false;"><i class="bi bi-x-lg me-2"></i>Reject</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item py-2 fw-medium text-warning" style="color: #d97706 !important;" href="#" onclick="openReasonModal(${item.id}, 'cancelled'); return false;"><i class="bi bi-x-circle me-2"></i>Cancel</a></li>
            `;
        } else if (item.status === 'approved') {
            actions += `
                    <li><a class="dropdown-item py-2 fw-medium text-primary" href="#" onclick="updateStatus(${item.id}, 'completed'); return false;"><i class="bi bi-check-all me-2"></i>Complete</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item py-2 fw-medium text-warning" style="color: #d97706 !important;" href="#" onclick="openReasonModal(${item.id}, 'cancelled'); return false;"><i class="bi bi-x-circle me-2"></i>Cancel</a></li>
            `;
        } else {
            actions += `
                    <li><a class="dropdown-item py-2 fw-medium text-secondary" href="#"><i class="bi bi-eye me-2"></i>View</a></li>
            `;
        }

        actions += `
                </ul>
            </div>
        `;
        
        let facilityText = item.facility_id ? ` (Fac: ${item.facility_id})` : '';
        
        tbody.innerHTML += `
            <tr>
                <td class="fw-bold">${ksEscape(item.booking_reference || `#${item.id}`)}</td>
                <td><i class="bi bi-geo-alt me-1 text-muted"></i>Venue ${item.venue_id}${facilityText}</td>
                <td>${ksEscape(item.booking_date)}</td>
                <td>${ksEscape(item.start_time)} - ${ksEscape(item.end_time)}</td>
                <td>${ksEscape(item.purpose)}</td>
                <td><span class="ks-badge ${badgeClass}">${ksEscape(item.status)}</span></td>
                <td class="text-end">${actions}</td>
            </tr>
        `;
    });
}

function openCreateModal() {
    document.getElementById('bookingForm').reset();
    document.getElementById('bookingErrorMsg').classList.add('d-none');
    const modal = new bootstrap.Modal(document.getElementById('createModal'));
    modal.show();
}

async function saveBooking() {
    const form = document.getElementById('bookingForm');
    if (!form.reportValidity()) return;
    
    document.getElementById('bookingErrorMsg').classList.add('d-none');
    
    const payload = {
        venue_id: document.getElementById('bVenueId').value,
        booking_date: document.getElementById('bDate').value,
        start_time: document.getElementById('bStartTime').value,
        end_time: document.getElementById('bEndTime').value,
        purpose: document.getElementById('bPurpose').value,
        booking_type: document.getElementById('bType').value,
        notes: document.getElementById('bNotes').value
    };
    
    ['FacilityId', 'TeamId', 'EventId', 'TournamentId'].forEach(field => {
        let val = document.getElementById('b' + field).value;
        if (val) payload[field.replace(/([A-Z])/g, "_$1").toLowerCase().substring(1)] = val;
    });

    try {
        const res = await fetch('/api/v1/bookings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const json = await res.json();
        
        if (res.status === 409) {
            document.getElementById('bookingErrorText').innerText = json.message || 'Time slot conflict detected.';
            document.getElementById('bookingErrorMsg').classList.remove('d-none');
        } else if (json.success) {
            ksToast('Booking created successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createModal')).hide();
            loadBookings();
        } else {
            document.getElementById('bookingErrorText').innerText = json.message || 'Error creating booking';
            document.getElementById('bookingErrorMsg').classList.remove('d-none');
        }
    } catch (e) {
        document.getElementById('bookingErrorText').innerText = 'Network error while creating booking';
        document.getElementById('bookingErrorMsg').classList.remove('d-none');
    }
}

async function updateStatus(id, newStatus) {
    if(!confirm(`Are you sure you want to mark this booking as ${newStatus}?`)) return;
    
    try {
        const res = await fetch(`/api/v1/bookings/${id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: newStatus })
        });
        const json = await res.json();
        if (json.success) {
            ksToast(`Booking ${newStatus}`, 'success');
            loadBookings();
        } else {
            ksToast(json.message || `Error updating booking`, 'error');
        }
    } catch (e) {
        ksToast('Network error', 'error');
    }
}

function openReasonModal(id, actionType) {
    document.getElementById('actionReasonForm').reset();
    document.getElementById('actionBookingId').value = id;
    document.getElementById('actionType').value = actionType;
    document.getElementById('actionReasonTitle').innerText = actionType === 'cancelled' ? 'Cancel Booking' : 'Reject Booking';
    document.getElementById('actionReasonBtn').className = actionType === 'cancelled' ? 'btn btn-warning text-dark' : 'btn btn-danger';
    
    const modal = new bootstrap.Modal(document.getElementById('actionReasonModal'));
    modal.show();
}

async function submitActionReason() {
    const form = document.getElementById('actionReasonForm');
    if (!form.reportValidity()) return;
    
    const id = document.getElementById('actionBookingId').value;
    const actionType = document.getElementById('actionType').value;
    const reason = document.getElementById('actionReasonInput').value;
    
    try {
        let url = `/api/v1/bookings/${id}`;
        let method = 'PATCH';
        let body = { status: actionType, reason: reason };
        
        if (actionType === 'cancelled') {
            url = `/api/v1/bookings/${id}/cancel`;
            method = 'POST';
            body = { cancellation_reason: reason };
        }
        
        const res = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        
        const json = await res.json();
        if (json.success) {
            ksToast(`Booking ${actionType}`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('actionReasonModal')).hide();
            loadBookings();
        } else {
            ksToast(json.message || `Error processing request`, 'error');
        }
    } catch (e) {
        ksToast('Network error', 'error');
    }
}

    const eventInput = document.getElementById('createEventId');
    const tournamentInput = document.getElementById('createTournamentId');
    const venueInput = document.getElementById('createVenue');
    
    async function filterVenuesByContext() {
        let sportId = null;
        
        // This is a mockup of checking the context for a sport.
        // We'd actually fetch the tournament/event to get the sport_id.
        // For now, if a user picks an event/tournament, we pass it as context.
        
        const eventId = eventInput ? eventInput.value : null;
        const tournamentId = tournamentInput ? tournamentInput.value : null;
        
        let url = '/api/v1/venues';
        const params = [];
        if (eventId) params.push('context_type=event&context_id=' + eventId);
        else if (tournamentId) params.push('context_type=tournament&context_id=' + tournamentId);
        
        if (params.length > 0) {
            url += '?' + params.join('&');
            
            try {
                const res = await fetch(url);
                const json = await res.json();
                if(json.success) {
                    const venues = json.data.data || json.data;
                    venueInput.innerHTML = '<option value="">Select Venue...</option>' + venues.map(v => `<option value="${v.id}">${v.name}</option>`).join('');
                }
            } catch(e) {
                console.error('Error fetching filtered venues');
            }
        }
    }
    
    if (eventInput) eventInput.addEventListener('change', filterVenuesByContext);
    if (tournamentInput) tournamentInput.addEventListener('change', filterVenuesByContext);

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
