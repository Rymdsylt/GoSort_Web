<?php
//Activity Logs - Connected to Database
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

        .loading-spinner {
            text-align: center;
            padding: 2rem;
        }

        .loading-spinner .spinner-border {
            color: var(--primary-green);
        }

        .badge-action {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .badge-login { background-color: #10b981; color: white; }
        .badge-logout { background-color: #6b7280; color: white; }
        .badge-maintenance { background-color: #f59e0b; color: white; }
        .badge-device { background-color: #3b82f6; color: white; }
        .badge-notification { background-color: #8b5cf6; color: white; }
        .badge-user { background-color: #ec4899; color: white; }
        .badge-default { background-color: #64748b; color: white; }

        .refresh-btn {
            background: none;
            border: none;
            color: var(--primary-green);
            cursor: pointer;
            font-size: 1rem;
            padding: 5px 10px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }

        .refresh-btn:hover {
            background-color: rgba(46, 125, 50, 0.1);
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Dark mode styles are handled by css/dark-mode-global.css */
    </style>
</head>
<body>
<div class="content-area">
    <div class="log-card">
        <div class="section-header d-flex justify-content-between align-items-center">
            <span>Activity Logs</span>
            <div class="header-controls">
                <button class="refresh-btn" onclick="refreshLogs()" title="Refresh logs">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>
        <p class="text-secondary mb-4">
            View all user, device, analytics, and maintenance activities in your GoSort system. 
            Select a category below to view detailed history.
        </p>

        <!-- MAIN CATEGORY BUTTONS -->
        <div id="mainButtons" class="mb-3">
            <button class="btn btn-outline-success btn-main active" data-category="all">All</button>
            <button class="btn btn-outline-success btn-main" data-category="devices">Devices</button>
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
        all: [],
        devices: ["Added Devices", "Deleted Devices", "Updated Devices"],
        analytics: ["Sorting History", "Online Activity"],
        maintenance: ["Device Mapping", "Testing", "Controls"],
        general: ["Login History", "Notifications", "User Activity"]
    };

    let currentCategory = 'all';
    let currentSubtopic = null;

    const mainButtons = document.querySelectorAll('#mainButtons button');
    const subButtonsContainer = document.getElementById('subButtons');
    const logTableContainer = document.getElementById('logTableContainer');

    function getActionBadgeClass(action) {
        action = action.toLowerCase();
        if (action.includes('login')) return 'badge-login';
        if (action.includes('logout')) return 'badge-logout';
        if (action.includes('maintenance')) return 'badge-maintenance';
        if (action.includes('device')) return 'badge-device';
        if (action.includes('notification')) return 'badge-notification';
        if (action.includes('user')) return 'badge-user';
        return 'badge-default';
    }

    function renderSubtopics(category) {
        currentCategory = category;
        subButtonsContainer.innerHTML = '';
        
        // If "All" category, no subtopics needed
        if (category === 'all') {
            currentSubtopic = null;
            fetchAllLogs();
            return;
        }
        
        subtopics[category].forEach((topic, i) => {
            const btn = document.createElement('button');
            btn.className = `btn btn-outline-secondary btn-sub ${i === 0 ? 'active' : ''}`;
            btn.textContent = topic;
            btn.addEventListener('click', () => {
                document.querySelectorAll('#subButtons button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentSubtopic = topic;
                fetchAndRenderLogs(category, topic);
            });
            subButtonsContainer.appendChild(btn);
        });
        currentSubtopic = subtopics[category][0];
        fetchAndRenderLogs(category, subtopics[category][0]);
    }

    function fetchAllLogs() {
        showLoading();
        
        fetch('api/activity_logs_api.php?all=1')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderTableAll(data.logs);
                } else {
                    renderError(data.message || 'Failed to fetch logs');
                }
            })
            .catch(error => {
                console.error('Error fetching logs:', error);
                renderError('Network error. Please try again.');
            });
    }

    function showLoading() {
        logTableContainer.innerHTML = `
            <div class="loading-spinner">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading activity logs...</p>
            </div>
        `;
    }

    function fetchAndRenderLogs(category, topic) {
        showLoading();
        
        fetch(`api/activity_logs_api.php?category=${encodeURIComponent(category)}&subtopic=${encodeURIComponent(topic)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderTable(category, topic, data.logs);
                } else {
                    renderError(data.message || 'Failed to fetch logs');
                }
            })
            .catch(error => {
                console.error('Error fetching logs:', error);
                renderError('Network error. Please try again.');
            });
    }

    function renderTable(category, topic, logs) {
        let tableHTML = `
            <div class="card p-3 shadow-sm">
                <h6 class="fw-bold text-success mb-3">${category.toUpperCase()} → ${topic}</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 160px;">Date</th>
                                <th style="width: 120px;">User</th>
                                <th style="width: 120px;">Device</th>
                                <th style="width: 140px;">Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        if (logs && logs.length > 0) {
            logs.forEach(log => {
                const badgeClass = getActionBadgeClass(log.action);
                tableHTML += `
                    <tr>
                        <td><small>${log.date}</small></td>
                        <td>${log.user || '-'}</td>
                        <td>${log.device || '-'}</td>
                        <td><span class="badge badge-action ${badgeClass}">${log.action}</span></td>
                        <td><small class="text-muted">${log.details || '-'}</small></td>
                    </tr>
                `;
            });
        } else {
            tableHTML += `
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <i class="bi bi-clipboard-x"></i>
                            <p>No activity logs found for ${topic}.</p>
                        </div>
                    </td>
                </tr>
            `;
        }

        tableHTML += `
                        </tbody>
                    </table>
                </div>
                <div class="text-muted small mt-2">
                    <i class="bi bi-info-circle"></i> Showing ${logs ? logs.length : 0} records
                </div>
            </div>
        `;

        logTableContainer.innerHTML = tableHTML;
    }

    function renderTableAll(logs) {
        let tableHTML = `
            <div class="card p-3 shadow-sm">
                <h6 class="fw-bold text-success mb-3">ALL ACTIVITY LOGS</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 160px;">Date</th>
                                <th style="width: 100px;">Category</th>
                                <th style="width: 120px;">User</th>
                                <th style="width: 120px;">Device</th>
                                <th style="width: 140px;">Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        if (logs && logs.length > 0) {
            logs.forEach(log => {
                const badgeClass = getActionBadgeClass(log.action);
                const categoryBadge = getCategoryBadgeClass(log.category);
                tableHTML += `
                    <tr>
                        <td><small>${log.date}</small></td>
                        <td><span class="badge ${categoryBadge}">${log.category}</span></td>
                        <td>${log.user || '-'}</td>
                        <td>${log.device || '-'}</td>
                        <td><span class="badge badge-action ${badgeClass}">${log.action}</span></td>
                        <td><small class="text-muted">${log.details || '-'}</small></td>
                    </tr>
                `;
            });
        } else {
            tableHTML += `
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="bi bi-clipboard-x"></i>
                            <p>No activity logs found.</p>
                        </div>
                    </td>
                </tr>
            `;
        }

        tableHTML += `
                        </tbody>
                    </table>
                </div>
                <div class="text-muted small mt-2">
                    <i class="bi bi-info-circle"></i> Showing ${logs ? logs.length : 0} records
                </div>
            </div>
        `;

        logTableContainer.innerHTML = tableHTML;
    }

    function getCategoryBadgeClass(category) {
        switch(category) {
            case 'devices': return 'bg-primary';
            case 'analytics': return 'bg-info';
            case 'maintenance': return 'bg-warning text-dark';
            case 'general': return 'bg-secondary';
            default: return 'bg-secondary';
        }
    }

    function renderError(message) {
        logTableContainer.innerHTML = `
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                ${message}
            </div>
        `;
    }

    function refreshLogs() {
        if (currentCategory === 'all') {
            fetchAllLogs();
        } else {
            fetchAndRenderLogs(currentCategory, currentSubtopic);
        }
    }

    // Handle main category switching
    mainButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            mainButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderSubtopics(btn.dataset.category);
        });
    });

    // Initialize with All category
    renderSubtopics('all');
</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</body>
</html>
