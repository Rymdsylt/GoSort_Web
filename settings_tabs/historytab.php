<?php
//TO UPDATE BACKEND AND STATIC TEXTS
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
            font-family: 'Poppins', sans-serif;
        }

        .content-area {
            padding: 0 0 2rem;
            overflow-y: auto;
        }

        .log-card {
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .section-header {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 1.2rem;
            border-bottom: 2px solid var(--primary-green);
            padding-bottom: 6px;
        }

        .btn-main {
            border-radius: 10px;
            margin-right: 6px;
            font-weight: 600;
            transition: background-color 0.2s ease;
        }

        .btn-main.active {
            background-color: var(--primary-green);
            color: #fff;
        }

        .btn-sub {
            border-radius: 8px;
            margin-right: 5px;
            font-weight: 500;
        }

        .table thead {
            background-color: var(--light-green);
            color: #fff;
        }

        .table td, .table th {
            vertical-align: middle;
        }

        .empty-state {
            text-align: center;
            color: var(--medium-gray);
            padding: 2rem;
        }

        .empty-state i {
            font-size: 2rem;
            color: var(--light-green);
            display: block;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
<div class="content-area">
    <div class="log-card">
        <div class="section-header">Activity Logs</div>
        <p class="text-secondary mb-4">
            View all user, device, analytics, and maintenance activities in your GoSort system. 
            Select a category below to view detailed history.
        </p>

        <!-- MAIN CATEGORY BUTTONS -->
        <div id="mainButtons" class="mb-3">
            <button class="btn btn-outline-success btn-main active" data-category="devices">Devices</button>
            <button class="btn btn-outline-success btn-main" data-category="analytics">Analytics</button>
            <button class="btn btn-outline-success btn-main" data-category="maintenance">Maintenance</button>
            <button class="btn btn-outline-success btn-main" data-category="general">General</button>
        </div>

        <!-- SUBTOPIC BUTTONS (changes dynamically) -->
        <div id="subButtons" class="mb-3"></div>

        <!-- TABLE CONTENT -->
        <div id="logTableContainer"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const subtopics = {
        devices: ["Added Devices", "Deleted Devices", "Updated Devices"],
        analytics: ["Sorting History", "Online Activity"],
        maintenance: ["Device Mapping", "Testing", "Controls"],
        general: ["Login History", "Notifications", "User Activity"]
    };

    const mainButtons = document.querySelectorAll('#mainButtons button');
    const subButtonsContainer = document.getElementById('subButtons');
    const logTableContainer = document.getElementById('logTableContainer');

    function renderSubtopics(category) {
        subButtonsContainer.innerHTML = '';
        subtopics[category].forEach((topic, i) => {
            const btn = document.createElement('button');
            btn.className = `btn btn-outline-secondary btn-sub ${i === 0 ? 'active' : ''}`;
            btn.textContent = topic;
            btn.addEventListener('click', () => {
                document.querySelectorAll('#subButtons button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                renderTable(category, topic);
            });
            subButtonsContainer.appendChild(btn);
        });
        renderTable(category, subtopics[category][0]); // Default to first subtopic
    }

    function renderTable(category, topic) {
        let tableHTML = `
            <div class="card p-3 shadow-sm">
                <h6 class="fw-bold text-success mb-3">${category.toUpperCase()} → ${topic}</h6>
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User/Device</th>
                            <th>Action</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>2025-10-14 10:00 AM</td>
                            <td>Admin</td>
                            <td>${topic}</td>
                            <td>Sample data for ${topic}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;

        // Placeholder if no data (you can connect this to your DB later)
        if (!tableHTML) {
            tableHTML = `
                <div class="empty-state">
                    <i class="bi bi-clipboard-x"></i>
                    <p>No logs available for ${topic} yet.</p>
                </div>
            `;
        }

        logTableContainer.innerHTML = tableHTML;
    }

    // Handle main category switching
    mainButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            mainButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderSubtopics(btn.dataset.category);
        });
    });

    // Initialize with Devices category
    renderSubtopics('devices');
</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</body>
</html>
