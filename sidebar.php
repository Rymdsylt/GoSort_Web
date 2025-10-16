<?php
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';

$currentPage = basename($_SERVER['PHP_SELF']);

if (!isset($_SESSION['user_id'])) {
    header("Location: GoSort_Login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, lastname FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$accountName = $user['username'] ?? "Pateros Catholic School";
$accountRole = $user['lastnam'] ?? "Admin";
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            font-family: "Inter", sans-serif;
            background-color: #f0f2f5;
        }
        .sidebar {
            width: 260px;
            height: 100vh;
            background-color: #fff;
            border-radius: 0px 20px 20px 0px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 1rem;
            transition: width 0.3s ease;
            z-index: 1000;
            overflow: hidden;
        }
        .sidebar.collapsed {
            width: 70px;
        }
        .logo-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding: 0.4rem;
            min-height: 56px;
        }
        .logo-section .logo-text {
            font-weight: 700;
            font-size: 1.4rem;
            transition: opacity 0.3s ease;
        }
        .logo-section .logo-full {
            display: block;
            height: 32px;
        }
        .logo-section .logo-collapsed {
            display: none;
            height: 32px;
            cursor: pointer;
            margin: 0;
            align-self: center;
        }
        .sidebar.collapsed .logo-section {
            justify-content: center;
            padding: 0.5rem 0;
            min-height: 56px;
        }
        .sidebar.collapsed .logo-section .logo-full {
            display: none;
        }
        .sidebar.collapsed .logo-section .logo-collapsed {
            display: block;
            margin: 0 auto;
            align-self: center;
        }
        
        .sidebar.collapsed .logo-section .logo-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }
        .collapse-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
        }
        .collapse-btn i {
            color: #a7a4a4ff; 
            padding: 2px 10px 0px 10px;
            margin-left:5px;
        }
        .collapse-btn i:hover {
            padding: 2px 10px 0px 10px;
            color: #a7a4a4ff; 
            background-color: #e4f5d0ff;
            border-radius: 10px;
            
        }

        .sidebar.collapsed .collapse-btn .bi-layout-sidebar {
            display: none;
        }
        .nav-link {
            display: flex;
            align-items: center;
            border-radius: 10px;
            padding: 0.6rem 0.4rem;
            color: #444;
            font-weight: 500;
            margin-bottom: 5px;
            transition: all 0.2s ease-in-out;
            white-space: nowrap;
            height: 45px;
            box-sizing: border-box;
        }
        .nav-link i {
            font-size: 1.3rem;
            margin-right: 12px;
            min-width: 25px;
            display: flex;
            justify-content: center;
            align-items: center;
            -webkit-text-stroke: 0.5px;
        }
        .sidebar.collapsed .nav-link {
            padding: 0.8rem 0;
            justify-content: center;
            text-align: center;
            height: 45px;
        }
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
            min-width: auto;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            -webkit-text-stroke: 0.5px;
        }
        .sidebar.collapsed .nav-link span {
            display: none;
            opacity: 0;
        }
        .nav-link.active {
            background-color: #7AF146;
            color: #ffffffff !important;
            font-weight: 600;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .nav-link.active i {
            color: #fff !important;
        }
        .nav-link:hover {
            background-color: #efffe8ff;
            margin-bottom: 5px;
            color: #000;
        }

        .nav-link.active:hover {
            background-color: #7AF146;
            color: #fff !important;
            font-weight: 600;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .sidebar-footer {
            border-top: 1px solid #eee;
            padding-top: 0.8rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            transition: justify-content 0.3s ease;
        }
        .sidebar-footer img {
            height: 32px;
            width: 32px;
            object-fit: contain;
            margin-right: 0.5rem;
            margin-left: 0;
        }
        .sidebar-footer div {
            width: 75%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .sidebar.collapsed .sidebar-footer {
            justify-content: center;
        }
        .sidebar-footer img {
            height: 32px;
            width: 32px;
        }
        .sidebar.collapsed .sidebar-footer div {
            display: none;
            opacity: 0;
        }
    </style>
</head>
<body>
<div class="sidebar" id="sidebar">
    <div class="logo-section">
        <div class="d-flex align-items-center">
            <img class="logo-full" src="images/logos/logonavbar.svg" alt="Full Logo">
        </div>
        <button class="collapse-btn" onclick="toggleSidebar()">
            <img class="logo-collapsed" src="images/logos/logocollapsed.svg" alt="Collapsed Logo">
            <i class="bi bi-layout-sidebar"></i>
        </button>
    </div>
    
    <ul class="nav flex-column flex-grow-1">
        <li class="nav-item">
            <a class="nav-link <?php if ($currentPage == 'GoSort_Dashboard.php') {echo 'active';} ?>" href="GoSort_Dashboard.php" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                <i class="bi bi-columns-gap"></i> <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($currentPage == 'GoSort_Sorters.php') {echo 'active';} ?>" href="GoSort_Sorters.php" data-bs-toggle="tooltip" data-bs-placement="right" title="Devices">
                <i class="bi bi-plus-circle"></i> <span>Devices</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($currentPage == 'GoSort_AnalyticsNavpage.php' || $currentPage == 'GoSort_Statistics.php') {echo 'active';} ?>" href="GoSort_AnalyticsNavpage.php" data-bs-toggle="tooltip" data-bs-placement="right" title="Analytics">
                <i class="bi bi-bar-chart"></i> <span>Analytics</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($currentPage == 'GoSort_MaintenanceNavpage.php' || $currentPage == 'GoSort_Maintenance.php') {echo 'active';} ?>" href="GoSort_MaintenanceNavpage.php" data-bs-toggle="tooltip" data-bs-placement="right" title="Maintenance">
                <i class="bi bi-clock-history"></i> <span>Maintenance</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($currentPage == 'waste.php') {echo 'active';} ?>" href="waste.php" data-bs-toggle="tooltip" data-bs-placement="right" title="Waste Monitoring">
                <i class="bi bi-trash"></i> <span>Waste Monitoring</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($currentPage == 'GoSort_Notifications.php') {echo 'active';} ?>" href="GoSort_Notifications.php" data-bs-toggle="tooltip" data-bs-placement="right" title="Notifications">
                <i class="bi bi-bell"></i> <span>Notifications</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($currentPage == 'GoSort_Settings.php') {echo 'active';} ?>" href="GoSort_Settings.php" data-bs-toggle="tooltip" data-bs-placement="right" title="Settings">
                <i class="bi bi-gear"></i> <span>Settings</span>
            </a>
        </li>
    </ul>

    <a href="GoSort_Settings.php" class="sidebar-footer" style="text-decoration:none; color:inherit;">
        <img src="<?php echo $accountLogo; ?>" alt="Account Logo" id="footer-logo" data-bs-toggle="tooltip" data-bs-placement="right" title="<?php echo htmlspecialchars($accountName); ?>">
        <div>
            <strong><?php echo $accountName; ?></strong>
            <span><?php echo $accountRole; ?></span>
        </div>
    </a>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        // Get the main content wrapper by its ID
        const mainContentWrapper = document.getElementById('main-content-wrapper');

        // Toggle the 'collapsed' class on both the sidebar and the main content
        sidebar.classList.toggle('collapsed');
        if (mainContentWrapper) {
            mainContentWrapper.classList.toggle('collapsed');
        }

        enableSidebarTooltips();
    }

    function enableSidebarTooltips() {
        var sidebar = document.getElementById('sidebar');
        var isCollapsed = sidebar.classList.contains('collapsed');
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('.nav-link'));
        tooltipTriggerList.forEach(function (el) {
            if (el._tooltipInstance) {
                el._tooltipInstance.dispose();
                el._tooltipInstance = null;
            }
            if (isCollapsed) {
                el._tooltipInstance = new bootstrap.Tooltip(el, {
                    trigger: 'hover',
                    placement: 'right',
                });
            }
        });
        var footerLogo = document.getElementById('footer-logo');
        if (footerLogo) {
            if (footerLogo._tooltipInstance) {
                footerLogo._tooltipInstance.dispose();
                footerLogo._tooltipInstance = null;
            }
            if (isCollapsed) {
                footerLogo._tooltipInstance = new bootstrap.Tooltip(footerLogo, {
                    trigger: 'hover',
                    placement: 'right',
                });
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        enableSidebarTooltips();
    });

</script>
</body>
</html>