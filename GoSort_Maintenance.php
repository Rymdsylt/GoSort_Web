<?php
session_start();
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: GoSort_Login.php");
    exit();
}

// Get device info from URL parameters
$device_id = $_GET['device'] ?? null;
$device_name = $_GET['name'] ?? 'Unknown Device';
$device_identity = $_GET['identity'] ?? null;

// Basic parameter validation
if (!$device_id || !$device_identity) {
    header("Location: GoSort_Sorters.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script>
    // Redirect to GoSort_MaintenanceNavpage.php after 1 minute of inactivity
    setTimeout(function() {
        window.location.href = 'GoSort_MaintenanceNavpage.php';
    }, 60000);
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Maintenance</title>
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
            --bio-color: #10b981;
            --nbio-color: #ef4444;
            --hazardous-color: #f59e0b;
            --mixed-color: #6b7280;
            --success-light: rgba(16, 185, 129, 0.1);
            --danger-light: rgba(239, 68, 68, 0.1);
            --warning-light: rgba(245, 158, 11, 0.1);
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .back-link {
            color: var(--dark-gray);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: color 0.3s ease;
            font-size: 1.5rem;
        }

        .back-link:hover {
            color: var(--primary-green);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 0;
            margin-left: 6px;
        }

        .device-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark-gray);
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--light-green);
            box-shadow: 0 0 8px rgba(122, 241, 70, 0.6);
        }

        /* Tab Navigation */
        .maintenance-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            padding-bottom: 2px;
            position: relative;
        }

        .maintenance-tabs::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: #e5e7eb;
        }

        .tab-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            color: var(--medium-gray);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            position: relative;
            font-size: 0.95rem;
        }

        .tab-btn:hover {
            color: var(--dark-gray);
        }

        .tab-btn.active {
            color: var(--primary-green);
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            z-index: 1;
            background: linear-gradient(90deg, var(--light-green), var(--primary-green));
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Status Alert */
        #statusAlertContainer {
            margin-bottom: 1.5rem;
        }

        .connection-status {
            border-radius: 12px;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            border: none;
            margin-bottom: 0;
        }

        .connection-status.alert-success {
            background-color: var(--success-light);
            color: var(--bio-color);
        }

        .connection-status.alert-danger {
            background-color: var(--danger-light);
            color: var(--nbio-color);
        }

        /* Content Card */
        .content-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--primary-green);
            font-size: 1.5rem;
        }

        .section-divider {
            border-top: 2px solid #e5e7eb;
            margin: 2rem 0;
            padding-top: 0;
        }

        /* Servo Mapping */
        .servo-mapping-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            max-width: 600px;
            margin: 2rem auto;
            padding: 0;
        }

        .servo-position {
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            border: 2px solid;
        }

        .servo-position:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .servo-position h6 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 1rem 0;
        }

        .servo-position-left {
            border-color: var(--bio-color);
            background: rgba(16, 185, 129, 0.05);
        }

        .servo-position-left h6 {
            color: var(--bio-color);
        }

        .servo-position-front {
            border-color: var(--nbio-color);
            background: rgba(239, 68, 68, 0.05);
        }

        .servo-position-front h6 {
            color: var(--nbio-color);
        }

        .servo-position-right {
            border-color: var(--hazardous-color);
            background: rgba(245, 158, 11, 0.05);
        }

        .servo-position-right h6 {
            color: var(--hazardous-color);
        }

        .servo-position-back {
            border-color: var(--mixed-color);
            background: rgba(107, 114, 128, 0.05);
        }

        .servo-position-back h6 {
            color: var(--mixed-color);
        }

        .quadrant-btn {
            width: 100%;
            padding: 0.75rem 1rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .mapping-preview {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
        }

        .mapping-preview h6 {
            color: var(--dark-gray);
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .mapping-preview-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem;
            color: var(--medium-gray);
            font-size: 0.9rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .mapping-preview-item:last-child {
            border-bottom: none;
        }

        .mapping-preview-position {
            font-weight: 500;
            color: var(--dark-gray);
        }

        /* Control Buttons */
        .btn {
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-lg {
            width: 100%;
            padding: 0.875rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
        }

        .btn-primary {
            background-color: var(--primary-green);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background-color: #1e3a11;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-outline-success, .btn-outline-danger, .btn-outline-warning {
            font-weight: 600;
        }

        .btn-outline-success {
            color: var(--bio-color);
            border-color: var(--bio-color);
        }

        .btn-outline-success:hover:not(:disabled) {
            background-color: var(--bio-color);
            border-color: var(--bio-color);
            color: white;
            transform: translateY(-1px);
        }

        .btn-outline-danger {
            color: var(--nbio-color);
            border-color: var(--nbio-color);
        }

        .btn-outline-danger:hover:not(:disabled) {
            background-color: var(--nbio-color);
            border-color: var(--nbio-color);
            color: white;
            transform: translateY(-1px);
        }

        .btn-outline-warning {
            color: var(--hazardous-color);
            border-color: var(--hazardous-color);
        }

        .btn-outline-warning:hover:not(:disabled) {
            background-color: var(--hazardous-color);
            border-color: var(--hazardous-color);
            color: white;
            transform: translateY(-1px);
        }

        .btn-info {
            background-color: #0dcaf0;
            color: white;
            border-color: #0dcaf0;
        }

        .btn-info:hover:not(:disabled) {
            background-color: #0ba5d9;
            border-color: #0ba5d9;
            transform: translateY(-1px);
        }

        .btn-dark {
            background-color: var(--dark-gray);
            color: white;
        }

        .btn-dark:hover:not(:disabled) {
            background-color: #111827;
            transform: translateY(-1px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            filter: grayscale(100%);
        }

        .warning-badge {
            background: var(--warning-light);
            color: var(--hazardous-color);
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--hazardous-color);
        }

        .button-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        /* Floating Progress Bar */
        .floating-progress {
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 300px;
            background: rgba(0, 0, 0, 0.95);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            z-index: 9999;
            display: none;
            backdrop-filter: blur(10px);
        }

        .floating-progress .progress-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .floating-progress .progress-title {
            font-weight: 600;
            font-size: 0.95rem;
            margin: 0;
        }

        .floating-progress .progress-time {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .floating-progress .progress-bar {
            height: 6px;
            border-radius: 3px;
            background: rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }

        .floating-progress .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--light-green), var(--primary-green));
            border-radius: 3px;
            transition: width 0.3s ease;
            width: 0%;
        }

        .floating-progress .progress-status {
            font-size: 0.8rem;
            margin-top: 0.75rem;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            #main-content-wrapper {
                margin-left: 80px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .maintenance-tabs {
                overflow-x: auto;
            }

            .servo-mapping-container {
                grid-template-columns: 1fr;
            }

            .device-badge {
                width: 100%;
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
                <div class="header-left">
                    <a href="GoSort_MaintenanceNavpage.php" class="back-link">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <h1 class="page-title">Device Maintenance</h1>
                </div>
                <div class="device-badge">
                    <span class="status-dot"></span>
                    <i class="bi bi-hdd-rack"></i>
                    <?php echo htmlspecialchars($device_name); ?>
                </div>
            </div>

            <hr style="height: 1.5px; background-color: #000; opacity: 1; margin-left:6.5px;" class="mb-2">

            <!-- Status Alert -->
            <div id="statusAlertContainer"></div>

            <!-- Connection Status -->
            <div class="connection-status alert mb-4" role="alert">
                Checking connection status...
            </div>

            <!-- Tab Navigation -->
            <div class="maintenance-tabs">
                <button class="tab-btn active" onclick="switchTab('mapping')">
                    <i class="bi bi-diagram-3 me-2"></i>Position Mapping
                </button>
                <button class="tab-btn" onclick="switchTab('controls')">
                    <i class="bi bi-sliders me-2"></i>Device Controls
                </button>
                <button class="tab-btn" onclick="switchTab('tests')">
                    <i class="bi bi-vial me-2"></i>Test Operations
                </button>
            </div>

            <!-- Tab 1: Position Mapping -->
            <div id="mapping" class="tab-content active">
                <div class="content-card">
                    <div class="section-title">
                        <i class="bi bi-diagram-3"></i>
                        Customize Trash Position Mapping
                    </div>

                    <p class="text-muted">Assign each position (Left, Front, Right, Back) to a trash type. Each position must have a unique type.</p>

                    <!-- Servo Mapping Grid (2x2) -->
                    <div class="servo-mapping-container" style="grid-template-columns: repeat(2, 1fr); max-width: 640px;">
                        <div class="servo-position servo-position-front servo-position-left">
                            <h6>Front - Left</h6>
                            <button id="btn-mdeg" class="btn btn-outline-secondary quadrant-btn">Mixed</button>
                        </div>

                        <div class="servo-position servo-position-front servo-position-right">
                            <h6>Front - Right</h6>
                            <button id="btn-zdeg" class="btn btn-outline-success quadrant-btn">Bio</button>
                        </div>

                        <div class="servo-position servo-position-back servo-position-left">
                            <h6>Back - Left</h6>
                            <button id="btn-ndeg" class="btn btn-outline-danger quadrant-btn">Non-Bio</button>
                        </div>

                        <div class="servo-position servo-position-back servo-position-right">
                            <h6>Back - Right</h6>
                            <button id="btn-odeg" class="btn btn-outline-warning quadrant-btn">Hazardous</button>
                        </div>

                    </div>

                    <!-- Mapping Preview -->
                    <div class="mapping-preview">
                        <h6>Current Mapping</h6>
                            <div id="mapping-preview-content">
                                <div class="mapping-preview-item">
                                    <span class="mapping-preview-position">Front - Left:</span>
                                    <span id="preview-front-left">Bio</span>
                                </div>
                                <div class="mapping-preview-item">
                                    <span class="mapping-preview-position">Front - Right:</span>
                                    <span id="preview-front-right">Non-Bio</span>
                                </div>
                                <div class="mapping-preview-item">
                                    <span class="mapping-preview-position">Back - Left:</span>
                                    <span id="preview-back-left">Hazardous</span>
                                </div>
                                <div class="mapping-preview-item">
                                    <span class="mapping-preview-position">Back - Right:</span>
                                    <span id="preview-back-right">Mixed</span>
                                </div>
                            </div>
                    </div>

                    <div class="text-center">
                        <button class="btn btn-primary btn-lg" style="max-width: 400px;" onclick="saveQuadrantMapping()">
                            <i class="bi bi-save me-2"></i>Save Mapping
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Device Controls -->
            <div id="controls" class="tab-content">
                <div class="content-card">
                    <div class="section-title">
                        <i class="bi bi-sliders"></i>
                        Device Controls
                    </div>

                    <!-- Movement Controls -->
                    <div>
                        <h5 style="color: var(--dark-gray); font-weight: 600; margin-bottom: 1rem;">Move to Position</h5>
                        <div id="dynamic-servo-controls" class="button-grid mb-4">
                            <!-- Dynamic buttons inserted here -->
                        </div>
                    </div>

                    <div class="section-divider"></div>

                    <!-- Unclog Control -->
                    <div class="mt-4">
                        <div class="section-title">
                            <i class="bi bi-wrench"></i>
                            Unclog Control
                        </div>
                        <p class="text-muted mb-3">Tilt mechanism up for 3 seconds while maintaining current pan position</p>
                        <button class="btn btn-warning btn-lg" onclick="moveServo('unclog')" style="max-width: 400px; margin: 0 auto; display: block;">
                            <i class="bi bi-wrench me-2"></i>Unclog Current Section
                        </button>
                    </div>

                    <div class="section-divider"></div>

                    <!-- Shutdown -->
                    <div class="mt-4">
                        <div class="section-title">
                            <i class="bi bi-power-off"></i>
                            Device Management
                        </div>
                        <div class="warning-badge">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            Remember to turn off the device's AVR after shutting down
                        </div>
                        <button class="btn btn-dark btn-lg" id="shutdownBtn" style="max-width: 400px; margin: 0 auto; display: block;">
                            <i class="bi bi-power-off me-2"></i>Shut Down Device
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Test Operations -->
            <div id="tests" class="tab-content">
                <div class="content-card">
                    <div class="section-title">
                        <i class="bi bi-vial"></i>
                        Test Operations
                    </div>

                    <div class="warning-badge">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        WARNING: Don't initiate when clogged!
                    </div>

                    <p class="text-muted mb-3">Run these tests to verify device movement and functionality</p>

                    <div class="button-grid">
                        <button class="btn btn-info btn-lg" onclick="confirmOperation('sweep1', 'Test Pan Sweep Only')">
                            <i class="bi bi-arrows-alt-h me-2"></i>Test Pan Sweep Only
                        </button>
                        <button class="btn btn-info btn-lg" onclick="confirmOperation('sweep2', 'Test Full Sweep')">
                            <i class="bi bi-arrows-alt me-2"></i>Test Full Sweep
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Progress Bar -->
    <div id="floatingProgress" class="floating-progress">
        <div class="progress-header">
            <div class="progress-title" id="progressTitle">Operation in Progress</div>
            <div class="progress-time" id="progressTime">0s</div>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        <div class="progress-status" id="progressStatus">Initializing...</div>
    </div>

    <!-- Shutdown Confirmation Modal -->
    <div class="modal fade" id="shutdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; border: none;">
                <div class="modal-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-exclamation-circle-fill" style="color: var(--hazardous-color); font-size: 1.5rem; margin-right: 1rem;"></i>
                        <h5 class="modal-title mb-0">Confirm Shutdown</h5>
                    </div>
                    <p class="text-muted mb-4">Are you sure? Remember to turn off the device's AVR after shutting down.</p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger flex-grow-1" id="confirmShutdownBtn">Shut Down</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Operation Confirmation Modal -->
    <div class="modal fade" id="operationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; border: none;">
                <div class="modal-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-exclamation-triangle-fill" style="color: var(--hazardous-color); font-size: 1.5rem; margin-right: 1rem;"></i>
                        <h5 class="modal-title mb-0">Confirm Operation</h5>
                    </div>
                    <p class="text-muted mb-3">Make sure the device is <strong>not clogged</strong> before proceeding.</p>
                    <p class="mb-4">Execute: <strong id="operationName">Operation</strong>?</p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary flex-grow-1" id="confirmOperationBtn">Execute</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
    // Tab switching
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Show selected tab
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}

// Status alerts
function showStatusAlert(message, type = 'danger', dismissible = true) {
    const alertContainer = document.getElementById('statusAlertContainer');
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'warning' ? 'alert-warning' : 
                      type === 'info' ? 'alert-info' : 'alert-danger';
    
    const dismissButton = dismissible ? 
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' : '';
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert" style="border-radius: 12px; border: none;">
            ${message}
            ${dismissButton}
        </div>
    `;
    
    alertContainer.innerHTML = alertHtml;
}

// Validate device status
function validateDeviceStatus() {
    const deviceId = <?php echo json_encode($device_id); ?>;
    const deviceIdentity = <?php echo json_encode($device_identity); ?>;
    
    fetch('gs_DB/check_device_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({device_id: deviceId, device_identity: deviceIdentity})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const formData = new FormData();
            formData.append('mode', 'enable');
            formData.append('device_id', deviceId);
            formData.append('device_identity', deviceIdentity);
            
            return fetch('gs_DB/set_maintenance_mode.php', {method: 'POST', body: formData});
        } else {
            showStatusAlert(`<strong>Error:</strong> ${data.message}`, 'danger');
            setTimeout(() => {window.location.href = 'GoSort_Sorters.php';}, 3000);
            throw new Error(data.message);
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Success - maintenance mode activated
        } else {
            throw new Error(data.message || 'Failed to set maintenance mode');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (!error.message.includes('Error:')) {
            showStatusAlert(`<strong>Error:</strong> Failed to initialize maintenance mode.`, 'danger');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    validateDeviceStatus();
});

function setMaintenanceMode(mode) {
    fetch('gs_DB/maintenance_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=maintenance_' + mode
    });
}

(async function initializeMaintenance() {
    await fetch('gs_DB/set_maintenance_mode.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'mode=enable'
    });

    await fetch('gs_DB/maintenance_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=maintenance_start'
    });
})();

const maintenanceInterval = setInterval(() => {
    fetch('gs_DB/maintenance_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=maintenance_keep'
    });
}, 500);

async function cleanupMaintenance() {
    clearInterval(maintenanceInterval);
    await fetch('gs_DB/end_maintenance.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}
    });
}

window.addEventListener('beforeunload', function(e) {
    e.preventDefault();
    e.returnValue = '';
    navigator.sendBeacon('gs_DB/end_maintenance.php');
});

document.querySelectorAll('a[href="GoSort_Sorters.php"], a[href="?logout=1"]').forEach(link => {
    link.addEventListener('click', async (e) => {
        e.preventDefault();
        await cleanupMaintenance();
        window.location.href = e.target.href;
    });
});

function updateConnectionStatus() {
    fetch('gs_DB/connection_status.php')
        .then(response => response.json())
        .then(data => {
            const statusDiv = document.querySelector('.connection-status');
            if (!statusDiv) return;
            
            console.log('Sorter Status:', {status: data.status, timestamp: new Date().toISOString(), details: data});
            
            const device = data.devices?.[0];
            if (device && device.status === 'online') {
                statusDiv.className = 'connection-status alert mb-4 alert-success';
                statusDiv.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Device is connected and running';
                if (!currentServoOperation) {
                    document.querySelectorAll('.btn').forEach(btn => btn.disabled = false);
                }
            } else {
                statusDiv.className = 'connection-status alert mb-4 alert-danger';
                statusDiv.innerHTML = '<i class="bi bi-x-circle-fill me-2"></i>Device is not running';
                document.querySelectorAll('.btn').forEach(btn => btn.disabled = true);
            }
        })
        .catch(error => {
            const statusDiv = document.querySelector('.connection-status');
            if (!statusDiv) return;
            statusDiv.className = 'connection-status alert mb-4 alert-warning';
            statusDiv.innerHTML = '<i class="bi bi-exclamation-circle-fill me-2"></i>Error checking connection';
        });
}

setInterval(updateConnectionStatus, 1000);
updateConnectionStatus();

let currentServoOperation = null;

    const OPERATION_TIMINGS = {
    'zdeg': 2000,
    'ndeg': 2000,
    'odeg': 2000,
    'mdeg': 2000,
    'unclog': 3500,
    'sweep1': 4000,
    'sweep2': 5000
};

function moveServo(position) {
    console.debug('moveServo requested', {position, quadrantMap});
    if (currentServoOperation) {
        console.log('Operation already in progress');
        return;
    }

    const statusDiv = document.getElementById('status');
    const operationTime = OPERATION_TIMINGS[position] || 2000;
    const controller = new AbortController();
    currentServoOperation = controller;
    
    const controlButtons = document.querySelectorAll('.btn');
    controlButtons.forEach(btn => btn.disabled = true);
    
    const floatingProgress = document.getElementById('floatingProgress');
    const progressTitle = document.getElementById('progressTitle');
    const progressTime = document.getElementById('progressTime');
    const progressFill = document.getElementById('progressFill');
    const progressStatus = document.getElementById('progressStatus');
    
    const operationNames = {
        'zdeg': 'Moving to Bio',
        'ndeg': 'Moving to Non-Bio',
        'odeg': 'Moving to Hazardous',
        'mdeg': 'Moving to Mixed',
        'unclog': 'Unclogging Section',
        'sweep1': 'Testing Pan Sweep',
        'sweep2': 'Testing Full Sweep'
    };
    
    progressTitle.textContent = operationNames[position] || 'Operation in Progress';
    progressTime.textContent = '0s';
    progressStatus.textContent = 'Initializing...';
    progressFill.style.width = '0%';
    progressFill.classList.add('animated');
    floatingProgress.style.display = 'block';
    
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += 2;
        progressFill.style.width = Math.min(progress, 100) + '%';
        progressTime.textContent = Math.floor((progress / 100) * (operationTime / 1000)) + 's';
        
        if (progress >= 100) {
            clearInterval(progressInterval);
            progressStatus.textContent = 'Waiting for completion...';
        }
    }, operationTime / 50);

    const urlParams = new URLSearchParams(window.location.search);
    const deviceIdentity = urlParams.get('identity');

    fetch('gs_DB/save_maintenance_command.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({device_identity: deviceIdentity, command: position}),
        signal: controller.signal
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.text();
    })
    .then(data => {
        pollCommandCompletion(deviceIdentity, position, statusDiv, controlButtons, controller);
    })
    .catch(error => {
        if (error.name === 'AbortError') {
            console.log('Servo operation was aborted');
            return;
        }
        
        console.error('Error:', error);
        const controlButtons = document.querySelectorAll('.btn');
        controlButtons.forEach(btn => btn.disabled = false);
        currentServoOperation = null;
    });
}

function pollCommandCompletion(deviceIdentity, command, statusDiv, controlButtons, controller) {
    let pollCount = 0;
    const maxPolls = 60;
    const pollInterval = 1000;
    
    const floatingProgress = document.getElementById('floatingProgress');
    const progressFill = document.getElementById('progressFill');
    const progressStatus = document.getElementById('progressStatus');
    const progressTime = document.getElementById('progressTime');
    
    const pollTimer = setInterval(() => {
        pollCount++;
        
        if (controller.signal.aborted) {
            clearInterval(pollTimer);
            floatingProgress.style.display = 'none';
            return;
        }
        
        if (pollCount > maxPolls) {
            clearInterval(pollTimer);
            progressStatus.textContent = 'Operation timeout - buttons re-enabled';
            progressFill.style.background = 'linear-gradient(90deg, #dc3545, #c82333)';
            progressFill.classList.remove('animated');
            
            controlButtons.forEach(btn => btn.disabled = false);
            currentServoOperation = null;
            
            setTimeout(() => {floatingProgress.style.display = 'none';}, 3000);
            return;
        }
        
        const pollProgress = Math.min((pollCount / maxPolls) * 100, 100);
        progressFill.style.width = pollProgress + '%';
        progressTime.textContent = pollCount + 's';
        progressStatus.textContent = `Polling... (${pollCount}/${maxPolls})`;
        
        fetch('gs_DB/check_maintenance_commands.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({device_identity: deviceIdentity})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && !data.command) {
                clearInterval(pollTimer);
                progressStatus.textContent = 'Operation completed successfully!';
                progressFill.style.background = 'linear-gradient(90deg, #28a745, #20c997)';
                progressFill.classList.remove('animated');
                progressFill.style.width = '100%';
                
                controlButtons.forEach(btn => btn.disabled = false);
                currentServoOperation = null;
                
                setTimeout(() => {floatingProgress.style.display = 'none';}, 2000);
            }
        })
        .catch(error => {
            console.error('Error polling command status:', error);
            progressStatus.textContent = 'Network error - retrying...';
        });
    }, pollInterval);
}

function confirmOperation(command, operationName) {
    console.debug('confirmOperation invoked', {command, operationName, quadrantMap});
    const operationModal = new bootstrap.Modal(document.getElementById('operationModal'));
    document.getElementById('operationName').textContent = operationName;
    
    const confirmBtn = document.getElementById('confirmOperationBtn');
    confirmBtn.onclick = function() {
        operationModal.hide();
        moveServo(command);
    };
    
    operationModal.show();
}

document.getElementById('shutdownBtn').addEventListener('click', function() {
    if (currentServoOperation) {
        alert('Please wait for current operation to complete before shutting down.');
        return;
    }
    
    var shutdownModal = new bootstrap.Modal(document.getElementById('shutdownModal'));
    shutdownModal.show();
});

document.getElementById('confirmShutdownBtn').addEventListener('click', function() {
    var shutdownModalEl = document.getElementById('shutdownModal');
    var shutdownModal = bootstrap.Modal.getInstance(shutdownModalEl);
    shutdownModal.hide();
    
    const allButtons = document.querySelectorAll('.btn');
    allButtons.forEach(btn => btn.disabled = true);
    
    const floatingProgress = document.getElementById('floatingProgress');
    const progressTitle = document.getElementById('progressTitle');
    const progressTime = document.getElementById('progressTime');
    const progressFill = document.getElementById('progressFill');
    const progressStatus = document.getElementById('progressStatus');
    
    progressTitle.textContent = 'Shutting Down Device';
    progressTime.textContent = '0s';
    progressStatus.textContent = 'Sending shutdown command...';
    progressFill.style.width = '0%';
    progressFill.classList.add('animated');
    progressFill.style.background = 'linear-gradient(90deg, #dc3545, #c82333)';
    floatingProgress.style.display = 'block';
    
    const urlParams = new URLSearchParams(window.location.search);
    const deviceIdentity = urlParams.get('identity');

    fetch('gs_DB/save_maintenance_command.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({device_identity: deviceIdentity, command: 'shutdown'})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            progressStatus.textContent = 'Shutdown command sent!';
            progressFill.style.background = 'linear-gradient(90deg, #28a745, #20c997)';
            progressFill.classList.remove('animated');
            progressFill.style.width = '100%';
            
            setTimeout(() => {
                allButtons.forEach(btn => btn.disabled = false);
                floatingProgress.style.display = 'none';
            }, 3000);
        } else {
            progressStatus.textContent = 'Failed: ' + data.message;
            progressFill.style.background = 'linear-gradient(90deg, #dc3545, #c82333)';
            progressFill.classList.remove('animated');
            
            allButtons.forEach(btn => btn.disabled = false);
            setTimeout(() => {floatingProgress.style.display = 'none';}, 3000);
        }
    })
    .catch(error => {
        progressStatus.textContent = 'Error: ' + error.message;
        progressFill.style.background = 'linear-gradient(90deg, #dc3545, #c82333)';
        progressFill.classList.remove('animated');
        console.error('Error:', error);
        
        allButtons.forEach(btn => btn.disabled = false);
        setTimeout(() => {floatingProgress.style.display = 'none';}, 3000);
    });
});

// Quadrant Mapping
let quadrantMap = {zdeg: 'bio', ndeg: 'nbio', odeg: 'hazardous', mdeg: 'mixed'};

function updateQuadrantButtons() {
    const mapToLabel = {bio: 'Bio', nbio: 'Non-Bio', hazardous: 'Hazardous', mixed: 'Mixed'};
    const mapToClass = {bio: 'btn-outline-success', nbio: 'btn-outline-danger', hazardous: 'btn-outline-warning', mixed: 'btn-outline-secondary'};
    
    ['zdeg','ndeg','odeg','mdeg'].forEach(q => {
        const btn = document.getElementById('btn-' + q);
        if (btn) {
            const label = mapToLabel[quadrantMap[q]];
            btn.textContent = label;
            btn.className = 'btn quadrant-btn ' + mapToClass[quadrantMap[q]];
        }
    });
    
    renderServoControls();
    updateMappingPreview();
}

function updateMappingPreview() {
    const mapToLabel = {bio: 'Bio', nbio: 'Non-Bio', hazardous: 'Hazardous', mixed: 'Mixed'};
    // Map internal quadrant keys to UI positions
    // Front - Left  -> mdeg
    // Front - Right -> zdeg
    // Back - Left   -> ndeg
    // Back - Right  -> odeg
    const previewMap = {
        mdeg: 'preview-front-left',   // Front - Left (Mixed)
        zdeg: 'preview-front-right',  // Front - Right (Bio)
        ndeg: 'preview-back-left',    // Back - Left (Non-Bio)
        odeg: 'preview-back-right'    // Back - Right (Hazardous)
    };

    Object.keys(previewMap).forEach(servoKey => {
        const trashType = quadrantMap[servoKey] || 'Not Set';
        const previewEl = document.getElementById(previewMap[servoKey]);
        if (previewEl) {
            previewEl.textContent = mapToLabel[trashType] || trashType;
        }
    });
}

function renderServoControls() {
    const trashTypes = ['bio', 'nbio', 'hazardous', 'mixed'];
    const mapToLabel = {bio: 'Move to Bio', nbio: 'Move to Non-Bio', hazardous: 'Move to Hazardous', mixed: 'Move to Mixed'};
    const mapToClass = {bio: 'btn-success', nbio: 'btn-danger', hazardous: 'btn-warning', mixed: 'btn-secondary'};
    
    let html = '';
    for (const trashType of trashTypes) {
        let mappedServo = null;
        for (const [servoKey, mappedType] of Object.entries(quadrantMap)) {
            if (mappedType === trashType) {
                mappedServo = servoKey;
                break;
            }
        }
        if (mappedServo) {
            // Include servo key in the label for clarity and debugging
            html += `<button class="btn ${mapToClass[trashType]} btn-lg" data-servo="${mappedServo}" onclick="confirmOperation('${mappedServo}', '${mapToLabel[trashType]}')">${mapToLabel[trashType]} <small style=\"opacity:0.8;margin-left:.5rem\">(${mappedServo})</small></button>`;
        }
    }
    
    const controlsDiv = document.getElementById('dynamic-servo-controls');
    if (controlsDiv) {
        controlsDiv.innerHTML = html;
    }
}

window.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const deviceIdentity = urlParams.get('identity');
    
    if (!deviceIdentity) {
        updateQuadrantButtons();
        return;
    }
    
    fetch('gs_DB/save_sorter_mapping.php?device_identity=' + encodeURIComponent(deviceIdentity))
        .then(res => res.json())
        .then(data => {
            if (data.success && data.mapping) {
                quadrantMap = data.mapping;
            }
            updateQuadrantButtons();
            renderServoControls();
        })
        .catch(() => updateQuadrantButtons());
});

function showQuadrantSelect(q) {
    const current = quadrantMap[q];
    const options = [
        {val:'bio', label:'Bio'},
        {val:'nbio', label:'Non-Bio'},
        {val:'hazardous', label:'Hazardous'},
        {val:'mixed', label:'Mixed'}
    ];
    
    const positionLabels = {
        zdeg: 'Front - Left',
        ndeg: 'Front - Right',
        odeg: 'Back - Left',
        mdeg: 'Back - Right'
    };
    let html = '<div id="quadrant-select-modal" class="modal" tabindex="-1" style="display:block;background:rgba(0,0,0,0.3)"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius: 16px; border: none;"><div class="modal-body p-4"><h5 class="modal-title mb-3">Select Trash Type for ' + positionLabels[q] + '</h5>';
    
    options.forEach(opt => {
        const isActive = current === opt.val;
        html += `<button class="btn btn-lg w-100 my-2 ${isActive ? 'btn-primary' : 'btn-outline-primary'}" onclick="setQuadrantType('${q}','${opt.val}')">${opt.label}</button>`;
    });
    
    html += '<button type="button" class="btn btn-outline-secondary btn-lg w-100 mt-3" onclick="closeQuadrantSelect()">Cancel</button></div></div></div></div>';
    document.body.insertAdjacentHTML('beforeend', html);
}

function closeQuadrantSelect() {
    const modal = document.getElementById('quadrant-select-modal');
    if(modal) modal.remove();
}

function setQuadrantType(q, val) {
    quadrantMap[q] = val;
    updateQuadrantButtons();
    closeQuadrantSelect();
}

// Bind quadrant select buttons (safe if elements not present)
['zdeg','ndeg','odeg','mdeg'].forEach(key => {
    const btn = document.getElementById('btn-' + key);
    if (btn) btn.addEventListener('click', () => showQuadrantSelect(key));
});

function saveQuadrantMapping() {
    if (currentServoOperation) {
        alert('Please wait for the current operation to complete.');
        return;
    }
    
    const urlParams = new URLSearchParams(window.location.search);
    const deviceIdentity = urlParams.get('identity');
    
    const values = [quadrantMap.zdeg, quadrantMap.ndeg, quadrantMap.odeg, quadrantMap.mdeg];
    const unique = new Set(values);
    
    if (unique.size < values.length) {
        const counts = {};
        values.forEach(type => {
            counts[type] = (counts[type] || 0) + 1;
        });
        const duplicates = Object.entries(counts)
            .filter(([_, count]) => count > 1)
            .map(([type, _]) => type)
            .join(', ');
        alert(`Duplicate trash types found: ${duplicates}\nEach position must have a unique trash type.`);
        return;
    }
    
    const saveBtn = document.querySelector('button[onclick="saveQuadrantMapping()"]');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Saving...';
    }
    
    fetch('gs_DB/save_sorter_mapping.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            device_identity: deviceIdentity,
            zdeg: quadrantMap.zdeg,
            ndeg: quadrantMap.ndeg,
            odeg: quadrantMap.odeg,
            mdeg: quadrantMap.mdeg || 'mixed'
        })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            showStatusAlert('Mapping saved successfully!', 'success');
        } else {
            showStatusAlert('Error: ' + (data.message || 'Unknown error'), 'danger');
        }
    })
    .catch(err => showStatusAlert('Error saving mapping: ' + err, 'danger'))
    .finally(() => {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-save me-2"></i>Save Mapping';
        }
    });
}

updateQuadrantButtons();
</script>
<script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>;
            