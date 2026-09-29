<?php
$pageTitle = 'Tournaments — KhelSutra';
$activePage = 'tournaments';
$orgId = current_organization_id();

$tournService = new \App\Services\Tournament\TournamentService();
$page = (int)($_GET['page'] ?? 1);
$search = trim($_GET['search'] ?? '');
$sportId = !empty($_GET['sport_id']) ? (int)$_GET['sport_id'] : null;
$status = trim($_GET['status'] ?? '');

$result = $tournService->listTournaments($orgId, $page, 15, $search ?: null, $status ?: null, $sportId);
$tournaments = $result['data'] ?? [];
$total = $result['total'] ?? 0;
$totalPages = $result['total_pages'] ?? 1;

$db = \App\Services\BaseService::getDatabaseConnection();
$sportsStmt = $db->query("SELECT id, name FROM sports ORDER BY name ASC");
$sports = $sportsStmt ? $sportsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

ob_start();
?>

<div class="ks-content">
    <!-- Clean Page Header Standard -->
    <div class="ks-page-header mb-4">
        <div>
            <h1 class="ks-page-title">Tournaments</h1>
        </div>
        <div class="ks-header-actions">
            <a href="/tournaments/create" class="ks-btn ks-btn-primary">
                <i class="bi bi-plus-lg"></i> Create Tournament
            </a>
        </div>
    </div>

    <!-- Search & Filters Toolbar -->
    <div class="card p-3 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
        <form method="GET" action="/tournaments" class="ks-filter-grid">
            <div>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button) 0 0 var(--ks-radius-button);">
                        <i class="bi bi-search text-muted" style="font-size: 13px;"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search tournament name, code, organizer..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" style="border-color: var(--ks-border); border-radius: 0 var(--ks-radius-button) var(--ks-radius-button) 0; font-size: 13px;">
                </div>
            </div>
            <div>
                <select name="sport_id" class="form-select" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button); font-size: 13px;">
                    <option value="">All Sports</option>
                    <?php foreach ($sports as $sp): ?>
                        <option value="<?= (int)$sp['id'] ?>" <?= $sportId == $sp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sp['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <select name="status" class="form-select" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button); font-size: 13px;">
                    <option value="">All Statuses</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="registration_open" <?= $status === 'registration_open' ? 'selected' : '' ?>>Registration Open</option>
                    <option value="ongoing" <?= $status === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn ks-btn ks-btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 500; font-size: 13px;">
                    Filter
                </button>
                <?php if ($search || $sportId || $status): ?>
                    <a href="/tournaments" class="ks-btn ks-btn-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;" title="Reset filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tournaments Table -->
    <div class="card" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff; overflow: hidden;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                    <tr>
                        <th class="py-3 px-3 text-muted fw-semibold" style="width: 270px;">Tournament</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Sport & Level</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Format</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Dates</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Teams</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Status</th>
                        <th class="py-3 px-3 text-muted fw-semibold text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tournaments)): ?>
                        <?php foreach ($tournaments as $tourn): ?>
                            <tr>
                                <td class="py-3 px-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 38px; height: 38px; border-radius: 8px; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                                            <i class="bi bi-trophy-fill"></i>
                                        </div>
                                        <div>
                                            <a href="/tournaments/<?= (int)$tourn['id'] ?>" class="fw-semibold text-decoration-none text-dark d-block">
                                                <?= htmlspecialchars($tourn['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted small" style="font-family: monospace; font-size: 11px;"><?= htmlspecialchars($tourn['tournament_reference'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="fw-medium text-dark"><?= htmlspecialchars($tourn['sport_name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></div>
                                    <span class="badge bg-light text-secondary border px-1" style="font-size: 10px;"><?= htmlspecialchars($tourn['level_name'] ?? 'State', ENT_QUOTES, 'UTF-8') ?> Level</span>
                                </td>
                                <td class="py-3 px-3 text-dark">
                                    <?= htmlspecialchars($tourn['format_name'] ?? 'Knockout', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 px-3 text-muted small">
                                    <div><?= !empty($tourn['start_date']) ? date('M d, Y', strtotime($tourn['start_date'])) : '—' ?></div>
                                    <div style="font-size: 11px;">to <?= !empty($tourn['end_date']) ? date('M d, Y', strtotime($tourn['end_date'])) : '—' ?></div>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                        <?= (int)($tourn['enrolled_teams_count'] ?? 0) ?> Squads
                                    </span>
                                </td>
                                <td class="py-3 px-3">
                                    <?php
                                        $badge = match($tourn['status'] ?? 'draft') {
                                            'ongoing' => 'badge-success',
                                            'registration_open' => 'badge-primary',
                                            'completed' => 'badge-secondary',
                                            'cancelled' => 'badge-danger',
                                            default => 'badge-warning'
                                        };
                                    ?>
                                    <span class="badge <?= $badge ?>" style="border-radius: 12px; font-size: 11px; padding: 4px 10px; text-transform: capitalize;">
                                        <?= htmlspecialchars(str_replace('_', ' ', $tourn['status'] ?? 'draft'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/tournaments/<?= (int)$tourn['id'] ?>" class="ks-btn ks-btn-secondary" style="border-radius: 6px 0 0 6px;" title="View Tournament & Fixtures">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="/tournaments/<?= (int)$tourn['id'] ?>/edit" class="ks-btn ks-btn-secondary" style="border-radius: 0;" title="Edit Tournament">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="/tournaments/<?= (int)$tourn['id'] ?>/delete" method="POST" style="display: contents;" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this tournament? This action marks the tournament as deleted.');">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" style="border-radius: 0 6px 6px 0; border-left: 0;" title="Delete Tournament">
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
                                <div class="text-muted mb-2"><i class="bi bi-trophy fs-2"></i></div>
                                <h6 class="fw-bold" style="color: var(--ks-navy);">No tournaments found</h6>
                                <p class="text-muted small mb-3">No tournament championships match the current search or filters.</p>
                                <a href="/tournaments/create" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 500;">
                                    <i class="bi bi-plus-lg me-1"></i> Create Tournament
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
                    Showing <strong><?= count($tournaments) ?></strong> of <strong><?= (int)$total ?></strong> tournaments
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
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
