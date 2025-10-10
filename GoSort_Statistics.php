<?php
session_start();
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';

if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('user_logged_in', '', time() - 3600, '/');
    header("Location: GoSort_Login.php");
    exit();
}

if (!isset($_SESSION['user_id']) || !isset($_COOKIE['user_logged_in'])) {
    header("Location: GoSort_Login.php");
    exit();
}

// Get device info - REQUIRED for this page
$device_id = $_GET['device'] ?? null;
$device_identity = $_GET['identity'] ?? null;

// Redirect if no device specified
if (!$device_id || !$device_identity) {
    header("Location: GoSort_Sorters.php");
    exit();
}

// Get specific device info
$stmt = $pdo->prepare("SELECT * FROM sorters WHERE id = ? AND device_identity = ?");
$stmt->execute([$device_id, $device_identity]);
$device_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device_info) {
    header("Location: GoSort_Sorters.php");
    exit();
}

// Determine default time filter based on device status
$default_filter = 'today';
if ($device_info['status'] === 'offline') {
    // Check if there's any activity today
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sorting_history WHERE device_identity = ? AND DATE(sorted_at) = CURDATE()");
    $stmt->execute([$device_identity]);
    $today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($today_count == 0) {
        $default_filter = 'all_time';
    }
}

$time_filter = $_GET['filter'] ?? $default_filter;

// Calculate date range based on filter
$date_condition = "";
$params = [$device_identity];

switch ($time_filter) {
    case 'today':
        $date_condition = "AND DATE(sorted_at) = CURDATE()";
        break;
    case 'yesterday':
        $date_condition = "AND DATE(sorted_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'last_7_days':
        $date_condition = "AND sorted_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'this_month':
        $date_condition = "AND MONTH(sorted_at) = MONTH(CURDATE()) AND YEAR(sorted_at) = YEAR(CURDATE())";
        break;
    case 'all_time':
        $date_condition = "";
        break;
}

// Get sorting history for this specific device
$base_query = "
    SELECT 
        trash_type,
        COUNT(*) as count,
        is_maintenance,
        DATE(sorted_at) as date,
        HOUR(sorted_at) as hour
    FROM sorting_history
    WHERE device_identity = ?
    $date_condition
    GROUP BY trash_type, is_maintenance, DATE(sorted_at), HOUR(sorted_at)
    ORDER BY date ASC, hour ASC
";

$stmt = $pdo->prepare($base_query);
$stmt->execute($params);

// Fetch and process data
$sorting_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$dates = array();
$bio_counts = array();
$nbio_counts = array();
$hazardous_counts = array();
$mixed_counts = array();
$maintenance_counts = array();
$hourly_activity = array_fill(0, 24, 0);
$total_items_processed = 0;

foreach ($sorting_data as $record) {
    $date = $record['date'];
    if (!in_array($date, $dates)) {
        $dates[] = $date;
    }
    
    $count = intval($record['count']);
    $type = $record['trash_type'];
    $is_maintenance = $record['is_maintenance'];
    $hour = intval($record['hour']);
    
    $total_items_processed += $count;
    $hourly_activity[$hour] += $count;

    if ($is_maintenance) {
        $maintenance_counts[$date][$type] = ($maintenance_counts[$date][$type] ?? 0) + $count;
    } else {
        switch ($type) {
            case 'bio':
                $bio_counts[$date] = ($bio_counts[$date] ?? 0) + $count;
                break;
            case 'nbio':
                $nbio_counts[$date] = ($nbio_counts[$date] ?? 0) + $count;
                break;
            case 'hazardous':
                $hazardous_counts[$date] = ($hazardous_counts[$date] ?? 0) + $count;
                break;
            case 'mixed':
                $mixed_counts[$date] = ($mixed_counts[$date] ?? 0) + $count;
                break;
        }
    }
}

// Get total counts
$total_bio = array_sum($bio_counts);
$total_nbio = array_sum($nbio_counts);
$total_hazardous = array_sum($hazardous_counts);
$total_mixed = array_sum($mixed_counts);

// Calculate maintenance counts
$maintenance_bio = 0;
$maintenance_nbio = 0;
$maintenance_hazardous = 0;
$maintenance_mixed = 0;

foreach ($maintenance_counts as $date_counts) {
    $maintenance_bio += $date_counts['bio'] ?? 0;
    $maintenance_nbio += $date_counts['nbio'] ?? 0;
    $maintenance_hazardous += $date_counts['hazardous'] ?? 0;
    $maintenance_mixed += $date_counts['mixed'] ?? 0;
}

// Calculate performance metrics
$total_sorted = $total_bio + $total_nbio + $total_hazardous + $total_mixed;
$operating_days = count($dates) > 0 ? count($dates) : 1;
$avg_per_day = $total_sorted / $operating_days;

// Find peak hour
$peak_hour = array_keys($hourly_activity, max($hourly_activity))[0];
$peak_hour_count = max($hourly_activity);

// Calculate sorting rate (items per hour during operating hours 6am-6pm)
$operating_hours_total = 0;
for ($h = 6; $h <= 18; $h++) {
    $operating_hours_total += $hourly_activity[$h];
}
$operating_hours_count = $operating_days * 12; // 12 hours per day
$items_per_hour = $operating_hours_count > 0 ? $operating_hours_total / $operating_hours_count : 0;

// Environmental impact calculations (example values - adjust based on your research)
$co2_per_kg_recycled = 0.5; // kg CO2 saved per kg recycled
$avg_weight_per_item = 0.3; // kg (assumption)
$recyclable_items = $total_bio + $total_nbio; // bio and nbio can be recycled
$total_weight = $total_sorted * $avg_weight_per_item;
$co2_saved = ($recyclable_items * $avg_weight_per_item * $co2_per_kg_recycled);
$recycling_rate = $total_sorted > 0 ? (($recyclable_items) / $total_sorted * 100) : 0;

// Get last 5 sorting records for recent activity
$recent_stmt = $pdo->prepare("
    SELECT trash_type, sorted_at, is_maintenance 
    FROM sorting_history 
    WHERE device_identity = ? 
    ORDER BY sorted_at DESC 
    LIMIT 10
");
$recent_stmt->execute([$device_identity]);
$recent_activities = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Analytics</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="js/chart.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
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

        .page-header {
            padding-top: 1rem;
            margin-bottom: 1.5rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin-left: 6px;
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .device-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: var(--light-green);
            color: var(--primary-green);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.25rem;
        }

        .status-online {
            background-color: #10b981;
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
        }

        .status-offline {
            background-color: #ef4444;
        }

        .time-filter-dropdown {
            position: relative;
        }

        .time-filter-btn {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 0.5rem 2rem 0.5rem 1rem;
            font-weight: 600;
            color: var(--dark-gray);
            cursor: pointer;
            transition: all 0.2s;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23274a17' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 12px;
            min-width: 160px;
        }

        .time-filter-btn:hover, .time-filter-btn:focus {
            background-color: #efffe8ff;
            color: var(--primary-green);
            border-color: var(--primary-green);
            outline: none;
        }

        .time-filter-btn option {
            background: white;
            color: var(--dark-gray);
            font-weight: 500;
            padding: 8px;
        }

        .export-btn {
            background: var(--primary-green);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .export-btn:hover {
            background-color: #1e3a11;
            transform: translateY(-1px);
        }

        .stat-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            height: 100%;
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
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--border-color);
        }

        .stat-card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-gray);
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

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-stat-box {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .quick-stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--light-green), var(--primary-green));
        }

        .quick-stat-box:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .quick-stat-label {
            font-size: 0.875rem;
            color: var(--medium-gray);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .quick-stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-gray);
            line-height: 1;
        }

        .quick-stat-change {
            font-size: 0.75rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: var(--primary-green);
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .insight-box {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-left: 4px solid var(--primary-green);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .insight-title {
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .insight-text {
            font-size: 0.875rem;
            color: var(--dark-gray);
            line-height: 1.5;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .metric-item {
            text-align: center;
            padding: 1rem;
            background: var(--light-gray);
            border-radius: 8px;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-green);
        }

        .metric-label {
            font-size: 0.75rem;
            color: var(--medium-gray);
            margin-top: 0.25rem;
        }

        .activity-log {
            max-height: 300px;
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
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1rem;
        }

        .activity-icon.bio { background: rgba(16, 185, 129, 0.1); color: var(--bio-color); }
        .activity-icon.nbio { background: rgba(239, 68, 68, 0.1); color: var(--nbio-color); }
        .activity-icon.hazardous { background: rgba(245, 158, 11, 0.1); color: var(--hazardous-color); }
        .activity-icon.mixed { background: rgba(107, 114, 128, 0.1); color: var(--mixed-color); }

        .activity-details {
            flex: 1;
        }

        .activity-type {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--dark-gray);
        }

        .activity-time {
            font-size: 0.75rem;
            color: var(--medium-gray);
        }

        .maintenance-badge {
            background: rgba(245, 158, 11, 0.1);
            color: var(--hazardous-color);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .see-all-link {
            font-size: 0.875rem;
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .see-all-link:hover {
            color: var(--border-color);
        }

        #main-content-wrapper {
            margin-left: 260px;
            transition: margin-left 0.3s ease;
            padding: 20px;
        }

        #main-content-wrapper.collapsed {
            margin-left: 80px;
        }

        @media (max-width: 992px) {
            #main-content-wrapper {
                margin-left: 0;
                padding: 12px;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .quick-stats {
                grid-template-columns: 1fr;
            }

            .header-controls {
                flex-direction: column;
                align-items: stretch;
            }
        }

        @media print {
            .export-btn, .filter-btn, #sidebar { display: none; }
            #main-content-wrapper { margin-left: 0; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container">
            <div class="page-header d-flex align-items-center justify-content-between flex-wrap">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <a href="GoSort_AnalyticsNavpage.php" class="text-decoration-none d-flex align-items-center">
                        <i class="bi bi-arrow-left me-2 fs-4" style="color: var(--dark-gray);"></i>
                        <h1 class="page-title">Analytics</h1>
                    </a>
                </div>

                <div class="header-controls">
                    <div class="device-badge">
                        <span class="status-indicator <?php echo $device_info['status'] === 'online' ? 'status-online' : 'status-offline'; ?>"></span>
                        <i class="bi bi-hdd-rack"></i>
                        <?php echo htmlspecialchars($device_info['device_name']); ?>
                    </div>

                   <div class="time-filter-dropdown-wrapper">
                    <select class="time-filter-btn" id="timeFilter" onchange="changeFilter()">
                        <option value="today" <?php echo $time_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo $time_filter === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="last_7_days" <?php echo $time_filter === 'last_7_days' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="this_month" <?php echo $time_filter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="all_time" <?php echo $time_filter === 'all_time' ? 'selected' : ''; ?>>All Time</option>
                    </select>
                    </div>


                    <button class="export-btn" onclick="exportReport()">
                        <i class="bi bi-download"></i>
                        Export Report
                    </button>
                </div>
            </div>
            
            <hr style="height: 1.5px; background-color: #000; opacity: 1; margin-left:6.5px;" class="mb-4">

            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="quick-stat-box">
                    <div class="quick-stat-label">Total Sorted</div>
                    <div class="quick-stat-value" id="totalSorted"><?php echo number_format($total_sorted); ?></div>
                    <div class="quick-stat-change">
                        <span>↑</span> Live updating
                    </div>
                </div>
                <div class="quick-stat-box">
                    <div class="quick-stat-label">Biodegradable</div>
                    <div class="quick-stat-value" style="color: var(--bio-color);" id="totalBio"><?php echo number_format($total_bio); ?></div>
                </div>
                <div class="quick-stat-box">
                    <div class="quick-stat-label">Non-Biodegradable</div>
                    <div class="quick-stat-value" style="color: var(--nbio-color);" id="totalNbio"><?php echo number_format($total_nbio); ?></div>
                </div>
                <div class="quick-stat-box">
                    <div class="quick-stat-label">Hazardous</div>
                    <div class="quick-stat-value" style="color: var(--hazardous-color);" id="totalHazardous"><?php echo number_format($total_hazardous); ?></div>
                </div>
                <div class="quick-stat-box">
                    <div class="quick-stat-label">Mixed</div>
                    <div class="quick-stat-value" style="color: var(--mixed-color);" id="totalMixed"><?php echo number_format($total_mixed); ?></div>
                </div>
            </div>

<!-- Performance Metrics -->
<div class="row g-0 mb-4"> 
    <div class="col-12 px-0 mb-4">
        <div class="stat-card">
            <div class="stat-card-header">
                <h5 class="stat-card-title">Performance Metrics</h5>
                <div class="stat-icon">
                    <i class="bi bi-speedometer2"></i>
                </div>
            </div>

            <div class="metric-grid">
                <div class="metric-item">
                    <div class="metric-value"><?php echo number_format($avg_per_day, 1); ?></div>
                    <div class="metric-label">Avg per Day</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value"><?php echo number_format($items_per_hour, 1); ?></div>
                    <div class="metric-label">Items/Hour</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value"><?php echo $peak_hour_count > 0 ? date('g A', strtotime("$peak_hour:00")) : 'N/A'; ?></div>
                    <div class="metric-label">Peak Hour</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value"><?php echo number_format($recycling_rate, 1); ?>%</div>
                    <div class="metric-label">Recycling Rate</div>
                </div>
            </div>

            <div class="insight-box">
                <div class="insight-title">
                    <i class="bi bi-info-circle-fill"></i>
                    Operating Hours Analysis
                </div>
                <div class="insight-text">
                    <?php
                    $morning_total = 0; // 6-12
                    $afternoon_total = 0; // 12-18
                    for ($h = 6; $h < 12; $h++) $morning_total += $hourly_activity[$h];
                    for ($h = 12; $h <= 18; $h++) $afternoon_total += $hourly_activity[$h];
                    
                    if ($morning_total > $afternoon_total && $morning_total > 0) {
                        echo "Device is more active in the <strong>morning hours</strong> (6 AM - 12 PM) with " . number_format($morning_total) . " items sorted.";
                    } elseif ($afternoon_total > $morning_total && $afternoon_total > 0) {
                        echo "Device is more active in the <strong>afternoon hours</strong> (12 PM - 6 PM) with " . number_format($afternoon_total) . " items sorted.";
                    } elseif ($morning_total === $afternoon_total && $morning_total > 0) {
                        echo "Activity is evenly distributed between morning and afternoon hours.";
                    } else {
                        echo "No activity recorded during operating hours (6 AM - 6 PM).";
                    }
                    ?>
                </div>
            </div>

            <div class="insight-title">
                <i class="bi bi-lightbulb-fill"></i>
                Insight
            </div>
            <div class="insight-text">
                <?php
                $dominant_type = 'balanced';
                $max_count = max($total_bio, $total_nbio, $total_hazardous, $total_mixed);
                if ($max_count > 0) {
                    if ($total_bio === $max_count) $dominant_type = 'biodegradable';
                    elseif ($total_nbio === $max_count) $dominant_type = 'non-biodegradable';
                    elseif ($total_hazardous === $max_count) $dominant_type = 'hazardous';
                    elseif ($total_mixed === $max_count) $dominant_type = 'mixed';
                }

                if ($total_sorted === 0) {
                    echo "No sorting activity recorded for this period. The device may be offline or inactive.";
                } elseif ($peak_hour >= 6 && $peak_hour <= 18) {
                    echo "Peak activity occurs at " . date('g A', strtotime("$peak_hour:00")) . " with $peak_hour_count items processed. ";
                    if ($dominant_type !== 'balanced') {
                        echo "Most sorted waste is <strong>$dominant_type</strong> (" . number_format(($max_count / $total_sorted) * 100, 1) . "%).";
                    }
                } else {
                    echo "Processing " . number_format($avg_per_day, 0) . " items daily. ";
                    if ($recycling_rate > 75) {
                        echo "Excellent recycling rate of " . number_format($recycling_rate, 1) . "%!";
                    } elseif ($recycling_rate > 50) {
                        echo "Good recycling rate of " . number_format($recycling_rate, 1) . "%. Room for improvement.";
                    } else {
                        echo "Recycling rate is " . number_format($recycling_rate, 1) . "%. Consider user education.";
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <div class="col-12 px-0"> 
        <div class="stat-card">
            <div class="stat-card-header">
                <h5 class="stat-card-title">Environmental Impact</h5>
                <div class="stat-icon">
                    <i class="bi bi-globe"></i>
                </div>
            </div>

            <div class="metric-grid" style="grid-template-columns: 1fr;">
                <div class="metric-item">
                    <div class="metric-value"><?php echo number_format($co2_saved, 1); ?> kg</div>
                    <div class="metric-label">CO₂ Emissions Saved</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value"><?php echo number_format($total_weight, 1); ?> kg</div>
                    <div class="metric-label">Total Weight Processed</div>
                </div>
            </div>

            <div class="insight-box">
                <div class="insight-title">
                    <i class="bi bi-tree-fill"></i>
                    Impact
                </div>
                <div class="insight-text">
                    Proper waste sorting has saved approximately 
                    <strong><?php echo number_format($co2_saved, 1); ?> kg of CO₂</strong> emissions, 
                    equivalent to planting <?php echo ceil($co2_saved / 21); ?> trees!
                </div>
            </div>
        </div>
    </div>
</div>
            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h5 class="stat-card-title">Waste Distribution</h5>
                            <div class="stat-icon">
                                <i class="bi bi-pie-chart-fill"></i>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="totalPieChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h5 class="stat-card-title">Daily Sorting Trend</h5>
                            <div class="stat-icon">
                                <i class="bi bi-graph-up"></i>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="trendLineChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hourly Activity & Recent Activity -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h5 class="stat-card-title">Hourly Activity Pattern (6 AM - 6 PM)</h5>
                            <div class="stat-icon">
                                <i class="bi bi-clock-fill"></i>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="hourlyBarChart"></canvas>
                        </div>
                        <div class="insight-box">
                            <div class="insight-title">
                                <i class="bi bi-info-circle-fill"></i>
                                Peak Hours
                            </div>
                            <div class="insight-text">
                                <?php
                                $peak_hours = [];
                                $threshold = $peak_hour_count * 0.8; // 80% of peak
                                
                                for ($h = 6; $h <= 18; $h++) {
                                    if ($hourly_activity[$h] >= $threshold) {
                                        $peak_hours[] = date('g A', strtotime("$h:00"));
                                    }
                                }

                                if (count($peak_hours) > 0) {
                                    echo "Peak sorting activity occurs at " . implode(', ', $peak_hours) . 
                                         ". Consider focusing maintenance outside these hours.";
                                } else {
                                    echo "No significant peak hours detected during operating hours.";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="stat-card">
                        <div class="stat-card-header">
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
                                        <div class="activity-type">
                                            <?php 
                                            $type = ucfirst($activity['trash_type']);
                                            echo htmlspecialchars($type); 
                                            if ($activity['is_maintenance']) {
                                                echo ' <span class="maintenance-badge">Maintenance</span>';
                                            }
                                            ?>
                                        </div>
                                        <div class="activity-time">
                                            <?php 
                                            $sorted_time = new DateTime($activity['sorted_at']);
                                            $now = new DateTime();
                                            $interval = $now->diff($sorted_time);
                                            
                                            if ($interval->days == 0) {
                                                if ($interval->h == 0) {
                                                    echo $interval->i . ' minutes ago';
                                                } else {
                                                    echo $interval->h . ' hours ago';
                                                }
                                            } else if ($interval->days == 1) {
                                                echo 'Yesterday';
                                            } else {
                                                echo $sorted_time->format('M j, Y');
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3 text-center border-top pt-3">
                            <a href="#" class="see-all-link d-inline-flex">
                                See All Device Activity log
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>

    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#6b7280';


    const pieCtx = document.getElementById('totalPieChart').getContext('2d');
    const totalPieChart = new Chart(pieCtx, {
        type: 'pie',
        data: {
            labels: ['Biodegradable', 'Non-Biodegradable', 'Hazardous', 'Mixed'],
            datasets: [{
                data: [<?php echo "$total_bio, $total_nbio, $total_hazardous, $total_mixed"; ?>],
                backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#6b7280'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        font: { size: 12 }
                    }
                }
            }
        }
    });

    // LINE CHART
    const lineCtx = document.getElementById('trendLineChart').getContext('2d');
    const trendLineChart = new Chart(lineCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_map(function($date) {
                return date('M j', strtotime($date));
            }, $dates)); ?>,
            datasets: [{
                label: 'Biodegradable',
                data: <?php echo json_encode(array_map(fn($date) => $bio_counts[$date] ?? 0, $dates)); ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Non-Biodegradable',
                data: <?php echo json_encode(array_map(fn($date) => $nbio_counts[$date] ?? 0, $dates)); ?>,
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Hazardous',
                data: <?php echo json_encode(array_map(fn($date) => $hazardous_counts[$date] ?? 0, $dates)); ?>,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Mixed',
                data: <?php echo json_encode(array_map(fn($date) => $mixed_counts[$date] ?? 0, $dates)); ?>,
                borderColor: '#6b7280',
                backgroundColor: 'rgba(107, 114, 128, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        font: { size: 12 }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f3f4f6' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

    // BAR CHART — Maintenance Mode
    const maintenanceBarChart = new Chart(document.getElementById('maintenanceBarChart'), {
        type: 'bar',
        data: {
            labels: ['Biodegradable', 'Non-Biodegradable', 'Hazardous', 'Mixed'],
            datasets: [{
                label: 'Normal Operation',
                data: [0, 0, 0, 0],
                backgroundColor: '#10b981',
                borderRadius: 6
            }, {
                label: 'Maintenance Mode',
                data: [0, 0, 0, 0],
                backgroundColor: '#f59e0b',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        font: { size: 12 }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, stacked: true, grid: { color: '#f3f4f6' } },
                x: { stacked: true, grid: { display: false } }
            }
        }
    });

    // Update only the BAR CHART dynamically (since pie/line are PHP-preloaded)
    function updateCharts() {
        const queryParams = new URLSearchParams(window.location.search);
        const deviceId = queryParams.get('device');
        const deviceIdentity = queryParams.get('identity');

        const url = 'gs_DB/get_statistics_data.php?device=' + deviceId + '&identity=' + deviceIdentity;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                // Update Bar Chart
                maintenanceBarChart.data.datasets[0].data = [
                    data.maintenance.normal.bio,
                    data.maintenance.normal.nbio,
                    data.maintenance.normal.hazardous,
                    data.maintenance.normal.mixed
                ];
                maintenanceBarChart.data.datasets[1].data = [
                    data.maintenance.maintenance.bio,
                    data.maintenance.maintenance.nbio,
                    data.maintenance.maintenance.hazardous,
                    data.maintenance.maintenance.mixed
                ];
                maintenanceBarChart.update();
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    // Initial and periodic update for maintenance chart
    updateCharts();
    setInterval(updateCharts, 1000);

    function exportReport() {
        try {
            // Create a new workbook
            const wb = XLSX.utils.book_new();
            
            // Get device info and time range
            const deviceName = document.querySelector('.device-badge').textContent.trim();
            const timeRange = document.getElementById('timeFilter').options[document.getElementById('timeFilter').selectedIndex].text;
            
            // Quick Stats worksheet
            const quickStats = [
                ['GoSort Analytics Report', ''],
                ['Device', deviceName],
                ['Time Range', timeRange],
                ['', ''],
                ['Quick Statistics', ''],
                ['Category', 'Count'],
                ['Total Sorted', document.getElementById('totalSorted').textContent],
                ['Biodegradable', document.getElementById('totalBio').textContent],
                ['Non-Biodegradable', document.getElementById('totalNbio').textContent],
                ['Hazardous', document.getElementById('totalHazardous').textContent],
                ['Mixed', document.getElementById('totalMixed').textContent]
            ];
            const wsQuickStats = XLSX.utils.aoa_to_sheet(quickStats);
            XLSX.utils.book_append_sheet(wb, wsQuickStats, 'Quick Stats');
            
            // Performance Metrics worksheet
            const metrics = document.querySelectorAll('.metric-grid .metric-item');
            const perfMetrics = [
                ['Performance Metrics', ''],
                ['Metric', 'Value'],
                ['Average per Day', metrics[0].querySelector('.metric-value').textContent],
                ['Items per Hour', metrics[1].querySelector('.metric-value').textContent],
                ['Peak Hour', metrics[2].querySelector('.metric-value').textContent],
                ['Recycling Rate', metrics[3].querySelector('.metric-value').textContent]
            ];
            const wsPerfMetrics = XLSX.utils.aoa_to_sheet(perfMetrics);
            XLSX.utils.book_append_sheet(wb, wsPerfMetrics, 'Performance');

            // Environmental Impact worksheet
            const envMetrics = document.querySelectorAll('.col-12.px-0:last-child .metric-item');
            const envImpact = [
                ['Environmental Impact', ''],
                ['Metric', 'Value'],
                ['CO₂ Emissions Saved', envMetrics[0].querySelector('.metric-value').textContent],
                ['Total Weight Processed', envMetrics[1].querySelector('.metric-value').textContent]
            ];
            const wsEnvImpact = XLSX.utils.aoa_to_sheet(envImpact);
            XLSX.utils.book_append_sheet(wb, wsEnvImpact, 'Environmental');

            // Charts Data
            // Pie Chart Data
            const pieData = [
                ['Waste Distribution', ''],
                ['Category', 'Count'],
                ...totalPieChart.data.labels.map((label, i) => [
                    label,
                    totalPieChart.data.datasets[0].data[i]
                ])
            ];
            const wsPieData = XLSX.utils.aoa_to_sheet(pieData);
            XLSX.utils.book_append_sheet(wb, wsPieData, 'Waste Distribution');

            // Line Chart Data
            const lineData = [
                ['Daily Sorting Trend', ''],
                ['Date', ...trendLineChart.data.datasets.map(d => d.label)],
                ...trendLineChart.data.labels.map((label, i) => [
                    label,
                    ...trendLineChart.data.datasets.map(d => d.data[i])
                ])
            ];
            const wsLineData = XLSX.utils.aoa_to_sheet(lineData);
            XLSX.utils.book_append_sheet(wb, wsLineData, 'Daily Trend');

            // Recent Activity
            const recentActivity = [
                ['Recent Activity Log', ''],
                ['Type', 'Time'],
                ...Array.from(document.querySelectorAll('.activity-item')).map(item => [
                    item.querySelector('.activity-type').textContent.trim(),
                    item.querySelector('.activity-time').textContent.trim()
                ])
            ];
            const wsRecentActivity = XLSX.utils.aoa_to_sheet(recentActivity);
            XLSX.utils.book_append_sheet(wb, wsRecentActivity, 'Recent Activity');

            // Generate filename with device name and date
            const date = new Date().toISOString().split('T')[0];
            const sanitizedDeviceName = deviceName.replace(/[^a-z0-9]/gi, '_');
            const fileName = `GoSort_Analytics_${sanitizedDeviceName}_${date}.xlsx`;

            // Save the file
            XLSX.writeFile(wb, fileName);
        } catch (error) {
            console.error('Error during export:', error);
            alert('An error occurred while exporting the report. Please try again.');
        }
    }
</script>

<script src="js/bootstrap.bundle.min.js"></script>

</body>
</html>