<?php
// Clean any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

session_start();
date_default_timezone_set('Asia/Manila');

require_once '../gs_DB/connection.php';

/**
 * Pure PHP Zip file creator (no ZipArchive extension required)
 */
class PureZip {
    private $files = [];
    private $centralDir = '';
    private $offset = 0;
    
    public function addFromString($filename, $data) {
        $filename = str_replace('\\', '/', $filename);
        
        $unc_len = strlen($data);
        $crc = crc32($data);
        $zdata = gzcompress($data);
        $zdata = substr($zdata, 2, -4); // Remove gzip header/footer
        $c_len = strlen($zdata);
        
        // Local file header
        $fr = "\x50\x4b\x03\x04"; // Local file header signature
        $fr .= "\x14\x00"; // Version needed to extract
        $fr .= "\x00\x00"; // General purpose bit flag
        $fr .= "\x08\x00"; // Compression method (deflate)
        $fr .= "\x00\x00\x00\x00"; // File modification time/date
        $fr .= pack('V', $crc); // CRC-32
        $fr .= pack('V', $c_len); // Compressed size
        $fr .= pack('V', $unc_len); // Uncompressed size
        $fr .= pack('v', strlen($filename)); // File name length
        $fr .= pack('v', 0); // Extra field length
        $fr .= $filename;
        $fr .= $zdata;
        
        $this->files[] = $fr;
        
        // Central directory entry
        $cdr = "\x50\x4b\x01\x02"; // Central file header signature
        $cdr .= "\x00\x00"; // Version made by
        $cdr .= "\x14\x00"; // Version needed to extract
        $cdr .= "\x00\x00"; // General purpose bit flag
        $cdr .= "\x08\x00"; // Compression method
        $cdr .= "\x00\x00\x00\x00"; // File modification time/date
        $cdr .= pack('V', $crc); // CRC-32
        $cdr .= pack('V', $c_len); // Compressed size
        $cdr .= pack('V', $unc_len); // Uncompressed size
        $cdr .= pack('v', strlen($filename)); // File name length
        $cdr .= pack('v', 0); // Extra field length
        $cdr .= pack('v', 0); // File comment length
        $cdr .= pack('v', 0); // Disk number start
        $cdr .= pack('v', 0); // Internal file attributes
        $cdr .= pack('V', 32); // External file attributes
        $cdr .= pack('V', $this->offset); // Relative offset of local header
        $cdr .= $filename;
        
        $this->centralDir .= $cdr;
        $this->offset += strlen($fr);
    }
    
    public function getZipData() {
        $data = implode('', $this->files);
        $ctrlDirOffset = strlen($data);
        $data .= $this->centralDir;
        
        // End of central directory record
        $data .= "\x50\x4b\x05\x06"; // End of central dir signature
        $data .= "\x00\x00"; // Number of this disk
        $data .= "\x00\x00"; // Disk where central directory starts
        $data .= pack('v', count($this->files)); // Number of central directory records on this disk
        $data .= pack('v', count($this->files)); // Total number of central directory records
        $data .= pack('V', strlen($this->centralDir)); // Size of central directory
        $data .= pack('V', $ctrlDirOffset); // Offset of start of central directory
        $data .= "\x00\x00"; // Comment length
        
        return $data;
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$deviceIdentity = $_GET['identity'] ?? null;
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$filterType = $_GET['filter'] ?? 'all'; // all, correct, wrong

if (!$deviceIdentity) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Device identity required']);
    exit();
}

// Build query based on filter
$whereClause = "sh.device_identity = ? AND DATE(sh.sorted_at) = ? AND sh.is_maintenance = 0 AND sr.id IS NOT NULL";
$params = [$deviceIdentity, $selectedDate];

if ($filterType === 'correct') {
    $whereClause .= " AND sr.is_correct = 1";
} elseif ($filterType === 'wrong') {
    $whereClause .= " AND sr.is_correct = 0";
}

$query = "
    SELECT 
        sh.id,
        sh.trash_type,
        sh.trash_class,
        sh.image_data,
        sh.sorted_at,
        sr.is_correct
    FROM sorting_history sh
    INNER JOIN sorting_reviews sr ON sh.id = sr.sorting_history_id
    WHERE {$whereClause}
    ORDER BY sh.sorted_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reviewedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($reviewedItems)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No reviewed items found']);
        exit();
    }

    // Get device name for zip filename
    $deviceQuery = $pdo->prepare("SELECT device_name FROM sorters WHERE device_identity = ?");
    $deviceQuery->execute([$deviceIdentity]);
    $deviceRow = $deviceQuery->fetch(PDO::FETCH_ASSOC);
    $deviceName = $deviceRow ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $deviceRow['device_name']) : $deviceIdentity;

    // Create zip file
    $zipFilename = "GoSort_Reviews_{$deviceName}_{$selectedDate}.zip";
    
    // Use pure PHP zip creator
    $zip = new PureZip();

    // Category mapping
    $categoryMap = [
        'bio' => 'biodegradable',
        'nbio' => 'non-biodegradable',
        'hazardous' => 'hazardous',
        'mixed' => 'mixed'
    ];

    $imageCounter = [];
    
    foreach ($reviewedItems as $item) {
        $category = $categoryMap[$item['trash_type']] ?? $item['trash_type'];
        $status = $item['is_correct'] == 1 ? 'correct' : 'wrong';
        
        // Create unique counter key
        $key = "{$category}_{$status}";
        if (!isset($imageCounter[$key])) {
            $imageCounter[$key] = 1;
        }
        
        // Generate filename: img_001_biodegradable_correct.png
        $counter = str_pad($imageCounter[$key], 3, '0', STR_PAD_LEFT);
        $filename = "img_{$counter}_{$category}_{$status}.png";
        
        // Decode base64 image and add to zip
        $imageData = base64_decode($item['image_data']);
        if ($imageData !== false) {
            $zip->addFromString($filename, $imageData);
        }
        
        $imageCounter[$key]++;
    }

    // Add a summary text file
    $summary = "GoSort Review Export Summary\r\n";
    $summary .= "============================\r\n\r\n";
    $summary .= "Device: {$deviceName}\r\n";
    $summary .= "Date: {$selectedDate}\r\n";
    $summary .= "Filter: {$filterType}\r\n";
    $summary .= "Total Images: " . count($reviewedItems) . "\r\n\r\n";
    $summary .= "Breakdown:\r\n";
    
    $correctCount = 0;
    $wrongCount = 0;
    foreach ($reviewedItems as $item) {
        if ($item['is_correct'] == 1) {
            $correctCount++;
        } else {
            $wrongCount++;
        }
    }
    $summary .= "- Correct: {$correctCount}\r\n";
    $summary .= "- Wrong: {$wrongCount}\r\n\r\n";
    $summary .= "Exported: " . date('Y-m-d H:i:s') . "\r\n";
    
    $zip->addFromString('_summary.txt', $summary);
    
    // Get zip data
    $zipData = $zip->getZipData();

    // Send zip file
    header('Content-Type: application/zip');
    header('Content-Transfer-Encoding: binary');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Content-Length: ' . strlen($zipData));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    echo $zipData;
    exit();

} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
