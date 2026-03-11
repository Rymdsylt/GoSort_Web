<?php
session_start();
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';
require_once 'gs_DB/activity_logs.php';

if (isset($_GET['logout'])) {
    if (isset($_SESSION['user_id'])) {
        log_logout($_SESSION['user_id']);
    }
    session_destroy();
    setcookie('user_logged_in', '', time() - 3600, '/');
    header("Location: GoSort_Login.php");
    exit();
}

if (!isset($_SESSION['user_id']) || !isset($_COOKIE['user_logged_in'])) {
    header("Location: GoSort_Login.php");
    exit();
}

$stmt = $pdo->prepare("SELECT COUNT(*) as total_devices, 
                       SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online_devices,
                       SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline_devices
                       FROM sorters");
$stmt->execute();
$system_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_sorted,
    SUM(CASE WHEN trash_type = 'bio' THEN 1 ELSE 0 END) as bio_count,
    SUM(CASE WHEN trash_type = 'nbio' THEN 1 ELSE 0 END) as nbio_count,
    SUM(CASE WHEN trash_type = 'hazardous' THEN 1 ELSE 0 END) as hazardous_count,
    SUM(CASE WHEN trash_type = 'mixed' THEN 1 ELSE 0 END) as mixed_count
    FROM sorting_history 
    WHERE DATE(sorted_at) = CURDATE()");
$stmt->execute();
$today_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$recent_activities = get_activity_logs(null, null, 10, 0);

$stmt = $pdo->prepare("SELECT * FROM sorters WHERE status = 'offline' OR status = 'maintenance'");
$stmt->execute();
$system_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// NEW: fetch all devices for status overview
$stmt = $pdo->prepare("SELECT id, device_name, status, location FROM sorters ORDER BY device_name ASC");
$stmt->execute();
$all_devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_devices  = $system_stats['total_devices'];
$online_devices = $system_stats['online_devices'];
$system_uptime  = $total_devices > 0 ? ($online_devices / $total_devices) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Dashboard</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode-global.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="js/theme-manager.js"></script>
    <style>
        :root {
            --primary-green:   #274a17;
            --light-green:     #7AF146;
            --dark-gray:       #1f2937;
            --medium-gray:     #6b7280;
            --light-gray:      #f3f4f6;
            --border-color:    #e5e7eb;
            --card-shadow:     0 1px 3px rgba(0,0,0,0.07);
            --bio-color:       #10b981;
            --nbio-color:      #ef4444;
            --hazardous-color: #f59e0b;
            --mixed-color:     #6b7280;
        }

        body {
            background-color: #e8f1e6;
            font-family: 'Poppins', sans-serif !important;
            color: var(--dark-gray);
        }

        #main-content-wrapper {
            margin-left: 240px;
            padding: 100px 0px 20px 0px;
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
            background: linear-gradient(135deg, rgb(236, 251, 234) 0%, #d5f5dc 100%);
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

        /* ── Welcome card ── */
        .welcome-card {
            background: linear-gradient(135deg, rgb(236, 251, 234) 0%, #84ca92 100%) !important;
            border-radius: 12px;
            padding: 1.75rem;
            color: black;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            margin-bottom: 1rem;
        }

        .welcome-title    { font-size: 1.4rem; font-weight: 700; margin-bottom: 0.2rem; }
        .welcome-subtitle { font-size: 0.875rem; opacity: 0.85; margin-bottom: 1.5rem; }
        .welcome-stats    { display: flex; gap: 2rem; flex-wrap: wrap; }
        .welcome-stat     { display: flex; align-items: center; gap: 0.75rem; }
        .welcome-stat-icon {
            width: 44px; height: 44px;
            background: rgb(253, 255, 254);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
        }
        .welcome-stat-info h3 { font-size: 1.6rem; font-weight: 700; margin: 0; }
        .welcome-stat-info p  { font-size: 0.8rem; margin: 0; opacity: 0.88; }

        /* ── Stat cards ── */
        .stat-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: var(--card-shadow);
            transition: all 0.2s;
            height: 100%;
        }
        .stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .stat-card-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 0.75rem;
        }
        .stat-card-title {
            font-size: 0.75rem; font-weight: 600;
            color: var(--medium-gray);
            text-transform: uppercase; letter-spacing: 0.5px; margin: 0;
        }
        .stat-icon {
            width: 36px; height: 36px;
            background: #e8f5e1;
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            color: var(--primary-green); font-size: 1.1rem;
        }
        .stat-value {
            font-size: 2.2rem; font-weight: 700;
            line-height: 1; margin-bottom: 0.35rem;
        }
        .stat-label { font-size: 0.8rem; color: var(--medium-gray); }

        /* ── Quick actions ── */
        .quick-action-card {
            background: white;
            border: 1.5px solid var(--border-color);
            border-radius: 50%;
            width: 80px; height: 80px;
            box-shadow: var(--card-shadow);
            cursor: pointer;
            transition: all 0.22s ease;
            text-decoration: none;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto;
        }
        .quick-action-card:hover {
            background: var(--light-green);
            transform: scale(1.07);
            box-shadow: 0 6px 14px rgba(88,197,66,0.3);
            border-color: var(--light-green);
        }
        .quick-action-card i { font-size: 1.6rem; color: var(--primary-green); }
        .quick-action-card:hover i { color: white; }
        .quick-action-title {
            margin-top: 0.6rem; font-size: 0.8rem; font-weight: 600;
            color: var(--dark-gray); text-align: center;
        }

        /* ── Alerts ── */
        .alert-item {
            display: flex; align-items: center; gap: 1rem;
            padding: 0.9rem 1rem;
            border-left: 4px solid #f59e0b;
            border-radius: 8px; margin-bottom: 0.6rem;
            background: #fff3cd;
        }
        .alert-item.offline     { background: #fee2e2; border-left-color: #ef4444; }
        .alert-item.maintenance { background: #dbeafe; border-left-color: #3b82f6; }
        .alert-icon   { font-size: 1.3rem; }
        .alert-content h6 { font-size: 0.85rem; font-weight: 600; margin: 0 0 0.2rem 0; }
        .alert-content p  { font-size: 0.78rem; color: var(--medium-gray); margin: 0; }

        .no-alerts { text-align: center; padding: 1.5rem; color: var(--medium-gray); }
        .no-alerts i { font-size: 2.5rem; color: var(--bio-color); display: block; margin-bottom: 0.5rem; }

        /* ── Device status grid ── */
        .device-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 0.75rem;
        }
        .device-status-card {
            background: #fff;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }
        .device-status-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--dark-gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .device-status-location {
            font-size: 0.7rem;
            color: var(--medium-gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .device-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.2rem 0.55rem;
            border-radius: 20px;
            width: fit-content;
        }
        .device-status-pill.online      { background: #dcfce7; color: #15803d; }
        .device-status-pill.offline     { background: #fee2e2; color: #dc2626; }
        .device-status-pill.maintenance { background: #dbeafe; color: #1d4ed8; }
        .device-status-pill .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        /* ── Activity feed ── */
        .activity-feed { display: flex; flex-direction: column; gap: 0; }

        .activity-row {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: background 0.15s;
            border-radius: 8px;
        }
        .activity-row:last-child { border-bottom: none; }
        .activity-row:hover { background: rgba(255,255,255,0.6); }

        .activity-avatar {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: #d4edda;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .activity-avatar i { font-size: 0.9rem; color: var(--primary-green); }

        .activity-body { flex: 1; min-width: 0; }

        .activity-top {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-bottom: 0.2rem;
        }
        .activity-user {
            font-size: 0.75rem;
            color: var(--medium-gray);
        }
        .activity-action {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--dark-gray);
        }
        .activity-device {
            font-size: 0.75rem;
            color: var(--primary-green);
            background: #e8f5e1;
            padding: 0.1rem 0.5rem;
            border-radius: 20px;
            font-weight: 500;
        }
        .activity-device i { font-size: 0.7rem; }
        .activity-details {
            font-size: 0.75rem;
            color: var(--medium-gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .activity-time {
            font-size: 0.72rem;
            color: #9ca3af;
            white-space: nowrap;
            flex-shrink: 0;
            padding-top: 3px;
        }

        @media (max-width: 992px) {
            #main-content-wrapper { margin-left: 0; padding: 12px; }
            .welcome-stats { flex-direction: column; gap: 1rem; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container-fluid">

            <?php include 'topbar.php'; ?>

            <div class="section-container">

                <!-- Welcome -->
                    <div class="welcome-card">
                        <div class="welcome-title">Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</div>
                        <div class="welcome-subtitle">Here's what's happening with your GoSort system today</div>
                        <div class="welcome-stats">
                            <div class="welcome-stat">
                                <div class="welcome-stat-icon"><i class="bi bi-box-seam"></i></div>
                                <div class="welcome-stat-info">
                                    <h3><?php echo number_format($today_stats['total_sorted']); ?></h3>
                                    <p>Items Sorted Today</p>
                                </div>
                            </div>
                            <div class="welcome-stat">
                                <div class="welcome-stat-icon"><i class="bi bi-hdd-rack"></i></div>
                                <div class="welcome-stat-info">
                                    <h3><?php echo $system_stats['online_devices'] ?? 0; ?></h3>
                                    <p>Devices Online</p>
                                </div>
                            </div>
                            <div class="welcome-stat">
                                <div class="welcome-stat-icon"><i class="bi bi-speedometer2"></i></div>
                                <div class="welcome-stat-info">
                                    <h3><?php echo number_format($system_uptime, 1); ?>%</h3>
                                    <p>System Uptime</p>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- Today's Statistics -->
                <div class="section-block">
                    <div class="section-label">Today's Statistics</div>
                    <div class="row g-3">
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-card">
                                <div class="stat-card-header">
                                    <span class="stat-card-title">Biodegradable</span>
                                    <div class="stat-icon"><i class="bi bi-recycle"></i></div>
                                </div>
                                <div class="stat-value" style="color:var(--bio-color);"><?php echo number_format($today_stats['bio_count']); ?></div>
                                <div class="stat-label">Items sorted</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-card">
                                <div class="stat-card-header">
                                    <span class="stat-card-title">Non-Biodegradable</span>
                                    <div class="stat-icon"><i class="bi bi-trash"></i></div>
                                </div>
                                <div class="stat-value" style="color:var(--nbio-color);"><?php echo number_format($today_stats['nbio_count']); ?></div>
                                <div class="stat-label">Items sorted</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-card">
                                <div class="stat-card-header">
                                    <span class="stat-card-title">Hazardous</span>
                                    <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                                </div>
                                <div class="stat-value" style="color:var(--hazardous-color);"><?php echo number_format($today_stats['hazardous_count']); ?></div>
                                <div class="stat-label">Items sorted</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-card">
                                <div class="stat-card-header">
                                    <span class="stat-card-title">Mixed Waste</span>
                                    <div class="stat-icon"><i class="bi bi-collection"></i></div>
                                </div>
                                <div class="stat-value" style="color:var(--mixed-color);"><?php echo number_format($today_stats['mixed_count']); ?></div>
                                <div class="stat-label">Items sorted</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Device Status + System Alerts side by side -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="section-block h-100 mb-0">
                            <div class="section-label">Device Status</div>
                            <?php if (count($all_devices) > 0): ?>
                                <div class="device-status-grid">
                                    <?php foreach ($all_devices as $device): ?>
                                        <div class="device-status-card">
                                            <div class="device-status-name"><?php echo htmlspecialchars($device['device_name']); ?></div>
                                            <?php if (!empty($device['location'])): ?>
                                                <div class="device-status-location"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($device['location']); ?></div>
                                            <?php endif; ?>
                                            <div class="device-status-pill <?php echo htmlspecialchars($device['status']); ?>">
                                                <span class="dot"></span>
                                                <?php echo ucfirst($device['status']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-alerts">
                                    <i class="bi bi-cpu"></i>
                                    <p>No devices registered</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="section-block h-100 mb-0">
                            <div class="section-label">System Alerts</div>
                            <?php if (count($system_alerts) > 0): ?>
                                <?php foreach ($system_alerts as $alert): ?>
                                    <div class="alert-item <?php echo htmlspecialchars($alert['status']); ?>">
                                        <div class="alert-icon">
                                            <?php if ($alert['status'] === 'offline'): ?>
                                                <i class="bi bi-exclamation-circle" style="color:#ef4444;"></i>
                                            <?php else: ?>
                                                <i class="bi bi-tools" style="color:#3b82f6;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="alert-content">
                                            <h6><?php echo htmlspecialchars($alert['device_name']); ?></h6>
                                            <p><?php echo ucfirst($alert['status']); ?> — Requires attention</p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-alerts">
                                    <i class="bi bi-check-circle"></i>
                                    <p>All systems operational</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="section-block">
                    <div class="section-label">Quick Actions</div>
                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6 text-center">
                            <a href="GoSort_Sorters.php" class="quick-action-card"><i class="bi bi-hdd-rack"></i></a>
                            <div class="quick-action-title">View Devices</div>
                        </div>
                        <div class="col-lg-3 col-md-6 text-center">
                            <a href="GoSort_AnalyticsNavpage.php" class="quick-action-card"><i class="bi bi-graph-up"></i></a>
                            <div class="quick-action-title">View Analytics</div>
                        </div>
                        <div class="col-lg-3 col-md-6 text-center">
                            <a href="GoSort_MaintenanceNavpage.php" class="quick-action-card"><i class="bi bi-tools"></i></a>
                            <div class="quick-action-title">View Maintenance</div>
                        </div>
                        <div class="col-lg-3 col-md-6 text-center">
                            <a href="GoSort_Settings.php" class="quick-action-card"><i class="bi bi-gear"></i></a>
                            <div class="quick-action-title">System Settings</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="section-block">
                    <div class="section-label">Recent Activity</div>
                    <?php if (count($recent_activities) > 0): ?>
                        <div class="activity-feed">
                            <?php foreach ($recent_activities as $activity): ?>
                                <?php $at = new DateTime($activity['created_at']); ?>
                                <div class="activity-row">
                                    <div class="activity-avatar">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <div class="activity-body">
                                        <div class="activity-top">
                                            <span class="activity-action"><?php echo htmlspecialchars($activity['action']); ?></span>
                                            <span class="activity-user"><?php echo htmlspecialchars($activity['username'] ?? 'System'); ?></span>
                                            <?php if ($activity['device_name']): ?>
                                                <span class="activity-device"><i class="bi bi-cpu"></i> <?php echo htmlspecialchars($activity['device_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($activity['details'])): ?>
                                            <div class="activity-details"><?php echo htmlspecialchars($activity['details']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-time"><?php echo $at->format('M d, g:i A'); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-alerts">
                            <i class="bi bi-clock-history"></i>
                            <p>No recent activity</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div><!-- /section-container -->
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        setInterval(function() { location.reload(); }, 30000);
    </script>
</body>
</html>