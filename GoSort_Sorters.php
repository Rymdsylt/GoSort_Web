<?php
session_start();
require_once 'gs_DB/connection.php';
require_once 'gs_DB/sorters_DB.php';

if (!isset($_SESSION['user_id']) || !isset($_COOKIE['user_logged_in'])) {
    header("Location: GoSort_Login.php");
    exit();
}

// Fetch all sorters from the database
$stmt = $pdo->query("SELECT * FROM sorters ORDER BY last_active DESC");
$sorters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Device addition is now handled by gs_DB/add_device.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Sorters</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">GoSort</a>
            <div class="navbar-nav me-auto">
            </div>
            <div>
                <a href="GoSort_Main.php?logout=1" class="btn btn-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
            <?php foreach ($sorters as $sorter): ?>
            <div class="col">
                <div class="card h-100" data-device="<?php echo htmlspecialchars($sorter['device_name']); ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($sorter['device_name']); ?></h5>
                        <span class="badge <?php 
                            echo match($sorter['status']) {
                                'online' => 'bg-success',
                                'maintenance' => 'bg-warning',
                                default => 'bg-danger'
                            };
                        ?>">
                            <?php echo ucfirst(htmlspecialchars($sorter['status'])); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            <i class="bi bi-geo-alt-fill"></i> 
                            Location: <?php echo htmlspecialchars($sorter['location']); ?>
                        </p>
                        <p class="card-text">
                            <i class="bi bi-clock"></i>
                            Last Active: <span class="last-active"><?php echo date('M j, Y g:i A', strtotime($sorter['last_active'])); ?></span>
                        </p>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between">
                            <a href="GoSort_Main.php?device=<?php echo $sorter['id']; ?>" class="btn btn-primary btn-sm">
                                View Statistics
                            </a>
                            <?php if ($sorter['status'] !== 'maintenance'): ?>
                            <a href="GoSort_Maintenance.php?device=<?php echo $sorter['id']; ?>" class="btn btn-warning btn-sm">
                                Maintenance
                            </a>
                            <?php endif; ?>
                            <?php if ($sorter['status'] === 'offline'): ?>
                            <button class="btn btn-danger btn-sm" onclick="confirmDelete('<?php echo $sorter['id']; ?>', '<?php echo htmlspecialchars($sorter['device_name']); ?>')">
                                Delete
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Add New Device Card -->
            <div class="col">
                <div class="card h-100 border-dashed">
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <button class="btn btn-outline-success btn-lg" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                            <i class="bi bi-plus-lg"></i> Add New Device
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Device Modal -->
    <div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDeviceModalLabel">Add New Sorter Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addDeviceForm" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="deviceName" class="form-label">Device Name</label>
                            <input type="text" class="form-control" id="deviceName" name="deviceName" required>
                        </div>
                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" required>
                        </div>
                        <div class="mb-3">
                            <label for="deviceIdentity" class="form-label">Device Identity</label>
                            <input type="text" class="form-control" id="deviceIdentity" name="deviceIdentity" 
                                   pattern="[A-Za-z0-9]+" 
                                   title="Only letters and numbers allowed"
                                   placeholder="e.g., Sorter1" required>
                            <div class="form-text text-danger">Important: The device must be running and attempting to connect before you can add it here.</div>
                            <div class="form-text">Enter the identity exactly as configured in the device.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Device</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
    .border-dashed {
        border-style: dashed !important;
        border-width: 2px !important;
        background-color: rgba(0,0,0,0.01);
    }
    </style>

    <script src="js/bootstrap.bundle.min.js"></script>


    <div id="statusModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Device Registration Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="statusMessage"></div>
                    <div class="progress mt-3 d-none">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the device "<span id="deleteDeviceName"></span>"?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Device</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
    const statusMessage = document.getElementById('statusMessage');
    const progressBar = document.querySelector('#statusModal .progress');

    function showStatus(message, isError = false, showProgress = false) {
        statusMessage.innerHTML = message;
        statusMessage.className = isError ? 'text-danger' : 'text-success';
        progressBar.className = showProgress ? 'progress mt-3' : 'progress mt-3 d-none';
        if (!statusModal._isShown) {
            statusModal.show();
        }
    }

    document.getElementById('addDeviceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const formData = new FormData(form);
        const deviceIdentity = formData.get('deviceIdentity');
        
        // First check if the device is trying to connect
        showStatus(`Checking if device "${deviceIdentity}" is attempting to connect...`, false, true);
        
        fetch('gs_DB/add_device_new.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error('Server returned: ' + text);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showStatus(`✅ ${data.message}`, false, false);
                // Reload after 2 seconds on success
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                showStatus(`❌ ${data.message}`, true, false);
            }
        })
        .catch(error => {
            showStatus(`❌ Server error: ${error.message}. Please try again or contact support.`, true, false);
            console.error('Error:', error);
        });
    });

    // Pre-fill device name based on identity
    document.getElementById('deviceIdentity').addEventListener('input', function(e) {
        const identity = e.target.value;
        document.getElementById('deviceName').value = 'GoSort-' + identity;
    });

    // Delete device functionality
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    let deviceToDelete = null;

    function confirmDelete(deviceId, deviceName) {
        deviceToDelete = deviceId;
        document.getElementById('deleteDeviceName').textContent = deviceName;
        deleteModal.show();
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (!deviceToDelete) return;

        fetch('gs_DB/delete_device.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                deviceId: deviceToDelete
            })
        })
        .then(response => response.json())
        .then(data => {
            deleteModal.hide();
            if (data.success) {
                showStatus('✅ ' + data.message, false, false);
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                showStatus('❌ ' + data.message, true, false);
            }
        })
        .catch(error => {
            deleteModal.hide();
            showStatus('❌ Error deleting device. Please try again.', true, false);
            console.error('Error:', error);
        });
    });

    // Update device statuses every 5 seconds
    setInterval(() => {
        fetch('gs_DB/connection_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.devices) {
                    // Update status badges for each device
                    data.devices.forEach(device => {
                        const deviceCard = document.querySelector(`[data-device="${device.device_name}"]`);
                        if (deviceCard) {
                            const badge = deviceCard.querySelector('.badge');
                            const statusClass = {
                                'online': 'bg-success',
                                'offline': 'bg-danger',
                                'maintenance': 'bg-warning'
                            }[device.status] || 'bg-danger';

                            // Update badge
                            badge.className = `badge ${statusClass}`;
                            badge.textContent = device.status.charAt(0).toUpperCase() + device.status.slice(1);

                            // Update last active time
                            const lastActive = deviceCard.querySelector('.last-active');
                            if (lastActive) {
                                lastActive.textContent = new Date(device.last_active).toLocaleString();
                            }
                        }
                    });
                }
            })
            .catch(console.error);
    }, 5000);
    </script>
</body>
</html>
