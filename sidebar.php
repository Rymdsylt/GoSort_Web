<?php
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';

$currentPage = basename($_SERVER['PHP_SELF']);

if (!isset($_SESSION['user_id'])) {
    header("Location: GoSort_Login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$accountName = $user['username'] ?? "Pateros Catholic School";
$accountRole = $user['email'] ?? "Admin@gmail.com";
$accountLogo = $user['logo'] ?? "images/logos/pcs.svg";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GOSORT Sidebar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/dark-mode-global.css" rel="stylesheet">
    <style>
        body {
            font-family: "Poppins", sans-serif !important;
            background-color: #f8fff6 !important;
        }
        .sidebar {
            width: 220px;
            top: 1rem;
            left: 1rem;
            bottom: 1rem;
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            position: fixed;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 0.5rem 1rem;
            z-index: 1000;
            overflow: hidden;
        }
        .logo-section {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 0.4rem;
            min-height: 56px;
        }
        .logo-section .logo-full {
            display: block;
            height: 25px;
        }
        .nav-link {
            display: flex;
            align-items: center;
            border-radius: 10px;
            padding: 0.5rem 0.6rem;
            color: #444;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 3px;
            transition: all 0.2s ease-in-out;
            white-space: nowrap;
            height: 38px;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif !important;
        }
        .nav-link i {
            font-size: 1rem;
            margin-right: 10px;
            min-width: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .nav-link.active {
            background-color: rgba(190, 219, 187, 0.94);
            color: #1a1a1a !important;
            font-weight: 600;
            border-left: 3px solid #58C542;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .nav-link.active i {
            color: #58C542 !important;
        }
        .nav-link:hover {
            background-color: rgb(236, 251, 234);
            color: #000;
        }
        .nav-link.active:hover {
            background-color: rgba(190, 219, 187, 0.94);
            color: #1a1a1a !important;
            font-weight: 600;
            border-left: 3px solid #58C542;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
<div class="sidebar" id="sidebar">

    <div>
        <div class="logo-section">
            <img class="logo-full" src="images/logos/logonavbar.svg" alt="Full Logo">
        </div>

        <ul class="nav flex-column flex-grow-1">
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'GoSort_Dashboard.php' ? 'active' : ''; ?>" href="GoSort_Dashboard.php">
                    <i class="bi bi-columns-gap"></i> <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'GoSort_Sorters.php' ? 'active' : ''; ?>" href="GoSort_Sorters.php">
                    <i class="bi bi-cpu"></i> <span>Devices</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'GoSort_AnalyticsNavpage.php' || $currentPage == 'GoSort_Statistics.php') ? 'active' : ''; ?>" href="GoSort_AnalyticsNavpage.php">
                    <i class="bi bi-bar-chart"></i> <span>Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'GoSort_MaintenanceNavpage.php' || $currentPage == 'GoSort_Maintenance.php') ? 'active' : ''; ?>" href="GoSort_MaintenanceNavpage.php">
                    <i class="bi bi-clock-history"></i> <span>Maintenance</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'GoSort_WasteMonitoringNavpage.php' || $currentPage == 'GoSort_LiveMonitor.php') ? 'active' : ''; ?>" href="GoSort_WasteMonitoringNavpage.php">
                    <i class="bi bi-trash"></i> <span>Waste Monitoring</span>
                </a>
            </li>
        </ul>
    </div>

</div>
</body>
</html>