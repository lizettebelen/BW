<?php
ob_start();
header('Content-Type: application/json');

// Increase limits for large imports
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);
set_time_limit(300);

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../db_config.php';

function respond(array $d, int $code = 200): never {
    ob_clean();
    http_response_code($code);
    echo json_encode($d);
    exit;
}

$json    = file_get_contents('php://input');
$request = json_decode($json, true);

if (!$request || !isset($request['data'])) {
    respond(['success' => false, 'message' => 'Invalid request data'], 400);
}

$data = $request['data'];

if (empty($data)) {
    respond(['success' => false, 'message' => 'No data to import'], 400);
}

// Function to convert Excel serial date to actual date
function excelDateToDate($excelDate) {
    if (empty($excelDate) || $excelDate == '-') return null;
    
    // If it's already a date string, return as is
    if (!is_numeric($excelDate)) {
        return $excelDate;
    }
    
    // Excel serial date conversion (Excel epoch is 1899-12-30)
    $unix = ($excelDate - 25569) * 86400;
    return date('Y-m-d', $unix);
}

// Function to get month name from date
function getMonthFromDate($dateStr) {
    if (empty($dateStr)) return '';
    $timestamp = strtotime($dateStr);
    if ($timestamp === false) return '';
    return date('F', $timestamp); // Returns full month name
}

// Function to get day from date
function getDayFromDate($dateStr) {
    if (empty($dateStr)) return 0;
    $timestamp = strtotime($dateStr);
    if ($timestamp === false) return 0;
    return intval(date('j', $timestamp)); // Returns day without leading zeros
}

// Column mapping - maps various Excel column names to database fields
$column_mappings = [
    // Invoice number variations
    'Invoice No.' => 'invoice_no',
    'Invoice No' => 'invoice_no',
    'InvoiceNo' => 'invoice_no',
    'Invoice_No' => 'invoice_no',
    'invoice_no' => 'invoice_no',
    'INVOICE NO.' => 'invoice_no',
    'INVOICE NO' => 'invoice_no',
    
    // Item code variations
    'Item' => 'item_code',
    'ITEM' => 'item_code',
    'Item_Code' => 'item_code',
    'ItemCode' => 'item_code',
    'item_code' => 'item_code',
    'Product' => 'item_code',
    'Product Code' => 'item_code',
    
    // Description/Item name variations
    'Description' => 'item_name',
    'DESCRIPTION' => 'item_name',
    'Item_Name' => 'item_name',
    'ItemName' => 'item_name',
    'item_name' => 'item_name',
    'Product Name' => 'item_name',
    
    // Quantity variations
    'Qty.' => 'quantity',
    'QTY.' => 'quantity',
    'Qty' => 'quantity',
    'Quantity' => 'quantity',
    'QUANTITY' => 'quantity',
    'quantity' => 'quantity',
    'QTY' => 'quantity',
    
    // Serial number variations
    'Serial No.' => 'serial_no',
    'SERIAL NO.' => 'serial_no',
    'Serial No' => 'serial_no',
    'SerialNo' => 'serial_no',
    'Serial_No' => 'serial_no',
    'serial_no' => 'serial_no',
    
    // Date variations
    'Date' => 'date',
    'DATE' => 'date',
    'date' => 'date',
    'Order Date' => 'date',
    
    // Date delivered variations
    'Date Delivered' => 'date_delivered',
    'DATE DELIVERED' => 'date_delivered',
    'DateDelivered' => 'date_delivered',
    'Date_Delivered' => 'date_delivered',
    'Delivery Date' => 'date_delivered',
    'Delivered Date' => 'date_delivered',
    
    // Delivery month variations (from user's Excel format)
    'DELIVERY MONTH TO ANDISON' => 'delivery_month',
    'Delivery Month To Andison' => 'delivery_month',
    'Delivery Month to Andison' => 'delivery_month',
    'Delivery_Month' => 'delivery_month',
    'Delivery Month' => 'delivery_month',
    
    // Delivery day variations (from user's Excel format)
    'DELIVERY DAY TO ANDISON' => 'delivery_day',
    'DEILVERY DAY TO ANDISON' => 'delivery_day',
    'Delivery Day To Andison' => 'delivery_day',
    'Delivery Day to Andison' => 'delivery_day',
    'Delilvery Day to Andison' => 'delivery_day',
    'Delivery_Day' => 'delivery_day',
    'Delivery Day' => 'delivery_day',
    
    // Year
    'YEAR' => 'year',
    'Year' => 'year',
    'year' => 'year',
    
    // Remarks/Notes variations
    'Remarks' => 'notes',
    'REMARKS' => 'notes',
    'remarks' => 'notes',
    'Notes' => 'notes',
    'notes' => 'notes',
    'Note' => 'notes',
    
    // Company name / Sold To variations
    'Company_Name' => 'company_name',
    'Company' => 'company_name',
    'Client' => 'company_name',
    'Customer' => 'company_name',
    'SOLD TO' => 'company_name',
    'Sold To' => 'company_name',
    'SOLD TO COMPANIES' => 'company_name',
    'Sold To Companies' => 'company_name',
    
    // Status variations
    'Status' => 'status',
    'STATUS' => 'status',
    'status' => 'status',
    
    // UOM (Unit of Measure)
    'UOM' => 'uom',
    'Uom' => 'uom',
    'uom' => 'uom',
    'Unit' => 'uom',
    'Unit of Measure' => 'uom',
    
    // Sold To Month
    'Sold To Month' => 'sold_to_month',
    'SOLD TO MONTH' => 'sold_to_month',
    'sold_to_month' => 'sold_to_month',
    
    // Sold To Day
    'Sold To Day' => 'sold_to_day',
    'SOLD TO DAY' => 'sold_to_day',
    'sold_to_day' => 'sold_to_day',
    
    // Groupings
    'Groupings' => 'groupings',
    'GROUPINGS' => 'groupings',
    'groupings' => 'groupings',
    'Grouping' => 'groupings',
    'Group' => 'groupings',
];

// Build lowercase version of mappings for case-insensitive lookup
$lower_mappings = [];
foreach ($column_mappings as $k => $v) {
    $lower_mappings[strtolower(trim($k))] = $v;
}

try {
    if (!$conn || !empty($conn->connect_error)) {
        throw new Exception('Database connection failed');
    }

    // Ensure dataset_name column exists
    $isMysql = ($conn instanceof mysqli);
    $colExists = false;
    if ($isMysql) {
        $chk = $conn->query("SHOW COLUMNS FROM delivery_records LIKE 'dataset_name'");
        $colExists = ($chk && $chk->num_rows > 0);
    } else {
        $chk = $conn->query('PRAGMA table_info(delivery_records)');
        if ($chk) {
            while ($r = $chk->fetch_assoc()) {
                if (strtolower($r['name']) === 'dataset_name') { $colExists = true; break; }
            }
        }
    }
    if (!$colExists) {
        $conn->query('ALTER TABLE delivery_records ADD COLUMN dataset_name VARCHAR(50) DEFAULT NULL');
    }
    // Tag any pre-existing untagged rows as data1 (imported before this feature existed)
    $conn->query("UPDATE delivery_records SET dataset_name = 'data1' WHERE dataset_name IS NULL OR dataset_name = ''");

    // Get dataset_name from request (e.g. data1, data2)
    $dataset_name = isset($request['dataset_name']) ? trim(strval($request['dataset_name'])) : '';
    if (empty($dataset_name)) $dataset_name = 'data1';

    // Detect all columns from the uploaded data and auto-create missing ones
    $all_columns_in_data = [];
    if (!empty($data) && is_array($data[0])) {
        foreach ($data[0] as $col => $val) {
            $all_columns_in_data[] = trim($col);
        }
    }

    // For each column in the data, check if it needs to be created
    $existing_columns = [];
    if ($isMysql) {
        $result = $conn->query("SHOW COLUMNS FROM delivery_records");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $existing_columns[strtolower($row['Field'])] = true;
            }
        }
    } else {
        $result = $conn->query('PRAGMA table_info(delivery_records)');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $existing_columns[strtolower($row['name'])] = true;
            }
        }
    }

    // Auto-create columns for unmapped data
    foreach ($all_columns_in_data as $col) {
        $col_lower = strtolower(trim($col));
        $mapped_name = isset($lower_mappings[$col_lower]) ? $lower_mappings[$col_lower] : null;
        
        // If this column is mapped to a standard field, skip it (it already exists)
        if ($mapped_name && isset($existing_columns[$mapped_name])) {
            continue;
        }
        
        // If this column is a mapped field but doesn't exist as the mapped name, skip (will be created later)
        if ($mapped_name) {
            continue;
        }
        
        // If this is an unmapped column and doesn't exist, create it
        if (!isset($existing_columns[$col_lower])) {
            // Create a safe column name from the header
            $safe_col_name = strtolower(trim($col));
            $safe_col_name = preg_replace('/[^a-z0-9_]/', '_', $safe_col_name);
            $safe_col_name = preg_replace('/_+/', '_', $safe_col_name);
            $safe_col_name = trim($safe_col_name, '_');
            
            if (!empty($safe_col_name) && !isset($existing_columns[$safe_col_name])) {
                // Determine column type based on content
                $is_numeric = true;
                $is_date = true;
                foreach ($data as $row) {
                    if (isset($row[$col])) {
                        $val = $row[$col];
                        if (!is_numeric($val)) $is_numeric = false;
                        if (!empty($val) && !preg_match('/^\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}/', $val) && !preg_match('/^\d{4}-\d{2}-\d{2}/', $val)) {
                            $is_date = false;
                        }
                    }
                }
                
                $col_type = 'VARCHAR(255)';
                if ($is_numeric) $col_type = 'DECIMAL(12,2)';
                elseif ($is_date) $col_type = 'VARCHAR(50)';
                
                // Alter table to add column
                $alter_sql = "ALTER TABLE delivery_records ADD COLUMN `{$safe_col_name}` {$col_type} DEFAULT NULL";
                $conn->query($alter_sql);
                
                $existing_columns[$safe_col_name] = true;
            }
        }
    }

    $imported_count = 0;
    $failed_count   = 0;
    $skipped_count  = 0;
    $errors  = [];
    $skipped = [];

    // Start transaction
    if ($conn instanceof mysqli) {
        $conn->begin_transaction();
    } else {
        $conn->query('BEGIN');
    }

    foreach ($data as $index => $record) {
        try {
            // Map columns to database fields (case-insensitive)
            $mapped = [];
            foreach ($record as $col => $value) {
                $col_lower = strtolower(trim($col));
                if (isset($lower_mappings[$col_lower]) && !isset($mapped[$lower_mappings[$col_lower]])) {
                    $mapped[$lower_mappings[$col_lower]] = $value;
                }
            }
            
            // Extract and process values
            $invoice_no = isset($mapped['invoice_no']) ? trim(strval($mapped['invoice_no'])) : '';
            $item_code = isset($mapped['item_code']) ? trim(strval($mapped['item_code'])) : '';
            $item_name = isset($mapped['item_name']) ? trim(strval($mapped['item_name'])) : '';
            $quantity = isset($mapped['quantity']) ? intval($mapped['quantity']) : 0;
            $serial_no = isset($mapped['serial_no']) ? trim(strval($mapped['serial_no'])) : '';
            $notes = isset($mapped['notes']) ? trim(strval($mapped['notes'])) : '';
            $company_name = isset($mapped['company_name']) ? trim(strval($mapped['company_name'])) : 'Andison Industrial';
            $status = isset($mapped['status']) ? trim(strval($mapped['status'])) : 'Delivered';
            $uom = isset($mapped['uom']) ? trim(strval($mapped['uom'])) : '';
            // Year starts at 0; will be filled from explicit YEAR column, delivery_date, or current year
            $year = isset($mapped['year']) ? intval($mapped['year']) : 0;
            
            // Handle dates
            $delivery_date = null;
            $delivery_month = '';
            $delivery_day = 0;
            
            // First check if we have direct month/day values from Excel
            if (!empty($mapped['delivery_month'])) {
                $delivery_month = trim(strval($mapped['delivery_month']));
            }
            if (!empty($mapped['delivery_day'])) {
                $delivery_day = intval($mapped['delivery_day']);
            }
            
            // Try date_delivered column (may be Excel serial or date string)
            if (!empty($mapped['date_delivered'])) {
                $delivery_date = excelDateToDate($mapped['date_delivered']);
                if ($delivery_date) {
                    if (empty($delivery_month))  $delivery_month = getMonthFromDate($delivery_date);
                    if ($delivery_day == 0)       $delivery_day   = getDayFromDate($delivery_date);
                    if ($year <= 0)               $year           = intval(date('Y', strtotime($delivery_date)));
                }
            }
            
            // Fallback to generic date field
            if ((empty($delivery_month) || $delivery_day == 0) && !empty($mapped['date'])) {
                $temp_date = excelDateToDate($mapped['date']);
                if ($temp_date) {
                    if (empty($delivery_month)) $delivery_month = getMonthFromDate($temp_date);
                    if ($delivery_day == 0)     $delivery_day   = getDayFromDate($temp_date);
                    if ($year <= 0)             $year           = intval(date('Y', strtotime($temp_date)));
                }
            }
            
            // If we have month+day+year but no delivery_date, build one
            if (empty($delivery_date) && !empty($delivery_month) && $delivery_day > 0 && $year > 0) {
                $month_num = date('n', strtotime($delivery_month . ' 1'));
                if ($month_num) {
                    $delivery_date = sprintf('%04d-%02d-%02d', $year, $month_num, $delivery_day);
                }
            }
            
            // Don't auto-fill year if not provided
            // if ($year <= 0) $year = intval(date('Y'));
            
            // Skip completely empty rows - only if ALL fields are empty
            $has_any_data = !empty($item_code) || !empty($item_name) || !empty($invoice_no) || 
                           $quantity > 0 || !empty($serial_no) || !empty($company_name) ||
                           !empty($delivery_month) || $delivery_day > 0 || !empty($notes);
            if (!$has_any_data) {
                $skipped_count++;
                $skipped[] = "Row " . ($index + 2) . ": empty row";
                continue;
            }
            
            // Skip total/subtotal rows
            $name_lower = strtolower($item_name . ' ' . $item_code);
            if (preg_match('/\b(total|subtotal|grand total|sub-total)\b/', $name_lower)) {
                $skipped_count++;
                $skipped[] = "Row " . ($index + 2) . ": total/subtotal row";
                continue;
            }

            // Skip repeat-header rows
            if (in_array(strtolower($item_code), ['item', 'item code', '-', '']) &&
                in_array(strtolower($item_name), ['description', 'item name', '-', ''])) {
                $skipped_count++;
                $skipped[] = "Row " . ($index + 2) . ": header-repeat row";
                continue;
            }
            
            // Handle "-" as empty
            if ($notes == '-') $notes = '';
            if ($serial_no == '-') $serial_no = '';
            if ($company_name == '-') $company_name = '';
            
            // Handle UOM - store in its own column (no default)
            if ($uom == '-') $uom = '';
            
            // Extract sold_to_month, sold_to_day, groupings
            $sold_to_month = isset($mapped['sold_to_month']) ? trim(strval($mapped['sold_to_month'])) : '';
            $sold_to_day = isset($mapped['sold_to_day']) ? intval($mapped['sold_to_day']) : 0;
            $groupings = isset($mapped['groupings']) ? trim(strval($mapped['groupings'])) : '';
            
            // Handle "-" values
            if ($sold_to_month == '-') $sold_to_month = '';
            if ($groupings == '-') $groupings = '';
            if ($delivery_month == '-') $delivery_month = '';
            
            // Default status if empty
            if (empty($status) || $status == '-') {
                $status = 'Delivered';
            }
            
            // Don't set default delivery month/day - only store what's in the Excel
            // Don't auto-fill quantity - if Excel has no quantity, leave it as 0/empty

            // Insert into database
            $sql = "INSERT INTO delivery_records 
                    (invoice_no, serial_no, delivery_month, delivery_day, delivery_year, delivery_date, item_code, item_name, company_name, quantity, status, notes, uom, sold_to_month, sold_to_day, groupings, dataset_name)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . ($conn->error ?? 'Unknown error'));
            }

            // Types: s=invoice_no, s=serial_no, s=delivery_month, i=delivery_day,
            //        i=delivery_year, s=delivery_date, s=item_code, s=item_name,
            //        s=company_name, i=quantity, s=status, s=notes, s=uom,
            //        s=sold_to_month, i=sold_to_day, s=groupings, s=dataset_name
            $stmt->bind_param(
                'sssiissssississss',
                $invoice_no,
                $serial_no,
                $delivery_month,
                $delivery_day,
                $year,
                $delivery_date,
                $item_code,
                $item_name,
                $company_name,
                $quantity,
                $status,
                $notes,
                $uom,
                $sold_to_month,
                $sold_to_day,
                $groupings,
                $dataset_name
            );

            if (!$stmt->execute()) {
                $errors[] = "Row " . ($index + 2) . ": " . $stmt->error;
                $failed_count++;
            } else {
                $imported_count++;
                
                // Get the last inserted ID
                $last_id = 0;
                if ($isMysql) {
                    $last_id = $conn->insert_id;
                } else {
                    // SQLite
                    $res = $conn->query("SELECT last_insert_rowid() as id");
                    if ($res) {
                        $row = $res->fetch_assoc();
                        $last_id = intval($row['id']);
                    }
                }
                
                // Insert unmapped column data
                if ($last_id > 0) {
                    foreach ($record as $col => $value) {
                        $col_lower = strtolower(trim($col));
                        
                        // Skip if this column is mapped
                        if (isset($lower_mappings[$col_lower])) {
                            continue;
                        }
                        
                        // Create safe column name
                        $safe_col_name = strtolower(trim($col));
                        $safe_col_name = preg_replace('/[^a-z0-9_]/', '_', $safe_col_name);
                        $safe_col_name = preg_replace('/_+/', '_', $safe_col_name);
                        $safe_col_name = trim($safe_col_name, '_');
                        
                        // Update with unmapped column value
                        if (!empty($safe_col_name) && !empty($value)) {
                            if ($isMysql) {
                                $update_sql = "UPDATE delivery_records SET `{$safe_col_name}` = ? WHERE id = ?";
                            } else {
                                $update_sql = "UPDATE delivery_records SET [{$safe_col_name}] = ? WHERE id = ?";
                            }
                            $update_stmt = $conn->prepare($update_sql);
                            if ($update_stmt) {
                                $update_stmt->bind_param('si', $value, $last_id);
                                $update_stmt->execute();
                                $update_stmt->close();
                            }
                        }
                    }
                }
            }
            
            $stmt->close();

        } catch (Exception $e) {
            $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            $failed_count++;
        }
    }

    // Commit transaction
    if ($conn instanceof mysqli) {
        $conn->commit();
    } else {
        $conn->query('COMMIT');
    }

    $response = [
        'success'  => true,
        'imported' => $imported_count,
        'failed'   => $failed_count,
        'skipped'  => $skipped_count,
        'total'    => count($data),
        'message'  => "Successfully imported $imported_count records"
    ];
    if (!empty($errors))   $response['errors']       = array_slice($errors,  0, 20);
    if (!empty($skipped))  $response['skipped_rows'] = array_slice($skipped, 0, 20);
    respond($response);

} catch (Exception $e) {
    if (isset($conn)) {
        try {
            if ($conn instanceof mysqli) $conn->rollback();
            else $conn->query('ROLLBACK');
        } catch (Throwable $_) {}
    }
    respond(['success' => false, 'message' => 'Import error: ' . $e->getMessage()], 500);
}
?>
