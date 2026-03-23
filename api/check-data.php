<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db_config.php';

try {
    // Check if connection is valid
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get total count
    $result = $conn->query("SELECT COUNT(*) as total FROM delivery_records");
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    $row = $result->fetch_assoc();
    $total = isset($row['total']) ? intval($row['total']) : 0;

    // Get sample records
    $samples = [];
    $result = $conn->query("SELECT * FROM delivery_records ORDER BY id DESC LIMIT 10");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $samples[] = $row;
        }
    }

    // Get quantity distribution
    $qty_dist = [];
    $result = $conn->query("SELECT quantity, COUNT(*) as cnt FROM delivery_records GROUP BY quantity ORDER BY cnt DESC LIMIT 10");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $qty_dist[] = $row;
        }
    }

    // Get year distribution
    $year_dist = [];
    $result = $conn->query("SELECT delivery_year, COUNT(*) as cnt FROM delivery_records GROUP BY delivery_year");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $year_dist[] = $row;
        }
    }

    echo json_encode([
        'success' => true,
        'total_records' => $total,
        'year_distribution' => $year_dist,
        'quantity_distribution' => $qty_dist,
        'sample_records' => $samples
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(200); // Still return 200 with error info
    echo json_encode([
        'success' => false,
        'total_records' => 0,
        'error' => $e->getMessage(),
        'year_distribution' => [],
        'quantity_distribution' => [],
        'sample_records' => []
    ], JSON_PRETTY_PRINT);
}

if ($conn) {
    $conn->close();
}
?>
