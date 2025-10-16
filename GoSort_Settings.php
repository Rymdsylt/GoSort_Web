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

// Accounts Tab AJAX Handler
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Prevent any output
    ob_clean();
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Check if user is logged in and is admin
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('Not authorized');
            }

            // Get POST data
            $userName = $_POST['userName'] ?? '';
            $lastName = $_POST['lastName'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';
            $assigned_floor = $_POST['assigned_floor'] ?? '';
            $assigned_sorters = $_POST['assigned_sorters'] ?? [];

            // Validate required fields
            if (!$userName || !$lastName || !$email || !$password || !$role) {
                throw new Exception('All required fields must be filled');
            }

            // Start transaction
            $conn->begin_transaction();

            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (userName, lastName, email, password, role, assigned_floor) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $userName, $lastName, $email, $hashedPassword, $role, $assigned_floor);
            $stmt->execute();
            
            $user_id = $conn->insert_id;

            // Insert assigned sorters if any
            if (!empty($assigned_sorters)) {
                $stmt = $conn->prepare("INSERT INTO assigned_sorters (user_id, device_identity, assigned_floor) VALUES (?, ?, ?)");
                foreach ($assigned_sorters as $sorter) {
                    $stmt->bind_param("iss", $user_id, $sorter, $assigned_floor);
                    $stmt->execute();
                }
            }

            // Commit transaction
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'User added successfully',
                'user_id' => $user_id
            ]);
            exit();
        } catch (Exception $e) {
            if ($conn->connect_errno) {
                $conn->rollback();
            }
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
            exit();
        }
    }
}

// Get list of available sorters for the form
$sorters = [];
$sorters_query = "SELECT device_identity, device_name, location FROM sorters";
$sorters_result = $conn->query($sorters_query);
if ($sorters_result) {
    while ($sorter = $sorters_result->fetch_assoc()) {
        $sorters[] = $sorter;
    }
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

        /* Account Management Styles */
        .content-area {
            padding: 2rem;
            overflow-y: auto;
        }

        .user-card {
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
        }

        .section-header {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 1.2rem;
            border-bottom: 2px solid var(--primary-green);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .section-header .controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .search-input {
            border-radius: 12px;
            padding: 6px 12px;
            border: 1px solid #ccc;
            width: 200px;
            height: 36px;
            transition: 0.3s;
        }

        .search-input:focus {
            border-color: var(--primary-green);
            outline: none;
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.2);
        }

        .btn-custom {
            background-color: var(--primary-green);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 6px 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-custom:hover {
            background-color: #1f3a13;
        }

        .btn-export {
            background-color: #2e7d32;
        }

        .btn-export:hover {
            background-color: #1f3a13;
        }

        .action-btn {
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 1.1rem;
            margin-right: 0.5rem;
        }

        .action-btn.edit {
            color: var(--primary-green);
        }

        .action-btn.delete {
            color: #dc3545;
        }

        .modal-content {
            border-radius: 16px;
        }

        /* Custom backdrop style */
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.3) !important; /* Lighter backdrop */
        }

        /* Fix for multiple backdrops */
        body:not(.modal-open) .modal-backdrop {
            display: none;
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
                    <div class="content-area">
                        <div class="user-card">
                            <div class="section-header">
                                <span>Manage User Accounts</span>
                                <div class="controls">
                                    <input type="text" class="search-input" id="searchInput" placeholder="Search user...">
                                    <button class="btn-custom btn-export" onclick="exportToCSV()">
                                        <i class="bi bi-download"></i> Export CSV
                                    </button>
                                    <button class="btn-custom" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                        <i class="bi bi-person-plus-fill"></i> Add User
                                    </button>
                                </div>
                            </div>

                            <table class="table align-middle table-hover" id="userTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Bin Assignment</th>
                                        <th style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="userTableBody">
                                    <tr>
                                        <td>Bea Landero</td>
                                        <td>Administrator</td>
                                        <td>Level 1, 2, 3, 4, 5</td>
                                        <td>
                                            <button class="action-btn edit" onclick="openEditModal('Bea Landero', 'Administrator', 'Level 1, 2, 3, 4, 5')"><i class="bi bi-pencil-square"></i></button>
                                            <button class="action-btn delete" onclick="openDeleteModal('Bea Landero')"><i class="bi bi-trash"></i></button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Michael Bargabino</td>
                                        <td>Utility Member</td>
                                        <td>Level 1</td>
                                        <td>
                                            <button class="action-btn edit" onclick="openEditModal('Michael Bargabino', 'Utility Member', 'Level 1')"><i class="bi bi-pencil-square"></i></button>
                                            <button class="action-btn delete" onclick="openDeleteModal('Michael Bargabino')"><i class="bi bi-trash"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ADD USER MODAL -->
                    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" role="dialog" aria-modal="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="addUserForm" method="POST">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">First Name</label>
                                                <input type="text" class="form-control" name="userName" id="addFirstName" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Last Name</label>
                                                <input type="text" class="form-control" name="lastName" id="addLastName" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Email</label>
                                                <input type="email" class="form-control" name="email" id="addEmail" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Password</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" name="password" id="addPassword" required>
                                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Role</label>
                                                <select class="form-select" name="role" id="addRole" required>
                                                    <option value="">Select Role</option>
                                                    <option value="admin">Administrator</option>
                                                    <option value="utility">Utility Member</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Assigned Floor</label>
                                                <input type="text" class="form-control" name="assigned_floor" id="addFloor" placeholder="e.g., Floor 1, Floor 2">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Assigned Sorters</label>
                                            <select class="form-select" name="assigned_sorters[]" id="addSorters" multiple size="4">
                                                <?php foreach ($sorters as $sorter): ?>
                                                    <option value="<?php echo htmlspecialchars($sorter['device_identity']); ?>">
                                                        <?php echo htmlspecialchars($sorter['device_name'] . ' (' . $sorter['location'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">Hold Ctrl (Windows) or Command (Mac) to select multiple sorters</small>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button class="btn btn-success" onclick="addUser()">Add User</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- EDIT MODAL -->
                    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit User</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="editUserForm">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Full Name</label>
                                            <input type="text" class="form-control" id="editName">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Role</label>
                                            <select class="form-select" id="editRole">
                                                <option>Administrator</option>
                                                <option>Utility Member</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Bin Assignment</label>
                                            <input type="text" class="form-control" id="editBin">
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button class="btn btn-success" onclick="saveUserChanges()">Save Changes</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DELETE MODAL -->
                    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-danger">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title">Confirm Deletion</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    Are you sure you want to delete <strong id="deleteUserName"></strong>?
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button class="btn btn-danger" onclick="confirmDelete()">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
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

    // Accounts Tab JavaScript
    let currentUser = {};

    const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    const deleteUserModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
    const addUserModal = new bootstrap.Modal(document.getElementById('addUserModal'));

    function openEditModal(name, role, bin) {
        document.getElementById('editName').value = name;
        document.getElementById('editRole').value = role;
        document.getElementById('editBin').value = bin;
        currentUser = { name, role, bin };
        editUserModal.show();
    }

    function saveUserChanges() {
        console.log("Saved changes for:", {
            name: document.getElementById('editName').value,
            role: document.getElementById('editRole').value,
            bin: document.getElementById('editBin').value
        });
        editUserModal.hide();
    }

    function openDeleteModal(name) {
        document.getElementById('deleteUserName').textContent = name;
        currentUser = { name };
        deleteUserModal.show();
    }

    function confirmDelete() {
        console.log("Deleted user:", currentUser.name);
        deleteUserModal.hide();
    }

    function addUser() {
        const form = document.getElementById('addUserForm');
        const modal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
        
        // Basic form validation
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Create FormData object
        const formData = new FormData(form);

        // Send POST request
        fetch(window.location.pathname, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add new row to table
                const table = document.getElementById('userTableBody');
                const fullName = `${formData.get('userName')} ${formData.get('lastName')}`;
                const role = formData.get('role') === 'admin' ? 'Administrator' : 'Utility Member';
                const floor = formData.get('assigned_floor');
                
                const newRow = `
                    <tr>
                        <td>${fullName}</td>
                        <td>${role}</td>
                        <td>${floor}</td>
                        <td>
                            <button class="action-btn edit" onclick="openEditModal('${fullName}', '${role}', '${floor}')">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="action-btn delete" onclick="openDeleteModal('${fullName}')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                table.insertAdjacentHTML('beforeend', newRow);
                
                // Reset form and close modal properly
                form.reset();
                modal.hide();
                setTimeout(() => {
                    // Remove all modal backdrops
                    document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }, 200);
                
                // Show success message with Bootstrap Toast
                const successToast = new bootstrap.Toast(document.createElement('div'));
                const toastContainer = document.createElement('div');
                toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
                toastContainer.style.zIndex = '1070';
                toastContainer.innerHTML = `
                    <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                User added successfully!
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                `;
                document.body.appendChild(toastContainer);
                const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
                toast.show();
                setTimeout(() => toastContainer.remove(), 5000);
            } else {
                const errorMessage = data.message || 'Error adding user. Please try again.';
                const errorToast = document.createElement('div');
                errorToast.className = 'position-fixed bottom-0 end-0 p-3';
                errorToast.style.zIndex = '1070';
                errorToast.innerHTML = `
                    <div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                ${errorMessage}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                `;
                document.body.appendChild(errorToast);
                const toast = new bootstrap.Toast(errorToast.querySelector('.toast'));
                toast.show();
                setTimeout(() => errorToast.remove(), 5000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorToast = document.createElement('div');
            errorToast.className = 'position-fixed bottom-0 end-0 p-3';
            errorToast.style.zIndex = '1070';
            errorToast.innerHTML = `
                <div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            An error occurred. Please try again.
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            document.body.appendChild(errorToast);
            const toast = new bootstrap.Toast(errorToast.querySelector('.toast'));
            toast.show();
            setTimeout(() => errorToast.remove(), 5000);
        });
    }

    // Add password toggle functionality
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('addPassword');
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });

    // SEARCH FILTER
    document.getElementById('searchInput').addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#userTableBody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });

    //EXPORT TO CSV
    function exportToCSV() {
        const rows = document.querySelectorAll('#userTable tr');
        let csvContent = '';
        rows.forEach(row => {
            const cols = row.querySelectorAll('th, td');
            const data = Array.from(cols).map(col => `"${col.innerText}"`).join(',');
            csvContent += data + '\n';
        });

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'GoSort_user_accounts.csv';
        a.click();
        URL.revokeObjectURL(url);
    }
    </script>
</body>
</html>