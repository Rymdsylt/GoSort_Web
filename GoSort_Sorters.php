<?php
session_start();
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';
require_once 'gs_DB/sorters_DB.php';

// Handle logout
if (isset($_GET['logout'])) {
    // Clean up maintenance mode if active
    require_once 'gs_DB/maintenance_tracking.php';
    if (isset($_SESSION['user_id'])) {
        endMaintenanceMode($_SESSION['user_id']);
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
<style>
        body {
            position: relative;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            transition: margin-left 0.3s ease;
            background-color: #F3F3EF !important;
            font-family: 'inter', sans-serif !important;
        }

        .border-dashed {
            border-style: dashed !important;
            border-width: 2px !important;
            background-color: rgba(0,0,0,0.01);
        }
        #main-content-wrapper {
            margin-left: 260px; 
            transition: margin-left 0.3s ease;
            padding: 20px; 
        }
        #main-content-wrapper.collapsed {
            margin-left: 80px; 
        }

        .shadow-dark {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        }

        .badge-online {
        background-color: #CDFFB7 !important; 
        color: #14AE31 !important;
        padding: 6px 10px !important
        }

        .badge-maintenance {
        background-color: #eff5a3ff !important; 
        color: #212529 !important; 
        padding: 6px 10px !important
        }

        .badge-offline {
        background-color: #FFA6A7 !important; 
        color: #FF1E1E !important;
        padding: 6px 10px !important;
        }
        .add-device-card {
        border: 3px dashed #368137 !important;
        border-radius: 16px !important;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        cursor: pointer;
        background-color: #fff;
        }

        .add-device-card:hover {
        background-color: #f9fff5;
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .add-device-icon {
        font-size: 3rem;
        color: #368137;
        margin-bottom: 0.5rem;
        }

        .add-device-text {
        font-size: 1.25rem;
        font-weight: 600;
        color: #000;
        }
        .image {
            width: 30px;
            height: 30px;
            margin-right: 5px;
        }
        .card-title{
            display: flex;
            align-items: center;
        }
        .d-flex
        {
            margin: 0px 5px;
        }
        .badge {
            margin:0px 5px;
        }
        .bi {
            margin-right: 5px;
        }

        .custom-drive-dropdown {
            border-radius: 14px !important;
            box-shadow: 0 4px 24px rgba(60,64,67,0.15), 0 1.5px 4px rgba(60,64,67,0.15);
            border: none;
            padding: 8px !important;
            min-width: 180px !important;
            background: #fff;
        }
        .custom-drive-dropdown .dropdown-item {
            border-radius: 8px;
            margin: 0px;
            padding: 8px 5px;
            color: #202124;
            font-size: 15px;
            box-sizing: border-box;
        }
        .custom-drive-dropdown .dropdown-item:hover, .custom-drive-dropdown .dropdown-item:focus {
            background: #f1f3f4;
            color: #202124;
        
        }
        .custom-drive-dropdown .dropdown-item.text-danger {
            color: #d93025 !important;
        }
        .custom-drive-dropdown .dropdown-item.text-danger:hover {
            background: #fce8e6;
            color: #a50e0e !important;
        }
        .custom-drive-dropdown .dropdown-divider {
            margin: 4px 0;
        }

</style>
<body>
    <?php include 'sidebar.php'; ?>

   <div id="main-content-wrapper">
  <div class="container">

            <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="fw-bold mb-0 mt-3">Devices</h2>
            <!-- Search bar -->
            <div class="input-group mt-3" style="max-width: 300px;">
            <input type="text" class="form-control" placeholder="Search Device">
            <button class="btn btn-outline-secondary" type="button">
                <i class="bi bi-search"></i>
            </button>
            </div>

            </div>
            <hr style="height: 1.5px; background-color: #000; opacity: 1; margin-left:6.5px;" class="mb-2">
            <div class="d-flex justify-content-end mb-2">
            <div class="dropdown">
                <button class="btn btn-link text-dark dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                Sort by
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="sortDropdown">
                <li><a class="dropdown-item" href="#">Last Active</a></li>
                <li><a class="dropdown-item" href="#">Name (A-Z)</a></li>
                <li><a class="dropdown-item" href="#">Status</a></li>
                </ul>
            </div>
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
                 <div class="col">
                    <div class="card h-100 add-device-card" 
                        data-bs-toggle="modal" 
                        data-bs-target="#addDeviceModal">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center text-center">
                        <div class="add-device-icon">
                            <i class="bi bi-plus-square"></i>
                        </div>
                        <div class="add-device-text">
                            Add New Device
                        </div>
                        </div>
                    </div>
                    </div>

                <?php foreach ($sorters as $sorter): ?>
                    <div class="col">
                        <div class="card h-100 shadow-dark border-success border-2 rounded-4" data-device="<?php echo htmlspecialchars($sorter['device_name']); ?>">
                            <div class="card-body">
                                <!-- Header: Device Name + Menu -->
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title fw-bold mb-0">
                                        <img class="image" src="images/icons/devices.svg" alt="Sorter Icon">
                                        <?php echo htmlspecialchars($sorter['device_name']); ?>
                                    </h5>
                                    <!-- Kebab menu -->
                                    <div class="dropdown">
                                        <button class="btn btn-link text-dark p-0" type="button" id="dropdownMenu<?php echo $sorter['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical fs-5"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end custom-drive-dropdown" aria-labelledby="dropdownMenu<?php echo $sorter['id']; ?>">
                                            <li>
                                                <a class="dropdown-item d-flex" href="#" >
                                                    <i class="bi bi-info-circle me-2"></i> Details
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item d-flex" href="GoSort_Statistics.php?device=<?php echo $sorter['id']; ?>&identity=<?php echo urlencode($sorter['device_identity']); ?>">
                                                    <i class="bi bi-bar-chart me-2"></i> Statistics
                                                </a>
                                            </li>
                                            <li>
                                                <button class="dropdown-item d-flex text-danger delete-btn" onclick="confirmDelete('<?php echo $sorter['id']; ?>', '<?php echo htmlspecialchars($sorter['device_name']); ?>')">
                                                    <i class="bi bi-trash me-2"></i> Delete
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex flex-column">
                                        <small class="text-muted mb-1">
                                            <i class="bi bi-geo-alt-fill"></i>
                                            <?php echo htmlspecialchars($sorter['location']); ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i>
                                            <?php echo date('M j, Y', strtotime($sorter['last_active'])); ?>
                                        </small>
                                    </div>
                                   <span class="badge rounded-pill <?php 
                                        echo match($sorter['status']) {
                                            'online' => 'badge-online',
                                            'maintenance' => 'badge-maintenance',
                                            default => 'badge-offline'
                                        };
                                    ?>">
                                        <?php echo ucfirst(htmlspecialchars($sorter['status'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

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

<div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDeviceModalLabel">Add New Device</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addDeviceForm">
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
                        <input type="text" class="form-control" id="deviceIdentity" name="deviceIdentity" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addDeviceForm" class="btn btn-primary">Add Device</button>
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

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
    const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
    const statusMessage = document.getElementById('statusMessage');
    const progressBar = document.querySelector('#statusModal .progress');
    const addDeviceModal = document.getElementById('addDeviceModal');
    
    addDeviceModal.addEventListener('show.bs.modal', function (event) {
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.style.backgroundColor = 'rgba(124, 124, 124, 0.15)';
        }
    });
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
        
        // Auto-dismiss success messages after 5 seconds
        if (type === 'success') {
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        }
    }

    // Function to check maintenance status before proceeding
    function checkMaintenanceStatus(deviceId, deviceName, deviceIdentity) {
        // Show loading state
        showStatusAlert(`Checking status for ${deviceName}...`, 'info', false);
        
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
                // Clear any existing alerts
                document.getElementById('statusAlertContainer').innerHTML = '';
                // Proceed to maintenance page
                window.location.href = `GoSort_Maintenance.php?device=${deviceId}&name=${encodeURIComponent(deviceName)}&identity=${encodeURIComponent(deviceIdentity)}`;
            } else {
                // Show error message
                let alertType = 'danger';
                if (data.error === 'maintenance_active') {
                    alertType = 'warning';
                }
                showStatusAlert(`<strong>Error:</strong> ${data.message}`, alertType);
            }
        })
        .catch(error => {
            showStatusAlert(`<strong>Error:</strong> Failed to check device status. Please try again.`, 'danger');
            console.error('Error:', error);
        });
    }

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
        
        // Convert FormData to JSON
        const jsonData = {
            deviceName: formData.get('deviceName'),
            location: formData.get('location'),
            deviceIdentity: formData.get('deviceIdentity')
        };

        fetch('gs_DB/add_device_new.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(jsonData)
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Server response:', text);
                    try {
                        const jsonError = JSON.parse(text);
                        throw new Error(jsonError.message || 'Server error');
                    } catch (e) {
                        throw new Error('Server returned: ' + text);
                    }
                });
            }
            return response.json();
        })
       .then(data => {
    if (data.success) {
        // Close the Add Device Modal first
        const addModal = bootstrap.Modal.getInstance(document.getElementById('addDeviceModal'));
        if (addModal) {
            addModal.hide();
        }
        
        // Then show success message
        showStatus(`✅ ${data.message}`, false, false);
        
        // Reload the page to show the newly added device
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
        document.getElementById('deviceName').value = 'GS-' + identity;
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

    function shutdownDevice(deviceIdentity, deviceName) {
        if (!confirm(`Are you sure you want to shut down the device "${deviceName}"? This will force the computer to power off immediately.`)) {
            return;
        }
        showStatus(`Sending shutdown command to "${deviceName}"...`, false, true);
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
                showStatus(`✅ Shutdown command sent to "${deviceName}". The device will shut down shortly.`, false, false);
            } else {
                showStatus(`❌ Failed to send shutdown command: ${data.message}`, true, false);
            }
        })
        .catch(error => {
            showStatus(`❌ Error sending shutdown command: ${error.message}`, true, false);
            console.error('Error:', error);
        });
    }

    // Update device statuses every 100ms for immediate offline detection
    setInterval(() => {
        fetch('gs_DB/connection_status.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.devices) {
                    // Update status badges for each device
                    data.devices.forEach(device => {
                        const deviceCard = document.querySelector(`[data-device="${device.device_name}"]`);
                        if (deviceCard) {
                            const badge = deviceCard.querySelector('.badge');
                            const statusClass = {
                                'online': 'badge-online',
                                'offline': 'badge-offline',
                                'maintenance': 'badge-maintenance'
                            }[device.status] || 'badge-offline';

                            badge.className = `badge rounded-pill ${statusClass}`;
                            badge.textContent = device.status.charAt(0).toUpperCase() + device.status.slice(1);

                            // Update last active time
                            const lastActive = deviceCard.querySelector('.last-active');
                            if (lastActive && device.last_active) {
                                const date = new Date(device.last_active);
                                if (!isNaN(date)) {
                                    lastActive.textContent = date.toLocaleString('en-US', {
                                        month: 'short',
                                        day: 'numeric',
                                        year: 'numeric',
                                        hour: 'numeric',
                                        minute: 'numeric',
                                        hour12: true
                                    });
                                }
                            }
                        }
                    });

                    // Show/hide buttons
                    data.devices.forEach(device => {
                        const deviceCard = document.querySelector(`[data-device="${device.device_name}"]`);
                        if (deviceCard) {
                            const maintenanceBtn = deviceCard.querySelector('.maintenance-btn');
                            const deleteBtn = deviceCard.querySelector('.delete-btn');
                            if (device.status === 'online') {
                                maintenanceBtn.style.display = '';
                                deleteBtn.style.display = 'none';
                            } else {
                                maintenanceBtn.style.display = 'none';
                                deleteBtn.style.display = '';
                            }
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error updating device statuses:', error);
            });
    }, 100);
    </script>
    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
