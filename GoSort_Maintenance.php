<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: GoSort_Login.php");
    exit();
}

require_once __DIR__ . '/gs_DB/connection.php';

// Get device info
$device_id = $_GET['device'] ?? null;
$device_name = $_GET['name'] ?? 'Unknown Device';
$device_identity = $_GET['identity'] ?? null;

// Check for missing parameters
$missing = [];
if (!$device_id) $missing[] = 'device';
if (!$device_identity) $missing[] = 'identity';

if (!empty($missing)) {
    header("Location: GoSort_Sorters.php?error=missing_params&params=" . implode(',', $missing));
    exit();
}

// First check if the device exists and get its status
$stmt = $pdo->prepare("SELECT * FROM sorters WHERE device_identity = ?");
$stmt->execute([$device_identity]);
$device = $stmt->fetch();

// Verify device exists and check ID
if (!$device) {
    header("Location: GoSort_Sorters.php?error=device_not_found&identity=" . urlencode($device_identity));
    exit();
}

if ($device['id'] != $device_id) {
    header("Location: GoSort_Sorters.php?error=device_mismatch&id=" . urlencode($device_id));
    exit();
}

// Check if device is online
if ($device['status'] !== 'online') {
    header("Location: GoSort_Sorters.php?error=device_offline&device=" . urlencode($device_name));
    exit();
}

// Check if already in maintenance mode
if ($device['maintenance_mode'] == 1) {
    header("Location: GoSort_Sorters.php?error=already_in_maintenance&device=" . urlencode($device_name));
    exit();
}

// Set maintenance mode only if device is online
$stmt = $pdo->prepare("UPDATE sorters SET maintenance_mode = 1 WHERE id = ? AND device_identity = ? AND status = 'online'");
$result = $stmt->execute([$device_id, $device_identity]);

if (!$result) {
    header("Location: GoSort_Sorters.php?error=database_error");
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
            opacity: 0.65;
            cursor: not-allowed;
            pointer-events: none;
            background-color: #6c757d;
            border-color: #6c757d;
            color: #fff;
        }
        .btn-success:disabled {
            background-color: #198754;
            border-color: #198754;
        }
        .btn-danger:disabled {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-warning:disabled {
            background-color: #ffc107;
            border-color: #ffc107;
        }
        .btn-info:disabled {
            background-color: #0dcaf0;
            border-color: #0dcaf0;
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
                        <h4 class="card-title text-center mb-4">Servo Control</h4>
                        <div class="mb-4">
                            <h5 class="text-center">Move Controls *WARNING: DON'T INITIATE WHEN CLOGGED!*</h5>
                            <div class="d-grid gap-3">
                                <button class="btn btn-success btn-lg" onclick="moveServo('bio')">Move to Bio</button>
                                <button class="btn btn-danger btn-lg" onclick="moveServo('nbio')">Move to Non-Bio</button>
                                <button class="btn btn-success btn-lg" onclick="moveServo('recyc')">Move to Recyclable</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <h5 class="text-center">Unclog Control</h5>
                            <p class="text-muted text-center small mb-3">Will tilt mechanism up for 3 seconds while maintaining current pan position</p>
                            <div class="d-grid">
                                <button class="btn btn-warning btn-lg" onclick="moveServo('unclog')">Unclog Current Section</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <h5 class="text-center">Test Controls *WARNING: DON'T INITIATE WHEN CLOGGED!*</h5>
                            <p class="text-muted text-center small mb-3">Test servo movements</p>
                            <div class="d-grid gap-3">
                                <button class="btn btn-info btn-lg" onclick="moveServo('sweep1')">Test Pan Sweep Only</button>
                                <button class="btn btn-info btn-lg" onclick="moveServo('sweep2')">Test Full Sweep</button>
                            </div>
                        </div>
                        <div id="status" class="alert mt-3" style="display: none;"></div>
                        <div class="d-grid mt-4">
                            <button class="btn btn-dark btn-lg" id="shutdownBtn">Shut Down Device</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
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
                        // Enable all control buttons
                        document.querySelectorAll('.btn').forEach(btn => btn.disabled = false);
                    } else {
                        statusDiv.className = 'connection-status alert mb-4 alert-danger';
                        statusDiv.innerHTML = '❌ GoSort Python App is not running - Controls disabled';
                        // Disable all control buttons
                        document.querySelectorAll('.btn').forEach(btn => btn.disabled = true);
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

        function moveServo(position) {
            // If there's already an operation in progress, ignore new requests
            if (currentServoOperation) {
                return;
            }

            const statusDiv = document.getElementById('status');
            const buttons = document.querySelectorAll('.btn');
            
            // Create an AbortController for this operation
            const controller = new AbortController();
            currentServoOperation = controller;
            
            // Special handling for unclog operation which takes longer (3 seconds hold)
            const operationTime = position === 'unclog' ? 5000 : 2000; // 3s hold + 2s movement for unclog, 2s for normal moves
            
            // Disable all control buttons while command is executing
            const controlButtons = document.querySelectorAll('.card-body .btn');
            controlButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.65';
            });
            
            statusDiv.style.display = 'block';
            statusDiv.className = 'alert mt-3 alert-info';
            statusDiv.textContent = 'Operating...';

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
                statusDiv.className = 'alert mt-3 alert-success';
                statusDiv.textContent = 'Done';
                
                // Wait for the full servo operation to complete (matching Arduino timing)
                // Arduino uses 4 delay(500) = 2000ms total for movement
                setTimeout(() => {
                    // Re-enable control buttons after servo completes all movements
                    const controlButtons = document.querySelectorAll('.card-body .btn');
                    controlButtons.forEach(btn => {
                        btn.disabled = false;
                        btn.style.opacity = '1';
                    });
                    currentServoOperation = null;
                }, operationTime);
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
                const controlButtons = document.querySelectorAll('.card-body .btn');
                controlButtons.forEach(btn => {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                });
                currentServoOperation = null;
            });
        }

        document.getElementById('shutdownBtn').addEventListener('click', function() {
            if (!confirm('Are you sure you want to shut down this device? This will force the computer to power off immediately.')) {
                return;
            }
            const statusDiv = document.getElementById('status');
            statusDiv.style.display = 'block';
            statusDiv.className = 'alert mt-3 alert-info';
            statusDiv.textContent = 'Sending shutdown command...';
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
                    statusDiv.className = 'alert mt-3 alert-success';
                    statusDiv.textContent = '✅ Shutdown command sent. The device will shut down shortly.';
                } else {
                    statusDiv.className = 'alert mt-3 alert-danger';
                    statusDiv.textContent = '❌ Failed to send shutdown command: ' + data.message;
                }
            })
            .catch(error => {
                statusDiv.className = 'alert mt-3 alert-danger';
                statusDiv.textContent = '❌ Error sending shutdown command: ' + error.message;
                console.error('Error:', error);
            });
        });
    </script>
</body>
</html>
