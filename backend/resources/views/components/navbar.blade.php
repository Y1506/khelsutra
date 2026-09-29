<?php
$auth = $_SESSION['auth'] ?? null;

if ($auth) {
    $userName = trim(($auth['user']['first_name'] ?? '') . ' ' . ($auth['user']['last_name'] ?? ''));
    $userRole = $auth['role']['name'] ?? 'Sports Administrator';
    $fInit = substr($auth['user']['first_name'] ?? 'S', 0, 1);
    $lInit = substr($auth['user']['last_name'] ?? 'A', 0, 1);
    $initials = strtoupper($fInit . $lInit);
    $isSuperAdmin = ((int)($auth['role']['id'] ?? 0) === 1) || ($userRole === 'Super Admin');
    $orgBadgeText = $isSuperAdmin ? 'KhelSutra Platform' : ($auth['organization']['name'] ?? 'Apex Sports Academy') . ' (' . ($auth['organization']['organization_code'] ?? 'ORG-DEMO') . ')';
    $navUserId = (int)($auth['user']['id'] ?? 1);
    $navOrgId = (int)($auth['organization']['id'] ?? 1);
} else {
    // Default Sports Administrator context
    $userName = 'Rajesh Sharma';
    $userRole = 'Sports Administrator';
    $initials = 'RS';
    $isSuperAdmin = false;
    $orgBadgeText = 'Apex Sports Academy (ORG-DEMO)';
    $navUserId = 1;
    $navOrgId = 1;
}

$navNotifService = new \App\Services\Notification\NotificationService();
$navUnreadCount = 0;
$navRecentNotifs = ['data' => [], 'total' => 0];
try {
    $navUnreadCount = $navNotifService->getUnreadCount($navOrgId, $navUserId);
    $navRecentNotifs = $navNotifService->getUserNotifications($navOrgId, $navUserId, 1, 5);
} catch (\Throwable $e) {}
?>

<header class="ks-top-header">
    <div class="d-flex align-items-center gap-3">
        <!-- Mobile Toggle Button -->
        <button class="ks-mobile-toggle" id="ksMobileToggle" aria-label="Toggle navigation">
            <i class="bi bi-list"></i>
        </button>

        <!-- Static Organisation Context (No Switcher - Tenant Enforced) -->
        <div class="ks-org-selector" style="cursor: default;">
            <i class="bi bi-building"></i>
            <span class="fw-semibold"><?= htmlspecialchars($orgBadgeText) ?></span>
        </div>
    </div>

    <!-- Global Search (Rule 41: Functional Search) -->
    <form action="/search" method="GET" class="ks-global-search m-0">
        <i class="bi bi-search"></i>
        <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" class="ks-search-input" placeholder="Search athletes, teams, tournaments..." aria-label="Global Search">
    </form>

    <!-- Header Right -->
    <div class="ks-header-right">
        <!-- Notification Bell (Section 12) -->
        <div class="dropdown">
            <button class="ks-notification-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications" id="ksNavNotifBtn">
                <i class="bi bi-bell fs-5"></i>
                <span class="ks-notification-badge <?= $navUnreadCount === 0 ? 'd-none' : '' ?>" id="ks-nav-notif-badge">
                    <?= $navUnreadCount > 99 ? '99+' : $navUnreadCount ?>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="width: 340px; border-radius: 12px; font-size: 13px; padding: 12px 0;" id="ks-nav-notif-menu">
                <li class="px-3 pb-2 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Notifications</span>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge <?= $navUnreadCount > 0 ? 'bg-primary-subtle text-primary' : 'bg-light text-muted' ?>" id="ks-nav-notif-count">
                            <?= $navUnreadCount ?> New
                        </span>
                        <?php if ($navUnreadCount > 0): ?>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-muted" style="font-size: 11px;" onclick="ksNavbarMarkAllRead(event)">
                                Mark all read
                            </button>
                        <?php endif; ?>
                    </div>
                </li>

                <?php if (empty($navRecentNotifs['data'])): ?>
                    <li class="px-3 py-4 text-center text-muted" id="ks-nav-notif-empty">
                        <i class="bi bi-bell-slash d-block fs-3 mb-1 text-secondary opacity-50"></i>
                        <div class="small fw-medium">No notifications</div>
                        <div style="font-size: 11px;">You're all caught up!</div>
                    </li>
                <?php else: ?>
                    <?php foreach ($navRecentNotifs['data'] as $notifItem): ?>
                        <?php
                        $nType = $notifItem['notification_type'] ?? '';
                        $nIcon = 'bi-bell';
                        $nColor = 'text-primary';
                        $nLink = '/notifications';

                        if ($nType === 'low_stock') {
                            $nIcon = 'bi-box-seam';
                            $nColor = 'text-danger';
                            $nLink = !empty($notifItem['reference_id']) ? '/inventory/' . $notifItem['reference_id'] : '/inventory';
                        } elseif ($nType === 'equipment_overdue') {
                            $nIcon = 'bi-clock-history';
                            $nColor = 'text-warning';
                            $nLink = '/equipment';
                        } elseif ($nType === 'budget_alert') {
                            $nIcon = 'bi-cash-stack';
                            $nColor = 'text-danger';
                            $nLink = !empty($notifItem['reference_id']) ? '/finance/budgets/' . $notifItem['reference_id'] : '/finance';
                        }
                        ?>
                        <li class="ks-nav-notif-item" id="ks-nav-notif-<?= $notifItem['id'] ?>">
                            <div class="dropdown-item py-2 px-3 d-flex gap-2 align-items-start <?= empty($notifItem['is_read']) ? 'bg-light-subtle' : '' ?>" style="white-space: normal; cursor: pointer;">
                                <i class="bi <?= $nIcon ?> <?= $nColor ?> mt-1 fs-6 flex-shrink-0"></i>
                                <div class="flex-grow-1 overflow-hidden" onclick="window.location.href='<?= $nLink ?>'">
                                    <div class="d-flex justify-content-between align-items-center gap-1">
                                        <span class="fw-semibold text-truncate <?= empty($notifItem['is_read']) ? 'text-dark' : 'text-muted' ?>" style="font-size: 12.5px;">
                                            <?= htmlspecialchars($notifItem['title']) ?>
                                        </span>
                                        <span class="text-muted text-nowrap ms-1" style="font-size: 10px;">
                                            <?= date('d M', strtotime($notifItem['created_at'])) ?>
                                        </span>
                                    </div>
                                    <div class="text-muted small" style="font-size: 11.5px; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?= htmlspecialchars($notifItem['message']) ?>
                                    </div>
                                </div>
                                <?php if (empty($notifItem['is_read'])): ?>
                                    <button type="button" class="btn btn-link p-0 text-primary ms-1 flex-shrink-0" style="font-size: 13px;" onclick="ksNavbarMarkRead(event, <?= $notifItem['id'] ?>)" title="Mark as read">
                                        <i class="bi bi-check2"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>

                <li class="pt-2 border-top text-center">
                    <a href="/notifications" class="text-decoration-none small text-primary fw-semibold">View All Notifications</a>
                </li>
            </ul>

        </div>

        <script>
        function ksNavbarMarkRead(event, notifId) {
            if (event) { event.stopPropagation(); event.preventDefault(); }
            fetch('/notifications/read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ notification_id: notifId })
            })
            .then(res => res.json())
            .then(data => {
                const item = document.getElementById('ks-nav-notif-' + notifId);
                if (item) {
                    item.querySelectorAll('.bg-light-subtle').forEach(el => el.classList.remove('bg-light-subtle'));
                    const btn = item.querySelector('button');
                    if (btn) btn.remove();
                }
                const badge = document.getElementById('ks-nav-notif-badge');
                const countText = document.getElementById('ks-nav-notif-count');
                const unread = data.unread_count || 0;
                if (badge) {
                    if (unread > 0) {
                        badge.innerText = unread > 99 ? '99+' : unread;
                        badge.classList.remove('d-none');
                    } else {
                        badge.classList.add('d-none');
                    }
                }
                if (countText) {
                    countText.innerText = unread + ' New';
                    if (unread === 0) {
                        countText.className = 'badge bg-light text-muted';
                    }
                }
            })
            .catch(() => {});
        }

        function ksNavbarMarkAllRead(event) {
            if (event) { event.stopPropagation(); event.preventDefault(); }
            fetch('/notifications/read-all', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(() => {
                const badge = document.getElementById('ks-nav-notif-badge');
                const countText = document.getElementById('ks-nav-notif-count');
                if (badge) badge.classList.add('d-none');
                if (countText) {
                    countText.innerText = '0 New';
                    countText.className = 'badge bg-light text-muted';
                }
                document.querySelectorAll('.ks-nav-notif-item .bg-light-subtle').forEach(el => el.classList.remove('bg-light-subtle'));
                document.querySelectorAll('.ks-nav-notif-item button').forEach(el => el.remove());
                const markAllBtn = event.target;
                if (markAllBtn) markAllBtn.remove();
            })
            .catch(() => {});
        }
        </script>

        <!-- User Profile (Section 13) -->
        <div class="dropdown">
            <div class="ks-user-widget dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="ks-avatar-circle"><?= htmlspecialchars($initials) ?></div>
                <div class="d-none d-sm-block text-start">
                    <div class="ks-user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="ks-user-role"><?= htmlspecialchars($userRole) ?></div>
                </div>
                <i class="bi bi-chevron-down text-muted" style="font-size: 11px;"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 13px; border-radius: 10px;">
                <li><a class="dropdown-item" href="/settings#profile"><i class="bi bi-person me-2"></i> My Profile</a></li>
                <li><a class="dropdown-item" href="/settings"><i class="bi bi-gear me-2"></i> Account Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/logout"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
            </ul>
        </div>
    </div>
</header>

<script>
let notifTimer;
function ksPollNotifications() {
    fetch('/api/v1/notifications')
    .then(r => r.json())
    .then(d => {
        if(!d.success) return;
        let unread = d.data.filter(x => !x.is_read);
        let list = document.getElementById('ksNotifList');
        document.getElementById('ksNotifBadge').style.display = unread.length > 0 ? 'flex' : 'none';
        document.getElementById('ksNotifBadge').innerText = unread.length;
        document.getElementById('ksNotifHeaderBadge').innerText = unread.length + ' New';
        
        if (d.data.length === 0) {
            list.innerHTML = '<li class="p-3 text-center text-muted small">No notifications</li>';
            return;
        }
        
        list.innerHTML = d.data.slice(0, 5).map(n => `
            <li>
                <a class="dropdown-item py-2 d-flex gap-2 ${n.is_read ? 'opacity-75' : 'bg-light'}" href="#" onclick="ksMarkRead(${n.id})">
                    <i class="bi ${n.is_read ? 'bi-bell' : 'bi-bell-fill'} text-primary mt-1"></i>
                    <div>
                        <div class="fw-semibold">${n.title}</div>
                        <div class="text-muted small">${n.message}</div>
                    </div>
                </a>
            </li>
        `).join('');
    }).catch(e => console.error(e));
}

function ksMarkRead(id) {
    fetch('/api/v1/notifications/' + id + '/read', {
        method: 'PATCH',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'}
    }).then(() => ksPollNotifications());
}

function ksMarkAllRead() {
    // Demo implementation
    ksPollNotifications(); 
}

document.addEventListener('DOMContentLoaded', () => {
    ksPollNotifications();
    notifTimer = setInterval(ksPollNotifications, 30000); // 30s polling
});
</script>
