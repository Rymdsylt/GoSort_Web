<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: GoSort_Login.php");
    exit();
}

require_once 'gs_DB/connection.php';
require_once 'gs_DB/maintenance_tracking.php';

// Get device info
$device_id = $_GET['device'] ?? null;
$device_name = $_GET['name'] ?? 'Unknown Device';

if (!$device_id) {
    header("Location: GoSort_Sorters.php");
    exit();
}

// Verify device exists and is not in maintenance
$stmt = $pdo->prepare("SELECT * FROM sorters WHERE id = ?");
$stmt->execute([$device_id]);
$device = $stmt->fetch();

if (!$device || $device['status'] === 'maintenance') {
    header("Location: GoSort_Sorters.php");
    exit();
}

// Check if someone else is already in maintenance mode
$activeSession = getActiveMaintenanceSession();
if ($activeSession && $activeSession['user_id'] != $_SESSION['user_id']) {
    // Someone else is in maintenance mode, redirect back with error
    header("Location: GoSort_Sorters.php?maintenance_error=active&user=" . urlencode($activeSession['userName']));
    exit();
}

// Start maintenance mode for this user
$result = startMaintenanceMode($_SESSION['user_id']);
if ($result['status'] === 'error') {
    header("Location: GoSort_Sorters.php?maintenance_error=active&user=" . urlencode($result['user']));
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
            
            // Create FormData objects for each request
            const modeData = new URLSearchParams();
            modeData.append('mode', 'disable');
            
            const controlData = new URLSearchParams();
            controlData.append('action', 'maintenance_end');
            
            const trackingData = new URLSearchParams();
            trackingData.append('action', 'end_maintenance');
            
            await Promise.all([
                fetch('gs_DB/set_maintenance_mode.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: modeData.toString()
                }),
                fetch('gs_DB/maintenance_control.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: controlData.toString()
                }),
                fetch('gs_DB/maintenance_tracking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: trackingData.toString()
                })
            ]);
        }

        // Handle page unload
        window.addEventListener('beforeunload', function(e) {
            // Cancel the event
            e.preventDefault();
            // Chrome requires returnValue to be set
            e.returnValue = '';
            
            // Perform cleanup synchronously to ensure it happens
            navigator.sendBeacon('gs_DB/maintenance_tracking.php', new URLSearchParams({
                'action': 'end_maintenance'
            }));
            
            cleanupMaintenance();
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
                    
                    if (data.status === 'connected' || data.status === 'maintenance') {
                        statusDiv.className = 'connection-status alert mb-4 alert-success';
                        statusDiv.innerHTML = data.status === 'maintenance' 
                            ? '🔧 GoSort Python App is in maintenance mode'
                            : '✅ GoSort Python App is connected and running';
                        document.querySelectorAll('.btn').forEach(btn => btn.disabled = false);
                    } else {
                        statusDiv.className = 'connection-status alert mb-4 alert-danger';
                        statusDiv.innerHTML = '❌ GoSort Python App is not running - Controls disabled';
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
            statusDiv.textContent = 'Sending command...';

            // Encode the position parameter
            const formData = new URLSearchParams();
            formData.append('action', position);

            fetch('gs_DB/maintenance_control.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString(),
                signal: controller.signal
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(data => {
                statusDiv.className = 'alert mt-3 ' + (data.includes('Success') ? 'alert-success' : 'alert-danger');
                statusDiv.textContent = data || 'Command completed successfully';
                
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
    </script>
</body>
</html>
