<?php
$title = "Notifications — KhelSutra Platform";
$auth = $_SESSION['auth'] ?? null;
$orgId = current_organization_id();
$userId = current_user_id() ?? (int)($auth['user']['id'] ?? 1);

$notifService = new \App\Services\Notification\NotificationService();
$filterType = $_GET['type'] ?? 'all';
$unreadOnly = isset($_GET['unread']) && $_GET['unread'] === '1';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;

$unreadCount = $notifService->getUnreadCount($orgId, $userId);
$notifsResult = $notifService->getUserNotifications($orgId, $userId, $page, $limit, $unreadOnly);
$notifications = $notifsResult['data'] ?? [];
$total = $notifsResult['total'] ?? 0;
$totalPages = $notifsResult['total_pages'] ?? 1;

// Client side filtering for type if requested
if (!empty($filterType) && $filterType !== 'all') {
    $notifications = array_values(array_filter($notifications, function($n) use ($filterType) {
        return ($n['notification_type'] ?? '') === $filterType;
    }));
}

ob_start();
?>

<div class="ks-page-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 text-muted small">
                <li class="breadcrumb-item"><a href="/dashboard" class="text-decoration-none text-muted">Dashboard</a></li>
                <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Notifications</li>
            </ol>
        </nav>
        <h2 class="ks-page-title mb-0">System Notifications & Alerts</h2>
        <p class="text-muted small mb-0">Stay informed on low-stock inventory, overdue equipment returns, and budget utilization thresholds.</p>
    </div>

    <div class="d-flex align-items-center gap-2">
        <?php if ($unreadCount > 0): ?>
            <form action="/notifications/read-all" method="POST" class="m-0">
                <button type="submit" class="ks-btn ks-btn-secondary d-flex align-items-center gap-2">
                    <i class="bi bi-check2-all"></i>
                    <span>Mark All as Read</span>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-4">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 bg-primary-subtle text-primary p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-bell fs-5"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Total Notifications</div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format($total) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 bg-danger-subtle text-danger p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-exclamation-circle fs-5"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Unread Alerts</div>
                    <div class="fs-4 fw-bold <?= $unreadCount > 0 ? 'text-danger' : 'text-dark' ?>"><?= number_format($unreadCount) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 bg-success-subtle text-success p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-check2-circle fs-5"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Read Status</div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format(max(0, $total - $unreadCount)) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="btn-group btn-group-sm" role="group">
                <a href="/notifications" class="btn <?= empty($unreadOnly) && $filterType === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    All
                </a>
                <a href="/notifications?unread=1" class="btn <?= $unreadOnly ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Unread Only (<?= $unreadCount ?>)
                </a>
                <a href="/notifications?type=low_stock" class="btn <?= $filterType === 'low_stock' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Low Stock
                </a>
                <a href="/notifications?type=equipment_overdue" class="btn <?= $filterType === 'equipment_overdue' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Overdue Equipment
                </a>
                <a href="/notifications?type=budget_alert" class="btn <?= $filterType === 'budget_alert' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Budget Alerts
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Notification Feed -->
<div class="card border-0 shadow-sm rounded-3 overflow-hidden">
    <?php if (empty($notifications)): ?>
        <div class="p-5 text-center text-muted">
            <i class="bi bi-bell-slash fs-1 text-secondary opacity-50 mb-3 d-block"></i>
            <h5 class="fw-semibold text-dark mb-1">No Notifications Found</h5>
            <p class="small text-muted mb-0">You're all caught up with your operational and logistics alerts.</p>
        </div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($notifications as $n): ?>
                <?php
                $type = $n['notification_type'] ?? '';
                $isRead = !empty($n['is_read']);
                $icon = 'bi-bell-fill';
                $badgeClass = 'bg-primary-subtle text-primary';
                $badgeText = 'General';
                $link = null;

                if ($type === 'low_stock') {
                    $icon = 'bi-box-seam-fill';
                    $badgeClass = 'bg-danger-subtle text-danger';
                    $badgeText = 'Low Stock';
                    $link = !empty($n['reference_id']) ? '/inventory/' . $n['reference_id'] : '/inventory';
                } elseif ($type === 'equipment_overdue') {
                    $icon = 'bi-clock-history';
                    $badgeClass = 'bg-warning-subtle text-warning-emphasis';
                    $badgeText = 'Equipment Overdue';
                    $link = '/equipment';
                } elseif ($type === 'budget_alert') {
                    $icon = 'bi-cash-stack';
                    $badgeClass = 'bg-danger-subtle text-danger';
                    $badgeText = 'Budget Alert';
                    $link = !empty($n['reference_id']) ? '/finance/budgets/' . $n['reference_id'] : '/finance';
                }
                ?>
                <div class="list-group-item p-3 d-flex align-items-start gap-3 <?= !$isRead ? 'bg-light-subtle' : '' ?>" style="transition: background-color 0.15s ease;">
                    <div class="rounded-circle p-2 d-flex align-items-center justify-content-center flex-shrink-0 <?= $badgeClass ?>" style="width: 40px; height: 40px;">
                        <i class="bi <?= $icon ?> fs-5"></i>
                    </div>

                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold <?= !$isRead ? 'text-dark' : 'text-muted' ?>" style="font-size: 14px;">
                                    <?= htmlspecialchars($n['title']) ?>
                                </span>
                                <span class="badge <?= $badgeClass ?> px-2 py-1" style="font-size: 11px;">
                                    <?= $badgeText ?>
                                </span>
                                <?php if (!$isRead): ?>
                                    <span class="badge bg-danger rounded-pill" style="font-size: 9px; padding: 2px 6px;">NEW</span>
                                <?php endif; ?>
                            </div>
                            <span class="text-muted small">
                                <i class="bi bi-clock me-1"></i><?= date('d M Y, h:i A', strtotime($n['created_at'])) ?>
                            </span>
                        </div>

                        <p class="text-secondary small mb-2" style="line-height: 1.5;">
                            <?= htmlspecialchars($n['message']) ?>
                        </p>

                        <div class="d-flex align-items-center gap-3">
                            <?php if ($link): ?>
                                <a href="<?= $link ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 12px;">
                                    View Resource <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            <?php endif; ?>

                            <?php if (!$isRead): ?>
                                <form action="/notifications/read" method="POST" class="m-0">
                                    <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-decoration-none text-muted p-0" style="font-size: 12px;">
                                        <i class="bi bi-check2 me-1"></i>Mark as read
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: 11px;">
                                    <i class="bi bi-check2-all text-success me-1"></i>Read
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top p-3 d-flex align-items-center justify-content-between">
                <span class="text-muted small">Page <?= $page ?> of <?= $totalPages ?> (Total <?= $total ?> items)</span>
                <nav>
                    <ul class="pagination pagination-sm m-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="/notifications?page=<?= $page - 1 ?>&unread=<?= $unreadOnly ? 1 : 0 ?>&type=<?= urlencode($filterType) ?>">Previous</a>
                        </li>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="/notifications?page=<?= $page + 1 ?>&unread=<?= $unreadOnly ? 1 : 0 ?>&type=<?= urlencode($filterType) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
