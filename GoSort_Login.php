<?php
session_start();
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';
if(isset($_SESSION['user_id'])) {
    header("Location: GoSort_Sorters.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
    $error = 'Please fill out all fields';
    } else {
    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
        if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        setcookie('user_logged_in', 'true', time() + (86400 * 30), "/"); // 30 days
        header("Location: GoSort_Sorters.php");
        exit();
        } else {
        $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Login</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <style>
        @font-face {
         font-family: 'Kay Pho Du';
         src: url('fonts/KayPhoDu-Regular.ttf') format('truetype');
         font-weight: normal;
         font-style: normal;
        }
        body {
            font-family: 'Kay Pho Du', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #F3F3EF;
        }
        .login-container {
            width: 100%;
            max-width:330px;
        }
        .login-container img{
            max-width: 330px;
            position: relative;
            z-index: 0;
            transform: scale(1.3);
            user-select:none;
            -webkit-user-select:none;
        }
        .form-control {
            font-family: 'Kay Pho Du', sans-serif;
            border: 1px solid #333;
            border-radius: 5px;
            background-color: transparent;
            padding: 10px;
            font-size: 14px;
            position: relative;
            z-index: 1;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #28a745;
        }
        .btn-login{
            font-family: 'Kay Pho Du', sans-serif;
            background-color: #58C542;
            color: black;
            border: none;
            padding: 10px 15px;
            font-size: 16px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .btn-login:hover {
            background-color: #14AE31;
        }

    </style>
</head>
<body>
    <div class="login-container">
            <img src="images/logos/5.svg" alt="GoSort Logo" oncontextmenu="return false" ondragstart="return false" onselectstart="return false;">
            <?php if ($error): ?>
                <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username">

                </div>
                <div class="mb-3">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password">
                    
                </div>
                <button class="w-100 btn btn-login" type="submit">Sign in</button>
            </form>
        </div>
    </div>
    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
