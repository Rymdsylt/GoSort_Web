<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';

if (!isset($_SESSION['user_id']) || !isset($_COOKIE['user_logged_in'])) {
    header("Location: GoSort_Login.php");
    exit();
}

// Get device ID and other parameters from URL
$deviceId = $_GET['device'] ?? null;
$deviceName = $_GET['name'] ?? 'Unknown Device';
$deviceIdentity = $_GET['identity'] ?? '';
$selectedDate = $_GET['date'] ?? date('Y-m-d'); // Support date parameter from archive

// If deviceIdentity is not provided but deviceId is (for archive links)
if (!$deviceIdentity && $deviceId) {
    // Try to get device identity from device ID or use deviceId as identity
    $deviceIdentity = $deviceId;
}

if (!$deviceId && !$deviceIdentity) {
    header("Location: GoSort_WasteMonitoringNavpage.php");
    exit();
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// Fetch sorting history for this device and date, including review status
$query = "
    SELECT 
        sh.id,
        sh.trash_type,
        sh.trash_class,
        sh.confidence,
        sh.image_data,
        sh.sorted_at,
        sr.is_correct as review_status,
        sr.reviewed_at
    FROM sorting_history sh
    LEFT JOIN sorting_reviews sr ON sh.id = sr.sorting_history_id
    WHERE 
        sh.device_identity = ? 
        AND DATE(sh.sorted_at) = ?
        AND sh.is_maintenance = 0
    ORDER BY sh.sorted_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$deviceIdentity, $selectedDate]);
$sortingHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get device name if not provided
if ($deviceName === 'Unknown Device' || empty($deviceName)) {
    $deviceQuery = $pdo->prepare("SELECT device_name FROM sorters WHERE device_identity = ?");
    $deviceQuery->execute([$deviceIdentity]);
    $deviceRow = $deviceQuery->fetch(PDO::FETCH_ASSOC);
    if ($deviceRow) {
        $deviceName = $deviceRow['device_name'];
    }
}

// Calculate review statistics
$totalDetections = count($sortingHistory);
$reviewedCount = 0;
$correctCount = 0;
$wrongCount = 0;
foreach ($sortingHistory as $detection) {
    if ($detection['review_status'] !== null) {
        $reviewedCount++;
        if ($detection['review_status'] == 1) {
            $correctCount++;
        } else {
            $wrongCount++;
        }
    }
}
$pendingCount = $totalDetections - $reviewedCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Review Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode-global.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="js/theme-manager.js"></script>
    <style>
        /* Root Variables */
        :root {
            --primary-green: #274a17ff;
            --light-green: #7AF146;
            --dark-gray: #1f2937;
            --medium-gray: #6b7280;
            --light-gray: #f3f4f6;
            --border-color: #368137;
        }

        /* General Styles */
        body {
            background-color: #F3F3EF !important;
            font-family: 'Inter', sans-serif !important;
            color: var(--dark-gray);
            padding: 20px;
        }

        /* Header Section */
        .monitor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: transparent;
            border: none;
            color: var(--dark-gray);
            text-decoration: none;
            font-size: 1.5rem;
            margin-right: 1rem;
            transition: all 0.2s ease;
        }

        .back-button:hover {
            color: var(--primary-green);
        }

        /* Time Display */
        .time-display {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            border: 2px solid var(--border-color);
        }

        .time-display .time {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-green);
            margin: 0;
        }

        .time-display .date {
            font-size: 0.875rem;
            color: var(--medium-gray);
            margin-top: 0.25rem;
        }

        /* Review Card */
        .review-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            max-width: 900px;
            margin: 0 auto;
        }

        .review-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(to bottom, var(--light-green), var(--primary-green));
        }

        /* Counter */
        .counter-display {
            text-align: center;
            font-size: 0.875rem;
            color: var(--medium-gray);
            margin-bottom: 1rem;
            font-weight: 500;
        }

        /* Category Title */
        .category-title {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--dark-gray);
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.02em;
            text-align: center;
        }

        .detection-subtitle {
            font-size: 1rem;
            color: var(--medium-gray);
            margin-bottom: 2rem;
            font-weight: 500;
            text-align: center;
        }

        .detected-item {
            display: inline-block;
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-green);
            background: #efffe8ff;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-left: 0.5rem;
        }

        /* Image Display */
        .image-container {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
            margin-bottom: 2rem;
        }

        .image-display {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
            background: #f9fafb;
            border-radius: 16px;
            border: 2px dashed #d1d5db;
            position: relative;
            overflow: visible;
            width: 100%;
        }

        .image-display img {
            max-width: 100%;
            max-height: 450px;
            object-fit: contain;
            border-radius: 12px;
        }

        .placeholder-image {
            text-align: center;
            color: var(--medium-gray);
        }

        .placeholder-image i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .placeholder-image p {
            font-size: 1rem;
            margin: 0;
        }

        /* Navigation Arrows */
        .nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
            font-size: 1.5rem;
            color: var(--dark-gray);
        }

        .nav-arrow:hover {
            background: var(--primary-green);
            color: white;
            transform: translateY(-50%) scale(1.1);
        }

        .nav-arrow:active {
            transform: translateY(-50%) scale(0.95);
        }

        .nav-arrow.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            pointer-events: none;
        }

        .nav-arrow-left {
            left: -25px;
        }

        .nav-arrow-right {
            right: -25px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 4rem;
            margin-top: 2rem;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-btn:hover .btn-icon {
            transform: scale(1.1);
        }

        .btn-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            transition: all 0.3s ease;
        }

        .btn-icon.wrong {
            background: #fee;
            color: #dc2626;
        }

        .btn-icon.correct {
            background: #eff;
            color: #16a34a;
        }

        .action-btn:hover .btn-icon.wrong {
            background: #dc2626;
            color: white;
        }

        .action-btn:hover .btn-icon.correct {
            background: #16a34a;
            color: white;
        }

        .btn-label {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--dark-gray);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .category-title {
                font-size: 2rem;
            }

            .image-display {
                min-height: 300px;
            }

            .nav-arrow {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
            }

            .nav-arrow-left {
                left: -20px;
            }

            .nav-arrow-right {
                right: -20px;
            }

            .btn-icon {
                width: 60px;
                height: 60px;
                font-size: 2rem;
            }

            .action-buttons {
                gap: 2rem;
            }
        }

        /* Review Stats Banner */
        .review-stats-banner {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            max-width: 900px;
            margin: 0 auto;
        }

        .stat-box {
            padding: 0.75rem;
            border-radius: 8px;
            background: #f9fafb;
        }

        .stat-box.pending {
            background: #fef3c7;
        }

        .stat-box.correct {
            background: #d1fae5;
        }

        .stat-box.wrong {
            background: #fee2e2;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-gray);
        }

        .stat-box.pending .stat-number {
            color: #d97706;
        }

        .stat-box.correct .stat-number {
            color: #059669;
        }

        .stat-box.wrong .stat-number {
            color: #dc2626;
        }

        .review-status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 5;
        }

        .review-status-badge.reviewed-correct {
            background: #d1fae5;
            color: #059669;
        }

        .review-status-badge.reviewed-wrong {
            background: #fee2e2;
            color: #dc2626;
        }

        .review-status-badge.pending {
            background: #fef3c7;
            color: #d97706;
        }

        /* Reviewed Table Styles */
        .reviewed-table-section {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            max-width: 900px;
            margin: 0 auto;
        }

        .reviewed-table {
            margin-bottom: 0;
        }

        .reviewed-table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid var(--border-color);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            color: var(--medium-gray);
            padding: 0.75rem;
        }

        .reviewed-table tbody td {
            vertical-align: middle;
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s;
            border: 2px solid #e5e7eb;
        }

        .table-thumbnail:hover {
            transform: scale(1.1);
            border-color: var(--primary-green);
        }

        .category-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .category-badge.category-bio {
            background: #dcfce7;
            color: #166534;
        }

        .category-badge.category-nbio {
            background: #fef3c7;
            color: #92400e;
        }

        .category-badge.category-hazardous {
            background: #fee2e2;
            color: #991b1b;
        }

        .category-badge.category-mixed {
            background: #e0e7ff;
            color: #3730a3;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-pill.correct {
            background: #dcfce7;
            color: #166534;
        }

        .status-pill.wrong {
            background: #fee2e2;
            color: #991b1b;
        }

        #exportZipBtn {
            background: var(--primary-green);
            border-color: var(--primary-green);
        }

        #exportZipBtn:hover:not(:disabled) {
            background: #1e3a12;
            border-color: #1e3a12;
        }

        #exportZipBtn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex align-items-center mb-2 mt-3">
            <a href="GoSort_WasteMonitoringNavpage.php" class="back-button">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h2 class="fw-bold mb-0">Review Logs</h2>
                <small class="text-muted"><?php echo htmlspecialchars($deviceName); ?> - <?php echo date('F j, Y', strtotime($selectedDate)); ?></small>
            </div>

            <!-- Time Display -->
            <div class="time-display ms-auto">
                <div class="time" id="currentTime">12:00 am</div>
                <div class="date" id="currentDate">Tuesday, April 15</div>
            </div>
        </div>

        <hr style="height: 1.5px; background-color: #000; opacity: 1;" class="mb-4">

        <!-- Review Statistics Banner -->
        <div class="review-stats-banner mb-4">
            <div class="row text-center">
                <div class="col-3">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $totalDetections; ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box pending">
                        <div class="stat-number"><?php echo $pendingCount; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box correct">
                        <div class="stat-number"><?php echo $correctCount; ?></div>
                        <div class="stat-label">Correct</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box wrong">
                        <div class="stat-number"><?php echo $wrongCount; ?></div>
                        <div class="stat-label">Wrong</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Review Card -->
        <div class="review-card">
            <!-- Counter -->
            <div class="counter-display">
                <span id="currentCount">1</span> out of <span id="totalCount">500</span>
            </div>

            <!-- Category -->
            <h1 class="category-title" id="wasteCategory">Biodegradable</h1>
            <p class="detection-subtitle">
                Detected: <span class="detected-item" id="detectedItem">Fruit Peel</span>
            </p>

            <!-- Image Container with Navigation -->
            <div class="image-container">
                <div class="nav-arrow nav-arrow-left" id="prevBtn">
                    <i class="bi bi-chevron-left"></i>
                </div>

                <div class="image-display">
                    <span class="review-status-badge pending" id="reviewStatusBadge">Pending Review</span>
                    <div class="placeholder-image" id="placeholderImage">
                        <i class="bi bi-camera-fill d-block"></i>
                        <p>No detections found</p>
                    </div>
                    <img id="detectionImage" src="" alt="Detection" style="display: none;">
                </div>

                <div class="nav-arrow nav-arrow-right" id="nextBtn">
                    <i class="bi bi-chevron-right"></i>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="action-btn" id="wrongBtn">
                    <div class="btn-icon wrong">
                        <i class="bi bi-x-lg"></i>
                    </div>
                    <span class="btn-label">Wrong</span>
                </button>

                <button class="action-btn" id="correctBtn">
                    <div class="btn-icon correct">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <span class="btn-label">Correct</span>
                </button>
            </div>
        </div>

        <!-- Reviewed Items Table Section -->
        <div class="reviewed-table-section mt-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-bold mb-0">
                    <i class="bi bi-table me-2"></i>Reviewed Items
                </h4>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="tableFilter" style="width: auto;">
                        <option value="all">All Reviewed</option>
                        <option value="correct">Correct Only</option>
                        <option value="wrong">Wrong Only</option>
                    </select>
                    <button class="btn btn-success btn-sm" id="exportZipBtn" <?php echo ($reviewedCount == 0) ? 'disabled' : ''; ?>>
                        <i class="bi bi-download me-1"></i>Export ZIP
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover reviewed-table" id="reviewedTable">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Image</th>
                            <th>Category</th>
                            <th>Detected Item</th>
                            <th>Confidence</th>
                            <th>Sorted At</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 100px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="reviewedTableBody">
                        <?php 
                        foreach ($sortingHistory as $index => $detection): 
                            if ($detection['review_status'] === null) continue;
                            $isCorrect = $detection['review_status'] == 1;
                            $categoryMap = [
                                'bio' => 'Biodegradable',
                                'nbio' => 'Non-Biodegradable',
                                'hazardous' => 'Hazardous',
                                'mixed' => 'Mixed'
                            ];
                            $categoryDisplay = $categoryMap[$detection['trash_type']] ?? ucfirst($detection['trash_type']);
                        ?>
                        <tr data-id="<?php echo $detection['id']; ?>" data-status="<?php echo $isCorrect ? 'correct' : 'wrong'; ?>">
                            <td>
                                <img src="data:image/jpeg;base64,<?php echo $detection['image_data']; ?>" 
                                     class="table-thumbnail" 
                                     alt="Detection"
                                     onclick="viewImage(<?php echo $index; ?>)">
                            </td>
                            <td>
                                <span class="category-badge category-<?php echo $detection['trash_type']; ?>">
                                    <?php echo $categoryDisplay; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($detection['trash_class']); ?></td>
                            <td><?php echo number_format($detection['confidence'] * 100, 1); ?>%</td>
                            <td><?php echo date('g:i A', strtotime($detection['sorted_at'])); ?></td>
                            <td>
                                <?php if ($isCorrect): ?>
                                    <span class="status-pill correct"><i class="bi bi-check-circle-fill me-1"></i>Correct</span>
                                <?php else: ?>
                                    <span class="status-pill wrong"><i class="bi bi-x-circle-fill me-1"></i>Wrong</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewImage(<?php echo $index; ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="noReviewedMessage" class="text-center py-4 text-muted" style="<?php echo ($reviewedCount > 0) ? 'display: none;' : ''; ?>">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                <p>No reviewed items yet. Mark items as correct or wrong above.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Get sorting history data from PHP
        const detections = <?php echo json_encode($sortingHistory); ?> || [];
        const deviceIdentity = '<?php echo addslashes($deviceIdentity); ?>';

        let currentIndex = 0;

        // Review statistics
        let stats = {
            total: <?php echo $totalDetections; ?>,
            pending: <?php echo $pendingCount; ?>,
            correct: <?php echo $correctCount; ?>,
            wrong: <?php echo $wrongCount; ?>
        };

        // Update time display
        function updateTime() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'pm' : 'am';
            hours = hours % 12;
            hours = hours ? hours : 12;
            
            const timeString = `${hours}:${minutes} ${ampm}`;
            const options = { weekday: 'long', month: 'long', day: 'numeric' };
            const dateString = now.toLocaleDateString('en-US', options);
            
            document.getElementById('currentTime').textContent = timeString;
            document.getElementById('currentDate').textContent = dateString;
        }

        // Update statistics display
        function updateStatsDisplay() {
            document.querySelector('.stat-box:nth-child(1) .stat-number').textContent = stats.total;
            document.querySelector('.stat-box.pending .stat-number').textContent = stats.pending;
            document.querySelector('.stat-box.correct .stat-number').textContent = stats.correct;
            document.querySelector('.stat-box.wrong .stat-number').textContent = stats.wrong;
        }

        // Update review status badge
        function updateReviewStatusBadge(detection) {
            const badge = document.getElementById('reviewStatusBadge');
            if (!badge) return;

            if (detection.review_status === null || detection.review_status === undefined) {
                badge.className = 'review-status-badge pending';
                badge.textContent = 'Pending Review';
            } else if (detection.review_status == 1) {
                badge.className = 'review-status-badge reviewed-correct';
                badge.textContent = '✓ Marked Correct';
            } else {
                badge.className = 'review-status-badge reviewed-wrong';
                badge.textContent = '✗ Marked Wrong';
            }
        }

        // Update display with current detection
        function updateDisplay() {
            if (detections.length === 0) {
                document.getElementById('placeholderImage').style.display = 'block';
                document.getElementById('detectionImage').style.display = 'none';
                document.getElementById('reviewStatusBadge').style.display = 'none';
                document.getElementById('wasteCategory').textContent = 'No Detections';
                document.getElementById('detectedItem').textContent = 'N/A';
                document.getElementById('currentCount').textContent = '0';
                document.getElementById('totalCount').textContent = '0';
                return;
            }

            const detection = detections[currentIndex];
            
            // Update counter
            document.getElementById('currentCount').textContent = currentIndex + 1;
            document.getElementById('totalCount').textContent = detections.length;
            
            // Update category and item
            document.getElementById('wasteCategory').textContent = detection.trash_type.toUpperCase();
            // Split trash_class by comma and take first item if multiple items
            const detectedItems = detection.trash_class.split(',').map(item => item.trim());
            document.getElementById('detectedItem').textContent = detectedItems.join(', ');
            
            // Update image
            const img = document.getElementById('detectionImage');
            img.src = `data:image/jpeg;base64,${detection.image_data}`;
            img.style.display = 'block';
            document.getElementById('placeholderImage').style.display = 'none';
            document.getElementById('reviewStatusBadge').style.display = 'block';
            
            // Update review status badge
            updateReviewStatusBadge(detection);
            
            // Update arrow states
            updateArrowStates();
        }

        // Update arrow button states
        function updateArrowStates() {
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            
            if (currentIndex === 0) {
                prevBtn.classList.add('disabled');
            } else {
                prevBtn.classList.remove('disabled');
            }
            
            if (currentIndex === detections.length - 1) {
                nextBtn.classList.add('disabled');
            } else {
                nextBtn.classList.remove('disabled');
            }
        }

        // Navigation functions
        function navigatePrev() {
            if (currentIndex > 0) {
                currentIndex--;
                updateDisplay();
            }
        }

        function navigateNext() {
            if (currentIndex < detections.length - 1) {
                currentIndex++;
                updateDisplay();
            }
        }

        // Action handlers
        async function markWrong() {
            const detection = detections[currentIndex];
            try {
                const response = await fetch('api/mark_sorting_wrong.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        sorting_id: detection.id,
                        device_identity: deviceIdentity
                    })
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    // Update local state
                    const wasReviewed = detection.review_status !== null;
                    const wasCorrect = detection.review_status == 1;
                    
                    // Update statistics
                    if (!wasReviewed) {
                        stats.pending--;
                        stats.wrong++;
                    } else if (wasCorrect) {
                        stats.correct--;
                        stats.wrong++;
                    }
                    
                    // Update detection review status
                    detection.review_status = 0;
                    
                    // Update displays
                    updateStatsDisplay();
                    updateReviewStatusBadge(detection);
                    
                    // Update table
                    addOrUpdateTableRow(detection, false);
                    
                    // Visual feedback
                    const btn = document.getElementById('wrongBtn');
                    const icon = btn.querySelector('.btn-icon');
                    icon.style.background = '#dc2626';
                    icon.style.color = 'white';
                    setTimeout(() => {
                        icon.style.background = '';
                        icon.style.color = '';
                        if (currentIndex < detections.length - 1) {
                            navigateNext();
                        }
                    }, 500);
                } else {
                    throw new Error(result.message || 'Failed to mark as wrong');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to mark detection as wrong: ' + error.message);
            }
        }

        async function markCorrect() {
            const detection = detections[currentIndex];
            try {
                const response = await fetch('api/mark_sorting_correct.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        sorting_id: detection.id,
                        device_identity: deviceIdentity
                    })
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    // Update local state
                    const wasReviewed = detection.review_status !== null;
                    const wasWrong = detection.review_status == 0;
                    
                    // Update statistics
                    if (!wasReviewed) {
                        stats.pending--;
                        stats.correct++;
                    } else if (wasWrong) {
                        stats.wrong--;
                        stats.correct++;
                    }
                    
                    // Update detection review status
                    detection.review_status = 1;
                    
                    // Update displays
                    updateStatsDisplay();
                    updateReviewStatusBadge(detection);
                    
                    // Update table
                    addOrUpdateTableRow(detection, true);
                    
                    // Visual feedback
                    const btn = document.getElementById('correctBtn');
                    const icon = btn.querySelector('.btn-icon');
                    icon.style.background = '#16a34a';
                    icon.style.color = 'white';
                    setTimeout(() => {
                        icon.style.background = '';
                        icon.style.color = '';
                        if (currentIndex < detections.length - 1) {
                            navigateNext();
                        }
                    }, 500);
                } else {
                    throw new Error(result.message || 'Failed to mark as correct');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to mark detection as correct: ' + error.message);
            }
        }

        // Event listeners
        document.getElementById('prevBtn').addEventListener('click', navigatePrev);
        document.getElementById('nextBtn').addEventListener('click', navigateNext);
        document.getElementById('wrongBtn').addEventListener('click', markWrong);
        document.getElementById('correctBtn').addEventListener('click', markCorrect);

        // Table filter functionality
        document.getElementById('tableFilter').addEventListener('change', function() {
            const filter = this.value;
            const rows = document.querySelectorAll('#reviewedTableBody tr');
            
            rows.forEach(row => {
                const status = row.dataset.status;
                if (filter === 'all' || status === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Export ZIP functionality
        document.getElementById('exportZipBtn').addEventListener('click', function() {
            const filter = document.getElementById('tableFilter').value;
            const url = `api/export_reviewed_images.php?identity=${encodeURIComponent(deviceIdentity)}&date=<?php echo $selectedDate; ?>&filter=${filter}`;
            
            // Direct download using window.location
            window.location.href = url;
        });

        // View image in main viewer
        function viewImage(index) {
            currentIndex = index;
            updateDisplay();
            // Scroll to review card
            document.querySelector('.review-card').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Function to add row to table after marking
        function addOrUpdateTableRow(detection, isCorrect) {
            const tbody = document.getElementById('reviewedTableBody');
            const existingRow = tbody.querySelector(`tr[data-id="${detection.id}"]`);
            
            const categoryMap = {
                'bio': 'Biodegradable',
                'nbio': 'Non-Biodegradable',
                'hazardous': 'Hazardous',
                'mixed': 'Mixed'
            };
            const categoryDisplay = categoryMap[detection.trash_type] || detection.trash_type;
            const statusClass = isCorrect ? 'correct' : 'wrong';
            const statusText = isCorrect ? 'Correct' : 'Wrong';
            const statusIcon = isCorrect ? 'check-circle-fill' : 'x-circle-fill';
            const confidence = (detection.confidence * 100).toFixed(1);
            const sortedTime = new Date(detection.sorted_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            
            const rowHtml = `
                <td>
                    <img src="data:image/jpeg;base64,${detection.image_data}" 
                         class="table-thumbnail" 
                         alt="Detection"
                         onclick="viewImage(${detections.indexOf(detection)})">
                </td>
                <td>
                    <span class="category-badge category-${detection.trash_type}">
                        ${categoryDisplay}
                    </span>
                </td>
                <td>${detection.trash_class}</td>
                <td>${confidence}%</td>
                <td>${sortedTime}</td>
                <td>
                    <span class="status-pill ${statusClass}"><i class="bi bi-${statusIcon} me-1"></i>${statusText}</span>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-secondary" onclick="viewImage(${detections.indexOf(detection)})">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            `;
            
            if (existingRow) {
                existingRow.dataset.status = statusClass;
                existingRow.innerHTML = rowHtml;
            } else {
                const newRow = document.createElement('tr');
                newRow.dataset.id = detection.id;
                newRow.dataset.status = statusClass;
                newRow.innerHTML = rowHtml;
                tbody.insertBefore(newRow, tbody.firstChild);
            }
            
            // Hide "no reviewed" message and enable export
            document.getElementById('noReviewedMessage').style.display = 'none';
            document.getElementById('exportZipBtn').disabled = false;
            
            // Apply current filter
            const currentFilter = document.getElementById('tableFilter').value;
            if (currentFilter !== 'all' && statusClass !== currentFilter) {
                const row = tbody.querySelector(`tr[data-id="${detection.id}"]`);
                if (row) row.style.display = 'none';
            }
        }

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') navigatePrev();
            if (e.key === 'ArrowRight') navigateNext();
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateTime();
            setInterval(updateTime, 1000);
            updateDisplay();
        });
    </script>
</body>
</html>