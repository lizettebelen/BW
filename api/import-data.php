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

// Infer grouping label from free-form row text when Category/Groupings is not provided.
function inferGroupingFromText($text) {
    $value = strtolower(trim((string) $text));
    if ($value === '') return '';

    $value = preg_replace('/--+>|->|=>|→/', ' to ', $value);
    $value = preg_replace('/\s+/', ' ', $value);

    if (strpos($value, 'katay') !== false) return 'katay';
    if (strpos($value, 'send to andison') !== false || strpos($value, 'send to andiso') !== false) return 'send to andison';
    if (strpos($value, 'warranty replacement') !== false || strpos($value, 'warranty replacemer') !== false) return 'warranty replacement';
    if (strpos($value, 'warranty to purchase') !== false || strpos($value, 'swapping') !== false) return 'warranty to purchase';
    if (strpos($value, 'purchase to warranty') !== false) return 'purchase --> warranty';

    return '';
}

function inferGroupingFromColor($hexColor) {
    $hex = strtoupper(trim((string) $hexColor));
    if ($hex === '') return '';
    if ($hex[0] !== '#') $hex = '#' . $hex;
    if (!preg_match('/^#[0-9A-F]{6}$/', $hex)) return '';

    $distance = function($a, $b) {
        $ar = [
            hexdec(substr($a, 1, 2)),
            hexdec(substr($a, 3, 2)),
            hexdec(substr($a, 5, 2)),
        ];
        $br = [
            hexdec(substr($b, 1, 2)),
            hexdec(substr($b, 3, 2)),
            hexdec(substr($b, 5, 2)),
        ];
        $dr = $ar[0] - $br[0];
        $dg = $ar[1] - $br[1];
        $db = $ar[2] - $br[2];
        return sqrt(($dr * $dr) + ($dg * $dg) + ($db * $db));
    };

    $palette = [
        'katay' => ['#800080', '#7030A0', '#9933CC', '#8E44AD'],
        'send to andison' => ['#FFFF00', '#FFD966', '#F1C232', '#FFEB3B'],
        'warranty replacement' => ['#FF0000', '#C00000', '#E74C3C', '#D32F2F'],
        'warranty to purchase' => ['#0000FF', '#4472C4', '#1F4E78', '#2F75B5'],
        'purchase --> warranty' => ['#FF00FF', '#FF66CC', '#E91E63', '#F4B6E5'],
    ];

    $bestGroup = '';
    $bestDistance = PHP_FLOAT_MAX;

    foreach ($palette as $group => $swatches) {
        foreach ($swatches as $swatch) {
            $currentDistance = $distance($hex, $swatch);
            if ($currentDistance < $bestDistance) {
                $bestDistance = $currentDistance;
                $bestGroup = $group;
            }
        }
    }

    // Accept close shades to handle Excel tint/theme variations.
    return ($bestDistance <= 145) ? $bestGroup : '';
}

function inferGroupingFromStyles($highlightColor, $cellStylesJson) {
    $candidates = [];

    $highlight = trim((string) $highlightColor);
    if ($highlight !== '' && $highlight !== '-') {
        $candidates[] = $highlight;
    }

    if (!empty($cellStylesJson)) {
        $decoded = json_decode((string) $cellStylesJson, true);
        if (is_array($decoded)) {
            $priorityFields = ['groupings', 'status', 'notes', 'item_name', 'item_code', 'invoice_no', 'serial_no'];

            foreach ($priorityFields as $fieldName) {
                if (!empty($decoded[$fieldName])) {
                    $value = $decoded[$fieldName];
                    if (is_array($value)) {
                        if (!empty($value['bg'])) $candidates[] = $value['bg'];
                        if (!empty($value['text'])) $candidates[] = $value['text'];
                    } else {
                        $candidates[] = $value;
                    }
                }
            }

            foreach ($decoded as $colorValue) {
                if (is_array($colorValue)) {
                    if (!empty($colorValue['bg'])) $candidates[] = $colorValue['bg'];
                    if (!empty($colorValue['text'])) $candidates[] = $colorValue['text'];
                } else {
                    $candidates[] = $colorValue;
                }
            }
        }
    }

    foreach ($candidates as $colorCandidate) {
        $inferred = inferGroupingFromColor($colorCandidate);
        if ($inferred !== '') {
            return $inferred;
        }
    }

    return '';
}

function ensureHighlightMemoryTable($conn, bool $isMysql): void {
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

function findPreservedHighlight($conn, string $datasetName, string $invoiceNo, string $itemCode, string $serialNo, string $soldTo, string $deliveryDate): array {
    $soldToNorm = strtolower(trim($soldTo));

    $stmt = $conn->prepare("SELECT highlight_color, cell_styles
                            FROM delivery_highlight_memory
                            WHERE dataset_name = ?
                              AND invoice_no = ?
                              AND item_code = ?
                              AND serial_no = ?
                              AND sold_to = ?
                              AND delivery_date = ?
                            LIMIT 1");
    $stmt->bind_param('ssssss', $datasetName, $invoiceNo, $itemCode, $serialNo, $soldToNorm, $deliveryDate);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;

    if (!$row) {
        return ['highlight_color' => '', 'cell_styles' => ''];
    }

    return [
        'highlight_color' => trim((string)($row['highlight_color'] ?? '')),
        'cell_styles' => trim((string)($row['cell_styles'] ?? '')),
    ];
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
    'Date' => 'record_date',
    'DATE' => 'record_date',
    'date' => 'record_date',
    'Order Date' => 'record_date',
    
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
    
    // Company name variations
    'Company_Name' => 'company_name',
    'Company' => 'company_name',
    'Client' => 'company_name',
    'Customer' => 'company_name',

    // Sold To variations
    'SOLD TO' => 'sold_to',
    'Sold To' => 'sold_to',
    'SoldTo' => 'sold_to',
    'sold_to' => 'sold_to',
    'SOLD TO COMPANIES' => 'sold_to',
    'Sold To Companies' => 'sold_to',
    
    // Status variations
    'Status' => 'status',
    'STATUS' => 'status',
    'status' => 'status',

    // Excel highlight / fill color variations
    'Highlight Color' => 'highlight_color',
    'HIGHLIGHT COLOR' => 'highlight_color',
    'highlight_color' => 'highlight_color',
    'Sheet Color' => 'highlight_color',
    'SHEET COLOR' => 'highlight_color',
    'sheet_color' => 'highlight_color',
    'Fill Color' => 'highlight_color',
    'FILL COLOR' => 'highlight_color',
    'fill_color' => 'highlight_color',

    // Imported per-cell style JSON
    'Cell Styles' => 'cell_styles',
    'CELL STYLES' => 'cell_styles',
    'cell_styles' => 'cell_styles',
    
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
    
    // Groupings / Category / Color / By Color variations
    'Groupings' => 'groupings',
    'GROUPINGS' => 'groupings',
    'groupings' => 'groupings',
    'Category' => 'groupings',
    'CATEGORY' => 'groupings',
    'category' => 'groupings',
    'Grouping' => 'groupings',
    'Group' => 'groupings',
    'By Color' => 'groupings',
    'BY COLOR' => 'groupings',
    'by color' => 'groupings',
    'Color' => 'groupings',
    'COLOR' => 'groupings',
    'color' => 'groupings',
    'Type' => 'groupings',
    'TYPE' => 'groupings',
    'type' => 'groupings',
    'Classification' => 'groupings',
    'CLASSIFICATION' => 'groupings',
    'classification' => 'groupings',
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
    if ($isMysql) {
        $soldToCol = $conn->query("SHOW COLUMNS FROM delivery_records LIKE 'sold_to'");
        if (!$soldToCol || $soldToCol->num_rows === 0) {
            $conn->query("ALTER TABLE delivery_records ADD COLUMN sold_to VARCHAR(255) DEFAULT NULL AFTER company_name");
        }
        $highlightCol = $conn->query("SHOW COLUMNS FROM delivery_records LIKE 'highlight_color'");
        if (!$highlightCol || $highlightCol->num_rows === 0) {
            $conn->query("ALTER TABLE delivery_records ADD COLUMN highlight_color VARCHAR(20) DEFAULT NULL AFTER status");
        }
        $cellStylesCol = $conn->query("SHOW COLUMNS FROM delivery_records LIKE 'cell_styles'");
        if (!$cellStylesCol || $cellStylesCol->num_rows === 0) {
            $conn->query("ALTER TABLE delivery_records ADD COLUMN cell_styles LONGTEXT DEFAULT NULL AFTER highlight_color");
        }
    } else {
        $hasSoldTo = false;
        $chkSoldTo = $conn->query('PRAGMA table_info(delivery_records)');
        if ($chkSoldTo) {
            while ($r = $chkSoldTo->fetch_assoc()) {
                if (strtolower($r['name']) === 'sold_to') { $hasSoldTo = true; break; }
            }
        }
        if (!$hasSoldTo) {
            $conn->query('ALTER TABLE delivery_records ADD COLUMN sold_to VARCHAR(255) DEFAULT NULL');
        }
    }
    // Get dataset_name from request (e.g. data1, data2)
    $dataset_name = isset($request['dataset_name']) ? trim(strval($request['dataset_name'])) : '';
    if (empty($dataset_name)) $dataset_name = 'data1';

    ensureHighlightMemoryTable($conn, $isMysql);

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
            $sold_to = isset($mapped['sold_to']) ? trim(strval($mapped['sold_to'])) : '';
            $status = isset($mapped['status']) ? trim(strval($mapped['status'])) : 'Delivered';
            $uom = isset($mapped['uom']) ? trim(strval($mapped['uom'])) : '';
            // Year starts at 0; will be filled from explicit YEAR column, delivery_date, or current year
            $year = isset($mapped['year']) ? intval($mapped['year']) : 0;
            
            // Handle dates
            $record_date = null;
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
            
            // Try the generic Excel Date column first (kept separate from Date Delivered)
            if (!empty($mapped['record_date'])) {
                $record_date = excelDateToDate($mapped['record_date']);
                if ($record_date) {
                    if (empty($delivery_month))  $delivery_month = getMonthFromDate($record_date);
                    if ($delivery_day == 0)       $delivery_day   = getDayFromDate($record_date);
                    if ($year <= 0)               $year           = intval(date('Y', strtotime($record_date)));
                }
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
            if ($sold_to == '-') $sold_to = '';
            
            // Handle UOM - store in its own column (no default)
            if ($uom == '-') $uom = '';
            
            // Extract sold_to_month, sold_to_day, groupings
            $sold_to_month = isset($mapped['sold_to_month']) ? trim(strval($mapped['sold_to_month'])) : '';
            $sold_to_day = isset($mapped['sold_to_day']) ? intval($mapped['sold_to_day']) : 0;
            $groupings = isset($mapped['groupings']) ? trim(strval($mapped['groupings'])) : '';
            $highlight_color = isset($mapped['highlight_color']) ? trim(strval($mapped['highlight_color'])) : '';
            $cell_styles = '';

            if (isset($mapped['cell_styles'])) {
                $rawCellStyles = $mapped['cell_styles'];
                if (is_array($rawCellStyles)) {
                    $mappedCellStyles = [];
                    foreach ($rawCellStyles as $sourceField => $colorValue) {
                        $sourceKey = strtolower(trim((string) $sourceField));
                        if (!isset($lower_mappings[$sourceKey])) {
                            continue;
                        }

                        $mappedField = $lower_mappings[$sourceKey];
                        if (is_array($colorValue)) {
                            $styleEntry = [];
                            $bg = trim((string) ($colorValue['bg'] ?? ''));
                            $text = trim((string) ($colorValue['text'] ?? ''));

                            if ($bg !== '' && $bg !== '-') {
                                if ($bg[0] !== '#') $bg = '#' . $bg;
                                $styleEntry['bg'] = $bg;
                            }

                            if ($text !== '' && $text !== '-') {
                                if ($text[0] !== '#') $text = '#' . $text;
                                $styleEntry['text'] = $text;
                            }

                            if (!empty($styleEntry)) {
                                $mappedCellStyles[$mappedField] = $styleEntry;
                            }
                            continue;
                        }

                        $colorString = trim((string) $colorValue);
                        if ($colorString === '' || $colorString === '-') {
                            continue;
                        }

                        if ($colorString[0] !== '#') {
                            $colorString = '#' . $colorString;
                        }

                        $mappedCellStyles[$mappedField] = ['bg' => $colorString];
                    }

                    if (!empty($mappedCellStyles)) {
                        $cell_styles = json_encode($mappedCellStyles, JSON_UNESCAPED_SLASHES);
                    }
                } else {
                    $cell_styles = trim((string) $rawCellStyles);
                }
            }

            if ($highlight_color === '' && !empty($cell_styles)) {
                $decodedStyles = json_decode($cell_styles, true);
                if (is_array($decodedStyles)) {
                    foreach ($decodedStyles as $colorValue) {
                        if (is_array($colorValue)) {
                            $bgColor = trim((string) ($colorValue['bg'] ?? ''));
                            $textColor = trim((string) ($colorValue['text'] ?? ''));
                            if ($bgColor !== '') {
                                $highlight_color = $bgColor;
                                break;
                            }
                            if ($textColor !== '') {
                                $highlight_color = $textColor;
                                break;
                            }
                            continue;
                        }

                        $flatColor = trim((string) $colorValue);
                        if ($flatColor !== '') {
                            $highlight_color = $flatColor;
                            break;
                        }
                    }
                }
            }

            // Restore previously saved highlights when this dataset was deleted and imported again.
            if ((trim($highlight_color) === '' && trim($cell_styles) === '') && !empty($invoice_no) && !empty($item_code)) {
                $preserved = findPreservedHighlight(
                    $conn,
                    $dataset_name,
                    $invoice_no,
                    $item_code,
                    $serial_no,
                    $sold_to,
                    (string)$delivery_date
                );

                if (!empty($preserved['highlight_color'])) {
                    $highlight_color = $preserved['highlight_color'];
                }
                if (!empty($preserved['cell_styles'])) {
                    $cell_styles = $preserved['cell_styles'];
                }
            }
            
            // Handle "-" values
            if ($sold_to_month == '-') $sold_to_month = '';
            if ($groupings == '-') $groupings = '';
            if ($highlight_color == '-') $highlight_color = '';
            if ($cell_styles == '-') $cell_styles = '';
            if ($delivery_month == '-') $delivery_month = '';

            // If category/groupings is blank, infer it from text fields that often contain sheet labels.
            if (empty($groupings)) {
                $mappedTextParts = [];
                foreach ($mapped as $mappedValue) {
                    if (is_scalar($mappedValue)) {
                        $mappedTextParts[] = strval($mappedValue);
                    }
                }

                $inferenceSource = implode(' ', [
                    $invoice_no,
                    $serial_no,
                    $item_code,
                    $item_name,
                    $company_name,
                    $sold_to,
                    $status,
                    $notes,
                    $delivery_date,
                    isset($mapped['record_date']) ? strval($mapped['record_date']) : '',
                    implode(' ', $mappedTextParts),
                ]);
                $inferredGrouping = inferGroupingFromText($inferenceSource);
                if (!empty($inferredGrouping)) {
                    $groupings = $inferredGrouping;
                }
            }

            // If still blank, infer from row/cell colors (fill or font) captured during upload parsing.
            if (empty($groupings)) {
                $inferredByColor = inferGroupingFromStyles($highlight_color, $cell_styles);
                if (!empty($inferredByColor)) {
                    $groupings = $inferredByColor;
                }
            }
            
            // Default status if empty
            if (empty($status) || $status == '-') {
                $status = 'Delivered';
            }
            
            // Don't set default delivery month/day - only store what's in the Excel
            // Don't auto-fill quantity - if Excel has no quantity, leave it as 0/empty

            // Insert into database
            $sql = "INSERT INTO delivery_records 
                    (invoice_no, serial_no, delivery_month, delivery_day, delivery_year, record_date, delivery_date, item_code, item_name, company_name, sold_to, quantity, status, highlight_color, cell_styles, notes, uom, sold_to_month, sold_to_day, groupings, dataset_name)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . ($conn->error ?? 'Unknown error'));
            }

            // Types: s=invoice_no, s=serial_no, s=delivery_month, i=delivery_day,
            //        i=delivery_year, s=record_date, s=delivery_date, s=item_code, s=item_name,
            //        s=company_name, s=sold_to, i=quantity, s=status, s=highlight_color, s=cell_styles, s=notes, s=uom,
            //        s=sold_to_month, i=sold_to_day, s=groupings, s=dataset_name
            $stmt->bind_param(
                'sssiissssssissssssiss',
                $invoice_no,
                $serial_no,
                $delivery_month,
                $delivery_day,
                $year,
                $record_date,
                $delivery_date,
                $item_code,
                $item_name,
                $company_name,
                $sold_to,
                $quantity,
                $status,
                $highlight_color,
                $cell_styles,
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
