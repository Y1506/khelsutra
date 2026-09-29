<?php
$pageTitle = 'Facilities & Maintenance — KhelSutra';
$activePage = 'maintenance';
ob_start();
?>

<div class="ks-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="ks-page-title mb-1">Facilities & Maintenance</h2>
            <p class="ks-page-subtitle">Track venue maintenance issues and schedule housekeeping tasks</p>
        </div>
    </div>
</div>

<ul class="nav nav-tabs ks-nav-tabs mb-4" id="facilityTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance-pane" type="button" role="tab" aria-controls="maintenance-pane" aria-selected="true">
            <i class="bi bi-tools me-2"></i> Maintenance Tickets
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="housekeeping-tab" data-bs-toggle="tab" data-bs-target="#housekeeping-pane" type="button" role="tab" aria-controls="housekeeping-pane" aria-selected="false">
            <i class="bi bi-brush me-2"></i> Housekeeping Tasks
        </button>
    </li>
</ul>

<div class="tab-content" id="facilityTabsContent">
    <div class="tab-pane fade show active" id="maintenance-pane" role="tabpanel" aria-labelledby="maintenance-tab" tabindex="0">
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-blue"><i class="bi bi-tools"></i></div>
                <div><div class="ks-kpi-label">OPEN TICKETS</div><div class="ks-kpi-value" id="kpi-open">—</div></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-red"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div><div class="ks-kpi-label">CRITICAL</div><div class="ks-kpi-value" id="kpi-critical">—</div></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-amber"><i class="bi bi-arrow-clockwise"></i></div>
                <div><div class="ks-kpi-label">IN PROGRESS</div><div class="ks-kpi-value" id="kpi-progress">—</div></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                <div><div class="ks-kpi-label">COMPLETED</div><div class="ks-kpi-value" id="kpi-completed">—</div></div>
            </div>
        </div>
    </div>
</div>


<div class="ks-filter-bar mb-3">
    <div class="ks-filter-grid">
        <input type="text" class="ks-form-control" id="filterSearch" placeholder="Search..." oninput="loadMaintenance()">
        <select class="ks-form-select" id="filterPriority" onchange="loadMaintenance()">
            <option value="">All Priorities</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
        </select>
        <select class="ks-form-select" id="filterStatus" onchange="loadMaintenance()">
            <option value="">All Statuses</option>
            <option value="reported">Reported</option>
            <option value="assigned">Assigned</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <button class="ks-btn ks-btn-secondary" onclick="clearFilters()"><i class="bi bi-x-circle"></i> Clear</button>
    </div>
</div>

<div class="ks-content-card">
    <div class="ks-card-header">
        <div class="ks-header-left">
            <i class="bi bi-tools" style="color:var(--ks-primary);font-size:18px;"></i>
            <h3 class="ks-header-title">Maintenance Tickets</h3>
            <span class="ks-badge ks-badge-scheduled ms-2" id="total-count">0</span>
        </div>
        <button class="ks-btn ks-btn-primary" data-bs-toggle="modal" data-bs-target="#createTicketModal">
            <i class="bi bi-plus-lg"></i> Create Ticket
        </button>
    </div>
    <div class="ks-table-responsive">
        <table class="ks-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Issue Title</th>
                    <th>Priority</th>
                    <th>Scheduled Date</th>
                    <th>Est. Cost</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
            </tbody>
        </table>
    </div>
</div>


    </div>

    <div class="tab-pane fade" id="housekeeping-pane" role="tabpanel" aria-labelledby="housekeeping-tab" tabindex="0">
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-4">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-amber"><i class="bi bi-hourglass-split"></i></div>
                <div><div class="ks-kpi-label">PENDING TASKS</div><div class="ks-kpi-value" id="kpi-pending">—</div></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-blue"><i class="bi bi-arrow-clockwise"></i></div>
                <div><div class="ks-kpi-label">IN PROGRESS</div><div class="ks-kpi-value" id="kpi-progress">—</div></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                <div><div class="ks-kpi-label">COMPLETED TODAY</div><div class="ks-kpi-value" id="kpi-completed">—</div></div>
            </div>
        </div>
    </div>
</div>


<div class="ks-filter-bar mb-3">
    <div class="ks-filter-grid">
        <div class="btn-group" role="group">
            <input type="radio" class="btn-check" name="dateFilter" id="dateToday" autocomplete="off" value="today" onchange="loadHousekeeping()">
            <label class="btn btn-outline-primary" for="dateToday">Today</label>
            
            <input type="radio" class="btn-check" name="dateFilter" id="dateUpcoming" autocomplete="off" value="upcoming" onchange="loadHousekeeping()">
            <label class="btn btn-outline-primary" for="dateUpcoming">Upcoming</label>
            
            <input type="radio" class="btn-check" name="dateFilter" id="dateAll" autocomplete="off" value="all" checked onchange="loadHousekeeping()">
            <label class="btn btn-outline-primary" for="dateAll">All</label>
        </div>

        <input type="text" class="ks-form-control" id="filterSearchHK" placeholder="Search..." oninput="loadHousekeeping()">
        <select class="ks-form-select" id="filterPriorityHK" onchange="loadHousekeeping()">
            <option value="">All Priorities</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
        </select>
        <select class="ks-form-select" id="filterStatusHK" onchange="loadHousekeeping()">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="assigned">Assigned</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <button class="ks-btn ks-btn-secondary" onclick="clearFiltersHK()"><i class="bi bi-x-circle"></i> Clear</button>
    </div>
</div>

<div class="ks-content-card">
    <div class="ks-card-header">
        <div class="ks-header-left">
            <i class="bi bi-stars" style="color:var(--ks-primary);font-size:18px;"></i>
            <h3 class="ks-header-title">Housekeeping Tasks</h3>
            <span class="ks-badge ks-badge-scheduled ms-2" id="total-count">0</span>
        </div>
        <button class="ks-btn ks-btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
            <i class="bi bi-plus-lg"></i> Create Task
        </button>
    </div>
    <div class="ks-table-responsive">
        <table class="ks-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Task Type</th>
                    <th>Scheduled Date</th>
                    <th>Priority</th>
                    <th>Assigned Employee</th>
                    <th>Status</th>
                    <th>Quick Action</th>
                </tr>
            </thead>
            <tbody id="tableBodyHK">
            </tbody>
        </table>
    </div>
</div>


    </div>
</div>

<!-- Complete Modal --><div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--ks-radius-modal); border: 1px solid var(--ks-border); box-shadow: var(--ks-shadow-modal);">
            <div class="modal-header">
                <h5 class="modal-title">Complete Ticket: <span id="completeTicketRef"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="completeForm" onsubmit="event.preventDefault(); submitCompleteTicket();">
                    <input type="hidden" id="completeTicketId">
                    <div class="mb-3">
                        <label class="form-label">Actual Cost <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="ks-form-control" id="completeCost" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Completed Date</label>
                        <input type="date" class="ks-form-control" id="completeDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="ks-form-control" id="completeNotes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="ks-btn ks-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="completeForm" class="ks-btn ks-btn-primary">Mark Completed</button>
            </div>
        </div>
    </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createTicketModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: var(--ks-radius-modal); border: 1px solid var(--ks-border); box-shadow: var(--ks-shadow-modal);">
            <div class="modal-header">
                <h5 class="modal-title">Create Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createForm" onsubmit="event.preventDefault(); submitCreateTicket();">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Issue Title <span class="text-danger">*</span></label>
                            <input type="text" class="ks-form-control" id="createTitle" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select class="ks-form-select" id="createPriority" required>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Venue ID <span class="text-danger">*</span></label>
                            <select class="ks-form-control" id="createVenue" required>
<option value="">Select Venue...</option>
</select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Facility ID</label>
                            <select class="ks-form-control" id="createFacility">
<option value="">Select Facility...</option>
</select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assigned Employee ID</label>
                            <select class="ks-form-control" id="createEmployee">
<option value="">Select Employee...</option>
</select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assigned Vendor ID</label>
                            <select class="ks-form-control" id="createVendor">
<option value="">Select Vendor...</option>
</select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Scheduled Date <span class="text-danger">*</span></label>
                            <input type="date" class="ks-form-control" id="createDate" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estimated Cost</label>
                            <input type="number" step="0.01" class="ks-form-control" id="createEstCost">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="ks-form-control" id="createDesc" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="ks-form-control" id="createNotes" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="ks-btn ks-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="createForm" class="ks-btn ks-btn-primary">Create Ticket</button>
            </div>
        </div>
    </div>
</div>
<!-- Create Task Modal --><div class="modal fade" id="createTaskModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: var(--ks-radius-modal); border: 1px solid var(--ks-border); box-shadow: var(--ks-shadow-modal);">
            <div class="modal-header">
                <h5 class="modal-title">Create Housekeeping Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createFormHK" onsubmit="event.preventDefault(); submitCreateTask();">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Task Type <span class="text-danger">*</span></label>
                            <input type="text" class="ks-form-control" id="createTypeHK" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select class="ks-form-select" id="createPriorityHK" required>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Venue ID <span class="text-danger">*</span></label>
                            <select class="ks-form-control" id="createVenueHK" required>
<option value="">Select Venue...</option>
</select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Facility ID</label>
                            <select class="ks-form-control" id="createFacilityHK">
<option value="">Select Facility...</option>
</select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assigned Employee ID</label>
                            <select class="ks-form-control" id="createEmployeeHK">
<option value="">Select Employee...</option>
</select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Scheduled Date <span class="text-danger">*</span></label>
                            <input type="date" class="ks-form-control" id="createDateHK" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="ks-form-control" id="createStartHK">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time</label>
                            <input type="time" class="ks-form-control" id="createEndHK">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="ks-form-control" id="createDescHK" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="ks-form-control" id="createRemarksHK" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="ks-btn ks-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="createFormHK" class="ks-btn ks-btn-primary">Create Task</button>
            </div>
        </div>
    </div>
</div>

<script>

let currentCompleteModal = null;
let currentCreateModal = null;

document.addEventListener('DOMContentLoaded', () => {
    currentCompleteModal = new bootstrap.Modal(document.getElementById('completeModal'));
    currentCreateModal = new bootstrap.Modal(document.getElementById('createTicketModal'));
    loadMaintenance();
});

function clearFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterPriority').value = '';
    document.getElementById('filterStatus').value = '';
    loadMaintenance();
}

async function loadMaintenance() {
    const search = document.getElementById('filterSearch').value;
    const priority = document.getElementById('filterPriority').value;
    const status = document.getElementById('filterStatus').value;
    
    const params = new URLSearchParams();
    if(search) params.append('search', search);
    if(priority) params.append('priority', priority);
    if(status) params.append('status', status);

    try {
        const res = await fetch(`/api/v1/maintenance?${params.toString()}`);
        const json = await res.json();
        if(json.success) {
            renderTable(json.data.data);
            updateKPIs(json.data.data);
        } else {
            ksToast('Failed to load tickets', 'error');
        }
    } catch(e) {
        ksToast('Error loading tickets', 'error');
    }
}

function updateKPIs(data) {
    let open = 0, critical = 0, inProgress = 0, completed = 0;
    data.forEach(t => {
        if(t.status !== 'completed' && t.status !== 'cancelled') open++;
        if(t.priority === 'critical') critical++;
        if(t.status === 'in_progress') inProgress++;
        if(t.status === 'completed') completed++;
    });
    document.getElementById('kpi-open').textContent = open;
    document.getElementById('kpi-critical').textContent = critical;
    document.getElementById('kpi-progress').textContent = inProgress;
    document.getElementById('kpi-completed').textContent = completed;
    document.getElementById('total-count').textContent = data.length;
}

function getPriorityBadge(priority) {
    switch(priority) {
        case 'critical': return '<span class="ks-badge ks-badge-rejected">Critical</span>';
        case 'high': return '<span class="ks-badge ks-badge-pending" style="background:#FEF3C7;color:#7C2D12">High</span>';
        case 'medium': return '<span class="ks-badge ks-badge-pending">Medium</span>';
        case 'low': return '<span class="ks-badge ks-badge-scheduled">Low</span>';
        default: return `<span class="ks-badge ks-badge-scheduled">${ksEscape(priority)}</span>`;
    }
}

function getStatusBadge(status) {
    switch(status) {
        case 'reported': return '<span class="ks-badge ks-badge-scheduled">Reported</span>';
        case 'assigned': return '<span class="ks-badge ks-badge-pending">Assigned</span>';
        case 'in_progress': return '<span class="ks-badge ks-badge-pending">In Progress</span>';
        case 'completed': return '<span class="ks-badge ks-badge-confirmed">Completed</span>';
        case 'cancelled': return '<span class="ks-badge ks-badge-cancelled">Cancelled</span>';
        default: return `<span class="ks-badge ks-badge-scheduled">${ksEscape(status)}</span>`;
    }
}

function getPriorityColor(priority) {
    switch(priority) {
        case 'critical': return 'red';
        case 'high': return 'orange';
        case 'medium': return '#ffc107'; // amber
        case 'low': return 'lightgray';
        default: return 'transparent';
    }
}

function getActions(ticket) {
    const s = ticket.status;
    let buttons = '';
    if (s === 'reported') {
        buttons = `<button class="ks-btn ks-btn-sm ks-btn-secondary" onclick="updateStatus(${ticket.id}, 'assigned')"><i class="bi bi-person-check"></i> Assign</button>`;
    } else if (s === 'assigned') {
        buttons = `<button class="ks-btn ks-btn-sm ks-btn-secondary" onclick="updateStatus(${ticket.id}, 'in_progress')"><i class="bi bi-play"></i> Start</button>`;
    } else if (s === 'in_progress') {
        buttons = `
            <button class="ks-btn ks-btn-sm ks-btn-primary" onclick="openCompleteModal(${ticket.id}, '${ksEscape(ticket.maintenance_reference)}')"><i class="bi bi-check-lg"></i> Complete</button>
            <button class="ks-btn ks-btn-sm ks-btn-secondary" onclick="updateStatus(${ticket.id}, 'cancelled')"><i class="bi bi-x-circle"></i> Cancel</button>
        `;
    } else if (s === 'completed' || s === 'cancelled') {
        buttons = `<span class="text-muted"><i class="bi bi-lock"></i> Read Only</span>`;
    }
    return buttons;
}

function renderTable(data) {
    const tbody = document.getElementById('tableBody');
    if(data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7"><div class="ks-empty-state">No tickets found.</div></td></tr>`;
        return;
    }

    tbody.innerHTML = data.map(t => {
        const borderStyle = `border-left: 4px solid ${getPriorityColor(t.priority)};`;
        return `
            <tr>
                <td style="${borderStyle}"><strong>${ksEscape(t.maintenance_reference)}</strong></td>
                <td>
                    <div class="fw-bold">${ksEscape(t.issue_title)}</div>
                    <div class="small text-muted">Venue: ${ksEscape(t.venue_id || '-')}</div>
                </td>
                <td>${getPriorityBadge(t.priority)}</td>
                <td>${ksEscape(t.scheduled_date || '-')}</td>
                <td>${t.estimated_cost ? '$' + t.estimated_cost : '-'}</td>
                <td>${getStatusBadge(t.status)}</td>
                <td>
                    <div class="d-flex gap-2">
                        ${getActions(t)}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

async function updateStatus(id, newStatus) {
    try {
        const res = await fetch(`/api/v1/maintenance/${id}`, {
            method: 'PATCH',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify({ status: newStatus })
        });
        const json = await res.json();
        if(json.success) {
            ksToast('Status updated', 'success');
            loadMaintenance();
        } else {
            ksToast('Update failed', 'error');
        }
    } catch(e) {
        ksToast('Error updating status', 'error');
    }
}

function openCompleteModal(id, ref) {
    document.getElementById('completeTicketId').value = id;
    document.getElementById('completeTicketRef').textContent = ref;
    document.getElementById('completeCost').value = '';
    document.getElementById('completeDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('completeNotes').value = '';
    currentCompleteModal.show();
}

async function submitCompleteTicket() {
    const form = document.getElementById('completeForm');
    if(!form.reportValidity()) return;

    const id = document.getElementById('completeTicketId').value;
    const cost = document.getElementById('completeCost').value;
    const date = document.getElementById('completeDate').value;
    const notes = document.getElementById('completeNotes').value;

    try {
        const res = await fetch(`/api/v1/maintenance/${id}`, {
            method: 'PATCH',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify({ 
                status: 'completed',
                actual_cost: cost,
                completed_date: date,
                notes: notes
            })
        });
        const json = await res.json();
        if(json.success) {
            ksToast('Ticket completed', 'success');
            currentCompleteModal.hide();
            loadMaintenance();
        } else {
            ksToast('Update failed', 'error');
        }
    } catch(e) {
        ksToast('Error completing ticket', 'error');
    }
}

async function submitCreateTicket() {
    const form = document.getElementById('createForm');
    if(!form.reportValidity()) return;

    const payload = {
        issue_title: document.getElementById('createTitle').value,
        priority: document.getElementById('createPriority').value,
        venue_id: document.getElementById('createVenue').value,
        facility_id: document.getElementById('createFacility').value || null,
        assigned_employee_id: document.getElementById('createEmployee').value || null,
        assigned_vendor_id: document.getElementById('createVendor').value || null,
        scheduled_date: document.getElementById('createDate').value,
        estimated_cost: document.getElementById('createEstCost').value || null,
        issue_description: document.getElementById('createDesc').value,
        notes: document.getElementById('createNotes').value
    };

    try {
        const res = await fetch('/api/v1/maintenance', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if(json.success) {
            ksToast('Ticket created', 'success');
            form.reset();
            currentCreateModal.hide();
            loadMaintenance();
        } else {
            ksToast('Creation failed', 'error');
        }
    } catch(e) {
        ksToast('Error creating ticket', 'error');
    }
}


let currentCreateModalHK = null;

document.addEventListener('DOMContentLoaded', () => {
    currentCreateModalHK = new bootstrap.Modal(document.getElementById('createTaskModal'));
    loadHousekeeping();
});

function clearFiltersHK() {
    document.getElementById('dateAll').checked = true;
    document.getElementById('filterSearchHK').value = '';
    document.getElementById('filterPriorityHK').value = '';
    document.getElementById('filterStatusHK').value = '';
    loadHousekeeping();
}

async function loadHousekeeping() {
    const search = document.getElementById('filterSearchHK').value;
    const priority = document.getElementById('filterPriorityHK').value;
    const status = document.getElementById('filterStatusHK').value;
    const dateFilter = document.querySelector('input[name="dateFilter"]:checked').value;
    
    const params = new URLSearchParams();
    if(search) params.append('search', search);
    if(priority) params.append('priority', priority);
    if(status) params.append('status', status);

    try {
        const res = await fetch(`/api/v1/housekeeping?${params.toString()}`);
        const json = await res.json();
        if(json.success) {
            let data = json.data.data;
            
            // Client-side date filtering if API doesn't support 'dateFilter' enum directly
            const todayStr = new Date().toISOString().split('T')[0];
            if(dateFilter === 'today') {
                data = data.filter(t => t.scheduled_date === todayStr);
            } else if (dateFilter === 'upcoming') {
                data = data.filter(t => t.scheduled_date > todayStr);
            }

            renderTableHK(data);
            updateKPIsHK(data, todayStr);
        } else {
            ksToast('Failed to load tasks', 'error');
        }
    } catch(e) {
        ksToast('Error loading tasks', 'error');
    }
}

function updateKPIsHK(data, todayStr) {
    let pending = 0, inProgress = 0, completedToday = 0;
    data.forEach(t => {
        if(t.status === 'pending') pending++;
        if(t.status === 'in_progress') inProgress++;
        if(t.status === 'completed' && t.scheduled_date === todayStr) completedToday++;
    });
    document.getElementById('kpi-pending').textContent = pending;
    document.getElementById('kpi-progress').textContent = inProgress;
    document.getElementById('kpi-completed').textContent = completedToday;
    document.getElementById('total-count').textContent = data.length;
}

function getPriorityBadgeHK(priority) {
    switch(priority) {
        case 'critical': return '<span class="ks-badge ks-badge-rejected">Critical</span>';
        case 'high': return '<span class="ks-badge ks-badge-pending" style="background:#FEF3C7;color:#7C2D12">High</span>';
        case 'medium': return '<span class="ks-badge ks-badge-pending">Medium</span>';
        case 'low': return '<span class="ks-badge ks-badge-scheduled">Low</span>';
        default: return `<span class="ks-badge ks-badge-scheduled">${ksEscape(priority)}</span>`;
    }
}

function getStatusBadgeHK(status) {
    switch(status) {
        case 'pending': return '<span class="ks-badge ks-badge-pending">Pending</span>';
        case 'assigned': return '<span class="ks-badge ks-badge-scheduled">Assigned</span>';
        case 'in_progress': return '<span class="ks-badge ks-badge-pending">In Progress</span>';
        case 'completed': return '<span class="ks-badge ks-badge-confirmed">Completed</span>';
        case 'cancelled': return '<span class="ks-badge ks-badge-cancelled">Cancelled</span>';
        default: return `<span class="ks-badge ks-badge-scheduled">${ksEscape(status)}</span>`;
    }
}

function getActionsHKDropdownHK(ticket) {
    const s = ticket.status;
    if (s === 'completed' || s === 'cancelled') {
        return `<span class="text-muted"><i class="bi bi-lock"></i> Read Only</span>`;
    }

    let items = '';
    if (s === 'pending') {
        items += `<li><a class="dropdown-item" href="#" onclick="updateStatusHK(${ticket.id}, 'assigned')">Assign</a></li>`;
        items += `<li><a class="dropdown-item" href="#" onclick="updateStatusHK(${ticket.id}, 'cancelled')">Cancel</a></li>`;
    } else if (s === 'assigned') {
        items += `<li><a class="dropdown-item" href="#" onclick="updateStatusHK(${ticket.id}, 'in_progress')">Start</a></li>`;
        items += `<li><a class="dropdown-item" href="#" onclick="updateStatusHK(${ticket.id}, 'cancelled')">Cancel</a></li>`;
    } else if (s === 'in_progress') {
        items += `<li><a class="dropdown-item" href="#" onclick="updateStatusHK(${ticket.id}, 'completed')">Complete</a></li>`;
        items += `<li><a class="dropdown-item" href="#" onclick="updateStatusHK(${ticket.id}, 'cancelled')">Cancel</a></li>`;
    }

    return `
        <div class="dropdown">
            <button class="ks-btn ks-btn-sm ks-btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                Action
            </button>
            <ul class="dropdown-menu">
                ${items}
            </ul>
        </div>
    `;
}

function renderTableHK(data) {
    const tbody = document.getElementById('tableBodyHK');
    if(data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7"><div class="ks-empty-state">No tasks found.</div></td></tr>`;
        return;
    }

    tbody.innerHTML = data.map(t => {
        let slot = t.scheduled_start_time ? (t.scheduled_start_time + (t.scheduled_end_time ? ' - ' + t.scheduled_end_time : '')) : '';
        return `
            <tr>
                <td><strong>${ksEscape(t.task_reference)}</strong></td>
                <td>
                    <div class="fw-bold">${ksEscape(t.task_type)}</div>
                    <div class="small text-muted">Venue: ${ksEscape(t.venue_id || '-')} ${t.facility_id ? '| Facility: '+ksEscape(t.facility_id) : ''}</div>
                </td>
                <td>
                    <div>${ksEscape(t.scheduled_date || '-')}</div>
                    ${slot ? `<div class="small text-muted">${ksEscape(slot)}</div>` : ''}
                </td>
                <td>${getPriorityBadgeHK(t.priority)}</td>
                <td>${ksEscape(t.assigned_employee_id || '-')}</td>
                <td>${getStatusBadgeHK(t.status)}</td>
                <td>${getActionsHKDropdownHK(t)}</td>
            </tr>
        `;
    }).join('');
}

async function updateStatusHK(id, newStatus) {
    event.preventDefault();
    try {
        const res = await fetch(`/api/v1/housekeeping/${id}`, {
            method: 'PATCH',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify({ status: newStatus })
        });
        const json = await res.json();
        if(json.success) {
            ksToast('Status updated', 'success');
            loadHousekeeping();
        } else {
            ksToast('Update failed', 'error');
        }
    } catch(e) {
        ksToast('Error updating status', 'error');
    }
}

async function submitCreateTask() {
    const form = document.getElementById('createFormHK');
    if(!form.reportValidity()) return;

    const payload = {
        task_type: document.getElementById('createTypeHK').value,
        priority: document.getElementById('createPriorityHK').value,
        venue_id: document.getElementById('createVenueHK').value,
        facility_id: document.getElementById('createFacilityHK').value || null,
        assigned_employee_id: document.getElementById('createEmployeeHK').value || null,
        scheduled_date: document.getElementById('createDateHK').value,
        scheduled_start_time: document.getElementById('createStartHK').value || null,
        scheduled_end_time: document.getElementById('createEndHK').value || null,
        description: document.getElementById('createDescHK').value,
        remarks: document.getElementById('createRemarksHK').value
    };

    try {
        const res = await fetch('/api/v1/housekeeping', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if(json.success) {
            ksToast('Task created', 'success');
            form.reset();
            currentCreateModalHK.hide();
            loadHousekeeping();
        } else {
            ksToast('Creation failed', 'error');
        }
    } catch(e) {
        ksToast('Error creating task', 'error');
    }
}


document.addEventListener('DOMContentLoaded', function() {
    const facilityTabs = document.getElementById('facilityTabs');
    if(facilityTabs) {
        facilityTabs.addEventListener('shown.bs.tab', function (e) {
            if (e.target.id === 'maintenance-tab') {
                if (typeof loadMaintenance === 'function') loadMaintenance();
            } else if (e.target.id === 'housekeeping-tab') {
                if (typeof loadHousekeeping === 'function') loadHousekeeping();
            }
        });
    }
});
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
