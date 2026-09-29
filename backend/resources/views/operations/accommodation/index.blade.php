<?php
$activePage = 'accommodation';
$title = 'Accommodation Management — KhelSutra';
ob_start();
?>
<div class="ks-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="ks-page-title mb-1">Accommodation Management</h2>
            <p class="ks-page-subtitle mb-0">Manage lodging facilities and room allocations for athletes and staff</p>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-blue"><i class="bi bi-building-fill"></i></div>
                <div>
                    <div class="ks-kpi-label">TOTAL PROPERTIES</div>
                    <div class="ks-kpi-value" id="kpi-total">—</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-green"><i class="bi bi-door-open-fill"></i></div>
                <div>
                    <div class="ks-kpi-label">TOTAL ROOMS</div>
                    <div class="ks-kpi-value" id="kpi-rooms">—</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-amber"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="ks-kpi-label">ACTIVE PROPERTIES</div>
                    <div class="ks-kpi-value" id="kpi-active">—</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="ks-filter-bar mb-3">
    <div class="ks-filter-grid">
        <div><input type="text" class="ks-form-control" id="filterSearch" placeholder="Search accommodation..." oninput="loadData()"></div>
        <div><select class="ks-form-select" id="filterStatus" onchange="loadData()">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select></div>
        <div><button class="ks-btn ks-btn-secondary w-100" onclick="clearFilters()"><i class="bi bi-x-circle"></i> Clear</button></div>
        <div class="ms-auto">
            <button class="ks-btn ks-btn-primary" onclick="openCreateModal()"><i class="bi bi-plus-lg"></i> Add Accommodation</button>
        </div>
    </div>
</div>

<div class="row g-3" id="accommodationGrid">
    <!-- Grid Cards Render Here -->
</div>

<!-- Empty State Template -->
<div id="emptyState" style="display: none;" class="ks-content-card">
    <div class="ks-empty-state" style="padding: 40px; text-align: center;">
        <i class="bi bi-buildings text-muted" style="font-size: 48px;"></i>
        <h4 class="mt-3">No accommodations found</h4>
        <p class="text-muted">Adjust your filters or add a new accommodation.</p>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="accommodationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: var(--ks-radius-modal); border: 1px solid var(--ks-border); box-shadow: var(--ks-shadow-modal);">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Add Accommodation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pb-0">
                <ul class="nav nav-pills mb-3" id="accommodationTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="property-tab" data-bs-toggle="pill" data-bs-target="#property" type="button" role="tab">Property</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="contact-tab" data-bs-toggle="pill" data-bs-target="#contact" type="button" role="tab">Contact</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="location-tab" data-bs-toggle="pill" data-bs-target="#location" type="button" role="tab">Location</button>
                    </li>
                </ul>
                <form id="accommodationForm" class="needs-validation" novalidate>
                    <input type="hidden" id="accommodationId">
                    <div class="tab-content" id="accommodationTabsContent">
                        <!-- Property Tab -->
                        <div class="tab-pane fade show active" id="property" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name *</label>
                                    <input type="text" class="ks-form-control" id="accName" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Type *</label>
                                    <select class="ks-form-select" id="accType" required>
                                        <option value="">Select Type</option>
                                        <option value="hotel">Hotel</option>
                                        <option value="hostel">Hostel</option>
                                        <option value="apartment">Apartment</option>
                                        <option value="guesthouse">Guesthouse</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Total Rooms</label>
                                    <input type="number" class="ks-form-control" id="accTotalRooms" min="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select class="ks-form-select" id="accStatus">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="ks-form-control" id="accNotes" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        <!-- Contact Tab -->
                        <div class="tab-pane fade" id="contact" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" class="ks-form-control" id="accContactPerson">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="ks-form-control" id="accPhone">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="ks-form-control" id="accEmail">
                                </div>
                            </div>
                        </div>
                        <!-- Location Tab -->
                        <div class="tab-pane fade" id="location" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Address Line 1</label>
                                    <input type="text" class="ks-form-control" id="accAddr1">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address Line 2</label>
                                    <input type="text" class="ks-form-control" id="accAddr2">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">City</label>
                                    <input type="text" class="ks-form-control" id="accCity">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">State</label>
                                    <input type="text" class="ks-form-control" id="accState">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Postal Code</label>
                                    <input type="text" class="ks-form-control" id="accPostalCode">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Country</label>
                                    <input type="text" class="ks-form-control" id="accCountry" value="India">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn ks-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn ks-btn-primary" onclick="saveAccommodation()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Rooms Drawer Overlay -->
<div id="roomsDrawer-overlay" onclick="ksDrawerClose('roomsDrawer')"
     style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1040;opacity:0;visibility:hidden;transition:opacity .3s;"></div>
<!-- Rooms Drawer -->
<div id="roomsDrawer"
     style="position:fixed;top:0;right:0;width:520px;height:100vh;background:#fff;z-index:1050;
            transform:translateX(100%);visibility:hidden;transition:transform .3s ease;
            overflow-y:auto;box-shadow:-4px 0 24px rgba(0,0,0,.12);">
    <div style="padding:24px 24px 0;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--ks-border);padding-bottom:16px;margin-bottom:20px;">
        <div>
            <h5 style="font-weight:700;color:var(--ks-navy);margin:0;" id="drawerTitle">Rooms</h5>
            <small class="text-muted" id="drawerSubtitle"></small>
        </div>
        <button class="btn-close" onclick="ksDrawerClose('roomsDrawer')"></button>
    </div>
    
    <div style="padding:0 24px 24px;" id="drawerContent">
        <div class="mb-4 p-3 bg-light rounded border">
            <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle text-primary me-2"></i>Add New Room</h6>
            <form id="addRoomForm">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small">Room Number *</label>
                        <input type="text" class="form-control form-control-sm" id="newRoomNumber" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Capacity</label>
                        <input type="number" class="form-control form-control-sm" id="newRoomCapacity" min="1" value="2">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Status</label>
                        <select class="form-select form-select-sm" id="newRoomStatus">
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary btn-sm w-100" onclick="addRoom()">Add</button>
                    </div>
                </div>
            </form>
        </div>

        <div id="roomsListContainer">
            <!-- Rooms List -->
        </div>
    </div>
</div>

<script>
let accommodationsData = [];
let currentAccommodationId = null;

document.addEventListener('DOMContentLoaded', () => {
    loadData();
});

async function loadData() {
    try {
        const res = await fetch('/api/v1/accommodations');
        const json = await res.json();
        
        if (json.success) {
            accommodationsData = json.data.data || json.data || [];
            applyFiltersAndRender();
        } else {
            ksToast('Failed to load accommodations', 'error');
        }
    } catch (e) {
        ksToast('Error loading accommodations', 'error');
    }
}

function clearFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterStatus').value = '';
    applyFiltersAndRender();
}

function applyFiltersAndRender() {
    const search = document.getElementById('filterSearch').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    
    let filtered = accommodationsData.filter(a => {
        let matchName = (a.name || '').toLowerCase().includes(search);
        let matchStatus = !status || a.status === status;
        return matchName && matchStatus;
    });

    renderGrid(filtered);
    updateKPIs(accommodationsData);
}

function updateKPIs(data) {
    document.getElementById('kpi-total').innerText = data.length;
    let totalRooms = data.reduce((sum, a) => sum + (parseInt(a.total_rooms) || 0), 0);
    document.getElementById('kpi-rooms').innerText = totalRooms;
    let active = data.filter(a => a.status === 'active').length;
    document.getElementById('kpi-active').innerText = active;
}

function renderGrid(data) {
    const grid = document.getElementById('accommodationGrid');
    const emptyState = document.getElementById('emptyState');
    
    grid.innerHTML = '';
    
    if (data.length === 0) {
        grid.style.display = 'none';
        emptyState.style.display = 'block';
        return;
    }
    
    grid.style.display = 'flex';
    emptyState.style.display = 'none';
    
    data.forEach(item => {
        const badgeClass = item.status === 'active' ? 'ks-badge-confirmed' : 'ks-badge-cancelled';
        const typeStr = item.accommodation_type ? item.accommodation_type.charAt(0).toUpperCase() + item.accommodation_type.slice(1) : 'N/A';
        const locStr = [item.city, item.state].filter(Boolean).join(', ') || 'Location not specified';
        
        let card = document.createElement('div');
        card.className = 'col-xl-4 col-md-6';
        card.innerHTML = `
            <div class="ks-content-card h-100 d-flex flex-column" style="padding: 20px;">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="d-flex align-items-center">
                        <div class="ks-icon-box ks-icon-blue me-3" style="width: 48px; height: 48px; min-width: 48px;">
                            <i class="bi bi-building-fill fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">${ksEscape(item.name || 'Unnamed')}</h5>
                            <span class="ks-badge ks-badge-scheduled">${ksEscape(typeStr)}</span>
                        </div>
                    </div>
                    <span class="ks-badge ${badgeClass}">${ksEscape(item.status || 'active')}</span>
                </div>
                
                <div class="mb-3 text-muted small">
                    <div class="mb-1"><i class="bi bi-geo-alt me-2"></i>${ksEscape(locStr)}</div>
                    <div class="mb-1"><i class="bi bi-person me-2"></i>${ksEscape(item.contact_person || 'No contact')}</div>
                    <div><i class="bi bi-telephone me-2"></i>${ksEscape(item.phone || 'N/A')}</div>
                </div>
                
                <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                    <div class="fw-bold text-dark">
                        <i class="bi bi-door-open me-1 text-success"></i> ${item.total_rooms || 0} Rooms
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" onclick="openRoomsDrawer(${item.id}, '${ksEscape(item.name)}')">
                            <i class="bi bi-door-open"></i> Rooms
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick='openEditModal(${JSON.stringify(item).replace(/'/g, "&#39;")})'>
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteAccommodation(${item.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });
}

function openCreateModal() {
    document.getElementById('accommodationForm').reset();
    document.getElementById('accommodationId').value = '';
    document.getElementById('modalTitle').innerText = 'Add Accommodation';
    
    // Reset tabs
    document.getElementById('property-tab').click();
    
    const modal = new bootstrap.Modal(document.getElementById('accommodationModal'));
    modal.show();
}

function openEditModal(item) {
    document.getElementById('accommodationForm').reset();
    document.getElementById('accommodationId').value = item.id;
    document.getElementById('modalTitle').innerText = 'Edit Accommodation';
    
    document.getElementById('accName').value = item.name || '';
    document.getElementById('accType').value = item.accommodation_type || '';
    document.getElementById('accTotalRooms').value = item.total_rooms || '';
    document.getElementById('accStatus').value = item.status || 'active';
    document.getElementById('accNotes').value = item.notes || '';
    
    document.getElementById('accContactPerson').value = item.contact_person || '';
    document.getElementById('accPhone').value = item.phone || '';
    document.getElementById('accEmail').value = item.email || '';
    
    document.getElementById('accAddr1').value = item.address_line1 || '';
    document.getElementById('accAddr2').value = item.address_line2 || '';
    document.getElementById('accCity').value = item.city || '';
    document.getElementById('accState').value = item.state || '';
    document.getElementById('accPostalCode').value = item.postal_code || '';
    document.getElementById('accCountry').value = item.country || 'India';
    
    document.getElementById('property-tab').click();
    
    const modal = new bootstrap.Modal(document.getElementById('accommodationModal'));
    modal.show();
}

async function saveAccommodation() {
    const form = document.getElementById('accommodationForm');
    if (!form.reportValidity()) return;
    
    const id = document.getElementById('accommodationId').value;
    const payload = {
        name: document.getElementById('accName').value,
        accommodation_type: document.getElementById('accType').value,
        total_rooms: document.getElementById('accTotalRooms').value,
        status: document.getElementById('accStatus').value,
        notes: document.getElementById('accNotes').value,
        contact_person: document.getElementById('accContactPerson').value,
        phone: document.getElementById('accPhone').value,
        email: document.getElementById('accEmail').value,
        address_line1: document.getElementById('accAddr1').value,
        address_line2: document.getElementById('accAddr2').value,
        city: document.getElementById('accCity').value,
        state: document.getElementById('accState').value,
        postal_code: document.getElementById('accPostalCode').value,
        country: document.getElementById('accCountry').value || 'India'
    };
    
    try {
        const url = id ? `/api/v1/accommodations/${id}` : '/api/v1/accommodations';
        const method = id ? 'PUT' : 'POST'; // Assuming PUT for update
        
        const res = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const json = await res.json();
        if (json.success) {
            ksToast(`Accommodation ${id ? 'updated' : 'added'} successfully`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('accommodationModal')).hide();
            loadData();
        } else {
            ksToast(json.message || 'Error saving accommodation', 'error');
        }
    } catch (e) {
        ksToast('Error saving accommodation', 'error');
    }
}

async function deleteAccommodation(id) {
    if (!confirm('Are you sure you want to delete this accommodation?')) return;
    
    try {
        const res = await fetch(`/api/v1/accommodations/${id}`, {
            method: 'DELETE'
        });
        const json = await res.json();
        if (json.success) {
            ksToast('Accommodation deleted', 'success');
            loadData();
        } else {
            ksToast(json.message || 'Error deleting accommodation', 'error');
        }
    } catch (e) {
        ksToast('Error deleting accommodation', 'error');
    }
}

// Rooms Drawer logic
async function openRoomsDrawer(accId, accName) {
    currentAccommodationId = accId;
    document.getElementById('drawerTitle').innerText = accName;
    document.getElementById('drawerSubtitle').innerText = 'Room Allocations';
    document.getElementById('addRoomForm').reset();
    
    ksDrawerOpen('roomsDrawer');
    await loadRooms(accId);
}

async function loadRooms(accId) {
    const container = document.getElementById('roomsListContainer');
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    try {
        const res = await fetch(`/api/v1/accommodations/${accId}/rooms`);
        
        if (res.status === 404) {
            container.innerHTML = `<div class="alert alert-info border-0"><i class="bi bi-info-circle me-2"></i>No rooms data available</div>`;
            return;
        }
        
        const json = await res.json();
        let rooms = json.data?.data || json.data || [];
        
        if (rooms.length === 0) {
            container.innerHTML = `<div class="alert alert-info border-0"><i class="bi bi-info-circle me-2"></i>No rooms added yet.</div>`;
            return;
        }
        
        let html = '<ul class="list-group list-group-flush border rounded">';
        rooms.forEach(room => {
            let badgeClass = 'ks-badge-pending';
            if (room.status === 'available') badgeClass = 'ks-badge-confirmed';
            else if (room.status === 'occupied') badgeClass = 'ks-badge-scheduled';
            else if (room.status === 'inactive') badgeClass = 'ks-badge-cancelled';
            else if (room.status === 'maintenance') badgeClass = 'ks-badge-rejected';
            
            html += `
                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                    <div>
                        <div class="fw-bold"><i class="bi bi-door-open me-2 text-muted"></i>Room ${ksEscape(room.room_number)}</div>
                        <div class="small text-muted mt-1">Capacity: ${room.capacity || 'N/A'}</div>
                    </div>
                    <span class="ks-badge ${badgeClass}">${ksEscape(room.status || 'available')}</span>
                </li>
            `;
        });
        html += '</ul>';
        container.innerHTML = html;
        
    } catch (e) {
        container.innerHTML = `<div class="alert alert-danger border-0">Error loading rooms</div>`;
    }
}

async function addRoom() {
    if (!currentAccommodationId) return;
    
    const form = document.getElementById('addRoomForm');
    if (!form.reportValidity()) return;
    
    const payload = {
        room_number: document.getElementById('newRoomNumber').value,
        capacity: document.getElementById('newRoomCapacity').value,
        status: document.getElementById('newRoomStatus').value
    };
    
    try {
        const res = await fetch(`/api/v1/accommodations/${currentAccommodationId}/rooms`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const json = await res.json();
        if (json.success) {
            ksToast('Room added successfully', 'success');
            form.reset();
            loadRooms(currentAccommodationId);
        } else {
            ksToast(json.message || 'Error adding room', 'error');
        }
    } catch (e) {
        ksToast('Error adding room', 'error');
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
