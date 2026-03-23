<?php
require 'db_config.php';

echo "=== CHECKING DATA AFTER DELETE ===\n\n";

$r = $conn->query('SELECT COUNT(*) as t FROM delivery_records');
$x = $r->fetch_assoc();
echo "Delivery Records: " . $x['t'] . "\n";

$r2 = @$conn->query('SELECT COUNT(*) as t FROM inventory');
if ($r2) {
    $x2 = $r2->fetch_assoc();
    echo "Inventory: " . $x2['t'] . "\n";
}

$r3 = @$conn->query('SELECT COUNT(*) as t FROM security_alerts');
if ($r3) {
    $x3 = $r3->fetch_assoc();
    echo "Security Alerts: " . $x3['t'] . "\n";
}

$conn->close();
?>
