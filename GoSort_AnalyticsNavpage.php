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

$sort = $_GET['sort'] ?? 'recent';

$query = "
    WITH DeviceMetrics AS (
        SELECT 
            s.id,
            s.device_name,
            s.device_identity,
            s.status,
            s.location,
            COUNT(sh.id) as total_items,
            COALESCE(COUNT(DISTINCT DATE(sh.sorted_at)), 0) as active_days,
            MAX(sh.sorted_at) as last_sort_time,
            COALESCE(SUM(CASE WHEN DATE(sh.sorted_at) = CURDATE() THEN 1 ELSE 0 END), 0) as today_count,
            CASE 
                WHEN COUNT(DISTINCT DATE(sh.sorted_at)) > 0 
                THEN ROUND(COUNT(sh.id) / COUNT(DISTINCT DATE(sh.sorted_at)), 1)
                ELSE 0 
            END as items_per_day,
            s.created_at
        FROM sorters s
        LEFT JOIN sorting_history sh ON s.device_identity = sh.device_identity
        GROUP BY s.id, s.device_name, s.device_identity, s.status, s.location, s.created_at
    )
    SELECT * FROM DeviceMetrics
";

switch ($sort) {
    case 'name':   $query .= " ORDER BY device_name ASC"; break;
    case 'active': $query .= " ORDER BY COALESCE(last_sort_time, '1970-01-01') DESC"; break;
    case 'recent': default: $query .= " ORDER BY created_at DESC"; break;
}

$stmt = $pdo->query($query);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentSortLabel = match($sort) {
    'name'   => 'Name (A-Z)',
    'active' => 'Last Active',
    default  => 'Most Recent',
};

function getTimeAgo($timestamp) {
    $difference = time() - $timestamp;
    if ($difference < 60) return "Just now";
    $intervals = [
        'year' => 31536000, 'month' => 2592000, 'week' => 604800,
        'day' => 86400, 'hour' => 3600, 'minute' => 60
    ];
    foreach ($intervals as $interval => $seconds) {
        $quotient = floor($difference / $seconds);
        if ($quotient >= 1) return $quotient === 1 ? "1 $interval ago" : "$quotient {$interval}s ago";
    }
    return "Just now";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Analytics Overview</title>
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

        /* ── Search ── */
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
            left: 0.65rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--medium-gray);
            font-size: 0.8rem;
            pointer-events: none;
        }

        /* ── Sort dropdown ── */
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

        /* ── Analytics device card ── */
        .analytics-card {
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
            text-decoration: none;
            display: block;
            color: inherit;
        }
        .analytics-card:hover {
            box-shadow: 0 4px 14px rgba(0,0,0,0.09);
            transform: translateY(-2px);
            color: inherit;
            text-decoration: none;
        }
        .analytics-card::before {
            content: '';
            position: absolute;
            left: 0; top: 16px; bottom: 16px;
            width: 3px;
            border-radius: 0 3px 3px 0;
            background: linear-gradient(to bottom, var(--light-green), var(--primary-green));
        }

        .analytics-card-header {
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

        .analytics-device-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 0 0 0.25rem 0;
        }

        /* Status pill */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.68rem;
            font-weight: 600;
            padding: 0.18rem 0.55rem;
            border-radius: 20px;
        }
        .status-pill .dot {
            width: 5px; height: 5px;
            border-radius: 50%;
            background: currentColor;
        }
        .status-pill.online      { background: #dcfce7; color: #15803d; }
        .status-pill.offline     { background: #fee2e2; color: #dc2626; }
        .status-pill.maintenance { background: #dbeafe; color: #1d4ed8; }

        /* Metrics row */
        .analytics-metrics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin: 0.85rem 0;
        }
        .analytics-metric {
            background: #f9fafb;
            border-radius: 8px;
            padding: 0.6rem 0.5rem;
            text-align: center;
        }
        .analytics-metric-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-green);
            line-height: 1;
            margin-bottom: 0.2rem;
        }
        .analytics-metric-label {
            font-size: 0.65rem;
            color: var(--medium-gray);
            font-weight: 500;
        }

        /* Card footer */
        .analytics-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.7rem;
            border-top: 1px solid #f3f4f6;
            margin-top: 0.5rem;
        }
        .last-active-label {
            font-size: 0.7rem;
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .last-active-label i { font-size: 0.68rem; }
        .view-stats-link {
            font-size: 0.76rem;
            color: var(--primary-green);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .view-stats-link i { font-size: 0.76rem; }

        /* Empty state */
        .empty-state {
            padding: 3rem 0;
            text-align: center;
            color: var(--medium-gray);
            width: 100%;
        }
        .empty-state i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 0.5rem;
            color: #9ca3af;
        }
        .empty-state p { font-size: 0.88rem; margin: 0; }

        /* Device grid wrapper */
        #deviceWrapper {
            min-height: 400px;
            overflow: visible;
            padding-right: 4px;
        }

        @media (max-width: 992px) {
            #main-content-wrapper { margin-left: 0; padding: 12px; }
            .page-header { flex-direction: column; align-items: flex-start; }
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

                <!-- Page header -->
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
                                <li><a class="dropdown-item<?= $sort === 'recent' ? ' active-sort' : '' ?>" href="?sort=recent">Most Recent</a></li>
                                <li><a class="dropdown-item<?= $sort === 'name'   ? ' active-sort' : '' ?>" href="?sort=name">Name (A–Z)</a></li>
                                <li><a class="dropdown-item<?= $sort === 'active' ? ' active-sort' : '' ?>" href="?sort=active">Last Active</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Devices analytics grid -->
                <div class="section-block">
                    <div class="section-label">All Devices (<?php echo count($devices); ?>)</div>

                    <div id="deviceWrapper">
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3" id="gridContainer">

                            <?php foreach ($devices as $device):
                                $lastActiveTime = $device['last_sort_time'] ? strtotime($device['last_sort_time']) : null;
                                $activeTimeAgo  = $lastActiveTime ? getTimeAgo($lastActiveTime) : 'Never active';
                            ?>
                            <div class="col" data-device="<?php echo htmlspecialchars($device['device_name']); ?>">
                                <a class="analytics-card"
                                   href="GoSort_Statistics.php?device=<?php echo $device['id']; ?>&identity=<?php echo urlencode($device['device_identity']); ?>">

                                    <div class="analytics-card-header">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="device-icon-wrap">
                                                <i class="bi bi-cpu-fill"></i>
                                            </div>
                                            <div>
                                                <div class="analytics-device-name"><?php echo htmlspecialchars($device['device_name']); ?></div>
                                                <div class="status-pill <?php echo htmlspecialchars($device['status']); ?>">
                                                    <span class="dot"></span>
                                                    <?php echo ucfirst($device['status']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if (!empty($device['location'])): ?>
                                            <span style="font-size:0.7rem; color:#9ca3af;">
                                                <i class="bi bi-geo-alt-fill" style="font-size:0.68rem;"></i>
                                                <?php echo htmlspecialchars($device['location']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="analytics-metrics">
                                        <div class="analytics-metric">
                                            <div class="analytics-metric-value"><?php echo number_format($device['today_count']); ?></div>
                                            <div class="analytics-metric-label">Today</div>
                                        </div>
                                        <div class="analytics-metric">
                                            <div class="analytics-metric-value"><?php echo number_format($device['total_items']); ?></div>
                                            <div class="analytics-metric-label">Total Items</div>
                                        </div>
                                        <div class="analytics-metric">
                                            <div class="analytics-metric-value"><?php echo number_format($device['items_per_day']); ?></div>
                                            <div class="analytics-metric-label">Items/Day</div>
                                        </div>
                                    </div>

                                    <div class="analytics-card-footer">
                                        <span class="last-active-label">
                                            <i class="bi bi-clock"></i>
                                            <?php echo $activeTimeAgo; ?>
                                        </span>
                                        <span class="view-stats-link">
                                            <i class="bi bi-graph-up"></i> View Stats
                                        </span>
                                    </div>

                                </a>
                            </div>
                            <?php endforeach; ?>

                            <?php if (count($devices) === 0): ?>
                                <div class="empty-state" style="width:100%;">
                                    <i class="bi bi-graph-up"></i>
                                    <p>No devices registered yet.</p>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

            </div><!-- /section-container -->
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('searchDevice').addEventListener('keyup', function() {
            const val = this.value.toLowerCase().trim();
            document.querySelectorAll('[data-device]').forEach(col => {
                col.style.display = col.getAttribute('data-device').toLowerCase().includes(val) ? '' : 'none';
            });
        });
    </script>
</body>
</html>