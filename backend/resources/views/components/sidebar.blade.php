<?php
// Resolve current active role strictly from authenticated session (never from query parameters)
$currentRole = $_SESSION['auth']['role_slug'] ?? 'sports_admin';
$activePage = $activePage ?? 'dashboard';

// Role-aware navigation definitions for all 7 application roles
$navMenus = [
    'sports_admin' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-house-door-fill', 'url' => '/dashboard'],
        ['key' => 'athletes', 'label' => 'Athletes', 'icon' => 'bi-person-walking', 'url' => '/athletes'],
        ['key' => 'coaches', 'label' => 'Coaches', 'icon' => 'bi-person-badge', 'url' => '/coaches'],
        ['key' => 'teams', 'label' => 'Teams', 'icon' => 'bi-people-fill', 'url' => '/teams'],
        ['key' => 'tournaments', 'label' => 'Tournaments', 'icon' => 'bi-trophy-fill', 'url' => '/tournaments'],
        ['key' => 'training', 'label' => 'Training', 'icon' => 'bi-stopwatch-fill', 'url' => '/attendance/training'],
        ['key' => 'venues', 'label' => 'Venues & Bookings', 'icon' => 'bi-geo-alt-fill', 'url' => '/venues'],
        ['key' => 'operations_venues', 'label' => 'Venues Admin', 'icon' => 'bi-geo-alt-fill', 'url' => '/operations/venues'],
        ['key' => 'bookings', 'label' => 'Facility Bookings', 'icon' => 'bi-calendar-plus', 'url' => '/operations/bookings'],
        ['key' => 'maintenance', 'label' => 'Maintenance', 'icon' => 'bi-tools', 'url' => '/operations/maintenance'],
        ['key' => 'events', 'label' => 'Events', 'icon' => 'bi-calendar-event', 'url' => '/operations/events'],
        ['key' => 'transport', 'label' => 'Transport', 'icon' => 'bi-truck', 'url' => '/operations/transport'],
        ['key' => 'accommodation', 'label' => 'Accommodation', 'icon' => 'bi-building-fill-add', 'url' => '/operations/accommodation'],
        ['key' => 'inventory', 'label' => 'Inventory', 'icon' => 'bi-box-seam-fill', 'url' => '/inventory'],
        ['key' => 'equipment', 'label' => 'Equipment', 'icon' => 'bi-tag-fill', 'url' => '/equipment'],
        ['key' => 'vendors', 'label' => 'Vendors & Suppliers', 'icon' => 'bi-truck', 'url' => '/vendors'],
        ['key' => 'purchases', 'label' => 'Purchases & Orders', 'icon' => 'bi-cart-check-fill', 'url' => '/purchases'],
        ['key' => 'finance', 'label' => 'Finance & Accounting', 'icon' => 'bi-wallet-fill', 'url' => '/finance'],
        ['key' => 'users', 'label' => 'Users & RBAC', 'icon' => 'bi-people-fill', 'url' => '/users'],
        ['key' => 'hr-finance', 'label' => 'Staff & HR', 'icon' => 'bi-briefcase-fill', 'url' => '/hr/employees'],
        ['key' => 'leave', 'label' => 'Leave Requests', 'icon' => 'bi-calendar-check', 'url' => '/leave'],
        ['key' => 'payroll', 'label' => 'Payroll', 'icon' => 'bi-cash-coin', 'url' => '/payroll'],
        ['key' => 'reports', 'label' => 'Reports', 'icon' => 'bi-bar-chart-fill', 'url' => '/reports'],
        ['key' => 'settings', 'label' => 'Settings', 'icon' => 'bi-gear-fill', 'url' => '/settings/organization'],
    ],
    'coach' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-house-door-fill', 'url' => '/dashboard'],
        ['key' => 'teams', 'label' => 'Teams', 'icon' => 'bi-people-fill', 'url' => '/teams'],
        ['key' => 'training', 'label' => 'Training & Attendance', 'icon' => 'bi-stopwatch-fill', 'url' => '/attendance/training'],
        ['key' => 'athletes', 'label' => 'Athletes', 'icon' => 'bi-person-walking', 'url' => '/athletes'],
        ['key' => 'tournaments', 'label' => 'Tournaments', 'icon' => 'bi-trophy-fill', 'url' => '/tournaments'],
        ['key' => 'fixtures', 'label' => 'Fixtures', 'icon' => 'bi-calendar-event', 'url' => '/tournaments#fixtures'],
        ['key' => 'leave', 'label' => 'My Leave', 'icon' => 'bi-calendar-x', 'url' => '/leave'],
        ['key' => 'performance', 'label' => 'Performance', 'icon' => 'bi-graph-up-arrow', 'url' => '/athletes#performance'],
        ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'bi-bell-fill', 'url' => '/settings#notifications'],
    ],
    'athlete' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-house-door-fill', 'url' => '/dashboard'],
        ['key' => 'profile', 'label' => 'My Profile', 'icon' => 'bi-person-badge', 'url' => '/athletes'],
        ['key' => 'teams', 'label' => 'My Team', 'icon' => 'bi-people-fill', 'url' => '/teams'],
        ['key' => 'training', 'label' => 'Training', 'icon' => 'bi-stopwatch-fill', 'url' => '/training'],
        ['key' => 'attendance', 'label' => 'My Attendance', 'icon' => 'bi-calendar-check', 'url' => '/attendance/training'],
        ['key' => 'leave', 'label' => 'Apply Leave', 'icon' => 'bi-calendar-plus', 'url' => '/leave/create'],
        ['key' => 'performance', 'label' => 'Performance', 'icon' => 'bi-graph-up-arrow', 'url' => '/athletes'],
        ['key' => 'achievements', 'label' => 'Achievements', 'icon' => 'bi-award-fill', 'url' => '/athletes'],
        ['key' => 'tournaments', 'label' => 'Tournaments', 'icon' => 'bi-trophy-fill', 'url' => '/tournaments'],
        ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'bi-bell-fill', 'url' => '/settings#notifications'],
    ],
    'hr_finance' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-house-door-fill', 'url' => '/dashboard'],
        ['key' => 'hr-finance', 'label' => 'Staff Directory', 'icon' => 'bi-briefcase-fill', 'url' => '/hr/employees'],
        ['key' => 'training', 'label' => 'Training Attendance', 'icon' => 'bi-calendar-check', 'url' => '/attendance/training'],
        ['key' => 'matches', 'label' => 'Match Attendance', 'icon' => 'bi-trophy', 'url' => '/attendance/matches'],
        ['key' => 'leave', 'label' => 'Leave Requests', 'icon' => 'bi-calendar-x', 'url' => '/leave'],
        ['key' => 'payroll', 'label' => 'Payroll Operations', 'icon' => 'bi-cash-coin', 'url' => '/payroll'],
        ['key' => 'finance', 'label' => 'Finance & Accounting', 'icon' => 'bi-wallet-fill', 'url' => '/finance'],
        ['key' => 'salary-structures', 'label' => 'Salary Structures', 'icon' => 'bi-cash-stack', 'url' => '/payroll/salary-structures'],
        ['key' => 'periods', 'label' => 'Payroll Periods', 'icon' => 'bi-calendar-range', 'url' => '/payroll/periods'],
        ['key' => 'reports', 'label' => 'Reports', 'icon' => 'bi-bar-chart-fill', 'url' => '/reports'],
        ['key' => 'settings', 'label' => 'Settings', 'icon' => 'bi-gear-fill', 'url' => '/settings/organization'],
    ],
    'venue_manager' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-house-door-fill', 'url' => '/dashboard'],
        ['key' => 'operations_venues', 'label' => 'Venues Admin', 'icon' => 'bi-geo-alt-fill', 'url' => '/operations/venues'],
        ['key' => 'events', 'label' => 'Events', 'icon' => 'bi-calendar-event', 'url' => '/operations/events'],
        ['key' => 'transport', 'label' => 'Transport', 'icon' => 'bi-truck', 'url' => '/operations/transport'],
        ['key' => 'accommodation', 'label' => 'Accommodation', 'icon' => 'bi-building-fill-add', 'url' => '/operations/accommodation'],
        ['key' => 'bookings', 'label' => 'Facility Bookings', 'icon' => 'bi-calendar-plus', 'url' => '/operations/bookings'],
        ['key' => 'maintenance', 'label' => 'Maintenance', 'icon' => 'bi-tools', 'url' => '/operations/maintenance'],
        ['key' => 'tournaments', 'label' => 'Tournaments', 'icon' => 'bi-trophy-fill', 'url' => '/tournaments'],
        ['key' => 'reports', 'label' => 'Reports', 'icon' => 'bi-bar-chart-fill', 'url' => '/reports'],
        ['key' => 'settings', 'label' => 'Settings', 'icon' => 'bi-gear-fill', 'url' => '/settings/organization'],
    ],
    'inventory_manager' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-house-door-fill', 'url' => '/dashboard'],
        ['key' => 'inventory', 'label' => 'Stock Inventory', 'icon' => 'bi-box-seam-fill', 'url' => '/inventory'],
        ['key' => 'equipment', 'label' => 'Equipment Tracking', 'icon' => 'bi-tag-fill', 'url' => '/equipment'],
        ['key' => 'vendors', 'label' => 'Vendors & Suppliers', 'icon' => 'bi-truck', 'url' => '/vendors'],
        ['key' => 'purchases', 'label' => 'Purchases & Orders', 'icon' => 'bi-cart-check-fill', 'url' => '/purchases'],
        ['key' => 'reports', 'label' => 'Reports', 'icon' => 'bi-bar-chart-fill', 'url' => '/reports'],
        ['key' => 'settings', 'label' => 'Settings', 'icon' => 'bi-gear-fill', 'url' => '/settings/organization'],
    ],
    'super_admin' => [
        ['key' => 'dashboard', 'label' => 'Platform Dashboard', 'icon' => 'bi-speedometer2', 'url' => '/dashboard'],
        ['key' => 'organizations', 'label' => 'Organisations', 'icon' => 'bi-building-fill', 'url' => '/super-admin/organizations'],
        ['key' => 'users', 'label' => 'Platform Users', 'icon' => 'bi-people-fill', 'url' => '/users'],
        ['key' => 'roles', 'label' => 'Roles & RBAC', 'icon' => 'bi-shield-lock-fill', 'url' => '/roles'],
        ['key' => 'sports', 'label' => 'Sports Catalog', 'icon' => 'bi-trophy-fill', 'url' => '/tournaments'],
        ['key' => 'finance', 'label' => 'Finance & Accounting', 'icon' => 'bi-wallet-fill', 'url' => '/finance'],
        ['key' => 'audit', 'label' => 'Audit Trail', 'icon' => 'bi-journal-check', 'url' => '/audit-logs'],
        ['key' => 'reports', 'label' => 'Global Analytics', 'icon' => 'bi-graph-up', 'url' => '/reports'],
        ['key' => 'settings', 'label' => 'Global Settings', 'icon' => 'bi-sliders', 'url' => '/settings/organization'],
    ],
];

$menuItems = $navMenus[$currentRole] ?? $navMenus['sports_admin'];
?>

<aside class="ks-sidebar" id="ksSidebar">
    <!-- Brand Logo Area -->
    <div class="ks-sidebar-brand">
        <div class="ks-logo-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94.63 1.5 1.98 2.63 3.61 2.96V19H7v2h10v-2h-4v-3.1c1.63-.33 2.98-1.46 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM5 8V7h2v3.82C5.84 10.4 5 9.3 5 8zm14 0c0 1.3-.84 2.4-2 2.82V7h2v1z"/>
            </svg>
        </div>
        <div>
            <div class="ks-brand-title">KhelSutra</div>
            <div class="ks-brand-subtitle">Sports Academy</div>
        </div>
    </div>

    <!-- Main Navigation Items -->
    <ul class="ks-sidebar-nav">
        <?php foreach ($menuItems as $item): ?>
            <?php 
                $isActive = ($activePage === $item['key']); 
                $url = $item['url'];
            ?>
            <li>
                <a href="<?= htmlspecialchars($url) ?>" class="ks-nav-link <?= $isActive ? 'active' : '' ?>">
                    <i class="bi <?= $item['icon'] ?>"></i>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>

        <!-- Sign Out Action -->
        <li style="margin-top: 6px; padding-top: 6px; border-top: 1px solid rgba(255,255,255,0.06);">
            <a href="/logout" class="ks-nav-link">
                <i class="bi bi-box-arrow-right"></i>
                <span>Sign Out</span>
            </a>
        </li>
    </ul>

    <!-- Bottom Decorative Section with Master Sports Image -->
    <div class="ks-sidebar-decorative">
        <img src="/assets/images/khelsutra-sidebar-athletes.png" 
             alt="KhelSutra Athletes: PLAY • TRAIN • GROW" 
             class="ks-sidebar-athletes-img" 
             loading="lazy">
    </div>
</aside>

<!-- Mobile Overlay -->
<div class="ks-sidebar-overlay" id="ksSidebarOverlay"></div>
