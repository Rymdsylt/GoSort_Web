<?php
session_start();
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

// Get device info if device ID is provided
$device_id = $_GET['device'] ?? null;
$device_identity = $_GET['identity'] ?? null;
$device_info = null;

// Prepare the base SQL query
$base_query = "
    SELECT 
        trash_type,
        COUNT(*) as count,
        is_maintenance,
        DATE(sorted_at) as date
    FROM sorting_history";

if ($device_id && $device_identity) {
    // Get specific device info
    $stmt = $pdo->prepare("SELECT * FROM sorters WHERE id = ? AND device_identity = ?");
    $stmt->execute([$device_id, $device_identity]);
    $device_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device_info) {
        header("Location: GoSort_Sorters.php");
        exit();
    }

    // Get sorting history for specific device
    $stmt = $pdo->prepare($base_query . " 
        WHERE device_identity = ?
        GROUP BY trash_type, is_maintenance, DATE(sorted_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$device_identity]);
} else {
    // Get overall sorting history
    $stmt = $pdo->prepare($base_query . " 
        GROUP BY trash_type, is_maintenance, DATE(sorted_at)
        ORDER BY date ASC
    ");
    $stmt->execute();
}

// Fetch and process data for charts
$sorting_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$dates = array();
$bio_counts = array();
$nbio_counts = array();
$hazardous_counts = array();
$maintenance_counts = array();

foreach ($sorting_data as $record) {
    $date = $record['date'];
    if (!in_array($date, $dates)) {
        $dates[] = $date;
    }
    
    $count = intval($record['count']);
    $type = $record['trash_type'];
    $is_maintenance = $record['is_maintenance'];

    if ($is_maintenance) {
        $maintenance_counts[$date][$type] = $count;
    } else {
        switch ($type) {
            case 'bio':
                $bio_counts[$date] = ($bio_counts[$date] ?? 0) + $count;
                break;
            case 'nbio':
                $nbio_counts[$date] = ($nbio_counts[$date] ?? 0) + $count;
                break;
            case 'hazardous':
                $hazardous_counts[$date] = ($hazardous_counts[$date] ?? 0) + $count;
                break;
        }
    }
}

// Get total counts
$total_bio = array_sum($bio_counts);
$total_nbio = array_sum($nbio_counts);
$total_hazardous = array_sum($hazardous_counts);

// Calculate maintenance percentages
$maintenance_bio = 0;
$maintenance_nbio = 0;
$maintenance_hazardous = 0;

foreach ($maintenance_counts as $date_counts) {
    $maintenance_bio += isset($date_counts['bio']) ? $date_counts['bio'] : 0;
    $maintenance_nbio += isset($date_counts['nbio']) ? $date_counts['nbio'] : 0;
        $maintenance_hazardous += isset($date_counts['hazardous']) ? $date_counts['hazardous'] : 0;
    }?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Statistics</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <script src="js/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success mb-4">
        <div class="container">
            <a class="navbar-brand" href="GoSort_Sorters.php">GoSort Dashboard</a>
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="GoSort_Sorters.php">Sorters</a>
                <a class="nav-link active" href="GoSort_Statistics.php">Statistics</a>
            </div>
            <div>
                <a href="GoSort_Sorters.php?logout=1" class="btn btn-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($device_info): ?>
            <h2 class="mb-4">Statistics for <?php echo htmlspecialchars($device_info['device_name']); ?></h2>
        <?php else: ?>
            <h2 class="mb-4">Overall Statistics</h2>
        <?php endif; ?>
            
            <div class="row g-4">
                <!-- Total Counts Card -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Total Sorting Counts</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="totalPieChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Daily Trend Card -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Daily Sorting Trend</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="trendLineChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Maintenance vs Normal Card -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Maintenance vs Normal Operation</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="maintenanceBarChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                // Initialize charts with empty data
                let totalPieChart = new Chart(document.getElementById('totalPieChart'), {
                    type: 'pie',
                    data: {
                        labels: ['Biodegradable', 'Non-Biodegradable', 'Hazardous', 'Mixed'],
                        datasets: [{
                            data: [0, 0, 0, 0],
                            backgroundColor: ['#28a745', '#dc3545', '#17a2b8', '#6c757d']
                        }]
                    }
                });

                let trendLineChart = new Chart(document.getElementById('trendLineChart'), {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Biodegradable',
                            data: [],
                            borderColor: '#28a745',
                            fill: false
                        }, {
                            label: 'Non-Biodegradable',
                            data: [],
                            borderColor: '#dc3545',
                            fill: false
                        }, {
                            label: 'Hazardous',
                            data: [],
                            borderColor: '#17a2b8',
                            fill: false
                        }, {
                            label: 'Mixed',
                            data: [],
                            borderColor: '#6c757d',
                            fill: false
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                let maintenanceBarChart = new Chart(document.getElementById('maintenanceBarChart'), {
                    type: 'bar',
                    data: {
                        labels: ['Biodegradable', 'Non-Biodegradable', 'Hazardous', 'Mixed'],
                        datasets: [{
                            label: 'Normal Operation',
                            data: [0, 0, 0, 0],
                            backgroundColor: '#28a745'
                        }, {
                            label: 'Maintenance Mode',
                            data: [0, 0, 0, 0],
                            backgroundColor: '#ffc107'
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true,
                                stacked: true
                            },
                            x: {
                                stacked: true
                            }
                        }
                    }
                });

                // Function to update charts with new data
                function updateCharts() {
                    const queryParams = new URLSearchParams(window.location.search);
                    const deviceId = queryParams.get('device');
                    const deviceIdentity = queryParams.get('identity');
                    
                    let url = 'gs_DB/get_statistics_data.php';
                    if (deviceId && deviceIdentity) {
                        url += `?device=${deviceId}&identity=${deviceIdentity}`;
                    }

                    fetch(url)
                        .then(response => response.json())
                        .then(data => {
                            // Update Pie Chart
                            totalPieChart.data.datasets[0].data = [
                                data.totals.bio,
                                data.totals.nbio,
                                data.totals.hazardous,
                                data.totals.mixed
                            ];
                            totalPieChart.update();

                            // Update Line Chart
                            trendLineChart.data.labels = data.dates;
                            trendLineChart.data.datasets[0].data = data.trends.bio;
                            trendLineChart.data.datasets[1].data = data.trends.nbio;
                            trendLineChart.data.datasets[2].data = data.trends.hazardous;
                            trendLineChart.data.datasets[3].data = data.trends.mixed;
                            trendLineChart.update();

                            // Update Bar Chart
                            maintenanceBarChart.data.datasets[0].data = [
                                data.maintenance.normal.bio,
                                data.maintenance.normal.nbio,
                                data.maintenance.normal.hazardous,
                                data.maintenance.normal.mixed
                            ];
                            maintenanceBarChart.data.datasets[1].data = [
                                data.maintenance.maintenance.bio,
                                data.maintenance.maintenance.nbio,
                                data.maintenance.maintenance.hazardous,
                                data.maintenance.maintenance.mixed
                            ];
                            maintenanceBarChart.update();
                        })
                        .catch(error => console.error('Error fetching data:', error));
                }

                // Initial update
                updateCharts();

                // Update every second
                setInterval(updateCharts, 1000);
            </script>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
