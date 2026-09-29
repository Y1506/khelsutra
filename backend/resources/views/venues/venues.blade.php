<?php
$pageTitle = 'Venues — KhelSutra';
$activePage = 'venues';
$orgId = current_organization_id();

$venueService = new \App\Services\Venue\VenueService();
$page = (int)($_GET['page'] ?? 1);
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$result = $venueService->listVenues($orgId, $page, 15, $search ?: null, $status ?: null);
$venues = $result['data'] ?? [];
$total = $result['total'] ?? 0;
$totalPages = $result['total_pages'] ?? 1;

ob_start();
?>

<div class="ks-content">
    <!-- Clean Page Header Standard -->
    <div class="ks-page-header mb-4">
        <div>
            <h1 class="ks-page-title mb-1">Venues</h1>
            <p class="ks-page-subtitle mb-0">Manage sports grounds, facilities, and physical locations</p>
        </div>
        <div class="ks-header-actions d-flex gap-2">
            <!-- Bookings Dropdown -->
            <div class="dropdown">
                <button class="ks-btn ks-btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 18px;">
                    <i class="bi bi-calendar-plus"></i> Bookings
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="border-radius: var(--ks-radius-md); font-size: 13px;">
                    <li><a class="dropdown-item py-2" href="/venues/bookings/create"><i class="bi bi-plus-circle me-2 text-primary"></i> New Booking</a></li>
                    <?php if (in_array($_SESSION['auth']['role']['slug'] ?? '', ['super_admin', 'sports_admin', 'venue_manager'])): ?>
                    <li><a class="dropdown-item py-2" href="/operations/bookings"><i class="bi bi-eye me-2 text-secondary"></i> View Bookings</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Add Venue Button -->
            <?php if (in_array($_SESSION['auth']['role']['slug'] ?? '', ['super_admin', 'sports_admin', 'venue_manager'])): ?>
            <a href="/venues/create" class="ks-btn ks-btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 18px; text-decoration: none;">
                <i class="bi bi-plus-lg"></i> Add Venue
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search & Filters Toolbar -->
    <div class="ks-filter-bar mb-4">
        <form method="GET" action="/venues" class="ks-filter-grid">
            <div>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button) 0 0 var(--ks-radius-button);">
                        <i class="bi bi-search text-muted" style="font-size: 13px;"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search venue name, code, type, city..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" style="border-color: var(--ks-border); border-radius: 0 var(--ks-radius-button) var(--ks-radius-button) 0; font-size: 13px;">
                </div>
            </div>
            <div>
                <select name="status" class="form-select" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button); font-size: 13px;">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="under_maintenance" <?= $status === 'under_maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn ks-btn ks-btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 500; font-size: 13px;">
                    Filter
                </button>
                <?php if ($search || $status): ?>
                    <a href="/venues" class="ks-btn ks-btn-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;" title="Reset filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Venues Table -->
    <div class="card" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
        <div>
            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                    <tr>
                        <th class="py-3 px-3 text-muted fw-semibold" style="width: 270px;">Venue</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Type</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Facilities</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Timings</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Location</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Status</th>
                        <th class="py-3 px-3 text-muted fw-semibold text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($venues)): ?>
                        <?php foreach ($venues as $venue): ?>
                            <tr>
                                <td class="py-3 px-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 38px; height: 38px; border-radius: 8px; background: #EDE9FE; color: #7C3AED; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                                            <i class="bi bi-geo-alt-fill"></i>
                                        </div>
                                        <div>
                                            <a href="/venues/<?= (int)$venue['id'] ?>" class="fw-semibold text-decoration-none text-dark d-block">
                                                <?= htmlspecialchars($venue['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted small" style="font-family: monospace; font-size: 11px;"><?= htmlspecialchars($venue['venue_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-dark fw-medium">
                                    <?= htmlspecialchars($venue['venue_type'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                        <?= (int)($venue['facility_count'] ?? 0) ?> Facilities
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-muted small">
                                    <div><?= htmlspecialchars(substr($venue['opening_time'] ?? '', 0, 5), ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(substr($venue['closing_time'] ?? '', 0, 5), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($venue['capacity'])): ?>
                                        <div style="font-size: 11px;">Cap: <?= number_format($venue['capacity']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-dark">
                                    <div><?= htmlspecialchars($venue['city'] ?? 'Mumbai', ENT_QUOTES, 'UTF-8') ?></div>
                                    <span class="text-muted small"><?= htmlspecialchars($venue['state'] ?? 'Maharashtra', ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="py-3 px-3">
                                    <?php
                                        $vStatus = $venue['status'] ?? 'active';
                                        $badge = match($vStatus) {
                                            'active' => 'badge-success',
                                            'under_maintenance' => 'badge-warning',
                                            default => 'badge-secondary'
                                        };
                                    ?>
                                    <form action="/venues/<?= (int)$venue['id'] ?>/status" method="POST" style="display: contents;" class="d-inline m-0 p-0">
                                        <input type="hidden" name="status" value="<?= $vStatus === 'active' ? 'inactive' : 'active' ?>">
                                        <button type="submit" class="badge <?= $badge ?>" style="cursor: pointer; border: none; border-radius: 12px; font-size: 11px; padding: 4px 10px; text-transform: capitalize; font-family: inherit;" title="Click to toggle status to <?= $vStatus === 'active' ? 'Inactive' : 'Active' ?>">
                                            <?= htmlspecialchars(str_replace('_', ' ', $vStatus), ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="py-3 px-3 text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" title="Actions" style="border: none; background: transparent;">
                                            <i class="bi bi-three-dots-vertical text-dark"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:13px; min-width:160px; border-radius: var(--ks-radius-md);">
                                            <li><a class="dropdown-item py-2 fw-medium text-secondary" href="/venues/<?= (int)$venue['id'] ?>"><i class="bi bi-eye me-2"></i>View Facilities</a></li>
                                            <li><a class="dropdown-item py-2 fw-medium text-secondary" href="/venues/<?= (int)$venue['id'] ?>/edit"><i class="bi bi-pencil me-2"></i>Edit Venue</a></li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li>
                                                <form action="/venues/<?= (int)$venue['id'] ?>/delete" method="POST" class="m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this venue? This action marks the venue as deleted.');">
                                                    <button type="submit" class="dropdown-item py-2 fw-medium text-danger" style="background: transparent; border: none; width: 100%; text-align: left;"><i class="bi bi-trash me-2"></i>Delete</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted mb-2"><i class="bi bi-geo-alt fs-2"></i></div>
                                <h6 class="fw-bold" style="color: var(--ks-navy);">No venues found</h6>
                                <p class="text-muted small mb-3">No sports grounds or facilities match the selected filters.</p>
                                <a href="/venues/create" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 500;">
                                    <i class="bi bi-plus-lg me-1"></i> Add Venue
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total > 0): ?>
            <div class="card-footer d-flex align-items-center justify-content-between py-3 px-3 bg-white" style="border-top: 1px solid var(--ks-border);">
                <div class="text-muted small">
                    Showing <strong><?= count($venues) ?></strong> of <strong><?= (int)$total ?></strong> venues
                </div>
                <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">Previous</a>
                            </li>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
