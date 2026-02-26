<?php
session_start();
date_default_timezone_set('Asia/Manila');
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

// Get device parameter from URL
$deviceId = $_GET['device'] ?? null;

// Validate device ID
if (!$deviceId) {
    header("Location: GoSort_WasteMonitoringNavpage.php");
    exit();
}

// Fetch device details from database including all necessary fields
$query = "SELECT id, device_name, device_identity, status, location, maintenance_mode, last_active 
          FROM sorters 
          WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$deviceId]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

// Validate device exists
if (!$device) {
    header("Location: GoSort_WasteMonitoringNavpage.php");
    exit();
}

// Check if device is offline
if ($device['status'] === 'offline' && !$device['maintenance_mode']) {
    header("Location: GoSort_WasteMonitoringNavpage.php?error=offline");
    exit();
}

// Use device details from database
$deviceName = $device['device_name'];
$deviceIdentity = $device['device_identity'];
$deviceLocation = $device['location'];
$isMaintenanceMode = $device['maintenance_mode'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Live Monitor</title>
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
            min-height: 100vh;
        }

        .detection-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 15px;
            overflow: hidden;
        }

        .detection-header {
            background: var(--primary-green);
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .detection-body {
            padding: 1.5rem;
        }

        .image-container {
            width: 100%;
            max-height: 400px;
            overflow: hidden;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }

        .image-container img {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .info-item {
            background: var(--light-gray);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .info-label {
            font-size: 0.875rem;
            color: var(--medium-gray);
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-gray);
        }

        .confidence-badge {
            background: var(--light-green);
            color: var(--primary-green);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }

        .detected-items {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .detected-items h6 {
            color: var(--medium-gray);
            margin-bottom: 0.5rem;
        }

        .item-tag {
            display: inline-block;
            background: var(--light-green);
            color: var(--primary-green);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            margin: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        #updateSpinner {
            width: 1rem;
            height: 1rem;
        }

        @media (max-width: 768px) {
            #main-content-wrapper {
                margin-left: 0;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Live Monitor - <?php echo htmlspecialchars($deviceName); ?></h2>
                <button onclick="window.location.href='GoSort_BinMonitoring.php'" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Devices
                </button>
            </div>

            <div class="detection-card">
                <div class="detection-header">
                    <h5 class="mb-0">Latest Detection</h5>
                    <div class="d-flex align-items-center">
                        <small class="me-2">Auto-updates every 2 seconds</small>
                        <div class="spinner-border spinner-border-sm text-light" role="status" id="updateSpinner" style="display: none;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="detection-body" id="detectionContent">
                    <!-- Content will be updated by JavaScript -->
                    <div class="text-center py-5">
                        <i class="bi bi-camera text-muted" style="font-size: 3rem;"></i>
                        <p class="mt-3 text-muted">Waiting for detections...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function updateDetection() {
            const spinner = document.getElementById('updateSpinner');
            const detectionContent = document.getElementById('detectionContent');
            
            try {
                spinner.style.display = 'inline-block';
                
                const response = await fetch(`api/get_latest_detection.php?identity=<?php echo urlencode($deviceIdentity); ?>&device=<?php echo urlencode($deviceId); ?>`)
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                
                if (data.success && data.data) {
                    const detection = data.data;
                    const detectedItems = detection.trash_class ? detection.trash_class.split(',').map(item => item.trim()) : [];
                    
                    let html = `
                        <div class="image-container">
                            <img src="data:image/jpeg;base64,${detection.image_data}" alt="Detected item">
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Type</div>
                                <div class="info-value">${detection.trash_type.toUpperCase()}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Confidence</div>
                                <div class="info-value">
                                    <span class="confidence-badge">${(detection.confidence * 100).toFixed(2)}%</span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Time</div>
                                <div class="info-value" style="font-size: 1rem">
                                    ${new Date(detection.sorted_at).toLocaleString()}
                                </div>
                            </div>
                        </div>`;

                    if (detectedItems.length > 0) {
                        html += `
                            <div class="detected-items">
                                <h6>Detected Items:</h6>
                                ${detectedItems.map(item => `<span class="item-tag">${item}</span>`).join('')}
                            </div>`;
                    }
                    
                    detectionContent.innerHTML = html;
                } else {
                    detectionContent.innerHTML = `
                        <div class="text-center py-5">
                            <i class="bi bi-camera text-muted" style="font-size: 3rem;"></i>
                            <p class="mt-3 text-muted">No detections available yet</p>
                        </div>`;
                }
            } catch (error) {
                console.error('Error fetching detection:', error);
                detectionContent.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Error loading detection data. Please try again later.
                    </div>`;
            } finally {
                spinner.style.display = 'none';
            }
        }

        // Initial update
        updateDetection();

        // Update every 2 seconds
        setInterval(updateDetection, 2000);
    </script>

    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>