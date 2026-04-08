<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db_config.php';

function ensureHighlightMemoryTable($conn): void {
    $isMysql = ($conn instanceof mysqli);

    if ($isMysql) {
        $sql = "CREATE TABLE IF NOT EXISTS delivery_highlight_memory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dataset_name VARCHAR(50) NOT NULL,
            invoice_no VARCHAR(100) DEFAULT '',
            item_code VARCHAR(100) DEFAULT '',
            serial_no VARCHAR(150) DEFAULT '',
            sold_to VARCHAR(255) DEFAULT '',
            delivery_date VARCHAR(50) DEFAULT '',
            highlight_color VARCHAR(20) DEFAULT NULL,
            cell_styles LONGTEXT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_dataset_record (dataset_name, invoice_no, item_code, serial_no, sold_to, delivery_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    } else {
        $sql = "CREATE TABLE IF NOT EXISTS delivery_highlight_memory (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            dataset_name VARCHAR(50) NOT NULL,
            invoice_no VARCHAR(100) DEFAULT '',
            item_code VARCHAR(100) DEFAULT '',
            serial_no VARCHAR(150) DEFAULT '',
            sold_to VARCHAR(255) DEFAULT '',
            delivery_date VARCHAR(50) DEFAULT '',
            highlight_color VARCHAR(20) DEFAULT NULL,
            cell_styles TEXT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (dataset_name, invoice_no, item_code, serial_no, sold_to, delivery_date)
        )";
    }

    $conn->query($sql);
}

function preserveDatasetHighlights($conn, string $datasetName): int {
    ensureHighlightMemoryTable($conn);

    $sel = $conn->prepare("SELECT invoice_no, item_code, serial_no, sold_to, delivery_date, highlight_color, cell_styles
                           FROM delivery_records
                           WHERE dataset_name = ?
                             AND (
                                 TRIM(COALESCE(highlight_color, '')) <> ''
                                 OR TRIM(COALESCE(cell_styles, '')) <> ''
                             )");
    $sel->bind_param('s', $datasetName);
    $sel->execute();
    $result = $sel->get_result();

    $saved = 0;
    while ($row = $result->fetch_assoc()) {
        $invoiceNo = trim((string)($row['invoice_no'] ?? ''));
        $itemCode = trim((string)($row['item_code'] ?? ''));
        $serialNo = trim((string)($row['serial_no'] ?? ''));
        $soldTo = strtolower(trim((string)($row['sold_to'] ?? '')));
        $deliveryDate = trim((string)($row['delivery_date'] ?? ''));
        $highlightColor = trim((string)($row['highlight_color'] ?? ''));
        $cellStyles = trim((string)($row['cell_styles'] ?? ''));

        $del = $conn->prepare("DELETE FROM delivery_highlight_memory
                              WHERE dataset_name = ?
                                AND invoice_no = ?
                                AND item_code = ?
                                AND serial_no = ?
                                AND sold_to = ?
                                AND delivery_date = ?");
        $del->bind_param('ssssss', $datasetName, $invoiceNo, $itemCode, $serialNo, $soldTo, $deliveryDate);
        $del->execute();

        $ins = $conn->prepare("INSERT INTO delivery_highlight_memory
                              (dataset_name, invoice_no, item_code, serial_no, sold_to, delivery_date, highlight_color, cell_styles)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param('ssssssss', $datasetName, $invoiceNo, $itemCode, $serialNo, $soldTo, $deliveryDate, $highlightColor, $cellStyles);
        if ($ins->execute()) {
            $saved++;
        }
    }

    return $saved;
}

try {
    if (!$conn) throw new Exception('Database connection failed');
    
    $json = file_get_contents('php://input');
    $request = json_decode($json, true);
    
    $action = $request['action'] ?? '';
    
    if ($action === 'rename') {
        $oldName = trim($request['old_name'] ?? '');
        $newName = trim($request['new_name'] ?? '');
        
        if (empty($oldName) || empty($newName)) {
            throw new Exception('Both old_name and new_name are required');
        }
        
        // Sanitize new name
        $newName = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $newName);
        $newName = substr($newName, 0, 50);
        
        if (empty($newName)) {
            throw new Exception('Invalid new name');
        }
        
        // Check if new name already exists
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM delivery_records WHERE dataset_name = ?");
        $stmt->bind_param('s', $newName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['cnt'] > 0 && $oldName !== $newName) {
            throw new Exception('A dataset with this name already exists');
        }
        
        // Rename the dataset
        $stmt = $conn->prepare("UPDATE delivery_records SET dataset_name = ? WHERE dataset_name = ?");
        $stmt->bind_param('ss', $newName, $oldName);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to rename dataset');
        }
        
        $affected = $stmt->affected_rows;
        
        echo json_encode([
            'success' => true,
            'message' => "Renamed '$oldName' to '$newName' ($affected records updated)",
            'refresh_required' => true,
            'new_dataset_name' => $newName
        ]);
        
    } elseif ($action === 'delete') {
        $datasetName = trim($request['dataset_name'] ?? '');
        
        if (empty($datasetName)) {
            throw new Exception('dataset_name is required');
        }
        
        $preservedHighlights = preserveDatasetHighlights($conn, $datasetName);

        $stmt = $conn->prepare("DELETE FROM delivery_records WHERE dataset_name = ?");
        $stmt->bind_param('s', $datasetName);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete dataset');
        }
        
        $affected = $stmt->affected_rows;

        if ($affected <= 0) {
            throw new Exception("Dataset '$datasetName' was not found or already deleted");
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Deleted dataset '$datasetName' ($affected records removed)",
            'refresh_required' => true,
            'deleted_dataset' => $datasetName,
            'preserved_highlights' => $preservedHighlights
        ]);
        
    } else {
        throw new Exception('Invalid action. Use "rename" or "delete"');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
