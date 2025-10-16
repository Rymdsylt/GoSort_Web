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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Settings</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #274a17ff;
            --light-green: #7AF146;
            --dark-gray: #1f2937;
            --medium-gray: #6b7280;
            --light-gray: #f3f4f6;
            --border-color: #368137;
            --bio-color: #10b981;
            --nbio-color: #ef4444;
            --hazardous-color: #f59e0b;
            --mixed-color: #6b7280;
            --success-light: rgba(16, 185, 129, 0.1);
            --danger-light: rgba(239, 68, 68, 0.1);
            --warning-light: rgba(245, 158, 11, 0.1);
        }

        body {
            background-color: #F3F3EF !important;
            font-family: 'Inter', sans-serif !important;
            color: var(--dark-gray);
            overflow: hidden !important; 
        }

        .tab-scroll-area {
            max-height: calc(100vh - 155px);
            overflow-y: auto;
            padding-right: 15px;
            padding-bottom: 5px;
        }

        #main-content-wrapper {
            margin-left: 260px;
            transition: margin-left 0.3s ease;
            padding: 20px;
        }

        #main-content-wrapper.collapsed {
            margin-left: 80px;
        }

        .page-header {
            padding: 1rem 0 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 0;
            margin-left: 6px;
        }

        /* Tab Navigation */
        .settings-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            padding-bottom: 2px;
            position: relative;
        }

        .settings-tabs::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: #e5e7eb;
        }

        .tab-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            color: var(--medium-gray);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            position: relative;
            font-size: 0.95rem;
        }

        .tab-btn:hover {
            color: var(--dark-gray);
        }

        .tab-btn.active {
            color: var(--primary-green);
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            z-index: 1;
            background: linear-gradient(90deg, var(--light-green), var(--primary-green));
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            #main-content-wrapper {
                margin-left: 80px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .settings-tabs {
                overflow-x: auto;
            }
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--light-green), var(--primary-green));
            transition: width 0.3s ease;
        }

        .page-header {
            margin-bottom: 1rem;
        }

    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-left">
                    <h1 class="page-title">Settings</h1>
                </div>
            </div>

            <hr style="height: 1.5px; background-color: #000; opacity: 1; margin-left:6.5px;" class="mb-3">

            <!-- Tab Navigation -->
            <div class="settings-tabs">
                <button class="tab-btn active" onclick="switchTab('profile')">
                    <i class="bi bi-person-circle me-2"></i>Profile
                </button>
                <button class="tab-btn" onclick="switchTab('accounts')">
                    <i class="bi bi-people me-2"></i>Accounts
                </button>
                <button class="tab-btn" onclick="switchTab('history')">
                    <i class="bi bi-clock-history me-2"></i>Activity Logs
                </button>
                <button class="tab-btn" onclick="switchTab('support')">
                    <i class="bi bi-question-circle me-2"></i>Help/Support
                </button>
            </div>

            <div class="tab-scroll-area">
                <!-- Tab 1: Profile -->
                <div id="profile" class="tab-content active">
                    <?php include 'settings_tabs/profiletab.php'; ?>
                </div>

                <!-- Tab 2: Accounts -->
                <div id="accounts" class="tab-content">
                    <?php include 'settings_tabs/accountstab.php'; ?>
                </div>

                <!-- Tab 3: Activity Logs -->
                <div id="history" class="tab-content">
                    <?php include 'settings_tabs/historytab.php'; ?>
                </div>

                <!-- Tab 4: Help/Support -->
                <div id="support" class="tab-content">
                    <?php include 'settings_tabs/supporttab.php'; ?>
                </div>
            </div>
    </div>
</div>
    <script>
    function switchTab(tabId) {
        const tabs = document.querySelectorAll('.tab-content');
        const buttons = document.querySelectorAll('.tab-btn');

        tabs.forEach(tab => tab.classList.remove('active'));
        buttons.forEach(btn => btn.classList.remove('active'));

        document.getElementById(tabId).classList.add('active');
        event.currentTarget.classList.add('active');
    }
    </script>

    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>