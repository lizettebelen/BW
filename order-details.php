<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

function so_id($id) {
    return 'SO-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT);
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$orderId = intval($_GET['id'] ?? 0);
if ($orderId <= 0) {
    header('Location: orders.php', true, 302);
    exit;
}

$flash = $_SESSION['order_detail_flash'] ?? null;
$openDeliveryModal = !empty($_SESSION['order_detail_open_delivery_modal']);
unset($_SESSION['order_detail_flash']);
unset($_SESSION['order_detail_open_delivery_modal']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_order') {
        $customer = trim($_POST['customer'] ?? '');
        $orderDate = trim($_POST['order_date'] ?? '');
        $itemCode = trim($_POST['item_code'] ?? '');
        $itemName = trim($_POST['item_name'] ?? '');
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        $unitPrice = max(0, floatval($_POST['unit_price'] ?? 0));
        $poNumber = trim($_POST['po_number'] ?? '');
        $poStatus = trim($_POST['po_status'] ?? 'No PO');
        $status = trim($_POST['status'] ?? 'Pending');
        $notes = trim($_POST['notes'] ?? '');

        if ($customer === '' || $orderDate === '' || $itemCode === '' || $itemName === '') {
            $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Customer, date, item code, and item name are required.'];
            header('Location: order-details.php?id=' . $orderId, true, 302);
            exit;
        }

        $allowedPoStatus = ['No PO', 'Pending', 'Received'];
        if (!in_array($poStatus, $allowedPoStatus, true)) {
            $poStatus = 'No PO';
        }

        $allowedStatus = ['Pending', 'Ready for Delivery', 'In Transit', 'Delivered'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'Pending';
        }

        $dt = DateTime::createFromFormat('Y-m-d', $orderDate);
        if (!$dt) {
            $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Invalid order date format.'];
            header('Location: order-details.php?id=' . $orderId, true, 302);
            exit;
        }

        $deliveryMonth = $dt->format('F');
        $deliveryDay = intval($dt->format('j'));
        $deliveryYear = intval($dt->format('Y'));
        $totalAmount = $quantity * $unitPrice;

        $updateSql = "UPDATE delivery_records
                      SET order_customer = ?, order_date = ?, item_code = ?, item_name = ?,
                          quantity = ?, unit_price = ?, total_amount = ?, po_number = ?,
                          po_status = ?, status = ?, notes = ?, delivery_month = ?,
                          delivery_day = ?, delivery_year = ?, delivery_date = ?, updated_at = CURRENT_TIMESTAMP
                      WHERE id = ? AND company_name = 'Orders'";

        $stmt = $conn->prepare($updateSql);
        if ($stmt) {
            $stmt->bind_param(
                'ssssiddsssssiisi',
                $customer,
                $orderDate,
                $itemCode,
                $itemName,
                $quantity,
                $unitPrice,
                $totalAmount,
                $poNumber,
                $poStatus,
                $status,
                $notes,
                $deliveryMonth,
                $deliveryDay,
                $deliveryYear,
                $orderDate,
                $orderId
            );

            if ($stmt->execute()) {
                $_SESSION['order_detail_flash'] = ['type' => 'success', 'message' => 'Order updated successfully.'];
            } else {
                $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Failed to update order: ' . $stmt->error];
            }
            $stmt->close();
        } else {
            $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Failed to prepare order update query.'];
        }

        header('Location: order-details.php?id=' . $orderId, true, 302);
        exit;
    }

    if ($action === 'generate_invoice') {
        $invoice = 'INV-' . date('Y') . '-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("UPDATE delivery_records
                               SET invoice_no = ?,
                                   status = CASE
                                       WHEN status IS NULL OR status = '' OR status = 'Pending' THEN 'Ready for Delivery'
                                       ELSE status
                                   END,
                                   updated_at = CURRENT_TIMESTAMP
                               WHERE id = ? AND company_name = 'Orders'");
        if ($stmt) {
            $stmt->bind_param('si', $invoice, $orderId);
            if ($stmt->execute()) {
                $_SESSION['order_detail_flash'] = ['type' => 'success', 'message' => 'Invoice generated: ' . $invoice . ' (status set to Ready for Delivery)'];
            } else {
                $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Failed to generate invoice.'];
            }
            $stmt->close();
        }
        header('Location: order-details.php?id=' . $orderId, true, 302);
        exit;
    }

    if ($action === 'mark_ready_for_delivery') {
        $fetch = $conn->prepare("SELECT * FROM delivery_records WHERE id = ? AND company_name = 'Orders' LIMIT 1");
        if ($fetch) {
            $fetch->bind_param('i', $orderId);
            $fetch->execute();
            $res = $fetch->get_result();
            $order = $res ? $res->fetch_assoc() : null;
            $fetch->close();

            if (!$order) {
                $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Order not found.'];
                header('Location: orders.php', true, 302);
                exit;
            }

            if (empty($order['invoice_no'])) {
                $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Generate invoice first before marking as ready for delivery.'];
                header('Location: order-details.php?id=' . $orderId, true, 302);
                exit;
            }

            $upd = $conn->prepare("UPDATE delivery_records SET status = 'Ready for Delivery', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND company_name = 'Orders'");
            if ($upd) {
                $upd->bind_param('i', $orderId);
                if ($upd->execute()) {
                    $_SESSION['order_detail_flash'] = ['type' => 'success', 'message' => 'Order marked as ready for delivery.'];
                } else {
                    $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Failed to mark order as ready.'];
                }
                $upd->close();
            }
            header('Location: order-details.php?id=' . $orderId, true, 302);
            exit;
        }
    }

    if ($action === 'create_delivery_record') {
        $fetch = $conn->prepare("SELECT * FROM delivery_records WHERE id = ? AND company_name = 'Orders' LIMIT 1");
        if ($fetch) {
            $fetch->bind_param('i', $orderId);
            $fetch->execute();
            $res = $fetch->get_result();
            $order = $res ? $res->fetch_assoc() : null;
            $fetch->close();

            if (!$order) {
                $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Order not found.'];
                header('Location: orders.php', true, 302);
                exit;
            }

            if (empty($order['invoice_no'])) {
                $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Generate invoice first before creating delivery record.'];
                header('Location: order-details.php?id=' . $orderId, true, 302);
                exit;
            }

            $orderRef = so_id($orderId);
            $customerName = trim((string) ($order['order_customer'] ?? ''));
            if ($customerName === '') {
                $customerName = 'Unknown Customer';
            }

            $deliveryDate = trim($_POST['delivery_date'] ?? '');
            $deliveryMonth = trim($_POST['delivery_month'] ?? '');
            $deliveryDay = intval($_POST['delivery_day'] ?? 0);
            $deliveryYear = intval($_POST['delivery_year'] ?? 0);
            $uom = trim($_POST['uom'] ?? '');
            $serialNo = trim($_POST['serial_no'] ?? '');
            $soldTo = trim($_POST['sold_to'] ?? $customerName);
            $soldToMonth = trim($_POST['sold_to_month'] ?? '');
            $soldToDay = intval($_POST['sold_to_day'] ?? 0);
            $groupings = trim($_POST['groupings'] ?? '');
            $deliveryStatus = trim($_POST['delivery_status'] ?? 'Pending');
            $remarks = trim($_POST['delivery_remarks'] ?? '');

            if ($deliveryDate === '' || $uom === '' || $serialNo === '' || $soldTo === '' || $soldToMonth === '' || $soldToDay <= 0 || $groupings === '') {
                $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Please fill all required delivery fields before creating the record.'];
                $_SESSION['order_detail_open_delivery_modal'] = true;
                header('Location: order-details.php?id=' . $orderId, true, 302);
                exit;
            }

            $deliveryDt = DateTime::createFromFormat('Y-m-d', $deliveryDate);
            if (!$deliveryDt) {
                $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Invalid delivery date format.'];
                $_SESSION['order_detail_open_delivery_modal'] = true;
                header('Location: order-details.php?id=' . $orderId, true, 302);
                exit;
            }

            if ($deliveryMonth === '') {
                $deliveryMonth = $deliveryDt->format('F');
            }
            if ($deliveryDay <= 0) {
                $deliveryDay = intval($deliveryDt->format('j'));
            }
            if ($deliveryYear <= 0) {
                $deliveryYear = intval($deliveryDt->format('Y'));
            }

            $allowedStatuses = ['Pending', 'In Transit', 'Delivered', 'Cancelled'];
            if (!in_array($deliveryStatus, $allowedStatuses, true)) {
                $deliveryStatus = 'Pending';
            }

            $deliveryNotes = trim((string) ($order['notes'] ?? ''));
            if ($remarks !== '') {
                $deliveryNotes = trim($deliveryNotes . ' | ' . $remarks);
            }
            $deliveryNotes = trim($deliveryNotes . ' | From Order ' . $orderRef);

            // Check if delivery record already exists for this order
            $existing = $conn->prepare("SELECT id FROM delivery_records WHERE company_name != 'Orders' AND invoice_no = ? AND notes LIKE ? LIMIT 1");
            $already = false;
            if ($existing) {
                $notesLike = '%' . $orderRef . '%';
                $existing->bind_param('ss', $order['invoice_no'], $notesLike);
                $existing->execute();
                $existingRes = $existing->get_result();
                $already = ($existingRes && $existingRes->num_rows > 0);
                $existing->close();
            }

            $redirectUrl = 'order-details.php?id=' . $orderId;

            if ($already) {
                $_SESSION['order_detail_flash'] = ['type' => 'warning', 'message' => 'Delivery record already exists for this order.'];
            } else {
                $activeDataset = trim((string) ($_SESSION['active_dataset'] ?? ''));
                if ($activeDataset === '' || strtolower($activeDataset) === 'all') {
                    $activeDataset = 'Orders Delivery';
                }

                $insert = $conn->prepare("INSERT INTO delivery_records (
                            invoice_no, delivery_month, delivery_day, delivery_year, delivery_date,
                            item_code, item_name, company_name, sold_to, quantity, unit_price, status, notes,
                            uom, serial_no, sold_to_month, sold_to_day, groupings, dataset_name,
                            created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                if ($insert) {
                    $qty = intval($order['quantity'] ?? 0);
                    $unitPriceFromOrder = floatval($order['unit_price'] ?? 0);
                    $itemCode = (string) ($order['item_code'] ?? '');
                    $itemName = (string) ($order['item_name'] ?? '');
                    $invoice = (string) ($order['invoice_no'] ?? '');
                    $insert->bind_param(
                        'ssiisssssidsssssiss',
                        $invoice,
                        $deliveryMonth,
                        $deliveryDay,
                        $deliveryYear,
                        $deliveryDate,
                        $itemCode,
                        $itemName,
                        $customerName,
                        $soldTo,
                        $qty,
                        $unitPriceFromOrder,
                        $deliveryStatus,
                        $deliveryNotes
                        ,$uom,
                        $serialNo,
                        $soldToMonth,
                        $soldToDay,
                        $groupings,
                        $activeDataset
                    );
                    if ($insert->execute()) {
                        $updateOrderStatus = $conn->prepare("UPDATE delivery_records SET status = 'In Transit', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND company_name = 'Orders'");
                        if ($updateOrderStatus) {
                            $updateOrderStatus->bind_param('i', $orderId);
                            $updateOrderStatus->execute();
                            $updateOrderStatus->close();
                        }
                        $_SESSION['order_detail_flash'] = ['type' => 'success', 'message' => 'Delivery record created successfully. Order data has been copied.'];
                        $redirectUrl = 'delivery-records.php?dataset=' . urlencode($activeDataset);
                    } else {
                        $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Failed to create delivery record: ' . $insert->error];
                        $_SESSION['order_detail_open_delivery_modal'] = true;
                    }
                    $insert->close();
                } else {
                    $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Failed to prepare delivery record insert.'];
                    $_SESSION['order_detail_open_delivery_modal'] = true;
                }
            }
            header('Location: ' . $redirectUrl, true, 302);
            exit;
        }
    }

    if ($action === 'mark_delivered') {
        $fetch = $conn->prepare("SELECT * FROM delivery_records WHERE id = ? AND company_name = 'Orders' LIMIT 1");
        if ($fetch) {
            $fetch->bind_param('i', $orderId);
            $fetch->execute();
            $res = $fetch->get_result();
            $order = $res ? $res->fetch_assoc() : null;
            $fetch->close();

            if (!$order) {
                $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Order not found.'];
                header('Location: orders.php', true, 302);
                exit;
            }

            if (empty($order['invoice_no'])) {
                $_SESSION['order_detail_flash'] = ['type' => 'error', 'message' => 'Generate invoice first before marking as delivered.'];
                header('Location: order-details.php?id=' . $orderId, true, 302);
                exit;
            }

            $orderRef = so_id($orderId);
            $customerName = trim((string) ($order['order_customer'] ?? ''));
            if ($customerName === '') {
                $customerName = 'Unknown Customer';
            }

            $today = new DateTime('now');
            $deliveryMonth = $today->format('F');
            $deliveryDay = intval($today->format('j'));
            $deliveryYear = intval($today->format('Y'));
            $deliveryDate = $today->format('Y-m-d');
            $deliveryNotes = trim((string) ($order['notes'] ?? ''));
            $deliveryNotes = trim($deliveryNotes . ' | From Sales Order ' . $orderRef);

            $existing = $conn->prepare("SELECT id FROM delivery_records WHERE company_name = ? AND invoice_no = ? AND notes LIKE ? LIMIT 1");
            $already = false;
            if ($existing) {
                $notesLike = '%' . $orderRef . '%';
                $existing->bind_param('sss', $customerName, $order['invoice_no'], $notesLike);
                $existing->execute();
                $existingRes = $existing->get_result();
                $already = ($existingRes && $existingRes->num_rows > 0);
                $existing->close();
            }

            if (!$already) {
                $insert = $conn->prepare("INSERT INTO delivery_records (
                            invoice_no, delivery_month, delivery_day, delivery_year, delivery_date,
                            item_code, item_name, company_name, quantity, status, notes,
                            created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Delivered', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                if ($insert) {
                    $qty = intval($order['quantity'] ?? 0);
                    $itemCode = (string) ($order['item_code'] ?? '');
                    $itemName = (string) ($order['item_name'] ?? '');
                    $invoice = (string) ($order['invoice_no'] ?? '');
                    $insert->bind_param(
                        'ssiissssis',
                        $invoice,
                        $deliveryMonth,
                        $deliveryDay,
                        $deliveryYear,
                        $deliveryDate,
                        $itemCode,
                        $itemName,
                        $customerName,
                        $qty,
                        $deliveryNotes
                    );
                    $insert->execute();
                    $insert->close();
                }
            }

            $upd = $conn->prepare("UPDATE delivery_records SET status = 'Delivered', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND company_name = 'Orders'");
            if ($upd) {
                $upd->bind_param('i', $orderId);
                $upd->execute();
                $upd->close();
            }

            $_SESSION['order_detail_flash'] = ['type' => 'success', 'message' => 'Order marked as delivered and synced to Delivery Records.'];
            header('Location: order-details.php?id=' . $orderId, true, 302);
            exit;
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM delivery_records WHERE id = ? AND company_name = 'Orders' LIMIT 1");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$result = $stmt->get_result();
$order = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$order) {
    header('Location: orders.php', true, 302);
    exit;
}

$unitPrice = floatval($order['unit_price'] ?? 0);
$totalAmount = floatval($order['total_amount'] ?? ($unitPrice * intval($order['quantity'] ?? 0)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - <?php echo h(so_id($orderId)); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .page-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:18px; flex-wrap:wrap; }
        .page-title { font-size:26px; color:#fff; margin:0; }
        .back-link { color:#a9c1dd; text-decoration:none; font-weight:600; }
        .flash { margin-bottom:14px; padding:10px 14px; border-radius:8px; font-size:14px; }
        .flash.success { background: rgba(46, 204, 113, 0.15); border: 1px solid rgba(46, 204, 113, 0.35); color: #b3f5cb; }
        .flash.error { background: rgba(231, 76, 60, 0.15); border: 1px solid rgba(231, 76, 60, 0.35); color: #ffd2cc; }
        .flash.warning { background: rgba(241, 196, 15, 0.15); border: 1px solid rgba(241, 196, 15, 0.35); color: #ffeeb0; }
        .grid { display:grid; grid-template-columns: 2fr 1fr; gap:16px; }
        .card { background:#13172c; border:1px solid rgba(255,255,255,.09); border-radius:12px; padding:20px; }
        .card h3 { margin:0 0 12px 0; color:#dce8f7; font-size:16px; font-weight:600; display:flex; align-items:center; gap:8px; }
        .card h3 i { font-size:18px; opacity:0.8; }
        .card-section { padding:16px 0; border-bottom:1px solid rgba(255,255,255,.06); }
        .card-section:last-child { border-bottom:none; padding-bottom:0; }
        .card-section h3 { margin-top:0; }
        .form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
        .form-group label { display:block; margin-bottom:6px; color:#b8c2cf; font-size:13px; font-weight:500; }
        .form-group input, .form-group select, .form-group textarea {
            width:100%; padding:10px 12px; border-radius:8px; border:1px solid rgba(255,255,255,.13);
            background:rgba(255,255,255,.03); color:#fff; font-family:inherit; font-size:14px;
        }
        .form-group textarea { min-height:90px; resize:vertical; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; }
        .btn {
            border:1px solid rgba(255,255,255,.14);
            border-radius:10px;
            padding:11px 16px;
            color:#fff;
            background:linear-gradient(135deg, #2f5fa7, #1f4174);
            cursor:pointer;
            font-weight:600;
            font-size:14px;
            display:inline-flex;
            align-items:center;
            gap:8px;
            transition:all 0.2s ease;
        }
        .btn:hover {
            opacity:0.9;
            transform:translateY(-1px);
        }
        .btn.secondary { background:rgba(255,255,255,.05); border-color:rgba(255,255,255,.14); }
        .btn.secondary:hover { background:rgba(255,255,255,.08); }
        .stat { font-size:12px; color:#9fb1c5; margin-bottom:6px; font-weight:500; text-transform:uppercase; letter-spacing:0.5px; }
        .value { font-size:20px; color:#fff; font-weight:700; margin-bottom:12px; }
        .pill { display:inline-block; border-radius:999px; padding:6px 12px; font-size:12px; font-weight:600; }
        .pill.no-po { background:rgba(231,76,60,.18); color:#ffd2cc; }
        .pill.pending { background:rgba(241,196,15,.18); color:#ffeeb0; }
        .pill.received { background:rgba(46,204,113,.18); color:#c6f7d8; }
        .pill.delivered { background:rgba(39,174,96,.22); color:#c9f3d9; }
        .delivery-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(5, 10, 22, 0.66);
            backdrop-filter: blur(3px);
            z-index: 1400;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }
        .delivery-modal.show { display: flex; }
        .delivery-modal-dialog {
            width: min(920px, 96vw);
            max-height: 92vh;
            overflow-y: auto;
            background:#13172c;
            border:1px solid rgba(255,255,255,.1);
            border-radius: 14px;
            padding: 18px;
        }
        .delivery-modal-header {
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:14px;
        }
        .delivery-modal-title { margin:0; font-size:18px; color:#dce8f7; }
        .delivery-modal-close {
            border:none;
            background:transparent;
            color:#b8c2cf;
            font-size:26px;
            cursor:pointer;
            line-height:1;
            padding:0 4px;
        }
        .delivery-form-hint { font-size:12px; color:#9fb1c5; margin:0 0 12px 0; }
        
        /* Light Mode Overrides */
        html.light-mode .page-title,
        body.light-mode .page-title { color: #1a1a1a; }
        
        html.light-mode .back-link,
        body.light-mode .back-link { color: #2f5fa7; }
        
        html.light-mode .card,
        body.light-mode .card { background: #ffffff; border-color: #d8e2ef; }
        
        html.light-mode .card h3,
        body.light-mode .card h3 { color: #1a1a1a; }

        html.light-mode .delivery-modal-dialog,
        body.light-mode .delivery-modal-dialog { background:#ffffff; border-color:#d8e2ef; }

        html.light-mode .delivery-modal-title,
        body.light-mode .delivery-modal-title { color:#1a1a1a; }

        html.light-mode .delivery-modal-close,
        body.light-mode .delivery-modal-close { color:#5a7088; }

        html.light-mode .delivery-form-hint,
        body.light-mode .delivery-form-hint { color:#5a7088; }
        
        html.light-mode .card-section,
        body.light-mode .card-section { border-bottom-color: #e8f0f8; }
        
        html.light-mode .form-group label,
        body.light-mode .form-group label { color: #3a4a5f; }
        
        html.light-mode .form-group input,
        html.light-mode .form-group select,
        html.light-mode .form-group textarea,
        body.light-mode .form-group input,
        body.light-mode .form-group select,
        body.light-mode .form-group textarea {
            border-color: #d8e2ef;
            background: #f5f8fc;
            color: #1a1a1a;
        }
        
        html.light-mode .stat,
        body.light-mode .stat { color: #5a7088; }
        
        html.light-mode .value,
        body.light-mode .value { color: #1a1a1a; }
        
        html.light-mode .pill.no-po,
        body.light-mode .pill.no-po { background: #fee5e0; color: #8b3a2f; }
        
        html.light-mode .pill.pending,
        body.light-mode .pill.pending { background: #fef3d4; color: #8b7a1f; }
        
        html.light-mode .pill.received,
        body.light-mode .pill.received { background: #d9f5e9; color: #2d7a47; }
        
        html.light-mode .pill.delivered,
        body.light-mode .pill.delivered { background: #d9f5e9; color: #2d7a47; }
        
        html.light-mode .flash.success,
        body.light-mode .flash.success { background: #d9f5e9; border-color: #6ee7b7; color: #0d5c3f; }
        
        html.light-mode .flash.error,
        body.light-mode .flash.error { background: #fee5e0; border-color: #f887ad; color: #8b3a2f; }
        
        html.light-mode .flash.warning,
        body.light-mode .flash.warning { background: #fef3d4; border-color: #fcc93a; color: #8b7a1f; }
        
        html.light-mode .btn,
        body.light-mode .btn {
            border-color: #2f5fa7;
            background: linear-gradient(135deg, #2f5fa7, #1f4174);
            color: #ffffff;
        }
        
        html.light-mode .btn:hover,
        body.light-mode .btn:hover {
            background: linear-gradient(135deg, #3d6cbf, #2a5284);
        }
        
        html.light-mode .btn.secondary,
        body.light-mode .btn.secondary {
            border-color: #d8e2ef;
            background: #e8f1f8;
            color: #1f4174;
        }
        
        html.light-mode .btn.secondary:hover,
        body.light-mode .btn.secondary:hover {
            background: #d8e2ef;
        }
        
        @media (max-width: 980px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <!-- TOP NAVBAR -->
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Hamburger Toggle & Logo -->
            <div class="navbar-start">
                <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle sidebar">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="logo">
                    <a href="index.php" style="display:flex;align-items:center;">
                        <img src="assets/logo.png" alt="Andison" style="height:48px;width:auto;object-fit:contain;">
                    </a>
                </div>
            </div>

            <!-- Right Profile Section -->
            <div class="navbar-end">
                <div class="profile-dropdown">
                    <button type="button" class="profile-btn" id="profileBtn" aria-label="Profile menu">
                        <span class="profile-name"><?php echo h($_SESSION['user_name'] ?? 'User'); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="profileMenu">
                        <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                        <a href="help.php"><i class="fas fa-question-circle"></i> Help</a>
                        <hr>
                        <a href="logout.php" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-content">
                <ul class="sidebar-menu">
                    <li class="menu-item"><a href="index.php" class="menu-link"><i class="fas fa-chart-line"></i><span class="menu-label">Dashboard</span></a></li>
                    <li class="menu-item"><a href="sales-overview.php" class="menu-link"><i class="fas fa-chart-pie"></i><span class="menu-label">Sales Overview</span></a></li>
                    <li class="menu-item active"><a href="orders.php" class="menu-link"><i class="fas fa-file-invoice-dollar"></i><span class="menu-label">Orders</span></a></li>
                    <li class="menu-item"><a href="sales-records.php" class="menu-link"><i class="fas fa-calendar-alt"></i><span class="menu-label">Sales Records</span></a></li>
                    <li class="menu-item"><a href="delivery-records.php" class="menu-link"><i class="fas fa-truck"></i><span class="menu-label">Delivery Records</span></a></li>
                    <li class="menu-item"><a href="inventory.php" class="menu-link"><i class="fas fa-boxes"></i><span class="menu-label">Inventory</span></a></li>
                    <li class="menu-item"><a href="andison-manila.php" class="menu-link"><i class="fas fa-truck-fast"></i><span class="menu-label">Andison Manila</span></a></li>
                    <li class="menu-item"><a href="client-companies.php" class="menu-link"><i class="fas fa-building"></i><span class="menu-label">Client Companies</span></a></li>
                    <li class="menu-item"><a href="models.php" class="menu-link"><i class="fas fa-cube"></i><span class="menu-label">Models</span></a></li>
                    <li class="menu-item"><a href="reports.php" class="menu-link"><i class="fas fa-file-alt"></i><span class="menu-label">Reports</span></a></li>
                    <li class="menu-item"><a href="upload-data.php" class="menu-link"><i class="fas fa-upload"></i><span class="menu-label">Upload Data</span></a></li>
                </ul>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Order Details: <?php echo h(so_id($orderId)); ?></h1>
                <a class="back-link" href="orders.php"><i class="fas fa-arrow-left"></i> Back to Orders</a>
            </div>

            <?php if ($flash): ?>
                <div class="flash <?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
            <?php endif; ?>

            <div class="grid">
                <div class="card">
                    <h3><i class="fas fa-info-circle"></i>Order Information</h3>
                    <form method="post" action="order-details.php?id=<?php echo intval($orderId); ?>">
                        <input type="hidden" name="action" value="save_order">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Customer</label>
                                <input name="customer" type="text" value="<?php echo h($order['order_customer'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Date</label>
                                <input name="order_date" type="date" value="<?php echo h($order['order_date'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Product Code</label>
                                <input name="item_code" type="text" value="<?php echo h($order['item_code'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Product Name</label>
                                <input name="item_name" type="text" value="<?php echo h($order['item_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Quantity</label>
                                <input name="quantity" type="number" min="1" value="<?php echo intval($order['quantity'] ?? 1); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Unit Price</label>
                                <input name="unit_price" type="number" min="0" step="0.01" value="<?php echo number_format($unitPrice, 2, '.', ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status">
                                    <option <?php echo (($order['status'] ?? '') === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option <?php echo (($order['status'] ?? '') === 'Ready for Delivery') ? 'selected' : ''; ?>>Ready for Delivery</option>
                                    <option <?php echo (($order['status'] ?? '') === 'In Transit') ? 'selected' : ''; ?>>In Transit</option>
                                    <option <?php echo (($order['status'] ?? '') === 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>PO Number</label>
                                <input name="po_number" type="text" value="<?php echo h($order['po_number'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>PO Status</label>
                                <select name="po_status">
                                    <option <?php echo (($order['po_status'] ?? '') === 'No PO') ? 'selected' : ''; ?>>No PO</option>
                                    <option <?php echo (($order['po_status'] ?? '') === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option <?php echo (($order['po_status'] ?? '') === 'Received') ? 'selected' : ''; ?>>Received</option>
                                </select>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Notes</label>
                                <textarea name="notes"><?php echo h($order['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="actions">
                            <button class="btn" type="submit"><i class="fas fa-save"></i> Save Changes</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <h3><i class="fas fa-credit-card"></i>Pricing Summary</h3>
                    <div class="card-section">
                        <div class="stat">Computed Total</div>
                        <div class="value">PHP <?php echo number_format($totalAmount, 2); ?></div>
                    </div>

                    <h3 style="margin-top:16px;"><i class="fas fa-file-invoice"></i>Invoice</h3>
                    <div class="card-section">
                        <div class="stat">Invoice Number</div>
                        <div class="value"><?php echo h($order['invoice_no'] ?: 'Not generated'); ?></div>
                        <form method="post" action="order-details.php?id=<?php echo intval($orderId); ?>">
                            <input type="hidden" name="action" value="generate_invoice">
                            <button class="btn secondary" type="submit"><i class="fas fa-file-invoice"></i> Generate Invoice</button>
                        </form>
                    </div>

                    <h3 style="margin-top:16px;"><i class="fas fa-clipboard-check"></i>PO Section</h3>
                    <div class="card-section">
                        <?php
                            $poClass = 'no-po';
                            if (($order['po_status'] ?? '') === 'Pending') $poClass = 'pending';
                            if (($order['po_status'] ?? '') === 'Received') $poClass = 'received';
                        ?>
                        <div class="stat">Current PO Status</div>
                        <div class="value"><span class="pill <?php echo h($poClass); ?>"><?php echo h($order['po_status'] ?: 'No PO'); ?></span></div>
                    </div>

                    <h3 style="margin-top:16px;"><i class="fas fa-truck"></i>Delivery Actions</h3>
                    <div class="card-section">
                        <div class="stat">Manage delivery workflow for this order.</div>
                        <div class="actions">
                            <form method="post" action="order-details.php?id=<?php echo intval($orderId); ?>" style="flex:1;">
                                <input type="hidden" name="action" value="mark_ready_for_delivery">
                                <button class="btn secondary" type="submit" style="width:100%;"><i class="fas fa-check-circle"></i> Mark as Ready</button>
                            </form>
                            <button class="btn" type="button" id="openCreateDeliveryBtn" style="flex:1; justify-content:center;"><i class="fas fa-plus-circle"></i> Create Delivery</button>
                        </div>
                        <p style="font-size:12px; color:#9fb1c5; margin-top:8px;"><i class="fas fa-info-circle"></i> Mark as Ready when the order is prepared. Create Delivery when it's shipped.</p>
                    </div>
                    <div class="actions" style="margin-top:10px;">
                        <a class="btn secondary" href="delivery-records.php" style="flex:1;justify-content:center;"><i class="fas fa-list"></i> View All Deliveries</a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="delivery-modal" id="createDeliveryModal" aria-hidden="true">
        <div class="delivery-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="createDeliveryTitle">
            <div class="delivery-modal-header">
                <h2 class="delivery-modal-title" id="createDeliveryTitle"><i class="fas fa-truck"></i> Create Delivery Record</h2>
                <button type="button" class="delivery-modal-close" id="closeCreateDeliveryBtn" aria-label="Close">&times;</button>
            </div>
            <p class="delivery-form-hint">Complete required details before creating the delivery record.</p>

            <form method="post" action="order-details.php?id=<?php echo intval($orderId); ?>">
                <input type="hidden" name="action" value="create_delivery_record">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Invoice No.</label>
                        <input type="text" value="<?php echo h($order['invoice_no'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Sold To *</label>
                        <input type="text" name="sold_to" value="<?php echo h($order['order_customer'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Date Delivered *</label>
                        <input type="date" name="delivery_date" value="<?php echo h(date('Y-m-d')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Delivery Month</label>
                        <input type="text" name="delivery_month" placeholder="Auto from date if blank">
                    </div>
                    <div class="form-group">
                        <label>Delivery Day</label>
                        <input type="number" name="delivery_day" min="1" max="31" placeholder="Auto from date if blank">
                    </div>
                    <div class="form-group">
                        <label>Delivery Year</label>
                        <input type="number" name="delivery_year" min="2000" max="2100" placeholder="Auto from date if blank">
                    </div>
                    <div class="form-group">
                        <label>UOM *</label>
                        <input type="text" name="uom" placeholder="e.g., pcs" required>
                    </div>
                    <div class="form-group">
                        <label>Serial No. *</label>
                        <input type="text" name="serial_no" placeholder="e.g., MA225-000613" required>
                    </div>
                    <div class="form-group">
                        <label>Sold To Month *</label>
                        <select name="sold_to_month" required>
                            <option value="">Select Month...</option>
                            <option value="January">January</option>
                            <option value="February">February</option>
                            <option value="March">March</option>
                            <option value="April">April</option>
                            <option value="May">May</option>
                            <option value="June">June</option>
                            <option value="July">July</option>
                            <option value="August">August</option>
                            <option value="September">September</option>
                            <option value="October">October</option>
                            <option value="November">November</option>
                            <option value="December">December</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sold To Day *</label>
                        <input type="number" name="sold_to_day" min="1" max="31" required>
                    </div>
                    <div class="form-group">
                        <label>Groupings *</label>
                        <input type="text" name="groupings" placeholder="e.g., A / B" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="delivery_status">
                            <option value="Pending" selected>Pending</option>
                            <option value="In Transit">In Transit</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Remarks</label>
                        <textarea name="delivery_remarks" placeholder="Additional delivery notes..."></textarea>
                    </div>
                </div>
                <div class="actions">
                    <button type="button" class="btn secondary" id="cancelCreateDeliveryBtn"><i class="fas fa-times"></i> Cancel</button>
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Create Delivery Record</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/app.js"></script>
    <script>
        (function () {
            const openBtn = document.getElementById('openCreateDeliveryBtn');
            const modal = document.getElementById('createDeliveryModal');
            const closeBtn = document.getElementById('closeCreateDeliveryBtn');
            const cancelBtn = document.getElementById('cancelCreateDeliveryBtn');

            if (!openBtn || !modal) return;

            const openModal = function () {
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            };

            const closeModal = function () {
                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            };

            openBtn.addEventListener('click', openModal);
            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.classList.contains('show')) {
                    closeModal();
                }
            });

            <?php if ($openDeliveryModal): ?>
            openModal();
            <?php endif; ?>
        })();
    </script>
</body>
</html>
