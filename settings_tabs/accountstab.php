<?php
// BACKEND NOT YET IMPLEMENTED
/*$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    $query = "SELECT userName, email, contact, role FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($name, $email, $contact, $role);
    $stmt->fetch();
    $stmt->close();
}*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        :root {
            --primary-green: #2e7d32;
            --light-green: #66bb6a;
            --dark-gray: #333;
            --medium-gray: #6b7280;
            --light-gray: #f9fafb;
        }

        body {
            background-color: var(--light-gray);
            font-family: 'Poppins', sans-serif;
        }

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

        table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        .table thead {
            background-color: var(--primary-green);
            color: white;
        }

        .table th {
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table td, .table th {
            padding: 14px 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .table tbody tr:hover {
            background-color: #f0fdf4;
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
    </style>
</head>
<body>
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
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" class="form-control" id="addName" placeholder="Enter name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role</label>
                        <select class="form-select" id="addRole">
                            <option>Administrator</option>
                            <option>Utility Member</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bin Assignment</label>
                        <input type="text" class="form-control" id="addBin" placeholder="Enter bin assignment">
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

<script>
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
        const name = document.getElementById('addName').value.trim();
        const role = document.getElementById('addRole').value;
        const bin = document.getElementById('addBin').value.trim();

        if (!name || !bin) {
            alert("Please fill out all fields.");
            return;
        }

        const table = document.getElementById('userTableBody');
        const newRow = `
            <tr>
                <td>${name}</td>
                <td>${role}</td>
                <td>${bin}</td>
                <td>
                    <button class="action-btn edit" onclick="openEditModal('${name}', '${role}', '${bin}')"><i class="bi bi-pencil-square"></i></button>
                    <button class="action-btn delete" onclick="openDeleteModal('${name}')"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `;
        table.insertAdjacentHTML('beforeend', newRow);

        addUserModal.hide();
        document.getElementById('addUserForm').reset();
    }

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
