<?php
/**
 * API Endpoint: Cleanup records with 0 quantity (bad imports)
 */

header('Content-Type: application/json');

// Include database configuration
require_once __DIR__ . '/../db_config.php';

try {
    // Count records with 0 quantity
    $result = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE quantity = 0 OR quantity IS NULL");
    $count = 0;
    if ($result && $row = $result->fetch_assoc()) {
        $count = intval($row['cnt']);
    }
    
    if ($count == 0) {
        echo json_encode([
            'success' => true,
            'message' => 'No records with 0 quantity found',
            'deleted' => 0
        ]);
        exit;
    }
    
    // Delete records with 0 quantity
    $result = $conn->query("DELETE FROM delivery_records WHERE quantity = 0 OR quantity IS NULL");
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => "Deleted {$count} records with 0 quantity",
            'deleted' => $count
        ]);
    } else {
        throw new Exception($conn->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
