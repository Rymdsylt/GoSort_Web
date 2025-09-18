<?php
// Detect current page filename
$currentPage = basename($_SERVER['PHP_SELF']);

// Example: dynamically load logged-in account
// Replace these with your session or DB values
$accountName = $_SESSION['account_name'] ?? "Pateros Catholic School";
$accountRole = $_SESSION['account_role'] ?? "Admin";
$accountLogo = $_SESSION['account_logo'] ?? "images/logos/footerlogo.png";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GOSORT Sidebar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: "Inter", sans-serif;
            background-color: #f0f2f5;
        }
        .sidebar {
            width: 280px;
            height: 100vh;
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 1rem;
            transition: width 0.3s ease;
            z-index: 1000;
        }
        .sidebar.collapsed {
            width: 80px;
        }
        .logo-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding: 0.5rem 0.5rem 0.5rem 1rem;
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
            justify-content: center;
        }
        .sidebar.collapsed .collapse-btn .bi-list {
            display: none;
        }
        .nav-link {
            display: flex;
            align-items: center;
            border-radius: 10px;
            padding: 0.8rem 1rem;
            color: #444;
            font-weight: 500;
            margin-bottom: 5px;
            transition: all 0.2s ease-in-out;
            white-space: nowrap;
        }
        .nav-link i {
            font-size: 1.3rem;
            margin-right: 12px;
            min-width: 25px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .sidebar.collapsed .nav-link {
            padding: 0.8rem; /* Use a single value for symmetrical padding */
            justify-content: center;
        }
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
            min-width: auto;
        }
        .sidebar.collapsed .nav-link span {
            display: none;
            opacity: 0;
        }
        .nav-link.active {
            background-color: #7AF146;
            color: #fff !important;
            font-weight: 600;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .nav-link.active i {
            color: #fff !important;
        }
        .nav-link:hover {
            background-color: #e9ffe9;
            color: #000;
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
            height: 28px;
            width: 25%;
            object-fit: contain;
            margin-right: 0.5rem;
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
            height: 28px;
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
            <i class="bi bi-list"></i>
        </button>
    </div>
    
    <ul class="nav flex-column flex-grow-1">
        <li class="nav-item">
            <a class="nav-link <?php if ($currentPage == 'dashboard.php') {echo 'active';} ?>" href="dashboard.php">
                <i class="bi bi-grid-1x2"></i> <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($currentPage == 'devices.php') {echo 'active';} ?>" href="devices.php">
                <i class="bi bi-plus-circle"></i> <span>Devices</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($currentPage == 'analytics.php') {echo 'active';} ?>" href="analytics.php">
                <i class="bi bi-bar-chart"></i> <span>Analytics</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($currentPage == 'maintenance.php') {echo 'active';} ?>" href="maintenance.php">
                <i class="bi bi-clock-history"></i> <span>Maintenance</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($currentPage == 'waste.php') {echo 'active';} ?>" href="waste.php">
                <i class="bi bi-trash"></i> <span>Waste Monitoring</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($currentPage == 'settings.php') {echo 'active';} ?>" href="settings.php">
                <i class="bi bi-gear"></i> <span>Settings</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <img src="<?php echo $accountLogo; ?>" alt="Account Logo">
        <div>
            <strong><?php echo $accountName; ?></strong>
            <span><?php echo $accountRole; ?></span>
        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    }
</script>
</body>
</html>
