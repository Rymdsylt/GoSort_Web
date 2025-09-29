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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .splash-logo {
            max-width: 440px;
            width: 90%;
            height: auto;
            display: block;
            margin-bottom: 2rem;
            transition: transform 0.2s;
        }
        .splash-logo:hover {
            transform: scale(1.08);
        }
        @font-face {
         font-family: 'Kay Pho Du';
         src: url('fonts/inter.ttf') format('truetype');
         font-weight: normal;
         font-style: normal;
        }
        body {
            font-family: 'inter', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #F3F3EF;
        }
        .login-container {
            width: 100%;
            max-width:500px;
            padding: 4rem;
            margin: 50px;
        }

        .form-control {
            font-family: 'inter', sans-serif;
            border: 1px solid #333;
            border-radius: 5px;
            background-color: transparent;
            padding: 10px;
            font-size: 14px;
            position: relative;
            z-index: 1;
            border-width: 1.5px;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #58C542;
            border-width: 1.5px;
        }
        .btn-login{
            font-family: 'inter', sans-serif;
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
        #splash-screen {
            position: fixed;
            z-index: 9999;
            inset: 0;
            background: #F3F3EF;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.7s, visibility 0.7s;
        }

        #main-content {
            transition: opacity 0.7s;
        }
    </style>
</head>
<body>
    <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
    <div id="splash-screen" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;height:100vh;width:100vw;">
        <div id="splash-anim-group" style="display:flex;flex-direction:column;align-items:center;">
            <img src="images/logos/splashlogo2.svg" alt="GoSort Logo" id="splash-logo" style="max-width:440px;width:90%;height:auto;display:block;margin-bottom:2rem;opacity:0;transform:scale(0.7);transition:opacity 0.7s,transform 0.7s;" oncontextmenu="return false" ondragstart="return false" onselectstart="return false;">
            <div id="splash-text" style="font-size:1.0rem;color:#333;font-family:'Kay Pho Du',sans-serif;opacity:0;transform:scale(0.7);transition:opacity 0.7s,transform 0.7s;">
                <em>A Smart Waste Management System in partnership with Pateros Catholic School</em>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="text-center mb-4">
        <img src="images/logos/splashlogo2.svg" alt="GoSort Logo" class="splash-logo d-block mx-auto" oncontextmenu="return false" ondragstart="return false" onselectstart="return false;">
        <div style="font-size:1.0rem;color:#333;font-family:'Kay Pho Du',sans-serif;">
            <em>A Smart Waste Management System in partnership with Pateros Catholic School</em>
        </div>
    </div>

    <div class="login-container bg-white rounded shadow">
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
    <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
    <script>
    window.addEventListener('DOMContentLoaded', function() {
      const splash = document.getElementById('splash-screen');
      const splashLogo = document.getElementById('splash-logo');
      const splashText = document.getElementById('splash-text');
      const splashGroup = document.getElementById('splash-anim-group');
      const loginLogo = document.querySelector('.splash-logo.d-block');
      const loginText = document.querySelector('.text-center.mb-4 em').parentElement;
      function getLogoTargetRect() {
        const rect = loginLogo.getBoundingClientRect();
        return {
          left: rect.left,
          top: rect.top,
          width: rect.width,
          height: rect.height
        };
      }
      function getTextTargetRect() {
        const rect = loginText.getBoundingClientRect();
        return {
          left: rect.left,
          top: rect.top,
          width: rect.width,
          height: rect.height
        };
      }
      // Fade in and scale up both logo and text
      setTimeout(() => {
        splashLogo.style.opacity = 1;
        splashLogo.style.transform = 'scale(1)';
        splashText.style.opacity = 1;
        splashText.style.transform = 'scale(1)';
      }, 200);
      // Wait about 1 second before sliding
      setTimeout(() => {
        const logoTarget = getLogoTargetRect();
        const textTarget = getTextTargetRect();
        const splashLogoRect = splashLogo.getBoundingClientRect();
        const splashTextRect = splashText.getBoundingClientRect();
        const dxLogo = logoTarget.left - splashLogoRect.left;
        const dyLogo = logoTarget.top - splashLogoRect.top;
        const scaleLogo = logoTarget.width / splashLogoRect.width;
        const dxText = textTarget.left - splashTextRect.left;
        const dyText = textTarget.top - splashTextRect.top;
        const scaleText = textTarget.width / splashTextRect.width;
        splashLogo.style.transition = 'transform 0.8s cubic-bezier(.77,0,.18,1)';
        splashLogo.style.transform = `translate(${dxLogo}px, ${dyLogo}px) scale(${scaleLogo})`;
        splashText.style.transition = 'transform 0.8s cubic-bezier(.77,0,.18,1)';
        splashText.style.transform = `translate(${dxText}px, ${dyText}px) scale(${scaleText})`;
      }, 1200);
      setTimeout(() => {
        splash.style.opacity = 0;
        splash.style.pointerEvents = 'none';
      }, 2000);
      setTimeout(() => {
        splash.remove();
      }, 2600);
    });
    </script>
    <?php endif; ?>
</body>
</html>
