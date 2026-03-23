<?php
require_once 'db_config.php';

// Test the new date formatting
$sql = "
    SELECT 
        item_code,
        item_name,
        COUNT(CASE WHEN company_name != 'Stock Addition' THEN 1 END) as delivery_count,
        MAX(CASE WHEN company_name != 'Stock Addition' THEN delivery_year || '-' || CASE 
            WHEN delivery_month = 'January' THEN '01'
            WHEN delivery_month = 'February' THEN '02'
            WHEN delivery_month = 'March' THEN '03'
            WHEN delivery_month = 'April' THEN '04'
            WHEN delivery_month = 'May' THEN '05'
            WHEN delivery_month = 'June' THEN '06'
            WHEN delivery_month = 'July' THEN '07'
            WHEN delivery_month = 'August' THEN '08'
            WHEN delivery_month = 'September' THEN '09'
            WHEN delivery_month = 'October' THEN '10'
            WHEN delivery_month = 'November' THEN '11'
            WHEN delivery_month = 'December' THEN '12'
            ELSE '00' END || '-' || printf('%02d', delivery_day) END) as last_delivery_date
    FROM delivery_records
    WHERE company_name != 'Stock Addition'
    GROUP BY item_code, item_name
    ORDER BY item_name
    LIMIT 5
";

$result = $conn->query($sql);

echo "=== CORRECTED LAST DELIVERY DATES ===\n\n";
while ($row = $result->fetch_assoc()) {
    $formatted = $row['last_delivery_date'] ? date('M d, Y', strtotime($row['last_delivery_date'])) : 'N/A';
    printf("%-25s | Last: %-15s | Format: %s\n", 
        $row['item_code'], 
        $formatted, 
        $row['last_delivery_date']
    );
}
?>
