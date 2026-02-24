<?php
session_start();
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';
require_once 'gs_DB/activity_logs.php';

if (isset($_GET['logout'])) {
    // Log logout before destroying session
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

// Get overall system statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_devices, 
                       SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online_devices,
                       SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline_devices
                       FROM sorters");
$stmt->execute();
$system_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get today's sorting statistics
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

// Get recent activities (last 10)
$stmt = $pdo->prepare("SELECT sh.*, s.device_name 
                       FROM sorting_history sh
                       JOIN sorters s ON sh.device_identity = s.device_identity
                       ORDER BY sh.sorted_at DESC 
                       LIMIT 10");
$stmt->execute();
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get system alerts (Note: this system alerts backend must be changed)
$stmt = $pdo->prepare("SELECT * FROM sorters WHERE status = 'offline' OR status = 'maintenance'");
$stmt->execute();
$system_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate uptime percentage
$total_devices = $system_stats['total_devices'];
$online_devices = $system_stats['online_devices'];
$system_uptime = $total_devices > 0 ? ($online_devices / $total_devices) * 100 : 0;
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="js/theme-manager.js"></script>
    <style>
        :root {
            --primary-green: #274a17ff;
            --light-green: #7AF146;
            --dark-gray: #1f2937;
            --medium-gray: #6b7280;
            --light-gray: #f3f4f6;
            --border-color: #368137;
            --card-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            --bio-color: #10b981;
            --nbio-color: #ef4444;
            --hazardous-color: #f59e0b;
            --mixed-color: #6b7280;
        }

        body {
            background-color: #F3F3EF !important;
            font-family: 'Inter', sans-serif !important;
            color: var(--dark-gray);
        }

        #main-content-wrapper {
            margin-left: 260px;
            transition: margin-left 0.3s ease;
            padding: 20px;
        }

        #main-content-wrapper.collapsed {
            margin-left: 80px;
        }

        .page-header {
            padding-top: 1rem;
            margin-bottom: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin-left: 6px;
        }

        .welcome-card {
            background: linear-gradient(135deg, var(--primary-green) 0%, #368137 100%);
            border-radius: 16px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .welcome-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 1.5rem;
        }

        .welcome-stats {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .welcome-stat {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .welcome-stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .welcome-stat-info h3 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
        }

        .welcome-stat-info p {
            font-size: 0.875rem;
            margin: 0;
            opacity: 0.9;
        }

        .stat-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.2s;
        }

        .stat-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-card-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--medium-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            background-color: var(--light-green);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-green);
            font-size: 1.25rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-gray);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--medium-gray);
        }

        .quick-action-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 50%;
            width: 90px;
            height: 90px;
            box-shadow: var(--card-shadow);
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            color: var(--primary-green);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .quick-action-card:hover {
            background: var(--light-green);
            transform: scale(1.05);
            box-shadow: 0 6px 10px rgba(0,0,0,0.15);
        }

        .quick-action-card i {
            font-size: 1.75rem;
            color: var(--primary-green);
        }

        .quick-action-card:hover i {
            color: white;
        }

        .quick-action-title {
            margin-top: 0.75rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark-gray);
            text-align: center;
        }

        .about-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
        }

        .about-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .about-icon {
            width: 64px;
            height: 64px;
            background: var(--light-green);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--primary-green);
        }

        .about-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 0;
        }

        .about-content p {
            font-size: 0.95rem;
            line-height: 1.7;
            color: var(--dark-gray);
            margin-bottom: 1rem;
        }

        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .feature-item {
            display: flex;
            align-items: start;
            gap: 0.75rem;
        }

        .feature-icon {
            width: 32px;
            height: 32px;
            background: var(--light-green);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-green);
            flex-shrink: 0;
            margin-top: 0.25rem;
        }

        .feature-text h6 {
            font-size: 0.875rem;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
        }

        .feature-text p {
            font-size: 0.8rem;
            color: var(--medium-gray);
            margin: 0;
        }

        .activity-log {
            max-height: 595px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid var(--light-gray);
            transition: background 0.2s;
        }

        .activity-item:hover {
            background: var(--light-gray);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .activity-icon.bio { background: rgba(16, 185, 129, 0.1); color: var(--bio-color); }
        .activity-icon.nbio { background: rgba(239, 68, 68, 0.1); color: var(--nbio-color); }
        .activity-icon.hazardous { background: rgba(245, 158, 11, 0.1); color: var(--hazardous-color); }
        .activity-icon.mixed { background: rgba(107, 114, 128, 0.1); color: var(--mixed-color); }

        .activity-details {
            flex: 1;
            min-width: 0;
        }

        .activity-device {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--dark-gray);
        }

        .activity-type {
            font-size: 0.8rem;
            color: var(--medium-gray);
        }

        .activity-time {
            font-size: 0.75rem;
            color: var(--medium-gray);
            text-align: right;
            flex-shrink: 0;
        }

        .alert-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
        }

        .alert-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #fff3cd;
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
            margin-bottom: 0.75rem;
        }

        .alert-item.offline {
            background: #fee2e2;
            border-left-color: #ef4444;
        }

        .alert-item.maintenance {
            background: #dbeafe;
            border-left-color: #3b82f6;
        }

        .alert-icon {
            font-size: 1.5rem;
        }

        .alert-content h6 {
            font-size: 0.875rem;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
        }

        .alert-content p {
            font-size: 0.8rem;
            color: var(--medium-gray);
            margin: 0;
        }

        .no-alerts {
            text-align: center;
            padding: 2rem;
            color: var(--medium-gray);
        }

        .no-alerts i {
            font-size: 3rem;
            color: var(--bio-color);
            margin-bottom: 1rem;
        }

        .team-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
        }

        .team-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .team-member {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .team-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .dev-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
        }

        .dev-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .dev-member {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .dev-icon {
            font-size: 1.5rem;
            color: var(--primary-green);
        }

        .insight-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
        }

        .insight-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .insight-list li {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
        }


        @media (max-width: 992px) {
            #main-content-wrapper {
                margin-left: 0;
                padding: 12px;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .welcome-stats {
                flex-direction: column;
                gap: 1rem;
            }

        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Dashboard</h1>
            </div>

            <hr style="height: 1.5px; background-color: #000; opacity: 1; margin-left:6.5px;" class="mb-4">

            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="welcome-title">Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</div>
                <div class="welcome-subtitle">Here's what's happening with your GoSort system today</div>
                <div class="welcome-stats">
                    <div class="welcome-stat">
                        <div class="welcome-stat-icon">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div class="welcome-stat-info">
                            <h3><?php echo number_format($today_stats['total_sorted']); ?></h3>
                            <p>Items Sorted Today</p>
                        </div>
                    </div>
                    <div class="welcome-stat">
                        <div class="welcome-stat-icon">
                            <i class="bi bi-hdd-rack"></i>
                        </div>
                        <div class="welcome-stat-info">
                            <h3><?php echo $system_stats['online_devices'] ?? 0; ?></h3>
                            <p>Devices Online</p>
                        </div>
                    </div>
                    <div class="welcome-stat">
                        <div class="welcome-stat-icon">
                            <i class="bi bi-speedometer2"></i>
                        </div>
                        <div class="welcome-stat-info">
                            <h3><?php echo number_format($system_uptime, 1); ?>%</h3>
                            <p>System Uptime</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Statistics -->
            <h5 class="mb-3" style="font-weight: 600; color: var(--dark-gray); margin-left: 6px;">Today's Statistics</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <span class="stat-card-title">Biodegradable</span>
                            <div class="stat-icon">
                                <i class="bi bi-recycle"></i>
                            </div>
                        </div>
                        <div class="stat-value" style="color: var(--bio-color);"><?php echo number_format($today_stats['bio_count']); ?></div>
                        <div class="stat-label">Items sorted</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <span class="stat-card-title">Non-Biodegradable</span>
                            <div class="stat-icon">
                                <i class="bi bi-trash"></i>
                            </div>
                        </div>
                        <div class="stat-value" style="color: var(--nbio-color);"><?php echo number_format($today_stats['nbio_count']); ?></div>
                        <div class="stat-label">Items sorted</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <span class="stat-card-title">Hazardous</span>
                            <div class="stat-icon">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                        </div>
                        <div class="stat-value" style="color: var(--hazardous-color);"><?php echo number_format($today_stats['hazardous_count']); ?></div>
                        <div class="stat-label">Items sorted</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <span class="stat-card-title">Mixed Waste</span>
                            <div class="stat-icon">
                                <i class="bi bi-collection"></i>
                            </div>
                        </div>
                        <div class="stat-value" style="color: var(--mixed-color);"><?php echo number_format($today_stats['mixed_count']); ?></div>
                        <div class="stat-label">Items sorted</div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <h5 class="mb-4" style="font-weight: 600; color: var(--dark-gray); margin-left: 6px;">Quick Actions</h5>
            <div class="row g-3 mb-4">
                <div class="col-lg-3 col-md-6 text-center">
                    <a href="GoSort_Sorters.php" class="quick-action-card">
                        <i class="bi bi-hdd-rack"></i>
                    </a>
                    <div class="quick-action-title">View Devices</div>
                </div>

                <div class="col-lg-3 col-md-6 text-center">
                    <a href="GoSort_AnalyticsNavpage.php" class="quick-action-card">
                        <i class="bi bi-graph-up"></i>
                    </a>
                    <div class="quick-action-title">View Analytics</div>
                </div>

                <div class="col-lg-3 col-md-6 text-center">
                    <a href="GoSort_MaintenanceNavpage.php" class="quick-action-card">
                        <i class="bi bi-tools"></i>
                    </a>
                    <div class="quick-action-title">View Maintenance</div>
                </div>

                <div class="col-lg-3 col-md-6 text-center">
                    <a href="GoSort_Settings.php" class="quick-action-card">
                        <i class="bi bi-gear"></i>
                    </a>
                    <div class="quick-action-title">View System Settings</div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="row g-4">
                <!-- Left Column: About GoSort + Additional Cards -->
                <div class="col-lg-8">
                    <?php if(false): ?>
                    <!-- About GoSort -->
                    <div class="about-card mb-4">
                        <div class="about-header">
                            <div class="about-icon">
                                <i class="bi bi-info-circle"></i>
                            </div>
                            <h2 class="about-title">About GoSort</h2>
                        </div>
                        <div class="about-content">
                            <p>
                                <strong>GoSort</strong> is an intelligent waste management system designed to automate and optimize the sorting process. 
                                GoSort helps organizations reduce waste, improve recycling rates, and contribute to a more sustainable future.
                            </p>
                            <p>
                                Our system accurately categorizes waste into biodegradable, non-biodegradable, hazardous, and mixed categories, 
                                ensuring proper disposal and maximizing resource recovery. With real-time monitoring and comprehensive analytics, 
                                you gain complete visibility into your waste management operations.
                            </p>

                            <div class="feature-list">
                                <div class="feature-item">
                                    <div class="feature-icon">
                                        <i class="bi bi-cpu"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h6>Automated trash Sorting</h6>
                                        <p>Intelligent classification for accurate waste separation</p>
                                    </div>
                                </div>
                                <div class="feature-item">
                                    <div class="feature-icon">
                                        <i class="bi bi-clock-history"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h6>Real-Time Monitoring</h6>
                                        <p>Live tracking of all sorting operations</p>
                                    </div>
                                </div>
                                <div class="feature-item">
                                    <div class="feature-icon">
                                        <i class="bi bi-bar-chart"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h6>Advanced Analytics</h6>
                                        <p>Comprehensive insights and performance metrics</p>
                                    </div>
                                </div>
                                <div class="feature-item">
                                    <div class="feature-icon">
                                        <i class="bi bi-shield-check"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h6>Safety Compliance</h6>
                                        <p>Proper handling of hazardous materials</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Custodial Team -->
                    <div class="team-card mb-4">
                        <div class="stat-card-header mb-3">
                            <h5 class="stat-card-title">Custodial Team</h5>
                            <div class="stat-icon">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                        <div class="team-list">
                            <div class="team-member">
                                <img src="images/icons/team.svg" alt="Custodian" class="team-avatar">
                                <div>
                                    <h6>Marlon Lagramada</h6>
                                    <p>Utility Head</p>
                                </div>
                            </div>
                            <div class="team-member">
                                <img src="images/icons/team.svg" alt="Custodian" class="team-avatar">
                                <div>
                                    <h6>Digna De Cagayunan</h6>
                                    <p>Utility Member</p>
                                </div>
                            </div>
                            <div class="team-member">
                                <img src="images/icons/team.svg" alt="Custodian" class="team-avatar">
                                <div>
                                    <h6>Janice Ison</h6>
                                    <p>Utility Member</p>
                                </div>
                            </div>
                            <div class="team-member">
                                <img src="images/icons/team.svg" alt="Custodian" class="team-avatar">
                                <div>
                                    <h6>Mira Luna Villadares</h6>
                                    <p>Utility Member</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Developers Behind GoSort -->
                    <div class="dev-card mb-4">
                        <div class="stat-card-header mb-3">
                            <h5 class="stat-card-title">Developers Behind GoSort</h5>
                            <div class="stat-icon">
                                <i class="bi bi-code-slash"></i>
                            </div>
                        </div>
                        <div class="dev-list">
                            <div class="dev-member">
                                <i class="bi bi-person-circle dev-icon"></i>
                                <div>
                                    <h6>Gwyneth Beatrice Landero</h6>
                                    <p>Project Manager, UI/UX Designer</p>
                                </div>
                            </div>
                            <div class="dev-member">
                                <i class="bi bi-person-circle dev-icon"></i>
                                <div>
                                    <h6>Michael Josh Bargabino</h6>
                                    <p>Mobile App Developer</p>
                                </div>
                            </div>
                            <div class="dev-member">
                                <i class="bi bi-person-circle dev-icon"></i>
                                <div>
                                    <h6>Miguel Roberto Sta. Maria</h6>
                                    <p>Web Developer</p>
                                </div>
                            </div>
                            <div class="dev-member">
                                <i class="bi bi-person-circle dev-icon"></i>
                                <div>
                                    <h6>Diosdado Tempra Jr.</h6>
                                    <p>Tester, Researcher</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- GoSort Insights -->
                    <div class="insight-card mb-4">
                        <div class="stat-card-header mb-3">
                            <h5 class="stat-card-title">GoSort Insights</h5>
                            <div class="stat-icon">
                                <i class="bi bi-lightbulb"></i>
                            </div>
                        </div>
                        <ul class="insight-list">
                            <li>Recyclable waste increased by <b>15%</b> this week ♻️</li>
                            <li>GoSort bins have sorted <b>2,340 kg</b> of waste this month.</li>
                            <li>System uptime: <b>99.9%</b></li>
                            <li>Average response time: <b>0.8s</b> ⚡</li>
                            <li>Carbon footprint reduced by <b>8%</b> 🌏</li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- System Alerts & Recent Activity -->
                <div class="col-lg-12">
                    <!-- System Alerts -->
                    <div class="alert-card mb-4">
                        <div class="stat-card-header mb-3">
                            <h5 class="stat-card-title">System Alerts</h5>
                            <div class="stat-icon">
                                <i class="bi bi-bell"></i>
                            </div>
                        </div>
                        <?php if (count($system_alerts) > 0): ?>
                            <?php foreach ($system_alerts as $alert): ?>
                                <div class="alert-item <?php echo $alert['status']; ?>">
                                    <div class="alert-icon">
                                        <?php if ($alert['status'] === 'offline'): ?>
                                            <i class="bi bi-exclamation-circle" style="color: #ef4444;"></i>
                                        <?php else: ?>
                                            <i class="bi bi-tools" style="color: #3b82f6;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="alert-content">
                                        <h6><?php echo htmlspecialchars($alert['device_name']); ?></h6>
                                        <p><?php echo ucfirst($alert['status']); ?> - Requires attention</p>
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

                    <!-- Recent Activity -->
                    <div class="stat-card">
                        <div class="stat-card-header mb-3">
                            <h5 class="stat-card-title">Recent Activity</h5>
                            <div class="stat-icon">
                                <i class="bi bi-activity"></i>
                            </div>
                        </div>
                        <div class="activity-log">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?php echo htmlspecialchars($activity['trash_type']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </div>
                                    <div class="activity-details">
                                        <div class="activity-device"><?php echo htmlspecialchars($activity['device_name']); ?></div>
                                        <div class="activity-type"><?php echo ucfirst($activity['trash_type']); ?> waste</div>
                                    </div>
                                    <div class="activity-time">
                                        <?php 
                                        $sorted_time = new DateTime($activity['sorted_at']);
                                        $now = new DateTime();
                                        $interval = $now->diff($sorted_time);
                                        
                                        if ($interval->days == 0) {
                                            if ($interval->h == 0) {
                                                echo $interval->i . 'm ago';
                                            } else {
                                                echo $interval->h . 'h ago';
                                            }
                                        } else {
                                            echo $interval->days . 'd ago';
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh statistics every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>