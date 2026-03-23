<?php
require_once 'db_config.php';

// Get all unique companies with Andison or Manila
$result = $conn->query("
    SELECT DISTINCT company_name 
    FROM delivery_records 
    WHERE (company_name LIKE '%Andison%' OR company_name LIKE '%Manila%')
    AND company_name != 'Stock Addition'
    ORDER BY company_name
");

echo "Companies matching 'Andison' or 'Manila':\n\n";
$companies = [];
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['company_name'] . "\n";
    $companies[] = $row['company_name'];
}

if (count($companies) === 0) {
    echo "None found. Showing first 10 companies:\n\n";
    $result = $conn->query("
        SELECT DISTINCT company_name 
        FROM delivery_records 
        WHERE company_name != 'Stock Addition'
        ORDER BY company_name
        LIMIT 10
    ");
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['company_name'] . "\n";
    }
}
?>
