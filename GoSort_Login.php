<?php
session_start();
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';
require_once 'gs_DB/activity_logs.php';

if(isset($_SESSION['user_id'])) {
    header("Location: GoSort_Dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill out all fields';
    } else {
        $stmt = $pdo->prepare("SELECT id, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            setcookie('user_logged_in', 'true', time() + (86400 * 30), "/");
            log_login($user['id']);
            header("Location: GoSort_Dashboard.php");
            exit();
        } else {
            $error = 'Invalid email or password';
            log_login_failed($email);
        }
    }
}

$has_error = !empty($error);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Login</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green:      #58C542;
            --green-dark: #14AE31;
            --green-deep: #1a5c2a;
            --green-glow: rgba(88,197,66,0.18);
            --bg-tint:    #edf7ed;
            --text:       #1a1a1a;
            --muted:      #6b7280;
            --border:     #e5e7eb;
            --dot-color:  rgba(146, 205, 134, 0.35);
        }

        html, body {
            font-family: 'Poppins', sans-serif !important;
            height: 100%;
            margin: 0;
        }

        @keyframes fadeLeft {
            from { opacity: 0; transform: translateX(-22px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .split-wrap {
            display: flex;
            min-height: 100vh;
            position: relative;
            overflow: hidden;
        }

        .panel-right { animation: none; }

        .animate .left-logo    { animation: fadeLeft 0.6s ease 0.2s both; }
        .animate .form-heading { animation: fadeLeft 0.6s ease 0.35s both; }
        .animate .field-wrap   { animation: fadeLeft 0.6s ease 0.5s both; }
        .animate .field-wrap + .field-wrap { animation-delay: 0.6s; }
        .animate .btn-login    { animation: fadeLeft 0.6s ease 0.7s both; }

        .dotted-bg {
            background-color: #fff;
            background-image: radial-gradient(circle, var(--dot-color) 1.5px, transparent 1.5px);
            background-size: 22px 22px;
        }

        .panel-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 5rem 0 6rem 12%;
            position: relative;
            z-index: 2;
        }

        .panel-left::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 40%, rgba(237,247,237,0.92) 100%);
            pointer-events: none;
            z-index: 0;
        }

        .panel-left > * { position: relative; z-index: 1; }

        .left-logo {
            width: min(220px, 60%);
            height: auto;
            margin-bottom: 2.5rem;
        }

        .form-heading { margin-bottom: 2rem; }
        .form-heading h2 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.03em;
            line-height: 1.15;
            margin-bottom: 0.4rem;
        }
        .form-heading p {
            font-family: 'Poppins', sans-serif;
            font-size: 0.875rem;
            font-weight: 400;
            color: var(--muted);
        }

        .field-label {
            font-family: 'Poppins', sans-serif;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 0.4rem;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            transition: color 0.2s;
        }
        .field-wrap { margin-bottom: 1.5rem; }
        .field-wrap:focus-within .field-label { color: var(--green-dark); }
        .field-wrap:focus-within .field-icon svg { stroke: var(--green); }

        .input-wrap { position: relative; }

        .field-icon {
            position: absolute;
            left: 0; top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            pointer-events: none;
            z-index: 2;
            display: flex; align-items: center;
            transition: color 0.2s;
        }
        .field-icon svg {
            width: 15px; height: 15px;
            stroke: currentColor; fill: none;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
            transition: stroke 0.2s;
        }

        .form-control {
            font-family: 'Poppins', sans-serif !important;
            width: 100%;
            border: none;
            border-bottom: 1.5px solid var(--border);
            border-radius: 0;
            background: transparent;
            padding: 10px 36px 10px 26px;
            font-size: 0.9rem;
            font-weight: 400;
            color: var(--text);
            transition: border-color 0.25s;
            outline: none !important;
            box-shadow: none !important;
            appearance: none;
        }
        .form-control::placeholder {
            font-family: 'Poppins', sans-serif;
            color: #c5cad3; font-weight: 300;
        }
        .form-control:focus { border-bottom: 2px solid var(--green); }

        .input-line {
            display: block;
            height: 2px;
            background: linear-gradient(90deg, var(--green), var(--green-dark));
            border-radius: 2px;
            width: 0;
            transition: width 0.3s cubic-bezier(.4,0,.2,1);
            margin-top: -2px;
        }
        .form-control:focus ~ .input-line { width: 100%; }

        .toggle-pw {
            position: absolute;
            right: 0; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            padding: 2px; cursor: pointer;
            color: var(--muted); z-index: 2;
            display: flex; align-items: center;
            transition: color 0.2s;
        }
        .toggle-pw:hover { color: var(--green-dark); }
        .toggle-pw svg {
            width: 16px; height: 16px;
            stroke: currentColor; fill: none;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        }

        .btn-login {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: #fff; border: none;
            padding: 13px 3.5rem;
            font-size: 0.9rem;
            font-weight: 600; border-radius: 50px;
            margin-top: 1rem; cursor: pointer;
            letter-spacing: 0.04em;
            width: 100%;
            max-width: 360px;
            transition: transform 0.18s cubic-bezier(.34,1.56,.64,1),
                        box-shadow 0.2s, filter 0.2s;
            box-shadow: 0 4px 16px rgba(88,197,66,0.35);
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(88,197,66,0.45);
            filter: brightness(1.05);
        }
        .btn-login:active { transform: translateY(0); }

        .error-box {
            font-family: 'Poppins', sans-serif;
            font-size: 0.82rem; font-weight: 400;
            border-radius: 8px;
            padding: 0.65rem 0.9rem;
            margin-bottom: 1.25rem;
            background: #fff2f2; border: 1px solid #fca5a5; color: #b91c1c;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .error-box svg {
            flex-shrink: 0; width: 15px; height: 15px;
            stroke: #b91c1c; fill: none;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        }

        .panel-right {
            flex: 1.8;
            position: relative;
            overflow: hidden;
        }

        .panel-right-dots {
            position: absolute;
            inset: 0;
            background-color: #fff;
            background-image: radial-gradient(circle, var(--dot-color) 1.5px, transparent 1.5px);
            background-size: 22px 22px;
            z-index: 0;
        }

        .panel-right-dots::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 40%, rgba(237,247,237,0.9) 100%);
        }

        .panel-right-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: left center;
            z-index: 1;
            display: block;
        }

        @media (max-width: 768px) {
            .split-wrap { flex-direction: column; }
            .panel-right { display: none; }
            .panel-left {
                padding: 3rem 2rem;
                justify-content: center;
                min-height: 100vh;
            }
            .left-logo { margin-bottom: 2rem; }
        }
    </style>
</head>
<body>

<div class="split-wrap">

    <div class="panel-left dotted-bg<?php echo $has_error ? '' : ' animate'; ?>">

        <img src="images/logos/splashlogo2.svg" alt="GoSort" class="left-logo"
             oncontextmenu="return false" ondragstart="return false" onselectstart="return false">

        <div class="form-heading">
            <h2>Welcome back</h2>
            <p>Sign in to your GoSort account to continue</p>
        </div>

        <?php if ($error): ?>
        <div class="error-box">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" style="max-width:360px;" novalidate>
            <div class="field-wrap">
                <label class="field-label" for="email">Email</label>
                <div class="input-wrap">
                    <span class="field-icon">
                        <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </span>
                    <input type="text" class="form-control" id="email" name="email"
                        placeholder="Enter your email" autocomplete="off"
                        value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    <span class="input-line"></span>
                </div>
            </div>

            <div class="field-wrap">
                <label class="field-label" for="password">Password</label>
                <div class="input-wrap">
                    <span class="field-icon">
                        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <input type="password" class="form-control" id="password" name="password"
                        placeholder="Enter your password" autocomplete="new-password">
                    <span class="input-line"></span>
                    <button type="button" class="toggle-pw" id="toggle-pw" title="Show/hide password">
                        <svg id="icon-eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg id="icon-eye-off" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <button class="btn-login" type="submit">Sign in</button>
        </form>

    </div>

    <div class="panel-right">
        <div class="panel-right-dots"></div>
        <img class="panel-right-img" src="images/logos/login.png" alt="">
    </div>

</div>

<script src="js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('toggle-pw').addEventListener('click', function () {
    const pw     = document.getElementById('password');
    const eyeOn  = document.getElementById('icon-eye');
    const eyeOff = document.getElementById('icon-eye-off');
    const show   = pw.type === 'text';
    pw.type              = show ? 'password' : 'text';
    eyeOn.style.display  = show ? '' : 'none';
    eyeOff.style.display = show ? 'none' : '';
});
</script>
</body>
</html>