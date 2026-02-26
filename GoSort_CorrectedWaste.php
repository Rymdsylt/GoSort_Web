<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once 'gs_DB/main_DB.php';
require_once 'gs_DB/connection.php';
require_once 'gs_DB/activity_logs.php';

if (isset($_GET['logout'])) {
    // Log logout before destroying session
    if (isset($_SESSION['user_id'])) {
        log_logout($_SESSION['user_id']);
    }
    session_destroy();
    setcookie('user_logged_in', '', time() - 3600, '/');
    header("Location: GoSort_Login.php");
    exit();
}

if (!isset($_SESSION['user_id']) || !isset($_COOKIE['user_logged_in'])) {
    header("Location: GoSort_Login.php");
    exit();
}

// Fetch corrected waste data with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Count total records
$countQuery = "SELECT COUNT(*) as total FROM corrected_waste";
$countStmt = $pdo->query($countQuery);
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get records for current page
$query = "
    SELECT 
        cw.*,
        s.device_name,
        s.location
    FROM corrected_waste cw
    JOIN sorters s ON s.device_identity = cw.device_identity
    ORDER BY cw.corrected_at DESC
    LIMIT :offset, :perPage
";

$stmt = $pdo->prepare($query);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->execute();
$corrections = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Corrected Waste</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode-global.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="js/theme-manager.js"></script>
    <style>
        body {
            background-color: #F3F3EF !important;
            font-family: 'Inter', sans-serif !important;
        }

        #main-content-wrapper {
            margin-left: 260px;
            transition: margin-left 0.3s ease;
            padding: 20px;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }

        .waste-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .waste-type.bio {
            background-color: #dcfce7;
            color: #166534;
        }

        .waste-type.nbio {
            background-color: #fff1f2;
            color: #be123c;
        }

        .waste-type.hazardous {
            background-color: #fef9c3;
            color: #854d0e;
        }

        .waste-type.mixed {
            background-color: #f3f4f6;
            color: #374151;
        }

        .confidence-score {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
            background-color: #f3f4f6;
        }

        .pagination {
            margin-bottom: 0;
        }

        .page-link {
            color: #274a17;
            border-color: #dee2e6;
        }

        .page-link:hover {
            color: #1a3110;
            background-color: #e9ecef;
        }

        .page-item.active .page-link {
            background-color: #274a17;
            border-color: #274a17;
        }

        .image-preview {
            max-width: 100px;
            max-height: 100px;
            cursor: pointer;
        }

        /* Modal styles */
        .modal-content {
            border-radius: 15px;
        }

        .modal-header {
            border-bottom: none;
            padding: 1.5rem;
        }

        .modal-body {
            padding: 0 1.5rem 1.5rem;
        }

        .modal-image {
            max-width: 100%;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content-wrapper">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0">Corrected Waste Images</h2>
                <a href="GoSort_WasteMonitoringNavpage.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>
                    Back to Monitoring
                </a>
            </div>

            <div class="table-container">
                <?php if (empty($corrections)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox-fill text-muted" style="font-size: 2rem;"></i>
                        <p class="mt-3 mb-0 text-muted">No corrections have been recorded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Device</th>
                                    <th>Location</th>
                                    <th>Was</th>
                                    <th>Corrected To</th>
                                    <th>Category Change</th>
                                    <th>Confidence</th>
                                    <th>Notes</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($corrections as $correction): ?>
                                    <tr>
                                        <td>
                                            <?php if ($correction['image_path']): ?>
                                                <img src="<?php echo htmlspecialchars($correction['image_path']); ?>" 
                                                     class="image-preview" 
                                                     data-bs-toggle="modal" 
                                                     data-bs-target="#imageModal"
                                                     data-img-src="<?php echo htmlspecialchars($correction['image_path']); ?>"
                                                     alt="Waste Image">
                                            <?php else: ?>
                                                <span class="text-muted">No image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($correction['device_name']); ?></td>
                                        <td><?php echo htmlspecialchars($correction['location']); ?></td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-medium"><?php echo htmlspecialchars($correction['was_class']); ?></span>
                                                <small class="text-muted"><?php echo ucfirst($correction['waste_category']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-medium"><?php echo htmlspecialchars($correction['now_class']); ?></span>
                                                <small class="text-muted"><?php echo ucfirst($correction['corrected_category']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($correction['waste_category'] !== $correction['corrected_category']): ?>
                                                <span class="badge bg-warning">
                                                    <?php echo ucfirst($correction['waste_category']); ?> → <?php echo ucfirst($correction['corrected_category']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No Change</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="confidence-score">
                                                <?php echo number_format($correction['confidence_score'], 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($correction['correction_notes']): ?>
                                                <span class="text-truncate d-inline-block" style="max-width: 200px;" 
                                                      data-bs-toggle="tooltip" 
                                                      title="<?php echo htmlspecialchars($correction['correction_notes']); ?>">
                                                    <?php echo htmlspecialchars($correction['correction_notes']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($correction['corrected_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <p class="mb-0 text-muted">
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalRecords); ?> 
                                of <?php echo $totalRecords; ?> entries
                            </p>
                            <nav>
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page - 1); ?>">&laquo;</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page + 1); ?>">&raquo;</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Waste Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" class="modal-image" alt="Full size waste image">
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Handle image preview modal
            var imageModal = document.getElementById('imageModal');
            imageModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var imgSrc = button.getAttribute('data-img-src');
                var modalImage = imageModal.querySelector('.modal-image');
                modalImage.src = imgSrc;
            });
        });
    </script>
</body>
</html>