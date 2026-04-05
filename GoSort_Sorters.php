<?php
session_start();
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';
require_once 'gs_DB/sorters_DB.php';
require_once 'gs_DB/activity_logs.php';

// Handle logout
if (isset($_GET['logout'])) {
    if (isset($_SESSION['user_id'])) {
        log_logout($_SESSION['user_id']);
    }
    require_once 'gs_DB/maintenance_tracking.php';
    if (isset($_SESSION['user_id'])) {
        endMaintenanceMode($_SESSION['user_id']);
    }
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

switch ($sort) {
    case 'name':   $orderBy = "device_name ASC"; break;
    case 'active': $orderBy = "last_active DESC"; break;
    case 'recent': default: $orderBy = "id DESC"; break;
}

$stmt = $pdo->query("SELECT * FROM sorters ORDER BY $orderBy");
$sorters = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentSortLabel = match($sort) {
    'name'   => 'Name (A-Z)',
    'active' => 'Last Active',
    default  => 'Most Recent',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Devices</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-green:  #274a17;
            --light-green:    #7AF146;
            --mid-green:      #368137;
            --dark-gray:      #1f2937;
            --medium-gray:    #6b7280;
            --border-color:   #e5e7eb;
            --card-shadow:    0 1px 3px rgba(0,0,0,0.07);
        }

        body {
            background-color: #e8f1e6;
            font-family: 'Poppins', sans-serif !important;
            color: var(--dark-gray);
        }

        #main-content-wrapper {
            margin-left: 240px;
            padding: 100px 0px 20px 0px;
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
        .page-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 0;
        }
        .page-header-right {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        /* ── Search ── */
        .search-wrap { position: relative; }
        .search-wrap input {
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            padding: 0.45rem 0.75rem 0.45rem 2.1rem;
            font-size: 0.82rem;
            font-family: 'Poppins', sans-serif;
            color: var(--dark-gray);
            width: 200px;
            outline: none;
            transition: border-color 0.2s;
        }
        .search-wrap input:focus { border-color: var(--mid-green); }
        .search-wrap .search-icon {
            position: absolute;
            left: 0.65rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--medium-gray);
            font-size: 0.8rem;
            pointer-events: none;
        }

        /* ── Sort dropdown ── */
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
        .sort-btn .sort-icon { font-size: 0.78rem; margin: 0; }

        .custom-drive-dropdown {
            border-radius: 12px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            border: 1px solid #efefef !important;
            padding: 6px !important;
            min-width: 170px !important;
            background: #fff;
        }
        .custom-drive-dropdown .dropdown-item {
            border-radius: 7px;
            padding: 7px 10px;
            font-size: 0.82rem;
            font-family: 'Poppins', sans-serif;
            color: var(--dark-gray);
        }
        .custom-drive-dropdown .dropdown-item:hover { background: #f1f3f4; }
        .custom-drive-dropdown .dropdown-item.text-danger { color: #d93025 !important; }
        .custom-drive-dropdown .dropdown-item.text-danger:hover { background: #fce8e6; color: #a50e0e !important; }
        .custom-drive-dropdown .dropdown-item.active-sort { background: #e8f5e1 !important; color: var(--primary-green) !important; font-weight: 600; }
        .dropdown-item:active { background-color: inherit !important; color: inherit !important; }

        /* ── Add Device button ── */
        .btn-add-device {
            background: linear-gradient(135deg, rgb(236,251,234) 0%, #84ca92 100%);
            border: none;
            border-radius: 8px;
            padding: 0.47rem 1rem;
            font-size: 0.82rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            color: var(--primary-green);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(39,74,23,0.12);
        }
        .btn-add-device:hover {
            background: linear-gradient(135deg, #84ca92 0%, #58C542 100%);
            color: #fff;
            box-shadow: 0 4px 10px rgba(39,74,23,0.2);
            transform: translateY(-1px);
        }
        .btn-add-device .btn-add-icon { font-size: 0.95rem; margin: 0; }

        /* ── Device card ── */
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
            flex-shrink: 0;
        }
        .device-icon-wrap img { width: 20px; height: 20px; }

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
        .status-pill .dot {
            width: 5px; height: 5px;
            border-radius: 50%;
            background: currentColor;
        }
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
        .device-meta-row i { font-size: 0.72rem; color: #9ca3af; margin: 0; }

        .device-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.85rem;
            padding-top: 0.7rem;
            border-top: 1px solid #f3f4f6;
        }
        .analytics-link {
            font-size: 0.76rem;
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            transition: opacity 0.15s;
        }
        .analytics-link:hover { opacity: 0.7; color: var(--primary-green); }
        .analytics-link i { margin: 0; font-size: 0.76rem; }
        .device-id-label {
            font-size: 0.7rem;
            color: #9ca3af;
        }

        /* kebab */
        .kebab-btn {
            background: none;
            border: none;
            padding: 0.2rem 0.35rem;
            color: var(--medium-gray);
            border-radius: 6px;
            transition: background 0.15s;
            line-height: 1;
            cursor: pointer;
        }
        .kebab-btn:hover { background: #f3f4f6; color: var(--dark-gray); }
        .kebab-btn i { margin: 0; font-size: 1rem; }

        /* scroll wrapper */
       #deviceWrapper {
            min-height: 400px;
            overflow: visible;
            padding-right: 4px;
        }

        #deviceWrapper::-webkit-scrollbar { width: 5px; }
        #deviceWrapper::-webkit-scrollbar-track { background: transparent; }
        #deviceWrapper::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }

        /* ── Modals ── */
        #addDeviceModal .modal-content,
        #statusModal .modal-content {
            border-radius: 16px;
            border: 2px solid var(--mid-green);
            box-shadow: 0 8px 24px rgba(0,0,0,0.13);
            overflow: hidden;
        }
        #addDeviceModal .modal-header,
        #statusModal .modal-header {
            background: linear-gradient(135deg, rgb(236,251,234) 0%, #84ca92 100%);
            border-bottom: none;
            padding: 1.25rem 1.5rem;
        }
        #addDeviceModal .modal-title,
        #statusModal .modal-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--primary-green);
        }
        #addDeviceModal .btn-close,
        #statusModal .btn-close { opacity: 0.5; }
        #addDeviceModal .modal-body,
        #statusModal .modal-body { padding: 1.5rem; background: #fff; }
        #addDeviceModal .form-label {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--primary-green);
            font-size: 0.8rem;
            margin-bottom: 0.4rem;
        }
        #addDeviceModal .form-control {
            font-family: 'Poppins', sans-serif;
            border: 1.5px solid var(--border-color);
            border-radius: 9px;
            padding: 0.6rem 0.9rem;
            font-size: 0.86rem;
            transition: border-color 0.2s;
        }
        #addDeviceModal .form-control:focus { border-color: var(--mid-green); box-shadow: 0 0 0 0.18rem rgba(54,129,55,0.13); }
        #addDeviceModal .form-control.is-invalid { border-color: #dc3545; }
        #addDeviceModal .form-control.is-valid   { border-color: var(--mid-green); }
        #addDeviceModal .modal-footer,
        #statusModal .modal-footer { background: #f8f9fa; border-top: 1px solid var(--border-color); padding: 1rem 1.5rem; }
        #addDeviceModal .btn, #statusModal .btn {
            font-family: 'Poppins', sans-serif; font-weight: 600;
            border-radius: 9px; padding: 0.52rem 1.2rem; font-size: 0.83rem; border: none; transition: all 0.2s;
        }
        #addDeviceModal .btn-secondary, #statusModal .btn-secondary { background: #9d9d9d; color: #fff; }
        #addDeviceModal .btn-secondary:hover, #statusModal .btn-secondary:hover { background: #6d6d6d; }
        #addDeviceModal .btn-primary {
            background: linear-gradient(135deg, var(--mid-green) 0%, var(--primary-green) 100%); color: #fff;
        }
        #addDeviceModal .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(39,74,23,0.2); }

        #statusModal #statusMessage { font-family: 'Poppins', sans-serif; font-size: 0.9rem; line-height: 1.6; }
        #statusModal #statusMessage.text-success { color: #15803d !important; font-weight: 600; }
        #statusModal #statusMessage.text-danger  { color: #dc2626 !important; font-weight: 600; }
        #statusModal .progress { height: 7px; border-radius: 10px; background: #e9ecef; }
        #statusModal .progress-bar { background: linear-gradient(90deg, var(--mid-green) 0%, var(--primary-green) 100%); }

        #deleteModal .modal-content {
            border-radius: 16px; border: 2px solid #dc3545;
            box-shadow: 0 8px 24px rgba(220,53,69,0.18); overflow: hidden;
        }
        #deleteModal .modal-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white; border-bottom: none; padding: 1.25rem 1.5rem;
        }
        #deleteModal .modal-title { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.05rem; }
        #deleteModal .btn-close { filter: brightness(0) invert(1); opacity: 0.8; }
        #deleteModal .modal-body { padding: 1.5rem; background: #fff; }
        #deleteModal .modal-body p { font-family: 'Poppins', sans-serif; font-size: 0.88rem; line-height: 1.6; color: #333; margin-bottom: 0.75rem; }
        #deleteModal .modal-body .text-danger {
            background: #fff5f5; padding: 0.65rem 0.9rem;
            border-radius: 8px; border-left: 4px solid #dc3545; color: #dc3545 !important;
        }
        #deleteModal #deleteDeviceName { font-weight: 700; color: #dc3545; }
        #deleteModal .modal-footer { background: #f8f9fa; border-top: 1px solid var(--border-color); padding: 1rem 1.5rem; }
        #deleteModal .btn {
            font-family: 'Poppins', sans-serif; font-weight: 600;
            border-radius: 9px; padding: 0.52rem 1.2rem; font-size: 0.83rem; border: none; transition: all 0.2s;
        }
        #deleteModal .btn-secondary { background: #6c757d; color: white; }
        #deleteModal .btn-secondary:hover { background: #5a6268; transform: translateY(-1px); }
        #deleteModal .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white; box-shadow: 0 3px 8px rgba(220,53,69,0.3);
        }
        #deleteModal .btn-danger:hover { transform: translateY(-1px); box-shadow: 0 5px 12px rgba(220,53,69,0.4); }

        @media (max-width: 992px) {
            #main-content-wrapper { margin-left: 0; padding: 12px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .search-wrap input { width: 100%; }
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
                    <div class="page-header-right">
                        <div class="search-wrap">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" id="searchDevice" placeholder="Search device…">
                        </div>

                        <div class="dropdown">
                            <button class="sort-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-arrow-down-up sort-icon"></i>
                                <?php echo $currentSortLabel; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end custom-drive-dropdown">
                                <li><a class="dropdown-item<?= $sort === 'recent' ? ' active-sort' : '' ?>" href="?sort=recent">Most Recent</a></li>
                                <li><a class="dropdown-item<?= $sort === 'name'   ? ' active-sort' : '' ?>" href="?sort=name">Name (A–Z)</a></li>
                                <li><a class="dropdown-item<?= $sort === 'active' ? ' active-sort' : '' ?>" href="?sort=active">Last Active</a></li>
                            </ul>
                        </div>

                        <button class="btn-add-device" data-bs-toggle="modal" data-bs-target="#addDeviceModal" id="openAddDevice">
                            <i class="bi bi-plus-lg btn-add-icon"></i> Add Device
                        </button>
                    </div>
                </div>

                <!-- Devices section -->
                <div class="section-block">
                    <div class="section-label">All Devices (<?php echo count($sorters); ?>)</div>

                    <div id="deviceWrapper">
                        <div id="gridContainer" class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">

                            <?php foreach ($sorters as $sorter): ?>
                                <div class="col" data-device="<?php echo htmlspecialchars($sorter['device_name']); ?>">
                                    <div class="device-card status-<?php echo htmlspecialchars($sorter['status']); ?>">

                                        <div class="device-card-header">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="device-icon-wrap">
                                                    <img src="images/icons/devices.svg" alt="Device">
                                                </div>
                                                <div>
                                                    <div class="device-name"><?php echo htmlspecialchars($sorter['device_name']); ?></div>
                                                    <div class="status-pill <?php echo htmlspecialchars($sorter['status']); ?>">
                                                        <span class="dot"></span>
                                                        <?php echo ucfirst($sorter['status']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="dropdown">
                                                <button class="kebab-btn" type="button"
                                                    data-bs-toggle="dropdown"
                                                    id="dropdownMenu<?php echo $sorter['id']; ?>">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end custom-drive-dropdown"
                                                    aria-labelledby="dropdownMenu<?php echo $sorter['id']; ?>">
                                                    <li>
                                                        <button class="dropdown-item text-danger delete-btn"
                                                            onclick="confirmDelete('<?php echo $sorter['id']; ?>', '<?php echo htmlspecialchars($sorter['device_name']); ?>')">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                        <div class="device-meta">
                                            <div class="device-meta-row">
                                                <i class="bi bi-geo-alt-fill"></i>
                                                <?php echo htmlspecialchars($sorter['location']); ?>
                                            </div>
                                            <div class="device-meta-row">
                                                <i class="bi bi-clock"></i>
                                                <?php echo date('M j, Y', strtotime($sorter['last_active'])); ?>
                                            </div>
                                        </div>

                                        <div class="device-footer">
                                            <a class="analytics-link"
                                               href="GoSort_Statistics.php?device=<?php echo $sorter['id']; ?>&identity=<?php echo urlencode($sorter['device_identity']); ?>">
                                                <i class="bi bi-graph-up"></i> View Analytics
                                            </a>
                                            <span class="device-id-label">ID: <?php echo htmlspecialchars($sorter['device_identity']); ?></span>
                                        </div>

                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($sorters) === 0): ?>
                                <div class="py-5 text-center" style="color:var(--medium-gray); width:100%;">
                                    <i class="bi bi-cpu" style="font-size:2.5rem; display:block; margin-bottom:0.5rem; color:#9ca3af;"></i>
                                    <p style="font-size:0.88rem; margin:0;">No devices registered yet.</p>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

            </div><!-- /section-container -->
        </div>
    </div>

    <!-- Status Modal -->
    <div id="statusModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Device Registration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="statusMessage"></div>
                    <div class="progress mt-3 d-none">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Device Modal -->
    <div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDeviceModalLabel">Add New Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addDeviceForm" novalidate>
                        <div class="mb-3">
                            <label for="deviceName" class="form-label">Device Name</label>
                            <input type="text" class="form-control" id="deviceName" name="deviceName">
                            <div class="invalid-feedback">Please enter a device name.</div>
                        </div>
                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <select class="form-control" id="location" name="location">
                                <option value="" disabled selected>Select a floor…</option>
                                <option value="1st Floor">1st Floor</option>
                                <option value="2nd Floor">2nd Floor</option>
                                <option value="3rd Floor">3rd Floor</option>
                                <option value="4th Floor">4th Floor</option>
                                <option value="5th Floor">5th Floor</option>
                            </select>
                            <div class="invalid-feedback">Please select the device location.</div>
                        </div>
                        <div class="mb-3">
                            <label for="deviceIdentity" class="form-label">Device Identity</label>
                            <input type="text" class="form-control" id="deviceIdentity" name="deviceIdentity">
                            <div class="invalid-feedback">Please enter the device identity.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addDeviceForm" class="btn btn-primary">Add Device</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong><span id="deleteDeviceName"></span></strong>?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Device</button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
    const statusModal   = new bootstrap.Modal(document.getElementById('statusModal'));
    const statusMessage = document.getElementById('statusMessage');
    const progressBar   = document.querySelector('#statusModal .progress');

    let reopenAddModalAfterStatus = false;

    document.getElementById('statusModal').addEventListener('hidden.bs.modal', function () {
        if (reopenAddModalAfterStatus) {
            reopenAddModalAfterStatus = false;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('addDeviceModal')).show();
        }
    });

    document.getElementById('openAddDevice').addEventListener('click', function () {
        const form = document.getElementById('addDeviceForm');
        if (form) form.reset();
    });

    function showStatus(message, isError = false, showProgress = false) {
        statusMessage.innerHTML = message;
        statusMessage.className = isError ? 'text-danger' : 'text-success';
        progressBar.className = showProgress ? 'progress mt-3' : 'progress mt-3 d-none';
        if (!statusModal._isShown) statusModal.show();
    }

    document.querySelectorAll('#addDeviceForm .form-control').forEach(function(input) {
        input.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
            }
        });
    });

    document.getElementById('addDeviceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        let isValid = true;
        form.querySelectorAll('.form-control').forEach(function(input) {
            if (input.value.trim() === '') {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
            }
        });
        if (!isValid) return;

        const formData = new FormData(form);
        const deviceIdentity = formData.get('deviceIdentity');
        showStatus(`Checking if device "${deviceIdentity}" is attempting to connect…`, false, true);

        const jsonData = {
            deviceName:     formData.get('deviceName'),
            location:       formData.get('location'),
            deviceIdentity: formData.get('deviceIdentity')
        };

        fetch('gs_DB/add_device_new.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(jsonData)
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    try { const j = JSON.parse(text); throw new Error(j.message || 'Server error'); }
                    catch (e) { throw new Error('Server returned: ' + text); }
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const addModal = bootstrap.Modal.getInstance(document.getElementById('addDeviceModal'));
                if (addModal) addModal.hide();
                showStatus(`✅ ${data.message}`, false, false);
                setTimeout(() => { location.reload(); }, 2000);
            } else {
                const addModal = bootstrap.Modal.getInstance(document.getElementById('addDeviceModal'));
                if (addModal) addModal.hide();
                reopenAddModalAfterStatus = true;
                showStatus(`❌ ${data.message}`, true, false);
            }
        })
        .catch(error => {
            const addModal = bootstrap.Modal.getInstance(document.getElementById('addDeviceModal'));
            if (addModal) addModal.hide();
            reopenAddModalAfterStatus = true;
            showStatus(`❌ Server error: ${error.message}. Please try again.`, true, false);
        });
    });

    document.getElementById('deviceIdentity').addEventListener('input', function(e) {
        document.getElementById('deviceName').value = 'GS-' + e.target.value;
    });

    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    let deviceToDelete = null;

    function confirmDelete(deviceId, deviceName) {
        deviceToDelete = deviceId;
        document.getElementById('deleteDeviceName').textContent = deviceName;
        deleteModal.show();
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (!deviceToDelete) return;
        fetch('gs_DB/delete_device.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ deviceId: deviceToDelete })
        })
        .then(r => r.json())
        .then(data => {
            deleteModal.hide();
            if (data.success) {
                showStatus('✅ ' + data.message, false, false);
                setTimeout(() => { location.reload(); }, 2000);
            } else {
                showStatus('❌ ' + data.message, true, false);
            }
        })
        .catch(() => {
            deleteModal.hide();
            showStatus('❌ Error deleting device. Please try again.', true, false);
        });
    });

    function shutdownDevice(deviceIdentity, deviceName) {
        if (!confirm(`Shut down "${deviceName}"? This will force the device to power off.`)) return;
        showStatus(`Sending shutdown command to "${deviceName}"…`, false, true);
        fetch('gs_DB/save_maintenance_command.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ device_identity: deviceIdentity, command: 'shutdown' })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) showStatus(`✅ Shutdown command sent to "${deviceName}".`, false, false);
            else showStatus(`❌ Failed: ${data.message}`, true, false);
        })
        .catch(error => { showStatus(`❌ Error: ${error.message}`, true, false); });
    }

    // Live status polling every 3s
    setInterval(() => {
        fetch('gs_DB/connection_status.php')
            .then(r => { if (!r.ok) throw new Error(); return r.json(); })
            .then(data => {
                if (!data.success || !data.devices) return;
                data.devices.forEach(device => {
                    const col = document.querySelector(`[data-device="${device.device_name}"]`);
                    if (!col) return;
                    const card = col.querySelector('.device-card');
                    const pill = col.querySelector('.status-pill');
                    if (card) card.className = card.className.replace(/status-\w+/, `status-${device.status}`);
                    if (pill) {
                        pill.className = `status-pill ${device.status}`;
                        pill.innerHTML = `<span class="dot"></span>${device.status.charAt(0).toUpperCase() + device.status.slice(1)}`;
                    }
                });
            })
            .catch(() => {});
    }, 3000);

    // Search
    document.getElementById('searchDevice').addEventListener('keyup', function() {
        const val = this.value.toLowerCase().trim();
        document.querySelectorAll('[data-device]').forEach(col => {
            col.style.display = col.getAttribute('data-device').toLowerCase().includes(val) ? '' : 'none';
        });
    });
    </script>
</body>
</html>