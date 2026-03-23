<?php
header('Content-Type: application/json');
require __DIR__ . '/db_config.php';

$count = @$conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name='Stock Addition'");
$row = $count ? @$count->fetch_assoc() : null;
$total = $row ? intval($row['cnt']) : 0;

echo json_encode([
    'success' => true,
    'message' => 'Inventory Status',
    'total_items' => $total
]);
?>
