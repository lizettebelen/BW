<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db_config.php';

try {
    if (!$conn) throw new Exception('Database connection failed');

    // Ensure dataset_name column exists
    $isMysql = ($conn instanceof mysqli);
    $colExists = false;
    if ($isMysql) {
        $chk = $conn->query("SHOW COLUMNS FROM delivery_records LIKE 'dataset_name'");
        $colExists = ($chk && $chk->num_rows > 0);
    } else {
        $chk = $conn->query("PRAGMA table_info(delivery_records)");
        if ($chk) {
            while ($r = $chk->fetch_assoc()) {
                if (strtolower($r['name']) === 'dataset_name') { $colExists = true; break; }
            }
        }
    }
    if (!$colExists) {
        $conn->query("ALTER TABLE delivery_records ADD COLUMN dataset_name VARCHAR(50) DEFAULT NULL");
    }
    // Read-only listing: never auto-mutate dataset_name values here.
    $result = $conn->query("SELECT dataset_name, COUNT(*) as record_count FROM delivery_records WHERE dataset_name IS NOT NULL AND dataset_name != '' GROUP BY dataset_name ORDER BY dataset_name ASC");
    $datasets = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $datasets[] = ['name' => $row['dataset_name'], 'count' => intval($row['record_count'])];
        }
    }

    // Include legacy/manual records with no dataset tag so they can be managed/deleted too.
    $unassignedCount = 0;
    $unassignedResult = $conn->query("SELECT COUNT(*) AS record_count FROM delivery_records WHERE dataset_name IS NULL OR TRIM(dataset_name) = ''");
    if ($unassignedResult) {
        $unassignedRow = $unassignedResult->fetch_assoc();
        $unassignedCount = isset($unassignedRow['record_count']) ? intval($unassignedRow['record_count']) : 0;
    }
    if ($unassignedCount > 0) {
        $datasets[] = [
            'name' => '__UNASSIGNED__',
            'label' => 'Unassigned / Manual Records',
            'count' => $unassignedCount,
            'is_unassigned' => true
        ];
    }

    // Determine next available dataset number
    $maxNum = 0;
    foreach ($datasets as $ds) {
        if (preg_match('/^data(\d+)$/i', $ds['name'], $m)) {
            $maxNum = max($maxNum, intval($m[1]));
        }
    }
    $nextNum = $maxNum + 1;

    echo json_encode([
        'success'   => true,
        'datasets'  => $datasets,
        'next_num'  => $nextNum,
        'next_name' => 'data' . $nextNum
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'datasets' => [], 'next_num' => 1, 'next_name' => 'data1']);
}
?>
