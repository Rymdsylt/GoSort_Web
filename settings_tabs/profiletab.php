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
        }

        .section-header {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #0a0a0aff;
        }

        /* Dark mode styles are handled by css/dark-mode-global.css */

        .theme-switch-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .theme-switch {
            display: inline-block;
            height: 25px;
            position: relative;
            width: 45px;
        }

        .theme-switch input {
            display: none;
        }

        .slider {
            background-color: #ccc;
            bottom: 0;
            cursor: pointer;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
            transition: 0.4s;
        }

        .slider:before {
            background-color: #fff;
            bottom: 4px;
            content: "";
            height: 17px;
            left: 4px;
            position: absolute;
            transition: 0.4s;
            width: 17px;
        }

        input:checked + .slider {
            background-color: var(--primary-green);
        }

        input:checked + .slider:before {
            transform: translateX(20px);
        }

        .slider.round {
            border-radius: 34px;
        }

        .slider.round:before {
            border-radius: 50%;
        }

        .settings-content-area {
            overflow-y: auto;
            padding-right: 15px;
        }

        .profile-card {
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            border-bottom: 1.5px solid #e5e7eb;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .profile-pic-container {
            position: relative;
            display: inline-block;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--light-green);
            box-sizing: border-box;
        }

        .profile-pic-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: opacity 0.3s ease;
        }

        .change-photo-btn {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.6);
            color: #fff;
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            opacity: 0;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }

        .profile-pic-container:hover .change-photo-btn {
            opacity: 1;
        }

        .profile-info label {
            font-weight: 600;
            color: var(--medium-gray);
            display: block;
            margin-bottom: 0.2rem;
        }

        .profile-info .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
            padding: 0.5rem 0.6rem;
            border-radius: 10px;
            transition: background 0.25s ease;
        }

        .profile-info .info-row > div:first-child {
            flex: 1;
            min-width: 0;
        }

        .profile-info .info-row.editing {
            background: rgba(46, 125, 50, 0.04);
        }

        .profile-info .info-row span {
            color: var(--dark-gray);
            font-size: 1rem;
        }

        .profile-info .info-row .form-control {
            border-radius: 10px;
            border: 1.5px solid #d1d5db;
            padding: 0.45rem 0.75rem;
            font-size: 0.92rem;
            transition: all 0.25s ease;
            animation: fadeInInput 0.25s ease;
        }

        .profile-info .info-row .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.12);
        }

        @keyframes fadeInInput {
            from { opacity: 0; transform: translateY(-4px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .edit-btn {
            background: transparent;
            border: none;
            color: var(--primary-green);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .edit-btn:hover {
            color: var(--light-green);
        }

        .edit-actions {
            display: flex;
            gap: 0.4rem;
            align-items: center;
            animation: fadeInInput 0.25s ease;
        }

        .section-edit-actions {
            display: none;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #f0f0f0;
            animation: fadeInInput 0.25s ease;
        }

        .save-btn {
            background: var(--primary-green);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .save-btn:hover {
            background: #1f3a13;
        }

        .cancel-btn {
            background: transparent;
            border: 1.5px solid #d1d5db;
            color: var(--medium-gray);
            border-radius: 8px;
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .cancel-btn:hover {
            border-color: #9ca3af;
            color: var(--dark-gray);
        }

        .password-fields {
            display: none;
            animation: fadeInInput 0.25s ease;
        }

        .password-fields .info-row {
            margin-bottom: 0.8rem;
        }

        .password-fields .input-group .form-control {
            border-radius: 10px 0 0 10px;
            border: 1.5px solid #d1d5db;
            padding: 0.45rem 0.75rem;
            font-size: 0.92rem;
        }

        .password-fields .input-group .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.12);
        }

        .password-fields .input-group .btn {
            border-radius: 0 10px 10px 0;
            border: 1.5px solid #d1d5db;
            border-left: none;
        }

        /* ── Responsive / Adaptive ── */
        @media (max-width: 768px) {
            .profile-card {
                padding: 1.2rem;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 0.8rem;
            }

            .profile-pic-container {
                width: 75px;
                height: 75px;
            }

            .section-header {
                font-size: 1rem;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .profile-info .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.4rem;
                padding: 0.4rem 0.4rem;
            }

            .section-edit-actions {
                flex-direction: column;
                gap: 0.4rem;
            }

            .section-edit-actions .cancel-btn,
            .section-edit-actions .save-btn {
                width: 100%;
                text-align: center;
            }

            .edit-btn {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .profile-card {
                padding: 1rem;
                border-radius: 12px;
            }

            .profile-pic-container {
                width: 65px;
                height: 65px;
            }

            .profile-header h4 {
                font-size: 1.1rem;
            }

            .profile-info label {
                font-size: 0.85rem;
            }

            .profile-info .info-row span {
                font-size: 0.9rem;
            }

            .theme-switch-wrapper {
                margin-top: 0.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="settings-content-area">
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-pic-container">
                    <img id="profile-pic" src="images/icons/default_profile.svg" alt="Profile Picture">
                    <label for="profile-upload" class="change-photo-btn">Change Photo</label>
                    <input type="file" id="profile-upload" accept="image/*" class="d-none" onchange="previewImage(event)">
                </div>
                <div>
                    <h4 class="fw-bold mb-1" style="color: var(--dark-gray);">
                        <?= htmlspecialchars($name ?? 'Admin User'); ?>
                    </h4>
                    <p class="text-muted mb-0"><?= htmlspecialchars($role ?? 'Administrator'); ?></p>
                </div>
            </div>

            <!-- Profile Confirmation Modal -->
            <div class="modal fade" id="profileConfirmationModal" tabindex="-1" aria-labelledby="profileConfirmationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius: 16px; border: none;">
                        <div class="modal-header" style="border-bottom: none;">
                            <h5 class="modal-title fw-semibold" style="color: var(--dark-gray);">Save Changes?</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" style="color: var(--medium-gray);">
                            Do you want to save the changes you made?
                        </div>
                        <div class="modal-footer" style="border-top: none;">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 8px; border: 1px solid #d1d5db; color: var(--dark-gray); font-weight: 500;">
                                Discard
                            </button>
                            <button type="button" class="btn" style="background: var(--primary-green); color: white; border-radius: 8px; font-weight: 600;" onclick="confirmSave()">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>



            <!-- Logout Modal -->
            <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius: 16px; border: none;">
                        <div class="modal-header" style="border-bottom: none;">
                            <h5 class="modal-title fw-semibold" style="color: var(--dark-gray);">Confirm Logout</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" style="color: var(--medium-gray);">
                            Are you sure you want to log out?
                        </div>
                        <div class="modal-footer" style="border-top: none;">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 8px; border: 1px solid #d1d5db; color: var(--dark-gray); font-weight: 500;">
                                Cancel
                            </button>
                            <button type="button" class="btn" style="background: var(--primary-green); color: white; border-radius: 8px; font-weight: 600;" onclick="handleLogout()">
                                Logout
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Account Modal -->
            <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-danger" style="border-radius: 16px;">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Confirm Account Deletion</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Warning:</strong> This action cannot be undone. Are you absolutely sure you want to delete your account?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" onclick="handleDeleteAccount()">Delete Account</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Information Section -->
            <div class="profile-info">
                <h4 class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
                    Personal Information
                    <button class="edit-btn" id="profile-edit-btn" onclick="enterProfileEdit()">Edit</button>
                </h4>

                <div class="section-edit-actions" id="profile-edit-actions">
                    <button class="cancel-btn" onclick="cancelProfileEdit()">Cancel</button>
                    <button class="save-btn" onclick="saveProfileEdit()">Save Changes</button>
                </div>

                <div class="info-row" id="name-row">
                    <div>
                        <label>Full Name</label>
                        <span id="name-display"><?= htmlspecialchars($name ?? 'Admin User'); ?></span>
                        <input type="text" id="name-input" class="form-control d-none" value="<?= htmlspecialchars($name ?? ''); ?>" placeholder="Enter your full name">
                    </div>
                </div>

                <div class="info-row" id="email-row">
                    <div>
                        <label>Email</label>
                        <span id="email-display"><?= htmlspecialchars($email ?? 'admin@example.com'); ?></span>
                        <input type="email" id="email-input" class="form-control d-none" value="<?= htmlspecialchars($email ?? ''); ?>" placeholder="Enter your email">
                    </div>
                </div>

                <div class="info-row" id="contact-row">
                    <div>
                        <label>Contact</label>
                        <span id="contact-display"><?= htmlspecialchars($contact ?? 'N/A'); ?></span>
                        <input type="text" id="contact-input" class="form-control d-none" value="<?= htmlspecialchars($contact ?? ''); ?>" placeholder="Enter your contact number">
                    </div>
                </div>

                <div class="info-row">
                    <div>
                        <label>Role</label>
                        <span><?= htmlspecialchars($role ?? 'Administrator'); ?></span>
                    </div>
                </div>

                <!-- Security Section -->
                <h4 class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
                    Security
                    <button class="edit-btn" id="password-edit-btn" onclick="enterPasswordEdit()">Change Password</button>
                </h4>

                <div class="info-row" id="password-display-row">
                    <div>
                        <label>Password</label>
                        <span>******</span>
                    </div>
                </div>

                <div class="password-fields" id="password-fields">
                    <div class="info-row">
                        <div style="width: 100%;">
                            <label>Current Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="currentPassword" placeholder="Enter current password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePwdVis('currentPassword', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div style="width: 100%;">
                            <label>New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="newPassword" placeholder="Enter new password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePwdVis('newPassword', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div style="width: 100%;">
                            <label>Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirmPassword" placeholder="Confirm new password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePwdVis('confirmPassword', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="section-edit-actions" style="display: flex;">
                        <button class="cancel-btn" onclick="cancelPasswordEdit()">Cancel</button>
                        <button class="save-btn" onclick="savePasswordEdit()">Save Password</button>
                    </div>
                </div>

                <!-- Preferences & Actions Section -->
                <h4 class="section-header">Preferences & Actions</h4>

                <div class="info-row">
                    <div>
                        <label>Dark Mode</label>
                        <span>Toggle between light and dark themes</span>
                    </div>
                    <div class="theme-switch-wrapper">
                        <label class="theme-switch">
                            <input type="checkbox" id="theme-toggle" onchange="toggleTheme()">
                            <span class="slider round"></span>
                        </label>
                    </div>
                </div>

                <div class="info-row">
                    <div>
                        <label>Session</label>
                        <span>Securely log out of your account</span>
                    </div>
                    <button class="edit-btn" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>
                </div>

                <div class="info-row">
                    <div>
                        <label class="text-danger">Account Actions</label>
                        <span class="text-danger">Permanently delete your account</span>
                    </div>
                    <button class="edit-btn text-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">Delete Account</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const profileConfirmationModal = new bootstrap.Modal(document.getElementById('profileConfirmationModal'));
        const logoutModalInstance = new bootstrap.Modal(document.getElementById('logoutModal'));
        const deleteAccountModalInstance = new bootstrap.Modal(document.getElementById('deleteAccountModal'));

        // ── Personal Information Edit ──
        let originalProfileValues = {};
        const profileFields = ['name', 'email', 'contact'];

        function enterProfileEdit() {
            // Store originals and switch all fields to edit mode
            profileFields.forEach(field => {
                const display = document.getElementById(`${field}-display`);
                const input = document.getElementById(`${field}-input`);
                const row = document.getElementById(`${field}-row`);
                originalProfileValues[field] = display.textContent;
                input.value = display.textContent;
                display.classList.add('d-none');
                input.classList.remove('d-none');
                row.classList.add('editing');
            });
            document.getElementById('profile-edit-btn').classList.add('d-none');
            document.getElementById('profile-edit-actions').style.display = 'flex';
            // Auto-close password edit if open
            if (document.getElementById('password-fields').style.display === 'block') {
                cancelPasswordEdit();
            }
            setTimeout(() => document.getElementById('name-input').focus(), 50);
        }

        function cancelProfileEdit() {
            profileFields.forEach(field => {
                const display = document.getElementById(`${field}-display`);
                const input = document.getElementById(`${field}-input`);
                const row = document.getElementById(`${field}-row`);
                input.value = originalProfileValues[field] || '';
                display.classList.remove('d-none');
                input.classList.add('d-none');
                row.classList.remove('editing');
            });
            document.getElementById('profile-edit-btn').classList.remove('d-none');
            document.getElementById('profile-edit-actions').style.display = 'none';

        }

        function saveProfileEdit() {
            profileConfirmationModal.show();
        }

        function confirmSave() {
            profileFields.forEach(field => {
                const display = document.getElementById(`${field}-display`);
                const input = document.getElementById(`${field}-input`);
                const row = document.getElementById(`${field}-row`);
                display.textContent = input.value || (field === 'contact' ? 'N/A' : '');
                display.classList.remove('d-none');
                input.classList.add('d-none');
                row.classList.remove('editing');
            });
            document.getElementById('profile-edit-btn').classList.remove('d-none');
            document.getElementById('profile-edit-actions').style.display = 'none';
            profileConfirmationModal.hide();
            console.log('Profile saved:', {
                name: document.getElementById('name-display').textContent,
                email: document.getElementById('email-display').textContent,
                contact: document.getElementById('contact-display').textContent
            });
        }

        // ── Password Edit ──
        function enterPasswordEdit() {
            document.getElementById('password-edit-btn').classList.add('d-none');
            document.getElementById('password-display-row').classList.add('d-none');
            document.getElementById('password-fields').style.display = 'block';
            // Auto-close profile edit if open
            if (document.getElementById('profile-edit-actions').style.display === 'flex') {
                cancelProfileEdit();
            }
            setTimeout(() => document.getElementById('currentPassword').focus(), 50);
        }

        function cancelPasswordEdit() {
            document.getElementById('password-edit-btn').classList.remove('d-none');
            document.getElementById('password-display-row').classList.remove('d-none');
            document.getElementById('password-fields').style.display = 'none';
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';

        }

        function savePasswordEdit() {
            const current = document.getElementById('currentPassword').value;
            const newPwd = document.getElementById('newPassword').value;
            const confirm = document.getElementById('confirmPassword').value;

            if (!current || !newPwd || !confirm) {
                alert('Please fill in all password fields.');
                return;
            }
            if (newPwd !== confirm) {
                alert('New password and confirmation do not match.');
                return;
            }
            // TODO: send to backend
            console.log('Password change submitted.');
            cancelPasswordEdit();
        }

        function togglePwdVis(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }

        // ── Image preview ──
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-pic').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }

        // ── Theme ──
        document.addEventListener('DOMContentLoaded', () => {
            if (window.themeManager) {
                const currentTheme = window.themeManager.getCurrentTheme();
                document.getElementById('theme-toggle').checked = currentTheme === 'dark';
            } else {
                const savedTheme = localStorage.getItem('gosort-theme') || localStorage.getItem('theme') ||
                                  (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                if (savedTheme === 'dark') {
                    document.body.classList.add('dark-mode');
                    document.getElementById('theme-toggle').checked = true;
                }
            }
        });

        function toggleTheme() {
            if (window.themeManager) {
                window.themeManager.toggleTheme();
            } else {
                if (document.getElementById('theme-toggle').checked) {
                    document.body.classList.add('dark-mode');
                    localStorage.setItem('gosort-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark-mode');
                    localStorage.setItem('gosort-theme', 'light');
                    localStorage.setItem('theme', 'light');
                }
            }
        }

        function handleLogout() {
            logoutModalInstance.hide();
            window.location.href = 'GoSort_Sorters.php?logout=1';
        }

        function handleDeleteAccount() {
            console.log("Account deletion confirmed.");
            deleteAccountModalInstance.hide();
        }
    </script>
</body>
</html>