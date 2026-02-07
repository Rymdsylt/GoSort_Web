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

// Fetch all sorters with analytics data
$sort = $_GET['sort'] ?? 'recent'; // default to recently added

$query = "
    WITH DeviceMetrics AS (
        SELECT 
            s.id,
            s.device_name,
            s.device_identity,
            s.status,
            COUNT(sh.id) as total_items,
            COALESCE(
                COUNT(DISTINCT DATE(sh.sorted_at)), 
                0
            ) as active_days,
            MAX(sh.sorted_at) as last_sort_time,
            COALESCE(
                SUM(CASE 
                    WHEN DATE(sh.sorted_at) = CURDATE() 
                    THEN 1 
                    ELSE 0 
                END),
                0
            ) as today_count,
            CASE 
                WHEN COUNT(DISTINCT DATE(sh.sorted_at)) > 0 
                THEN ROUND(COUNT(sh.id) / COUNT(DISTINCT DATE(sh.sorted_at)), 1)
                ELSE 0 
            END as items_per_day,
            s.created_at
        FROM sorters s
        LEFT JOIN sorting_history sh ON s.device_identity = sh.device_identity
        GROUP BY s.id, s.device_name, s.device_identity, s.status, s.created_at
    )
    SELECT * FROM DeviceMetrics
";

switch ($sort) {
    case 'name':
        $query .= " ORDER BY device_name ASC";
        break;
    case 'active':
        $query .= " ORDER BY COALESCE(last_sort_time, '1970-01-01') DESC";
        break;
    case 'recent':
    default:
        $query .= " ORDER BY created_at DESC";
        break;
}

$stmt = $pdo->query($query);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentSortLabel = match($sort) {
    'name' => 'Name (A-Z)',
    'active' => 'Last Active',
    default => 'Recently Added',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Analytics Overview</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #274a17ff;
            --light-green: #7AF146;
            --dark-gray: #1f2937;
            --medium-gray: #6b7280;
            --light-gray: #f3f4f6;
            --border-color: #368137;
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
            padding: 1rem 0;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin-left: 6px;
        }

        .analytics-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }



        .device-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .device-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .device-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--light-green), var(--primary-green));
        }

        .device-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .device-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 0;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .status-online {
            background-color: var(--light-green);
            color: var(--primary-green);
        }

        .status-offline {
            background-color: #fee2e2;
            color: #ef4444;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-online .status-indicator {
            background-color: var(--primary-green);
            box-shadow: 0 0 8px rgba(39, 74, 23, 0.6);
        }

        .status-offline .status-indicator {
            background-color: #ef4444;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }

        .metric-item {
            text-align: center;
            padding: 1rem;
            background: var(--light-gray);
            border-radius: 10px;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-green);
            margin: 0;
        }

        .metric-label {
            font-size: 0.75rem;
            color: var(--medium-gray);
            margin-top: 0.25rem;
        }

        .last-active {
            font-size: 0.875rem;
            color: var(--medium-gray);
            margin-top: 1rem;
            text-align: right;
        }

        .view-stats-btn {
            position: absolute;
            bottom: 1.5rem;
            right: 1.5rem;
            background: var(--primary-green);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }

        .device-card:hover .view-stats-btn {
            opacity: 1;
            transform: translateY(0);
        }

        .view-stats-btn:hover {
            background: #1e3a11;
        }

        /* Green compact toggle buttons - Exact copy from Sorters */
        .btn-outline-secondary, .btn-outline-secondary:focus, .btn-outline-secondary:active {
            border-color: #368137 !important;
            color: #368137 !important;
            background: #fff !important;
            box-shadow: none !important;
            padding: 0.5rem 1rem !important;
            max-height: 2.5rem;
        }
        .btn-check:checked + .btn-outline-secondary, .btn-outline-secondary.active {
            background: #368137 !important;
            color: #fff !important;
            border-color: #368137 !important;
        }
        .btn-outline-secondary:hover {
            background: #eafbe7 !important;
            color: #368137 !important;
            border-color: #368137 !important;
        }

        .sort-dropdown-wrapper {
            background: #fff;
            border: 1px solid #368137;
            border-radius: 6px;
            padding: 0.1rem 0.7rem 0.1rem 0.7rem;
            display: flex;
            align-items: center;
            box-shadow: none;
            margin: 0rem 1rem 0rem 0.2rem;
            max-height: 2.5rem;
        }

        .sort-dropdown-wrapper .btn-link,
        .sort-dropdown-wrapper .btn-link:focus {
            text-decoration: none !important;
        }

        .sort-dropdown-wrapper:hover {
            background: #efffe8ff !important;
        }

        /* Search styling */
        .input-group .form-control {
            border-color: #368137;
            border-radius: 6px 0 0 6px;
        }

        .input-group .form-control:focus {
            border-color: #368137;
            box-shadow: 0 0 0 0.25rem rgba(54, 129, 55, 0.25);
        }

        .input-group .btn {
            border-radius: 0 6px 6px 0;
        }

        @media (max-width: 992px) {
            #main-content-wrapper {
                margin-left: 0;
                padding: 12px;
            }

            .analytics-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .metrics-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="fw-bold mb-0 mt-3">Analytics Overview</h2>
                <!-- Search bar -->
                <div class="input-group mt-3" style="max-width: 300px;">
                    <input type="text" id="searchDevice" class="form-control" placeholder="Search Device">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>

            <hr style="height: 1.5px; background-color: #000; opacity: 1; margin-left:6.5px;" class="mb-2">

            <div class="d-flex mb-2">
                <div class="sort-dropdown-wrapper">
                    <div class="dropdown">
                        <button class="btn btn-link text-dark dropdown-toggle px-0" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            Sort by
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end custom-drive-dropdown" aria-labelledby="sortDropdown">
                            <li><a class="dropdown-item<?= $sort === 'recent' ? ' active-sort' : '' ?>" href="?sort=recent">Recently Added</a></li>
                            <li><a class="dropdown-item<?= $sort === 'name' ? ' active-sort' : '' ?>" href="?sort=name">Name (A-Z)</a></li>
                            <li><a class="dropdown-item<?= $sort === 'active' ? ' active-sort' : '' ?>" href="?sort=active">Last Active</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="row">
                <?php foreach ($devices as $device): 
                    $lastActiveTime = $device['last_sort_time'] ? strtotime($device['last_sort_time']) : null;
                    $isOnline = $device['status'] === 'online';
                    $activeTimeAgo = $lastActiveTime ? getTimeAgo($lastActiveTime) : 'Never active';
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="device-card" data-device="<?php echo htmlspecialchars($device['device_name']); ?>" onclick="window.location.href='GoSort_Statistics.php?device=<?php echo $device['id']; ?>&identity=<?php echo $device['device_identity']; ?>'">
                        <div class="device-header">
                            <h3 class="device-name">
                                <?php echo htmlspecialchars($device['device_name']); ?>
                            </h3>
                            <div class="status-badge <?php echo $isOnline ? 'status-online' : 'status-offline'; ?>">
                                <span class="status-indicator"></span>
                                <?php echo $isOnline ? 'Online' : 'Offline'; ?>
                            </div>
                        </div>

                        <div class="metrics-grid">
                            <div class="metric-item">
                                <div class="metric-value"><?php echo number_format($device['today_count']); ?></div>
                                <div class="metric-label">Today</div>
                            </div>
                            <div class="metric-item">
                                <div class="metric-value"><?php echo number_format($device['total_items']); ?></div>
                                <div class="metric-label">Total Items</div>
                            </div>
                            <div class="metric-item">
                                <div class="metric-value"><?php echo number_format($device['items_per_day']); ?></div>
                                <div class="metric-label">Items/Day</div>
                            </div>
                        </div>

                        <div class="last-active">
                            Last active: <?php echo $activeTimeAgo; ?>
                        </div>

                        <button class="view-stats-btn">
                            <i class="bi bi-graph-up"></i>
                            View Statistics
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality - Exact copy from Sorters
        document.getElementById('searchDevice').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase().trim();
            document.querySelectorAll('[data-device]').forEach(col => {
                const deviceName = col.getAttribute('data-device').toLowerCase();
                col.style.display = deviceName.includes(searchValue) ? '' : 'none';
            });
        });

        // Time ago function
        function getTimeAgo(timestamp) {
            const seconds = Math.floor((new Date() - timestamp * 1000) / 1000);
            
            const intervals = {
                year: 31536000,
                month: 2592000,
                week: 604800,
                day: 86400,
                hour: 3600,
                minute: 60,
                second: 1
            };
            
            for (const [unit, secondsInUnit] of Object.entries(intervals)) {
                const interval = Math.floor(seconds / secondsInUnit);
                
                if (interval >= 1) {
                    return interval === 1 ? `1 ${unit} ago` : `${interval} ${unit}s ago`;
                }
            }
            
            return 'Just now';
        }
    </script>
     <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
function getTimeAgo($timestamp) {
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return "Just now";
    }
    
    $intervals = [
        'year' => 31536000,
        'month' => 2592000,
        'week' => 604800,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60
    ];
    
    foreach ($intervals as $interval => $seconds) {
        $quotient = floor($difference / $seconds);
        if ($quotient >= 1) {
            return $quotient === 1 ? "1 $interval ago" : "$quotient {$interval}s ago";
        }
    }
    
    return "Just now";
}
?>
