<?php
header('Content-Type: application/json; charset=utf-8');
ob_start();

function respond($success, $message, $code = 200, $data = []) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code($code);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

function normalizeKey($value) {
    $value = strtolower(trim((string) $value));
    return preg_replace('/[^a-z0-9]/', '', $value);
}

function getRowValue(array $row, array $aliases) {
    $normalized = [];
    foreach ($row as $k => $v) {
        $normalized[normalizeKey($k)] = $v;
    }

    foreach ($aliases as $alias) {
        $key = normalizeKey($alias);
        if (array_key_exists($key, $normalized)) {
            return is_string($normalized[$key]) ? trim($normalized[$key]) : $normalized[$key];
        }
    }

    return null;
}

function toFloat($value, $default = 0.0) {
    if ($value === null || $value === '') {
        return $default;
    }
    if (is_string($value)) {
        $value = str_replace([',', ' '], '', $value);
    }
    return is_numeric($value) ? floatval($value) : $default;
}

function normalizeDate($value) {
    if ($value === null || $value === '') {
        return date('Y-m-d');
    }

    if (is_numeric($value)) {
        $excelDate = floatval($value);
        $unix = intval(($excelDate - 25569) * 86400);
        if ($unix > 0) {
            return gmdate('Y-m-d', $unix);
        }
    }

    $ts = strtotime((string) $value);
    if ($ts === false) {
        return date('Y-m-d');
    }

    return date('Y-m-d', $ts);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, 'Only POST allowed', 405);
    }

    $raw = file_get_contents('php://input');
    if (!$raw) {
        respond(false, 'No data sent', 400);
    }

    $input = json_decode($raw, true);
    if (!is_array($input)) {
        respond(false, 'Invalid JSON', 400);
    }

    if (!isset($input['data']) || !is_array($input['data']) || count($input['data']) === 0) {
        respond(false, 'No rows to import', 400);
    }

    require __DIR__ . '/../db_config.php';

    if (!isset($conn)) {
        respond(false, 'Database connection not available', 500);
    }

    $filename = trim((string) ($input['filename'] ?? 'orders-upload'));
    $baseDatasetName = 'Orders - ' . pathinfo($filename, PATHINFO_FILENAME);
    $datasetName = substr($baseDatasetName, 0, 50);

    // Ensure dataset_name column exists for both MySQL and SQLite.
    $isMysql = ($conn instanceof mysqli);
    $datasetColumnExists = false;

    if ($isMysql) {
        $chk = $conn->query("SHOW COLUMNS FROM delivery_records LIKE 'dataset_name'");
        $datasetColumnExists = ($chk && $chk->num_rows > 0);
    } else {
        $chk = $conn->query('PRAGMA table_info(delivery_records)');
        if ($chk) {
            while ($c = $chk->fetch_assoc()) {
                if (strtolower($c['name']) === 'dataset_name') {
                    $datasetColumnExists = true;
                    break;
                }
            }
        }
    }

    if (!$datasetColumnExists) {
        $conn->query('ALTER TABLE delivery_records ADD COLUMN dataset_name VARCHAR(50) DEFAULT NULL');
    }

    $insertSql = "INSERT INTO delivery_records (
        invoice_no, serial_no, delivery_month, delivery_day, delivery_year, delivery_date,
        item_code, item_name, company_name, quantity, status, notes,
        order_customer, order_date, unit_price, total_amount, po_number, po_status,
        dataset_name, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Orders', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        respond(false, 'Failed to prepare import query: ' . ($conn->error ?? 'unknown'), 500);
    }

    $imported = 0;
    $failed = 0;
    $errors = [];

    if (method_exists($conn, 'begin_transaction')) {
        @$conn->begin_transaction();
    }

    foreach ($input['data'] as $index => $row) {
        $rowNo = $index + 2;

        if (!is_array($row)) {
            $failed++;
            $errors[] = 'Row ' . $rowNo . ': Invalid row format';
            continue;
        }

        $customer = trim((string) getRowValue($row, ['order customer', 'customer', 'client', 'sold to', 'company', 'order_customer']));
        $orderDateRaw = getRowValue($row, ['order date', 'date', 'order_date']);
        $itemCode = trim((string) getRowValue($row, ['item code', 'item', 'model no', 'model', 'items', 'product code', 'item_code']));
        $itemName = trim((string) getRowValue($row, ['item name', 'description', 'product name', 'item_name']));
        $qtyValue = getRowValue($row, ['quantity', 'qty', 'qty.', 'order qty']);
        $unitPriceValue = getRowValue($row, ['unit price', 'price', 'unit_price']);
        $totalAmountValue = getRowValue($row, ['total amount', 'amount', 'sales amount', 'total_amount']);
        $poNumber = trim((string) getRowValue($row, ['po number', 'po no', 'po #', 'po_number']));
        $poStatus = trim((string) getRowValue($row, ['po status', 'po_status']));
        $invoiceNo = trim((string) getRowValue($row, ['invoice no', 'invoice no.', 'invoice', 'invoice_no']));
        $serialNo = trim((string) getRowValue($row, ['serial no', 'serial no.', 'serial_no']));
        $status = trim((string) getRowValue($row, ['status', 'order status']));
        $notes = trim((string) getRowValue($row, ['notes', 'remarks', 'comment']));

        if ($itemCode === '' && $itemName !== '') {
            $itemCode = preg_replace('/\s+/', '-', strtoupper($itemName));
        }
        if ($itemName === '' && $itemCode !== '') {
            $itemName = $itemCode;
        }

        $quantity = intval(round(toFloat($qtyValue, 0)));
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $unitPrice = toFloat($unitPriceValue, 0);
        $totalAmount = toFloat($totalAmountValue, 0);
        if ($totalAmount <= 0) {
            $totalAmount = $quantity * $unitPrice;
        }

        if ($customer === '' || $itemCode === '') {
            $failed++;
            $errors[] = 'Row ' . $rowNo . ': Missing required Customer or Item Code';
            continue;
        }

        $orderDate = normalizeDate($orderDateRaw);
        $orderTs = strtotime($orderDate);
        if ($orderTs === false) {
            $orderTs = time();
            $orderDate = date('Y-m-d', $orderTs);
        }

        $deliveryMonth = date('F', $orderTs);
        $deliveryDay = intval(date('j', $orderTs));
        $deliveryYear = intval(date('Y', $orderTs));

        if ($poStatus === '') {
            $poStatus = ($poNumber !== '') ? 'Pending' : 'No PO';
        }

        $allowedPo = ['No PO', 'Pending', 'Received'];
        if (!in_array($poStatus, $allowedPo, true)) {
            $poStatus = ($poNumber !== '') ? 'Pending' : 'No PO';
        }

        if ($status === '') {
            $status = 'Pending';
        }

        $stmt->bind_param(
            'sssiisssissssddsss',
            $invoiceNo,
            $serialNo,
            $deliveryMonth,
            $deliveryDay,
            $deliveryYear,
            $orderDate,
            $itemCode,
            $itemName,
            $quantity,
            $status,
            $notes,
            $customer,
            $orderDate,
            $unitPrice,
            $totalAmount,
            $poNumber,
            $poStatus,
            $datasetName
        );

        if ($stmt->execute()) {
            $imported++;
        } else {
            $failed++;
            $errors[] = 'Row ' . $rowNo . ': ' . ($stmt->error ?: 'Insert failed');
        }
    }

    if (method_exists($conn, 'commit')) {
        if ($imported > 0) {
            @$conn->commit();
        } else {
            @$conn->rollback();
        }
    }

    $stmt->close();

    respond(true, 'Orders import completed', 200, [
        'imported' => $imported,
        'failed' => $failed,
        'errors' => $errors,
        'dataset_name' => $datasetName
    ]);
} catch (Throwable $e) {
    respond(false, $e->getMessage(), 500);
}
