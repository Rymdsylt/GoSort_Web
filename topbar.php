<?php
// topbar.php — include inside #main-content-wrapper on every page

$topbar_userId = $_SESSION['user_id'] ?? null;
$topbar_name   = 'User';
$topbar_email  = '';
$topbar_logo   = 'images/logos/pcs.svg';

if ($topbar_userId) {
    $tb_stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $tb_stmt->execute([$topbar_userId]);
    $tb_user = $tb_stmt->fetch();
    $topbar_name  = $tb_user['username'] ?? 'User';
    $topbar_email = $tb_user['email']    ?? '';
}

// Unread notification count
$notif_stmt = $pdo->prepare("SELECT COUNT(*) FROM bin_notifications WHERE is_read = 0");
$notif_stmt->execute();
$unread_count = $notif_stmt->fetchColumn();

$pageTitles = [
    'GoSort_Dashboard.php'              => 'Dashboard',
    'GoSort_Sorters.php'                => 'Devices',
    'GoSort_AnalyticsNavpage.php'       => 'Analytics',
    'GoSort_Statistics.php'             => 'Analytics',
    'GoSort_MaintenanceNavpage.php'     => 'Maintenance',
    'GoSort_Maintenance.php'            => 'Maintenance',
    'GoSort_WasteMonitoringNavpage.php' => 'Waste Monitoring',
    'GoSort_LiveMonitor.php'            => 'Waste Monitoring',
    'GoSort_ReviewLogs.php'             => 'Waste Monitoring',
    'GoSort_Notifications.php'          => 'Notifications',
    'GoSort_Settings.php'               => 'Settings',
];
$topbar_pageTitle = $pageTitles[basename($_SERVER['PHP_SELF'])] ?? 'GoSort';
?>
<style>
    .topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #fff;
        border-radius: 16px;
        padding: 0.65rem 1.25rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        border: 1px solid #eeeeee;
        font-family: 'Poppins', sans-serif;
        gap: 1rem;

        /* Fixed at top */
        position: fixed;
        top: 1rem;
        left: calc(220px + 2rem);
        right: 1.5rem;
        z-index: 999;
        transition: transform 0.3s ease, opacity 0.3s ease;
    }
    .topbar.hidden {
        transform: translateY(calc(-100% - 1.5rem));
        opacity: 0;
        pointer-events: none;
    }

    .topbar-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
        white-space: nowrap;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .topbar-notif {
        position: relative;
        width: 38px; height: 38px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 10px;
        cursor: pointer;
        transition: background 0.18s;
        text-decoration: none;
        color: #6b7280;
    }
    .topbar-notif:hover { background: #f3f9f1; color: #1f2937; }
    .topbar-notif i { font-size: 1.15rem; }
    .topbar-notif-badge {
        position: absolute;
        top: 6px; right: 7px;
        width: 8px; height: 8px;
        background: #ef4444;
        border-radius: 50%;
        border: 1.5px solid #fff;
    }

    .topbar-divider {
        width: 1px; height: 28px;
        background: #e5e7eb;
        margin: 0 0.25rem;
    }

    .topbar-account {
        position: relative;
        display: flex; align-items: center;
        gap: 0.55rem;
        padding: 0.35rem 0.65rem;
        border-radius: 12px;
        cursor: pointer;
        transition: background 0.18s;
        user-select: none;
    }
    .topbar-account:hover { background: #f3f9f1; }

    .topbar-avatar {
        width: 34px; height: 34px;
        border-radius: 50%;
        object-fit: contain;
        background: #edf7ed;
        border: 2px solid #d1eac8;
        padding: 2px;
        flex-shrink: 0;
    }
    .topbar-account-info {
        display: flex; flex-direction: column; line-height: 1.3;
    }
    .topbar-account-name  { font-size: 0.8rem;  font-weight: 600; color: #1f2937; white-space: nowrap; }
    .topbar-account-email { font-size: 0.7rem;  color: #6b7280;   white-space: nowrap; }
    .topbar-chevron {
        font-size: 0.7rem; color: #9ca3af;
        transition: transform 0.2s; flex-shrink: 0;
    }
    .topbar-account.open .topbar-chevron { transform: rotate(180deg); }

    .topbar-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 8px); right: 0;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        min-width: 195px;
        z-index: 2000;
        overflow: hidden;
        animation: tbDropDown 0.16s ease;
    }
    @keyframes tbDropDown {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .topbar-dropdown.show { display: block; }

    .topbar-dropdown-header {
        padding: 0.85rem 1rem 0.7rem;
        border-bottom: 1px solid #f3f4f6;
    }
    .topbar-dropdown-header .tb-name  { font-size: 0.82rem; font-weight: 600; color: #1f2937; }
    .topbar-dropdown-header .tb-email { font-size: 0.72rem; color: #6b7280; }

    .topbar-dropdown a {
        display: flex; align-items: center; gap: 0.55rem;
        padding: 0.6rem 1rem;
        font-size: 0.8rem; font-weight: 500;
        color: #374151; text-decoration: none;
        transition: background 0.15s;
        font-family: 'Poppins', sans-serif;
    }
    .topbar-dropdown a:hover { background: #f9fafb; }
    .topbar-dropdown a i { font-size: 0.95rem; color: #6b7280; }

    .topbar-dropdown .tb-logout { border-top: 1px solid #f3f4f6; color: #dc2626; }
    .topbar-dropdown .tb-logout i { color: #dc2626; }
    .topbar-dropdown .tb-logout:hover { background: #fff5f5; }
</style>

<div class="topbar">
    <h1 class="topbar-title"><?php echo htmlspecialchars($topbar_pageTitle); ?></h1>

    <div class="topbar-right">

        <a href="GoSort_Notifications.php" class="topbar-notif">
            <i class="bi bi-bell<?= $unread_count > 0 ? '-fill' : '' ?>"></i>
            <?php if ($unread_count > 0): ?>
                <span class="topbar-notif-badge"></span>
            <?php endif; ?>
        </a>

        <div class="topbar-divider"></div>

        <div class="topbar-account" id="topbar-account" onclick="toggleTopbarDropdown()">
            <img src="<?php echo htmlspecialchars($topbar_logo); ?>" alt="avatar" class="topbar-avatar">
            <div class="topbar-account-info">
                <span class="topbar-account-name"><?php echo htmlspecialchars($topbar_name); ?></span>
                <span class="topbar-account-email"><?php echo htmlspecialchars($topbar_email); ?></span>
            </div>
            <i class="bi bi-chevron-down topbar-chevron"></i>

            <div class="topbar-dropdown" id="topbar-dropdown">
                <div class="topbar-dropdown-header">
                    <div class="tb-name"><?php echo htmlspecialchars($topbar_name); ?></div>
                    <div class="tb-email"><?php echo htmlspecialchars($topbar_email); ?></div>
                </div>
                <a href="GoSort_Settings.php">
                    <i class="bi bi-gear"></i> Settings
                </a>
                <a href="?logout=1" class="tb-logout">
                    <i class="bi bi-box-arrow-right"></i> Log out
                </a>
            </div>
        </div>

    </div>
</div>

<script>
function toggleTopbarDropdown() {
    const account  = document.getElementById('topbar-account');
    const dropdown = document.getElementById('topbar-dropdown');
    account.classList.toggle('open');
    dropdown.classList.toggle('show');
}
document.addEventListener('click', function(e) {
    const account = document.getElementById('topbar-account');
    if (account && !account.contains(e.target)) {
        account.classList.remove('open');
        document.getElementById('topbar-dropdown').classList.remove('show');
    }
});

// Smart scroll: hide on scroll down, show on scroll up
(function() {
    let lastScroll = 0;
    const scrollEl = document.getElementById('main-content-wrapper');
    if (!scrollEl) return;
    scrollEl.addEventListener('scroll', function() {
        const current = scrollEl.scrollTop;
        const topbar  = document.querySelector('.topbar');
        if (!topbar) return;
        if (current > lastScroll && current > 60) {
            topbar.classList.add('hidden');
        } else {
            topbar.classList.remove('hidden');
        }
        lastScroll = current;
    });
})();
</script>