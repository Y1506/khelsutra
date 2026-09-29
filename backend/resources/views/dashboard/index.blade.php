<?php
$activePage = 'dashboard';
$title = 'Sports Operations Dashboard — KhelSutra';

$reportService = new \App\Services\Report\ReportService();
$metrics = $reportService->getDashboardMetrics(1);
$fixtures = $reportService->getUpcomingFixtures(1, 5);
$sessions = $reportService->getTodaySessions(1, 5);

ob_start();
?>

<!-- Page Header (Rule 7 & 8: Clean Page Title + Primary Action, No Subtitle) -->
<div class="ks-page-header">
    <div>
        <h1 class="ks-page-title">Sports Operations Dashboard</h1>
    </div>
    <div class="ks-header-actions">
        <!-- Date Indicator Widget -->
        <div class="ks-date-widget">
            <i class="bi bi-calendar-check fs-5"></i>
            <div>
                <div class="ks-date-text"><?= date('l, d M Y') ?></div>
                <div class="ks-time-text"><?= date('h:i A') ?></div>
            </div>
        </div>

        <a href="/reports" class="ks-btn ks-btn-secondary">
            <i class="bi bi-file-earmark-bar-graph"></i>
            <span>View Reports</span>
        </a>

        <a href="/athletes/create" class="ks-btn ks-btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Add Athlete</span>
        </a>
    </div>
</div>

<!-- Primary KPI Row (Real Database Values) -->
<div class="row g-3 mb-3">
    <!-- Card 1: Total Athletes -->
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-blue">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
                <div>
                    <div class="ks-kpi-label">Total Athletes</div>
                    <div class="ks-kpi-value"><?= (int)$metrics['total_athletes'] ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom">
                <span class="ks-trend-text ks-trend-positive">
                    <i class="bi bi-person-check fs-5 align-middle"></i> Registered in academy
                </span>
                <svg class="ks-sparkline" viewBox="0 0 90 28" fill="none">
                    <path d="M2 22C18 20 28 26 44 14C60 2 72 16 88 4" stroke="#16A34A" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Card 2: Total Coaches -->
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-purple">
                    <i class="bi bi-person-badge-fill fs-4"></i>
                </div>
                <div>
                    <div class="ks-kpi-label">Total Coaches</div>
                    <div class="ks-kpi-value"><?= (int)$metrics['total_coaches'] ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom">
                <span class="ks-trend-text">Licensed coaching staff</span>
                <svg class="ks-sparkline" viewBox="0 0 90 28" fill="none">
                    <path d="M2 18C20 18 30 24 50 16C70 8 78 4 88 12" stroke="#7C3AED" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Card 3: Total Teams -->
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-green">
                    <i class="bi bi-shield-shaded fs-4"></i>
                </div>
                <div>
                    <div class="ks-kpi-label">Total Teams</div>
                    <div class="ks-kpi-value"><?= (int)$metrics['total_teams'] ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom">
                <span class="ks-trend-text">Active squads</span>
                <svg class="ks-sparkline" viewBox="0 0 90 28" fill="none">
                    <path d="M2 16C22 14 36 24 54 10C72 -4 78 18 88 8" stroke="#16A34A" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Card 4: Upcoming Tournaments -->
    <div class="col-xl-3 col-md-6">
        <div class="ks-kpi-card">
            <div class="ks-kpi-top">
                <div class="ks-icon-box ks-icon-amber">
                    <i class="bi bi-trophy-fill fs-4"></i>
                </div>
                <div>
                    <div class="ks-kpi-label">Active Tournaments</div>
                    <div class="ks-kpi-value"><?= (int)$metrics['upcoming_tournaments'] ?></div>
                </div>
            </div>
            <div class="ks-kpi-bottom">
                <span class="ks-trend-text text-warning">State & academy leagues</span>
                <svg class="ks-sparkline" viewBox="0 0 90 28" fill="none">
                    <path d="M2 20C16 16 34 8 52 14C70 20 78 12 88 6" stroke="#F59E0B" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Compact KPI Strip (Real Database Metrics) -->
<div class="row g-3 mb-4">
    <!-- Compact 1: Upcoming Matches -->
    <div class="col-xl col-md-4 col-sm-6">
        <a href="/tournaments" class="ks-compact-card">
            <div class="ks-compact-left">
                <div class="ks-compact-icon ks-icon-cyan">
                    <i class="bi bi-calendar-event fs-5"></i>
                </div>
                <div>
                    <div class="ks-compact-title">Upcoming Matches</div>
                    <div class="ks-compact-value"><?= (int)$metrics['upcoming_matches'] ?></div>
                    <div class="ks-compact-sub">Fixtures scheduled</div>
                </div>
            </div>
            <i class="bi bi-chevron-right ks-compact-chevron" style="color: #06B6D4;"></i>
        </a>
    </div>

    <!-- Compact 2: Today's Training -->
    <div class="col-xl col-md-4 col-sm-6">
        <a href="/training" class="ks-compact-card">
            <div class="ks-compact-left">
                <div class="ks-compact-icon ks-icon-blue">
                    <i class="bi bi-stopwatch-fill fs-5"></i>
                </div>
                <div>
                    <div class="ks-compact-title">Today's Training</div>
                    <div class="ks-compact-value"><?= (int)$metrics['todays_training'] ?></div>
                    <div class="ks-compact-sub">Scheduled sessions</div>
                </div>
            </div>
            <i class="bi bi-chevron-right ks-compact-chevron" style="color: #6366F1;"></i>
        </a>
    </div>

    <!-- Compact 3: Venue Bookings -->
    <div class="col-xl col-md-4 col-sm-6">
        <a href="/venues" class="ks-compact-card">
            <div class="ks-compact-left">
                <div class="ks-compact-icon ks-icon-purple">
                    <i class="bi bi-building fs-5"></i>
                </div>
                <div>
                    <div class="ks-compact-title">Venue Bookings</div>
                    <div class="ks-compact-value"><?= (int)$metrics['venue_bookings'] ?></div>
                    <div class="ks-compact-sub">Courts & grounds</div>
                </div>
            </div>
            <i class="bi bi-chevron-right ks-compact-chevron" style="color: #7C3AED;"></i>
        </a>
    </div>

    <!-- Compact 4: Pending Leave -->
    <div class="col-xl col-md-6 col-sm-6">
        <a href="/leave" class="ks-compact-card">
            <div class="ks-compact-left">
                <div class="ks-compact-icon ks-icon-red">
                    <i class="bi bi-file-earmark-text fs-5"></i>
                </div>
                <div>
                    <div class="ks-compact-title">Pending Leave</div>
                    <div class="ks-compact-value" style="color: var(--ks-danger);"><?= (int)$metrics['pending_leave'] ?></div>
                    <div class="ks-compact-sub">Awaiting review</div>
                </div>
            </div>
            <i class="bi bi-chevron-right ks-compact-chevron" style="color: var(--ks-danger);"></i>
        </a>
    </div>

    <!-- Compact 5: Low Inventory -->
    <div class="col-xl col-md-6 col-sm-12">
        <a href="/inventory" class="ks-compact-card">
            <div class="ks-compact-left">
                <div class="ks-compact-icon ks-icon-amber">
                    <i class="bi bi-box-seam fs-5"></i>
                </div>
                <div>
                    <div class="ks-compact-title">Low Inventory</div>
                    <div class="ks-compact-value" style="color: var(--ks-warning);"><?= (int)$metrics['low_inventory'] ?></div>
                    <div class="ks-compact-sub">Stock below threshold</div>
                </div>
            </div>
            <i class="bi bi-chevron-right ks-compact-chevron" style="color: var(--ks-warning);"></i>
        </a>
    </div>
</div>

<!-- Main Operations Grid (Tables & Live Sessions) -->
<div class="row g-4 mb-4">
    <!-- Left Column: Upcoming Fixtures & Matches Table -->
    <div class="col-lg-7">
        <div class="ks-content-card h-100">
            <div class="ks-card-header">
                <div class="ks-header-left">
                    <i class="bi bi-trophy-fill" style="color: var(--ks-gold); font-size: 18px;"></i>
                    <h3 class="ks-header-title">Upcoming Fixtures & Matches</h3>
                </div>
                <a href="/tournaments" class="ks-header-link">
                    <span>View All</span>
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="ks-table-responsive">
                <table class="ks-table">
                    <thead>
                        <tr>
                            <th>Tournament</th>
                            <th>Teams</th>
                            <th>Venue Facility</th>
                            <th>Schedule</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fixtures)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="bi bi-calendar-x d-block fs-3 mb-2"></i>
                                    No upcoming fixtures scheduled.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fixtures as $fix): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-navy"><?= htmlspecialchars($fix['tournament_name'] ?? 'Championship') ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($fix['round_name'] ?? 'Regular Round') ?></div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-navy"><?= htmlspecialchars($fix['home_team_name'] ?? 'Home Team') ?></span>
                                        <span class="text-muted small mx-1">vs</span>
                                        <span class="fw-bold text-navy"><?= htmlspecialchars($fix['away_team_name'] ?? 'Away Team') ?></span>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($fix['venue_name'] ?? 'Main Venue') ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($fix['facility_name'] ?? 'Facility') ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?= date('d M Y', strtotime($fix['scheduled_date'])) ?></div>
                                        <div class="text-muted small"><?= date('h:i A', strtotime($fix['scheduled_start_time'])) ?></div>
                                    </td>
                                    <td>
                                        <span class="ks-badge ks-badge-scheduled text-uppercase"><?= htmlspecialchars($fix['status']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Today's Sessions & Facility Slots -->
    <div class="col-lg-5">
        <div class="ks-content-card h-100">
            <div class="ks-card-header">
                <div class="ks-header-left">
                    <i class="bi bi-stopwatch-fill" style="color: var(--ks-primary); font-size: 18px;"></i>
                    <h3 class="ks-header-title">Today's Sessions & Facility Slots</h3>
                </div>
                <a href="/training" class="ks-header-link">
                    <span>View All</span>
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="ks-card-body p-0">
                <?php if (empty($sessions)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar2-check d-block fs-3 mb-2"></i>
                        No training sessions scheduled for today.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($sessions as $sess): ?>
                            <a href="/training/<?= $sess['id'] ?>" class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between text-decoration-none">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="ks-icon-box ks-icon-blue" style="width: 42px; height: 42px;">
                                        <i class="bi bi-activity fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-navy"><?= htmlspecialchars($sess['title']) ?></div>
                                        <div class="text-muted small">
                                            <span><?= htmlspecialchars($sess['team_name'] ?? 'Academy Squad') ?></span> •
                                            <span>Coach: <?= htmlspecialchars($sess['coach_name'] ?? 'Assigned Coach') ?></span>
                                        </div>
                                        <div class="text-muted small mt-1">
                                            <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($sess['venue_name'] ?? '') ?> (<?= htmlspecialchars($sess['facility_name'] ?? '') ?>)
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold text-primary" style="font-size: 13px;">
                                        <?= date('h:i A', strtotime($sess['start_time'])) ?> - <?= date('h:i A', strtotime($sess['end_time'])) ?>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary mt-1 text-uppercase" style="font-size: 10px;">
                                        <?= htmlspecialchars($sess['status']) ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Operations: Quick Actions -->
<div class="ks-content-card mb-4">
    <div class="ks-card-header">
        <div class="ks-header-left">
            <i class="bi bi-lightning-charge-fill" style="color: var(--ks-gold); font-size: 18px;"></i>
            <h3 class="ks-header-title">Quick Operational Actions</h3>
        </div>
    </div>
    <div class="p-3">
        <div class="d-flex flex-wrap gap-2">
            <a href="/athletes/create" class="btn btn-outline-primary d-flex align-items-center gap-2" style="border-radius: 8px; font-weight: 500; font-size: 13px;">
                <i class="bi bi-person-plus-fill"></i> Add New Athlete
            </a>
            <a href="/coaches/create" class="btn btn-outline-primary d-flex align-items-center gap-2" style="border-radius: 8px; font-weight: 500; font-size: 13px;">
                <i class="bi bi-person-badge"></i> Add New Coach
            </a>
            <a href="/teams/create" class="btn btn-outline-primary d-flex align-items-center gap-2" style="border-radius: 8px; font-weight: 500; font-size: 13px;">
                <i class="bi bi-shield-plus"></i> Create Team
            </a>
            <a href="/training/create" class="btn btn-outline-primary d-flex align-items-center gap-2" style="border-radius: 8px; font-weight: 500; font-size: 13px;">
                <i class="bi bi-stopwatch"></i> Schedule Training Session
            </a>
            <a href="/tournaments/create" class="btn btn-outline-primary d-flex align-items-center gap-2" style="border-radius: 8px; font-weight: 500; font-size: 13px;">
                <i class="bi bi-trophy"></i> Register Tournament
            </a>
            <a href="/venues/bookings/create" class="btn btn-outline-primary d-flex align-items-center gap-2" style="border-radius: 8px; font-weight: 500; font-size: 13px;">
                <i class="bi bi-calendar-plus"></i> New Facility Booking
            </a>
            <a href="/inventory/create" class="btn btn-outline-primary d-flex align-items-center gap-2" style="border-radius: 8px; font-weight: 500; font-size: 13px;">
                <i class="bi bi-box-seam"></i> Add Stock Item
            </a>
        </div>
    </div>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
