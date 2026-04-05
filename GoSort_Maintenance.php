<?php
session_start();
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';
require_once 'gs_DB/activity_logs.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: GoSort_Login.php");
    exit();
}

$device_id       = $_GET['device']   ?? null;
$device_name     = $_GET['name']     ?? 'Unknown Device';
$device_identity = $_GET['identity'] ?? null;

if (!$device_id || !$device_identity) {
    header("Location: GoSort_Sorters.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script>
        setTimeout(function() { window.location.href = 'GoSort_MaintenanceNavpage.php'; }, 60000);
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Maintenance</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-green:   #274a17;
            --light-green:     #7AF146;
            --mid-green:       #368137;
            --dark-gray:       #1f2937;
            --medium-gray:     #6b7280;
            --border-color:    #e5e7eb;
            --card-shadow:     0 1px 3px rgba(0,0,0,0.07);
            --bio-color:       #10b981;
            --nbio-color:      #ef4444;
            --hazardous-color: #f59e0b;
            --mixed-color:     #6b7280;
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
            margin-bottom: 1.25rem;
        }

        /* ── Page header ── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--medium-gray);
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--primary-green); }

        .device-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #e8f5e1;
            color: var(--primary-green);
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .device-badge .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #15803d;
            box-shadow: 0 0 6px rgba(21,128,61,0.5);
        }

        /* ── Inner card ── */
        .inner-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: var(--card-shadow);
        }
        .inner-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .inner-card-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .inner-card-title i { color: var(--primary-green); }
        .inner-card-icon {
            width: 32px; height: 32px;
            background: #e8f5e1;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: var(--primary-green);
            font-size: 0.9rem;
        }

        /* ── Connection status ── */
        .connection-status {
            border-radius: 10px;
            padding: 0.7rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.8rem;
            font-weight: 500;
            border: none;
            margin-bottom: 1rem;
        }
        .connection-status.alert-success { background: rgba(16,185,129,0.1); color: #065f46; }
        .connection-status.alert-danger  { background: rgba(239,68,68,0.1);  color: #991b1b; }
        .connection-status.alert-warning { background: rgba(245,158,11,0.1); color: #92400e; }

        /* ── Tabs ── */
        .maintenance-tabs {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 1rem;
            background: #f3f4f6;
            border-radius: 10px;
            padding: 0.25rem;
        }
        .tab-btn {
            flex: 1;
            padding: 0.55rem 0.75rem;
            border: none;
            background: transparent;
            color: var(--medium-gray);
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            font-size: 0.78rem;
            cursor: pointer;
            transition: all 0.2s;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            white-space: nowrap;
        }
        .tab-btn:hover { color: var(--dark-gray); background: rgba(255,255,255,0.6); }
        .tab-btn.active {
            background: #fff;
            color: var(--primary-green);
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

        /* ── Status alert ── */
        #statusAlertContainer .alert {
            border-radius: 10px;
            border: none;
            font-size: 0.8rem;
            padding: 0.65rem 1rem;
            margin-bottom: 0.85rem;
        }

        /* ── Servo mapping ── */
        .servo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            max-width: 480px;
            margin: 0 auto 1rem;
        }
        .servo-position {
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.2s;
            border: 1.5px solid;
        }
        .servo-position:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .servo-position h6 { font-size: 0.75rem; font-weight: 700; margin: 0; text-transform: uppercase; letter-spacing: 0.04em; }

        .servo-position.bio       { border-color: var(--bio-color);       background: rgba(16,185,129,0.05); }
        .servo-position.bio h6    { color: var(--bio-color); }
        .servo-position.nbio      { border-color: var(--nbio-color);      background: rgba(239,68,68,0.05); }
        .servo-position.nbio h6   { color: var(--nbio-color); }
        .servo-position.hazardous { border-color: var(--hazardous-color); background: rgba(245,158,11,0.05); }
        .servo-position.hazardous h6 { color: var(--hazardous-color); }
        .servo-position.mixed     { border-color: var(--mixed-color);     background: rgba(107,114,128,0.05); }
        .servo-position.mixed h6  { color: var(--mixed-color); }

        .quadrant-btn {
            width: 100%;
            padding: 0.45rem 0.75rem;
            font-size: 0.78rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            border-radius: 7px;
            border: 1.5px solid currentColor;
            background: transparent;
            cursor: pointer;
            transition: all 0.2s;
        }
        .quadrant-btn.bio       { color: var(--bio-color); }
        .quadrant-btn.bio:hover { background: var(--bio-color); color: white; }
        .quadrant-btn.nbio       { color: var(--nbio-color); }
        .quadrant-btn.nbio:hover { background: var(--nbio-color); color: white; }
        .quadrant-btn.hazardous       { color: var(--hazardous-color); }
        .quadrant-btn.hazardous:hover { background: var(--hazardous-color); color: white; }
        .quadrant-btn.mixed       { color: var(--mixed-color); }
        .quadrant-btn.mixed:hover { background: var(--mixed-color); color: white; }

        /* ── Mapping preview ── */
        .mapping-preview {
            background: #f9fafb;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .mapping-preview-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .mapping-preview-item {
            display: flex;
            justify-content: space-between;
            padding: 0.45rem 0;
            font-size: 0.78rem;
            color: var(--medium-gray);
            border-bottom: 1px solid #f3f4f6;
        }
        .mapping-preview-item:last-child { border-bottom: none; }
        .mapping-preview-item span:first-child { font-weight: 600; color: var(--dark-gray); }

        /* ── Buttons ── */
        .btn-green {
            background: linear-gradient(135deg, var(--mid-green) 0%, var(--primary-green) 100%);
            border: none;
            border-radius: 8px;
            padding: 0.55rem 1.25rem;
            font-size: 0.82rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            color: #fff;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(39,74,23,0.15);
        }
        .btn-green:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(39,74,23,0.25); }
        .btn-green:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-control {
            width: 100%;
            padding: 0.65rem 1rem;
            font-size: 0.82rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: all 0.2s;
            color: white;
        }
        .btn-control:hover:not(:disabled) { transform: translateY(-1px); filter: brightness(1.05); }
        .btn-control:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(0.5); }
        .btn-control.bio       { background: var(--bio-color); }
        .btn-control.nbio      { background: var(--nbio-color); }
        .btn-control.hazardous { background: var(--hazardous-color); }
        .btn-control.mixed     { background: var(--mixed-color); }
        .btn-control.warning   { background: #f59e0b; }
        .btn-control.dark      { background: var(--dark-gray); }
        .btn-control.info      { background: #0ea5e9; }

        /* ── Warning note ── */
        .warning-note {
            background: rgba(245,158,11,0.08);
            border-left: 3px solid var(--hazardous-color);
            border-radius: 8px;
            padding: 0.65rem 0.9rem;
            font-size: 0.78rem;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.85rem;
        }

        /* ── Floating progress ── */
        .floating-progress {
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 280px;
            background: rgba(15,23,42,0.97);
            color: white;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            z-index: 9999;
            display: none;
            backdrop-filter: blur(10px);
            font-family: 'Poppins', sans-serif;
        }
        .floating-progress .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.85rem;
        }
        .floating-progress .progress-title { font-weight: 600; font-size: 0.82rem; }
        .floating-progress .progress-time  { font-size: 0.75rem; opacity: 0.7; }
        .floating-progress .progress-bar-wrap {
            height: 5px;
            border-radius: 3px;
            background: rgba(255,255,255,0.15);
            overflow: hidden;
        }
        .floating-progress .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--light-green), var(--mid-green));
            border-radius: 3px;
            transition: width 0.3s ease;
            width: 0%;
        }
        .floating-progress .progress-status { font-size: 0.72rem; margin-top: 0.6rem; opacity: 0.85; }

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

                <!-- Page header -->
                <div class="page-header">
                    <div class="d-flex align-items-center gap-3">
                        <a href="GoSort_MaintenanceNavpage.php" class="back-link">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                        <div class="device-badge">
                            <span class="dot"></span>
                            <i class="bi bi-cpu-fill"></i>
                            <?php echo htmlspecialchars($device_name); ?>
                        </div>
                    </div>
                </div>

                <!-- Status alert -->
                <div id="statusAlertContainer"></div>

                <!-- Connection status -->
                <div class="connection-status alert" role="alert">
                    <i class="bi bi-hourglass-split"></i> Checking connection status…
                </div>

                <!-- Tabs -->
                <div class="maintenance-tabs">
                    <button class="tab-btn active" onclick="switchTab('mapping', this)">
                        <i class="bi bi-diagram-3"></i> Position Mapping
                    </button>
                    <button class="tab-btn" onclick="switchTab('controls', this)">
                        <i class="bi bi-sliders"></i> Device Controls
                    </button>
                    <button class="tab-btn" onclick="switchTab('tests', this)">
                        <i class="bi bi-vial"></i> Test Operations
                    </button>
                </div>

                <!-- Tab 1: Position Mapping -->
                <div id="mapping" class="tab-content active">
                    <div class="section-block">
                        <div class="section-label">Position Mapping</div>
                        <p style="font-size:0.78rem; color:var(--medium-gray); margin-bottom:1rem;">
                            Assign each position to a trash type. Each position must have a unique type.
                        </p>

                        <div class="servo-grid">
                            <div class="servo-position bio" id="pos-zdeg">
                                <h6>Back – Left</h6>
                                <button id="btn-zdeg" class="quadrant-btn bio">Bio</button>
                            </div>
                            <div class="servo-position hazardous" id="pos-odeg">
                                <h6>Back – Right</h6>
                                <button id="btn-odeg" class="quadrant-btn hazardous">Hazardous</button>
                            </div>
                            <div class="servo-position nbio" id="pos-ndeg">
                                <h6>Front – Left</h6>
                                <button id="btn-ndeg" class="quadrant-btn nbio">Non-Bio</button>
                            </div>
                            <div class="servo-position mixed" id="pos-mdeg">
                                <h6>Front – Right</h6>
                                <button id="btn-mdeg" class="quadrant-btn mixed">Mixed</button>
                            </div>
                        </div>

                        <div class="mapping-preview">
                            <div class="mapping-preview-title">Current Mapping</div>
                            <div class="mapping-preview-item"><span>Back – Left</span>  <span id="preview-back-left">Bio</span></div>
                            <div class="mapping-preview-item"><span>Back – Right</span> <span id="preview-back-right">Hazardous</span></div>
                            <div class="mapping-preview-item"><span>Front – Left</span> <span id="preview-front-left">Non-Bio</span></div>
                            <div class="mapping-preview-item"><span>Front – Right</span><span id="preview-front-right">Mixed</span></div>
                        </div>

                        <div class="text-center">
                            <button class="btn-green" style="min-width:200px;" onclick="saveQuadrantMapping()">
                                <i class="bi bi-save"></i> Save Mapping
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Device Controls -->
                <div id="controls" class="tab-content">
                    <div class="section-block">
                        <div class="section-label">Move to Position</div>
                        <div id="dynamic-servo-controls" class="row g-2 mb-2"></div>
                    </div>

                    <div class="section-block">
                        <div class="section-label">Unclog Control</div>
                        <p style="font-size:0.78rem; color:var(--medium-gray); margin-bottom:0.85rem;">
                            Tilts mechanism up for 3 seconds while maintaining current pan position.
                        </p>
                        <button class="btn-control warning" style="max-width:320px; margin:0 auto; display:flex;" onclick="moveServo('unclog')">
                            <i class="bi bi-wrench"></i> Unclog Current Section
                        </button>
                    </div>

                    <div class="section-block">
                        <div class="section-label">Device Management</div>
                        <div class="warning-note">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            Remember to turn off the device's AVR after shutting down.
                        </div>
                        <button class="btn-control dark" id="shutdownBtn" style="max-width:320px; margin:0 auto; display:flex;">
                            <i class="bi bi-power-off"></i> Shut Down Device
                        </button>
                    </div>
                </div>

                <!-- Tab 3: Test Operations -->
                <div id="tests" class="tab-content">
                    <div class="section-block">
                        <div class="section-label">Test Operations</div>
                        <div class="warning-note">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            WARNING: Do not initiate when clogged!
                        </div>
                        <p style="font-size:0.78rem; color:var(--medium-gray); margin-bottom:0.85rem;">
                            Run these tests to verify device movement and functionality.
                        </p>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <button class="btn-control info" onclick="confirmOperation('sweep1','Test Pan Sweep Only')">
                                    <i class="bi bi-arrows-alt-h"></i> Test Pan Sweep Only
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button class="btn-control info" onclick="confirmOperation('sweep2','Test Full Sweep')">
                                    <i class="bi bi-arrows-alt"></i> Test Full Sweep
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /section-container -->
        </div>
    </div>

    <!-- Floating Progress -->
    <div id="floatingProgress" class="floating-progress">
        <div class="progress-header">
            <div class="progress-title" id="progressTitle">Operation in Progress</div>
            <div class="progress-time" id="progressTime">0s</div>
        </div>
        <div class="progress-bar-wrap">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        <div class="progress-status" id="progressStatus">Initializing…</div>
    </div>

    <!-- Shutdown Modal -->
    <div class="modal fade" id="shutdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px; border:none; font-family:'Poppins',sans-serif;">
                <div class="modal-body p-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-exclamation-circle-fill" style="color:var(--hazardous-color); font-size:1.4rem;"></i>
                        <h5 class="modal-title mb-0" style="font-size:0.95rem; font-weight:700;">Confirm Shutdown</h5>
                    </div>
                    <p style="font-size:0.82rem; color:var(--medium-gray); margin-bottom:1.25rem;">Are you sure? Remember to turn off the device's AVR after shutting down.</p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1" data-bs-dismiss="modal" style="font-family:'Poppins',sans-serif; font-size:0.82rem; border-radius:8px;">Cancel</button>
                        <button type="button" class="btn btn-danger flex-grow-1" id="confirmShutdownBtn" style="font-family:'Poppins',sans-serif; font-size:0.82rem; border-radius:8px;">Shut Down</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Operation Modal -->
    <div class="modal fade" id="operationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px; border:none; font-family:'Poppins',sans-serif;">
                <div class="modal-body p-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-exclamation-triangle-fill" style="color:var(--hazardous-color); font-size:1.4rem;"></i>
                        <h5 class="modal-title mb-0" style="font-size:0.95rem; font-weight:700;">Confirm Operation</h5>
                    </div>
                    <p style="font-size:0.82rem; color:var(--medium-gray); margin-bottom:0.5rem;">Make sure the device is <strong>not clogged</strong> before proceeding.</p>
                    <p style="font-size:0.82rem; margin-bottom:1.25rem;">Execute: <strong id="operationName">Operation</strong>?</p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1" data-bs-dismiss="modal" style="font-family:'Poppins',sans-serif; font-size:0.82rem; border-radius:8px;">Cancel</button>
                        <button type="button" class="btn flex-grow-1" id="confirmOperationBtn" style="background:var(--primary-green); color:white; font-family:'Poppins',sans-serif; font-size:0.82rem; border-radius:8px;">Execute</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
    // ── Tab switching ──────────────────────────────────────────────
    function switchTab(tabName, btnEl) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(tabName).classList.add('active');
        btnEl.classList.add('active');
    }

    // ── Status alert ──────────────────────────────────────────────
    function showStatusAlert(message, type = 'danger') {
        const c = document.getElementById('statusAlertContainer');
        const cls = {success:'alert-success', warning:'alert-warning', info:'alert-info', danger:'alert-danger'}[type] || 'alert-danger';
        c.innerHTML = `<div class="alert ${cls} alert-dismissible fade show" role="alert" style="border-radius:10px; border:none; font-size:0.8rem; padding:0.65rem 1rem;">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    }

    // ── Device validation ─────────────────────────────────────────
    const deviceId       = <?php echo json_encode($device_id); ?>;
    const deviceIdentity = <?php echo json_encode($device_identity); ?>;

    function validateDeviceStatus() {
        fetch('gs_DB/check_device_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({device_id: deviceId, device_identity: deviceIdentity})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const fd = new FormData();
                fd.append('mode', 'enable');
                fd.append('device_id', deviceId);
                fd.append('device_identity', deviceIdentity);
                return fetch('gs_DB/set_maintenance_mode.php', {method:'POST', body:fd});
            } else {
                showStatusAlert(`<strong>Error:</strong> ${data.message}`, 'danger');
                setTimeout(() => { window.location.href = 'GoSort_MaintenanceNavpage.php'; }, 3000);
                throw new Error(data.message);
            }
        })
        .then(r => r.json())
        .catch(err => console.error('Validation error:', err));
    }

    document.addEventListener('DOMContentLoaded', validateDeviceStatus);

    // ── Maintenance keep-alive ────────────────────────────────────
    (async function init() {
        await fetch('gs_DB/set_maintenance_mode.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'mode=enable'});
        await fetch('gs_DB/maintenance_control.php',  {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=maintenance_start'});
    })();

    const maintenanceInterval = setInterval(() => {
        fetch('gs_DB/maintenance_control.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=maintenance_keep'});
    }, 500);

    async function cleanupMaintenance() {
        clearInterval(maintenanceInterval);
        await fetch('gs_DB/end_maintenance.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}});
    }

    window.addEventListener('beforeunload', e => {
        e.preventDefault();
        e.returnValue = '';
        navigator.sendBeacon('gs_DB/end_maintenance.php');
    });

    // ── Connection status polling ─────────────────────────────────
    function updateConnectionStatus() {
        fetch('gs_DB/connection_status.php')
            .then(r => r.json())
            .then(data => {
                const s = document.querySelector('.connection-status');
                if (!s) return;
                const device = data.devices?.[0];
                if (device && device.status === 'online') {
                    s.className = 'connection-status alert alert-success';
                    s.innerHTML = '<i class="bi bi-check-circle-fill"></i> Device is connected and running';
                    if (!currentServoOperation) document.querySelectorAll('.btn-control, .btn-green').forEach(b => b.disabled = false);
                } else {
                    s.className = 'connection-status alert alert-danger';
                    s.innerHTML = '<i class="bi bi-x-circle-fill"></i> Device is not running';
                    document.querySelectorAll('.btn-control, .btn-green').forEach(b => b.disabled = true);
                }
            })
            .catch(() => {
                const s = document.querySelector('.connection-status');
                if (s) { s.className = 'connection-status alert alert-warning'; s.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i> Error checking connection'; }
            });
    }
    setInterval(updateConnectionStatus, 1000);
    updateConnectionStatus();

    // ── Servo operations ──────────────────────────────────────────
    let currentServoOperation = null;
    const OPERATION_TIMINGS = { zdeg:2000, ndeg:2000, odeg:2000, mdeg:2000, unclog:3500, sweep1:4000, sweep2:5000 };

    function moveServo(position) {
        if (currentServoOperation) return;
        const operationTime = OPERATION_TIMINGS[position] || 2000;
        const controller = new AbortController();
        currentServoOperation = controller;

        document.querySelectorAll('.btn-control, .btn-green').forEach(b => b.disabled = true);

        const fp = document.getElementById('floatingProgress');
        const names = { zdeg:'Moving to Bio', ndeg:'Moving to Non-Bio', odeg:'Moving to Hazardous', mdeg:'Moving to Mixed', unclog:'Unclogging Section', sweep1:'Testing Pan Sweep', sweep2:'Testing Full Sweep' };
        document.getElementById('progressTitle').textContent = names[position] || 'Operation in Progress';
        document.getElementById('progressTime').textContent = '0s';
        document.getElementById('progressStatus').textContent = 'Initializing…';
        document.getElementById('progressFill').style.width = '0%';
        fp.style.display = 'block';

        let prog = 0;
        const interval = setInterval(() => {
            prog += 2;
            document.getElementById('progressFill').style.width = Math.min(prog, 100) + '%';
            document.getElementById('progressTime').textContent = Math.floor((prog/100) * (operationTime/1000)) + 's';
            if (prog >= 100) { clearInterval(interval); document.getElementById('progressStatus').textContent = 'Waiting for completion…'; }
        }, operationTime / 50);

        fetch('gs_DB/save_maintenance_command.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({device_identity: deviceIdentity, command: position}),
            signal: controller.signal
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
        .then(() => pollCommandCompletion(position, controller))
        .catch(err => {
            if (err.name === 'AbortError') return;
            console.error(err);
            document.querySelectorAll('.btn-control, .btn-green').forEach(b => b.disabled = false);
            currentServoOperation = null;
        });
    }

    function pollCommandCompletion(command, controller) {
        let pollCount = 0;
        const maxPolls = 60;
        const fp = document.getElementById('floatingProgress');
        const fill = document.getElementById('progressFill');
        const status = document.getElementById('progressStatus');
        const time = document.getElementById('progressTime');

        const timer = setInterval(() => {
            pollCount++;
            if (controller.signal.aborted) { clearInterval(timer); fp.style.display = 'none'; return; }
            if (pollCount > maxPolls) {
                clearInterval(timer);
                status.textContent = 'Timeout — buttons re-enabled';
                fill.style.background = 'linear-gradient(90deg, #dc3545, #c82333)';
                document.querySelectorAll('.btn-control, .btn-green').forEach(b => b.disabled = false);
                currentServoOperation = null;
                setTimeout(() => { fp.style.display = 'none'; }, 3000);
                return;
            }
            fill.style.width = Math.min((pollCount / maxPolls) * 100, 100) + '%';
            time.textContent = pollCount + 's';
            status.textContent = `Polling… (${pollCount}/${maxPolls})`;

            fetch('gs_DB/check_maintenance_commands.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({device_identity: deviceIdentity}) })
                .then(r => r.json())
                .then(data => {
                    if (data.success && !data.command) {
                        clearInterval(timer);
                        status.textContent = 'Operation completed!';
                        fill.style.background = 'linear-gradient(90deg, #28a745, #20c997)';
                        fill.style.width = '100%';
                        document.querySelectorAll('.btn-control, .btn-green').forEach(b => b.disabled = false);
                        currentServoOperation = null;
                        setTimeout(() => { fp.style.display = 'none'; }, 2000);
                    }
                });
        }, 1000);
    }

    function confirmOperation(command, opName) {
        const modal = new bootstrap.Modal(document.getElementById('operationModal'));
        document.getElementById('operationName').textContent = opName;
        document.getElementById('confirmOperationBtn').onclick = () => { modal.hide(); moveServo(command); };
        modal.show();
    }

    document.getElementById('shutdownBtn').addEventListener('click', function() {
        if (currentServoOperation) { alert('Please wait for the current operation to complete.'); return; }
        new bootstrap.Modal(document.getElementById('shutdownModal')).show();
    });

    document.getElementById('confirmShutdownBtn').addEventListener('click', function() {
        bootstrap.Modal.getInstance(document.getElementById('shutdownModal')).hide();
        document.querySelectorAll('.btn-control, .btn-green').forEach(b => b.disabled = true);

        const fp = document.getElementById('floatingProgress');
        document.getElementById('progressTitle').textContent = 'Shutting Down Device';
        document.getElementById('progressTime').textContent = '0s';
        document.getElementById('progressStatus').textContent = 'Sending shutdown command…';
        document.getElementById('progressFill').style.width = '0%';
        document.getElementById('progressFill').style.background = 'linear-gradient(90deg, #dc3545, #c82333)';
        fp.style.display = 'block';

        fetch('gs_DB/save_maintenance_command.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({device_identity: deviceIdentity, command:'shutdown'}) })
            .then(r => r.json())
            .then(data => {
                const s = document.getElementById('progressStatus');
                const fill = document.getElementById('progressFill');
                if (data.success) {
                    s.textContent = 'Shutdown command sent!';
                    fill.style.background = 'linear-gradient(90deg, #28a745, #20c997)';
                    fill.style.width = '100%';
                } else {
                    s.textContent = 'Failed: ' + data.message;
                }
                setTimeout(() => { document.querySelectorAll('.btn-control, .btn-green').forEach(b => b.disabled = false); fp.style.display = 'none'; }, 3000);
            });
    });

    // ── Quadrant mapping ─────────────────────────────────────────
    let quadrantMap = { zdeg:'bio', ndeg:'nbio', odeg:'hazardous', mdeg:'mixed' };

    const TYPE_LABEL = { bio:'Bio', nbio:'Non-Bio', hazardous:'Hazardous', mixed:'Mixed' };
    const TYPE_CLASS = { bio:'bio', nbio:'nbio', hazardous:'hazardous', mixed:'mixed' };

    function updateQuadrantButtons() {
        ['zdeg','ndeg','odeg','mdeg'].forEach(q => {
            const btn = document.getElementById('btn-' + q);
            const pos = document.getElementById('pos-' + q);
            if (!btn) return;
            const type = quadrantMap[q];
            btn.textContent = TYPE_LABEL[type];
            btn.className = 'quadrant-btn ' + TYPE_CLASS[type];
            pos.className = 'servo-position ' + TYPE_CLASS[type];
        });
        updateMappingPreview();
        renderServoControls();
    }

    function updateMappingPreview() {
        document.getElementById('preview-back-left').textContent  = TYPE_LABEL[quadrantMap.zdeg] || '—';
        document.getElementById('preview-back-right').textContent = TYPE_LABEL[quadrantMap.odeg] || '—';
        document.getElementById('preview-front-left').textContent = TYPE_LABEL[quadrantMap.ndeg] || '—';
        document.getElementById('preview-front-right').textContent= TYPE_LABEL[quadrantMap.mdeg] || '—';
    }

    function renderServoControls() {
        const container = document.getElementById('dynamic-servo-controls');
        if (!container) return;
        let html = '';
        ['bio','nbio','hazardous','mixed'].forEach(type => {
            const servoKey = Object.keys(quadrantMap).find(k => quadrantMap[k] === type);
            if (servoKey) {
                const label = { bio:'Move to Bio', nbio:'Move to Non-Bio', hazardous:'Move to Hazardous', mixed:'Move to Mixed' }[type];
                html += `<div class="col-md-6"><button class="btn-control ${type}" data-servo="${servoKey}" onclick="confirmOperation('${servoKey}','${label}')">${label}</button></div>`;
            }
        });
        container.innerHTML = html;
    }

    function showQuadrantSelect(q) {
        const posLabels = { zdeg:'Back – Left', ndeg:'Front – Left', odeg:'Back – Right', mdeg:'Front – Right' };
        let html = `<div id="q-select-modal" class="modal" tabindex="-1" style="display:block; background:rgba(0,0,0,0.4);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius:16px; border:none; font-family:'Poppins',sans-serif;">
                    <div class="modal-body p-4">
                        <h5 style="font-size:0.9rem; font-weight:700; margin-bottom:1rem;">Select type for ${posLabels[q]}</h5>`;
        ['bio','nbio','hazardous','mixed'].forEach(opt => {
            const active = quadrantMap[q] === opt;
            html += `<button class="btn-control ${opt} mb-2" style="${active ? 'opacity:1;' : 'opacity:0.6;'}" onclick="setQuadrantType('${q}','${opt}')">${TYPE_LABEL[opt]}</button>`;
        });
        html += `<button class="btn-control dark mt-1" onclick="closeQuadrantSelect()">Cancel</button></div></div></div></div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    }

    function closeQuadrantSelect() {
        const m = document.getElementById('q-select-modal');
        if (m) m.remove();
    }

    function setQuadrantType(q, val) {
        quadrantMap[q] = val;
        updateQuadrantButtons();
        closeQuadrantSelect();
    }

    ['zdeg','ndeg','odeg','mdeg'].forEach(key => {
        const btn = document.getElementById('btn-' + key);
        if (btn) btn.addEventListener('click', () => showQuadrantSelect(key));
    });

    function saveQuadrantMapping() {
        if (currentServoOperation) { alert('Please wait for the current operation to complete.'); return; }
        const values = Object.values(quadrantMap);
        if (new Set(values).size < values.length) { alert('Each position must have a unique trash type.'); return; }

        const saveBtn = document.querySelector('button[onclick="saveQuadrantMapping()"]');
        if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…'; }

        fetch('gs_DB/save_sorter_mapping.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ device_identity: deviceIdentity, ...quadrantMap })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) showStatusAlert('Mapping saved successfully!', 'success');
            else showStatusAlert('Error: ' + (data.message || 'Unknown error'), 'danger');
        })
        .catch(err => showStatusAlert('Error saving mapping: ' + err, 'danger'))
        .finally(() => {
            if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="bi bi-save"></i> Save Mapping'; }
        });
    }

    // Load existing mapping on init
    window.addEventListener('DOMContentLoaded', function() {
        if (!deviceIdentity) { updateQuadrantButtons(); return; }
        fetch('gs_DB/save_sorter_mapping.php?device_identity=' + encodeURIComponent(deviceIdentity))
            .then(r => r.json())
            .then(data => { if (data.success && data.mapping) quadrantMap = data.mapping; })
            .catch(() => {})
            .finally(() => { updateQuadrantButtons(); renderServoControls(); });
    });

    updateQuadrantButtons();
    </script>
</body>
</html>