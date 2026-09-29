<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'KhelSutra — Sports Management Platform') ?></title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- KhelSutra Master Design System Stylesheet with Cache Busting -->
    <link rel="stylesheet" href="/assets/css/khelsutra-design-system.css?v=<?= @filemtime(dirname(__DIR__, 3) . '/public/assets/css/khelsutra-design-system.css') ?: time() ?>">

    <!-- Idempotency Token for Operations Module -->
    <meta name="idempotency-token" content="<?= bin2hex(random_bytes(16)) ?>">
    <script>
        const originalFetch = window.fetch;
        window.fetch = async function() {
            let [resource, config] = arguments;
            if(config && config.method && config.method.toUpperCase() === 'POST' && resource.includes('/api/v1/')) {
                config.headers = config.headers || {};
                if(!config.headers['Idempotency-Key']) {
                    const token = document.querySelector('meta[name="idempotency-token"]')?.content;
                    if(token) config.headers['Idempotency-Key'] = token;
                }
            }
            return originalFetch(resource, config);
        };
    </script>
</head>

<body>
    <div class="ks-app-layout">
        <!-- Sidebar Component -->
        <?php include __DIR__ . '/../components/sidebar.blade.php'; ?>

        <!-- Main Workspace -->
        <div class="ks-main-wrapper">
            <!-- Header Component -->
            <?php include __DIR__ . '/../components/navbar.blade.php'; ?>

            <!-- Page Body -->
            <main class="ks-page-body">
                <?php
                $flashSuccess = $_GET['success'] ?? $_SESSION['flash_success'] ?? null;
                $flashError = $_GET['error'] ?? $_SESSION['flash_error'] ?? null;
                unset($_SESSION['flash_success'], $_SESSION['flash_error']);
                ?>

                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4 py-2 px-3 shadow-sm" role="alert" style="border-radius: 10px; font-size: 13.5px; border-left: 4px solid var(--ks-success);">
                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                        <span class="fw-medium text-dark"><?= htmlspecialchars($flashSuccess) ?></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 10px;"></button>
                    </div>
                <?php endif; ?>

                <?php if ($flashError): ?>
                    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4 py-2 px-3 shadow-sm" role="alert" style="border-radius: 10px; font-size: 13.5px; border-left: 4px solid var(--ks-danger);">
                        <i class="bi bi-exclamation-octagon-fill text-danger fs-5"></i>
                        <span class="fw-medium text-dark"><?= htmlspecialchars($flashError) ?></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 10px;"></button>
                    </div>
                <?php endif; ?>

                <?= $slot ?? '' ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- KS Shared UI Utilities -->
    <script>
    // ── Escape HTML (shared across all views) ──────────────────────────────────
    window.ksEscape = (v) => {
        if (v == null) return '';
        return String(v).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    };

    // ── Toast notifications (replaces all alert() calls) ──────────────────────
    window.ksToast = (msg, type = 'success') => {
        let tc = document.getElementById('ks-toast-container');
        if (!tc) {
            tc = document.createElement('div');
            tc.id = 'ks-toast-container';
            tc.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
            document.body.appendChild(tc);
        }
        const id = 'toast-' + Date.now();
        const colors = { success: '#166534', danger: '#991B1B', warning: '#92400E', info: '#1E40AF' };
        const icons  = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
        const t = document.createElement('div');
        t.id = id;
        t.style.cssText = `background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:12px 16px;box-shadow:0 4px 16px rgba(0,0,0,.12);display:flex;align-items:center;gap:10px;min-width:280px;max-width:380px;font-size:13.5px;font-family:Inter,sans-serif;`;
        t.innerHTML = `<i class="bi ${icons[type]}" style="color:${colors[type]};font-size:16px;flex-shrink:0;"></i><span style="color:#1E293B;">${ksEscape(msg)}</span><button onclick="this.closest('[id^=toast-]').remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:#94A3B8;font-size:18px;line-height:1;">&times;</button>`;
        tc.appendChild(t);
        setTimeout(() => t && t.remove(), 4500);
    };

    // ── Side Drawer ──────────────────────────────────────────────────────────
    window.ksDrawerOpen = (id) => {
        const d = document.getElementById(id);
        const ov = document.getElementById(id + '-overlay');
        if (d) { d.style.transform = 'translateX(0)'; d.style.visibility = 'visible'; }
        if (ov) { ov.style.opacity = '1'; ov.style.visibility = 'visible'; }
        document.body.style.overflow = 'hidden';
    };
    window.ksDrawerClose = (id) => {
        const d = document.getElementById(id);
        const ov = document.getElementById(id + '-overlay');
        if (d) { d.style.transform = 'translateX(100%)'; d.style.visibility = 'hidden'; }
        if (ov) { ov.style.opacity = '0'; ov.style.visibility = 'hidden'; }
        document.body.style.overflow = '';
    };
    </script>

    <!-- Master Layout Mobile & Interactive Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.getElementById('ksMobileToggle');
            const sidebar = document.getElementById('ksSidebar');
            const overlay = document.getElementById('ksSidebarOverlay');

            if (toggle && sidebar && overlay) {
                toggle.addEventListener('click', () => {
                    sidebar.classList.toggle('ks-open');
                    overlay.classList.toggle('ks-open');
                });
                overlay.addEventListener('click', () => {
                    sidebar.classList.remove('ks-open');
                    overlay.classList.remove('ks-open');
                });
            }
        });
    </script>

<script>
// Global fix for HTML5 Date/Time validation messages
document.addEventListener('DOMContentLoaded', function() {
    function attachDateValidationFix() {
        document.querySelectorAll('input[type="date"], input[type="time"], input[type="month"]').forEach(input => {
            input.removeEventListener('invalid', handleInvalidDate);
            input.removeEventListener('input', handleDateInput);
            
            input.addEventListener('invalid', handleInvalidDate);
            input.addEventListener('input', handleDateInput);
        });
    }

    function handleInvalidDate(e) {
        if (this.validity.badInput) {
            this.setCustomValidity('Please enter a valid, complete date/time. (e.g. Nov 30 instead of Nov 31)');
        } else if (this.validity.valueMissing) {
            this.setCustomValidity('This date/time field is required.');
        }
    }

    function handleDateInput(e) {
        this.setCustomValidity(''); 
    }

    attachDateValidationFix();

    // Re-attach if DOM changes (e.g. new modals loaded dynamically)
    const observer = new MutationObserver((mutations) => {
        let shouldAttach = false;
        mutations.forEach(m => {
            if (m.addedNodes && m.addedNodes.length > 0) {
                shouldAttach = true;
            }
        });
        if (shouldAttach) attachDateValidationFix();
    });
    
    observer.observe(document.body, { childList: true, subtree: true });
});
</script>
</body>

</html>
