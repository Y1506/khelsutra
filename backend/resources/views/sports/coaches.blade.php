<?php
$activePage = 'coaches';
$title = 'Coaches — KhelSutra';

$orgId = current_organization_id();
$coachService = new \App\Services\Coach\CoachService();
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$spec = trim($_GET['specialization'] ?? '');
$status = trim($_GET['status'] ?? '');

$result = $coachService->listCoaches($orgId, $page, 15, $search ?: null, $spec ?: null, $status ?: null);
$coaches = $result['data'] ?? [];
$total = $result['total'] ?? 0;
$totalPages = $result['total_pages'] ?? 1;

// Specializations for filter
$db = \App\Services\BaseService::getDatabaseConnection();
$specStmt = $db->query("SELECT DISTINCT specialization FROM coach_profiles WHERE specialization IS NOT NULL AND specialization != '' ORDER BY specialization ASC");
$specializations = $specStmt ? $specStmt->fetchAll(PDO::FETCH_COLUMN) : [];

ob_start();
?>

<!-- Clean Page Header Standard (Section 8) -->
<div class="ks-page-header">
    <div>
        <h1 class="ks-page-title">Coaches</h1>
    </div>
    <div class="ks-header-actions">
        <a href="/coaches/create" class="ks-btn ks-btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Add Coach</span>
        </a>
    </div>
</div>

<?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill text-success"></i>
        <span><?= htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-octagon-fill text-danger"></i>
        <span><?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<!-- Search & Filters Toolbar -->
<div class="ks-filter-bar mb-4">
    <form method="GET" action="/coaches" class="ks-filter-grid">
        <div class="position-relative">
            <i class="bi bi-search position-absolute" style="left: 12px; top: 12px; color: var(--ks-text-muted); font-size: 13px;"></i>
            <input type="text" name="search" class="ks-form-control" placeholder="Search by coach name, code, specialization..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" style="padding-left: 34px; height: 38px; font-size: 13px;">
        </div>
        <div>
            <select name="specialization" class="ks-form-select" style="height: 38px; font-size: 13px;">
                <option value="">All Specializations</option>
                <?php foreach ($specializations as $sp): ?>
                    <option value="<?= htmlspecialchars($sp, ENT_QUOTES, 'UTF-8') ?>" <?= $spec === $sp ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sp, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <select name="status" class="ks-form-select" style="height: 38px; font-size: 13px;">
                <option value="">All Statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="ks-btn ks-btn-primary" style="height: 38px; font-size: 13px; min-width: 90px;">
                Filter
            </button>
            <?php if ($search || $spec || $status): ?>
                <a href="/coaches" class="ks-btn ks-btn-secondary" style="height: 38px; font-size: 13px;" title="Reset filters">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Coaches Table -->
<div class="ks-table-card">
    <div class="ks-table-header">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-person-badge-fill" style="color: var(--ks-primary); font-size: 18px;"></i>
            <span class="ks-card-title mb-0">Coaching Staff (<?= (int)$total ?>)</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="ks-table ks-table-coaches">
            <thead>
                <tr>
                    <th>Coach</th>
                    <th>Code</th>
                    <th>Specialization</th>
                    <th>Assigned Teams</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($coaches)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="text-muted mb-2"><i class="bi bi-person-badge fs-2"></i></div>
                            <h6 class="fw-bold text-navy">No coaches found</h6>
                            <p class="text-muted small mb-3">No coaching personnel match your current search or filter criteria.</p>
                            <a href="/coaches/create" class="ks-btn ks-btn-primary" style="display: inline-flex;">
                                <i class="bi bi-plus-lg me-1"></i> Add Coach
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($coaches as $coach): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width: 36px; height: 36px; border-radius: 50%; background: #EEF2FF; color: #4F46E5; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">
                                        <?= strtoupper(substr($coach['first_name'] ?? 'C', 0, 1) . substr($coach['last_name'] ?? '', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <a href="/coaches/<?= (int)$coach['coach_profile_id'] ?>" class="fw-semibold text-decoration-none text-navy d-block">
                                            <?= htmlspecialchars(($coach['first_name'] ?? '') . ' ' . ($coach['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted small"><?= htmlspecialchars($coach['designation'] ?? 'Coach', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border px-2 py-1 font-monospace" style="font-size: 11px;">
                                    <?= htmlspecialchars($coach['coach_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-medium text-navy"><?= htmlspecialchars($coach['specialization'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-muted small" style="font-size: 11px;"><?= (float)($coach['experience_years'] ?? 0) ?> yrs experience</div>
                            </td>
                            <td>
                                <?php if (!empty($coach['assigned_teams'])): ?>
                                    <span class="ks-badge ks-badge-blue px-2 py-1" style="font-size: 11px; max-width: 200px; text-overflow: ellipsis; overflow: hidden; display: inline-block;">
                                        <?= htmlspecialchars($coach['assigned_teams'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">No active team</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small">
                                <div><?= htmlspecialchars($coach['phone'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                                <div style="font-size: 11px;"><?= htmlspecialchars($coach['email'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <?php $cStatus = $coach['coach_status'] ?? 'active'; ?>
                                <form action="/coaches/<?= (int)$coach['coach_profile_id'] ?>/status" method="POST" style="display: contents;" class="d-inline m-0 p-0">
                                    <input type="hidden" name="status" value="<?= $cStatus === 'active' ? 'inactive' : 'active' ?>">
                                    <button type="submit" class="ks-badge <?= $cStatus === 'active' ? 'ks-badge-confirmed' : 'ks-badge-scheduled' ?>" style="cursor: pointer; border: 1px solid <?= $cStatus === 'active' ? '#BBF7D0' : '#BFDBFE' ?>; background-color: <?= $cStatus === 'active' ? '#DCFCE7' : '#DBEAFE' ?>; color: <?= $cStatus === 'active' ? '#166534' : '#1E40AF' ?>; padding: 0 10px; font-family: inherit;" title="Click to toggle status to <?= $cStatus === 'active' ? 'Inactive' : 'Active' ?>">
                                        <?= htmlspecialchars(ucfirst($cStatus), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                </form>
                            </td>
                            <td style="text-align: right;">
                                <div class="btn-group btn-group-sm">
                                    <a href="/coaches/<?= (int)$coach['coach_profile_id'] ?>" class="btn btn-outline-secondary" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="/coaches/<?= (int)$coach['coach_profile_id'] ?>/edit" class="btn btn-outline-secondary" title="Edit Coach">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="/coaches/<?= (int)$coach['coach_profile_id'] ?>/delete" method="POST" style="display: contents;" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this coach profile? This action marks the record as deleted.');">
                                        <button type="submit" class="btn btn-outline-danger" title="Delete Coach" style="border-top-left-radius: 0; border-bottom-left-radius: 0; border-left: 0;">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total > 0): ?>
        <div class="p-3 border-top d-flex align-items-center justify-content-between bg-white">
            <div class="text-muted small">
                Showing <strong><?= count($coaches) ?></strong> of <strong><?= (int)$total ?></strong> coaches
            </div>
            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&specialization=<?= urlencode($spec) ?>&status=<?= urlencode($status) ?>">Previous</a>
                        </li>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&specialization=<?= urlencode($spec) ?>&status=<?= urlencode($status) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&specialization=<?= urlencode($spec) ?>&status=<?= urlencode($status) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
