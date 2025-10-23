<?php
session_start();
date_default_timezone_set('Asia/Manila');
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

// Check if current time is within operating hours (6 AM - 6 PM)
$currentHour = (int)date('G'); // 24-hour format
$isOperatingHours = ($currentHour >= 6 && $currentHour < 18);

// Fetch all devices
$sort = $_GET['sort'] ?? 'recent';

$orderBy = match($sort) {
    'name' => 'device_name ASC',
    'active' => 'last_active DESC',
    'recent' => 'created_at DESC',
    default => 'created_at DESC'
};

$query = "
    SELECT 
        s.id,
        s.device_name,
        s.device_identity,
        s.status,
        s.last_active,
        s.location,
        s.created_at
    FROM sorters s
    ORDER BY {$orderBy}
";

$stmt = $pdo->query($query);
$allDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sort devices based on selected criteria
$sortDevices = function($devices) use ($sort) {
    $sortedDevices = $devices;
    
    switch ($sort) {
        case 'name':
            usort($sortedDevices, function($a, $b) {
                return strcasecmp($a['device_name'], $b['device_name']);
            });
            break;
        case 'active':
            usort($sortedDevices, function($a, $b) {
                return strcmp($b['last_active'], $a['last_active']);
            });
            break;
        case 'recent':
        default:
            usort($sortedDevices, function($a, $b) {
                return strcmp($b['created_at'], $a['created_at']);
            });
            break;
    }
    
    return $sortedDevices;
};

$allDevices = $sortDevices($allDevices);

// Separate devices by status
$onlineDevices = array_filter($allDevices, function($device) {
    return $device['status'] === 'online';
});

$offlineDevices = array_filter($allDevices, function($device) {
    return $device['status'] !== 'online';
});

$onlineDevices = array_values($onlineDevices);
$offlineDevices = array_values($offlineDevices);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Bin Monitoring</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Root Variables */
        :root {
            --primary-green: #274a17ff;
            --light-green: #7AF146;
            --dark-gray: #1f2937;
            --medium-gray: #6b7280;
            --light-gray: #f3f4f6;
            --border-color: #368137;
        }

        /* General Styles */
        body {
            background-color: #F3F3EF !important;
            font-family: 'Inter', sans-serif !important;
            color: var(--dark-gray);
        }

        /* Layout */
        #main-content-wrapper {
            margin-left: 260px;
            transition: margin-left 0.3s ease;
            padding: 20px;
        }

        #main-content-wrapper.collapsed {
            margin-left: 80px;
        }

        /* Device Cards */
        .device-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
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

        /* Card Header and Status */
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
            background-color: var(--light-green);
            color: var(--primary-green);
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            background-color: var(--primary-green);
            box-shadow: 0 0 8px rgba(39, 74, 23, 0.6);
        }

        .status-badge.offline {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .status-badge.offline .status-indicator {
            background-color: #dc2626;
            box-shadow: 0 0 8px rgba(220, 38, 38, 0.6);
        }

        .device-info {
            margin-top: 1rem;
            font-size: 0.875rem;
            color: var(--medium-gray);
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .action-btn {
            flex: 1;
            border: 2px solid;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-weight: 600;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            cursor: pointer;
        }

        .action-btn.monitor-live {
            background: var(--primary-green);
            border-color: var(--primary-green);
            color: white;
        }

        .action-btn.monitor-live:hover {
            background: #1e3a11;
            border-color: #1e3a11;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 74, 23, 0.3);
        }

        .action-btn.review-logs {
            background: white;
            border-color: var(--border-color);
            color: var(--primary-green);
        }

        .action-btn.review-logs:hover:not(.disabled) {
            background: #efffe8ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(54, 129, 55, 0.2);
        }

        .action-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            position: relative;
        }

        .action-btn.disabled::after {
            position: absolute;
            bottom: -1.5rem;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.75rem;
            color: var(--medium-gray);
            white-space: nowrap;
        }

        .sort-dropdown-wrapper {
            background: #fff;
            border: 1px solid #368137;
            border-radius: 6px;
            padding: 0.1rem 0.7rem;
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

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-gray);
            margin: 2rem 0 1rem;
            padding-left: 1rem;
            padding: 0.5rem 0;
        }

        #searchDevice {
            max-width: 300px;
            border-color: var(--border-color);
        }

        #searchDevice:focus {
            box-shadow: 0 0 0 0.25rem rgba(54, 129, 55, 0.25);
        }

        .device-count {
            font-size: 0.875rem;
            color: var(--medium-gray);
            margin-left: 0.5rem;
        }

        .info-banner {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            border: 2px solid #0ea5e9;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: start;
            gap: 1rem;
        }

        .info-banner i {
            font-size: 1.5rem;
            color: #0284c7;
            margin-top: 0.2rem;
        }

        .info-banner .banner-text {
            flex: 1;
        }

        .info-banner h5 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            font-weight: 600;
            color: #075985;
        }

        .info-banner ul {
            margin: 0;
            padding-left: 1.25rem;
            font-size: 0.875rem;
            color: #0c4a6e;
        }

        .info-banner li {
            margin-bottom: 0.25rem;
        }

        .no-devices-message {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin: 1.5rem 0;
        }

        .no-devices-message i {
            font-size: 2rem;
            color: var(--medium-gray);
            margin-bottom: 1rem;
        }

        .no-devices-message p {
            color: var(--medium-gray);
            font-size: 1rem;
            margin-bottom: 0;
        }

        /* Sorting Statistics Styles */
        .stats-card {
            border: 2px solid var(--border-color);
            border-radius: 15px;
            overflow: hidden;
        }

        .stat-item {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
        }

        .stat-count {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-green);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--medium-gray);
        }

        .confidence-badge {
            background: var(--light-green);
            color: var(--primary-green);
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .latest-sort-preview {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .latest-sort-preview img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn.disabled::after {
                position: static;
                transform: none;
                display: block;
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="fw-bold mb-0 mt-3">Waste Monitoring</h2>
                <!-- Search bar -->
                <div class="input-group mt-3" style="max-width: 300px;">
                    <input type="text" id="searchDevice" class="form-control" placeholder="Search Device">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>

            <hr style="height: 1.5px; background-color: #000; opacity: 1; margin-left:6.5px;" class="mb-4">

            <!-- Today's Sorting Statistics -->
            <div class="stats-card mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center p-3">
                    <h5 class="mb-0">Today's Sorting Statistics</h5>
                    <div class="d-flex align-items-center">
                        <small class="me-2">Auto-updates every 2 seconds</small>
                        <div class="spinner-border spinner-border-sm text-light" role="status" id="updateSpinner" style="display: none;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="row" id="sortingStats">
                                <!-- Stats will be populated by JavaScript -->
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="latest-sort-preview">
                                <h6 class="text-center mb-3">Latest Sorted Item</h6>
                                <div id="latestImageContainer" class="text-center">
                                    <!-- Latest image will be displayed here -->
                                </div>
                                <div id="latestSortInfo" class="text-center mt-2">
                                    <!-- Latest sort info will be displayed here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Banner -->
            <div class="info-banner">
                <i class="bi bi-info-circle-fill"></i>
                <div class="banner-text">
                    <h5>Monitoring Options</h5>
                    <ul>
                        <li><strong>Monitor Live Sorting:</strong> Watch real-time waste sorting on online devices</li>
                        <li><strong>Review Daily Logs:</strong> Check and verify all sorting history (available after 6:00 PM for all devices)</li>
                    </ul>
                </div>
            </div>

            <div class="d-flex mb-2">
                <div class="sort-dropdown-wrapper">
                    <div class="dropdown">
                        <button class="btn btn-link text-dark dropdown-toggle px-0" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            Sort by
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end custom-drive-dropdown" aria-labelledby="sortDropdown">
                            <?php
                            // Get current query parameters
                            $params = $_GET;
                            
                            // Function to generate sort URL
                            function getSortUrl($sortValue) {
                                global $params;
                                $urlParams = $params;
                                $urlParams['sort'] = $sortValue;
                                return '?' . http_build_query($urlParams);
                            }
                            ?>
                            <li><a class="dropdown-item<?= $sort === 'recent' ? ' active-sort' : '' ?>" href="<?= getSortUrl('recent') ?>">Recently Added</a></li>
                            <li><a class="dropdown-item<?= $sort === 'name' ? ' active-sort' : '' ?>" href="<?= getSortUrl('name') ?>">Name (A-Z)</a></li>
                            <li><a class="dropdown-item<?= $sort === 'active' ? ' active-sort' : '' ?>" href="<?= getSortUrl('active') ?>">Last Active</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Online Devices Section -->
            <div class="section-title mt-4">
                <div class="d-flex align-items-center">
                    <i class="bi bi-circle-fill text-success me-2" style="font-size: 0.75rem;"></i>
                    <h3 class="mb-0" style="font-size: 1.25rem;">Online Devices</h3>
                    <span class="device-count">(<?php echo count($onlineDevices); ?>)</span>
                </div>
            </div>
            <div class="row">
                <?php if (empty($onlineDevices)): ?>
                    <div class="col-12">
                        <div class="no-devices-message">
                            <i class="bi bi-info-circle-fill d-block"></i>
                            <p>No online devices available for live monitoring.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($onlineDevices as $device): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="device-card" data-device="<?php echo htmlspecialchars($device['device_name']); ?>">
                                <div class="device-header">
                                    <h3 class="device-name">
                                        <?php echo htmlspecialchars($device['device_name']); ?>
                                    </h3>
                                    <div class="status-badge">
                                        <span class="status-indicator"></span>
                                        Online
                                    </div>
                                </div>
                                <div class="device-info">
                                    <div class="info-item">
                                        <i class="bi bi-geo-alt"></i>
                                        <?php echo htmlspecialchars($device['location']); ?>
                                    </div>
                                    <div class="info-item">
                                        <i class="bi bi-clock"></i>
                                        Last active: <?php echo date('M j, Y g:i A', strtotime($device['last_active'])); ?>
                                    </div>
                                </div>
                                
                                <div class="action-buttons">
                                    <a href="GoSort_LiveMonitor.php?device=<?php echo $device['id']; ?>&name=<?php echo urlencode($device['device_name']); ?>&identity=<?php echo urlencode($device['device_identity']); ?>" 
                                       class="action-btn monitor-live">
                                        <i class="bi bi-broadcast"></i>
                                        Monitor Live
                                    </a>
                                    
                                    <?php if ($isOperatingHours): ?>
                                        <div class="action-btn review-logs disabled">
                                            <i class="bi bi-clock-history"></i>
                                            Review Logs
                                        </div>
                                    <?php else: ?>
                                        <a href="GoSort_ReviewLogs.php?device=<?php echo $device['id']; ?>&name=<?php echo urlencode($device['device_name']); ?>&identity=<?php echo urlencode($device['device_identity']); ?>" 
                                           class="action-btn review-logs">
                                            <i class="bi bi-clock-history"></i>
                                            Review Logs
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Offline Devices Section -->
            <div class="section-title mt-5">
                <div class="d-flex align-items-center">
                    <i class="bi bi-circle-fill text-danger me-2" style="font-size: 0.75rem;"></i>
                    <h3 class="mb-0" style="font-size: 1.25rem;">Offline Devices</h3>
                    <span class="device-count">(<?php echo count($offlineDevices); ?>)</span>
                </div>
            </div>
            <div class="row">
                <?php if (empty($offlineDevices)): ?>
                    <div class="col-12">
                        <div class="no-devices-message">
                            <i class="bi bi-info-circle-fill d-block"></i>
                            <p>All devices are online!</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($offlineDevices as $device): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="device-card offline" data-device="<?php echo htmlspecialchars($device['device_name']); ?>">
                                <div class="device-header">
                                    <h3 class="device-name">
                                        <?php echo htmlspecialchars($device['device_name']); ?>
                                    </h3>
                                    <div class="status-badge offline">
                                        <span class="status-indicator"></span>
                                        Offline
                                    </div>
                                </div>
                                <div class="device-info">
                                    <div class="info-item">
                                        <i class="bi bi-geo-alt"></i>
                                        <?php echo htmlspecialchars($device['location']); ?>
                                    </div>
                                    <div class="info-item">
                                        <i class="bi bi-clock"></i>
                                        Last active: <?php echo date('M j, Y g:i A', strtotime($device['last_active'])); ?>
                                    </div>
                                </div>
                                
                                <div class="action-buttons">
                                    <div class="action-btn monitor-live disabled" style="opacity: 0.4; cursor: not-allowed;">
                                        <i class="bi bi-broadcast"></i>
                                        Monitor Live
                                    </div>
                                    
                                    <?php if ($isOperatingHours): ?>
                                        <div class="action-btn review-logs disabled">
                                            <i class="bi bi-clock-history"></i>
                                            Review Logs
                                        </div>
                                    <?php else: ?>
                                        <a href="GoSort_ReviewLogs.php?device=<?php echo $device['id']; ?>&name=<?php echo urlencode($device['device_name']); ?>&identity=<?php echo urlencode($device['device_identity']); ?>" 
                                           class="action-btn review-logs">
                                            <i class="bi bi-clock-history"></i>
                                            Review Logs
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to update sorting statistics
            function updateSortingStats() {
                const spinner = document.getElementById('updateSpinner');
                spinner.style.display = 'inline-block';

                fetch('api/get_daily_sorting.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const statsContainer = document.getElementById('sortingStats');
                            const imageContainer = document.getElementById('latestImageContainer');
                            const sortInfoContainer = document.getElementById('latestSortInfo');
                            
                            // Clear existing stats
                            statsContainer.innerHTML = '';
                            
                            // Group data by trash type
                            const statsByType = {};
                            let latestImage = null;
                            let latestInfo = null;
                            let latestTimestamp = 0;
                            
                            data.data.forEach(item => {
                                if (!statsByType[item.trash_type]) {
                                    statsByType[item.trash_type] = {
                                        count: 0,
                                        confidence: []
                                    };
                                }
                                statsByType[item.trash_type].count += parseInt(item.count);
                                statsByType[item.trash_type].confidence.push(parseFloat(item.avg_confidence));
                                
                                // Check if this is the latest image by comparing timestamps
                                if (item.latest_image && item.timestamp) {
                                    const itemTimestamp = new Date(item.timestamp).getTime();
                                    if (itemTimestamp > latestTimestamp) {
                                        latestTimestamp = itemTimestamp;
                                        latestImage = item.latest_image;
                                        latestInfo = {
                                            type: item.trash_type,
                                            confidence: item.avg_confidence,
                                            timestamp: item.timestamp
                                        };
                                    }
                                }
                            });
                            
                            // Create stat cards for each type
                            Object.entries(statsByType).forEach(([type, stats]) => {
                                const avgConfidence = stats.confidence.reduce((a, b) => a + b, 0) / stats.confidence.length;
                                const html = `
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="stat-item">
                                            <div class="stat-count">${stats.count}</div>
                                            <div class="stat-label">${type.toUpperCase()} Items</div>
                                            <div class="confidence-badge mt-2">
                                                Avg. Confidence: ${avgConfidence.toFixed(2)}%
                                            </div>
                                        </div>
                                    </div>
                                `;
                                statsContainer.innerHTML += html;
                            });
                            
                            // Update latest image if available
                            if (latestImage && latestInfo) {
                                imageContainer.innerHTML = `<img src="data:image/jpeg;base64,${latestImage}" class="img-fluid rounded" alt="Latest sorted item">`;
                                sortInfoContainer.innerHTML = `
                                    <div class="mt-2">
                                        <strong>Type:</strong> ${latestInfo.type.toUpperCase()}<br>
                                        <strong>Confidence:</strong> ${parseFloat(latestInfo.confidence).toFixed(2)}%<br>
                                        <small class="text-muted">Time: ${new Date(latestInfo.timestamp).toLocaleTimeString()}</small>
                                    </div>
                                `;
                            }
                        }
                        spinner.style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Error fetching sorting data:', error);
                        spinner.style.display = 'none';
                    });
            }

            // Initial update
            updateSortingStats();

            // Update every 2 seconds
            setInterval(updateSortingStats, 2000);

            // Search functionality
            document.getElementById('searchDevice').addEventListener('keyup', function() {
                const searchValue = this.value.toLowerCase().trim();
                document.querySelectorAll('[data-device]').forEach(card => {
                    const deviceName = card.getAttribute('data-device').toLowerCase();
                    
                    if (deviceName.includes(searchValue)) {
                        card.closest('.col-lg-4').style.display = '';
                    } else {
                        card.closest('.col-lg-4').style.display = 'none';
                    }
                });
            });
        });
    </script>
    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>