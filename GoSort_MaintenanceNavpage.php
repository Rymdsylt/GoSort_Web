<?php
session_start();
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';
require_once 'gs_DB/activity_logs.php';

if (isset($_GET['logout'])) {
    if (isset($_SESSION['user_id'])) { log_logout($_SESSION['user_id']); }
    session_destroy();
    setcookie('user_logged_in', '', time() - 3600, '/');
    header("Location: GoSort_Login.php");
    exit();
}

if (!isset($_SESSION['user_id']) || !isset($_COOKIE['user_logged_in'])) {
    header("Location: GoSort_Login.php");
    exit();
}

$sort = $_GET['sort'] ?? 'recent';

$orderBy = match($sort) {
    'name'   => 'device_name ASC',
    'active' => 'last_active DESC',
    default  => 'created_at DESC'
};

$stmt = $pdo->query("
    SELECT id, device_name, device_identity, status, maintenance_mode, last_active, location, created_at
    FROM sorters ORDER BY {$orderBy}
");
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$onlineDevices      = array_values(array_filter($devices, fn($d) => $d['status'] === 'online'));
$offlineDevices     = array_values(array_filter($devices, fn($d) => $d['status'] === 'offline'));
$maintenanceDevices = array_values(array_filter($devices, fn($d) => $d['status'] === 'maintenance'));

function getSortUrl($sortValue) {
    $p = $_GET; $p['sort'] = $sortValue;
    return '?' . http_build_query($p);
}

$currentSortLabel = match($sort) {
    'name'   => 'Name (A-Z)',
    'active' => 'Last Active',
    default  => 'Most Recent',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Maintenance Overview</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode-global.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="js/theme-manager.js"></script>
    <style>
        :root {
            --primary-green:  #274a17;
            --light-green:    #7AF146;
            --mid-green:      #368137;
            --dark-gray:      #1f2937;
            --medium-gray:    #6b7280;
            --border-color:   #e5e7eb;
            --card-shadow:    0 1px 3px rgba(0,0,0,0.07);
        }

        body {
            background-color: #e8f1e6;
            font-family: 'Poppins', sans-serif !important;
            color: var(--dark-gray);
        }

        #main-content-wrapper {
            margin-left: 240px;
            padding: 100px 0 20px 0;
            height: 100vh;
            overflow-y: auto;
        }

        .section-container {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #eeeeee;
        }

        .section-block {
            background: linear-gradient(135deg, rgb(236,251,234) 0%, #d5f5dc 100%);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }
        .section-block:last-child { margin-bottom: 0; }

        .section-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #000000b1;
            margin-bottom: 1.25rem;
        }

        .page-header {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .page-header-right {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .search-wrap { position: relative; }
        .search-wrap input {
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            padding: 0.45rem 0.75rem 0.45rem 2.1rem;
            font-size: 0.82rem;
            font-family: 'Poppins', sans-serif;
            color: var(--dark-gray);
            width: 200px;
            outline: none;
            transition: border-color 0.2s;
        }
        .search-wrap input:focus { border-color: var(--mid-green); }
        .search-wrap .search-icon {
            position: absolute;
            left: 0.65rem; top: 50%;
            transform: translateY(-50%);
            color: var(--medium-gray);
            font-size: 0.8rem;
            pointer-events: none;
        }

        .sort-btn {
            background: #fff;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            padding: 0.45rem 0.85rem;
            font-size: 0.82rem;
            font-family: 'Poppins', sans-serif;
            color: var(--dark-gray);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: border-color 0.2s;
        }
        .sort-btn:hover { border-color: var(--mid-green); }

        .custom-drive-dropdown {
            border-radius: 12px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            border: 1px solid #efefef !important;
            padding: 6px !important;
            min-width: 170px !important;
            background: #fff;
        }
        .custom-drive-dropdown .dropdown-item {
            border-radius: 7px;
            padding: 7px 10px;
            font-size: 0.82rem;
            font-family: 'Poppins', sans-serif;
            color: var(--dark-gray);
        }
        .custom-drive-dropdown .dropdown-item:hover { background: #f1f3f4; }
        .custom-drive-dropdown .dropdown-item.active-sort {
            background: #e8f5e1 !important;
            color: var(--primary-green) !important;
            font-weight: 600;
        }
        .dropdown-item:active { background-color: inherit !important; color: inherit !important; }

        /* ── Device card ── */
        .maint-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.1rem 1.2rem;
            box-shadow: var(--card-shadow);
            transition: all 0.2s;
            height: 100%;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            display: block;
            color: inherit;
            text-decoration: none;
        }
        .maint-card:hover {
            box-shadow: 0 4px 14px rgba(0,0,0,0.09);
            transform: translateY(-2px);
            color: inherit;
            text-decoration: none;
        }
        .maint-card::before {
            content: '';
            position: absolute;
            left: 0; top: 16px; bottom: 16px;
            width: 3px;
            border-radius: 0 3px 3px 0;
            background: linear-gradient(to bottom, var(--light-green), var(--primary-green));
        }
        .maint-card.offline::before     { background: linear-gradient(to bottom, #d1d5db, #9ca3af); }
        .maint-card.offline             { opacity: 0.85; cursor: default; }
        .maint-card.offline:hover       { transform: none; box-shadow: var(--card-shadow); }
        .maint-card.maintenance::before { background: linear-gradient(to bottom, #93c5fd, #1d4ed8); }

        .maint-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.85rem;
        }

        .device-icon-wrap {
            width: 38px; height: 38px;
            background: #e8f5e1;
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .device-icon-wrap i { font-size: 1rem; color: var(--primary-green); }

        .maint-device-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 0 0 0.25rem 0;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.68rem;
            font-weight: 600;
            padding: 0.18rem 0.55rem;
            border-radius: 20px;
        }
        .status-pill .dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
        .status-pill.online      { background: #dcfce7; color: #15803d; }
        .status-pill.offline     { background: #fee2e2; color: #dc2626; }
        .status-pill.maintenance { background: #dbeafe; color: #1d4ed8; }

        .maint-info-item {
            font-size: 0.75rem;
            color: var(--medium-gray);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 0.3rem;
        }
        .maint-info-item i { font-size: 0.72rem; color: #9ca3af; }

        .maint-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.7rem;
            border-top: 1px solid #f3f4f6;
            margin-top: 0.75rem;
        }
        .footer-label {
            font-size: 0.7rem;
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .footer-action {
            font-size: 0.76rem;
            color: var(--primary-green);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .empty-state {
            padding: 3rem 0;
            text-align: center;
            color: var(--medium-gray);
        }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 0.5rem; color: #9ca3af; }
        .empty-state p { font-size: 0.88rem; margin: 0; }

        #deviceWrapper { min-height: 200px; }

        @media (max-width: 992px) {
            #main-content-wrapper { margin-left: 0; padding: 12px; }
            .search-wrap input { width: 100%; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container-fluid">

            <?php include 'topbar.php'; ?>

            <div class="section-container">

                <!-- Header -->
                <div class="page-header">
                    <div class="page-header-right">
                        <div class="search-wrap">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" id="searchDevice" placeholder="Search device…">
                        </div>
                        <div class="dropdown">
                            <button class="sort-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-arrow-down-up" style="font-size:0.78rem;"></i>
                                <?php echo $currentSortLabel; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end custom-drive-dropdown">
                                <li><a class="dropdown-item<?= $sort==='recent'?' active-sort':'' ?>" href="<?= getSortUrl('recent') ?>">Most Recent</a></li>
                                <li><a class="dropdown-item<?= $sort==='name'  ?' active-sort':'' ?>" href="<?= getSortUrl('name') ?>">Name (A–Z)</a></li>
                                <li><a class="dropdown-item<?= $sort==='active'?' active-sort':'' ?>" href="<?= getSortUrl('active') ?>">Last Active</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div id="deviceWrapper">

                    <?php $hasAny = !empty($onlineDevices) || !empty($offlineDevices) || !empty($maintenanceDevices); ?>

                    <?php if (!$hasAny): ?>

                        <!-- No devices registered -->
                        <div class="empty-state">
                            <i class="bi bi-cpu"></i>
                            <p>No devices registered yet.</p>
                        </div>

                    <?php else: ?>

                        <!-- Online -->
                        <?php if (!empty($onlineDevices)): ?>
                        <div class="section-block">
                            <div class="section-label">
                                <span style="display:inline-flex;align-items:center;gap:0.5rem;">
                                    <span style="width:7px;height:7px;border-radius:50%;background:#15803d;box-shadow:0 0 5px rgba(21,128,61,0.5);display:inline-block;"></span>
                                    Online Devices (<?php echo count($onlineDevices); ?>)
                                </span>
                            </div>
                            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                                <?php foreach ($onlineDevices as $d): ?>
                                <div class="col" data-device="<?php echo htmlspecialchars($d['device_name']); ?>">
                                    <a class="maint-card online"
                                       href="GoSort_Maintenance.php?device=<?php echo $d['id']; ?>&name=<?php echo urlencode($d['device_name']); ?>&identity=<?php echo urlencode($d['device_identity']); ?>">
                                        <div class="maint-card-header">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="device-icon-wrap">
                                                    <i class="bi bi-cpu-fill"></i>
                                                </div>
                                                <div>
                                                    <div class="maint-device-name"><?php echo htmlspecialchars($d['device_name']); ?></div>
                                                    <div class="status-pill online"><span class="dot"></span> Online</div>
                                                </div>
                                            </div>
                                            <?php if (!empty($d['location'])): ?>
                                                <span style="font-size:0.7rem;color:#9ca3af;">
                                                    <i class="bi bi-geo-alt-fill" style="font-size:0.68rem;"></i>
                                                    <?php echo htmlspecialchars($d['location']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="maint-info-item">
                                            <i class="bi bi-clock"></i>
                                            Last active: <?php echo $d['last_active'] ? date('M j, Y g:i A', strtotime($d['last_active'])) : 'Never'; ?>
                                        </div>
                                        <div class="maint-card-footer">
                                            <span class="footer-label"><i class="bi bi-tools"></i> Ready for maintenance</span>
                                            <span class="footer-action"><i class="bi bi-arrow-right-circle"></i> Start</span>
                                        </div>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- In Maintenance -->
                        <?php if (!empty($maintenanceDevices)): ?>
                        <div class="section-block">
                            <div class="section-label">
                                <span style="display:inline-flex;align-items:center;gap:0.5rem;">
                                    <span style="width:7px;height:7px;border-radius:50%;background:#1d4ed8;display:inline-block;"></span>
                                    In Maintenance (<?php echo count($maintenanceDevices); ?>)
                                </span>
                            </div>
                            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                                <?php foreach ($maintenanceDevices as $d): ?>
                                <div class="col" data-device="<?php echo htmlspecialchars($d['device_name']); ?>">
                                    <a class="maint-card maintenance"
                                       href="GoSort_Maintenance.php?device=<?php echo $d['id']; ?>&name=<?php echo urlencode($d['device_name']); ?>&identity=<?php echo urlencode($d['device_identity']); ?>">
                                        <div class="maint-card-header">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="device-icon-wrap" style="background:#dbeafe;">
                                                    <i class="bi bi-tools" style="color:#1d4ed8;"></i>
                                                </div>
                                                <div>
                                                    <div class="maint-device-name"><?php echo htmlspecialchars($d['device_name']); ?></div>
                                                    <div class="status-pill maintenance"><span class="dot"></span> Maintenance</div>
                                                </div>
                                            </div>
                                            <?php if (!empty($d['location'])): ?>
                                                <span style="font-size:0.7rem;color:#9ca3af;">
                                                    <i class="bi bi-geo-alt-fill" style="font-size:0.68rem;"></i>
                                                    <?php echo htmlspecialchars($d['location']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="maint-info-item">
                                            <i class="bi bi-clock"></i>
                                            Last active: <?php echo $d['last_active'] ? date('M j, Y g:i A', strtotime($d['last_active'])) : 'Never'; ?>
                                        </div>
                                        <div class="maint-card-footer">
                                            <span class="footer-label" style="color:#1d4ed8;"><i class="bi bi-wrench-adjustable"></i> Currently in maintenance</span>
                                            <span class="footer-action" style="color:#1d4ed8;"><i class="bi bi-arrow-right-circle"></i> View</span>
                                        </div>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Offline -->
                        <?php if (!empty($offlineDevices)): ?>
                        <div class="section-block">
                            <div class="section-label">
                                <span style="display:inline-flex;align-items:center;gap:0.5rem;">
                                    <span style="width:7px;height:7px;border-radius:50%;background:#dc2626;display:inline-block;"></span>
                                    Offline Devices (<?php echo count($offlineDevices); ?>)
                                </span>
                            </div>
                            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                                <?php foreach ($offlineDevices as $d): ?>
                                <div class="col" data-device="<?php echo htmlspecialchars($d['device_name']); ?>">
                                    <div class="maint-card offline"
                                         onclick="showOfflineModal('<?php echo htmlspecialchars(addslashes($d['device_name'])); ?>','<?php echo htmlspecialchars(addslashes($d['location'])); ?>','<?php echo $d['last_active']; ?>')">
                                        <div class="maint-card-header">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="device-icon-wrap" style="background:#f3f4f6;">
                                                    <i class="bi bi-cpu-fill" style="color:#9ca3af;"></i>
                                                </div>
                                                <div>
                                                    <div class="maint-device-name"><?php echo htmlspecialchars($d['device_name']); ?></div>
                                                    <div class="status-pill offline"><span class="dot"></span> Offline</div>
                                                </div>
                                            </div>
                                            <?php if (!empty($d['location'])): ?>
                                                <span style="font-size:0.7rem;color:#9ca3af;">
                                                    <i class="bi bi-geo-alt-fill" style="font-size:0.68rem;"></i>
                                                    <?php echo htmlspecialchars($d['location']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="maint-info-item">
                                            <i class="bi bi-clock"></i>
                                            Last active: <?php echo $d['last_active'] ? date('M j, Y g:i A', strtotime($d['last_active'])) : 'Never'; ?>
                                        </div>
                                        <div class="maint-card-footer">
                                            <span class="footer-label"><i class="bi bi-exclamation-circle"></i> Unavailable for maintenance</span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                    <?php endif; ?>

                </div><!-- /deviceWrapper -->
            </div><!-- /section-container -->
        </div>
    </div>

    <!-- Offline Modal -->
    <div class="modal fade" id="offlineDeviceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 4px 24px rgba(60,64,67,0.15);">
                <div class="modal-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="modal-title d-flex align-items-center gap-2" style="font-family:'Poppins',sans-serif;font-size:0.95rem;font-weight:700;">
                            <i class="bi bi-exclamation-triangle-fill text-warning"></i> Device Offline
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div id="offlineDeviceInfo" class="mb-3"></div>
                    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert" style="font-size:0.8rem;font-family:'Poppins',sans-serif;">
                        <i class="bi bi-info-circle-fill mt-1"></i>
                        Maintenance mode is only available for online devices. Please ensure the device is connected and try again.
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal" style="font-family:'Poppins',sans-serif;font-size:0.82rem;">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        const offlineModal = new bootstrap.Modal(document.getElementById('offlineDeviceModal'));

        window.showOfflineModal = function(deviceName, location, lastActive) {
            document.getElementById('offlineDeviceInfo').innerHTML = `
                <div style="font-family:'Poppins',sans-serif;">
                    <div style="font-size:0.95rem;font-weight:700;margin-bottom:0.5rem;">${deviceName}</div>
                    <div style="font-size:0.78rem;color:#6b7280;display:flex;align-items:center;gap:0.4rem;margin-bottom:0.3rem;">
                        <i class="bi bi-geo-alt-fill"></i> ${location || 'No location set'}
                    </div>
                    <div style="font-size:0.78rem;color:#6b7280;display:flex;align-items:center;gap:0.4rem;">
                        <i class="bi bi-clock-fill"></i> Last active: ${lastActive ? new Date(lastActive).toLocaleString() : 'Never'}
                    </div>
                </div>`;
            offlineModal.show();
        };

        document.getElementById('searchDevice').addEventListener('keyup', function() {
            const val = this.value.toLowerCase().trim();
            document.querySelectorAll('[data-device]').forEach(col => {
                col.style.display = col.getAttribute('data-device').toLowerCase().includes(val) ? '' : 'none';
            });
        });
    </script>
</body>
</html>