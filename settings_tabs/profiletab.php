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

        /* Global Dark Mode Styles - imported from global CSS */
        /* Dark Mode Styles */
        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        body.dark-mode .section-header {
            color: #e0e0e0;
            border-bottom-color: #444;
        }

        body.dark-mode .profile-card {
            background-color: #2d2d2d;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }

        body.dark-mode .profile-header {
            border-bottom-color: #444;
        }

        body.dark-mode .profile-info label {
            color: #b0b0b0;
        }

        body.dark-mode .profile-info .info-row span {
            color: #e0e0e0;
        }

        body.dark-mode .profile-info .info-row {
            border-bottom-color: #444;
        }

        body.dark-mode .form-control {
            background-color: #3a3a3a;
            color: #e0e0e0;
            border-color: #555;
        }

        body.dark-mode .form-control:focus {
            background-color: #3a3a3a;
            color: #e0e0e0;
            border-color: #66bb6a;
            box-shadow: 0 0 0 0.2rem rgba(102, 187, 106, 0.25);
        }

        body.dark-mode .modal-content {
            background-color: #2d2d2d;
            border-color: #444;
        }

        body.dark-mode .modal-header {
            border-bottom-color: #444;
        }

        body.dark-mode .modal-footer {
            border-top-color: #444;
        }

        body.dark-mode .modal-title {
            color: #e0e0e0;
        }

        body.dark-mode .modal-body {
            color: #b0b0b0;
        }

        body.dark-mode .btn-light {
            background-color: #444;
            border-color: #555;
            color: #e0e0e0;
        }

        body.dark-mode .btn-light:hover {
            background-color: #555;
            border-color: #666;
            color: #fff;
        }

        body.dark-mode .btn-close {
            filter: invert(1);
        }

        body.dark-mode h4 {
            color: #e0e0e0;
        }

        body.dark-mode p {
            color: #b0b0b0;
        }

        body.dark-mode .text-muted {
            color: #999 !important;
        }

        body.dark-mode .text-danger {
            color: #ff6b6b !important;
        }

        body.dark-mode .slider {
            background-color: #555;
        }

        body.dark-mode .slider:before {
            background-color: #e0e0e0;
        }

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
        }

        .profile-info .info-row span {
            color: var(--dark-gray);
            font-size: 1rem;
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

            <!-- Password Modal -->
            <div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius: 16px; border: none;">
                        <div class="modal-header">
                            <h5 class="modal-title">Change Password</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Password change form goes here (Current Password, New Password, Confirm New Password).</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" onclick="handlePasswordChange()">Save Password</button>
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
                <h4 class="section-header">Personal Information</h4>

                <div class="info-row">
                    <div>
                        <label>Full Name</label>
                        <span id="name-display"><?= htmlspecialchars($name ?? 'Admin User'); ?></span>
                        <input type="text" id="name-input" class="form-control form-control-sm d-none" value="<?= htmlspecialchars($name ?? ''); ?>">
                    </div>
                    <button class="edit-btn" onclick="toggleEdit('name')">Edit</button>
                </div>

                <div class="info-row">
                    <div>
                        <label>Email</label>
                        <span id="email-display"><?= htmlspecialchars($email ?? 'admin@example.com'); ?></span>
                        <input type="email" id="email-input" class="form-control form-control-sm d-none" value="<?= htmlspecialchars($email ?? ''); ?>">
                    </div>
                    <button class="edit-btn" onclick="toggleEdit('email')">Edit</button>
                </div>

                <div class="info-row">
                    <div>
                        <label>Contact</label>
                        <span id="contact-display"><?= htmlspecialchars($contact ?? 'N/A'); ?></span>
                        <input type="text" id="contact-input" class="form-control form-control-sm d-none" value="<?= htmlspecialchars($contact ?? ''); ?>">
                    </div>
                    <button class="edit-btn" onclick="toggleEdit('contact')">Edit</button>
                </div>

                <div class="info-row">
                    <div>
                        <label>Role</label>
                        <span><?= htmlspecialchars($role ?? 'Administrator'); ?></span>
                    </div>
                </div>

                <!-- Security Section -->
                <h4 class="section-header">Security</h4>

                <div class="info-row">
                    <div>
                        <label>Password</label>
                        <span>******</span>
                    </div>
                    <button class="edit-btn" data-bs-toggle="modal" data-bs-target="#passwordModal">Change Password</button>
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
        const passwordModalInstance = new bootstrap.Modal(document.getElementById('passwordModal'));
        const logoutModalInstance = new bootstrap.Modal(document.getElementById('logoutModal'));
        const deleteAccountModalInstance = new bootstrap.Modal(document.getElementById('deleteAccountModal'));

        function toggleEdit(field) {
            const displayEl = document.getElementById(`${field}-display`);
            const inputEl = document.getElementById(`${field}-input`);
            const editBtn = event.currentTarget;

            if (inputEl.classList.contains('d-none')) {
                displayEl.classList.add('d-none');
                inputEl.classList.remove('d-none');
                editBtn.textContent = 'Save';
                editBtn.classList.add('save-btn');
                editBtn.classList.remove('edit-btn');
                editBtn.dataset.mode = 'editing';
            } else if (editBtn.dataset.mode === 'editing') {
                window.currentField = field;
                window.currentInput = inputEl;
                window.currentDisplay = displayEl;
                window.currentButton = editBtn;
                profileConfirmationModal.show();
            }
        }

        function confirmSave() {
            currentDisplay.textContent = currentInput.value;
            currentDisplay.classList.remove('d-none');
            currentInput.classList.add('d-none');
            currentButton.textContent = 'Edit';
            currentButton.classList.remove('save-btn');
            currentButton.classList.add('edit-btn');
            currentButton.dataset.mode = '';
            profileConfirmationModal.hide();

            console.log(`Confirmed save for ${currentField}: ${currentInput.value}`);
        }

        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-pic').src = e.target.result;
                };
                reader.readAsDataURL(file);
                console.log("Previewing new profile image:", file.name);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // If global theme manager exists, use it; otherwise use local storage
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
            // Use global theme manager if available
            if (window.themeManager) {
                window.themeManager.toggleTheme();
            } else {
                // Fallback to local storage
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
            console.log("Theme toggled.");
        }

        function handlePasswordChange() {
            console.log("Password change initiated.");
            passwordModalInstance.hide();
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