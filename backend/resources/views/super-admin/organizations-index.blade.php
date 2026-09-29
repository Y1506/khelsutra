<?php
$activePage = 'organizations';
$title = 'Organisation Management — KhelSutra Super Admin';

$orgService = new \App\Services\Organization\OrganizationManagementService();
$organizations = $orgService->listOrganizations();

ob_start();
?>

<!-- Page Header (Section 16) -->
<div class="ks-page-header">
    <div>
        <h1 class="ks-page-title">Organisations</h1>
        <p class="ks-page-subtitle">Multi-tenant academy directory, access dates, subscription tiers, and tenant isolation control.</p>
    </div>
    <div class="ks-header-actions">
        <a href="/super-admin/organizations/create" class="ks-btn ks-btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Create Organisation</span>
        </a>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-blue">
                    <i class="bi bi-building-fill fs-4"></i>
                </div>
                <div>
                    <div class="ks-kpi-label">Total Academies</div>
                    <div class="ks-kpi-value"><?= count($organizations) ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom">
                <span class="ks-trend-text ks-trend-positive">Active Multi-Tenant Orgs</span>
                <svg class="ks-sparkline" viewBox="0 0 90 28" fill="none">
                    <path d="M2 22C18 20 28 26 44 14C60 2 72 16 88 4" stroke="#0B6EF3" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-green">
                    <i class="bi bi-patch-check-fill fs-4"></i>
                </div>
                <div>
                    <div class="ks-kpi-label">Active Subscriptions</div>
                    <div class="ks-kpi-value">
                        <?= count(array_filter($organizations, fn($o) => $o['status'] === 'active')) ?>
                    </div>
                </div>
            </div>
            <div class="ks-kpi-bottom">
                <span class="ks-trend-text">Access enabled</span>
                <svg class="ks-sparkline" viewBox="0 0 90 28" fill="none">
                    <path d="M2 18C20 18 30 24 50 16C70 8 78 4 88 12" stroke="#16A34A" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-purple">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
                <div>
                    <div class="ks-kpi-label">Rostered Staff & Coaches</div>
                    <div class="ks-kpi-value">
                        <?= array_sum(array_column($organizations, 'active_employees_count')) ?>
                    </div>
                </div>
            </div>
            <div class="ks-kpi-bottom">
                <span class="ks-trend-text">Platform wide</span>
                <svg class="ks-sparkline" viewBox="0 0 90 28" fill="none">
                    <path d="M2 14C22 10 42 18 62 12C74 8 82 14 88 6" stroke="#7C3AED" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-amber">
                    <i class="bi bi-shield-lock-fill fs-4"></i>
                </div>
                <div>
                    <div class="ks-kpi-label">Tenant Isolation</div>
                    <div class="ks-kpi-value">Enforced</div>
                </div>
            </div>
            <div class="ks-kpi-bottom">
                <span class="ks-trend-text text-warning">Strict Backend Isolation</span>
                <svg class="ks-sparkline" viewBox="0 0 90 28" fill="none">
                    <path d="M2 20C16 16 34 8 52 14C70 20 78 12 88 6" stroke="#F59E0B" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Organisations Table Card (Section 22 & 50) -->
<div class="ks-table-card">
    <div class="ks-table-header">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-building" style="color: var(--ks-primary); font-size: 18px;"></i>
            <span class="ks-card-title mb-0">Platform Organisations</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="position-relative" style="width: 260px;">
                <i class="bi bi-search position-absolute" style="left: 12px; top: 12px; color: var(--ks-text-muted); font-size: 13px;"></i>
                <input type="text" class="ks-form-control" style="padding-left: 34px; height: 38px; font-size: 13px;" placeholder="Search organisation, code...">
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="ks-table">
            <thead>
                <tr>
                    <th>Organisation Code</th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Access Window</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($organizations)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No organisations registered yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($organizations as $org): ?>
                        <tr>
                            <td>
                                <span class="badge" style="background: #EAF3FF; color: #0B6EF3; font-weight: 600; padding: 6px 10px; border-radius: 6px;">
                                    <?= htmlspecialchars($org['organization_code']) ?>
                                </span>
                            </td>
                            <td>
                                <div>
                                    <span class="fw-bold text-navy"><?= htmlspecialchars($org['name']) ?></span>
                                    <?php if (!empty($org['legal_name'])): ?>
                                        <div class="small text-muted"><?= htmlspecialchars($org['legal_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="small text-navy"><?= htmlspecialchars($org['email'] ?? '—') ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($org['phone'] ?? '—') ?></div>
                            </td>
                            <td>
                                <span class="ks-badge ks-badge-blue"><?= htmlspecialchars($org['plan_name'] ?? 'Standard') ?></span>
                            </td>
                            <td>
                                <?php if ($org['status'] === 'active'): ?>
                                    <span class="ks-badge ks-badge-confirmed">Active</span>
                                <?php elseif ($org['status'] === 'suspended'): ?>
                                    <span class="ks-badge ks-badge-rejected">Suspended</span>
                                <?php elseif ($org['status'] === 'expired'): ?>
                                    <span class="ks-badge ks-badge-pending">Expired</span>
                                <?php else: ?>
                                    <span class="ks-badge ks-badge-scheduled"><?= htmlspecialchars(ucfirst($org['status'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small text-navy"><?= htmlspecialchars($org['access_start_date'] ?? '2026-01-01') ?></div>
                                <div class="small text-muted">to <?= htmlspecialchars($org['access_end_date'] ?? '2027-01-01') ?></div>
                            </td>
                            <td style="text-align: right;">
                                <a href="/super-admin/organizations/<?= $org['id'] ?>" class="ks-btn ks-btn-secondary" style="height: 32px; padding: 0 10px; font-size: 12px;">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
