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

// Fetch all devices and sort them by status (online first, then offline)
$sort = $_GET['sort'] ?? 'recent'; // default to recently added

// Determine sort order for the query
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
        s.maintenance_mode,
        s.last_active,
        s.location,
        s.created_at
    FROM sorters s
    ORDER BY {$orderBy}
";

$stmt = $pdo->query($query);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate devices by status first
$onlineDevices = array_filter($devices, function($device) {
    return $device['status'] === 'online';
});

$offlineDevices = array_filter($devices, function($device) {
    return $device['status'] !== 'online';
});

// Sort function for both arrays
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

// Sort each section separately while maintaining the separation
$onlineDevices = $sortDevices(array_values($onlineDevices));
$offlineDevices = $sortDevices(array_values($offlineDevices));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Maintenance Overview</title>
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

        .device-card.online {
            cursor: pointer;
        }

        .device-card.online:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .device-card.offline {
            opacity: 0.85;
            border-color: #d1d5db;
            cursor: pointer;
        }

        .device-card.offline:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }

        .device-card.offline::before {
            background: linear-gradient(to bottom, #d1d5db, #9ca3af);
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
        }

        .status-badge.online {
            background-color: var(--light-green);
            color: var(--primary-green);
        }

        .status-badge.offline {
            background-color: #fee2e2;
            color: #ef4444;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-badge.online .status-indicator {
            background-color: var(--primary-green);
            box-shadow: 0 0 8px rgba(39, 74, 23, 0.6);
        }

        .status-badge.offline .status-indicator {
            background-color: #ef4444;
            box-shadow: 0 0 8px rgba(239, 68, 68, 0.6);
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

        .maintenance-button {
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

        .device-card.online:hover .maintenance-button {
            opacity: 1;
            transform: translateY(0);
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

        .device-info-modal .device-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-gray);
        }
        
        .device-info-modal .info-item {
            font-size: 0.95rem;
            color: var(--medium-gray);
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        @media (max-width: 768px) {
            .device-grid {
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
                <h2 class="fw-bold mb-0 mt-3">Maintenance Overview</h2>
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
                            <p>No online devices at this moment. Please make sure your devices are properly connected to the network and try refreshing the page.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($onlineDevices as $device): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="device-card online" data-device="<?php echo htmlspecialchars($device['device_name']); ?>" 
                                 onclick="window.location.href='GoSort_Maintenance.php?device=<?php echo $device['id']; ?>&name=<?php echo urlencode($device['device_name']); ?>&identity=<?php echo urlencode($device['device_identity']); ?>'">
                                <div class="device-header">
                                    <h3 class="device-name">
                                        <?php echo htmlspecialchars($device['device_name']); ?>
                                    </h3>
                                    <div class="status-badge online">
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
                                <button class="maintenance-button">
                                    <i class="bi bi-tools"></i>
                                    Start Maintenance
                                </button>
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
                <?php foreach ($offlineDevices as $device): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="device-card offline" data-device="<?php echo htmlspecialchars($device['device_name']); ?>"
                             onclick="showOfflineModal('<?php echo htmlspecialchars($device['device_name']); ?>', '<?php echo htmlspecialchars($device['location']); ?>', '<?php echo htmlspecialchars($device['last_active']); ?>')">
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
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Offline Device Modal -->
    <div class="modal fade" id="offlineDeviceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 4px 24px rgba(60,64,67,0.15);">
                <div class="modal-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="modal-title d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                            Device Offline
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div id="offlineDeviceInfo" class="mb-4">
                        <!-- Device info will be inserted here by JavaScript -->
                    </div>
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        Maintenance mode is only available for online devices. Please ensure the device is connected and try again.
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap Modal
            const offlineModal = new bootstrap.Modal(document.getElementById('offlineDeviceModal'));

            // Global function to show offline modal
            window.showOfflineModal = function(deviceName, location, lastActive) {
                const infoHtml = `
                    <div class="device-info-modal">
                        <div class="mb-3">
                            <h4 class="device-name mb-3">${deviceName}</h4>
                            <div class="info-item mb-2">
                                <i class="bi bi-geo-alt-fill text-secondary"></i>
                                Location: ${location}
                            </div>
                            <div class="info-item">
                                <i class="bi bi-clock-fill text-secondary"></i>
                                Last Active: ${new Date(lastActive).toLocaleString()}
                            </div>
                        </div>
                    </div>
                `;
                
                document.getElementById('offlineDeviceInfo').innerHTML = infoHtml;
                offlineModal.show();
            };

            // Search functionality
            document.getElementById('searchDevice').addEventListener('keyup', function() {
                const searchValue = this.value.toLowerCase().trim();
                document.querySelectorAll('[data-device]').forEach(card => {
                    const deviceName = card.getAttribute('data-device').toLowerCase();
                    const cardSection = card.closest('.row').previousElementSibling;
                    
                    if (deviceName.includes(searchValue)) {
                        card.closest('.col-lg-4').style.display = '';
                        if (cardSection) cardSection.style.display = '';
                    } else {
                        card.closest('.col-lg-4').style.display = 'none';
                        // Hide section title if all cards in that section are hidden
                        if (cardSection) {
                            const visibleCards = cardSection.nextElementSibling.querySelectorAll('[data-device]');
                            const allHidden = Array.from(visibleCards).every(c => 
                                !c.getAttribute('data-device').toLowerCase().includes(searchValue)
                            );
                            if (allHidden) cardSection.style.display = 'none';
                        }
                    }
                });
            });
        });
    </script>
    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>