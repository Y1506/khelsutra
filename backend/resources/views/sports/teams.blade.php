<?php
$pageTitle = 'Teams — KhelSutra';
$activePage = 'teams';
$orgId = current_organization_id();

$teamService = new \App\Services\Team\TeamService();
$page = (int)($_GET['page'] ?? 1);
$search = trim($_GET['search'] ?? '');
$sportId = !empty($_GET['sport_id']) ? (int)$_GET['sport_id'] : null;
$status = trim($_GET['status'] ?? '');

$result = $teamService->listTeams($orgId, $page, 15, $search ?: null, $sportId, $status ?: null);
$teams = $result['data'] ?? [];
$total = $result['total'] ?? 0;
$totalPages = $result['total_pages'] ?? 1;

// Fetch sports for filter
$db = \App\Services\BaseService::getDatabaseConnection();
$sportsStmt = $db->query("SELECT id, name FROM sports ORDER BY name ASC");
$sports = $sportsStmt ? $sportsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

ob_start();
?>

<div class="ks-page-header">
    <div>
        <h1 class="ks-page-title">Teams</h1>
    </div>
    <div class="ks-header-actions">
        <a href="/teams/create" class="ks-btn ks-btn-primary" id="btnCreateTeam">
            <i class="bi bi-plus-lg"></i> Create Team
        </a>
    </div>
</div>

<!-- Search & Filters Toolbar -->
<div class="ks-filter-bar mb-4">
    <form method="GET" action="/teams" class="ks-filter-grid">
        <div class="position-relative">
            <i class="bi bi-search position-absolute" style="left: 12px; top: 12px; color: var(--ks-text-muted); font-size: 13px;"></i>
            <input type="text" name="search" class="ks-form-control" placeholder="Search team name, code, age group..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" style="padding-left: 34px; height: 38px; font-size: 13px;">
        </div>
        <div>
            <select name="sport_id" class="ks-form-select" style="height: 38px; font-size: 13px;">
                <option value="">All Sports</option>
                <?php foreach ($sports as $sp): ?>
                    <option value="<?= (int)$sp['id'] ?>" <?= $sportId == $sp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sp['name'], ENT_QUOTES, 'UTF-8') ?></option>
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
            <?php if ($search || $sportId || $status): ?>
                <a href="/teams" class="ks-btn ks-btn-secondary" style="height: 38px; font-size: 13px;" title="Reset filters">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Teams Grid / Table View -->
<div class="ks-table-card">
    <div class="table-responsive">
        <table class="ks-table ks-table-teams">
            <thead>
                <tr>
                    <th>Team Name</th>
                    <th>Sport</th>
                    <th>Age Group</th>
                    <th>Head Coach</th>
                    <th>Roster Size</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
                <tbody>
                    <?php if (!empty($teams)): ?>
                        <?php foreach ($teams as $team): ?>
                            <tr>
                                <td class="py-3 px-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 36px; height: 36px; border-radius: 8px; background: #E0F2FE; color: #0284C7; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">
                                            <?= htmlspecialchars(strtoupper(substr($team['name'] ?? 'T', 0, 2)), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div>
                                            <a href="/teams/<?= (int)$team['id'] ?>" class="fw-semibold text-decoration-none text-dark d-block">
                                                <?= htmlspecialchars($team['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted small" style="font-family: monospace; font-size: 11px;"><?= htmlspecialchars($team['team_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 11px;">
                                        <?= htmlspecialchars($team['sport_name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 fw-medium text-dark">
                                    <?= htmlspecialchars($team['age_group'] ?: 'Open', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?php if (!empty($team['head_coach_name'])): ?>
                                        <a href="/coaches/<?= (int)($team['head_coach_id'] ?? 0) ?>" class="text-decoration-none" style="color: var(--ks-blue); font-weight: 500;">
                                            <?= htmlspecialchars($team['head_coach_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                        <?= (int)($team['athlete_count'] ?? 0) ?> Athletes
                                    </span>
                                </td>
                                <td class="py-3 px-3">
                                    <?php
                                        $tStatus = $team['status'] ?? 'active';
                                    ?>
                                    <form action="/teams/<?= (int)$team['id'] ?>/status" method="POST" style="display: contents;" class="d-inline m-0 p-0">
                                        <input type="hidden" name="status" value="<?= $tStatus === 'active' ? 'inactive' : 'active' ?>">
                                        <button type="submit" class="badge <?= $tStatus === 'active' ? 'badge-success' : 'badge-secondary' ?>" style="cursor: pointer; border: none; border-radius: 12px; font-size: 11px; padding: 4px 10px; text-transform: capitalize; font-family: inherit;" title="Click to toggle status to <?= $tStatus === 'active' ? 'Inactive' : 'Active' ?>">
                                            <?= htmlspecialchars($tStatus, ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="py-3 px-3 text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/teams/<?= (int)$team['id'] ?>" class="btn btn-outline-secondary" style="border-radius: 6px 0 0 6px;" title="View Squad Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="/teams/<?= (int)$team['id'] ?>/edit" class="btn btn-outline-secondary" style="border-radius: 0;" title="Edit Team">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="/teams/<?= (int)$team['id'] ?>/delete" method="POST" style="display: contents;" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this team? This action marks the team as deleted.');">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" style="border-radius: 0 6px 6px 0; border-left: 0;" title="Delete Team">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted mb-2"><i class="bi bi-shield fs-2"></i></div>
                                <h6 class="fw-bold" style="color: var(--ks-navy);">No teams found</h6>
                                <p class="text-muted small mb-3">No team squads match your current filters.</p>
                                <a href="/teams/create" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 500;">
                                    <i class="bi bi-plus-lg me-1"></i> Create Team
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
                    Showing <strong><?= count($teams) ?></strong> of <strong><?= (int)$total ?></strong> teams
                </div>
                <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&sport_id=<?= $sportId ?>&status=<?= urlencode($status) ?>">Previous</a>
                            </li>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&sport_id=<?= $sportId ?>&status=<?= urlencode($status) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&sport_id=<?= $sportId ?>&status=<?= urlencode($status) ?>">Next</a>
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
