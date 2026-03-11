<?php
// Activity Logs - Connected to Database
require_once __DIR__ . '/../gs_DB/connection.php';

// Always fetch everything - JS handles category filtering client-side
$user_actions = ['Login', 'Logout', 'Login Failed', 'User Added', 'User Deleted', 'User Updated', 'Profile Updated'];
$user_actions_str = implode("','", array_map('addslashes', $user_actions));

try {
    $sql = "SELECT id, category, action, details, username, device_name, created_at,
            CASE
                WHEN category IN ('devices','maintenance') THEN category
                WHEN action IN ('$user_actions_str') THEN 'users'
                ELSE 'general'
            END AS js_category
            FROM activity_logs
            ORDER BY created_at DESC LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $logs = [];
    $fetch_error = $e->getMessage();
}

// ── Badge helpers ─────────────────────────────────────────────────────────────
function getActionBadge($action) {
    $a = strtolower($action ?? '');
    if (str_contains($a, 'login') && !str_contains($a, 'failed')) return ['login',       '#dcfce7','#15803d'];
    if (str_contains($a, 'failed'))                                 return ['failed',      '#fee2e2','#dc2626'];
    if (str_contains($a, 'logout'))                                 return ['logout',      '#f3f4f6','#4b5563'];
    if (str_contains($a, 'added'))                                  return ['added',       '#dbeafe','#1d4ed8'];
    if (str_contains($a, 'deleted') || str_contains($a, 'removed'))return ['deleted',     '#fee2e2','#dc2626'];
    if (str_contains($a, 'updated') || str_contains($a, 'modified'))return ['updated',    '#fef9c3','#854d0e'];
    if (str_contains($a, 'maintenance'))                            return ['maintenance', '#fef3c7','#92400e'];
    if (str_contains($a, 'test') || str_contains($a, 'mapping'))   return ['maintenance', '#fef3c7','#92400e'];
    if (str_contains($a, 'notification'))                           return ['notif',       '#ede9fe','#5b21b6'];
    return ['general', '#f1f5f9','#475569'];
}

function getCatBadge($cat) {
    switch ($cat) {
        case 'devices':     return ['#dbeafe','#1d4ed8'];
        case 'maintenance': return ['#fef3c7','#92400e'];
        case 'general':     return ['#ede9fe','#5b21b6'];
        default:            return ['#f1f5f9','#475569'];
    }
}
?>

<div class="section-block">
    <div class="section-label">Activity Logs</div>

    <div class="inner-card">
        <!-- Header -->
        <div class="inner-card-header">
            <div class="inner-card-title">
                <i class="bi bi-clock-history me-2" style="color:var(--primary-green);"></i>System Activity
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="search-wrap">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="logSearch" placeholder="Search logs…" oninput="filterLogs(this.value)">
                </div>
                <button class="btn-outline-green" onclick="exportLogsCSV()">
                    <i class="bi bi-download"></i> Export CSV
                </button>
            </div>
        </div>

        <!-- Category tabs -->
        <div class="log-tabs mb-3">
            <button class="log-tab-btn active" data-cat="all"          onclick="switchLogCat(this)"><i class="bi bi-list-ul"></i> All</button>
            <button class="log-tab-btn"        data-cat="devices"      onclick="switchLogCat(this)"><i class="bi bi-cpu"></i> Devices</button>
            <button class="log-tab-btn"        data-cat="maintenance"  onclick="switchLogCat(this)"><i class="bi bi-tools"></i> Maintenance</button>
            <button class="log-tab-btn"        data-cat="users"        onclick="switchLogCat(this)"><i class="bi bi-people"></i> Users</button>
        </div>

        <?php if (!empty($fetch_error)): ?>
        <div style="padding:1rem;background:#fee2e2;border-radius:8px;font-size:0.8rem;color:#dc2626;">
            <i class="bi bi-exclamation-circle me-1"></i><?php echo htmlspecialchars($fetch_error); ?>
        </div>
        <?php elseif (empty($logs)): ?>
        <div style="text-align:center;padding:2.5rem;color:var(--medium-gray);">
            <i class="bi bi-clipboard-x" style="font-size:2rem;display:block;margin-bottom:0.5rem;color:#c8e6c9;"></i>
            <span style="font-size:0.82rem;">No activity logs found.</span>
        </div>
        <?php else: ?>

        <div style="overflow-x:auto;">
            <table id="logTable" style="width:100%;border-collapse:collapse;font-size:0.8rem;font-family:'Poppins',sans-serif;">
                <thead>
                    <tr>
                        <th class="log-th" style="width:150px;">Date &amp; Time</th>
                        <th class="log-th log-col-cat" style="width:110px;">Category</th>
                        <th class="log-th" style="width:180px;">User</th>
                        <th class="log-th" style="width:170px;white-space:nowrap;">Action</th>
                        <th class="log-th log-col-device" style="width:120px;">Device</th>
                        <th class="log-th">Details</th>
                    </tr>
                </thead>
                <tbody id="logTbody">
                <?php foreach ($logs as $log):
                    [$badgeKey, $badgeBg, $badgeColor] = getActionBadge($log['action']);
                    $date = $log['created_at'] ? date('M d, Y g:i A', strtotime($log['created_at'])) : '—';
                    $user = htmlspecialchars($log['username'] ?? '—');
                    $action = htmlspecialchars($log['action'] ?? '—');
                    $details = htmlspecialchars($log['details'] ?? '—');
                    $device = htmlspecialchars($log['device_name'] ?? '—');
                    $cat = $log['category'] ?? 'general';
                    $jsCat = htmlspecialchars($log['js_category'] ?? 'general');
                    [$catBg, $catColor] = getCatBadge($cat);
                ?>
                <tr class="log-row" data-cat="<?php echo $jsCat; ?>">
                    <td class="log-td" style="color:var(--medium-gray);font-size:0.74rem;white-space:nowrap;"><?php echo $date; ?></td>
                    <td class="log-td log-col-cat">
                        <span style="display:inline-flex;align-items:center;padding:0.15rem 0.5rem;border-radius:20px;font-size:0.67rem;font-weight:600;background:<?php echo $catBg; ?>;color:<?php echo $catColor; ?>;">
                            <?php echo ucfirst($cat); ?>
                        </span>
                    </td>
                    <td class="log-td" style="font-weight:600;"><?php echo $user; ?></td>
                    <td class="log-td" style="white-space:nowrap;">
                        <span style="display:inline-flex;align-items:center;padding:0.18rem 0.55rem;border-radius:20px;font-size:0.68rem;font-weight:600;white-space:nowrap;background:<?php echo $badgeBg; ?>;color:<?php echo $badgeColor; ?>;">
                            <?php echo $action; ?>
                        </span>
                    </td>
                    <td class="log-td log-col-device" style="color:var(--medium-gray);"><?php echo $device; ?></td>
                    <td class="log-td" style="color:var(--medium-gray);font-size:0.78rem;"><?php echo $details; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:0.9rem;flex-wrap:wrap;gap:0.5rem;">
            <div style="font-size:0.74rem;color:var(--medium-gray);">
                <i class="bi bi-info-circle me-1"></i>
                Showing <span id="logRangeStart">1</span>–<span id="logRangeEnd">30</span>
                of <span id="logTotal"><?php echo count($logs); ?></span> records
            </div>
            <div style="display:flex;align-items:center;gap:0.4rem;" id="paginationControls">
                <button class="btn-outline-green" id="prevPageBtn" onclick="changePage(-1)" style="padding:0.3rem 0.7rem;font-size:0.75rem;" disabled>
                    <i class="bi bi-chevron-left"></i>
                </button>
                <span id="pageIndicator" style="font-size:0.78rem;font-weight:600;color:var(--dark-gray);min-width:70px;text-align:center;">Page 1</span>
                <button class="btn-outline-green" id="nextPageBtn" onclick="changePage(1)" style="padding:0.3rem 0.7rem;font-size:0.75rem;">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<style>
.log-tabs {
    display: flex;
    gap: 0.25rem;
    border-bottom: 2px solid #f3f4f6;
    overflow-x: auto;
    overflow-y: visible;
    scrollbar-width: none;
}
.log-tabs::-webkit-scrollbar { display: none; }

.log-tab-btn {
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    color: var(--medium-gray);
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    font-size: 0.78rem;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    border-radius: 8px 8px 0 0;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
    text-decoration: none;
}
.log-tab-btn:hover { color: var(--dark-gray); background: #f9fafb; }
.log-tab-btn.active { color: var(--primary-green); }
.log-tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--light-green), var(--primary-green));
    z-index: 2;
}

.log-th {
    font-size: 0.71rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--medium-gray);
    border-bottom: 1px solid #f3f4f6;
    padding: 0.6rem 0.75rem;
    background: #fafafa;
    text-align: left;
}
.log-td {
    padding: 0.7rem 0.75rem;
    border-bottom: 1px solid #f9fafb;
    vertical-align: middle;
    color: var(--dark-gray);
}
.log-row:hover .log-td { background: #f9fafb; }
.log-row:last-child .log-td { border-bottom: none; }
.log-row.hidden { display: none; }
.log-col-cat    { }
.log-col-device { }
.hide-cat    .log-col-cat    { display: none; }
.hide-device .log-col-device { display: none; }
</style>

<script>
const ROWS_PER_PAGE = 30;
let currentPage = 1;
let allRows = [];
let filteredRows = [];

let currentCat = 'all';

document.addEventListener('DOMContentLoaded', () => {
    allRows = Array.from(document.querySelectorAll('#logTbody .log-row'));
    filteredRows = [...allRows];
    renderPage();
});

function switchLogCat(btn) {
    document.querySelectorAll('.log-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentCat = btn.dataset.cat;
    document.getElementById('logSearch').value = '';
    // Toggle category column visibility
    const table = document.getElementById('logTable');
    if (table) {
        table.classList.toggle('hide-cat',    currentCat !== 'all');
        table.classList.toggle('hide-device', currentCat === 'users');
    }
    applyFilters();
    const wrapper = document.getElementById('main-content-wrapper');
    if (wrapper) wrapper.scrollTo({ top: 0, behavior: 'smooth' });
}

function applyFilters() {
    const q = (document.getElementById('logSearch').value || '').toLowerCase();
    filteredRows = allRows.filter(row => {
        const catMatch = currentCat === 'all' || row.dataset.cat === currentCat;
        const searchMatch = !q || row.textContent.toLowerCase().includes(q);
        return catMatch && searchMatch;
    });
    currentPage = 1;
    renderPage();
}

function renderPage() {
    const total = filteredRows.length;
    const totalPages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
    currentPage = Math.min(currentPage, totalPages);
    const start = (currentPage - 1) * ROWS_PER_PAGE;
    const end   = Math.min(start + ROWS_PER_PAGE, total);

    // Hide all, show only current page slice
    allRows.forEach(r => r.style.display = 'none');
    filteredRows.forEach((r, i) => {
        r.style.display = (i >= start && i < end) ? '' : 'none';
    });

    // Update footer
    document.getElementById('logRangeStart').textContent = total ? start + 1 : 0;
    document.getElementById('logRangeEnd').textContent   = end;
    document.getElementById('logTotal').textContent      = total;
    document.getElementById('pageIndicator').textContent = `Page ${currentPage} / ${totalPages}`;
    document.getElementById('prevPageBtn').disabled = currentPage <= 1;
    document.getElementById('nextPageBtn').disabled = currentPage >= totalPages;

    // Hide pagination if only 1 page
    document.getElementById('paginationControls').style.display = totalPages <= 1 ? 'none' : 'flex';
}

function changePage(dir) {
    currentPage += dir;
    renderPage();
    // Scroll to top of the wrapper
    const wrapper = document.getElementById('main-content-wrapper');
    if (wrapper) wrapper.scrollTo({ top: 0, behavior: 'smooth' });
    else window.scrollTo({ top: 0, behavior: 'smooth' });
}

function filterLogs(query) {
    applyFilters();
}

function exportLogsCSV() {
    const table = document.getElementById('logTable');
    if (!table) return;
    // Show ALL rows temporarily for export
    allRows.forEach(r => r.style.display = '');
    let csv = '';
    table.querySelectorAll('tr').forEach(row => {
        const cols = row.querySelectorAll('th, td');
        csv += Array.from(cols).map(c => `"${c.innerText.trim()}"`).join(',') + '\n';
    });
    // Restore pagination view
    renderPage();
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    a.download = 'GoSort_activity_logs.csv';
    a.click();
}
</script>