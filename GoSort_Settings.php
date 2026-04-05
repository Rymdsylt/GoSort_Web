<?php
session_start();
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

// Check common session key names for role
$session_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['userRole'] ?? 'admin';

// ── AJAX Handler ──────────────────────────────────────────────────────────────
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    ob_clean();
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            if (!isset($_SESSION['user_id'])) throw new Exception('Not authorized');
            $result = $conn->query("SELECT id, userName, lastName, email, role FROM users");
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $uid = (int)$row['id'];
                $dr = $conn->query("SELECT s.device_name, s.location FROM assigned_sorters a JOIN sorters s ON a.device_identity = s.device_identity WHERE a.user_id = $uid");
                $row['devices'] = [];
                if ($dr) { while ($d = $dr->fetch_assoc()) $row['devices'][] = $d; }
                $users[] = $row;
            }
            echo json_encode(['success' => true, 'users' => $users]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {

                if (($_POST['action'] ?? '') === 'update_profile') {
            $uid      = $_SESSION['user_id'];
            $userName = trim($_POST['userName'] ?? '');
            $lastName = trim($_POST['lastName'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            if (!$userName || !$lastName || !$email)
                throw new Exception('Name and email are required');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                throw new Exception('Invalid email format');
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->bind_param("si", $email, $uid); $chk->execute();
            if ($chk->get_result()->num_rows > 0) throw new Exception('Email already in use');
            if (!empty($password)) {
                $hp   = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET userName=?, lastName=?, email=?, password=? WHERE id=?");
                $stmt->bind_param("ssssi", $userName, $lastName, $email, $hp, $uid);
            } else {
                $stmt = $conn->prepare("UPDATE users SET userName=?, lastName=?, email=? WHERE id=?");
                $stmt->bind_param("sssi", $userName, $lastName, $email, $uid);
            }
            $stmt->execute();
            log_activity('general', 'Profile Updated', "User updated their own profile", $uid);
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            exit();
        }

            if (!isset($_SESSION['user_id'])) throw new Exception('Not authorized');
            $userName        = $_POST['userName'] ?? '';
            $lastName        = $_POST['lastName'] ?? '';
            $email           = $_POST['email'] ?? '';
            $password        = $_POST['password'] ?? '';
            $role            = $_POST['role'] ?? '';
            $assigned_sorters = $_POST['assigned_sorters'] ?? [];
            if (!$userName || !$lastName || !$email || !$password || !$role)
                throw new Exception('All required fields must be filled');
            // Role permission check
            if ($session_role === 'admin' && $role !== 'utility')
                throw new Exception('Admins can only create utility members');
            $conn->begin_transaction();
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (userName, lastName, email, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $userName, $lastName, $email, $hashedPassword, $role);
            $stmt->execute();
            $user_id = $conn->insert_id;
            if (!empty($assigned_sorters)) {
                $stmt2 = $conn->prepare("INSERT INTO assigned_sorters (user_id, device_identity) VALUES (?, ?)");
                foreach ($assigned_sorters as $sorter) {
                    $stmt2->bind_param("is", $user_id, $sorter);
                    $stmt2->execute();
                }
            }
            $conn->commit();
            log_user_added($_SESSION['user_id'], $userName . ' ' . $lastName);
            echo json_encode(['success' => true, 'message' => 'User added successfully', 'user_id' => $user_id]);
            exit();
        } catch (Exception $e) {
            if ($conn->connect_errno) $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        try {
            if (!isset($_SESSION['user_id'])) throw new Exception('Not authorized');
            parse_str(file_get_contents("php://input"), $_DELETE);
            $user_id_to_delete = $_DELETE['user_id'] ?? null;
            if (!$user_id_to_delete) throw new Exception('User ID is required');
            if ($user_id_to_delete == $_SESSION['user_id']) throw new Exception('You cannot delete your own account');
            // Check if target is superadmin — superadmin accounts are undeletable
            $chk = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $chk->bind_param("i", $user_id_to_delete); $chk->execute();
            $target = $chk->get_result()->fetch_assoc();
            if ($target && $target['role'] === 'superadmin') throw new Exception('Superadmin accounts cannot be deleted');
            $conn->begin_transaction();
            $stmt = $conn->prepare("SELECT userName, lastName FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id_to_delete); $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if (!$user) throw new Exception('User not found');
            $stmt = $conn->prepare("DELETE FROM assigned_sorters WHERE user_id = ?");
            $stmt->bind_param("i", $user_id_to_delete); $stmt->execute();
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id_to_delete); $stmt->execute();
            $conn->commit();
            log_user_deleted($_SESSION['user_id'], $user['userName'] . ' ' . $user['lastName']);
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            exit();
        } catch (Exception $e) {
            if ($conn->connect_errno) $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        try {
            if (!isset($_SESSION['user_id'])) throw new Exception('Not authorized');
            parse_str(file_get_contents("php://input"), $_PUT);
            $user_id_to_update  = $_PUT['user_id'] ?? null;
            $userName           = $_PUT['userName'] ?? '';
            $lastName           = $_PUT['lastName'] ?? '';
            $email              = $_PUT['email'] ?? '';
            $password           = $_PUT['password'] ?? '';
            $role               = $_PUT['role'] ?? '';
            $assigned_sorters   = isset($_PUT['assigned_sorters']) ? (array)$_PUT['assigned_sorters'] : [];
            // parse multi-value from raw body manually if needed
            if (empty($assigned_sorters)) {
                preg_match_all('/assigned_sorters\[\]=' . '([^&]*)/', file_get_contents("php://input"), $m);
                $assigned_sorters = array_map('urldecode', $m[1] ?? []);
            }
            if (!$user_id_to_update) throw new Exception('User ID is required');
            if ($user_id_to_update == $_SESSION['user_id']) throw new Exception('You cannot change your own role');
            if (!in_array($role, ['superadmin', 'admin', 'utility'])) throw new Exception('Invalid role');
            if ($session_role === 'admin' && $role !== 'utility') throw new Exception('Admins can only assign utility role');
            if (!$userName || !$lastName) throw new Exception('Full name is required');
            if (!$email) throw new Exception('Email is required');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email format');
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->bind_param("si", $email, $user_id_to_update); $chk->execute();
            if ($chk->get_result()->num_rows > 0) throw new Exception('Email already in use');
            $conn->begin_transaction();
            if (!empty($password)) {
                $hp = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET userName=?, lastName=?, email=?, password=?, role=? WHERE id=?");
                $stmt->bind_param("sssssi", $userName, $lastName, $email, $hp, $role, $user_id_to_update);
            } else {
                $stmt = $conn->prepare("UPDATE users SET userName=?, lastName=?, email=?, role=? WHERE id=?");
                $stmt->bind_param("ssssi", $userName, $lastName, $email, $role, $user_id_to_update);
            }
            $stmt->execute();
            // Re-assign sorters: delete old, insert new
            $del = $conn->prepare("DELETE FROM assigned_sorters WHERE user_id = ?");
            $del->bind_param("i", $user_id_to_update); $del->execute();
            if (!empty($assigned_sorters)) {
                $ins = $conn->prepare("INSERT INTO assigned_sorters (user_id, device_identity) VALUES (?, ?)");
                foreach ($assigned_sorters as $sorter) {
                    $ins->bind_param("is", $user_id_to_update, $sorter);
                    $ins->execute();
                }
            }
            $conn->commit();
            log_activity('general', 'User Updated', "Updated user: $userName $lastName", $_SESSION['user_id']);
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            exit();
        } catch (Exception $e) {
            if (isset($conn) && !$conn->connect_errno) $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }

    // AJAX: get assigned sorters for a user (edit modal pre-check)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_sorters') {
        try {
            if (!isset($_SESSION['user_id'])) throw new Exception('Not authorized');
            $uid = intval($_GET['user_id'] ?? 0);
            if (!$uid) throw new Exception('Invalid user ID');
            $stmt = $conn->prepare("SELECT device_identity FROM assigned_sorters WHERE user_id = ?");
            $stmt->bind_param("i", $uid); $stmt->execute();
            $res = $stmt->get_result();
            $sorters = [];
            while ($row = $res->fetch_assoc()) $sorters[] = $row['device_identity'];
            echo json_encode(['success' => true, 'sorters' => $sorters]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }
}

// Sorters for dropdown
$sorters = [];
$sr = $conn->query("SELECT device_identity, device_name, location FROM sorters");
if ($sr) { while ($row = $sr->fetch_assoc()) $sorters[] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Settings</title>
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

        .settings-tabs {
            display: flex;
            gap: 0.15rem;
            border-bottom: 2px solid #f3f4f6;
            margin-bottom: 1.25rem;
            overflow-x: auto;
            overflow-y: visible;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE/Edge */
        }
        .settings-tabs::-webkit-scrollbar { display: none; } /* Chrome/Safari */

        .tab-btn {
            padding: 0.6rem 1.1rem;
            border: none;
            background: transparent;
            color: var(--medium-gray);
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            border-radius: 8px 8px 0 0;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            white-space: nowrap;
        }
        .tab-btn:hover { color: var(--dark-gray); background: #f9fafb; }
        .tab-btn.active { color: var(--primary-green); }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--light-green), var(--primary-green));
            z-index: 2;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.22s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }

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
            flex-wrap: wrap;
            gap: 0.6rem;
        }
        .inner-card-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 0;
        }

        .search-wrap { position: relative; }
        .search-wrap input {
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            padding: 0.4rem 0.75rem 0.4rem 2rem;
            font-size: 0.8rem;
            font-family: 'Poppins', sans-serif;
            color: var(--dark-gray);
            width: 190px;
            outline: none;
            transition: border-color 0.2s;
        }
        .search-wrap input:focus { border-color: var(--mid-green); }
        .search-wrap .search-icon {
            position: absolute; left: 0.6rem; top: 50%;
            transform: translateY(-50%);
            color: var(--medium-gray); font-size: 0.75rem;
            pointer-events: none;
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

        .btn-outline-green {
            background: #fff;
            color: var(--primary-green);
            border: 1.5px solid var(--mid-green);
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
        }
        .btn-outline-green:hover { background: #f0fdf4; }

        #userTable {
            font-size: 0.8rem;
            font-family: 'Poppins', sans-serif;
            margin: 0;
        }
        #userTable thead th {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--medium-gray);
            border-bottom: 1px solid #f3f4f6;
            padding: 0.6rem 0.75rem;
            background: #fafafa;
        }
        #userTable tbody td {
            padding: 0.75rem 0.75rem;
            border-bottom: 1px solid #f9fafb;
            vertical-align: middle;
            color: var(--dark-gray);
        }
        #userTable tbody tr:hover td { background: #f9fafb; }
        #userTable tbody tr:last-child td { border-bottom: none; }

        .action-btn {
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 0.95rem;
            padding: 0.25rem 0.4rem;
            border-radius: 6px;
            transition: all 0.15s;
        }
        .action-btn.edit   { color: var(--primary-green); }
        .action-btn.edit:hover  { background: #e8f5e1; }
        .action-btn.delete { color: #dc2626; }
        .action-btn.delete:hover { background: #fee2e2; }

        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.18rem 0.55rem;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 600;
        }
        .role-badge.admin      { background: #e8f5e1; color: var(--primary-green); }
        .role-badge.utility    { background: #dbeafe; color: #1d4ed8; }
        .role-badge.superadmin { background: #fef3c7; color: #92400e; }

        /* ── Modal ── */
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

        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
            color: var(--dark-gray);
        }
        .form-control, .form-select {
            font-family: 'Poppins', sans-serif;
            font-size: 0.82rem;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            padding: 0.45rem 0.75rem;
            transition: border-color 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--mid-green);
            box-shadow: 0 0 0 3px rgba(54,129,55,0.1);
        }

        /* ── Sorter card grid ── */
        .sorter-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
            gap: 0.55rem;
            max-height: 200px;
            overflow-y: auto;
            padding: 0.15rem 0.1rem;
        }
        .sorter-card-grid::-webkit-scrollbar { width: 5px; }
        .sorter-card-grid::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .sorter-card-grid::-webkit-scrollbar-thumb { background: #c8e6c9; border-radius: 10px; }
        .sorter-card-grid::-webkit-scrollbar-thumb:hover { background: var(--mid-green); }

        .sorter-card { cursor: pointer; display: block; margin: 0; user-select: none; }
        .sorter-checkbox { display: none; }

        .sorter-card-inner {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.55rem 0.65rem;
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            background: #fff;
            transition: all 0.18s;
        }
        .sorter-card:hover .sorter-card-inner { border-color: var(--mid-green); background: #f6fdf5; }
        .sorter-checkbox:checked + .sorter-card-inner {
            border-color: var(--mid-green);
            background: linear-gradient(135deg, #f0fdf4, #e8f5e1);
            box-shadow: 0 0 0 3px rgba(54,129,55,0.1);
        }

        .sorter-icon-wrap {
            width: 28px; height: 28px;
            border-radius: 7px;
            background: #e8f5e1;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            font-size: 0.82rem;
            color: var(--primary-green);
            transition: background 0.18s;
        }
        .sorter-checkbox:checked + .sorter-card-inner .sorter-icon-wrap {
            background: var(--primary-green); color: #fff;
        }

        .sorter-info { flex: 1; min-width: 0; }
        .sorter-name {
            font-size: 0.73rem; font-weight: 700;
            color: var(--dark-gray);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sorter-location {
            font-size: 0.66rem; color: var(--medium-gray);
            display: flex; align-items: center; gap: 0.2rem;
            margin-top: 0.1rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sorter-location i { font-size: 0.58rem; flex-shrink: 0; }

        .sorter-check-indicator {
            width: 16px; height: 16px;
            border-radius: 50%;
            border: 1.5px solid var(--border-color);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.6rem; color: transparent;
            flex-shrink: 0; transition: all 0.18s;
        }
        .sorter-checkbox:checked + .sorter-card-inner .sorter-check-indicator {
            background: var(--mid-green); border-color: var(--mid-green); color: #fff;
        }

        /* ── Device chips in table ── */
        .devices-cell { display: flex; flex-wrap: wrap; gap: 0.3rem; }
        .device-chip {
            display: inline-flex; align-items: center; gap: 0.25rem;
            background: #f0fdf4; border: 1px solid #c8e6c9;
            border-radius: 20px; padding: 0.15rem 0.5rem;
            font-size: 0.7rem; font-weight: 600; color: var(--primary-green);
            white-space: nowrap;
        }
        .device-chip i { font-size: 0.6rem; }
        .device-chip .device-loc { font-weight: 400; color: var(--medium-gray); margin-left: 0.15rem; }

        /* ── Modal backdrop ── */
        .modal-backdrop { --bs-backdrop-opacity: 0.35; }

        /* ── Password field monospace ── */
        .pw-mono {
            font-family: 'Courier New', monospace !important;
            letter-spacing: 0.04em;
        }

        @media (max-width: 992px) {
            #main-content-wrapper { margin-left: 0; padding: 12px; }
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

                <!-- Tab Navigation -->
                <div class="settings-tabs">
                    <button class="tab-btn active" onclick="switchTab('profile', this)">
                        <i class="bi bi-person-circle"></i> Profile
                    </button>
                    <button class="tab-btn" onclick="switchTab('accounts', this)">
                        <i class="bi bi-people"></i> Accounts
                    </button>
                    <button class="tab-btn" onclick="switchTab('history', this)">
                        <i class="bi bi-clock-history"></i> Activity Logs
                    </button>
                    <button class="tab-btn" onclick="switchTab('about', this)">
                        <i class="bi bi-info-circle"></i> About GoSort
                    </button>
                    <button class="tab-btn" onclick="switchTab('support', this)">
                        <i class="bi bi-question-circle"></i> Help / Support
                    </button>
                </div>

                <!-- ── Tab: Profile ── -->
                <div id="profile" class="tab-content active">
                    <?php include 'settings_tabs/profiletab.php'; ?>
                </div>

                <!-- ── Tab: Accounts ── -->
                <div id="accounts" class="tab-content">
                    <div class="section-block">
                        <div class="inner-card">
                            <div class="inner-card-header">
                                <div class="inner-card-title">Manage User Accounts</div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <div class="search-wrap">
                                        <i class="bi bi-search search-icon"></i>
                                        <input type="text" id="searchInput" placeholder="Search user…">
                                    </div>
                                    <button class="btn-outline-green" onclick="exportToCSV()">
                                        <i class="bi bi-download"></i> Export CSV
                                    </button>
                                    <button class="btn-green" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                        <i class="bi bi-person-plus-fill"></i> Add User
                                    </button>
                                </div>
                            </div>

                            <div style="overflow-x:auto;min-height:350px;">
                                <table class="table table-hover align-middle mb-0" id="userTable">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Role</th>
                                            <th>Assigned Devices</th>
                                            <th style="width:100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="userTableBody">
                                        <tr>
                                            <td colspan="4" style="text-align:center;color:var(--medium-gray);font-size:0.8rem;padding:1.5rem;">
                                                Loading users…
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Tab: Activity Logs ── -->
                <div id="history" class="tab-content">
                    <?php include 'settings_tabs/historytab.php'; ?>
                </div>

                <!-- ── Tab: Help / Support ── -->
                <div id="about" class="tab-content">
                    <?php include 'settings_tabs/abouttab.php'; ?>
                </div>

                <div id="support" class="tab-content">
                    <?php include 'settings_tabs/supporttab.php'; ?>
                </div>

            </div><!-- /section-container -->
        </div>
    </div>

    <!-- ════ ADD USER MODAL ════ -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" role="dialog" aria-modal="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">
                        <i class="bi bi-person-plus-fill me-2" style="color:var(--primary-green);"></i>Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm" onsubmit="return false;">
                        <!-- Row 1: Name -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="userName" id="addFirstName" placeholder="e.g. Juan" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="lastName" id="addLastName" placeholder="e.g. dela Cruz" required>
                            </div>
                        </div>
                        <!-- Row 2: Email + Role -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" id="addEmail" placeholder="user@example.com" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" name="role" id="addRole" required>
                                    <option value="">Select Role</option>
                                </select>
                            </div>
                        </div>
                        <!-- Row 3: Password -->
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2 align-items-center">
                                <div class="input-group flex-grow-1">
                                    <input type="text" class="form-control pw-mono" name="password" id="addPassword"
                                        placeholder="Click Generate or type manually" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleAddPassword"
                                        style="border-radius:0 8px 8px 0;border:1.5px solid var(--border-color);border-left:none;">
                                        <i class="bi bi-eye-slash"></i>
                                    </button>
                                </div>
                                <button type="button" class="btn-green flex-shrink-0" onclick="generatePassword('addPassword', 'toggleAddPassword')" style="white-space:nowrap;">
                                    <i class="bi bi-arrow-repeat"></i> Generate
                                </button>
                                <button type="button" class="btn-outline-green flex-shrink-0" id="copyAddPassword" onclick="copyPassword('addPassword', 'copyAddPassword')" style="white-space:nowrap;">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                            </div>
                            <small class="d-block mt-1" style="font-size:0.71rem;color:var(--medium-gray);">
                                <i class="bi bi-info-circle me-1"></i>Share this password with the user. To change it later, the admin must reset it here.
                            </small>
                        </div>
                        <!-- Sorters -->
                        <div class="mb-1">
                            <label class="form-label">Assign Sorters</label>
                            <div id="addSorterCards" class="sorter-card-grid">
                                <?php if (empty($sorters)): ?>
                                    <div style="font-size:0.8rem;color:var(--medium-gray);padding:0.75rem;grid-column:1/-1;text-align:center;">No sorters available.</div>
                                <?php else: ?>
                                    <?php foreach ($sorters as $s): ?>
                                        <label class="sorter-card">
                                            <input type="checkbox" class="sorter-checkbox add-sorter-cb" value="<?php echo htmlspecialchars($s['device_identity']); ?>">
                                            <div class="sorter-card-inner">
                                                <div class="sorter-icon-wrap"><i class="bi bi-cpu-fill"></i></div>
                                                <div class="sorter-info">
                                                    <div class="sorter-name"><?php echo htmlspecialchars($s['device_name']); ?></div>
                                                    <div class="sorter-location"><i class="bi bi-geo-alt-fill"></i><?php echo htmlspecialchars($s['location']); ?></div>
                                                </div>
                                                <div class="sorter-check-indicator"><i class="bi bi-check-lg"></i></div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal" style="font-family:'Poppins',sans-serif;font-size:0.82rem;">Cancel</button>
                    <button class="btn-green" onclick="addUser()">
                        <i class="bi bi-person-check-fill"></i> Add User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ════ EDIT USER MODAL ════ -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square me-2" style="color:var(--primary-green);"></i>Edit User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm" onsubmit="return false;">
                        <!-- Row 1: Name -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editFirstName" placeholder="e.g. Juan" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editLastName" placeholder="e.g. dela Cruz" required>
                            </div>
                        </div>
                        <!-- Row 2: Email + Role -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="editEmail" placeholder="user@example.com" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="editRole" required>
                                    <option value="">Select Role</option>
                                </select>
                            </div>
                        </div>
                        <!-- Row 3: Reset Password -->
                        <div class="mb-3">
                            <label class="form-label">Reset Password <span style="font-weight:400;color:var(--medium-gray);">(optional)</span></label>
                            <div class="d-flex gap-2 align-items-center">
                                <div class="input-group flex-grow-1">
                                    <input type="text" class="form-control pw-mono" id="editPassword"
                                        placeholder="Leave blank to keep current password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleEditPassword"
                                        style="border-radius:0 8px 8px 0;border:1.5px solid var(--border-color);border-left:none;">
                                        <i class="bi bi-eye-slash"></i>
                                    </button>
                                </div>
                                <button type="button" class="btn-green flex-shrink-0" onclick="generatePassword('editPassword', 'toggleEditPassword')" style="white-space:nowrap;">
                                    <i class="bi bi-arrow-repeat"></i> Generate
                                </button>
                                <button type="button" class="btn-outline-green flex-shrink-0" id="copyEditPassword" onclick="copyPassword('editPassword', 'copyEditPassword')" style="white-space:nowrap;">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                            </div>
                            <small class="d-block mt-1" style="font-size:0.71rem;color:var(--medium-gray);">
                                <i class="bi bi-info-circle me-1"></i>Only fill this if you need to reset the user's password.
                            </small>
                        </div>
                        <!-- Sorters -->
                        <div class="mb-1">
                            <label class="form-label">Assign Sorters</label>
                            <div id="editSorterCards" class="sorter-card-grid">
                                <?php if (empty($sorters)): ?>
                                    <div style="font-size:0.8rem;color:var(--medium-gray);padding:0.75rem;grid-column:1/-1;text-align:center;">No sorters available.</div>
                                <?php else: ?>
                                    <?php foreach ($sorters as $s): ?>
                                        <label class="sorter-card">
                                            <input type="checkbox" class="sorter-checkbox edit-sorter-cb" value="<?php echo htmlspecialchars($s['device_identity']); ?>">
                                            <div class="sorter-card-inner">
                                                <div class="sorter-icon-wrap"><i class="bi bi-cpu-fill"></i></div>
                                                <div class="sorter-info">
                                                    <div class="sorter-name"><?php echo htmlspecialchars($s['device_name']); ?></div>
                                                    <div class="sorter-location"><i class="bi bi-geo-alt-fill"></i><?php echo htmlspecialchars($s['location']); ?></div>
                                                </div>
                                                <div class="sorter-check-indicator"><i class="bi bi-check-lg"></i></div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal" style="font-family:'Poppins',sans-serif;font-size:0.82rem;">Cancel</button>
                    <button class="btn-green" onclick="saveUserChanges()">
                        <i class="bi bi-check-lg"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ════ DELETE USER MODAL ════ -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body p-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-exclamation-circle-fill" style="color:#dc2626;font-size:1.3rem;"></i>
                        <h5 style="font-size:0.95rem;font-weight:700;margin:0;">Confirm Deletion</h5>
                    </div>
                    <p style="font-size:0.82rem;color:var(--medium-gray);margin-bottom:1rem;">
                        Are you sure you want to delete <strong id="deleteUserName" style="color:var(--dark-gray);"></strong>?
                        This action cannot be undone.
                    </p>
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn btn-light" data-bs-dismiss="modal"
                            style="font-family:'Poppins',sans-serif;font-size:0.82rem;">Cancel</button>
                        <button class="btn btn-danger" onclick="confirmDelete()"
                            style="font-family:'Poppins',sans-serif;font-size:0.82rem;border-radius:8px;">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
    // ── Session role from PHP ──
    const CURRENT_USER_ID = <?php echo json_encode((int)$_SESSION['user_id']); ?>;
    const SESSION_ROLE = <?php echo json_encode($session_role); ?>;
    console.log('[GoSort Debug] SESSION_ROLE raw =', SESSION_ROLE, '| normalized =', (SESSION_ROLE||'').trim().toLowerCase());

    // ── Populate role dropdowns based on session role ──
    function populateRoleDropdowns() {
        const normalizedRole = (SESSION_ROLE || '').trim().toLowerCase();
        const opts = normalizedRole === 'superadmin'
            ? [['admin','Administrator'], ['utility','Utility Member']]
            : [['utility','Utility Member']];
        ['addRole','editRole'].forEach(id => {
            const sel = document.getElementById(id);
            // keep blank placeholder, rebuild the rest
            while (sel.options.length > 1) sel.remove(1);
            opts.forEach(([val, label]) => {
                const o = new Option(label, val);
                sel.add(o);
            });
        });
    }

    // ── Tab switch ──
    function switchTab(tabId, btn) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        btn.classList.add('active');
        if (tabId === 'accounts') loadUsers();
    }

    // ── Toast ──
    function showToast(message, type = 'success') {
        const wrap = document.createElement('div');
        wrap.className = 'position-fixed bottom-0 end-0 p-3';
        wrap.style.zIndex = '1070';
        wrap.innerHTML = `<div class="toast align-items-center text-white bg-${type} border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body" style="font-family:'Poppins',sans-serif;font-size:0.82rem;">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div></div>`;
        document.body.appendChild(wrap);
        setTimeout(() => wrap.remove(), 4000);
    }

    function clearModalArtifacts() {
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }

    // ── Password generator ──
    function generatePassword(inputId, toggleId) {
        const charset = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
        let pw = '';
        for (let i = 0; i < 12; i++) pw += charset[Math.floor(Math.random() * charset.length)];
        const input = document.getElementById(inputId);
        input.value = pw;
        input.type = 'text';
        const icon = document.getElementById(toggleId)?.querySelector('i');
        if (icon) icon.className = 'bi bi-eye-slash';
    }

    // ── Copy password ──
    function copyPassword(inputId, btnId) {
        const val = document.getElementById(inputId).value;
        if (!val) { showToast('Nothing to copy — generate or enter a password first.', 'warning'); return; }
        navigator.clipboard.writeText(val).then(() => {
            const btn = document.getElementById(btnId);
            btn.innerHTML = '<i class="bi bi-clipboard-check"></i> Copied!';
            showToast('Password copied!', 'success');
            setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy'; }, 2500);
        }).catch(() => showToast('Copy failed. Please copy manually.', 'danger'));
    }

    // ── Password visibility toggles ──
    document.getElementById('toggleAddPassword').addEventListener('click', function () {
        const i = document.getElementById('addPassword');
        i.type = i.type === 'text' ? 'password' : 'text';
        this.querySelector('i').className = i.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
    document.getElementById('toggleEditPassword').addEventListener('click', function () {
        const i = document.getElementById('editPassword');
        i.type = i.type === 'text' ? 'password' : 'text';
        this.querySelector('i').className = i.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    // ── Reset add modal on open ──
    // Toggle sorter visibility based on role
    function toggleSorterVisibility(gridId, role) {
        const grid = document.getElementById(gridId);
        const label = grid.previousElementSibling;
        const show = role === 'utility' || role === '';
        grid.style.display = show ? '' : 'none';
        if (label) label.style.display = show ? '' : 'none';
    }

    document.getElementById('addRole').addEventListener('change', function() {
        toggleSorterVisibility('addSorterCards', this.value);
    });

    document.getElementById('editRole').addEventListener('change', function() {
        toggleSorterVisibility('editSorterCards', this.value);
    });

    document.getElementById('addUserModal').addEventListener('show.bs.modal', function () {
        document.getElementById('addUserForm').reset();
        document.querySelectorAll('.add-sorter-cb').forEach(cb => cb.checked = false);
        document.getElementById('addPassword').type = 'text';
        document.getElementById('copyAddPassword').innerHTML = '<i class="bi bi-clipboard"></i> Copy';
        document.getElementById('toggleAddPassword').querySelector('i').className = 'bi bi-eye-slash';
        toggleSorterVisibility('addSorterCards', '');
    });

    // ── Load users ──
    let currentUser = {};

    function loadUsers() {
        fetch(window.location.pathname, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            const tb = document.getElementById('userTableBody');
            if (!data.success || !data.users.length) {
                tb.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--medium-gray);font-size:0.8rem;padding:1.5rem;">No users found.</td></tr>';
                return;
            }
            tb.innerHTML = '';
            data.users.forEach(user => {
                const fullName  = `${user.userName} ${user.lastName}`;
                const roleMap   = { admin: ['Administrator','admin'], utility: ['Utility Member','utility'], superadmin: ['Super Admin','superadmin'] };
                const [roleLabel, roleClass] = roleMap[user.role] || [user.role, 'utility'];
                const esc       = s => (s||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
                // Build assigned devices chips
                const devices = user.devices || [];
                const devicesHtml = devices.length
                    ? devices.map(d => `<span class="device-chip"><i class="bi bi-cpu-fill"></i>${d.device_name} <span class="device-loc">${d.location}</span></span>`).join('')
                    : '<span style="color:var(--medium-gray);font-size:0.78rem;">—</span>';
                // Admin cannot edit superadmin rows
                const isSuperadminRow = user.role === 'superadmin';
                const canEdit = !(SESSION_ROLE === 'admin' && isSuperadminRow);
                const editBtn = canEdit
                    ? `<button class="action-btn edit" title="Edit" onclick="openEditModal('${esc(user.userName)}','${esc(user.lastName)}','${esc(user.email)}','${user.role}',${user.id})"><i class="bi bi-pencil-square"></i></button>`
                    : `<button class="action-btn edit" style="opacity:0.3;cursor:not-allowed;" title="Cannot edit superadmin"><i class="bi bi-pencil-square"></i></button>`;
                // Superadmin rows and own account are undeletable
                const canDelete = !(isSuperadminRow || user.id == CURRENT_USER_ID);
                const deleteBtn = canDelete
                    ? `<button class="action-btn delete" title="Delete" onclick="openDeleteModal('${esc(fullName)}',${user.id})"><i class="bi bi-trash"></i></button>`
                    : `<button class="action-btn delete" style="opacity:0.3;cursor:not-allowed;" title="Cannot delete this account"><i class="bi bi-trash"></i></button>`;
                tb.insertAdjacentHTML('beforeend', `
                    <tr>
                        <td style="font-weight:600;">${fullName}</td>
                        <td><span class="role-badge ${roleClass}">${roleLabel}</span></td>
                        <td><div class="devices-cell">${devicesHtml}</div></td>
                        <td>
                            ${editBtn}
                            ${deleteBtn}
                        </td>
                    </tr>`);
            });
        })
        .catch(err => console.error('Error loading users:', err));
    }

    document.addEventListener('DOMContentLoaded', () => { populateRoleDropdowns(); loadUsers(); });

    // ── Edit ──
    function openEditModal(firstName, lastName, email, role, userId) {
        document.getElementById('editFirstName').value = firstName;
        document.getElementById('editLastName').value  = lastName;
        document.getElementById('editEmail').value     = email;
        document.getElementById('editPassword').value  = '';
        document.getElementById('editPassword').type   = 'text';
        document.getElementById('copyEditPassword').innerHTML = '<i class="bi bi-clipboard"></i> Copy';
        document.getElementById('toggleEditPassword').querySelector('i').className = 'bi bi-eye-slash';
        // Superadmin editing own account: lock role field
        const roleRow = document.getElementById('editRole').closest('.row');
        const ownAccount = (SESSION_ROLE === 'superadmin' && userId == CURRENT_USER_ID);
        roleRow.style.display = ownAccount ? 'none' : '';
        if (!ownAccount) document.getElementById('editRole').value = role;
        // Show/hide sorters based on role
        toggleSorterVisibility('editSorterCards', role);
        // clear sorters first
        document.querySelectorAll('.edit-sorter-cb').forEach(cb => cb.checked = false);
        currentUser = { firstName, lastName, email, role, userId };
        // fetch assigned sorters for this user via same page AJAX
        fetch(`${window.location.pathname}?action=get_sorters&user_id=${userId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.sorters) {
                data.sorters.forEach(v => {
                    const cb = document.querySelector(`.edit-sorter-cb[value="${CSS.escape(v)}"]`);
                    if (cb) cb.checked = true;
                });
            }
        })
        .catch(() => {});
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    }

    function saveUserChanges() {
        if (!currentUser.userId) return;
        const userName = document.getElementById('editFirstName').value.trim();
        const lastName = document.getElementById('editLastName').value.trim();
        const email    = document.getElementById('editEmail').value.trim();
        const password = document.getElementById('editPassword').value;
        const role     = document.getElementById('editRole').value;
        const sorters  = [...document.querySelectorAll('.edit-sorter-cb:checked')].map(cb => cb.value);
        if (!userName || !lastName || !email || !role) {
            showToast('Please fill all required fields.', 'danger'); return;
        }
        let body = `user_id=${currentUser.userId}&userName=${encodeURIComponent(userName)}&lastName=${encodeURIComponent(lastName)}&email=${encodeURIComponent(email)}&role=${role}`;
        if (password) body += `&password=${encodeURIComponent(password)}`;
        sorters.forEach(s => body += `&assigned_sorters[]=${encodeURIComponent(s)}`);
        fetch(window.location.pathname, {
            method: 'PUT',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        })
        .then(r => r.json())
        .then(data => {
            bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
            setTimeout(() => { clearModalArtifacts(); loadUsers(); }, 200);
            showToast(data.success ? 'User updated successfully!' : (data.message || 'Error updating user.'), data.success ? 'success' : 'danger');
        })
        .catch(() => showToast('An error occurred.', 'danger'));
    }

    // ── Delete ──
    function openDeleteModal(name, userId) {
        document.getElementById('deleteUserName').textContent = name;
        currentUser = { name, userId };
        new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
    }

    function confirmDelete() {
        if (!currentUser.userId) return;
        fetch(window.location.pathname, {
            method: 'DELETE',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `user_id=${currentUser.userId}`
        })
        .then(r => r.json())
        .then(data => {
            bootstrap.Modal.getInstance(document.getElementById('deleteUserModal')).hide();
            setTimeout(() => { clearModalArtifacts(); loadUsers(); }, 200);
            showToast(data.success ? 'User deleted successfully!' : (data.message || 'Error deleting user.'), data.success ? 'success' : 'danger');
        })
        .catch(() => showToast('An error occurred.', 'danger'));
    }

    // ── Add ──
    function addUser() {
        const userName = document.getElementById('addFirstName').value.trim();
        const lastName = document.getElementById('addLastName').value.trim();
        const email    = document.getElementById('addEmail').value.trim();
        const password = document.getElementById('addPassword').value.trim();
        const role     = document.getElementById('addRole').value;
        if (!userName || !lastName || !email || !password || !role) {
            showToast('Please fill all required fields.', 'danger'); return;
        }
        const formData = new FormData();
        formData.append('userName', userName);
        formData.append('lastName', lastName);
        formData.append('email', email);
        formData.append('password', password);
        formData.append('role', role);
        document.querySelectorAll('.add-sorter-cb:checked').forEach(cb => formData.append('assigned_sorters[]', cb.value));
        fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
            setTimeout(() => { clearModalArtifacts(); loadUsers(); }, 200);
            showToast(data.success ? 'User added successfully!' : (data.message || 'Error adding user.'), data.success ? 'success' : 'danger');
        })
        .catch(() => showToast('An error occurred.', 'danger'));
    }

    // ── Search ──
    document.getElementById('searchInput').addEventListener('keyup', function () {
        const f = this.value.toLowerCase();
        document.querySelectorAll('#userTableBody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(f) ? '' : 'none';
        });
    });

    // ── Export CSV ──
    function exportToCSV() {
        const rows = document.querySelectorAll('#userTable tr');
        let csv = '';
        rows.forEach(row => {
            const cols = row.querySelectorAll('th, td');
            csv += Array.from(cols).map(c => `"${c.innerText.trim()}"`).join(',') + '\n';
        });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
        a.download = 'GoSort_user_accounts.csv';
        a.click();
    }
    </script>
</body>
</html>