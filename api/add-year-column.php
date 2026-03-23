<?php
/**
 * API Endpoint: Add delivery_year column to database and update existing records
 * Run this once to add the year column to existing database
 */

header('Content-Type: application/json');

// Include database configuration
require_once __DIR__ . '/../db_config.php';

try {
    // Check if column exists
    $result = $conn->query("PRAGMA table_info(delivery_records)");
    $columnExists = false;
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['name'] === 'delivery_year') {
                $columnExists = true;
                break;
            }
        }
    }
    
    if (!$columnExists) {
        // Add the column (SQLite compatible)
        $sql = "ALTER TABLE delivery_records ADD COLUMN delivery_year INTEGER DEFAULT NULL";
        $conn->query($sql);
    }
    
    // Update existing records with year from delivery_date first
    $updated1 = 0;
    $result = $conn->query("UPDATE delivery_records SET delivery_year = CAST(strftime('%Y', delivery_date) AS INTEGER) WHERE (delivery_year IS NULL OR delivery_year = 0) AND delivery_date IS NOT NULL AND delivery_date != ''");
    if ($result) {
        $updated1 = $conn->affected_rows ?? 0;
    }
    
    // For records without delivery_date, use created_at
    $updated2 = 0;
    $result = $conn->query("UPDATE delivery_records SET delivery_year = CAST(strftime('%Y', created_at) AS INTEGER) WHERE (delivery_year IS NULL OR delivery_year = 0) AND created_at IS NOT NULL");
    if ($result) {
        $updated2 = $conn->affected_rows ?? 0;
    }
    
    // For any remaining records, set to current year
    $currentYear = date('Y');
    $updated3 = 0;
    $result = $conn->query("UPDATE delivery_records SET delivery_year = {$currentYear} WHERE delivery_year IS NULL OR delivery_year = 0");
    if ($result) {
        $updated3 = $conn->affected_rows ?? 0;
    }
    
    // Get years summary
    $years = [];
    $result = $conn->query("SELECT delivery_year, COUNT(*) as cnt FROM delivery_records WHERE delivery_year IS NOT NULL GROUP BY delivery_year ORDER BY delivery_year DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $years[$row['delivery_year']] = $row['cnt'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Year column setup complete',
        'updated_from_delivery_date' => $updated1,
        'updated_from_created_at' => $updated2,
        'updated_to_current_year' => $updated3,
        'years_summary' => $years
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
