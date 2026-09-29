<?php
$pageTitle = 'Equipment Tracking — KhelSutra';
$activePage = 'equipment';
$orgId = current_organization_id();

$eqService = new \App\Services\Equipment\EquipmentService();
$page = (int)($_GET['page'] ?? 1);
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$itemId = !empty($_GET['inventory_item_id']) ? (int)$_GET['inventory_item_id'] : null;

$result = $eqService->listEquipment($orgId, $page, 15, $search ?: null, $status ?: null, $condition ?: null, $itemId);
$equipmentList = $result['data'] ?? [];
$total = $result['total'] ?? 0;
$totalPages = $result['total_pages'] ?? 1;

$db = \App\Services\BaseService::getDatabaseConnection();

// KPI Stats
$statStmt = $db->prepare("
    SELECT 
        COUNT(*) as total_units,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_units,
        SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_units,
        SUM(CASE WHEN status IN ('maintenance', 'lost', 'disposed') THEN 1 ELSE 0 END) as issue_units
    FROM equipment
    WHERE organization_id = :org_id AND deleted_at IS NULL
");
$statStmt->execute([':org_id' => $orgId]);
$stats = $statStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_units' => 0, 'available_units' => 0, 'assigned_units' => 0, 'issue_units' => 0];

// Fetch assignees for assignment modals
$athletes = $db->query("SELECT id, CONCAT(first_name, ' ', last_name, ' (', athlete_code, ')') as label FROM athletes WHERE organization_id = {$orgId} AND deleted_at IS NULL ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$coaches = $db->query("SELECT cp.id, CONCAT(e.first_name, ' ', e.last_name, ' (', cp.coach_code, ')') as label FROM coach_profiles cp JOIN employees e ON cp.employee_id = e.id WHERE cp.organization_id = {$orgId} AND cp.deleted_at IS NULL ORDER BY e.first_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$employees = $db->query("SELECT id, CONCAT(first_name, ' ', last_name, ' (', employee_code, ')') as label FROM employees WHERE organization_id = {$orgId} AND deleted_at IS NULL ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$teams = $db->query("SELECT id, CONCAT(name, ' (', team_code, ')') as label FROM teams WHERE organization_id = {$orgId} AND deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$venues = $db->query("SELECT id, CONCAT(name, ' (', venue_code, ')') as label FROM venues WHERE organization_id = {$orgId} AND deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

ob_start();
?>

<div class="ks-content">
    <?php if (!empty($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert" style="border-radius: var(--ks-radius-button); font-size: 13px;">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert" style="border-radius: var(--ks-radius-button); font-size: 13px;">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header Standard -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/inventory" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Inventory</a>
                <span class="text-muted small">/</span>
                <span class="text-dark small fw-semibold">Equipment</span>
            </div>
            <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">Tracked Equipment</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="/inventory" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 16px;">
                <i class="bi bi-boxes"></i> Stock Inventory
            </a>
            <a href="/equipment/create" class="btn btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 18px;">
                <i class="bi bi-plus-lg"></i> Add Equipment Unit
            </a>
        </div>
    </div>

    <!-- Quick Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Total Assets</span>
                    <span class="badge bg-light text-dark border"><i class="bi bi-tag-fill text-primary"></i></span>
                </div>
                <div class="h3 fw-bold mb-0" style="color: var(--ks-navy);"><?= number_format($stats['total_units'] ?? 0) ?></div>
                <span class="text-muted" style="font-size: 12px;">Tracked asset units</span>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Available</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check2-circle"></i></span>
                </div>
                <div class="h3 fw-bold mb-0 text-success"><?= number_format($stats['available_units'] ?? 0) ?></div>
                <span class="text-muted" style="font-size: 12px;">Ready for assignment</span>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">In Use / Assigned</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-person-badge"></i></span>
                </div>
                <div class="h3 fw-bold mb-0 text-primary"><?= number_format($stats['assigned_units'] ?? 0) ?></div>
                <span class="text-muted" style="font-size: 12px;">Currently deployed</span>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Issues / Maintenance</span>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-wrench-adjustable"></i></span>
                </div>
                <div class="h3 fw-bold mb-0 text-warning"><?= number_format($stats['issue_units'] ?? 0) ?></div>
                <span class="text-muted" style="font-size: 12px;">Damaged, lost, maintenance</span>
            </div>
        </div>
    </div>

    <!-- Search & Filters Toolbar -->
    <div class="card p-3 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
        <form method="GET" action="/equipment" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button) 0 0 var(--ks-radius-button);">
                        <i class="bi bi-search text-muted" style="font-size: 13px;"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search equipment name, asset code, serial, model..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" style="border-color: var(--ks-border); border-radius: 0 var(--ks-radius-button) var(--ks-radius-button) 0; font-size: 13px;">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button); font-size: 13px;">
                    <option value="">All Deployment Statuses</option>
                    <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option>
                    <option value="assigned" <?= $status === 'assigned' ? 'selected' : '' ?>>Assigned (In Use)</option>
                    <option value="maintenance" <?= $status === 'maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                    <option value="lost" <?= $status === 'lost' ? 'selected' : '' ?>>Lost / Missing</option>
                    <option value="disposed" <?= $status === 'disposed' ? 'selected' : '' ?>>Disposed</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="condition" class="form-select" style="border-color: var(--ks-border); border-radius: var(--ks-radius-button); font-size: 13px;">
                    <option value="">All Conditions</option>
                    <option value="new" <?= $condition === 'new' ? 'selected' : '' ?>>New</option>
                    <option value="good" <?= $condition === 'good' ? 'selected' : '' ?>>Good</option>
                    <option value="damaged" <?= $condition === 'damaged' ? 'selected' : '' ?>>Damaged</option>
                    <option value="under_maintenance" <?= $condition === 'under_maintenance' ? 'selected' : '' ?>>Maintenance</option>
                    <option value="lost" <?= $condition === 'lost' ? 'selected' : '' ?>>Lost</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 500; font-size: 13px;">
                    Filter
                </button>
                <?php if ($search || $status || $condition || $itemId): ?>
                    <a href="/equipment" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;" title="Reset filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Equipment Table -->
    <div class="card" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff; overflow: hidden;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                <thead style="background: var(--ks-page-bg); border-bottom: 1px solid var(--ks-border);">
                    <tr>
                        <th class="py-3 px-3 text-muted fw-semibold" style="width: 250px;">Asset / Equipment</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Serial / Model</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Condition</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Location</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Deployment Status</th>
                        <th class="py-3 px-3 text-muted fw-semibold">Current Assignee</th>
                        <th class="py-3 px-3 text-muted fw-semibold text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($equipmentList)): ?>
                        <?php foreach ($equipmentList as $eq): ?>
                            <tr>
                                <td class="py-3 px-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-light p-2 rounded text-primary border d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                            <i class="bi bi-tag-fill"></i>
                                        </div>
                                        <div>
                                            <a href="/equipment/<?= (int)$eq['id'] ?>" class="fw-semibold text-dark text-decoration-none">
                                                <?= htmlspecialchars($eq['equipment_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <div class="text-muted" style="font-size: 11px;">
                                                Code: <span class="fw-medium text-dark"><?= htmlspecialchars($eq['asset_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if (!empty($eq['linked_item_name'])): ?>
                                                    &bull; <span class="text-primary"><?= htmlspecialchars($eq['linked_item_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-muted">
                                    <?php if (!empty($eq['serial_number'])): ?>
                                        <div>SN: <span class="text-dark fw-medium"><?= htmlspecialchars($eq['serial_number'], ENT_QUOTES, 'UTF-8') ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($eq['model_number'])): ?>
                                        <div style="font-size: 11px;">Model: <?= htmlspecialchars($eq['model_number'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (empty($eq['serial_number']) && empty($eq['model_number'])): ?>
                                        <span class="text-muted fst-italic">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?php
                                    $cBadge = match($eq['condition_status']) {
                                        'new' => 'bg-success-subtle text-success border border-success-subtle',
                                        'good' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                        'damaged' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                        'under_maintenance' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                        'lost' => 'bg-dark text-white',
                                        default => 'bg-light text-secondary border'
                                    };
                                    ?>
                                    <span class="badge <?= $cBadge ?> fw-medium" style="font-size: 11px;">
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $eq['condition_status'])), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-muted">
                                    <i class="bi bi-geo-alt me-1 text-secondary"></i>
                                    <?= htmlspecialchars($eq['current_location'] ?: 'Main Storage', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?php
                                    $sBadge = match($eq['status']) {
                                        'available' => 'bg-success-subtle text-success border border-success-subtle',
                                        'assigned' => 'bg-primary text-white',
                                        'maintenance' => 'bg-warning text-dark',
                                        'lost' => 'bg-danger text-white',
                                        'disposed' => 'bg-secondary text-white',
                                        default => 'bg-light text-dark border'
                                    };
                                    ?>
                                    <span class="badge <?= $sBadge ?> fw-semibold" style="font-size: 11px;">
                                        <?= htmlspecialchars(ucfirst($eq['status']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3">
                                    <?php if ($eq['status'] === 'assigned' && !empty($eq['current_assignee_name'])): ?>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="badge bg-secondary-subtle text-secondary border" style="font-size: 10px; text-transform: uppercase;">
                                                <?= htmlspecialchars($eq['current_assignee_type'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <span class="fw-medium text-dark small"><?= htmlspecialchars($eq['current_assignee_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="text-muted" style="font-size: 11px;">Since <?= htmlspecialchars($eq['current_assigned_date'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-end">
                                    <div class="d-inline-flex gap-1">
                                        <?php if ($eq['status'] === 'available'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" style="font-size: 12px;" data-bs-toggle="modal" data-bs-target="#assignModal<?= (int)$eq['id'] ?>" title="Assign Equipment">
                                                <i class="bi bi-person-plus"></i> Assign
                                            </button>
                                        <?php elseif ($eq['status'] === 'assigned'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-success" style="font-size: 12px;" data-bs-toggle="modal" data-bs-target="#returnModal<?= (int)$eq['id'] ?>" title="Return Equipment">
                                                <i class="bi bi-arrow-return-left"></i> Return
                                            </button>
                                        <?php endif; ?>

                                        <a href="/equipment/<?= (int)$eq['id'] ?>" class="btn btn-sm btn-light border" style="font-size: 12px;" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="/equipment/<?= (int)$eq['id'] ?>/edit" class="btn btn-sm btn-light border" style="font-size: 12px;" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($eq['status'] !== 'assigned'): ?>
                                            <form method="POST" action="/equipment/<?= (int)$eq['id'] ?>/delete" onsubmit="return confirm('Are you sure you want to remove this equipment unit?');" style="display:inline;">
                                                <button type="submit" class="btn btn-sm btn-light border text-danger" style="font-size: 12px;" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <!-- ASSIGN MODAL FOR THIS ITEM -->
                            <?php if ($eq['status'] === 'available'): ?>
                                <div class="modal fade" id="assignModal<?= (int)$eq['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content text-start" style="border-radius: var(--ks-radius-card);">
                                            <form method="POST" action="/equipment/<?= (int)$eq['id'] ?>/assign">
                                                <div class="modal-header border-bottom">
                                                    <h5 class="modal-title fw-bold" style="color: var(--ks-navy); font-size: 15px;">
                                                        <i class="bi bi-person-plus text-primary me-2"></i>Assign Equipment: <?= htmlspecialchars($eq['equipment_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Assignee Type <span class="text-danger">*</span></label>
                                                        <select name="assignee_type" class="form-select assignee-type-select" data-id="<?= (int)$eq['id'] ?>" required style="font-size: 13px;">
                                                            <option value="athlete">Athlete</option>
                                                            <option value="coach">Coach</option>
                                                            <option value="employee">Employee / Staff</option>
                                                            <option value="team">Team</option>
                                                            <option value="venue">Venue / Facility</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3 assignee-select-group" id="grp-athlete-<?= (int)$eq['id'] ?>">
                                                        <label class="form-label small fw-semibold">Select Athlete <span class="text-danger">*</span></label>
                                                        <select name="athlete_id" class="form-select" style="font-size: 13px;">
                                                            <option value="">-- Choose Athlete --</option>
                                                            <?php foreach ($athletes as $a): ?>
                                                                <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3 assignee-select-group d-none" id="grp-coach-<?= (int)$eq['id'] ?>">
                                                        <label class="form-label small fw-semibold">Select Coach <span class="text-danger">*</span></label>
                                                        <select name="coach_id" class="form-select" style="font-size: 13px;">
                                                            <option value="">-- Choose Coach --</option>
                                                            <?php foreach ($coaches as $c): ?>
                                                                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3 assignee-select-group d-none" id="grp-employee-<?= (int)$eq['id'] ?>">
                                                        <label class="form-label small fw-semibold">Select Employee <span class="text-danger">*</span></label>
                                                        <select name="employee_id" class="form-select" style="font-size: 13px;">
                                                            <option value="">-- Choose Staff Member --</option>
                                                            <?php foreach ($employees as $e): ?>
                                                                <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3 assignee-select-group d-none" id="grp-team-<?= (int)$eq['id'] ?>">
                                                        <label class="form-label small fw-semibold">Select Team <span class="text-danger">*</span></label>
                                                        <select name="team_id" class="form-select" style="font-size: 13px;">
                                                            <option value="">-- Choose Team --</option>
                                                            <?php foreach ($teams as $t): ?>
                                                                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3 assignee-select-group d-none" id="grp-venue-<?= (int)$eq['id'] ?>">
                                                        <label class="form-label small fw-semibold">Select Venue <span class="text-danger">*</span></label>
                                                        <select name="venue_id" class="form-select" style="font-size: 13px;">
                                                            <option value="">-- Choose Venue --</option>
                                                            <?php foreach ($venues as $v): ?>
                                                                <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="row g-2 mb-3">
                                                        <div class="col-6">
                                                            <label class="form-label small fw-semibold">Assigned Date</label>
                                                            <input type="date" name="assigned_date" class="form-control" value="<?= date('Y-m-d') ?>" style="font-size: 13px;">
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label small fw-semibold">Expected Return</label>
                                                            <input type="date" name="expected_return_date" class="form-control" style="font-size: 13px;">
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Condition on Issue</label>
                                                        <input type="text" name="condition_on_issue" class="form-control" value="<?= htmlspecialchars($eq['condition_status'], ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Good, minor scratches" style="font-size: 13px;">
                                                    </div>

                                                    <div class="mb-2">
                                                        <label class="form-label small fw-semibold">Assignment Notes</label>
                                                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional purpose or instructions..." style="font-size: 13px;"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-top">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="font-size: 13px;">Cancel</button>
                                                    <button type="submit" class="btn btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); font-size: 13px;">
                                                        Confirm Assignment
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- RETURN MODAL FOR THIS ITEM -->
                            <?php if ($eq['status'] === 'assigned'): ?>
                                <div class="modal fade" id="returnModal<?= (int)$eq['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content text-start" style="border-radius: var(--ks-radius-card);">
                                            <form method="POST" action="/equipment/<?= (int)$eq['id'] ?>/return">
                                                <div class="modal-header border-bottom">
                                                    <h5 class="modal-title fw-bold" style="color: var(--ks-navy); font-size: 15px;">
                                                        <i class="bi bi-arrow-return-left text-success me-2"></i>Return Equipment: <?= htmlspecialchars($eq['equipment_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="p-2 mb-3 bg-light rounded border text-muted small">
                                                        Currently assigned to: <span class="fw-semibold text-dark"><?= htmlspecialchars($eq['current_assignee_name'] ?? 'Assignee', ENT_QUOTES, 'UTF-8') ?></span> (<?= htmlspecialchars(ucfirst($eq['current_assignee_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)
                                                    </div>

                                                    <div class="row g-2 mb-3">
                                                        <div class="col-6">
                                                            <label class="form-label small fw-semibold">Returned Date</label>
                                                            <input type="date" name="returned_date" class="form-control" value="<?= date('Y-m-d') ?>" style="font-size: 13px;" required>
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label small fw-semibold">Condition on Return</label>
                                                            <select name="condition_on_return" class="form-select" style="font-size: 13px;">
                                                                <option value="good" selected>Good</option>
                                                                <option value="new">Like New</option>
                                                                <option value="damaged">Damaged / Broken</option>
                                                                <option value="lost">Lost / Missing</option>
                                                                <option value="under_maintenance">Needs Maintenance</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Return Status</label>
                                                        <select name="status" class="form-select" style="font-size: 13px;">
                                                            <option value="returned">Returned — Available for Use</option>
                                                            <option value="damaged">Damaged — Needs Maintenance</option>
                                                            <option value="lost">Lost — Write-off / Missing</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Storage Location</label>
                                                        <input type="text" name="return_location" class="form-control" value="Main Storage" placeholder="e.g. Main Storage Rack B" style="font-size: 13px;">
                                                    </div>

                                                    <div class="mb-2">
                                                        <label class="form-label small fw-semibold">Inspection / Return Notes</label>
                                                        <textarea name="notes" class="form-control" rows="2" placeholder="Notes on wear, damage, or missing accessories..." style="font-size: 13px;"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-top">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="font-size: 13px;">Cancel</button>
                                                    <button type="submit" class="btn btn-success" style="font-size: 13px;">
                                                        Process Return
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-tag d-block fs-1 mb-2 opacity-50"></i>
                                <p class="mb-2 fw-medium">No tracked equipment assets found.</p>
                                <p class="text-muted small mb-3">Add serialized sports gear and asset units to begin tracking assignments.</p>
                                <a href="/equipment/create" class="btn btn-sm btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); font-size: 12px;">
                                    <i class="bi bi-plus-lg me-1"></i> Add First Asset
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="d-flex align-items-center justify-content-between p-3 border-top" style="font-size: 13px;">
                <span class="text-muted">Showing page <?= $page ?> of <?= $totalPages ?> (<?= $total ?> items)</span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&condition=<?= urlencode($condition) ?>">&laquo;</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&condition=<?= urlencode($condition) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&condition=<?= urlencode($condition) ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dynamic switch of assignee group based on assignee-type-select
    document.querySelectorAll('.assignee-type-select').forEach(function(select) {
        select.addEventListener('change', function() {
            var eqId = this.dataset.id;
            var val = this.value;
            var groups = ['athlete', 'coach', 'employee', 'team', 'venue'];
            groups.forEach(function(g) {
                var el = document.getElementById('grp-' + g + '-' + eqId);
                if (el) {
                    if (g === val) {
                        el.classList.remove('d-none');
                    } else {
                        el.classList.add('d-none');
                    }
                }
            });
        });
    });
});
</script>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
