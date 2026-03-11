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

$device_id       = $_GET['device']   ?? null;
$device_identity = $_GET['identity'] ?? null;

if (!$device_id || !$device_identity) {
    header("Location: GoSort_AnalyticsNavpage.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM sorters WHERE id = ? AND device_identity = ?");
$stmt->execute([$device_id, $device_identity]);
$device_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device_info) {
    header("Location: GoSort_AnalyticsNavpage.php");
    exit();
}

$default_filter = 'today';
if ($device_info['status'] === 'offline') {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sorting_history WHERE device_identity = ? AND DATE(sorted_at) = CURDATE()");
    $stmt->execute([$device_identity]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) $default_filter = 'all_time';
}

$time_filter = $_GET['filter'] ?? $default_filter;
$params = [$device_identity];

$date_condition = match($time_filter) {
    'today'      => "AND DATE(sorted_at) = CURDATE()",
    'yesterday'  => "AND DATE(sorted_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
    'last_7_days'=> "AND sorted_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
    'this_month' => "AND MONTH(sorted_at) = MONTH(CURDATE()) AND YEAR(sorted_at) = YEAR(CURDATE())",
    default      => "",
};

$stmt = $pdo->prepare("
    SELECT trash_type, COUNT(*) as count, is_maintenance,
           DATE(sorted_at) as date, HOUR(sorted_at) as hour
    FROM sorting_history
    WHERE device_identity = ? $date_condition
    GROUP BY trash_type, is_maintenance, DATE(sorted_at), HOUR(sorted_at)
    ORDER BY date ASC, hour ASC
");
$stmt->execute($params);
$sorting_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dates = []; $bio_counts = []; $nbio_counts = []; $hazardous_counts = []; $mixed_counts = [];
$hourly_activity = array_fill(0, 24, 0);
$total_items_processed = 0;

foreach ($sorting_data as $record) {
    $date = $record['date'];
    if (!in_array($date, $dates)) $dates[] = $date;
    $count = intval($record['count']);
    $type  = $record['trash_type'];
    $hour  = intval($record['hour']);
    $total_items_processed += $count;
    $hourly_activity[$hour] += $count;
    if (!$record['is_maintenance']) {
        switch ($type) {
            case 'bio':       $bio_counts[$date]       = ($bio_counts[$date] ?? 0) + $count; break;
            case 'nbio':      $nbio_counts[$date]      = ($nbio_counts[$date] ?? 0) + $count; break;
            case 'hazardous': $hazardous_counts[$date] = ($hazardous_counts[$date] ?? 0) + $count; break;
            case 'mixed':     $mixed_counts[$date]     = ($mixed_counts[$date] ?? 0) + $count; break;
        }
    }
}

$total_bio       = array_sum($bio_counts);
$total_nbio      = array_sum($nbio_counts);
$total_hazardous = array_sum($hazardous_counts);
$total_mixed     = array_sum($mixed_counts);
$total_sorted    = $total_bio + $total_nbio + $total_hazardous + $total_mixed;

$operating_days  = count($dates) > 0 ? count($dates) : 1;
$avg_per_day     = $total_sorted / $operating_days;
$peak_hour       = array_keys($hourly_activity, max($hourly_activity))[0];
$peak_hour_count = max($hourly_activity);

$operating_hours_total = 0;
for ($h = 6; $h <= 18; $h++) $operating_hours_total += $hourly_activity[$h];
$items_per_hour   = ($operating_days * 12) > 0 ? $operating_hours_total / ($operating_days * 12) : 0;
$recyclable_items = $total_bio + $total_nbio;
$total_weight     = $total_sorted * 0.3;
$co2_saved        = $recyclable_items * 0.3 * 0.5;
$recycling_rate   = $total_sorted > 0 ? ($recyclable_items / $total_sorted * 100) : 0;

$recent_stmt = $pdo->prepare("
    SELECT trash_type, sorted_at, is_maintenance 
    FROM sorting_history WHERE device_identity = ? ORDER BY sorted_at DESC LIMIT 10
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
    <link href="css/dark-mode-global.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="js/theme-manager.js"></script>
    <script src="js/chart.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
    <style>
        :root {
            --primary-green:   #274a17;
            --light-green:     #7AF146;
            --mid-green:       #368137;
            --dark-gray:       #1f2937;
            --medium-gray:     #6b7280;
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
            padding: 100px 0 20px 0;
            height: 100vh;
            overflow-y: auto;
        }

        /* ── Section wrappers ── */
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

        /* ── Page header ── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .page-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 0;
        }
        .page-header-right {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        /* ── Device badge ── */
        .device-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #e8f5e1;
            color: var(--primary-green);
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .device-badge .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
        }
        .device-badge .dot.online  { background: #15803d; box-shadow: 0 0 6px rgba(21,128,61,0.5); }
        .device-badge .dot.offline { background: #dc2626; }

        /* ── Filter select ── */
        .filter-select {
            background: #fff;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            padding: 0.45rem 2rem 0.45rem 0.75rem;
            font-size: 0.82rem;
            font-family: 'Poppins', sans-serif;
            color: var(--dark-gray);
            outline: none;
            cursor: pointer;
            transition: border-color 0.2s;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b7280' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.65rem center;
        }
        .filter-select:focus { border-color: var(--mid-green); }

        /* ── Export button ── */
        .btn-export {
            background: linear-gradient(135deg, var(--mid-green) 0%, var(--primary-green) 100%);
            border: none;
            border-radius: 8px;
            padding: 0.47rem 1rem;
            font-size: 0.82rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(39,74,23,0.15);
        }
        .btn-export:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(39,74,23,0.25);
        }

        /* ── Back link ── */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--medium-gray);
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--primary-green); }

        /* ── Quick stat boxes ── */
        .quick-stat-box {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem 1.1rem;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            transition: all 0.2s;
            height: 110px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .quick-stat-box:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .quick-stat-box::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 3px;
            border-radius: 0 3px 3px 0;
            background: linear-gradient(to bottom, var(--light-green), var(--primary-green));
        }
        .quick-stat-label { font-size: 0.72rem; font-weight: 600; color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem; }
        .quick-stat-value { font-size: 1.8rem; font-weight: 700; line-height: 1; }
        .quick-stat-sub   { font-size: 0.7rem; color: var(--medium-gray); margin-top: 0.3rem; }

        /* ── White inner card ── */
        .inner-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: var(--card-shadow);
            height: 100%;
        }
        .inner-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .inner-card-title {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 0;
        }
        .inner-card-icon {
            width: 32px; height: 32px;
            background: #e8f5e1;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: var(--primary-green);
            font-size: 0.9rem;
        }

        /* ── Metric grid inside cards ── */
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.6rem;
        }
        .metric-item {
            background: #f9fafb;
            border-radius: 8px;
            padding: 0.75rem 0.6rem;
            text-align: center;
        }
        .metric-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-green);
            line-height: 1;
            margin-bottom: 0.2rem;
        }
        .metric-label {
            font-size: 0.67rem;
            color: var(--medium-gray);
            font-weight: 500;
        }

        /* ── Insight box ── */
        .insight-box {
            background: #f0fdf4;
            border-left: 3px solid var(--primary-green);
            border-radius: 8px;
            padding: 0.85rem 1rem;
            margin-top: 0.85rem;
            font-size: 0.78rem;
            color: var(--dark-gray);
            line-height: 1.6;
        }
        .insight-box-title {
            font-weight: 700;
            color: var(--primary-green);
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.78rem;
        }

        /* ── Chart container ── */
        .chart-wrap {
            position: relative;
            height: 260px;
        }

        /* ── Activity log ── */
        .activity-log { max-height: 300px; overflow-y: auto; }
        .activity-log::-webkit-scrollbar { width: 4px; }
        .activity-log::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 0.25rem;
            border-bottom: 1px solid #f3f4f6;
            transition: background 0.15s;
            border-radius: 6px;
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-item:hover { background: #f9fafb; }

        .activity-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .activity-icon.bio        { background: rgba(16,185,129,0.1); color: var(--bio-color); }
        .activity-icon.nbio       { background: rgba(239,68,68,0.1);  color: var(--nbio-color); }
        .activity-icon.hazardous  { background: rgba(245,158,11,0.1); color: var(--hazardous-color); }
        .activity-icon.mixed      { background: rgba(107,114,128,0.1);color: var(--mixed-color); }

        .activity-type { font-size: 0.8rem; font-weight: 600; color: var(--dark-gray); }
        .activity-time { font-size: 0.7rem; color: var(--medium-gray); }
        .maintenance-tag {
            background: rgba(245,158,11,0.12);
            color: var(--hazardous-color);
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 600;
            margin-left: 0.3rem;
        }

        @media (max-width: 992px) {
            #main-content-wrapper { margin-left: 0; padding: 12px; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container-fluid">

            <?php include 'topbar.php'; ?>

            <div class="section-container">

                <!-- Page header -->
                <div class="page-header">
                    <div class="d-flex align-items-center gap-3">
                        <a href="GoSort_AnalyticsNavpage.php" class="back-link">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                        <div class="device-badge">
                            <span class="dot <?php echo $device_info['status']; ?>"></span>
                            <i class="bi bi-cpu-fill"></i>
                            <?php echo htmlspecialchars($device_info['device_name']); ?>
                        </div>
                    </div>
                    <div class="page-header-right">
                        <select class="filter-select" id="timeFilter" onchange="changeFilter()">
                            <option value="today"    <?= $time_filter === 'today'    ? 'selected' : '' ?>>Today</option>
                            <option value="all_time" <?= $time_filter === 'all_time' ? 'selected' : '' ?>>All Time</option>
                        </select>
                        <button class="btn-export" onclick="exportReport()">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Quick stats -->
                <div class="section-block">
                    <div class="section-label">Summary</div>
                    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3">
                        <div class="col">
                            <div class="quick-stat-box">
                                <div class="quick-stat-label">Total Sorted</div>
                                <div class="quick-stat-value" id="totalSorted"><?php echo number_format($total_sorted); ?></div>
                                <div class="quick-stat-sub">↑ Live updating</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="quick-stat-box">
                                <div class="quick-stat-label">Biodegradable</div>
                                <div class="quick-stat-value" style="color:var(--bio-color);" id="totalBio"><?php echo number_format($total_bio); ?></div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="quick-stat-box">
                                <div class="quick-stat-label">Non-Biodegradable</div>
                                <div class="quick-stat-value" style="color:var(--nbio-color);" id="totalNbio"><?php echo number_format($total_nbio); ?></div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="quick-stat-box">
                                <div class="quick-stat-label">Hazardous</div>
                                <div class="quick-stat-value" style="color:var(--hazardous-color);" id="totalHazardous"><?php echo number_format($total_hazardous); ?></div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="quick-stat-box">
                                <div class="quick-stat-label">Mixed</div>
                                <div class="quick-stat-value" style="color:var(--mixed-color);" id="totalMixed"><?php echo number_format($total_mixed); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts row -->
                <div class="section-block">
                    <div class="section-label">Charts</div>
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <div class="inner-card">
                                <div class="inner-card-header">
                                    <span class="inner-card-title">Waste Distribution</span>
                                    <div class="inner-card-icon"><i class="bi bi-pie-chart-fill"></i></div>
                                </div>
                                <div class="chart-wrap">
                                    <canvas id="totalPieChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="inner-card">
                                <div class="inner-card-header">
                                    <span class="inner-card-title">Daily Sorting Trend</span>
                                    <div class="inner-card-icon"><i class="bi bi-graph-up"></i></div>
                                </div>
                                <div class="chart-wrap">
                                    <canvas id="trendLineChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hourly activity -->
                <div class="section-block">
                    <div class="section-label">Hourly Activity</div>
                    <div class="inner-card">
                        <div class="inner-card-header">
                            <span class="inner-card-title">Activity Pattern (6 AM – 6 PM)</span>
                            <div class="inner-card-icon"><i class="bi bi-clock-fill"></i></div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="hourlyBarChart"></canvas>
                        </div>
                        <div class="insight-box">
                            <div class="insight-box-title"><i class="bi bi-info-circle-fill"></i> Peak Hours</div>
                            <?php
                            $peak_hours = [];
                            $threshold  = $peak_hour_count * 0.8;
                            for ($h = 6; $h <= 18; $h++) {
                                if ($hourly_activity[$h] >= $threshold && $hourly_activity[$h] > 0)
                                    $peak_hours[] = date('g A', strtotime("$h:00"));
                            }
                            echo count($peak_hours) > 0
                                ? "Peak sorting activity at " . implode(', ', $peak_hours) . ". Consider scheduling maintenance outside these hours."
                                : "No significant peak hours detected during operating hours.";
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Performance + Environmental + Recent Activity -->
                <div class="section-block">
                    <div class="section-label">Performance & Activity</div>
                    <div class="row g-3">

                        <!-- Performance Metrics -->
                        <div class="col-lg-4">
                            <div class="inner-card">
                                <div class="inner-card-header">
                                    <span class="inner-card-title">Performance Metrics</span>
                                    <div class="inner-card-icon"><i class="bi bi-speedometer2"></i></div>
                                </div>
                                <div class="metric-grid">
                                    <div class="metric-item">
                                        <div class="metric-value"><?php echo number_format($avg_per_day, 1); ?></div>
                                        <div class="metric-label">Avg / Day</div>
                                    </div>
                                    <div class="metric-item">
                                        <div class="metric-value"><?php echo number_format($items_per_hour, 1); ?></div>
                                        <div class="metric-label">Items / Hour</div>
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
                                    <div class="insight-box-title"><i class="bi bi-lightbulb-fill"></i> Insight</div>
                                    <?php
                                    $morning = 0; $afternoon = 0;
                                    for ($h = 6; $h < 12; $h++)  $morning   += $hourly_activity[$h];
                                    for ($h = 12; $h <= 18; $h++) $afternoon += $hourly_activity[$h];
                                    if ($morning > $afternoon && $morning > 0)
                                        echo "More active in the <strong>morning</strong> (6 AM–12 PM) with " . number_format($morning) . " items.";
                                    elseif ($afternoon > $morning && $afternoon > 0)
                                        echo "More active in the <strong>afternoon</strong> (12 PM–6 PM) with " . number_format($afternoon) . " items.";
                                    elseif ($morning === $afternoon && $morning > 0)
                                        echo "Evenly distributed between morning and afternoon.";
                                    else
                                        echo "No activity recorded during operating hours.";
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Environmental Impact -->
                        <div class="col-lg-4">
                            <div class="inner-card">
                                <div class="inner-card-header">
                                    <span class="inner-card-title">Environmental Impact</span>
                                    <div class="inner-card-icon"><i class="bi bi-globe"></i></div>
                                </div>
                                <div class="metric-grid">
                                    <div class="metric-item" style="grid-column: span 2;">
                                        <div class="metric-value"><?php echo number_format($co2_saved, 1); ?> kg</div>
                                        <div class="metric-label">CO₂ Emissions Saved</div>
                                    </div>
                                    <div class="metric-item" style="grid-column: span 2;">
                                        <div class="metric-value"><?php echo number_format($total_weight, 1); ?> kg</div>
                                        <div class="metric-label">Total Weight Processed</div>
                                    </div>
                                </div>
                                <div class="insight-box">
                                    <div class="insight-box-title"><i class="bi bi-tree-fill"></i> Impact</div>
                                    Proper sorting saved ~<strong><?php echo number_format($co2_saved, 1); ?> kg CO₂</strong>,
                                    equivalent to planting <strong><?php echo ceil($co2_saved / 21); ?> trees</strong>!
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="col-lg-4">
                            <div class="inner-card">
                                <div class="inner-card-header">
                                    <span class="inner-card-title">Recent Activity</span>
                                    <div class="inner-card-icon"><i class="bi bi-activity"></i></div>
                                </div>
                                <div class="activity-log">
                                    <?php foreach ($recent_activities as $activity):
                                        $tz  = new DateTimeZone('Asia/Manila');
                                        $at  = new DateTime($activity['sorted_at'], $tz);
                                        $now = new DateTime('now', $tz);
                                        $diff = $now->diff($at);
                                        if ($diff->days == 0) {
                                            $timeAgo = $diff->h == 0 ? $diff->i . ' min ago' : $diff->h . ' hr ago';
                                        } elseif ($diff->days == 1) {
                                            $timeAgo = 'Yesterday';
                                        } else {
                                            $timeAgo = $at->format('M j, Y');
                                        }
                                        $binLabel = match($activity['trash_type']) {
                                            'bio'       => 'Biodegradable Bin',
                                            'nbio'      => 'Non-Biodegradable Bin',
                                            'hazardous' => 'Hazardous Bin',
                                            'mixed'     => 'Mixed Waste Bin',
                                            default     => ucfirst($activity['trash_type']) . ' Bin',
                                        };
                                    ?>
                                    <div class="activity-item">
                                        <div class="activity-icon <?php echo htmlspecialchars($activity['trash_type']); ?>">
                                            <i class="bi bi-trash"></i>
                                        </div>
                                        <div style="flex:1; min-width:0;">
                                            <div class="activity-type">
                                                Item sorted into <strong><?php echo $binLabel; ?></strong>
                                                <?php if ($activity['is_maintenance']): ?>
                                                    <span class="maintenance-tag">Maintenance</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="activity-time"><?php echo $timeAgo; ?></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (count($recent_activities) === 0): ?>
                                        <div style="text-align:center; padding:1.5rem; color:var(--medium-gray); font-size:0.8rem;">
                                            No recent activity recorded.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div><!-- /section-container -->
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
    Chart.defaults.font.family = "'Poppins', sans-serif";
    Chart.defaults.color = '#6b7280';

    // Pie chart
    new Chart(document.getElementById('totalPieChart').getContext('2d'), {
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
                    labels: { padding: 12, usePointStyle: true, font: { size: 11 } }
                }
            }
        }
    });

    // Line chart
    const trendLineChart = new Chart(document.getElementById('trendLineChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_map(fn($d) => date('M j', strtotime($d)), $dates)); ?>,
            datasets: [
                { label: 'Biodegradable',     data: <?php echo json_encode(array_map(fn($d) => $bio_counts[$d]       ?? 0, $dates)); ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)',  tension: 0.4, fill: true },
                { label: 'Non-Biodegradable', data: <?php echo json_encode(array_map(fn($d) => $nbio_counts[$d]      ?? 0, $dates)); ?>, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.08)',   tension: 0.4, fill: true },
                { label: 'Hazardous',         data: <?php echo json_encode(array_map(fn($d) => $hazardous_counts[$d] ?? 0, $dates)); ?>, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.08)', tension: 0.4, fill: true },
                { label: 'Mixed',             data: <?php echo json_encode(array_map(fn($d) => $mixed_counts[$d]     ?? 0, $dates)); ?>, borderColor: '#6b7280', backgroundColor: 'rgba(107,114,128,0.08)',tension: 0.4, fill: true }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, font: { size: 11 } } } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Hourly bar chart
    const hourlyLabels = [];
    const hourlyData   = [];
    <?php for ($h = 6; $h <= 18; $h++): ?>
        hourlyLabels.push('<?php echo date('g A', strtotime("$h:00")); ?>');
        hourlyData.push(<?php echo $hourly_activity[$h]; ?>);
    <?php endfor; ?>

    new Chart(document.getElementById('hourlyBarChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: hourlyLabels,
            datasets: [{
                label: 'Items Sorted',
                data: hourlyData,
                backgroundColor: 'rgba(39,74,23,0.15)',
                borderColor: '#368137',
                borderWidth: 1.5,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
                x: { grid: { display: false } }
            }
        }
    });

    function changeFilter() {
        const filter = document.getElementById('timeFilter').value;
        const params = new URLSearchParams(window.location.search);
        params.set('filter', filter);
        window.location.href = '?' + params.toString();
    }

    function exportReport() {
        try {
            const wb = XLSX.utils.book_new();
            const deviceName = '<?php echo htmlspecialchars($device_info['device_name']); ?>';
            const timeRange  = document.getElementById('timeFilter').options[document.getElementById('timeFilter').selectedIndex].text;

            // Sheet 1: Summary
            const ws1 = XLSX.utils.aoa_to_sheet([
                ['GoSort Analytics Report'],
                ['Device', deviceName],
                ['Time Range', timeRange],
                ['Exported On', new Date().toLocaleString()],
                [],
                ['Category', 'Count'],
                ['Total Sorted',       document.getElementById('totalSorted').textContent],
                ['Biodegradable',      document.getElementById('totalBio').textContent],
                ['Non-Biodegradable',  document.getElementById('totalNbio').textContent],
                ['Hazardous',          document.getElementById('totalHazardous').textContent],
                ['Mixed',              document.getElementById('totalMixed').textContent],
            ]);
            XLSX.utils.book_append_sheet(wb, ws1, 'Summary');

            // Sheet 2: Daily Breakdown (all dates from PHP)
            const dailyRows = [
                ['Daily Breakdown'],
                ['Date', 'Biodegradable', 'Non-Biodegradable', 'Hazardous', 'Mixed', 'Daily Total'],
            ];
            const dates      = <?php echo json_encode($dates); ?>;
            const bioData    = <?php echo json_encode(array_map(fn($d) => $bio_counts[$d]       ?? 0, $dates)); ?>;
            const nbioData   = <?php echo json_encode(array_map(fn($d) => $nbio_counts[$d]      ?? 0, $dates)); ?>;
            const hazData    = <?php echo json_encode(array_map(fn($d) => $hazardous_counts[$d] ?? 0, $dates)); ?>;
            const mixedData  = <?php echo json_encode(array_map(fn($d) => $mixed_counts[$d]     ?? 0, $dates)); ?>;
            dates.forEach((date, i) => {
                const total = bioData[i] + nbioData[i] + hazData[i] + mixedData[i];
                dailyRows.push([date, bioData[i], nbioData[i], hazData[i], mixedData[i], total]);
            });
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(dailyRows), 'Daily Breakdown');

            // Sheet 3: Performance & Environmental
            const ws3 = XLSX.utils.aoa_to_sheet([
                ['Performance Metrics'],
                ['Avg per Day',    '<?php echo number_format($avg_per_day, 1); ?>'],
                ['Items per Hour', '<?php echo number_format($items_per_hour, 1); ?>'],
                ['Peak Hour',      '<?php echo $peak_hour_count > 0 ? date('g A', strtotime("$peak_hour:00")) : "N/A"; ?>'],
                ['Recycling Rate', '<?php echo number_format($recycling_rate, 1); ?>%'],
                [],
                ['Environmental Impact'],
                ['CO2 Saved (kg)',     '<?php echo number_format($co2_saved, 1); ?>'],
                ['Total Weight (kg)',  '<?php echo number_format($total_weight, 1); ?>'],
            ]);
            XLSX.utils.book_append_sheet(wb, ws3, 'Metrics');

            // Sheet 4: Hourly Activity
            const hourlyRows = [['Hourly Activity'], ['Hour', 'Items Sorted']];
            <?php for ($h = 6; $h <= 18; $h++): ?>
                hourlyRows.push(['<?php echo date('g A', strtotime("$h:00")); ?>', <?php echo $hourly_activity[$h]; ?>]);
            <?php endfor; ?>
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(hourlyRows), 'Hourly Activity');

            const date = new Date().toISOString().split('T')[0];
            XLSX.writeFile(wb, `GoSort_Analytics_${deviceName.replace(/[^a-z0-9]/gi,'_')}_${timeRange.replace(/ /g,'_')}_${date}.xlsx`);
        } catch (e) {
            console.error(e);
            alert('Export failed. Please try again.');
        }
    }
    </script>
</body>
</html>