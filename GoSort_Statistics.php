<?php
session_start();
require_once 'gs_DB/connection.php';


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

// Get device info if device ID is provided
$device_id = $_GET['device'] ?? null;
$device_info = null;
if ($device_id) {
    $stmt = $pdo->prepare("SELECT * FROM sorters WHERE id = ?");
    $stmt->execute([$device_id]);
    $device_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device_info) {
        header("Location: GoSort_Sorters.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $category = $_POST['category'] ?? '';
    
    if ($category) {
        $sorted = '';
        switch($category) {
            case 'Bio':
                $sorted = 'biodegradable';
                break;
            case 'Non-Bio':
                $sorted = 'non-biodegradable';
                break;
            case 'Recyclables':
                $sorted = 'recyclable';
                break;
        }
        $stmt = $pdo->prepare("INSERT INTO trash_sorted (sorted, user_id, device_id) VALUES (?, ?, ?)");
        $stmt->execute([$sorted, $_SESSION['user_id'], $device_id]);
    }
}

$query = "SELECT 
    CASE 
        WHEN sorted = 'biodegradable' THEN 'Bio'
        WHEN sorted = 'non-biodegradable' THEN 'Non-Bio'
        WHEN sorted = 'recyclable' THEN 'Recyclables'
    END as category,
    COUNT(*) as total_count 
    FROM trash_sorted";

// Just use GROUP BY for now until we add device_id support
$stmt = $pdo->query($query . " GROUP BY sorted");
$wasteData = $stmt->fetchAll(PDO::FETCH_ASSOC);


$categories = [];
$counts = [];
foreach ($wasteData as $data) {
    $categories[] = $data['category'];
    $counts[] = intval($data['total_count']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Dashboard</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success mb-4">
        <div class="container">
            <a class="navbar-brand" href="GoSort_Sorters.php">GoSort Dashboard</a>
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="GoSort_Sorters.php">Sorters</a>
                <a class="nav-link active" href="GoSort_Statistics.php">Overall Statistics</a>
            </div>
            <?php if ($device_info): ?>
                <div class="d-flex gap-2">
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#maintenanceModal">Maintenance</button>
                    <a href="GoSort_Sorters.php" class="btn btn-primary">Back to Sorters</a>
                    <a href="?logout=1" class="btn btn-light">Logout</a>
                </div>
            <?php else: ?>
                <div>
                    <a href="?logout=1" class="btn btn-light">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <?php if ($device_info): ?>
    <div class="container mb-4">
        <div class="alert alert-info">
            <h4 class="mb-0">
                <i class="bi bi-info-circle-fill"></i>
                Viewing Statistics for: <?php echo htmlspecialchars($device_info['device_name']); ?>
                <span class="badge <?php 
                    echo match($device_info['status']) {
                        'online' => 'bg-success',
                        'maintenance' => 'bg-warning',
                        default => 'bg-danger'
                    };
                ?>">
                    <?php echo ucfirst(htmlspecialchars($device_info['status'])); ?>
                </span>
            </h4>
        </div>
    </div>
    <?php endif; ?>

    <!-- Maintenance Confirmation Modal -->
    <div class="modal fade" id="maintenanceModal" tabindex="-1" aria-labelledby="maintenanceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="maintenanceModalLabel">Maintenance Mode Warning</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Maintenance will disable the trash sorter device, continue?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="GoSort_Maintenance.php" class="btn btn-warning">Continue to Maintenance</a>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['maintenance_error']) && $_GET['maintenance_error'] === 'active'): ?>
    <!-- Maintenance Error Modal -->
    <div class="modal fade" id="maintenanceErrorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Maintenance Mode Active</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Someone is already in maintenance mode: <strong><?php echo htmlspecialchars($_GET['user'] ?? 'Unknown User'); ?></strong>
                    </div>
                    <p>Please try again later when maintenance mode is free.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Show the error modal if we have a maintenance error
        document.addEventListener('DOMContentLoaded', function() {
            const maintenanceErrorModal = new bootstrap.Modal(document.getElementById('maintenanceErrorModal'));
            maintenanceErrorModal.show();
        });
    </script>
    <?php endif; ?>

    <div class="container">
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        Add New Waste Data (!UNIT TESTING! REMOVE IN RELEASE VERSION)
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="Non-Bio">Non-Bio</option>
                                    <option value="Bio">Bio</option>
                                    <option value="Recyclables">Recyclables</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success">Add Sorted Item</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        Waste Distribution
                    </div>
                    <div class="card-body">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                Waste Data Table
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Total Items Sorted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wasteData as $data): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($data['category']); ?></td>
                            <td><?php echo number_format($data['total_count']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        let chart;
        

        function updateTable(data) {
            const tbody = document.querySelector('table tbody');
            tbody.innerHTML = '';
            data.forEach(item => {
                tbody.innerHTML += `
                    <tr>
                        <td>${item.category}</td>
                        <td>${new Intl.NumberFormat().format(item.total_count)}</td>
                    </tr>
                `;
            });
        }


        function updateChart(data) {
            const categories = data.map(item => item.category);
            const counts = data.map(item => item.total_count);

            if (!chart) {
        
                const ctx = document.getElementById('pieChart').getContext('2d');
                chart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: categories,
                        datasets: [{
                            data: counts,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.8)',
                                'rgba(75, 192, 192, 0.8)',
                                'rgba(255, 205, 86, 0.8)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        },
                        animation: {
                            duration: 500
                        }
                    }
                });
            } else {

                chart.data.labels = categories;
                chart.data.datasets[0].data = counts;
                chart.update();
            }
        }


        function fetchData() {
            fetch('gs_DB/get_data.php')
                .then(response => response.json())
                .then(data => {
                    updateTable(data);
                    updateChart(data);
                })
                .catch(error => console.error('Error fetching data:', error));
        }


        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    fetchData(); 
                }
            })
            .catch(error => console.error('Error submitting form:', error));
        });


        updateChart(<?php echo json_encode($wasteData); ?>);

        setInterval(fetchData, 1000);
    </script>
</body>
</html>
