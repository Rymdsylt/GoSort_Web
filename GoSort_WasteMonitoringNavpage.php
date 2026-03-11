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

$sort = $_GET['sort'] ?? 'recent';
$orderBy = match($sort) {
    'name'   => 'device_name ASC',
    'active' => 'last_active DESC',
    default  => 'created_at DESC'
};

$stmt = $pdo->query("SELECT id, device_name, device_identity, status, last_active, location, created_at FROM sorters ORDER BY {$orderBy}");
$allDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$onlineDevices  = array_values(array_filter($allDevices, fn($d) => $d['status'] === 'online'));
$offlineDevices = array_values(array_filter($allDevices, fn($d) => $d['status'] !== 'online'));

function getSortUrl($sortValue) {
    $p = $_GET;
    $p['sort'] = $sortValue;
    return '?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Waste Monitoring</title>
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

        .inner-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: var(--card-shadow);
        }

        /* ── page header ── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .sort-btn {
            background: #fff;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            padding: 0.45rem 0.85rem;
            font-size: 0.82rem;
            font-family: 'Poppins', sans-serif;
            color: var(--dark-gray);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: border-color 0.2s;
        }
        .sort-btn:hover { border-color: var(--mid-green); }

        .custom-drive-dropdown {
            border-radius: 12px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            border: 1px solid #efefef !important;
            padding: 6px !important;
            min-width: 170px !important;
        }
        .custom-drive-dropdown .dropdown-item {
            border-radius: 7px;
            padding: 7px 10px;
            font-size: 0.82rem;
            font-family: 'Poppins', sans-serif;
        }
        .custom-drive-dropdown .dropdown-item:hover { background: #f1f3f4; }
        .custom-drive-dropdown .dropdown-item.active-sort {
            background: #e8f5e1 !important;
            color: var(--primary-green) !important;
            font-weight: 600;
        }

        /* ── device card ── */
        .device-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.1rem 1.2rem;
            box-shadow: var(--card-shadow);
            transition: all 0.2s;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        .device-card:hover {
            box-shadow: 0 4px 14px rgba(0,0,0,0.09);
            transform: translateY(-2px);
        }
        .device-card::before {
            content: '';
            position: absolute;
            left: 0; top: 16px; bottom: 16px;
            width: 3px;
            border-radius: 0 3px 3px 0;
        }
        .device-card.status-online::before     { background: #15803d; }
        .device-card.status-offline::before    { background: #dc2626; }
        .device-card.status-maintenance::before { background: #1d4ed8; }

        .device-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.85rem;
        }

        .device-icon-wrap {
            width: 38px; height: 38px;
            background: #e8f5e1;
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            color: var(--primary-green);
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .device-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 0 0 0.25rem 0;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.68rem;
            font-weight: 600;
            padding: 0.18rem 0.55rem;
            border-radius: 20px;
        }
        .status-pill .dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
        .status-pill.online      { background: #dcfce7; color: #15803d; }
        .status-pill.offline     { background: #fee2e2; color: #dc2626; }
        .status-pill.maintenance { background: #dbeafe; color: #1d4ed8; }

        .device-meta {
            font-size: 0.74rem;
            color: var(--medium-gray);
            display: flex;
            flex-direction: column;
            gap: 0.22rem;
            margin-top: 0.5rem;
        }
        .device-meta-row { display: flex; align-items: center; gap: 0.3rem; }
        .device-meta-row i { font-size: 0.72rem; color: #9ca3af; }

        /* ── action buttons ── */
        .device-footer {
            display: flex;
            gap: 0.6rem;
            margin-top: 0.85rem;
            padding-top: 0.7rem;
            border-top: 1px solid #f3f4f6;
        }

        .btn-monitor {
            flex: 1;
            background: linear-gradient(135deg, var(--mid-green) 0%, var(--primary-green) 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.45rem 0.6rem;
            font-size: 0.76rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            transition: all 0.2s;
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(39,74,23,0.15);
        }
        .btn-monitor:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(39,74,23,0.25); color: #fff; }
        .btn-monitor.disabled {
            background: #d1d5db;
            box-shadow: none;
            cursor: not-allowed;
            pointer-events: none;
            color: #9ca3af;
        }

        .btn-logs {
            flex: 1;
            background: #fff;
            color: var(--primary-green);
            border: 1.5px solid var(--mid-green);
            border-radius: 8px;
            padding: 0.45rem 0.6rem;
            font-size: 0.76rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            transition: all 0.2s;
        }
        .btn-logs:hover { background: #f0fdf4; transform: translateY(-1px); }

        /* ── empty state ── */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--medium-gray);
        }
        .empty-state i { font-size: 2rem; display: block; margin-bottom: 0.5rem; color: #9ca3af; }
        .empty-state p { font-size: 0.85rem; margin: 0; }

        /* ── date picker modal ── */
        .modal-content {
            border-radius: 16px;
            border: none;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 8px 32px rgba(0,0,0,0.14);
        }
        .modal-header {
            border-bottom: 1px solid #f3f4f6;
            padding: 1.1rem 1.25rem;
        }
        .modal-title { font-size: 0.92rem; font-weight: 700; }
        .modal-body  { padding: 1.25rem; }
        .modal-footer { border-top: 1px solid #f3f4f6; padding: 0.9rem 1.25rem; }

        .date-device-name {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--medium-gray);
            margin-bottom: 0.75rem;
        }

        .date-input-wrap label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 0.3rem;
            display: block;
        }

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
        input[type="date"]:focus {
            border-color: var(--mid-green);
            box-shadow: 0 0 0 3px rgba(54,129,55,0.1);
        }

        .btn-green {
            background: linear-gradient(135deg, var(--mid-green) 0%, var(--primary-green) 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.4rem 0.9rem;
            font-size: 0.78rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(39,74,23,0.15);
        }
        .btn-green:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(39,74,23,0.25); color: #fff; }

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
                    <div></div>
                    <div class="dropdown">
                        <button class="sort-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-arrow-down-up" style="font-size:0.78rem;"></i>
                            <?php echo match($sort) { 'name' => 'Name (A–Z)', 'active' => 'Last Active', default => 'Most Recent' }; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end custom-drive-dropdown">
                            <li><a class="dropdown-item<?= $sort === 'recent' ? ' active-sort' : '' ?>" href="<?= getSortUrl('recent') ?>">Most Recent</a></li>
                            <li><a class="dropdown-item<?= $sort === 'name'   ? ' active-sort' : '' ?>" href="<?= getSortUrl('name') ?>">Name (A–Z)</a></li>
                            <li><a class="dropdown-item<?= $sort === 'active' ? ' active-sort' : '' ?>" href="<?= getSortUrl('active') ?>">Last Active</a></li>
                        </ul>
                    </div>
                </div>

                <?php $hasAny = !empty($onlineDevices) || !empty($offlineDevices); ?>

                <?php if (!$hasAny): ?>

                    <div class="empty-state">
                        <i class="bi bi-cpu"></i>
                        <p>No devices registered yet.</p>
                    </div>

                <?php else: ?>

                    <!-- ── Online Devices ── -->
                    <?php if (!empty($onlineDevices)): ?>
                    <div class="section-block">
                        <div class="section-label">
                            <i class="bi bi-circle-fill text-success me-1" style="font-size:0.55rem;vertical-align:middle;"></i>
                            Online Devices (<?= count($onlineDevices) ?>)
                        </div>
                        <div class="row g-3">
                            <?php foreach ($onlineDevices as $device): ?>
                                <div class="col-lg-4 col-md-6">
                                    <div class="device-card status-online">
                                        <div class="device-card-header">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="device-icon-wrap"><i class="bi bi-cpu-fill"></i></div>
                                                <div style="margin-left:0.6rem;">
                                                    <div class="device-name"><?= htmlspecialchars($device['device_name']) ?></div>
                                                    <span class="status-pill online"><span class="dot"></span>Online</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="device-meta">
                                            <div class="device-meta-row"><i class="bi bi-geo-alt-fill"></i><?= htmlspecialchars($device['location']) ?></div>
                                            <div class="device-meta-row"><i class="bi bi-clock"></i>Last active: <?= date('M j, Y g:i A', strtotime($device['last_active'])) ?></div>
                                        </div>
                                        <div class="device-footer">
                                            <a href="GoSort_LiveMonitor.php?device=<?= $device['id'] ?>&name=<?= urlencode($device['device_name']) ?>&identity=<?= urlencode($device['device_identity']) ?>"
                                               class="btn-monitor">
                                                <i class="bi bi-broadcast"></i> Monitor Live
                                            </a>
                                            <button class="btn-logs"
                                                onclick="openDatePicker('<?= $device['id'] ?>','<?= htmlspecialchars(addslashes($device['device_name'])) ?>','<?= urlencode($device['device_identity']) ?>')">
                                                <i class="bi bi-clock-history"></i> Review Logs
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ── Offline Devices ── -->
                    <?php if (!empty($offlineDevices)): ?>
                    <div class="section-block">
                        <div class="section-label">
                            <i class="bi bi-circle-fill text-danger me-1" style="font-size:0.55rem;vertical-align:middle;"></i>
                            Offline Devices (<?= count($offlineDevices) ?>)
                        </div>
                        <div class="row g-3">
                            <?php foreach ($offlineDevices as $device): ?>
                                <div class="col-lg-4 col-md-6">
                                    <div class="device-card status-offline">
                                        <div class="device-card-header">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="device-icon-wrap" style="background:#fee2e2;color:#dc2626;"><i class="bi bi-cpu-fill"></i></div>
                                                <div style="margin-left:0.6rem;">
                                                    <div class="device-name"><?= htmlspecialchars($device['device_name']) ?></div>
                                                    <span class="status-pill offline"><span class="dot"></span>Offline</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="device-meta">
                                            <div class="device-meta-row"><i class="bi bi-geo-alt-fill"></i><?= htmlspecialchars($device['location']) ?></div>
                                            <div class="device-meta-row"><i class="bi bi-clock"></i>Last active: <?= date('M j, Y g:i A', strtotime($device['last_active'])) ?></div>
                                        </div>
                                        <div class="device-footer">
                                            <div class="btn-monitor disabled">
                                                <i class="bi bi-broadcast"></i> Monitor Live
                                            </div>
                                            <button class="btn-logs"
                                                onclick="openDatePicker('<?= $device['id'] ?>','<?= htmlspecialchars(addslashes($device['device_name'])) ?>','<?= urlencode($device['device_identity']) ?>')">
                                                <i class="bi bi-clock-history"></i> Review Logs
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>

            </div><!-- /section-container -->
        </div>
    </div>

    <!-- ── Date Picker Modal ── -->
    <div class="modal fade" id="datePickerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:360px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar3 me-2" style="color:var(--primary-green);"></i>Select Date to Review
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="date-device-name" id="modalDeviceName"></div>
                    <div class="date-input-wrap">
                        <label for="reviewDate">Date</label>
                        <input type="date" id="reviewDate" max="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal" style="font-family:'Poppins',sans-serif;font-size:0.82rem;border-radius:8px;">Cancel</button>
                    <button class="btn-green" onclick="goToReviewLogs()">
                        <i class="bi bi-arrow-right"></i> View Logs
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        let _reviewDeviceId       = '';
        let _reviewDeviceIdentity = '';

        function openDatePicker(deviceId, deviceName, deviceIdentity) {
            _reviewDeviceId       = deviceId;
            _reviewDeviceIdentity = deviceIdentity;
            document.getElementById('modalDeviceName').textContent = 'Device: ' + deviceName;
            document.getElementById('reviewDate').value = new Date().toISOString().split('T')[0];
            new bootstrap.Modal(document.getElementById('datePickerModal')).show();
        }

        function goToReviewLogs() {
            const date = document.getElementById('reviewDate').value;
            if (!date) { alert('Please select a date.'); return; }
            const url = `GoSort_ReviewLogs.php?device=${_reviewDeviceId}&identity=${_reviewDeviceIdentity}&date=${encodeURIComponent(date)}`;
            window.location.href = url;
        }

        // Live status polling every 5s
        setInterval(() => {
            fetch('gs_DB/connection_status.php')
                .then(r => r.ok ? r.json() : null)
                .then(data => {
                    if (!data?.devices) return;
                    data.devices.forEach(dev => {
                        document.querySelectorAll('[data-device-id]').forEach(card => {
                            // optional: wire up if you add data-device-id attrs
                        });
                    });
                })
                .catch(() => {});
        }, 5000);
    </script>
</body>
</html>