@extends('layouts.app')

@section('title', 'Facilities & Maintenance')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0 text-gray-800">Facilities & Maintenance <small class="text-muted fs-6 ms-2">Housekeeping Management</small></h1>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs ks-nav-tabs mb-4" id="housekeepingTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks-pane" type="button" role="tab" aria-controls="tasks-pane" aria-selected="true">Tasks</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="schedules-tab" data-bs-toggle="tab" data-bs-target="#schedules-pane" type="button" role="tab" aria-controls="schedules-pane" aria-selected="false">Schedules</button>
        </li>
    </ul>

    <div class="tab-content" id="housekeepingTabsContent">
        <!-- Tasks Pane -->
        <div class="tab-pane fade show active" id="tasks-pane" role="tabpanel" aria-labelledby="tasks-tab" tabindex="0">
            <!-- KPI Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Tasks</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpi-pending">0</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-hourglass-split fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">In Progress</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpi-inprogress">0</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-tools fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4 mb-md-0">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed Today</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpi-completed">0</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-check-circle fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Overdue</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpi-overdue">0</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-exclamation-triangle fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters & Actions -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="btn-group" role="group" aria-label="Task timeframe filters">
                    <input type="radio" class="btn-check" name="task_timeframe" id="filter-today" autocomplete="off" value="today" checked>
                    <label class="btn btn-outline-primary btn-sm" for="filter-today">Today</label>

                    <input type="radio" class="btn-check" name="task_timeframe" id="filter-upcoming" autocomplete="off" value="upcoming">
                    <label class="btn btn-outline-primary btn-sm" for="filter-upcoming">Upcoming</label>

                    <input type="radio" class="btn-check" name="task_timeframe" id="filter-all" autocomplete="off" value="all">
                    <label class="btn btn-outline-primary btn-sm" for="filter-all">All</label>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                    <i class="bi bi-plus-lg"></i> Create Task
                </button>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="m-0 font-weight-bold text-primary">Housekeeping Tasks</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <input type="text" id="task-search" class="form-control form-control-sm" placeholder="Search tasks..." style="max-width: 200px;">
                        <select id="task-priority-filter" class="form-select form-select-sm" style="max-width: 150px;">
                            <option value="">All Priorities</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                        <select id="task-status-filter" class="form-select form-select-sm" style="max-width: 150px;">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="assigned">Assigned</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                        <select id="task-employee-filter" class="form-select form-select-sm employee-select-filter" style="max-width: 200px;">
                            <option value="">All Employees</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle" id="tasksTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Reference</th>
                                    <th>Task Type</th>
                                    <th>Venue / Facility / Area</th>
                                    <th>Scheduled Date & Time</th>
                                    <th>Priority</th>
                                    <th>Assigned To</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tasksTableBody">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedules Pane -->
        <div class="tab-pane fade" id="schedules-pane" role="tabpanel" aria-labelledby="schedules-tab" tabindex="0">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Automated Schedules</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createScheduleModal">
                    <i class="bi bi-plus-lg"></i> Create Schedule
                </button>
            </div>
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle" id="schedulesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Schedule Name</th>
                                    <th>Task Type</th>
                                    <th>Venue / Facility</th>
                                    <th>Frequency</th>
                                    <th>Time</th>
                                    <th>Assigned To</th>
                                    <th>Next Run</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="schedulesTableBody">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Task Modals -->

<!-- Create Task Modal -->
<div class="modal fade" id="createTaskModal" tabindex="-1" aria-labelledby="createTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="createTaskForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createTaskModalLabel">Create Manual Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Task Type <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="task_type" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select class="form-select" name="priority" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Venue <span class="text-danger">*</span></label>
                            <select class="form-select" name="venue_id" id="task_venue_id" required>
                                <option value="">Select Venue</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Facility</label>
                            <select class="form-select" name="facility_id" id="task_facility_id">
                                <option value="">Select Facility</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Area (Optional)</label>
                            <input type="text" class="form-control" name="area">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Scheduled Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="scheduled_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Time (Optional)</label>
                            <input type="time" class="form-control" name="scheduled_time">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Assign To Employee (Optional)</label>
                            <select class="form-select employee-select" name="assigned_employee_id">
                                <option value="">Select Employee</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Event/Match Link (Optional)</label>
                            <div class="input-group">
                                <select class="form-select" name="link_type" id="task_link_type">
                                    <option value="">None</option>
                                    <option value="event">Event</option>
                                    <option value="match">Match</option>
                                </select>
                                <input type="number" class="form-control" name="link_id" placeholder="ID" disabled>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks / Instructions</label>
                        <textarea class="form-control" name="remarks" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Assign Task Modal -->
<div class="modal fade" id="assignTaskModal" tabindex="-1" aria-labelledby="assignTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="assignTaskForm">
            <input type="hidden" name="task_id" id="assign_task_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignTaskModalLabel">Assign Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Employee <span class="text-danger">*</span></label>
                        <select class="form-select employee-select" name="employee_id" required>
                            <option value="">Select Employee</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Task</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Complete Task Modal -->
<div class="modal fade" id="completeTaskModal" tabindex="-1" aria-labelledby="completeTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="completeTaskForm">
            <input type="hidden" name="task_id" id="complete_task_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="completeTaskModalLabel">Complete Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Completion Remarks</label>
                        <textarea class="form-control" name="remarks" rows="3" placeholder="Add any notes about the task completion..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Mark as Completed</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Reopen Task Modal -->
<div class="modal fade" id="reopenTaskModal" tabindex="-1" aria-labelledby="reopenTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="reopenTaskForm">
            <input type="hidden" name="task_id" id="reopen_task_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reopenTaskModalLabel">Reopen Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason for Reopening <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="remarks" rows="3" required placeholder="Why does this task need to be reopened?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reopen Task</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Create Schedule Modal -->
<div class="modal fade" id="createScheduleModal" tabindex="-1" aria-labelledby="createScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="createScheduleForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createScheduleModalLabel">Create Automated Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Schedule Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required placeholder="e.g., Morning Cleanup">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Task Type <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="task_type" required placeholder="e.g., General Cleaning">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Venue <span class="text-danger">*</span></label>
                            <select class="form-select" name="venue_id" id="schedule_venue_id" required>
                                <option value="">Select Venue</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Facility</label>
                            <select class="form-select" name="facility_id" id="schedule_facility_id">
                                <option value="">Select Facility</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Area (Optional)</label>
                            <input type="text" class="form-control" name="area">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Frequency <span class="text-danger">*</span></label>
                            <select class="form-select" name="frequency" id="schedule_frequency" required>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="scheduled_time" required>
                        </div>
                        <div class="col-md-3" id="schedule_day_of_week_container" style="display:none;">
                            <label class="form-label">Day of Week <span class="text-danger">*</span></label>
                            <select class="form-select" name="day_of_week">
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                                <option value="0">Sunday</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="schedule_day_of_month_container" style="display:none;">
                            <label class="form-label">Day of Month <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="day_of_month" min="1" max="31">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Default Assigned Employee</label>
                            <select class="form-select employee-select" name="assigned_employee_id">
                                <option value="">Select Employee (Optional)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select class="form-select" name="priority" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Schedule</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- State & Elements ---
    let tasks = [];
    let schedules = [];
    const tasksTableBody = document.getElementById('tasksTableBody');
    const schedulesTableBody = document.getElementById('schedulesTableBody');
    
    // Filters
    const timeframeRadios = document.querySelectorAll('input[name="task_timeframe"]');
    const taskSearch = document.getElementById('task-search');
    const priorityFilter = document.getElementById('task-priority-filter');
    const statusFilter = document.getElementById('task-status-filter');
    const employeeFilter = document.getElementById('task-employee-filter');
    
    // Modals
    const assignTaskModal = new bootstrap.Modal(document.getElementById('assignTaskModal'));
    const completeTaskModal = new bootstrap.Modal(document.getElementById('completeTaskModal'));
    const reopenTaskModal = new bootstrap.Modal(document.getElementById('reopenTaskModal'));
    const createTaskModal = new bootstrap.Modal(document.getElementById('createTaskModal'));
    const createScheduleModal = new bootstrap.Modal(document.getElementById('createScheduleModal'));
    
    // API Endpoints (Base)
    const apiTasks = '/api/v1/housekeeping/tasks';
    const apiSchedules = '/api/v1/housekeeping/schedules';
    
    // --- Initial Load ---
    loadTasks();
    loadSchedules();
    loadEmployees(); // Pre-fill employee selects
    loadVenues();    // Pre-fill venue selects
    
    // --- Filter Event Listeners ---
    timeframeRadios.forEach(radio => radio.addEventListener('change', renderTasks));
    taskSearch.addEventListener('input', renderTasks);
    priorityFilter.addEventListener('change', renderTasks);
    statusFilter.addEventListener('change', renderTasks);
    employeeFilter.addEventListener('change', renderTasks);
    
    // --- Task Form Links Toggle ---
    const taskLinkType = document.getElementById('task_link_type');
    const taskLinkId = document.querySelector('input[name="link_id"]');
    if (taskLinkType) {
        taskLinkType.addEventListener('change', function(e) {
            if(e.target.value) {
                taskLinkId.disabled = false;
                taskLinkId.required = true;
                taskLinkId.placeholder = e.target.value === 'event' ? 'Event ID' : 'Match ID';
            } else {
                taskLinkId.disabled = true;
                taskLinkId.required = false;
                taskLinkId.value = '';
                taskLinkId.placeholder = 'ID';
            }
        });
    }

    // --- Schedule Frequency Toggle ---
    const freqSelect = document.getElementById('schedule_frequency');
    const dayOfWeekCont = document.getElementById('schedule_day_of_week_container');
    const dayOfMonthCont = document.getElementById('schedule_day_of_month_container');
    if (freqSelect) {
        freqSelect.addEventListener('change', function(e) {
            const val = e.target.value;
            dayOfWeekCont.style.display = val === 'weekly' ? 'block' : 'none';
            dayOfMonthCont.style.display = val === 'monthly' ? 'block' : 'none';
            
            const dowSelect = document.querySelector('select[name="day_of_week"]');
            const domInput = document.querySelector('input[name="day_of_month"]');
            if(dowSelect) dowSelect.required = (val === 'weekly');
            if(domInput) domInput.required = (val === 'monthly');
        });
    }

    // --- Data Fetching ---
    function loadTasks() {
        // Fetch tasks
        fetch(apiTasks)
            .then(res => res.json())
            .then(data => {
                tasks = data.data || [];
                updateTaskKPIs();
                renderTasks();
            })
            .catch(err => {
                console.error('Error fetching tasks:', err);
                if(typeof ksToast === 'function') ksToast('Failed to load tasks', 'danger');
            });
    }
    
    function loadSchedules() {
        fetch(apiSchedules)
            .then(res => res.json())
            .then(data => {
                schedules = data.data || [];
                renderSchedules();
            })
            .catch(err => console.error('Error fetching schedules:', err));
    }
    
    function loadEmployees() {
        fetch('/api/v1/employees')
            .then(res => res.json())
            .then(data => {
                const employees = data.data || [];
                const selects = document.querySelectorAll('.employee-select');
                
                // Clear existing (except first)
                selects.forEach(select => {
                    const firstOpt = select.options[0];
                    select.innerHTML = '';
                    if(firstOpt) select.appendChild(firstOpt);
                    
                    employees.forEach(emp => {
                        const opt = document.createElement('option');
                        opt.value = emp.id;
                        opt.textContent = `${typeof ksEscape === 'function' ? ksEscape(emp.first_name) : emp.first_name} ${typeof ksEscape === 'function' ? ksEscape(emp.last_name) : emp.last_name}`;
                        select.appendChild(opt.cloneNode(true));
                    });
                });
                
                // Populate employee filter specifically
                const filter = document.querySelector('.employee-select-filter');
                if(filter) {
                    employees.forEach(emp => {
                        const opt = document.createElement('option');
                        opt.value = emp.id;
                        opt.textContent = `${typeof ksEscape === 'function' ? ksEscape(emp.first_name) : emp.first_name} ${typeof ksEscape === 'function' ? ksEscape(emp.last_name) : emp.last_name}`;
                        filter.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error('Error fetching employees:', err));
    }

    function loadVenues() {
        fetch('/api/v1/venues')
            .then(res => res.json())
            .then(data => {
                const venues = data.data || [];
                const taskV = document.getElementById('task_venue_id');
                const schedV = document.getElementById('schedule_venue_id');
                
                venues.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.id;
                    opt.textContent = typeof ksEscape === 'function' ? ksEscape(v.name) : v.name;
                    
                    if(taskV) taskV.appendChild(opt.cloneNode(true));
                    if(schedV) schedV.appendChild(opt.cloneNode(true));
                });

                // Attach venue change events for facility loads
                if(taskV) taskV.addEventListener('change', (e) => loadFacilities(e.target.value, 'task_facility_id'));
                if(schedV) schedV.addEventListener('change', (e) => loadFacilities(e.target.value, 'schedule_facility_id'));
            })
            .catch(err => console.error('Error fetching venues:', err));
    }

    function loadFacilities(venueId, targetSelectId) {
        const select = document.getElementById(targetSelectId);
        if(!select) return;
        select.innerHTML = '<option value="">Select Facility</option>';
        if(!venueId) return;

        fetch(`/api/v1/venues/${venueId}/facilities`)
            .then(res => res.json())
            .then(data => {
                const facilities = data.data || [];
                facilities.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f.id;
                    opt.textContent = typeof ksEscape === 'function' ? ksEscape(f.name) : f.name;
                    select.appendChild(opt);
                });
            })
            .catch(err => console.error('Error fetching facilities:', err));
    }

    // --- Rendering logic ---
    function updateTaskKPIs() {
        const todayStr = new Date().toISOString().split('T')[0];
        
        let pending = 0;
        let inProgress = 0;
        let completedToday = 0;
        let overdue = 0;

        const now = new Date();

        tasks.forEach(task => {
            if (task.status === 'pending' || task.status === 'assigned') pending++;
            if (task.status === 'in_progress') inProgress++;
            if (task.status === 'completed' && task.completed_at && task.completed_at.startsWith(todayStr)) completedToday++;
            
            if (task.status !== 'completed' && task.status !== 'cancelled' && task.status !== 'closed') {
                const taskDate = new Date(`${task.scheduled_date}T${task.scheduled_time || '23:59:00'}`);
                if (taskDate < now) overdue++;
            }
        });

        document.getElementById('kpi-pending').textContent = pending;
        document.getElementById('kpi-inprogress').textContent = inProgress;
        document.getElementById('kpi-completed').textContent = completedToday;
        document.getElementById('kpi-overdue').textContent = overdue;
    }

    function safeEscape(str) {
        if (!str) return '';
        return typeof ksEscape === 'function' ? ksEscape(str) : str.replace(/[&<>'"]/g, 
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[tag] || tag)
        );
    }

    function getPriorityBadge(priority) {
        const map = {
            'low': 'bg-info text-dark',
            'medium': 'bg-primary',
            'high': 'bg-warning text-dark',
            'critical': 'bg-danger'
        };
        const cls = map[priority] || 'bg-secondary';
        const txt = priority ? priority.charAt(0).toUpperCase() + priority.slice(1) : 'Unknown';
        return `<span class="badge ${cls}">${safeEscape(txt)}</span>`;
    }

    function getStatusBadge(status) {
        const map = {
            'pending': 'bg-secondary',
            'assigned': 'bg-info text-dark',
            'in_progress': 'bg-primary',
            'completed': 'bg-success',
            'verified': 'bg-success',
            'closed': 'bg-dark',
            'cancelled': 'bg-danger'
        };
        const cls = map[status] || 'bg-secondary';
        const txt = status ? status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Unknown';
        return `<span class="badge ${cls}">${safeEscape(txt)}</span>`;
    }

    function renderTasks() {
        if(!tasksTableBody) return;
        
        const timeframe = document.querySelector('input[name="task_timeframe"]:checked')?.value || 'today';
        const searchVal = taskSearch.value.toLowerCase();
        const priorityVal = priorityFilter.value;
        const statusVal = statusFilter.value;
        const employeeVal = employeeFilter.value;

        const todayStr = new Date().toISOString().split('T')[0];

        let filteredTasks = tasks.filter(task => {
            // Timeframe
            if (timeframe === 'today' && task.scheduled_date !== todayStr) return false;
            if (timeframe === 'upcoming' && task.scheduled_date <= todayStr) return false;

            // Search
            if (searchVal) {
                const text = `${task.task_reference || ''} ${task.task_type || ''} ${task.venue_name || ''} ${task.facility_name || ''}`.toLowerCase();
                if (!text.includes(searchVal)) return false;
            }

            // Selects
            if (priorityVal && task.priority !== priorityVal) return false;
            if (statusVal && task.status !== statusVal) return false;
            if (employeeVal && task.assigned_employee_id != employeeVal) return false;

            return true;
        });

        tasksTableBody.innerHTML = '';
        if (filteredTasks.length === 0) {
            tasksTableBody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No tasks found.</td></tr>`;
            return;
        }

        filteredTasks.forEach(task => {
            let location = safeEscape(task.venue_name || 'No Venue');
            if (task.facility_name) location += ` / ${safeEscape(task.facility_name)}`;
            if (task.area) location += ` / ${safeEscape(task.area)}`;
            
            let employeeName = task.assigned_employee_name ? safeEscape(task.assigned_employee_name) : '<em class="text-muted">Unassigned</em>';
            
            let actions = `<div class="btn-group btn-group-sm">`;
            
            if (task.status === 'pending' || task.status === 'assigned') {
                actions += `<button type="button" class="btn btn-outline-primary" onclick="openAssignModal(${task.id})" title="Assign"><i class="bi bi-person-plus"></i></button>`;
                actions += `<button type="button" class="btn btn-outline-success" onclick="updateTaskStatus(${task.id}, 'start')" title="Start Task"><i class="bi bi-play-circle"></i></button>`;
            } else if (task.status === 'in_progress') {
                actions += `<button type="button" class="btn btn-outline-success" onclick="openCompleteModal(${task.id})" title="Complete"><i class="bi bi-check2-circle"></i></button>`;
            } else if (task.status === 'completed') {
                actions += `<button type="button" class="btn btn-outline-warning" onclick="openReopenModal(${task.id})" title="Reopen"><i class="bi bi-arrow-counterclockwise"></i></button>`;
                actions += `<button type="button" class="btn btn-outline-dark" onclick="updateTaskStatus(${task.id}, 'close')" title="Close"><i class="bi bi-lock"></i></button>`;
            }
            
            if (task.status !== 'completed' && task.status !== 'closed' && task.status !== 'cancelled') {
                actions += `<button type="button" class="btn btn-outline-danger" onclick="updateTaskStatus(${task.id}, 'cancel')" title="Cancel"><i class="bi bi-x-circle"></i></button>`;
            }
            actions += `</div>`;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span class="fw-bold">${safeEscape(task.task_reference || '-')}</span></td>
                <td>${safeEscape(task.task_type || '-')}</td>
                <td>${location}</td>
                <td>${safeEscape(task.scheduled_date)} ${safeEscape(task.scheduled_time || '')}</td>
                <td>${getPriorityBadge(task.priority)}</td>
                <td>${employeeName}</td>
                <td>${getStatusBadge(task.status)}</td>
                <td>${actions}</td>
            `;
            tasksTableBody.appendChild(tr);
        });
    }

    function renderSchedules() {
        if(!schedulesTableBody) return;
        schedulesTableBody.innerHTML = '';
        if (schedules.length === 0) {
            schedulesTableBody.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No automated schedules found.</td></tr>`;
            return;
        }

        schedules.forEach(schedule => {
            let location = safeEscape(schedule.venue_name || '-');
            if (schedule.facility_name) location += ` / ${safeEscape(schedule.facility_name)}`;
            
            let employeeName = schedule.assigned_employee_name ? safeEscape(schedule.assigned_employee_name) : '<em class="text-muted">Unassigned</em>';
            
            const actions = `
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" onclick="editSchedule(${schedule.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteSchedule(${schedule.id})" title="Delete"><i class="bi bi-trash"></i></button>
                </div>
            `;
            
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${safeEscape(schedule.name)}</td>
                <td>${safeEscape(schedule.task_type)}</td>
                <td>${location}</td>
                <td><span class="badge bg-secondary text-capitalize">${safeEscape(schedule.frequency)}</span></td>
                <td>${safeEscape(schedule.scheduled_time)}</td>
                <td>${employeeName}</td>
                <td>${safeEscape(schedule.next_run_date || 'Not Scheduled')}</td>
                <td>${schedule.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'}</td>
                <td>${actions}</td>
            `;
            schedulesTableBody.appendChild(tr);
        });
    }

    // --- Action Modal Handlers ---
    window.openAssignModal = function(id) {
        document.getElementById('assign_task_id').value = id;
        assignTaskModal.show();
    };

    window.openCompleteModal = function(id) {
        document.getElementById('complete_task_id').value = id;
        completeTaskModal.show();
    };

    window.openReopenModal = function(id) {
        document.getElementById('reopen_task_id').value = id;
        reopenTaskModal.show();
    };

    // Generic status update via PATCH /api/v1/housekeeping/tasks/{id}/{action}
    window.updateTaskStatus = function(id, action, bodyData = null) {
        if (action === 'cancel' && !confirm('Are you sure you want to cancel this task?')) return;
        
        let fetchOptions = {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };
        
        if (bodyData) {
            fetchOptions.body = JSON.stringify(bodyData);
        }

        fetch(`/api/v1/housekeeping/tasks/${id}/${action}`, fetchOptions)
            .then(async res => {
                const isJson = res.headers.get('content-type')?.includes('application/json');
                const data = isJson ? await res.json() : null;
                if (!res.ok) throw new Error(data?.message || `Failed to ${action} task.`);
                return data;
            })
            .then(() => {
                if(typeof ksToast === 'function') ksToast(`Task ${action} successfully.`, 'success');
                loadTasks();
            })
            .catch(err => {
                console.error(err);
                if(typeof ksToast === 'function') ksToast(err.message, 'danger');
            });
    };

    // --- Form Submissions ---
    document.getElementById('createTaskForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        let data = Object.fromEntries(formData.entries());
        
        // Handle optional link
        if (data.link_type && data.link_id) {
            data[`${data.link_type}_id`] = data.link_id;
        }
        delete data.link_type;
        delete data.link_id;

        fetch(apiTasks, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(async res => {
            const result = await res.json();
            if(!res.ok) throw new Error(result.message || 'Error creating task');
            if(typeof ksToast === 'function') ksToast('Task created successfully', 'success');
            createTaskModal.hide();
            this.reset();
            loadTasks();
        })
        .catch(err => {
            if(typeof ksToast === 'function') ksToast(err.message, 'danger');
        });
    });

    document.getElementById('assignTaskForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('assign_task_id').value;
        const employee_id = this.employee_id.value;
        updateTaskStatus(id, 'assign', { assigned_employee_id: employee_id });
        assignTaskModal.hide();
    });

    document.getElementById('completeTaskForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('complete_task_id').value;
        const remarks = this.remarks.value;
        updateTaskStatus(id, 'complete', { remarks });
        completeTaskModal.hide();
        this.reset();
    });

    document.getElementById('reopenTaskForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('reopen_task_id').value;
        const remarks = this.remarks.value;
        updateTaskStatus(id, 'reopen', { remarks });
        reopenTaskModal.hide();
        this.reset();
    });

    document.getElementById('createScheduleForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());

        fetch(apiSchedules, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(async res => {
            const result = await res.json();
            if(!res.ok) throw new Error(result.message || 'Error creating schedule');
            if(typeof ksToast === 'function') ksToast('Schedule created successfully', 'success');
            createScheduleModal.hide();
            this.reset();
            loadSchedules();
        })
        .catch(err => {
            if(typeof ksToast === 'function') ksToast(err.message, 'danger');
        });
    });

    // Schedule actions (placeholder for potential edit/delete endpoints)
    window.editSchedule = function(id) {
        if(typeof ksToast === 'function') ksToast('Edit schedule not fully implemented yet.', 'info');
        // Fetch schedule and populate modal
    };

    window.deleteSchedule = function(id) {
        if (!confirm('Are you sure you want to delete this schedule?')) return;
        fetch(`${apiSchedules}/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(res => {
            if(!res.ok) throw new Error('Failed to delete schedule');
            if(typeof ksToast === 'function') ksToast('Schedule deleted', 'success');
            loadSchedules();
        })
        .catch(err => {
            if(typeof ksToast === 'function') ksToast(err.message, 'danger');
        });
    };

});
</script>
@endpush
