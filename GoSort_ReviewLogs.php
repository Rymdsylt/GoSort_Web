<?php
session_start();
date_default_timezone_set('Asia/Manila');
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

// Get device information
$deviceId = $_GET['device'] ?? null;
$deviceName = $_GET['name'] ?? 'Unknown Device';

if (!$deviceId) {
    header("Location: GoSort_BinMonitoring.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Review Logs: <?php echo htmlspecialchars($deviceName); ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #274a17ff;
            --light-green: #7AF146;
            --dark-gray: #1f2937;
            --medium-gray: #6b7280;
            --light-gray: #f3f4f6;
            --border-color: #368137;
        }
        body {
            background-color: #F3F3EF !important;
            font-family: 'Inter', sans-serif !important;
            color: var(--dark-gray);
        }
        #main-content-wrapper {
            transition: margin-left 0.3s ease;
            padding: 20px;
        }
        .monitor-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        .waste-category {
            margin-bottom: 2rem;
        }
        .category-title {
            font-size: 2.2rem;
            font-weight: 900;
        }
        .detected-item {
            display: inline-block;
            background: #efffe8ff;
            color: var(--primary-green);
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-top: 0.5rem;
        }
        .image-display {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 300px;
            background: #f9fafb;
            border-radius: 16px;
            border: 2px dashed #d1d5db;
            overflow: hidden;
        }
        .image-display img {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
        }
        .feedback-icons {
            display: flex;
            justify-content: center;
            gap: 5rem;
            margin-top: 2rem;
        }
        .feedback-icons .text-danger,
        .feedback-icons .text-success {
            font-size: 3rem;
        }
        .feedback-icons p {
            font-weight: 600;
            margin-top: 0.5rem;
        }
        .navigation-arrows {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
        .arrow-btn {
            background: none;
            border: none;
            font-size: 2rem;
            color: var(--dark-gray);
            transition: color 0.2s;
        }
        .arrow-btn:hover {
            color: var(--primary-green);
        }
        .time-display {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            border: 2px solid var(--border-color);
        }
    </style>
</head>
<body>
    <?php // include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex align-items-center mb-2 mt-3">
                <a href="GoSort_BinMonitoring.php" class="back-button">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h2 class="fw-bold mb-0">Review Logs</h2>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <div class="time-display text-end">
                        <div class="time" id="currentTime">12:00 am</div>
                        <div class="date" id="currentDate">Tuesday, April 15</div>
                    </div>
                </div>
            </div>

            <hr style="height: 1.5px; background-color: #000; opacity: 1;">

            <!-- Main Review Card -->
            <div class="monitor-card">
                <p id="recordCount" class="mb-2">1 out of <?php echo $totalRecords; ?></p>

                <div class="waste-category">
                    <h1 class="category-title" id="wasteCategory">Biodegradable</h1>
                    <p>Detected: <span class="detected-item" id="detectedItem">Fruit Peel</span></p>
                </div>

                <div class="image-display">
                    <img id="detectionImage" src="uploads/sample_banana.jpg" alt="Waste Image">
                </div>

                <div class="feedback-icons">
                    <div class="text-center">
                        <i class="bi bi-x-lg text-danger"></i>
                        <p>Wrong</p>
                    </div>
                    <div class="text-center">
                        <i class="bi bi-check-lg text-success"></i>
                        <p>Correct</p>
                    </div>
                </div>

                <div class="navigation-arrows">
                    <button class="arrow-btn" id="prevBtn"><i class="bi bi-arrow-left"></i></button>
                    <button class="arrow-btn" id="nextBtn"><i class="bi bi-arrow-right"></i></button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        let currentIndex = 1;
        const total = <?php echo $totalRecords; ?>;
        const deviceId = <?php echo json_encode($deviceId); ?>;

        function updateTime() {
            const now = new Date();
            const options = { weekday: 'long', month: 'long', day: 'numeric' };
            const dateString = now.toLocaleDateString('en-US', options);

            let hours = now.getHours();
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'pm' : 'am';
            hours = hours % 12 || 12;

            document.getElementById('currentTime').textContent = `${hours}:${minutes} ${ampm}`;
            document.getElementById('currentDate').textContent = dateString;
        }

        function loadRecord(index) {
            fetch(`fetch_review_record.php?device=${deviceId}&index=${index}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('wasteCategory').textContent = data.record.category;
                        document.getElementById('detectedItem').textContent = data.record.item;
                        document.getElementById('detectionImage').src = data.record.image;
                        document.getElementById('recordCount').textContent = `${index} out of ${total}`;
                    }
                });
        }

        document.getElementById('nextBtn').addEventListener('click', () => {
            if (currentIndex < total) {
                currentIndex++;
                loadRecord(currentIndex);
            }
        });

        document.getElementById('prevBtn').addEventListener('click', () => {
            if (currentIndex > 1) {
                currentIndex--;
                loadRecord(currentIndex);
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            updateTime();
            setInterval(updateTime, 1000);
            loadRecord(currentIndex);
        });
    </script>
</body>
</html>
