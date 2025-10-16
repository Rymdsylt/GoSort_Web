<?php
session_start();
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';

if (!isset($_SESSION['user_id']) || !isset($_COOKIE['user_logged_in'])) {
    header("Location: GoSort_Login.php");
    exit();
}

// Check if the logged-in user is an admin
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user['role'] !== 'admin') {
    header("Location: GoSort_Settings.php");
    exit();
}

// Get list of available sorters
$sorters_query = "SELECT device_identity, device_name, location FROM sorters";
$sorters_result = $conn->query($sorters_query);
$sorters = [];
while ($row = $sorters_result->fetch_assoc()) {
    $sorters[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userName = $_POST['userName'];
    $lastName = $_POST['lastName'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $assigned_floor = $_POST['assigned_floor'];
    $assigned_sorters = isset($_POST['assigned_sorters']) ? $_POST['assigned_sorters'] : [];

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Insert user
        $stmt = $conn->prepare("INSERT INTO users (userName, lastName, email, password, role, assigned_floor) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $userName, $lastName, $email, $password, $role, $assigned_floor);
        $stmt->execute();
        $user_id = $conn->insert_id;

        // Insert assigned sorters
        if (!empty($assigned_sorters)) {
            $assign_stmt = $conn->prepare("INSERT INTO assigned_sorters (user_id, device_identity) VALUES (?, ?)");
            foreach ($assigned_sorters as $sorter) {
                $assign_stmt->bind_param("is", $user_id, $sorter);
                $assign_stmt->execute();
            }
        }

        $conn->commit();
        $success_message = "User added successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error adding user: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Add User</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #F3F3EF;
            font-family: 'Inter', sans-serif;
        }

        .container {
            max-width: 800px;
            margin-top: 2rem;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: #274a17;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }

        .form-label {
            font-weight: 600;
        }

        .btn-primary {
            background-color: #274a17;
            border-color: #274a17;
        }

        .btn-primary:hover {
            background-color: #1a3110;
            border-color: #1a3110;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Add New User</h4>
            </div>
            <div class="card-body">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="userName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="lastName" class="form-control" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="utility">Utility</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assigned Floor</label>
                            <input type="text" name="assigned_floor" class="form-control" placeholder="e.g., Floor 1, Floor 2">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Assigned Sorters</label>
                        <select name="assigned_sorters[]" class="form-select" multiple size="5">
                            <?php foreach ($sorters as $sorter): ?>
                                <option value="<?php echo htmlspecialchars($sorter['device_identity']); ?>">
                                    <?php echo htmlspecialchars($sorter['device_name'] . ' (' . $sorter['location'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Hold Ctrl (Windows) or Command (Mac) to select multiple sorters</small>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>