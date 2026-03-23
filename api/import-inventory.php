<?php
// ABSOLUTE MINIMUM setup - nothing else
header('Content-Type: application/json; charset=utf-8');
ob_start();

// Emergency response function
function respond($success, $message, $code = 200, $data = []) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit(0);
}

// Catch literally everything
try {
    // 1. Check method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, 'Only POST allowed', 405);
    }

    // 2. Get input
    $raw = @file_get_contents('php://input');
    if (!$raw) {
        respond(false, 'No data sent', 400);
    }

    // 3. Parse JSON
    $input = @json_decode($raw, true);
    if ($input === null) {
        respond(false, 'Invalid JSON', 400);
    }

    // 4. Check data field
    if (!isset($input['data'])) {
        respond(false, 'Missing data field', 400);
    }

    if (!is_array($input['data'])) {
        respond(false, 'Data must be array', 400);
    }

    // 5. Get DB connection
    require __DIR__ . '/../db_config.php';
    
    if (!isset($conn)) {
        respond(false, 'Database not available', 500);
    }

    // 6. Extract request data
    $data = $input['data'];
    $filename = $input['filename'] ?? 'upload';
    $datasetName = 'Inventory - ' . pathinfo($filename, PATHINFO_FILENAME);

    // 7. Validate rows exist
    if (count($data) < 1) {
        respond(false, 'No rows in data', 400);
    }

    // 8. Get header and columns
    $header = $data[0];
    if (!is_array($header)) {
        respond(false, 'Header is not array', 400);
    }

    $cols = array_keys($header);
    $map = [];

    // 9. Map required columns
    $required = ['ITEMS', 'DESCRIPTION', 'UOM', 'INVENTORY'];
    foreach ($required as $req) {
        $found = false;
        foreach ($cols as $col) {
            if (strtoupper(trim($col)) === strtoupper(trim($req))) {
                $map[$req] = $col;
                $found = true;
                break;
            }
        }
        if (!$found) {
            respond(false, "Missing column: $req", 400);
        }
    }

    // 10. Map optional columns
    foreach (['BOX', 'STATUS', 'NOTES'] as $opt) {
        foreach ($cols as $col) {
            if (strtoupper(trim($col)) === strtoupper(trim($opt))) {
                $map[$opt] = $col;
                break;
            }
        }
    }

    // 11. Process rows - use direct queries (simpler and more reliable)
    $imported = 0;
    $failed = 0;
    $errors = [];
    $now = date('Y-m-d H:i:s');
    $current_year = date('Y');

    @$conn->begin_transaction();

    for ($i = 1; $i < count($data); $i++) {
        $row = $data[$i];

        if (!is_array($row) || empty(array_filter($row))) {
            $failed++;
            $errors[] = "Row " . ($i + 1) . ": Empty row (skipped)";
            continue;
        }

        $box = isset($map['BOX'], $row[$map['BOX']]) ? trim($row[$map['BOX']]) : '';
        $modelNo = isset($row[$map['ITEMS']]) ? trim($row[$map['ITEMS']]) : '';
        $desc = isset($row[$map['DESCRIPTION']]) ? trim($row[$map['DESCRIPTION']]) : '';
        $qty = isset($row[$map['INVENTORY']]) ? intval($row[$map['INVENTORY']]) : 0;

        if (!$modelNo) {
            $failed++;
            $errors[] = "Row " . ($i + 1) . ": No Items";
            continue;
        }

        $code = $box ? $box . '-' . $modelNo : $modelNo;
        $name = $desc ?: $modelNo;
        $src = "Upload: $filename";

        // Escape for SQL
        $code_esc = $conn->real_escape_string($code);
        $name_esc = $conn->real_escape_string($name);
        $src_esc = $conn->real_escape_string($src);
        $box_esc = $conn->real_escape_string($box);
        $modelNo_esc = $conn->real_escape_string($modelNo);

        // Always INSERT as new record (don't check for duplicates)
        // This allows same item codes to be imported multiple times
        $sql = "INSERT INTO delivery_records (item_code, item_name, box_code, model_no, quantity, company_name, notes, status, delivery_month, delivery_day, delivery_year, dataset_name, created_at, updated_at) VALUES ('$code_esc', '$name_esc', '$box_esc', '$modelNo_esc', $qty, 'Stock Addition', '$src_esc', 'Inventory', 'Inventory', 1, $current_year, '$datasetName', '$now', '$now')";
        
        $result = $conn->query($sql);
        if ($result) {
            $imported++;
        } else {
            $failed++;
            $errors[] = "Row " . ($i + 1) . " INSERT failed for '$modelNo_esc': " . ($conn->error ?: "Unknown error");
        }
    }

    // Commit changes
    try {
        if ($imported > 0) {
            @$conn->commit();
        } else {
            @$conn->rollback();
        }
    } catch (Exception $e) {
        try { @$conn->rollback(); } catch (Exception $x) {}
    }

    // Verify
    usleep(500000);
    $verify = @$conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name='Stock Addition'");
    $verifyRow = $verify ? @$verify->fetch_assoc() : null;
    $savedCount = $verifyRow ? intval($verifyRow['cnt']) : 0;

    if (method_exists($conn, 'close')) @$conn->close();

    respond(true, "$imported items added! Total in database: $savedCount", 200, [
        'imported' => $imported,
        'failed' => $failed,
        'errors' => $errors,
        'verified_total' => $savedCount
    ]);

} catch (Exception $e) {
    respond(false, $e->getMessage(), 500);
} catch (Error $e) {
    respond(false, $e->getMessage(), 500);
}
?>

