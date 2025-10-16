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

// Get device information from URL parameters
$deviceId = $_GET['device'] ?? null;
$deviceName = $_GET['name'] ?? 'Unknown Device';
$deviceIdentity = $_GET['identity'] ?? '';

if (!$deviceId) {
    header("Location: GoSort_BinMonitoring.php");
    exit();
}

// Fetch device details
$query = "SELECT * FROM sorters WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$deviceId]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device) {
    header("Location: GoSort_BinMonitoring.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Live Monitor: <?php echo htmlspecialchars($deviceName); ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
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
            transition: margin-left 0.3s ease;
            padding: 20px;
        }

        #main-content-wrapper.collapsed {
            margin-left: 80px;
        }

        /* Header Section */
        .monitor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: transparent;
            border: none;
            color: var(--dark-gray);
            text-decoration: none;
            font-size: 1.5rem;
            margin-right: 1rem;
            transition: all 0.2s ease;
        }

        .back-button:hover {
            color: var(--primary-green);
        }

        .device-status-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            border: 2px solid var(--border-color);
        }

        .status-indicator-live {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--primary-green);
            box-shadow: 0 0 12px rgba(39, 74, 23, 0.8);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        .monitor-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }

        .monitor-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(to bottom, var(--light-green), var(--primary-green));
        }

        /* Time Display */
        .time-display {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            border: 2px solid var(--border-color);
        }

        .time-display .time {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-green);
            margin: 0;
        }

        .time-display .date {
            font-size: 0.875rem;
            color: var(--medium-gray);
            margin-top: 0.25rem;
        }

        .waste-category {
            text-align: center;
            margin-bottom: 2rem;
        }

        .category-title {
            font-size: 2rem;
            font-weight: 900;
            color: var(--dark-gray);
            margin: 0;
            letter-spacing: -0.02em;
        }

        .detection-subtitle {
            font-size: 1rem;
            color: var(--medium-gray);
            margin-top: 0.5rem;
            font-weight: 500;
        }

        .detected-item {
            display: inline-block;
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-green);
            background: #efffe8ff;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-top: 0.5rem;
        }

        .image-display {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 350px;
            background: #f9fafb;
            border-radius: 16px;
            border: 2px dashed #d1d5db;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }

        .image-display img {
            max-width: 100%;
            max-height: 500px;
            object-fit: contain;
            border-radius: 12px;
        }

        .placeholder-image {
            text-align: center;
            color: var(--medium-gray);
        }

        .placeholder-image i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .placeholder-image p {
            font-size: 1rem;
            margin: 0;
        }

        /* Loading Animation */
        .loading-spinner {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
            border-width: 0.3rem;
            color: var(--primary-green);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .device-status-header {
                display: none;
            }

            .category-title {
                font-size: 2rem;
            }

            .image-display {
                min-height: 300px;
            }
        }
    </style>
</head>
<body>
   <?php // include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
                    <div class="container-fluid">
                <!-- Header -->
                <div class="d-flex align-items-center mb-2 mt-3">
                    <a href="GoSort_WasteMonitoringNavpage.php" class="back-button">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <h2 class="fw-bold mb-0">Waste Monitoring</h2>

                    <!-- Right side (time + status) -->
                    <div class="d-flex align-items-center ms-auto gap-4">
                        <!-- Time Display -->
                        <div class="time-display text-end">
                            <div class="time" id="currentTime">12:00 am</div>
                            <div class="date" id="currentDate">Tuesday, April 15</div>
                        </div>

                        <!-- Device Status -->
                        <div class="device-status-header d-flex align-items-center">
                            <span class="status-indicator-live me-2"></span>
                            <span style="font-weight: 600; color: var(--dark-gray);">
                                <?php echo htmlspecialchars($deviceName); ?> - Live Monitoring
                            </span>
                        </div>
                    </div>
                </div>
            </div>


            <hr style="height: 1.5px; background-color: #000; opacity: 1; margin-left:6.5px;" class="mb-4">

            <!-- Main Monitor Card -->
            <div class="monitor-card">

                <!-- Waste Category -->
                <div class="waste-category">
                    <h5 class="category-title" id="wasteCategory">Non-Biodegradable</h5>
                    <p class="detection-subtitle">Detected: <span class="detected-item" id="detectedItem">Plastic Bottle</span></p>
                </div>

                <!-- Image Display -->
                <div class="image-display" id="imageDisplay">
                    <div class="loading-spinner" id="loadingSpinner">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div class="placeholder-image" id="placeholderImage">
                        <i class="bi bi-camera-fill d-block"></i>
                        <p>Waiting for detection...</p>
                    </div>
                    <img id="detectionImage" src="" alt="Detection" style="display: none;">
                </div>

            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        // Update time display in real-time
        function updateTime() {
            const now = new Date();
            
            // Format time (12-hour format)
            let hours = now.getHours();
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'pm' : 'am';
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            
            const timeString = `${hours}:${minutes} ${ampm}`;
            
            // Format date
            const options = { weekday: 'long', month: 'long', day: 'numeric' };
            const dateString = now.toLocaleDateString('en-US', options);
            
            document.getElementById('currentTime').textContent = timeString;
            document.getElementById('currentDate').textContent = dateString;
        }

        // Fetch latest detection data
        function fetchLatestDetection() {
            const deviceId = <?php echo json_encode($deviceId); ?>;
            
            fetch(`fetch_latest_detection.php?device=${deviceId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.detection) {
                        // Update category
                        document.getElementById('wasteCategory').textContent = data.detection.category || 'Non-Biodegradable';
                        
                        // Update detected item
                        document.getElementById('detectedItem').textContent = data.detection.item || 'Plastic Bottle';
                        
                        // Update image if available
                        if (data.detection.image) {
                            const img = document.getElementById('detectionImage');
                            img.src = data.detection.image;
                            img.style.display = 'block';
                            document.getElementById('placeholderImage').style.display = 'none';
                        }
                        
                    }
                })
                .catch(error => {
                    console.error('Error fetching detection:', error);
                });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Update time immediately and then every second
            updateTime();
            setInterval(updateTime, 1000);
            
            // Fetch detection data immediately and then every 3 seconds
            fetchLatestDetection();
            setInterval(fetchLatestDetection, 3000);
        });
    </script>
</body>
</html>