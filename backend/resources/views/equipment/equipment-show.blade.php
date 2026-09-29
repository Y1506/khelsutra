<?php
$pageTitle = 'Equipment Details — KhelSutra';
$activePage = 'equipment';
$orgId = current_organization_id();

$id = (int)($id ?? ($data['id'] ?? ($_GET['id'] ?? 0)));
$eqService = new \App\Services\Equipment\EquipmentService();
$equipment = $eqService->getEquipment($orgId, $id);

$db = \App\Services\BaseService::getDatabaseConnection();
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

    <?php if (!$equipment): ?>
        <div class="card p-5 text-center" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <i class="bi bi-exclamation-circle text-danger fs-1 mb-3"></i>
            <h4 class="fw-bold mb-2">Equipment Asset Not Found</h4>
            <p class="text-muted small mb-4">The requested equipment unit does not exist or you do not have permission to view it.</p>
            <div>
                <a href="/equipment" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-size: 13px;">
                    Back to Equipment
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Page Header Standard -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <a href="/equipment" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Equipment List</a>
                    <span class="text-muted small">/</span>
                    <span class="text-dark small fw-semibold"><?= htmlspecialchars($equipment['asset_code'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <h1 class="h3 fw-bold mb-0" style="color: var(--ks-navy); letter-spacing: -0.02em;">
                        <?= htmlspecialchars($equipment['equipment_name'], ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <?php
                    $sBadge = match($equipment['status']) {
                        'available' => 'bg-success-subtle text-success border border-success-subtle',
                        'assigned' => 'bg-primary text-white',
                        'maintenance' => 'bg-warning text-dark',
                        'lost' => 'bg-danger text-white',
                        'disposed' => 'bg-secondary text-white',
                        default => 'bg-light text-dark border'
                    };
                    ?>
                    <span class="badge <?= $sBadge ?> px-3 py-2 fw-semibold" style="font-size: 12px;">
                        <?= htmlspecialchars(ucfirst($equipment['status']), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <?php if ($equipment['status'] === 'available'): ?>
                    <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 16px;" data-bs-toggle="modal" data-bs-target="#assignModal">
                        <i class="bi bi-person-plus"></i> Assign Equipment
                    </button>
                <?php elseif ($equipment['status'] === 'assigned'): ?>
                    <button type="button" class="btn btn-success d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 16px;" data-bs-toggle="modal" data-bs-target="#returnModal">
                        <i class="bi bi-arrow-return-left"></i> Return Equipment
                    </button>
                <?php endif; ?>

                <a href="/equipment/<?= (int)$equipment['id'] ?>/edit" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 16px;">
                    <i class="bi bi-pencil"></i> Edit
                </a>

                <?php if ($equipment['status'] !== 'assigned'): ?>
                    <form method="POST" action="/equipment/<?= (int)$equipment['id'] ?>/delete" onsubmit="return confirm('Are you sure you want to remove this equipment unit?');" style="display:inline;">
                        <button type="submit" class="btn btn-outline-danger d-inline-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 9px 16px;">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Current Assignment & History -->
            <div class="col-lg-8">
                <!-- Active Assignment Banner Card (if assigned) -->
                <?php if ($equipment['status'] === 'assigned' && !empty($equipment['active_assignment'])): ?>
                    <?php $act = $equipment['active_assignment']; ?>
                    <div class="card p-4 mb-4 border-primary border-opacity-25" style="border-radius: var(--ks-radius-card); background: #fdfefe;">
                        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                            <h5 class="fw-bold mb-0 text-primary" style="font-size: 15px;">
                                <i class="bi bi-person-badge me-2"></i>Currently Assigned
                            </h5>
                            <span class="badge bg-primary text-white text-uppercase" style="font-size: 11px;">
                                <?= htmlspecialchars($act['assignee_type'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                        <div class="row g-3" style="font-size: 13px;">
                            <div class="col-sm-6">
                                <div class="text-muted small">Assigned To</div>
                                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($act['assignee_name'] ?? 'Assignee', ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if (!empty($act['assignee_code'])): ?>
                                    <div class="text-muted" style="font-size: 11px;">Code: <?= htmlspecialchars($act['assignee_code'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Assigned Date</div>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($act['assigned_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if (!empty($act['expected_return_date'])): ?>
                                    <div class="text-muted" style="font-size: 11px;">Expected Return: <?= htmlspecialchars($act['expected_return_date'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Condition on Issue</div>
                                <div class="fw-medium text-dark"><?= htmlspecialchars($act['condition_on_issue'] ?? 'Good', ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Issued By</div>
                                <div class="fw-medium text-dark"><?= htmlspecialchars($act['issued_by_name'] ?? 'Staff', ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <?php if (!empty($act['notes'])): ?>
                                <div class="col-12">
                                    <div class="text-muted small">Assignment Notes</div>
                                    <div class="p-2 bg-light rounded text-dark small fst-italic"><?= nl2br(htmlspecialchars($act['notes'], ENT_QUOTES, 'UTF-8')) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Assignment History Ledger Table -->
                <div class="card p-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
                        <h5 class="fw-bold mb-0" style="color: var(--ks-navy); font-size: 15px;">
                            <i class="bi bi-clock-history me-2 text-primary"></i>Assignment History Ledger
                        </h5>
                        <span class="badge bg-light text-dark border"><?= count($equipment['assignment_history'] ?? []) ?> records</span>
                    </div>

                    <?php if (!empty($equipment['assignment_history']) && count($equipment['assignment_history']) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 12.5px;">
                                <thead style="background: var(--ks-page-bg);">
                                    <tr>
                                        <th class="py-2 px-3 text-muted">Assignee</th>
                                        <th class="py-2 px-3 text-muted">Assigned Date</th>
                                        <th class="py-2 px-3 text-muted">Returned Date</th>
                                        <th class="py-2 px-3 text-muted">Condition (Issue &rarr; Return)</th>
                                        <th class="py-2 px-3 text-muted">Status</th>
                                        <th class="py-2 px-3 text-muted">Staff</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($equipment['assignment_history'] as $h): ?>
                                        <tr>
                                            <td class="py-2 px-3">
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($h['assignee_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></div>
                                                <span class="badge bg-light text-secondary border" style="font-size: 10px; text-transform: uppercase;">
                                                    <?= htmlspecialchars($h['assignee_type'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td class="py-2 px-3 text-muted">
                                                <?= htmlspecialchars($h['assigned_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="py-2 px-3 text-muted">
                                                <?= htmlspecialchars($h['returned_date'] ?: 'Active', ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="py-2 px-3">
                                                <span class="text-dark fw-medium"><?= htmlspecialchars($h['condition_on_issue'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if (!empty($h['condition_on_return'])): ?>
                                                    &rarr; <span class="text-primary fw-medium"><?= htmlspecialchars($h['condition_on_return'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-2 px-3">
                                                <?php
                                                $ahBadge = match($h['status']) {
                                                    'assigned' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                                    'returned' => 'bg-success-subtle text-success border border-success-subtle',
                                                    'damaged' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                                    'lost' => 'bg-dark text-white',
                                                    default => 'bg-light text-secondary border'
                                                };
                                                ?>
                                                <span class="badge <?= $ahBadge ?> fw-medium" style="font-size: 10.5px;">
                                                    <?= htmlspecialchars(ucfirst($h['status']), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td class="py-2 px-3 text-muted" style="font-size: 11px;">
                                                <div>Issued: <?= htmlspecialchars($h['issued_by_name'] ?? 'Staff', ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php if (!empty($h['received_by_name'])): ?>
                                                    <div>Recv: <?= htmlspecialchars($h['received_by_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-clock-history d-block fs-2 mb-2 opacity-50"></i>
                            <p class="small mb-0">No past assignment records for this equipment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Asset Specifications -->
            <div class="col-lg-4">
                <div class="card p-4 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                    <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 10px;">
                        <i class="bi bi-info-circle me-2" style="color: var(--ks-blue);"></i> Asset Specifications
                    </h5>

                    <div class="d-flex flex-column gap-3" style="font-size: 13px;">
                        <div>
                            <span class="text-muted d-block small">Asset Code</span>
                            <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($equipment['asset_code'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <?php if (!empty($equipment['linked_item_name'])): ?>
                            <div>
                                <span class="text-muted d-block small">Linked Stock Item</span>
                                <a href="/inventory/<?= (int)$equipment['inventory_item_id'] ?>" class="text-primary fw-semibold text-decoration-none">
                                    <i class="bi bi-box me-1"></i><?= htmlspecialchars($equipment['linked_item_name'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                                <?php if (!empty($equipment['linked_category_name'])): ?>
                                    <span class="badge bg-light text-secondary border ms-1" style="font-size: 10px;"><?= htmlspecialchars($equipment['linked_category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div>
                            <span class="text-muted d-block small">Serial Number</span>
                            <span class="text-dark fw-medium"><?= htmlspecialchars($equipment['serial_number'] ?: 'Not Serialized', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div>
                            <span class="text-muted d-block small">Model / Manufacturer</span>
                            <span class="text-dark"><?= htmlspecialchars(trim(($equipment['model_number'] ?? '') . ' ' . ($equipment['manufacturer'] ?? '')) ?: 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div>
                            <span class="text-muted d-block small">Current Location</span>
                            <span class="text-dark"><i class="bi bi-geo-alt me-1 text-secondary"></i><?= htmlspecialchars($equipment['current_location'] ?: 'Main Storage', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div>
                            <span class="text-muted d-block small">Condition Status</span>
                            <span class="badge bg-light text-dark border fw-medium">
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $equipment['condition_status'])), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <?php if (!empty($equipment['purchase_cost']) || !empty($equipment['purchase_date'])): ?>
                            <div class="border-top pt-2">
                                <span class="text-muted d-block small">Purchase Details</span>
                                <div class="text-dark">
                                    <?php if (!empty($equipment['purchase_cost'])): ?>
                                        <span class="fw-semibold">₹<?= number_format((float)$equipment['purchase_cost'], 2) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($equipment['purchase_date'])): ?>
                                        <span class="text-muted" style="font-size: 12px;">on <?= htmlspecialchars($equipment['purchase_date'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($equipment['warranty_expiry_date'])): ?>
                            <div>
                                <span class="text-muted d-block small">Warranty Expiry</span>
                                <span class="text-dark"><?= htmlspecialchars($equipment['warranty_expiry_date'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ASSIGN MODAL -->
        <?php if ($equipment['status'] === 'available'): ?>
            <div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content" style="border-radius: var(--ks-radius-card);">
                        <form method="POST" action="/equipment/<?= (int)$equipment['id'] ?>/assign">
                            <div class="modal-header border-bottom">
                                <h5 class="modal-title fw-bold" style="color: var(--ks-navy); font-size: 15px;">
                                    <i class="bi bi-person-plus text-primary me-2"></i>Assign Equipment
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Assignee Type <span class="text-danger">*</span></label>
                                    <select name="assignee_type" id="modalAssigneeType" class="form-select" required style="font-size: 13px;">
                                        <option value="athlete">Athlete</option>
                                        <option value="coach">Coach</option>
                                        <option value="employee">Employee / Staff</option>
                                        <option value="team">Team</option>
                                        <option value="venue">Venue / Facility</option>
                                    </select>
                                </div>

                                <div class="mb-3 show-assignee-grp" id="show-grp-athlete">
                                    <label class="form-label small fw-semibold">Select Athlete <span class="text-danger">*</span></label>
                                    <select name="athlete_id" class="form-select" style="font-size: 13px;">
                                        <option value="">-- Choose Athlete --</option>
                                        <?php foreach ($athletes as $a): ?>
                                            <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3 show-assignee-grp d-none" id="show-grp-coach">
                                    <label class="form-label small fw-semibold">Select Coach <span class="text-danger">*</span></label>
                                    <select name="coach_id" class="form-select" style="font-size: 13px;">
                                        <option value="">-- Choose Coach --</option>
                                        <?php foreach ($coaches as $c): ?>
                                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3 show-assignee-grp d-none" id="show-grp-employee">
                                    <label class="form-label small fw-semibold">Select Employee <span class="text-danger">*</span></label>
                                    <select name="employee_id" class="form-select" style="font-size: 13px;">
                                        <option value="">-- Choose Staff Member --</option>
                                        <?php foreach ($employees as $e): ?>
                                            <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3 show-assignee-grp d-none" id="show-grp-team">
                                    <label class="form-label small fw-semibold">Select Team <span class="text-danger">*</span></label>
                                    <select name="team_id" class="form-select" style="font-size: 13px;">
                                        <option value="">-- Choose Team --</option>
                                        <?php foreach ($teams as $t): ?>
                                            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3 show-assignee-grp d-none" id="show-grp-venue">
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
                                    <input type="text" name="condition_on_issue" class="form-control" value="<?= htmlspecialchars($equipment['condition_status'], ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Good" style="font-size: 13px;">
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Notes</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..." style="font-size: 13px;"></textarea>
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

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var select = document.getElementById('modalAssigneeType');
                if (select) {
                    select.addEventListener('change', function() {
                        var val = this.value;
                        ['athlete', 'coach', 'employee', 'team', 'venue'].forEach(function(g) {
                            var el = document.getElementById('show-grp-' + g);
                            if (el) {
                                if (g === val) {
                                    el.classList.remove('d-none');
                                } else {
                                    el.classList.add('d-none');
                                }
                            }
                        });
                    });
                }
            });
            </script>
        <?php endif; ?>

        <!-- RETURN MODAL -->
        <?php if ($equipment['status'] === 'assigned'): ?>
            <div class="modal fade" id="returnModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content" style="border-radius: var(--ks-radius-card);">
                        <form method="POST" action="/equipment/<?= (int)$equipment['id'] ?>/return">
                            <div class="modal-header border-bottom">
                                <h5 class="modal-title fw-bold" style="color: var(--ks-navy); font-size: 15px;">
                                    <i class="bi bi-arrow-return-left text-success me-2"></i>Return Equipment
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
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
                                    <label class="form-label small fw-semibold">Return Location</label>
                                    <input type="text" name="return_location" class="form-control" value="Main Storage" style="font-size: 13px;">
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Inspection / Return Notes</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Notes on wear, damages, or missing parts..." style="font-size: 13px;"></textarea>
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

    <?php endif; ?>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
