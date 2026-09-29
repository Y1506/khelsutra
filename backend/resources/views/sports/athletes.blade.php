<?php
$activePage = 'athletes';
$title = 'Athletes — KhelSutra';

$orgId = current_organization_id();
$search = trim($_GET['search'] ?? '');
$sportId = !empty($_GET['sport_id']) ? (int)$_GET['sport_id'] : null;
$status = !empty($_GET['status']) ? trim($_GET['status']) : null;
$page = max(1, (int)($_GET['page'] ?? 1));

$athleteService = new \App\Services\Athlete\AthleteService();
$result = $athleteService->listAthletes($orgId, $page, 15, $search, $sportId, $status);
$athletes = $result['data'] ?? [];
$totalAthletes = $result['total'] ?? 0;
$totalPages = $result['total_pages'] ?? 1;

// Fetch sports for filter dropdown
$pdo = \App\Services\BaseService::getDatabaseConnection();
$sportsList = $pdo->query("SELECT id, name FROM sports WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<!-- Page Header (Clean Page Title + Action, No Subtitle) -->
<div class="ks-page-header">
    <div>
        <h1 class="ks-page-title">Athletes</h1>
    </div>
    <div class="ks-header-actions">
        <a href="/reports" class="ks-btn ks-btn-secondary">
            <i class="bi bi-download"></i>
            <span>Export Report</span>
        </a>
        <a href="/athletes/create" class="ks-btn ks-btn-primary" id="btnAddAthlete">
            <i class="bi bi-plus-lg"></i>
            <span>Add Athlete</span>
        </a>
    </div>
</div>

<!-- Table Card & Live Filter Bar -->
<div class="ks-table-card">
    <div class="ks-table-header flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-person-lines-fill" style="color: var(--ks-primary); font-size: 18px;"></i>
            <span class="ks-card-title mb-0">Registered Athletes (<?= (int)$totalAthletes ?>)</span>
        </div>
        
        <form action="/athletes" method="GET" class="d-flex align-items-center flex-wrap gap-2 m-0">
            <div class="position-relative" style="width: 240px;">
                <i class="bi bi-search position-absolute" style="left: 12px; top: 12px; color: var(--ks-text-muted); font-size: 13px;"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" class="ks-form-control" style="padding-left: 34px; height: 38px; font-size: 13px;" placeholder="Search by name, ID...">
            </div>
            
            <select name="sport_id" class="ks-form-select" style="width: 140px; height: 38px; font-size: 13px;" onchange="this.form.submit()">
                <option value="">All Sports</option>
                <?php foreach ($sportsList as $sp): ?>
                    <option value="<?= (int)$sp['id'] ?>" <?= $sportId == $sp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sp['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            
            <select name="status" class="ks-form-select" style="width: 130px; height: 38px; font-size: 13px;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="injured" <?= $status === 'injured' ? 'selected' : '' ?>>Injured</option>
                <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>

            <?php if (!empty($search) || !empty($sportId) || !empty($status)): ?>
                <a href="/athletes" class="btn btn-sm btn-outline-secondary" style="height: 38px; display: flex; align-items: center;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="ks-table ks-table-athletes">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Athlete Name</th>
                    <th>Reg ID</th>
                    <th>Sport</th>
                    <th>Assigned Team</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($athletes)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-people d-block fs-1 mb-2" style="color: var(--ks-text-muted);"></i>
                            <div class="fw-semibold text-navy fs-5">No athletes found</div>
                            <div class="small mt-1 mb-3">No registered athletes matched the filter criteria in this academy.</div>
                            <a href="/athletes/create" class="ks-btn ks-btn-primary d-inline-flex">
                                <i class="bi bi-plus-lg"></i>
                                <span>Add Athlete</span>
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $idx = ($page - 1) * 15 + 1; foreach ($athletes as $ath): ?>
                        <tr>
                            <td><?= $idx++ ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="ks-user-avatar" style="width: 36px; height: 36px; font-size: 12px; background: #E8F2FF; color: var(--ks-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                        <?= strtoupper(substr($ath['first_name'] ?? 'A', 0, 1) . substr($ath['last_name'] ?? 'A', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-navy">
                                            <a href="/athletes/<?= (int)$ath['id'] ?>" class="text-navy text-decoration-none hover-primary">
                                                <?= htmlspecialchars(($ath['first_name'] ?? '') . ' ' . ($ath['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </div>
                                        <div class="small text-muted"><?= htmlspecialchars($ath['gender'] ?? 'Not specified', ENT_QUOTES, 'UTF-8') ?> • DOB: <?= !empty($ath['date_of_birth']) ? date('d M Y', strtotime($ath['date_of_birth'])) : '—' ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="fw-medium text-navy"><?= htmlspecialchars($ath['athlete_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td>
                                <span class="ks-badge ks-badge-blue"><?= htmlspecialchars($ath['sport_name'] ?? 'Football', ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td>
                                <?= htmlspecialchars($ath['team_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($ath['phone'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($ath['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <?php $aStatus = $ath['status'] ?? 'active'; ?>
                                <form action="/athletes/<?= (int)$ath['id'] ?>/status" method="POST" class="d-inline m-0 p-0">
                                    <input type="hidden" name="status" value="<?= $aStatus === 'active' ? 'inactive' : 'active' ?>">
                                    <button type="submit" class="ks-badge ks-badge-<?= $aStatus === 'active' ? 'confirmed' : 'pending' ?> text-capitalize" style="cursor: pointer; border: 1px solid <?= $aStatus === 'active' ? '#BBF7D0' : '#FED7AA' ?>; background-color: <?= $aStatus === 'active' ? '#DCFCE7' : '#FFEDD5' ?>; color: <?= $aStatus === 'active' ? '#166534' : '#9A3412' ?>; padding: 0 10px; font-family: inherit;" title="Click to toggle status to <?= $aStatus === 'active' ? 'Inactive' : 'Active' ?>">
                                        <?= htmlspecialchars($aStatus, ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                </form>
                            </td>
                            <td style="text-align: right;">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <a href="/athletes/<?= (int)$ath['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View Profile" style="padding: 4px 8px; font-size: 12px;">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <a href="/athletes/<?= (int)$ath['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit Athlete" style="padding: 4px 8px; font-size: 12px;">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <form action="/athletes/<?= (int)$ath['id'] ?>/delete" method="POST" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this athlete? This action marks the athlete as deleted.');">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Athlete" style="padding: 4px 8px; font-size: 12px;">
                                            <i class="bi bi-trash"></i> Delete
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
    <?php if ($totalPages > 1): ?>
        <div class="p-3 border-top d-flex align-items-center justify-content-between">
            <div class="small text-muted">Showing page <?= $page ?> of <?= $totalPages ?> (Total: <?= (int)$totalAthletes ?>)</div>
            <div class="btn-group btn-group-sm">
                <?php if ($page > 1): ?>
                    <a href="/athletes?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&sport_id=<?= $sportId ?>&status=<?= urlencode((string)$status) ?>" class="btn btn-outline-secondary">Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="/athletes?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&sport_id=<?= $sportId ?>&status=<?= urlencode((string)$status) ?>" class="btn btn-outline-secondary">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
