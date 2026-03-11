<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';

if (!isset($_SESSION['user_id']) || !isset($_COOKIE['user_logged_in'])) {
    header("Location: GoSort_Login.php");
    exit();
}

$deviceId       = $_GET['device']   ?? null;
$deviceName     = $_GET['name']     ?? '';
$deviceIdentity = $_GET['identity'] ?? '';
$selectedDate   = $_GET['date']     ?? date('Y-m-d');

if (!$deviceIdentity && $deviceId) {
    $deviceIdentity = $deviceId;
}

if (!$deviceId && !$deviceIdentity) {
    header("Location: GoSort_WasteMonitoringNavpage.php");
    exit();
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// If device name not in URL, look it up
if (empty($deviceName)) {
    $dq = $pdo->prepare("SELECT device_name FROM sorters WHERE id = ? OR device_identity = ? LIMIT 1");
    $dq->execute([$deviceId, $deviceIdentity]);
    $dr = $dq->fetch(PDO::FETCH_ASSOC);
    if ($dr) $deviceName = $dr['device_name'];
}

$stmt = $pdo->prepare("
    SELECT sh.id, sh.trash_type, sh.trash_class, sh.confidence, sh.image_data, sh.sorted_at,
           sr.is_correct as review_status, sr.reviewed_at
    FROM sorting_history sh
    LEFT JOIN sorting_reviews sr ON sh.id = sr.sorting_history_id
    WHERE sh.device_identity = ? AND DATE(sh.sorted_at) = ? AND sh.is_maintenance = 0
    ORDER BY sh.sorted_at DESC
");
$stmt->execute([$deviceIdentity, $selectedDate]);
$sortingHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalDetections = count($sortingHistory);
$reviewedCount = $correctCount = $wrongCount = 0;
foreach ($sortingHistory as $d) {
    if ($d['review_status'] !== null) {
        $reviewedCount++;
        if ($d['review_status'] == 1) $correctCount++; else $wrongCount++;
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
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #274a17;
            --light-green:   #7AF146;
            --mid-green:     #368137;
            --dark-gray:     #1f2937;
            --medium-gray:   #6b7280;
            --border-color:  #e5e7eb;
            --card-shadow:   0 1px 3px rgba(0,0,0,0.07);
        }

        body {
            background-color: #e8f1e6;
            font-family: 'Poppins', sans-serif !important;
            color: var(--dark-gray);
        }

        #main-content-wrapper {
            margin-left: 240px;
            padding: 100px 0 40px 0;
            min-height: 100vh;
            overflow-y: auto;
        }

        /* ── shared containers ── */
        .section-container {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #eeeeee;
        }

        .section-block {
            background: linear-gradient(135deg, rgb(236,251,234) 0%, #d5f5dc 100%);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }
        .section-block:last-child { margin-bottom: 0; }

        .section-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #000000b1;
            margin-bottom: 1rem;
        }

        .inner-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: var(--card-shadow);
        }

        /* ── back link ── */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--medium-gray);
            text-decoration: none;
            margin-bottom: 1rem;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--primary-green); }

        /* ── device/date strip ── */
        .context-strip {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .ctx-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #e8f5e1;
            border: 1px solid #c8e6c9;
            border-radius: 20px;
            padding: 0.3rem 0.8rem;
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--primary-green);
        }

        .date-change-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #fff;
            border: 1.5px solid var(--mid-green);
            border-radius: 20px;
            padding: 0.3rem 0.8rem;
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--primary-green);
            cursor: pointer;
            transition: background 0.2s;
        }
        .date-change-btn:hover { background: #f0fdf4; }

        /* ── stats pills ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }

        @media (max-width: 576px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }

        .stat-pill {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            text-align: center;
            box-shadow: var(--card-shadow);
        }

        .stat-pill-num {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.2rem;
        }

        .stat-pill-label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--medium-gray);
        }

        .stat-pill.total   .stat-pill-num { color: var(--dark-gray); }
        .stat-pill.pending .stat-pill-num { color: #d97706; }
        .stat-pill.correct .stat-pill-num { color: #059669; }
        .stat-pill.wrong   .stat-pill-num { color: #dc2626; }

        /* ── review card ── */
        .review-outer {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            max-width: 860px;
            margin: 0 auto 1.5rem auto;
            position: relative;
        }

        .review-outer::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
            background: linear-gradient(to bottom, var(--light-green), var(--primary-green));
        }

        .review-body { padding: 1.5rem 1.5rem 1.5rem 1.75rem; }

        .counter-label {
            text-align: center;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--medium-gray);
            margin-bottom: 0.6rem;
        }

        .waste-category {
            text-align: center;
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark-gray);
            letter-spacing: -0.02em;
            margin-bottom: 0.25rem;
        }

        .waste-subtitle {
            text-align: center;
            font-size: 0.85rem;
            color: var(--medium-gray);
            margin-bottom: 1.25rem;
        }

        .detected-chip {
            display: inline-block;
            background: #e8f5e1;
            color: var(--primary-green);
            border: 1px solid #c8e6c9;
            padding: 0.2rem 0.7rem;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            margin-left: 0.25rem;
        }

        /* ── image area ── */
        .image-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
        }

        .image-box {
            width: 100%;
            min-height: 340px;
            background: #f9fafb;
            border: 1.5px dashed #d1d5db;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .image-box img {
            max-width: 100%;
            max-height: 420px;
            object-fit: contain;
            border-radius: 10px;
        }

        .image-placeholder {
            text-align: center;
            color: var(--medium-gray);
        }
        .image-placeholder i { font-size: 3rem; display: block; margin-bottom: 0.5rem; opacity: 0.3; }
        .image-placeholder p { font-size: 0.82rem; margin: 0; }

        /* review status overlay */
        .review-badge-overlay {
            position: absolute;
            top: 10px; right: 10px;
            padding: 0.22rem 0.65rem;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            z-index: 5;
        }
        .review-badge-overlay.pending  { background: #fef3c7; color: #d97706; }
        .review-badge-overlay.correct  { background: #d1fae5; color: #059669; }
        .review-badge-overlay.wrong    { background: #fee2e2; color: #dc2626; }

        /* ── nav arrows ── */
        .nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 42px; height: 42px;
            background: #fff;
            border: 1.5px solid var(--border-color);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            font-size: 1.1rem;
            color: var(--dark-gray);
            transition: all 0.2s;
            z-index: 10;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .nav-arrow:hover { background: var(--primary-green); color: #fff; border-color: var(--primary-green); transform: translateY(-50%) scale(1.06); }
        .nav-arrow.disabled { opacity: 0.3; cursor: not-allowed; pointer-events: none; }
        .nav-arrow-left  { left: -21px; }
        .nav-arrow-right { right: -21px; }

        /* ── action buttons ── */
        .review-actions {
            display: flex;
            justify-content: center;
            gap: 3.5rem;
            margin-top: 1.25rem;
        }

        .rev-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.6rem;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .rev-btn:hover { transform: translateY(-2px); }

        .rev-btn-icon {
            width: 70px; height: 70px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem;
            transition: all 0.2s;
        }

        .rev-btn-icon.wrong   { background: #fee2e2; color: #dc2626; }
        .rev-btn-icon.correct { background: #d1fae5; color: #059669; }

        .rev-btn:hover .rev-btn-icon.wrong   { background: #dc2626; color: #fff; }
        .rev-btn:hover .rev-btn-icon.correct { background: #059669; color: #fff; }

        .rev-btn-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--dark-gray);
        }

        /* ── reviewed table ── */
        .table-outer {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            max-width: 860px;
            margin: 0 auto;
        }

        .table-outer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.9rem 1.1rem;
            border-bottom: 1px solid #f3f4f6;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .table-outer-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--dark-gray);
        }

        .reviewed-table { font-size: 0.79rem; font-family: 'Poppins', sans-serif; margin: 0; }

        .reviewed-table thead th {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--medium-gray);
            background: #fafafa;
            border-bottom: 1px solid #f3f4f6;
            padding: 0.6rem 0.75rem;
        }

        .reviewed-table tbody td {
            padding: 0.65rem 0.75rem;
            border-bottom: 1px solid #f9fafb;
            vertical-align: middle;
        }

        .reviewed-table tbody tr:last-child td { border-bottom: none; }
        .reviewed-table tbody tr:hover td { background: #f9fafb; }

        .table-thumbnail {
            width: 52px; height: 52px;
            object-fit: cover;
            border-radius: 7px;
            cursor: pointer;
            border: 1.5px solid #e5e7eb;
            transition: transform 0.15s, border-color 0.15s;
        }
        .table-thumbnail:hover { transform: scale(1.08); border-color: var(--mid-green); }

        .category-badge {
            display: inline-block;
            padding: 0.18rem 0.6rem;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
        }
        .category-badge.category-bio       { background: #dcfce7; color: #166534; }
        .category-badge.category-nbio      { background: #fef3c7; color: #92400e; }
        .category-badge.category-hazardous { background: #fee2e2; color: #991b1b; }
        .category-badge.category-mixed     { background: #e0e7ff; color: #3730a3; }

        .status-pill-sm {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.18rem 0.55rem;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
        }
        .status-pill-sm.correct { background: #dcfce7; color: #166534; }
        .status-pill-sm.wrong   { background: #fee2e2; color: #991b1b; }

        /* ── buttons ── */
        .btn-green {
            background: linear-gradient(135deg, var(--mid-green) 0%, var(--primary-green) 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.35rem 0.8rem;
            font-size: 0.76rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(39,74,23,0.15);
        }
        .btn-green:hover { transform: translateY(-1px); color: #fff; }
        .btn-green:disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }

        .btn-filter {
            background: #fff;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            padding: 0.35rem 0.75rem;
            font-size: 0.76rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            color: var(--dark-gray);
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .btn-filter:hover { border-color: var(--mid-green); }

        .form-select-sm {
            font-family: 'Poppins', sans-serif;
            font-size: 0.76rem;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
        }
        .form-select-sm:focus { border-color: var(--mid-green); box-shadow: 0 0 0 3px rgba(54,129,55,0.1); }

        /* ── empty state ── */
        .empty-table {
            text-align: center;
            padding: 2rem;
            color: var(--medium-gray);
        }
        .empty-table i { font-size: 2rem; display: block; margin-bottom: 0.5rem; }
        .empty-table p { font-size: 0.82rem; margin: 0; }

        /* ── date modal ── */
        .modal-content {
            border-radius: 16px;
            border: none;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 8px 32px rgba(0,0,0,0.14);
        }
        .modal-header { border-bottom: 1px solid #f3f4f6; padding: 1.1rem 1.25rem; }
        .modal-title  { font-size: 0.92rem; font-weight: 700; }
        .modal-body   { padding: 1.25rem; }
        .modal-footer { border-top: 1px solid #f3f4f6; padding: 0.9rem 1.25rem; }

        input[type="date"] {
            font-family: 'Poppins', sans-serif;
            font-size: 0.82rem;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            padding: 0.45rem 0.75rem;
            width: 100%;
            outline: none;
            transition: border-color 0.2s;
        }
        input[type="date"]:focus { border-color: var(--mid-green); box-shadow: 0 0 0 3px rgba(54,129,55,0.1); }

        @media (max-width: 992px) {
            #main-content-wrapper { margin-left: 0; padding: 12px; }
            .review-outer { padding: 0; }
            .nav-arrow-left  { left: -10px; }
            .nav-arrow-right { right: -10px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container-fluid">

            <?php include 'topbar.php'; ?>

            <div class="section-container">

                <!-- Back -->
                <a href="GoSort_WasteMonitoringNavpage.php" class="back-link">
                    <i class="bi bi-arrow-left"></i> Back to Devices
                </a>

                <!-- Context strip -->
                <div class="context-strip">
                    <span class="ctx-badge"><i class="bi bi-cpu-fill"></i><?= htmlspecialchars($deviceName) ?></span>
                    <span class="ctx-badge"><i class="bi bi-calendar3"></i><?= date('F j, Y', strtotime($selectedDate)) ?></span>
                    <button class="date-change-btn" data-bs-toggle="modal" data-bs-target="#changeDateModal">
                        <i class="bi bi-pencil-fill"></i> Change Date
                    </button>
                </div>

                <!-- Stats -->
                <div class="section-block">
                    <div class="section-label">Review Summary</div>
                    <div class="stats-row">
                        <div class="stat-pill total">
                            <div class="stat-pill-num" id="statTotal"><?= $totalDetections ?></div>
                            <div class="stat-pill-label">Total</div>
                        </div>
                        <div class="stat-pill pending">
                            <div class="stat-pill-num" id="statPending"><?= $pendingCount ?></div>
                            <div class="stat-pill-label">Pending</div>
                        </div>
                        <div class="stat-pill correct">
                            <div class="stat-pill-num" id="statCorrect"><?= $correctCount ?></div>
                            <div class="stat-pill-label">Correct</div>
                        </div>
                        <div class="stat-pill wrong">
                            <div class="stat-pill-num" id="statWrong"><?= $wrongCount ?></div>
                            <div class="stat-pill-label">Wrong</div>
                        </div>
                    </div>
                </div>

                <!-- Review card -->
                <div class="review-outer">
                    <div class="review-body">

                        <div class="counter-label">
                            <span id="currentCount">1</span> of <span id="totalCount"><?= $totalDetections ?></span>
                        </div>

                        <h2 class="waste-category" id="wasteCategory">—</h2>
                        <p class="waste-subtitle">
                            Detected: <span class="detected-chip" id="detectedItem">—</span>
                        </p>

                        <div class="image-wrapper">
                            <div class="nav-arrow nav-arrow-left disabled" id="prevBtn">
                                <i class="bi bi-chevron-left"></i>
                            </div>

                            <div class="image-box">
                                <span class="review-badge-overlay pending" id="reviewBadge" style="display:none;">Pending</span>
                                <div class="image-placeholder" id="imagePlaceholder">
                                    <i class="bi bi-camera-fill"></i>
                                    <p><?= $totalDetections === 0 ? 'No detections for this date' : 'Loading…' ?></p>
                                </div>
                                <img id="detectionImg" src="" alt="Detection" style="display:none;">
                            </div>

                            <div class="nav-arrow nav-arrow-right disabled" id="nextBtn">
                                <i class="bi bi-chevron-right"></i>
                            </div>
                        </div>

                        <div class="review-actions">
                            <button class="rev-btn" id="wrongBtn">
                                <div class="rev-btn-icon wrong"><i class="bi bi-x-lg"></i></div>
                                <span class="rev-btn-label">Wrong</span>
                            </button>
                            <button class="rev-btn" id="correctBtn">
                                <div class="rev-btn-icon correct"><i class="bi bi-check-lg"></i></div>
                                <span class="rev-btn-label">Correct</span>
                            </button>
                        </div>

                    </div>
                </div>

                <!-- Reviewed table -->
                <div class="section-block">
                    <div class="section-label">Reviewed Items</div>
                    <div class="inner-card" style="padding:0;overflow:hidden;">
                        <div class="table-outer-header">
                            <span class="table-outer-title"><i class="bi bi-table me-1"></i>Reviewed Items</span>
                            <div class="d-flex gap-2 align-items-center">
                                <select class="form-select form-select-sm btn-filter" id="tableFilter" style="width:auto;">
                                    <option value="all">All Reviewed</option>
                                    <option value="correct">Correct Only</option>
                                    <option value="wrong">Wrong Only</option>
                                </select>
                                <button class="btn-green" id="exportZipBtn" <?= $reviewedCount == 0 ? 'disabled' : '' ?>>
                                    <i class="bi bi-download"></i> Export ZIP
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover reviewed-table">
                                <thead>
                                    <tr>
                                        <th style="width:68px;">Image</th>
                                        <th>Category</th>
                                        <th>Detected Item</th>
                                        <th>Confidence</th>
                                        <th>Time</th>
                                        <th style="width:100px;">Status</th>
                                        <th style="width:70px;">View</th>
                                    </tr>
                                </thead>
                                <tbody id="reviewedTableBody">
                                    <?php foreach ($sortingHistory as $idx => $det):
                                        if ($det['review_status'] === null) continue;
                                        $isCorrect = $det['review_status'] == 1;
                                        $catMap = ['bio'=>'Biodegradable','nbio'=>'Non-Biodegradable','hazardous'=>'Hazardous','mixed'=>'Mixed'];
                                        $catLabel = $catMap[$det['trash_type']] ?? ucfirst($det['trash_type']);
                                    ?>
                                    <tr data-id="<?= $det['id'] ?>" data-status="<?= $isCorrect ? 'correct' : 'wrong' ?>">
                                        <td>
                                            <img src="data:image/jpeg;base64,<?= $det['image_data'] ?>"
                                                 class="table-thumbnail"
                                                 onclick="viewImage(<?= $idx ?>)"
                                                 alt="img">
                                        </td>
                                        <td><span class="category-badge category-<?= $det['trash_type'] ?>"><?= $catLabel ?></span></td>
                                        <td><?= htmlspecialchars($det['trash_class']) ?></td>
                                        <td><?= number_format($det['confidence'] * 100, 1) ?>%</td>
                                        <td><?= date('g:i A', strtotime($det['sorted_at'])) ?></td>
                                        <td>
                                            <?php if ($isCorrect): ?>
                                                <span class="status-pill-sm correct"><i class="bi bi-check-circle-fill"></i> Correct</span>
                                            <?php else: ?>
                                                <span class="status-pill-sm wrong"><i class="bi bi-x-circle-fill"></i> Wrong</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="viewImage(<?= $idx ?>)" style="border-radius:7px;font-size:0.75rem;">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div id="noReviewedMsg" class="empty-table" style="<?= $reviewedCount > 0 ? 'display:none;' : '' ?>">
                            <i class="bi bi-inbox"></i>
                            <p>No reviewed items yet. Mark items above.</p>
                        </div>
                    </div>
                </div>

            </div><!-- /section-container -->
        </div>
    </div>

    <!-- ── Change Date Modal ── -->
    <div class="modal fade" id="changeDateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:340px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar3 me-2" style="color:var(--primary-green);"></i>Change Date</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label style="font-size:0.78rem;font-weight:600;color:var(--dark-gray);display:block;margin-bottom:0.3rem;">Select Date</label>
                    <input type="date" id="newDateInput" max="<?= date('Y-m-d') ?>" value="<?= $selectedDate ?>">
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal" style="font-family:'Poppins',sans-serif;font-size:0.82rem;border-radius:8px;">Cancel</button>
                    <button class="btn-green" onclick="changeDate()"><i class="bi bi-arrow-right"></i> Go</button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        const detections    = <?= json_encode($sortingHistory) ?>;
        const deviceIdentity = '<?= addslashes($deviceIdentity) ?>';
        let currentIndex = 0;

        let stats = {
            total:   <?= $totalDetections ?>,
            pending: <?= $pendingCount ?>,
            correct: <?= $correctCount ?>,
            wrong:   <?= $wrongCount ?>
        };

        // ── Date change ──
        function changeDate() {
            const d = document.getElementById('newDateInput').value;
            if (!d) return;
            const url = new URL(window.location.href);
            url.searchParams.set('date', d);
            window.location.href = url.toString();
        }

        // ── Display ──
        function updateDisplay() {
            if (detections.length === 0) {
                document.getElementById('wasteCategory').textContent = 'No Detections';
                document.getElementById('detectedItem').textContent  = '—';
                document.getElementById('currentCount').textContent  = '0';
                document.getElementById('totalCount').textContent    = '0';
                document.getElementById('reviewBadge').style.display = 'none';
                return;
            }

            const d = detections[currentIndex];
            document.getElementById('currentCount').textContent  = currentIndex + 1;
            document.getElementById('wasteCategory').textContent = d.trash_type.toUpperCase();
            document.getElementById('detectedItem').textContent  = d.trash_class || '—';

            const img = document.getElementById('detectionImg');
            img.src   = `data:image/jpeg;base64,${d.image_data}`;
            img.style.display = 'block';
            document.getElementById('imagePlaceholder').style.display = 'none';

            updateBadge(d);
            updateArrows();
        }

        function updateBadge(d) {
            const badge = document.getElementById('reviewBadge');
            badge.style.display = 'block';
            if (d.review_status === null || d.review_status === undefined) {
                badge.className = 'review-badge-overlay pending';
                badge.textContent = 'Pending Review';
            } else if (d.review_status == 1) {
                badge.className = 'review-badge-overlay correct';
                badge.textContent = '✓ Correct';
            } else {
                badge.className = 'review-badge-overlay wrong';
                badge.textContent = '✗ Wrong';
            }
        }

        function updateArrows() {
            document.getElementById('prevBtn').classList.toggle('disabled', currentIndex === 0);
            document.getElementById('nextBtn').classList.toggle('disabled', currentIndex === detections.length - 1);
        }

        function updateStatsUI() {
            document.getElementById('statTotal').textContent   = stats.total;
            document.getElementById('statPending').textContent = stats.pending;
            document.getElementById('statCorrect').textContent = stats.correct;
            document.getElementById('statWrong').textContent   = stats.wrong;
        }

        // ── Navigation ──
        document.getElementById('prevBtn').addEventListener('click', () => { if (currentIndex > 0) { currentIndex--; updateDisplay(); } });
        document.getElementById('nextBtn').addEventListener('click', () => { if (currentIndex < detections.length - 1) { currentIndex++; updateDisplay(); } });
        document.addEventListener('keydown', e => {
            if (e.key === 'ArrowLeft')  document.getElementById('prevBtn').click();
            if (e.key === 'ArrowRight') document.getElementById('nextBtn').click();
        });

        // ── Review ──
        async function markDetection(isCorrect) {
            if (detections.length === 0) return;
            const d   = detections[currentIndex];
            const url = isCorrect ? 'api/mark_sorting_correct.php' : 'api/mark_sorting_wrong.php';
            try {
                const res    = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ sorting_id: d.id, device_identity: deviceIdentity }) });
                const result = await res.json();
                if (!result.success) throw new Error(result.message);

                const wasReviewed = d.review_status !== null;
                const wasCorrect  = d.review_status == 1;

                if (!wasReviewed) {
                    stats.pending--;
                    isCorrect ? stats.correct++ : stats.wrong++;
                } else {
                    if (wasCorrect && !isCorrect)  { stats.correct--; stats.wrong++; }
                    if (!wasCorrect && isCorrect)  { stats.wrong--;  stats.correct++; }
                }

                d.review_status = isCorrect ? 1 : 0;
                updateBadge(d);
                updateStatsUI();
                addOrUpdateTableRow(d, isCorrect);

                // Flash icon
                const icon = document.querySelector(`#${isCorrect ? 'correctBtn' : 'wrongBtn'} .rev-btn-icon`);
                icon.style.background = isCorrect ? '#059669' : '#dc2626';
                icon.style.color      = '#fff';
                setTimeout(() => {
                    icon.style.background = '';
                    icon.style.color      = '';
                    if (currentIndex < detections.length - 1) { currentIndex++; updateDisplay(); }
                }, 400);

            } catch (err) {
                alert('Failed to save review: ' + err.message);
            }
        }

        document.getElementById('wrongBtn').addEventListener('click',   () => markDetection(false));
        document.getElementById('correctBtn').addEventListener('click',  () => markDetection(true));

        // ── Table ──
        function addOrUpdateTableRow(d, isCorrect) {
            const tbody = document.getElementById('reviewedTableBody');
            const existing = tbody.querySelector(`tr[data-id="${d.id}"]`);
            const catMap = { bio:'Biodegradable', nbio:'Non-Biodegradable', hazardous:'Hazardous', mixed:'Mixed' };
            const catLabel  = catMap[d.trash_type] || d.trash_type;
            const statusCls = isCorrect ? 'correct' : 'wrong';
            const statusTxt = isCorrect ? 'Correct' : 'Wrong';
            const statusIco = isCorrect ? 'check-circle-fill' : 'x-circle-fill';
            const conf      = (d.confidence * 100).toFixed(1);
            const sortTime  = new Date(d.sorted_at).toLocaleTimeString('en-US', {hour:'numeric',minute:'2-digit',hour12:true});
            const idx       = detections.indexOf(d);

            const inner = `
                <td><img src="data:image/jpeg;base64,${d.image_data}" class="table-thumbnail" onclick="viewImage(${idx})" alt="img"></td>
                <td><span class="category-badge category-${d.trash_type}">${catLabel}</span></td>
                <td>${d.trash_class || '—'}</td>
                <td>${conf}%</td>
                <td>${sortTime}</td>
                <td><span class="status-pill-sm ${statusCls}"><i class="bi bi-${statusIco}"></i> ${statusTxt}</span></td>
                <td><button class="btn btn-sm btn-outline-secondary" onclick="viewImage(${idx})" style="border-radius:7px;font-size:0.75rem;"><i class="bi bi-eye"></i></button></td>
            `;

            if (existing) {
                existing.dataset.status = statusCls;
                existing.innerHTML = inner;
            } else {
                const row = document.createElement('tr');
                row.dataset.id     = d.id;
                row.dataset.status = statusCls;
                row.innerHTML      = inner;
                tbody.insertBefore(row, tbody.firstChild);
            }

            document.getElementById('noReviewedMsg').style.display = 'none';
            document.getElementById('exportZipBtn').disabled = false;

            const filter = document.getElementById('tableFilter').value;
            if (filter !== 'all' && statusCls !== filter) {
                const row = tbody.querySelector(`tr[data-id="${d.id}"]`);
                if (row) row.style.display = 'none';
            }
        }

        function viewImage(index) {
            currentIndex = index;
            updateDisplay();
            document.querySelector('.review-outer').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        document.getElementById('tableFilter').addEventListener('change', function () {
            const f = this.value;
            document.querySelectorAll('#reviewedTableBody tr').forEach(row => {
                row.style.display = (f === 'all' || row.dataset.status === f) ? '' : 'none';
            });
        });

        document.getElementById('exportZipBtn').addEventListener('click', () => {
            const f = document.getElementById('tableFilter').value;
            window.location.href = `api/export_reviewed_images.php?identity=${encodeURIComponent(deviceIdentity)}&date=<?= $selectedDate ?>&filter=${f}`;
        });

        // ── Init ──
        document.addEventListener('DOMContentLoaded', () => {
            if (detections.length > 0) updateDisplay();
        });
    </script>
</body>
</html>