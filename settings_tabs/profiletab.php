<?php
// ── Fetch current user profile ───────────────────────────────────────────────
$profile_user = null;
if (isset($_SESSION['user_id']) && isset($conn)) {
    $stmt = $conn->prepare("SELECT userName, lastName, email, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $profile_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$p_fname      = htmlspecialchars($profile_user['userName'] ?? '');
$p_lname      = htmlspecialchars($profile_user['lastName'] ?? '');
$p_name       = trim("$p_fname $p_lname") ?: 'User';
$p_email      = htmlspecialchars($profile_user['email'] ?? '');
$p_role       = $profile_user['role'] ?? 'utility';
$role_labels  = ['superadmin' => 'Super Admin', 'admin' => 'Administrator', 'utility' => 'Utility Member'];
$p_role_label = $role_labels[$p_role] ?? ucfirst($p_role);

// ── AJAX: update_profile ─────────────────────────────────────────────────────
// Paste this block inside your GoSort_Settings.php AJAX POST handler
// (inside the `if ($_SERVER['REQUEST_METHOD'] === 'POST')` try block,
//  BEFORE the existing "add user" logic):
//
// if (($_POST['action'] ?? '') === 'update_profile') {
//     $uid      = $_SESSION['user_id'];
//     $userName = trim($_POST['userName'] ?? '');
//     $lastName = trim($_POST['lastName'] ?? '');
//     $email    = trim($_POST['email'] ?? '');
//     $password = $_POST['password'] ?? '';
//     if (!$userName || !$lastName || !$email)
//         throw new Exception('Name and email are required');
//     if (!filter_var($email, FILTER_VALIDATE_EMAIL))
//         throw new Exception('Invalid email format');
//     $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
//     $chk->bind_param("si", $email, $uid); $chk->execute();
//     if ($chk->get_result()->num_rows > 0) throw new Exception('Email already in use');
//     if (!empty($password)) {
//         $hp   = password_hash($password, PASSWORD_DEFAULT);
//         $stmt = $conn->prepare("UPDATE users SET userName=?, lastName=?, email=?, password=? WHERE id=?");
//         $stmt->bind_param("ssssi", $userName, $lastName, $email, $hp, $uid);
//     } else {
//         $stmt = $conn->prepare("UPDATE users SET userName=?, lastName=?, email=? WHERE id=?");
//         $stmt->bind_param("sssi", $userName, $lastName, $email, $uid);
//     }
//     $stmt->execute();
//     log_activity('general', 'Profile Updated', "User updated their own profile", $uid);
//     echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
//     exit();
// }
?>

<style>
.profile-section-block {
    background: linear-gradient(135deg, rgb(236,251,234) 0%, #d5f5dc 100%);
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
}
.profile-section-block:last-child { margin-bottom: 0; }

.profile-inner-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.07);
}

/* ── Hero ── */
.profile-hero {
    display: flex; align-items: center; gap: 1rem;
    padding-bottom: 1rem; margin-bottom: 1.25rem;
    border-bottom: 1px solid #f3f4f6;
}
.profile-hero-avatar {
    width: 52px; height: 52px; border-radius: 50%;
    background: linear-gradient(135deg, #7AF146, #368137);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; font-weight: 700; color: #fff;
    font-family: 'Poppins', sans-serif; flex-shrink: 0;
    letter-spacing: 0.02em; user-select: none;
}
.profile-hero-name {
    font-size: 0.98rem; font-weight: 700; color: #1f2937;
    margin: 0 0 0.2rem; font-family: 'Poppins', sans-serif;
}
.profile-hero-role {
    display: inline-flex; align-items: center;
    padding: 0.18rem 0.55rem; border-radius: 20px;
    font-size: 0.68rem; font-weight: 600;
    font-family: 'Poppins', sans-serif;
}
.profile-hero-role.admin      { background: #e8f5e1; color: #274a17; }
.profile-hero-role.utility    { background: #dbeafe; color: #1d4ed8; }
.profile-hero-role.superadmin { background: #fef3c7; color: #92400e; }

/* ── Section label ── */
.profile-section-label {
    font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.07em;
    color: #000000b1; margin-bottom: 0.9rem;
}

/* ── Info rows ── */
.profile-info-row {
    display: flex; align-items: center;
    justify-content: space-between;
    gap: 1rem; padding: 0.65rem 0;
    border-bottom: 1px solid #f3f4f6;
}
.profile-info-row:last-child  { border-bottom: none; padding-bottom: 0; }
.profile-info-row:first-child { padding-top: 0; }
.pir-edit-wrap { flex: 1; min-width: 0; }

.pir-label {
    font-size: 0.72rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.04em;
    color: #6b7280; margin-bottom: 0.18rem;
    font-family: 'Poppins', sans-serif;
}
.pir-value {
    font-size: 0.83rem; font-weight: 500;
    color: #1f2937; font-family: 'Poppins', sans-serif;
}

/* ── Inline input ── */
.pir-field-input {
    font-family: 'Poppins', sans-serif !important;
    font-size: 0.82rem;
    border: 1.5px solid #e5e7eb; border-radius: 8px;
    padding: 0.4rem 0.65rem;
    width: 100%; max-width: 280px;
    outline: none; transition: border-color 0.2s;
    display: none; margin-top: 0.25rem;
}
.pir-field-input:focus { border-color: #368137; box-shadow: 0 0 0 3px rgba(54,129,55,0.1); }
.pir-field-input.visible { display: block; }

/* ── Buttons ── */
.pir-edit-btn {
    background: transparent;
    border: 1.5px solid #368137; color: #274a17;
    border-radius: 7px; padding: 0.3rem 0.7rem;
    font-size: 0.74rem; font-weight: 600;
    font-family: 'Poppins', sans-serif;
    cursor: pointer; white-space: nowrap; flex-shrink: 0;
    transition: all 0.18s;
    display: inline-flex; align-items: center; gap: 0.3rem;
}
.pir-edit-btn:hover { background: #f0fdf4; }
.pir-edit-btn.active {
    background: linear-gradient(135deg, #368137 0%, #274a17 100%);
    color: #fff; border-color: transparent;
}
.pir-edit-btn.danger { border-color: #dc2626; color: #dc2626; }
.pir-edit-btn.danger:hover { background: #fee2e2; }

/* ── Theme switch ── */
.theme-switch {
    display: inline-block; width: 42px; height: 24px;
    position: relative; flex-shrink: 0;
}
.theme-switch input { display: none; }
.ts-slider {
    position: absolute; inset: 0;
    background: #d1d5db; border-radius: 34px;
    cursor: pointer; transition: background 0.3s;
}
.ts-slider:before {
    content: ''; position: absolute;
    width: 16px; height: 16px; left: 4px; top: 4px;
    background: #fff; border-radius: 50%; transition: transform 0.3s;
}
.theme-switch input:checked + .ts-slider { background: #368137; }
.theme-switch input:checked + .ts-slider:before { transform: translateX(18px); }

/* ── Unsaved bar ── */
.profile-save-bar {
    display: none; align-items: center;
    justify-content: space-between;
    background: #fff8e1; border: 1px solid #fbbf24;
    border-radius: 10px; padding: 0.6rem 1rem;
    margin-bottom: 0.9rem;
    font-size: 0.8rem; font-family: 'Poppins', sans-serif;
    color: #92400e; font-weight: 500; gap: 0.75rem;
}
.profile-save-bar.visible { display: flex; }
.psb-actions { display: flex; gap: 0.5rem; flex-shrink: 0; }
.psb-save {
    background: linear-gradient(135deg, #368137 0%, #274a17 100%);
    color: #fff; border: none; border-radius: 7px;
    padding: 0.32rem 0.8rem; font-size: 0.77rem; font-weight: 600;
    font-family: 'Poppins', sans-serif; cursor: pointer;
    display: inline-flex; align-items: center; gap: 0.3rem;
    transition: box-shadow 0.2s;
}
.psb-save:hover { box-shadow: 0 3px 10px rgba(39,74,23,0.25); }
.psb-discard {
    background: transparent; border: 1.5px solid #d1d5db;
    color: #6b7280; border-radius: 7px;
    padding: 0.3rem 0.7rem; font-size: 0.77rem; font-weight: 600;
    font-family: 'Poppins', sans-serif; cursor: pointer;
    transition: background 0.15s;
}
.psb-discard:hover { background: #f9fafb; }
</style>

<!-- ── Unsaved changes bar ── -->
<div class="profile-save-bar" id="profileSaveBar">
    <span><i class="bi bi-exclamation-circle me-1"></i>You have unsaved changes</span>
    <div class="psb-actions">
        <button class="psb-discard" onclick="discardProfileChanges()">Discard</button>
        <button class="psb-save" onclick="saveProfileChanges()">
            <i class="bi bi-check-lg"></i> Save Changes
        </button>
    </div>
</div>

<!-- ── BLOCK 1: Personal Information ── -->
<div class="profile-section-block">
    <div class="profile-inner-card">
        <div class="profile-hero">
            <div class="profile-hero-avatar" id="hero-initials">
                <?= strtoupper(substr($p_fname,0,1) . substr($p_lname,0,1)) ?: 'U' ?>
            </div>
            <div>
                <p class="profile-hero-name" id="hero-name"><?= $p_name ?></p>
                <span class="profile-hero-role <?= htmlspecialchars($p_role) ?>"><?= $p_role_label ?></span>
            </div>
        </div>

        <div class="profile-section-label">Personal Information</div>

        <div class="profile-info-row">
            <div class="pir-edit-wrap">
                <div class="pir-label">First Name</div>
                <div class="pir-value" id="display-fname"><?= $p_fname ?: '—' ?></div>
                <input class="pir-field-input" id="input-fname" type="text"
                    value="<?= $p_fname ?>" placeholder="First name">
            </div>
            <button class="pir-edit-btn" id="btn-fname" onclick="toggleProfileField('fname')">
                <i class="bi bi-pencil"></i> Edit
            </button>
        </div>

        <div class="profile-info-row">
            <div class="pir-edit-wrap">
                <div class="pir-label">Last Name</div>
                <div class="pir-value" id="display-lname"><?= $p_lname ?: '—' ?></div>
                <input class="pir-field-input" id="input-lname" type="text"
                    value="<?= $p_lname ?>" placeholder="Last name">
            </div>
            <button class="pir-edit-btn" id="btn-lname" onclick="toggleProfileField('lname')">
                <i class="bi bi-pencil"></i> Edit
            </button>
        </div>

        <div class="profile-info-row" style="border-bottom:none;padding-bottom:0;">
            <div class="pir-edit-wrap">
                <div class="pir-label">Email</div>
                <div class="pir-value" id="display-email"><?= $p_email ?: '—' ?></div>
                <input class="pir-field-input" id="input-email" type="email"
                    value="<?= $p_email ?>" placeholder="your@email.com">
            </div>
            <button class="pir-edit-btn" id="btn-email" onclick="toggleProfileField('email')">
                <i class="bi bi-pencil"></i> Edit
            </button>
        </div>
    </div>
</div>

<!-- ── BLOCK 2: Security ── -->
<div class="profile-section-block">
    <div class="profile-inner-card">
        <div class="profile-section-label">Security</div>

        <div class="profile-info-row">
            <div>
                <div class="pir-label">Role</div>
                <div class="pir-value"><?= $p_role_label ?></div>
            </div>
        </div>

        <div class="profile-info-row" style="border-bottom:none;padding-bottom:0;">
            <div>
                <div class="pir-label">Password</div>
                <?php if ($p_role === 'superadmin'): ?>
                    <div class="pir-value">••••••••</div>
                    <div style="font-size:0.75rem;color:#6b7280;margin-top:0.2rem;font-family:'Poppins',sans-serif;">
                        <i class="bi bi-shield-lock me-1"></i>Password management is handled at the system level.
                    </div>
                <?php elseif ($p_role === 'admin'): ?>
                    <div class="pir-value">••••••••</div>
                    <div style="font-size:0.75rem;color:#6b7280;margin-top:0.2rem;font-family:'Poppins',sans-serif;">
                        <i class="bi bi-info-circle me-1"></i>To reset your password, contact GoSort support at
                        <a href="mailto:gosort.support@gmail.com" style="color:#368137;font-weight:600;text-decoration:none;">gosort.support@gmail.com</a>
                    </div>
                <?php else: ?>
                    <div class="pir-value">••••••••</div>
                    <div style="font-size:0.75rem;color:#6b7280;margin-top:0.2rem;font-family:'Poppins',sans-serif;">
                        <i class="bi bi-info-circle me-1"></i>To change your password, contact your <strong>Administrator</strong>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── BLOCK 3: Preferences & Actions ── -->
<div class="profile-section-block">
    <div class="profile-inner-card">
        <div class="profile-section-label">Preferences & Actions</div>

        <div class="profile-info-row">
            <div>
                <div class="pir-label">Dark Mode</div>
                <div class="pir-value">Toggle light / dark theme</div>
            </div>
            <label class="theme-switch">
                <input type="checkbox" id="theme-toggle" onchange="profileToggleTheme()">
                <span class="ts-slider"></span>
            </label>
        </div>

        <div class="profile-info-row">
            <div>
                <div class="pir-label">Session</div>
                <div class="pir-value">Securely log out of your account</div>
            </div>
            <button class="pir-edit-btn" data-bs-toggle="modal" data-bs-target="#profileLogoutModal">
                <i class="bi bi-box-arrow-right"></i> Logout
            </button>
        </div>

        <div class="profile-info-row" style="border-bottom:none;padding-bottom:0;">
            <div>
                <div class="pir-label" style="color:#dc2626;">Danger Zone</div>
                <div class="pir-value" style="color:#dc2626;">Permanently delete your account</div>
            </div>
            <button class="pir-edit-btn danger" data-bs-toggle="modal" data-bs-target="#profileDeleteModal">
                <i class="bi bi-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<!-- ════ LOGOUT MODAL ════ -->
<div class="modal fade" id="profileLogoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-box-arrow-right" style="color:#368137;font-size:1.3rem;"></i>
                    <h5 style="font-size:0.95rem;font-weight:700;margin:0;">Confirm Logout</h5>
                </div>
                <p style="font-size:0.82rem;color:#6b7280;margin-bottom:1rem;">
                    Are you sure you want to log out of your session?
                </p>
                <div class="d-flex gap-2 justify-content-end">
                    <button class="btn btn-light" data-bs-dismiss="modal"
                        style="font-family:'Poppins',sans-serif;font-size:0.82rem;">Cancel</button>
                    <button onclick="window.location.href='GoSort_Settings.php?logout=1'"
                        style="background:linear-gradient(135deg,#368137,#274a17);color:#fff;border:none;
                               border-radius:8px;padding:0.4rem 1rem;font-size:0.82rem;font-weight:600;
                               font-family:'Poppins',sans-serif;cursor:pointer;">
                        Logout
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ════ DELETE ACCOUNT MODAL ════ -->
<div class="modal fade" id="profileDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-exclamation-triangle-fill" style="color:#dc2626;font-size:1.3rem;"></i>
                    <h5 style="font-size:0.95rem;font-weight:700;margin:0;color:#dc2626;">Delete Account</h5>
                </div>
                <p style="font-size:0.82rem;color:#6b7280;margin-bottom:0.5rem;">
                    This is <strong style="color:#1f2937;">permanent and cannot be undone.</strong>
                </p>
                <p style="font-size:0.82rem;color:#6b7280;margin-bottom:0.75rem;">
                    Type <strong style="color:#1f2937;">DELETE</strong> to confirm:
                </p>
                <input type="text" id="deleteConfirmInput"
                    style="font-family:'Poppins',sans-serif;font-size:0.82rem;border:1.5px solid #e5e7eb;
                           border-radius:8px;padding:0.4rem 0.75rem;width:100%;outline:none;"
                    placeholder="Type DELETE">
                <div class="d-flex gap-2 justify-content-end mt-3">
                    <button class="btn btn-light" data-bs-dismiss="modal"
                        style="font-family:'Poppins',sans-serif;font-size:0.82rem;">Cancel</button>
                    <button class="btn btn-danger" id="confirmDeleteAccBtn"
                        onclick="confirmDeleteAccount()" disabled
                        style="font-family:'Poppins',sans-serif;font-size:0.82rem;border-radius:8px;">
                        Delete Account
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const editingFields = new Set();
    let hasUnsaved = false;

    // Original DB values — used for Discard
    const orig = {
        fname: <?= json_encode($p_fname) ?>,
        lname: <?= json_encode($p_lname) ?>,
        email: <?= json_encode($p_email) ?>,
    };

    // ── Dirty / clean ────────────────────────────────────────────────────────
    function markDirty() {
        if (hasUnsaved) return;
        hasUnsaved = true;
        document.getElementById('profileSaveBar').classList.add('visible');
    }
    function markClean() {
        hasUnsaved = false;
        document.getElementById('profileSaveBar').classList.remove('visible');
    }

    // ── Hero initials + name update ──────────────────────────────────────────
    function updateHero() {
        const fn   = document.getElementById('input-fname').value.trim();
        const ln   = document.getElementById('input-lname').value.trim();
        document.getElementById('hero-name').textContent     = (fn + ' ' + ln).trim() || '—';
        document.getElementById('hero-initials').textContent = (fn.charAt(0) + ln.charAt(0)).toUpperCase() || 'U';
    }

    // ── Toggle field edit ────────────────────────────────────────────────────
    window.toggleProfileField = function (field) {
        const display = document.getElementById('display-' + field);
        const input   = document.getElementById('input-' + field);
        const btn     = document.getElementById('btn-' + field);

        if (!editingFields.has(field)) {
            display.style.display = 'none';
            input.classList.add('visible');
            input.focus();
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Done';
            btn.classList.add('active');
            editingFields.add(field);
            markDirty();
        } else {
            const val = input.value.trim();
            display.textContent   = val || '—';
            display.style.display = '';
            input.classList.remove('visible');
            btn.innerHTML = '<i class="bi bi-pencil"></i> Edit';
            btn.classList.remove('active');
            editingFields.delete(field);
            updateHero();
        }
    };

    // ── Save ─────────────────────────────────────────────────────────────────
    window.saveProfileChanges = function () {
        // Close any still-open edit fields
        [...editingFields].forEach(f => {
            document.getElementById('display-' + f).style.display = '';
            document.getElementById('display-' + f).textContent =
                document.getElementById('input-' + f).value.trim() || '—';
            document.getElementById('input-' + f).classList.remove('visible');
            const b = document.getElementById('btn-' + f);
            b.innerHTML = '<i class="bi bi-pencil"></i> Edit';
            b.classList.remove('active');
        });
        editingFields.clear();

        const fname = document.getElementById('input-fname').value.trim();
        const lname = document.getElementById('input-lname').value.trim();
        const email = document.getElementById('input-email').value.trim();

        if (!fname || !lname || !email) {
            showProfileToast('First name, last name and email are required.', 'danger'); return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showProfileToast('Please enter a valid email address.', 'danger'); return;
        }

        const fd = new FormData();
        fd.append('action',   'update_profile');
        fd.append('userName', fname);
        fd.append('lastName', lname);
        fd.append('email',    email);

        fetch(window.location.pathname, {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body:    fd
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                orig.fname = fname;
                orig.lname = lname;
                orig.email = email;
                if (passwordOpen) togglePasswordField();
                updateHero();
                markClean();
                showProfileToast('Profile updated successfully!', 'success');
            } else {
                showProfileToast(data.message || 'Failed to update profile.', 'danger');
            }
        })
        .catch(() => showProfileToast('An error occurred. Please try again.', 'danger'));
    };

    // ── Discard ──────────────────────────────────────────────────────────────
    window.discardProfileChanges = function () {
        ['fname', 'lname', 'email'].forEach(f => {
            const input   = document.getElementById('input-' + f);
            const display = document.getElementById('display-' + f);
            const btn     = document.getElementById('btn-' + f);
            input.value           = orig[f];
            display.textContent   = orig[f] || '—';
            display.style.display = '';
            input.classList.remove('visible');
            btn.innerHTML = '<i class="bi bi-pencil"></i> Edit';
            btn.classList.remove('active');
        });
        editingFields.clear();
        updateHero();
        markClean();
    };

    // ── Theme ────────────────────────────────────────────────────────────────
    window.profileToggleTheme = function () {
        if (window.themeManager) {
            window.themeManager.toggleTheme();
        } else {
            const dark = document.getElementById('theme-toggle').checked;
            document.body.classList.toggle('dark-mode', dark);
            localStorage.setItem('gosort-theme', dark ? 'dark' : 'light');
        }
    };

    // ── Delete modal gate ────────────────────────────────────────────────────
    const delInput = document.getElementById('deleteConfirmInput');
    const delBtn   = document.getElementById('confirmDeleteAccBtn');
    delInput.addEventListener('input', () => { delBtn.disabled = delInput.value !== 'DELETE'; });
    document.getElementById('profileDeleteModal').addEventListener('show.bs.modal', () => {
        delInput.value = ''; delBtn.disabled = true;
    });
    window.confirmDeleteAccount = function () {
        showProfileToast('Account deletion is not yet implemented.', 'warning');
        bootstrap.Modal.getInstance(document.getElementById('profileDeleteModal')).hide();
    };

    // ── Toast ────────────────────────────────────────────────────────────────
    function showProfileToast(msg, type) {
        if (window.showToast) { window.showToast(msg, type); return; }
        const w = document.createElement('div');
        w.className = 'position-fixed bottom-0 end-0 p-3';
        w.style.zIndex = '1070';
        w.innerHTML = `<div class="toast align-items-center text-white bg-${type} border-0 show">
            <div class="d-flex">
                <div class="toast-body" style="font-family:'Poppins',sans-serif;font-size:0.82rem;">${msg}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"></button>
            </div></div>`;
        document.body.appendChild(w);
        setTimeout(() => w.remove(), 4000);
    }

    // ── Init theme toggle state ──────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const saved = window.themeManager?.getCurrentTheme?.()
            || localStorage.getItem('gosort-theme')
            || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        const t = document.getElementById('theme-toggle');
        if (t) t.checked = saved === 'dark';
    });

})();
</script>