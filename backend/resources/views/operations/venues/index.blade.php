<?php
$activePage = 'operations_venues';
$title = 'Venues Management — KhelSutra';
ob_start();
?>
<?php
$title = "Venues";
$pageHeader = "Venues & Infrastructure";
$pageSubheader = "Manage physical locations, stadiums, and training grounds.";
ob_start();
?>


<div class="ks-page-header mb-4">
    <div>
        <h2 class="ks-page-title mb-1"><?= htmlspecialchars($pageHeader ?? 'Venues & Infrastructure') ?></h2>
        <p class="ks-page-subtitle"><?= htmlspecialchars($pageSubheader ?? 'Manage physical locations, stadiums, and training grounds.') ?></p>
    </div>
</div>

<div class="ks-filter-bar mb-4">
    <div class="ks-filter-grid">
        <div>
            <div class="ks-search-container">
                <i class="bi bi-search ks-search-icon" style="position: absolute; left: 12px; top: 11px; color: var(--ks-text-muted);"></i>
                <input type="text" class="ks-form-control ks-search-input" id="filterSearch" placeholder="Search venues by name or code..." style="padding-left: 35px;" oninput="loadVenues()">
            </div>
        </div>
        <div>
            <select class="ks-form-select" id="filterStatus" onchange="loadVenues()">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="under_maintenance">Under Maintenance</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div style="flex: 0 0 auto !important; width: auto !important;">
            <button class="ks-btn ks-btn-secondary w-100" onclick="document.getElementById('filterSearch').value=''; document.getElementById('filterStatus').value=''; loadVenues();"><i class="bi bi-x-circle"></i> Clear</button>
        </div>
        <div class="ms-auto d-flex gap-2">
            <button class="ks-btn ks-btn-secondary"><i class="bi bi-download me-1"></i> Export</button>
            <button class="ks-btn ks-btn-primary" data-bs-toggle="modal" data-bs-target="#newVenueModal"><i class="bi bi-plus-lg me-1"></i> Add Venue</button>
        </div>
    </div>
</div>

<!-- Data Card -->
<div class="ks-card" style="padding: 0;">
    <div class="table-responsive">
        <table class="table ks-table mb-0">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 25%;">Venue Name</th>
                    <th style="width: 15%;">Code</th>
                    <th style="width: 15%;">Type</th>
                    <th style="width: 15%;">Location</th>
                    <th style="width: 15%;">Status</th>
                    <th style="width: 10%; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="venuesTableBody">
                <!-- Data loaded via JS -->
            </tbody>
        </table>
    </div>
    
    <div class="p-3 border-top d-flex justify-content-between align-items-center" style="border-color: var(--ks-border-light) !important;">
        <div class="small text-muted" id="venuesPaginationInfo">Loading venues...</div>
    </div>
</div>

<!-- Modal for New Venue -->
<div class="modal fade" id="newVenueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: var(--ks-radius-modal); border: 1px solid var(--ks-border); box-shadow: var(--ks-shadow-modal);">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold text-navy" style="font-size: 18px;">Add New Venue</h5>
                    <p class="small text-muted mb-0">Register a new physical location.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <form id="venueForm">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="ks-form-label">Venue Name *</label>
                            <input type="text" class="ks-form-control" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="ks-form-label">Venue Type</label>
                            <input type="text" class="ks-form-control" name="venue_type" placeholder="e.g. Stadium, Training Ground">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="ks-form-label">Description</label>
                        <textarea class="ks-form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="ks-form-label">City</label>
                            <input type="text" class="ks-form-control" name="city">
                        </div>
                        <div class="col-md-4">
                            <label class="ks-form-label">Capacity</label>
                            <input type="number" class="ks-form-control" name="capacity">
                        </div>
                        <div class="col-md-4">
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
                <button type="button" class="ks-btn ks-btn-primary" onclick="saveVenue()">Save Venue</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', loadVenues);

function loadVenues() {
    fetch('/api/v1/venues')
        .then(res => res.json())
        .then(res => {
            const tbody = document.getElementById('venuesTableBody');
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
                res.data.data.forEach((v, index) => {
                    tbody.innerHTML += `
                        <tr>
                            <td>${index + 1}</td>
                            <td><div class="fw-semibold text-navy">${escapeHtml(v.name)}</div></td>
                            <td><span class="fw-medium text-navy">${escapeHtml(v.venue_code)}</span></td>
                            <td>${escapeHtml(v.venue_type) || '-'}</td>
                            <td>${escapeHtml(v.city) || '-'}</td>
                            <td>
                                <span class="ks-badge ks-badge-${v.status === 'active' ? 'success' : 'warning'}">
                                    ${escapeHtml(v.status)}
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="/operations/venues/${v.id}/facilities" class="ks-btn ks-btn-sm ks-btn-secondary" title="Manage Facilities">
                                        <i class="bi bi-grid"></i>
                                    </a>
                                    <button onclick="deleteVenue(${v.id})" class="ks-btn ks-btn-sm ks-btn-secondary text-danger" title="Delete Venue">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
                document.getElementById('venuesPaginationInfo').innerText = `Total venues: ${res.data.meta.total}`;
            }
        });
}

function saveVenue() {
    const form = document.getElementById('venueForm');
    const data = Object.fromEntries(new FormData(form).entries());
    
    fetch('/api/v1/venues', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(res => res.json()).then(res => {
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('newVenueModal')).hide();
            form.reset();
            loadVenues();
        } else {
            alert('Error: ' + JSON.stringify(res.errors || res.message));
        }
    });
}

function deleteVenue(id) {
    if (confirm("Are you sure you want to delete this venue?")) {
        fetch('/api/v1/venues/' + id, { method: 'DELETE' })
            .then(res => res.json())
            .then(res => {
                if (res.success) loadVenues();
                else alert(res.message);
            });
    }
}
</script>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../../layouts/app.blade.php';
?>
