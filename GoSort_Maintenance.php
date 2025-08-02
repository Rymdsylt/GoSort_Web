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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Maintenance</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <style>
        .btn-lg {
            width: 100%;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .connection-status {
            margin-bottom: 0 !important;
        }
        .btn:disabled {
            opacity: 0.4 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: #fff !important;
            filter: grayscale(100%) !important;
            transform: none !important;
            box-shadow: none !important;
        }
        .btn-success:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
        }
        .btn-danger:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
        }
        .btn-warning:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
        }
        .btn-info:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
        }
        .btn-primary:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
        }
        .btn-outline-primary:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: #fff !important;
        }
        .btn-outline-secondary:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: #fff !important;
        }
        .btn-outline-warning:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: #fff !important;
        }
        .btn-outline-success:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: #fff !important;
        }
        .btn-outline-danger:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: #fff !important;
        }
        .btn-outline-info:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: #fff !important;
        }
        .btn-dark:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
        }
        
        /* Floating Progress Bar */
        .floating-progress {
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 300px;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 9999;
            display: none;
            backdrop-filter: blur(10px);
        }
        
        .floating-progress .progress-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .floating-progress .progress-title {
            font-weight: 600;
            font-size: 14px;
            margin: 0;
        }
        
        .floating-progress .progress-time {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .floating-progress .progress-bar {
            height: 8px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }
        
        .floating-progress .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #007bff, #0056b3);
            border-radius: 4px;
            transition: width 0.3s ease;
            width: 0%;
        }
        
        .floating-progress .progress-status {
            font-size: 12px;
            margin-top: 8px;
            opacity: 0.9;
        }
        
        /* Animation for progress bar */
        .floating-progress .progress-fill.animated {
            background: linear-gradient(90deg, #007bff, #0056b3, #007bff);
            background-size: 200% 100%;
            animation: progressShimmer 2s infinite;
        }
        
        @keyframes progressShimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        /* Improved UI/UX styles */
        .servo-position {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .servo-position:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .servo-position.selected {
            border-width: 3px !important;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .section-divider {
            border-top: 2px solid #e9ecef;
            margin: 2rem 0;
            padding-top: 1rem;
        }
        .control-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .control-section h5 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .warning-badge {
            background: #fff3cd;
            color: #856404;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }
        .status-indicator.success {
            background: #d1e7dd;
            color: #0f5132;
        }
        .status-indicator.warning {
            background: #fff3cd;
            color: #856404;
        }
        .status-indicator.danger {
            background: #f8d7da;
            color: #721c24;
        }
        .btn-group-vertical .btn {
            margin-bottom: 0.5rem;
        }
        .mapping-preview {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .mapping-preview h6 {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        .servo-mapping-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
        }
        .servo-position {
            min-width: 140px;
            flex: 1 1 140px;
            max-width: 220px;
            margin: 0.5rem 0.25rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        @media (max-width: 767px) {
            .servo-mapping-container {
                flex-direction: column;
                align-items: stretch;
            }
            .servo-position {
                max-width: 100%;
                min-width: 0;
            }
        }
        .quadrant-btn {
            width: 100%;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success mb-4">
        <div class="container">
            <a class="navbar-brand" href="GoSort_Sorters.php">GoSort Dashboard</a>
            <div class="d-flex gap-2">
                <a href="GoSort_Sorters.php" class="btn btn-warning">Dashboard</a>
                <a href="?logout=1" class="btn btn-light">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Status Alert Container for AJAX messages -->
    <div id="statusAlertContainer"></div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <div class="connection-status alert mb-0" role="alert">
                            Checking connection status...
                        </div>
                    </div>
                    <div class="card-body">
                        <h4 class="card-title text-center mb-4">Customize Trash Position Mapping</h4>
                        
                        <!-- Mapping Section -->
                        <div class="control-section">
                            <h5 class="text-center mb-3">
                                <i class="fas fa-cogs"></i> Servo Position Assignment
                            </h5>
                            <div class="servo-mapping-container">
                                <div class="servo-position" style="border: 2px solid #ffc107; border-radius: 10px; padding: 15px; background: #fff3cd;">
                                    <h6 style="color: #ffc107; margin: 0;">Left</h6>
                                    <button id="btn-odeg" class="btn btn-outline-warning quadrant-btn">Recyc</button>
                                </div>
                                <div class="servo-position" style="border: 2px solid #6c757d; border-radius: 10px; padding: 15px; background: #f8f9fa;">
                                    <h6 style="color: #6c757d; margin: 0;">Center</h6>
                                    <button id="btn-ndeg" class="btn btn-outline-secondary quadrant-btn">Non-Bio</button>
                                </div>
                                <div class="servo-position" style="border: 2px solid #0d6efd; border-radius: 10px; padding: 15px; background: #e7f1ff;">
                                    <h6 style="color: #0d6efd; margin: 0;">Right</h6>
                                    <button id="btn-zdeg" class="btn btn-outline-primary quadrant-btn">Bio</button>
                                </div>
                            </div>
                                <div class="text-center mt-3">
                                    <small class="text-muted">Click each position to assign a trash type</small>
                                </div>
                            </div>
                            
                            <!-- Mapping Preview -->
                            <div class="mapping-preview">
                                <h6>Current Mapping Preview:</h6>
                                <div id="mapping-preview-content" class="small text-muted">
                                    Loading...
                                </div>
                            </div>
                            
                            <div class="text-center mt-3">
                                <button class="btn btn-primary btn-lg" onclick="saveQuadrantMapping()">
                                    <i class="fas fa-save"></i> Save Mapping
                                </button>
                            </div>
                        </div>
                        
                        <div class="section-divider"></div>
                        
                        <!-- Unclog Control Section -->
                        <div class="control-section">
                            <h5 class="text-center mb-3">
                                <i class="fas fa-tools"></i> Unclog Control
                            </h5>
                            <p class="text-muted text-center mb-3">
                                Will tilt mechanism up for 3 seconds while maintaining current pan position
                            </p>
                            <div class="d-grid">
                                <button class="btn btn-warning btn-lg" onclick="moveServo('unclog')">
                                    <i class="fas fa-wrench"></i> Unclog Current Section
                                </button>
                            </div>
                        </div>
                        
                        <div class="section-divider"></div>
                        
                        <!-- Servo Control Section -->
                        <div class="control-section">
                            <h5 class="text-center mb-3">
                                <i class="fas fa-robot"></i> Servo Control
                            </h5>
                            
                            <div class="warning-badge">
                                <i class="fas fa-exclamation-triangle"></i>
                                WARNING: Don't initiate when clogged!
                            </div>
                            
                            <div class="btn-group-vertical w-100" id="dynamic-servo-controls">
                                <!-- Dynamic buttons will be inserted here -->
                            </div>
                        </div>
                        
                        <div class="section-divider"></div>
                        
                        <!-- Test Controls Section -->
                        <div class="control-section">
                            <h5 class="text-center mb-3">
                                <i class="fas fa-vial"></i> Test Controls
                            </h5>
                            <div class="warning-badge">
                                <i class="fas fa-exclamation-triangle"></i>
                                WARNING: Don't initiate when clogged!
                            </div>
                            <div class="d-grid gap-3">
                                <button class="btn btn-info btn-lg" onclick="confirmOperation('sweep1', 'Test Pan Sweep Only')">
                                    <i class="fas fa-arrows-alt-h"></i> Test Pan Sweep Only
                                </button>
                                <button class="btn btn-info btn-lg" onclick="confirmOperation('sweep2', 'Test Full Sweep')">
                                    <i class="fas fa-arrows-alt"></i> Test Full Sweep
                                </button>
                            </div>
                        </div>
                        
                        <div class="section-divider"></div>
                        
                        <!-- Status and Shutdown -->
                        <div id="status" class="alert mt-3" style="display: none;"></div>
                        
                        <div class="d-grid mt-4">
                            <button class="btn btn-dark btn-lg" id="shutdownBtn">
                                <i class="fas fa-power-off"></i> Shut Down Device
                            </button>
                        </div>
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
    <div class="modal fade" id="shutdownModal" tabindex="-1" aria-labelledby="shutdownModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="shutdownModalLabel">Confirm Shut Down</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Confirm shut down? <br><strong>Remember to turn off the device's AVR after shutting down.</strong>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmShutdownBtn">Shut Down</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Operation Confirmation Modal -->
    <div class="modal fade" id="operationModal" tabindex="-1" aria-labelledby="operationModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="operationModalLabel">Confirm Operation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-warning">
              <i class="fas fa-exclamation-triangle"></i>
              <strong>Warning:</strong> Make sure the device is not clogged before proceeding!
            </div>
            <p>Are you sure you want to execute: <strong id="operationName"></strong>?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmOperationBtn">Execute</button>
          </div>
        </div>
      </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to show status alerts
        function showStatusAlert(message, type = 'danger', dismissible = true) {
            const alertContainer = document.getElementById('statusAlertContainer');
            const alertClass = type === 'success' ? 'alert-success' : 
                              type === 'warning' ? 'alert-warning' : 
                              type === 'info' ? 'alert-info' : 'alert-danger';
            
            const dismissButton = dismissible ? 
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' : '';
            
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    ${message}
                    ${dismissButton}
                </div>
            `;
            
            alertContainer.innerHTML = alertHtml;
        }

        // Function to validate device status on page load
        function validateDeviceStatus() {
            const deviceId = <?php echo json_encode($device_id); ?>;
            const deviceIdentity = <?php echo json_encode($device_identity); ?>;
            
            fetch('gs_DB/check_device_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    device_id: deviceId,
                    device_identity: deviceIdentity
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Device is ready for maintenance, set maintenance mode
                    const formData = new FormData();
                    formData.append('mode', 'enable');
                    formData.append('device_id', deviceId);
                    formData.append('device_identity', deviceIdentity);
                    
                    return fetch('gs_DB/set_maintenance_mode.php', {
                        method: 'POST',
                        body: formData
                    });
                } else {
                    // Show error and redirect back to sorters page
                    showStatusAlert(`<strong>Error:</strong> ${data.message}`, 'danger');
                    setTimeout(() => {
                        window.location.href = 'GoSort_Sorters.php';
                    }, 3000);
                    throw new Error(data.message);
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Maintenance mode set successfully - no alert needed
                    // showStatusAlert(`<strong>Success:</strong> Maintenance mode activated for ${<?php echo json_encode($device_name); ?>}`, 'success');
                } else {
                    throw new Error(data.message || 'Failed to set maintenance mode');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (!error.message.includes('Error:')) {
                    showStatusAlert(`<strong>Error:</strong> Failed to initialize maintenance mode. Please try again.`, 'danger');
                }
            });
        }

        // Validate device status when page loads
        document.addEventListener('DOMContentLoaded', function() {
            validateDeviceStatus();
        });

        // Enable maintenance mode when page loads
        function setMaintenanceMode(mode) {
            fetch('gs_DB/maintenance_control.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=maintenance_' + mode
            });
        }

        // Send initial maintenance signal
        (async function initializeMaintenance() {
            // First create the maintenance mode file
            await fetch('gs_DB/set_maintenance_mode.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'mode=enable'
            });

            // Then send the maintenance signal through the command channel
            await fetch('gs_DB/maintenance_control.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=maintenance_start'
            });
        })();

        // Set up an interval to keep maintenance mode active
        const maintenanceInterval = setInterval(() => {
            fetch('gs_DB/maintenance_control.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=maintenance_keep'
            });
        }, 500);

        // Function to handle cleanup
        async function cleanupMaintenance() {
            clearInterval(maintenanceInterval);
            
            // Send request to end maintenance mode
            await fetch('gs_DB/end_maintenance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            });
        }

        // Handle page unload
        window.addEventListener('beforeunload', function(e) {
            // Cancel the event
            e.preventDefault();
            // Chrome requires returnValue to be set
            e.returnValue = '';
            
            // Use sendBeacon for reliable cleanup during page unload
            navigator.sendBeacon('gs_DB/end_maintenance.php');
        });

        // Also handle when user clicks "Dashboard" or "Logout"
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
                    if (!statusDiv) return; // Guard against null element
                    
                    // Log sorter status to console
                    console.log('Sorter Status:', {
                        status: data.status,
                        timestamp: new Date().toISOString(),
                        details: data
                    });
                    
                    // Check device status from the devices array
                    const device = data.devices?.[0];
                    if (device && device.status === 'online') {
                        statusDiv.className = 'connection-status alert mb-4 alert-success';
                        statusDiv.innerHTML = '✅ GoSort Python App is connected and running';
                        // Enable all control buttons (only if no operation is in progress)
                        if (!currentServoOperation) {
                            document.querySelectorAll('.card-body .btn, .servo-position button, .btn-group-vertical .btn, .control-section .btn, .d-grid .btn').forEach(btn => btn.disabled = false);
                        }
                    } else {
                        statusDiv.className = 'connection-status alert mb-4 alert-danger';
                        statusDiv.innerHTML = '❌ GoSort Python App is not running - Controls disabled';
                        // Disable all control buttons
                        document.querySelectorAll('.card-body .btn, .servo-position button, .btn-group-vertical .btn, .control-section .btn, .d-grid .btn').forEach(btn => btn.disabled = true);
                    }
                })
                .catch(error => {
                    const statusDiv = document.querySelector('.connection-status');
                    if (!statusDiv) return; // Guard against null element
                    
                    // Don't disable controls on connection check error during maintenance
                    statusDiv.className = 'connection-status alert mb-4 alert-warning';
                    statusDiv.innerHTML = '⚠️ Error checking connection status, but maintenance mode is active';
                });
        }

        // Check connection status every second
        setInterval(updateConnectionStatus, 1000);
        updateConnectionStatus(); // Initial check

        let currentServoOperation = null;

        // Operation timing based on Arduino code analysis
        const OPERATION_TIMINGS = {
            'zdeg': 2000,    // 4 × 500ms delays = 2000ms
            'ndeg': 2000,    // 4 × 500ms delays = 2000ms  
            'odeg': 2000,    // 4 × 500ms delays = 2000ms
            'unclog': 3500,  // 3000ms hold + 500ms movement = 3500ms
            'sweep1': 4000,  // 4 × 1000ms delays = 4000ms
            'sweep2': 5000   // 4 × 1000ms + 1000ms setup = 5000ms
        };

        function moveServo(position) {
            // If there's already an operation in progress, ignore new requests
            if (currentServoOperation) {
                console.log('Operation already in progress, ignoring new request');
                return;
            }

            const statusDiv = document.getElementById('status');
            
            // Get operation time from timing table
            const operationTime = OPERATION_TIMINGS[position] || 2000;
            
            // Create an AbortController for this operation
            const controller = new AbortController();
            currentServoOperation = controller;
            
            // Disable all control buttons while command is executing
            const controlButtons = document.querySelectorAll('.card-body .btn, .servo-position button, .btn-group-vertical .btn, .control-section .btn, .d-grid .btn');
            controlButtons.forEach(btn => {
                btn.disabled = true;
            });
            
            // Show floating progress bar
            const floatingProgress = document.getElementById('floatingProgress');
            const progressTitle = document.getElementById('progressTitle');
            const progressTime = document.getElementById('progressTime');
            const progressFill = document.getElementById('progressFill');
            const progressStatus = document.getElementById('progressStatus');
            
            // Set operation title
            const operationNames = {
                'zdeg': 'Moving to Bio',
                'ndeg': 'Moving to Non-Bio', 
                'odeg': 'Moving to Recyclable',
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
            
            // Start progress animation
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += 2; // Update every 40ms for smooth animation
                progressFill.style.width = Math.min(progress, 100) + '%';
                progressTime.textContent = Math.floor((progress / 100) * (operationTime / 1000)) + 's';
                
                if (progress >= 100) {
                    clearInterval(progressInterval);
                    progressStatus.textContent = 'Waiting for completion...';
                }
            }, operationTime / 50); // 50 steps over the operation time

                        // Get device identity from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const deviceIdentity = urlParams.get('identity');

            fetch('gs_DB/save_maintenance_command.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    device_identity: deviceIdentity,
                    command: position
                }),
                signal: controller.signal
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(data => {
                // Start polling for command completion instead of using fixed timer
                pollCommandCompletion(deviceIdentity, position, statusDiv, controlButtons, controller);
            })
            .catch(error => {
                if (error.name === 'AbortError') {
                    console.log('Servo operation was aborted');
                    return;
                }
                
                console.error('Error:', error);
                statusDiv.className = 'alert mt-3 alert-danger';
                if (error.message.includes('400')) {
                    statusDiv.textContent = 'Error: Invalid command. Please try again.';
                } else {
                    statusDiv.textContent = 'Error: ' + error.message;
                }
                
                // Re-enable control buttons if there's an error
                const controlButtons = document.querySelectorAll('.card-body .btn, .servo-position button, .btn-group-vertical .btn, .control-section .btn, .d-grid .btn');
                controlButtons.forEach(btn => {
                    btn.disabled = false;
                });
                currentServoOperation = null;
            });
        }

        // Function to poll for command completion
        function pollCommandCompletion(deviceIdentity, command, statusDiv, controlButtons, controller) {
            let pollCount = 0;
            const maxPolls = 60; // Maximum 60 seconds of polling (60 * 1000ms)
            const pollInterval = 1000; // Poll every 1 second
            
            const floatingProgress = document.getElementById('floatingProgress');
            const progressFill = document.getElementById('progressFill');
            const progressStatus = document.getElementById('progressStatus');
            const progressTime = document.getElementById('progressTime');
            
            const pollTimer = setInterval(() => {
                pollCount++;
                
                // Check if operation was aborted
                if (controller.signal.aborted) {
                    clearInterval(pollTimer);
                    floatingProgress.style.display = 'none';
                    return;
                }
                
                // Check if we've exceeded max polling time
                if (pollCount > maxPolls) {
                    clearInterval(pollTimer);
                    
                    // Show timeout in floating progress
                    progressStatus.textContent = 'Operation timeout - buttons re-enabled';
                    progressFill.style.background = 'linear-gradient(90deg, #dc3545, #c82333)';
                    progressFill.classList.remove('animated');
                    
                    // Re-enable control buttons
                    controlButtons.forEach(btn => {
                        btn.disabled = false;
                    });
                    currentServoOperation = null;
                    
                    // Hide progress bar after timeout
                    setTimeout(() => {
                        floatingProgress.style.display = 'none';
                    }, 3000);
                    return;
                }
                
                // Update polling progress
                const pollProgress = Math.min((pollCount / maxPolls) * 100, 100);
                progressFill.style.width = pollProgress + '%';
                progressTime.textContent = pollCount + 's';
                progressStatus.textContent = `Polling for completion... (${pollCount}/${maxPolls})`;
                
                // Poll the database to check if command has been executed
                fetch('gs_DB/check_maintenance_commands.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        device_identity: deviceIdentity
                    })
                })
                .then(response => response.json())
                .then(data => {
                    // If no command is returned, it means the command has been executed
                    if (data.success && !data.command) {
                        clearInterval(pollTimer);
                        
                        // Show success in floating progress
                        progressStatus.textContent = 'Operation completed successfully!';
                        progressFill.style.background = 'linear-gradient(90deg, #28a745, #20c997)';
                        progressFill.classList.remove('animated');
                        progressFill.style.width = '100%';
                        
                        // Re-enable control buttons
                        controlButtons.forEach(btn => {
                            btn.disabled = false;
                        });
                        currentServoOperation = null;
                        
                        // Hide progress bar after success
                        setTimeout(() => {
                            floatingProgress.style.display = 'none';
                        }, 2000);
                    }
                    // If command is still there, continue polling
                    else if (data.success && data.command === command) {
                        // Continue polling - progress already updated above
                    }
                })
                .catch(error => {
                    console.error('Error polling command status:', error);
                    progressStatus.textContent = 'Network error - retrying...';
                    // Continue polling even if there's an error
                });
            }, pollInterval);
        }

        // Function to show operation confirmation dialog
        function confirmOperation(command, operationName) {
            const operationModal = new bootstrap.Modal(document.getElementById('operationModal'));
            document.getElementById('operationName').textContent = operationName;
            
            // Set up the confirm button to execute the operation
            const confirmBtn = document.getElementById('confirmOperationBtn');
            confirmBtn.onclick = function() {
                operationModal.hide();
                moveServo(command);
            };
            
            operationModal.show();
        }

        document.getElementById('shutdownBtn').addEventListener('click', function() {
            // Disable shutdown button if operation is in progress
            if (currentServoOperation) {
                alert('Please wait for the current operation to complete before shutting down.');
                return;
            }
            
            var shutdownModal = new bootstrap.Modal(document.getElementById('shutdownModal'));
            shutdownModal.show();
        });
        document.getElementById('confirmShutdownBtn').addEventListener('click', function() {
            var shutdownModalEl = document.getElementById('shutdownModal');
            var shutdownModal = bootstrap.Modal.getInstance(shutdownModalEl);
            shutdownModal.hide();
            
            // Disable all buttons during shutdown
            const allButtons = document.querySelectorAll('.card-body .btn, .servo-position button, .btn-group-vertical .btn, .control-section .btn, .d-grid .btn');
            allButtons.forEach(btn => {
                btn.disabled = true;
            });
            
            // Show floating progress for shutdown
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
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    device_identity: deviceIdentity,
                    command: 'shutdown'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    progressStatus.textContent = '✅ Shutdown command sent. The device will shut down shortly.';
                    progressFill.style.background = 'linear-gradient(90deg, #28a745, #20c997)';
                    progressFill.classList.remove('animated');
                    progressFill.style.width = '100%';
                    
                    // Re-enable buttons after shutdown command is sent
                    setTimeout(() => {
                        allButtons.forEach(btn => {
                            btn.disabled = false;
                        });
                        floatingProgress.style.display = 'none';
                    }, 3000);
                } else {
                    progressStatus.textContent = '❌ Failed to send shutdown command: ' + data.message;
                    progressFill.style.background = 'linear-gradient(90deg, #dc3545, #c82333)';
                    progressFill.classList.remove('animated');
                    
                    // Re-enable buttons on error
                    allButtons.forEach(btn => {
                        btn.disabled = false;
                    });
                    
                    // Hide progress bar after error
                    setTimeout(() => {
                        floatingProgress.style.display = 'none';
                    }, 3000);
                }
            })
            .catch(error => {
                progressStatus.textContent = '❌ Error sending shutdown command: ' + error.message;
                progressFill.style.background = 'linear-gradient(90deg, #dc3545, #c82333)';
                progressFill.classList.remove('animated');
                console.error('Error:', error);
                
                // Re-enable buttons on error
                allButtons.forEach(btn => {
                    btn.disabled = false;
                });
                
                // Hide progress bar after error
                setTimeout(() => {
                    floatingProgress.style.display = 'none';
                }, 3000);
            });
        });
    </script>
    <script>
    // Quadrant mapping logic
    let quadrantMap = {zdeg: 'bio', ndeg: 'nbio', odeg: 'recyc'};
    // Update button labels/colors
    function updateQuadrantButtons() {
        const mapToLabel = {bio: 'Bio', nbio: 'Non-Bio', recyc: 'Recyclable'};
        const mapToClass = {bio: 'btn-outline-primary', nbio: 'btn-outline-secondary', recyc: 'btn-outline-warning'};
        ['zdeg','ndeg','odeg'].forEach(q => {
            const btn = document.getElementById('btn-' + q);
            btn.textContent = mapToLabel[quadrantMap[q]];
            btn.className = 'btn quadrant-btn mx-2 ' + mapToClass[quadrantMap[q]];
        });
        renderServoControls();
        updateMappingPreview();
    }
    
    function updateMappingPreview() {
        const previewContent = document.getElementById('mapping-preview-content');
        if (!previewContent) return;
        
        const mapToLabel = {bio: 'Bio', nbio: 'Non-Bio', recyc: 'Recyclable'};
        const servoLabels = {zdeg: 'Right', ndeg: 'Center', odeg: 'Left'};
        
        let preview = '';
        for (const [servoKey, trashType] of Object.entries(quadrantMap)) {
            preview += `<div class="mb-1"><strong>${servoLabels[servoKey]}:</strong> ${mapToLabel[trashType]}</div>`;
        }
        previewContent.innerHTML = preview;
    }
    function renderServoControls() {
        const trashTypes = ['bio', 'nbio', 'recyc'];
        const mapToLabel = {bio: 'Move to Bio', nbio: 'Move to Non-Bio', recyc: 'Move to Recyclable'};
        const mapToClass = {bio: 'btn-success', nbio: 'btn-success', recyc: 'btn-success'};
        let html = '';
        for (const trashType of trashTypes) {
            // Find which servo position is mapped to this trash type
            let mappedServo = null;
            for (const [servoKey, mappedType] of Object.entries(quadrantMap)) {
                if (mappedType === trashType) {
                    mappedServo = servoKey;
                    break;
                }
            }
            if (mappedServo) {
                html += `<button class="btn ${mapToClass[trashType] || 'btn-secondary'} btn-lg mb-2" onclick="confirmOperation('${mappedServo}', '${mapToLabel[trashType]}')">${mapToLabel[trashType]}</button>`;
            }
        }
        document.getElementById('dynamic-servo-controls').innerHTML = html;
    }
    // Fetch mapping from backend on page load
    window.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const deviceIdentity = urlParams.get('identity');
        if (!deviceIdentity) { updateQuadrantButtons(); return; }
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
    // Show select menu for quadrant
    function showQuadrantSelect(q) {
        const current = quadrantMap[q];
        const options = [
            {val:'bio', label:'Bio'},
            {val:'nbio', label:'Non-Bio'},
            {val:'recyc', label:'Recyc'}
        ];
        let html = '<div id="quadrant-select-modal" class="modal" tabindex="-1" style="display:block;background:rgba(0,0,0,0.3)"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Select Trash Type</h5><button type="button" class="btn-close" onclick="closeQuadrantSelect()"></button></div><div class="modal-body">';
        options.forEach(opt => {
            html += `<button class="btn btn-block btn-lg w-100 my-1 ${current===opt.val?'btn-primary':'btn-outline-primary'}" onclick="setQuadrantType('${q}','${opt.val}')">${opt.label}</button>`;
        });
        html += '</div></div></div></div>';
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
    document.getElementById('btn-zdeg').onclick = () => showQuadrantSelect('zdeg');
    document.getElementById('btn-ndeg').onclick = () => showQuadrantSelect('ndeg');
    document.getElementById('btn-odeg').onclick = () => showQuadrantSelect('odeg');
    // Save mapping to backend
    function saveQuadrantMapping() {
        // Check if operation is in progress
        if (currentServoOperation) {
            alert('Please wait for the current operation to complete before saving mapping.');
            return;
        }
        
        const urlParams = new URLSearchParams(window.location.search);
        const deviceIdentity = urlParams.get('identity');
        // Check for duplicate trash types
        const values = [quadrantMap.zdeg, quadrantMap.ndeg, quadrantMap.odeg];
        const unique = new Set(values);
        if (unique.size < 3) {
            alert('Same Trash types are not allowed');
            return;
        }
        
        // Disable save button during save operation
        const saveBtn = document.querySelector('button[onclick="saveQuadrantMapping()"]');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        }
        
        fetch('gs_DB/save_sorter_mapping.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                device_identity: deviceIdentity,
                zdeg: quadrantMap.zdeg,
                ndeg: quadrantMap.ndeg,
                odeg: quadrantMap.odeg
            })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert('Mapping saved!');
            } else {
                alert('Error saving mapping: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => alert('Error saving mapping: ' + err))
        .finally(() => {
            // Re-enable save button
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Mapping';
            }
        });
    }
    // Initial
    updateQuadrantButtons();
    </script>
</body>
</html>

