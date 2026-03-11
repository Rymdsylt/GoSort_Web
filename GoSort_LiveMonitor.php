<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';
require_once 'gs_DB/activity_logs.php';

if (isset($_GET['logout'])) {
    if (isset($_SESSION['user_id'])) { log_logout($_SESSION['user_id']); }
    session_destroy();
    setcookie('user_logged_in', '', time() - 3600, '/');
    header("Location: GoSort_Login.php");
    exit();
}

if (!isset($_SESSION['user_id']) || !isset($_COOKIE['user_logged_in'])) {
    header("Location: GoSort_Login.php");
    exit();
}

$deviceId = $_GET['device'] ?? null;
if (!$deviceId) {
    header("Location: GoSort_WasteMonitoringNavpage.php");
    exit();
}

$stmt = $pdo->prepare("SELECT id, device_name, device_identity, status, location, maintenance_mode, last_active FROM sorters WHERE id = ?");
$stmt->execute([$deviceId]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device) {
    header("Location: GoSort_WasteMonitoringNavpage.php");
    exit();
}

if ($device['status'] === 'offline' && !$device['maintenance_mode']) {
    header("Location: GoSort_WasteMonitoringNavpage.php?error=offline");
    exit();
}

$deviceName     = $device['device_name'];
$deviceIdentity = $device['device_identity'];
$deviceLocation = $device['location'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Live Monitor</title>
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
            padding: 100px 0 20px 0;
            height: 100vh;
            overflow-y: auto;
        }

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

        /* ── device badge bar ── */
        .device-badge-bar {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .device-badge {
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

        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #fee2e2;
            border: 1px solid #fca5a5;
            border-radius: 20px;
            padding: 0.3rem 0.8rem;
            font-size: 0.76rem;
            font-weight: 600;
            color: #dc2626;
        }

        .live-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #dc2626;
            animation: livepulse 1.4s ease-in-out infinite;
        }

        @keyframes livepulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.4; transform: scale(0.8); }
        }

        /* ── detection card ── */
        .detection-outer {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .detection-header-bar {
            background: linear-gradient(135deg, var(--mid-green) 0%, var(--primary-green) 100%);
            padding: 0.85rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .detection-header-bar h6 {
            color: #fff;
            font-size: 0.82rem;
            font-weight: 700;
            margin: 0;
        }

        .auto-update-label {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.75);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .detection-body {
            padding: 1.25rem;
        }

        /* ── image area ── */
        .image-well {
            width: 100%;
            min-height: 320px;
            background: #f9fafb;
            border: 1.5px dashed #d1d5db;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 1.1rem;
        }

        .image-well img {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
            border-radius: 8px;
        }

        .image-placeholder {
            text-align: center;
            color: var(--medium-gray);
        }
        .image-placeholder i { font-size: 2.5rem; display: block; margin-bottom: 0.5rem; opacity: 0.35; }
        .image-placeholder p { font-size: 0.82rem; margin: 0; }

        /* ── info grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 576px) {
            .info-grid { grid-template-columns: 1fr; }
        }

        .info-cell {
            background: linear-gradient(135deg, rgb(236,251,234) 0%, #d5f5dc 100%);
            border-radius: 10px;
            padding: 0.85rem 1rem;
            text-align: center;
        }

        .info-cell-label {
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--medium-gray);
            margin-bottom: 0.3rem;
        }

        .info-cell-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-gray);
        }

        .confidence-pill {
            display: inline-block;
            background: var(--light-green);
            color: var(--primary-green);
            padding: 0.2rem 0.7rem;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 700;
        }

        /* ── detected items tags ── */
        .tags-area {
            background: #f9fafb;
            border-radius: 10px;
            padding: 0.85rem 1rem;
        }

        .tags-area-label {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--medium-gray);
            margin-bottom: 0.5rem;
        }

        .item-tag {
            display: inline-block;
            background: #e8f5e1;
            color: var(--primary-green);
            border: 1px solid #c8e6c9;
            padding: 0.22rem 0.65rem;
            border-radius: 20px;
            margin: 0.2rem;
            font-size: 0.76rem;
            font-weight: 600;
        }

        /* ── error alert ── */
        .error-box {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            color: #dc2626;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (max-width: 992px) {
            #main-content-wrapper { margin-left: 0; padding: 12px; }
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

                <!-- Device badge bar -->
                <div class="device-badge-bar">
                    <span class="device-badge"><i class="bi bi-cpu-fill"></i><?= htmlspecialchars($deviceName) ?></span>
                    <span class="device-badge"><i class="bi bi-geo-alt-fill"></i><?= htmlspecialchars($deviceLocation) ?></span>
                    <span class="live-badge"><span class="live-dot"></span>Live</span>
                </div>

                <!-- Detection card -->
                <div class="section-block">
                    <div class="detection-outer">
                        <div class="detection-header-bar">
                            <h6><i class="bi bi-broadcast me-1"></i>Latest Detection</h6>
                            <div class="auto-update-label">
                                <span>Updates every 2s</span>
                                <div class="spinner-border spinner-border-sm text-light" role="status" id="updateSpinner" style="display:none;width:12px;height:12px;border-width:2px;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>

                        <div class="detection-body" id="detectionContent">
                            <!-- Populated by JS -->
                            <div class="image-well">
                                <div class="image-placeholder">
                                    <i class="bi bi-camera-fill"></i>
                                    <p>Waiting for detections…</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /section-container -->
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        async function updateDetection() {
            const spinner = document.getElementById('updateSpinner');
            const content = document.getElementById('detectionContent');
            try {
                spinner.style.display = 'inline-block';
                const res  = await fetch(`api/get_latest_detection.php?identity=<?= urlencode($deviceIdentity) ?>&device=<?= urlencode($deviceId) ?>`);
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();

                if (data.success && data.data) {
                    const d = data.data;
                    const tags = d.trash_class
                        ? d.trash_class.split(',').map(t => `<span class="item-tag">${t.trim()}</span>`).join('')
                        : '';

                    content.innerHTML = `
                        <div class="image-well">
                            <img src="data:image/jpeg;base64,${d.image_data}" alt="Detection">
                        </div>
                        <div class="info-grid">
                            <div class="info-cell">
                                <div class="info-cell-label">Type</div>
                                <div class="info-cell-value">${d.trash_type.toUpperCase()}</div>
                            </div>
                            <div class="info-cell">
                                <div class="info-cell-label">Confidence</div>
                                <div class="info-cell-value">
                                    <span class="confidence-pill">${(d.confidence * 100).toFixed(1)}%</span>
                                </div>
                            </div>
                            <div class="info-cell">
                                <div class="info-cell-label">Time</div>
                                <div class="info-cell-value" style="font-size:0.82rem;">
                                    ${new Date(d.sorted_at).toLocaleTimeString('en-US', {hour:'numeric',minute:'2-digit',hour12:true})}
                                </div>
                            </div>
                        </div>
                        ${tags ? `<div class="tags-area"><div class="tags-area-label">Detected Items</div>${tags}</div>` : ''}
                    `;
                } else {
                    content.innerHTML = `
                        <div class="image-well">
                            <div class="image-placeholder">
                                <i class="bi bi-camera-fill"></i>
                                <p>No detections yet</p>
                            </div>
                        </div>
                    `;
                }
            } catch (e) {
                content.innerHTML = `
                    <div class="error-box">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Error loading detection data. Retrying…
                    </div>
                `;
            } finally {
                spinner.style.display = 'none';
            }
        }

        updateDetection();
        setInterval(updateDetection, 2000);
    </script>
</body>
</html>