<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

// Get all deliveries to "to Andison Manila" with full details
$companyName = 'to Andison Manila';
$delivery_records = [];
$totalQuantity = 0;

$result = $conn->query("
    SELECT 
        id,
        invoice_no,
        delivery_date,
        delivery_month,
        delivery_day,
        delivery_year,
        item_code,
        item_name,
        quantity,
        uom,
        serial_no,
        company_name,
        transferred_to,
        sold_to,
        sold_to_month,
        sold_to_day,
        notes as remarks,
        groupings,
        status
    FROM delivery_records
    WHERE (
        company_name = '{$companyName}'
        OR transferred_to = '{$companyName}'
        OR LOWER(TRIM(COALESCE(sold_to, ''))) IN ('andison manila', 'to andison manila')
    )
    ORDER BY delivery_year DESC, delivery_month DESC, delivery_day DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $delivery_records[] = $row;
        $totalQuantity += intval($row['quantity'] ?? 0);
    }
}

$totalDeliveries = count($delivery_records);

// Count distinct item codes
$itemCodes = array_unique(array_column($delivery_records, 'item_code'));
$totalItemTypes = count($itemCodes);

// Count records with a sold_to value
$isRealSoldTo = function ($value) {
    $normalized = strtolower(trim((string) $value));
    return $normalized !== '' && $normalized !== 'andison manila' && $normalized !== 'to andison manila';
};

$totalSold = count(array_filter($delivery_records, function($r) use ($isRealSoldTo) {
    return $isRealSoldTo($r['sold_to'] ?? '');
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Andison Manila Deliveries - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <script src="https://cdn.jsdelivr.net/npm/@dotlottie/web-component@latest/dist/dotlottie-wc.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .dashboard-wrapper {
            min-height: calc(100vh - 70px);
        }

        .andison-container {
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title i {
            color: #f4d03f;
            font-size: 32px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #3a86ff;
            margin: 10px 0;
        }

        .stat-label {
            font-size: 12px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #e2e8f0;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3a86ff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #3a86ff;
        }

        .table-responsive {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            padding: 25px;
            border-radius: 12px;
            overflow-x: auto;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        table {
            width: 100%;
            min-width: 1800px;
            border-collapse: collapse;
            background: #1b2838;
        }

        thead {
            background: rgba(255, 255, 255, 0.08);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        th {
            padding: 16px;
            text-align: left;
            color: #dbe7f5;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            color: #d4deea;
            font-size: 14px;
        }

        tbody tr:hover {
            background: rgba(58, 134, 255, 0.12);
        }

        /* Column-specific widths */
        th:nth-child(1), td:nth-child(1) { min-width: 110px; } /* Invoice No */
        th:nth-child(2), td:nth-child(2) { min-width: 90px; }  /* Date */
        th:nth-child(3), td:nth-child(3) { min-width: 110px; } /* Delivery Month */
        th:nth-child(4), td:nth-child(4) { min-width: 100px; } /* Delivery Day */
        th:nth-child(5), td:nth-child(5) { min-width: 60px; }  /* Year */
        th:nth-child(6), td:nth-child(6) { min-width: 90px; }  /* Item */
        th:nth-child(7), td:nth-child(7) { min-width: 180px; } /* Description */
        th:nth-child(8), td:nth-child(8) { min-width: 70px; }  /* Qty */
        th:nth-child(9), td:nth-child(9) { min-width: 70px; }  /* UOM */
        th:nth-child(10), td:nth-child(10) { min-width: 100px; } /* Serial No */
        th:nth-child(11), td:nth-child(11) { min-width: 100px; } /* Transferred */
        th:nth-child(12), td:nth-child(12) { min-width: 100px; } /* Date Delivered */
        th:nth-child(13), td:nth-child(13) { min-width: 90px; } /* Transferred Month */
        th:nth-child(14), td:nth-child(14) { min-width: 90px; } /* Transferred Day */
        th:nth-child(15), td:nth-child(15) { min-width: 120px; } /* Remarks */
        th:nth-child(16), td:nth-child(16) { min-width: 100px; } /* Groupings */
        th:nth-child(17), td:nth-child(17) { min-width: 80px; text-align: center; } /* Action */

        .item-code {
            color: #60a5fa;
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }

        .quantity {
            color: #27ae60;
            font-weight: 600;
            font-size: 16px;
        }

        .date-cell {
            color: #9fb3c8;
            font-size: 13px;
        }

        .status-delivered {
            background: rgba(39, 174, 96, 0.2);
            color: #27ae60;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #9fb3c8;
        }

        .no-data i {
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .view-btn {
            padding: 6px 12px;
            background: rgba(0, 102, 204, 0.1);
            border: 1px solid rgba(0, 102, 204, 0.3);
            border-radius: 4px;
            color: #0066cc;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
        }

        .view-btn:hover {
            background: rgba(0, 102, 204, 0.2);
            border-color: rgba(0, 102, 204, 0.5);
        }

        .edit-btn {
            padding: 6px 10px;
            background: rgba(230, 126, 34, 0.1);
            border: none;
            border-radius: 4px;
            color: #e67e22;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .edit-btn:hover {
            background: rgba(230, 126, 34, 0.2);
        }

        .delete-btn {
            padding: 6px 10px;
            background: rgba(231, 76, 60, 0.1);
            border: none;
            border-radius: 4px;
            color: #e74c3c;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .delete-btn:hover {
            background: rgba(231, 76, 60, 0.2);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
        }

        .back-btn {
            padding: 10px 20px;
            background: #0066cc;
            border: none;
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #004999;
            transform: translateY(-2px);
        }

        /* Search bar */
        .search-container {
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-box {
            flex: 1;
            min-width: 280px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 11px 42px 11px 14px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.08);
            color: #e2e8f0;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .search-box input:focus {
            outline: none;
            border-color: #3a86ff;
        }
        .search-box i {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }
        .search-count {
            font-size: 13px;
            color: #9fb3c8;
            white-space: nowrap;
        }

        /* Filter tabs */
        .filter-tab {
            padding: 8px 20px;
            border: 1px solid rgba(255,255,255,0.14);
            border-radius: 8px;
            background: rgba(255,255,255,0.08);
            color: #d4deea;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-tab:hover { background: rgba(255,255,255,0.14); }
        .filter-tab.active {
            background: #3a86ff;
            color: #fff;
            border-color: #3a86ff;
        }

        .group-filter-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .group-filter-label {
            font-size: 13px;
            font-weight: 600;
            color: #d4deea;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .group-filter-select {
            min-width: 170px;
            padding: 8px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.08);
            color: #d4deea;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
        }

        .group-filter-select:focus {
            outline: none;
            border-color: #3a86ff;
        }

        .group-filter-select option {
            background: #1e2a38;
            color: #d4deea;
        }

        html.light-mode .page-title,
        body.light-mode .page-title { color: #1f2b3a; }
        html.light-mode .page-title i,
        body.light-mode .page-title i { color: #1565c0; }
        html.light-mode .section-title,
        body.light-mode .section-title { color: #1f2b3a; border-bottom-color: #1565c0; }
        html.light-mode .section-title i,
        body.light-mode .section-title i { color: #1565c0; }

        html.light-mode .table-responsive,
        body.light-mode .table-responsive {
            background: #ffffff;
            border: 1px solid #dbe3ef;
        }
        html.light-mode table,
        body.light-mode table { background: #ffffff; }
        html.light-mode thead,
        body.light-mode thead {
            background: #f1f5f9;
            border-bottom-color: #e2e8f0;
        }
        html.light-mode th,
        body.light-mode th { color: #1f2b3a; }
        html.light-mode td,
        body.light-mode td {
            color: #334155;
            border-bottom-color: #edf2f7;
        }
        html.light-mode tbody tr:hover,
        body.light-mode tbody tr:hover { background: #f8fbff; }
        html.light-mode .item-code,
        body.light-mode .item-code { color: #1565c0; }
        html.light-mode .date-cell,
        body.light-mode .date-cell { color: #64748b; }
        html.light-mode .search-count,
        body.light-mode .search-count { color: #64748b; }
        html.light-mode .no-data,
        body.light-mode .no-data { color: #64748b; }

        html.light-mode .filter-tab,
        body.light-mode .filter-tab { background: #f0f0f0; color: #333; border-color: #ccc; }
        html.light-mode .filter-tab.active,
        body.light-mode .filter-tab.active { background: #0066cc; color: #fff; border-color: #0066cc; }
        html.light-mode .group-filter-label,
        body.light-mode .group-filter-label { color: #334155; }
        html.light-mode .group-filter-select,
        body.light-mode .group-filter-select { background: #fff; color: #222; border-color: #ccc; }
        html.light-mode .search-box input,
        body.light-mode .search-box input { background: #fff; color: #222; border-color: #ccc; }

        .btn-add-record {
            padding: 10px 20px;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            border: none;
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-add-record:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(39, 174, 96, 0.35);
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px;
            }
        }

        /* ===== MODAL STYLES ===== */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            overflow-y: auto;
            overflow-x: hidden;
        }
        .modal.show {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 20px;
        }
        body.modal-open { overflow: hidden; }

        .modal-content {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            padding: 30px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.06);
            width: 90%;
            max-width: 560px;
            color: #e0e0e0;
            margin-top: 20px;
        }
        .modal-content.modal-large {
            max-width: 750px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            box-sizing: border-box;
            margin: 20px 0;
        }
        .modal-content.modal-large::-webkit-scrollbar { width: 6px; }
        .modal-content.modal-large::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); border-radius: 3px; }
        .modal-content.modal-large::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 3px; }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 15px;
        }
        .modal-header h2 { color: #fff; margin: 0; font-size: 18px; }
        .close-btn { background: none; border: none; color: #a0a0a0; font-size: 28px; cursor: pointer; transition: color 0.3s; }
        .close-btn:hover { color: #fff; }

        .modal-body { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .modal-row { display: flex; flex-direction: column; }
        .modal-label { font-size: 11px; color: #a0a0a0; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px; }
        .modal-value { font-size: 14px; color: #fff; font-weight: 600; }
        .modal-row.full-width { grid-column: 1 / -1; }

        /* Delete Modal */
        .delete-modal {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }
        .delete-modal.show { display: flex; }
        .delete-modal-content {
            background: linear-gradient(145deg, #1e2a38, #16202c);
            border-radius: 16px;
            padding: 35px 40px;
            max-width: 450px; width: 90%;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .delete-modal-icon { font-size: 60px; color: #e74c3c; margin-bottom: 20px; }
        .delete-modal-title { font-size: 22px; font-weight: 600; color: #fff; margin-bottom: 12px; }
        .delete-modal-message { font-size: 15px; color: #a0a0a0; margin-bottom: 30px; line-height: 1.5; }
        .delete-modal-message strong { color: #f4d03f; }
        .delete-modal-actions { display: flex; gap: 15px; justify-content: center; }
        .delete-modal-btn { padding: 14px 30px; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-confirm-delete { background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff; }
        .btn-confirm-delete:hover { background: linear-gradient(135deg, #ff6b5b, #e74c3c); transform: translateY(-2px); }
        .btn-cancel-delete { background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); }
        .btn-cancel-delete:hover { background: rgba(255,255,255,0.15); }

        /* Form styles for Edit modal */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 20px; width: 100%; box-sizing: border-box; }
        .form-group { display: flex; flex-direction: column; min-width: 0; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { font-size: 11px; color: #a0a0a0; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.08);
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s;
            width: 100%; box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { outline: none; border-color: #2f5fa7; background: rgba(255,255,255,0.12); }
        .form-group select option { background: #1e2a38; color: #fff; }
        .form-group textarea { resize: vertical; min-height: 60px; }
        .input-hint { display: block; font-size: 11px; color: #8899a8; margin-top: 4px; font-style: italic; }
        .form-actions { display: flex; gap: 15px; margin-top: 20px; justify-content: flex-end; }
        .btn-submit { background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: #fff; border: none; padding: 12px 28px; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(46,204,113,0.35); }
        .btn-cancel-form { background: rgba(255,255,255,0.1); color: #a0a0a0; border: none; padding: 12px 28px; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-cancel-form:hover { background: rgba(255,255,255,0.15); color: #fff; }

        /* Light mode overrides */
        html.light-mode .modal-content, body.light-mode .modal-content { background: #ffffff; color: #333; border: 1px solid #e0e0e0; }
        html.light-mode .modal-header h2, body.light-mode .modal-header h2 { color: #1a3a5c; }
        html.light-mode .modal-header, body.light-mode .modal-header { border-bottom-color: #e0e0e0; }
        html.light-mode .modal-label, body.light-mode .modal-label { color: #666; }
        html.light-mode .modal-value, body.light-mode .modal-value { color: #222; }
        html.light-mode .close-btn, body.light-mode .close-btn { color: #666; }
        html.light-mode .close-btn:hover, body.light-mode .close-btn:hover { color: #222; }
        html.light-mode .delete-modal-content, body.light-mode .delete-modal-content { background: #fff; border: 1px solid rgba(0,0,0,0.1); }
        html.light-mode .delete-modal-title, body.light-mode .delete-modal-title { color: #1a3a5c; }
        html.light-mode .delete-modal-message, body.light-mode .delete-modal-message { color: #5a6a7a; }
        html.light-mode .delete-modal-message strong, body.light-mode .delete-modal-message strong { color: #1e88e5; }
        html.light-mode .btn-cancel-delete, body.light-mode .btn-cancel-delete { background: #e8f4fc; color: #1a3a5c; border: 1px solid #c5ddf0; }
        html.light-mode .form-group input,
        html.light-mode .form-group select,
        html.light-mode .form-group textarea,
        body.light-mode .form-group input,
        body.light-mode .form-group select,
        body.light-mode .form-group textarea { background: #f5f7fa; color: #222; border-color: #ccc; }
        html.light-mode .form-group label, body.light-mode .form-group label { color: #555; }
        html.light-mode .btn-cancel-form, body.light-mode .btn-cancel-form { background: #e8e8e8; color: #444; }
    </style>
</head>
<body>
    <!-- TOP NAVBAR -->
    <nav class="navbar">
        <div class="navbar-container">
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

            <div class="navbar-end">
                <div class="profile-dropdown">
                    <button type="button" class="profile-btn" id="profileBtn" aria-label="Profile menu">
                        <span class="profile-name"><?php echo htmlspecialchars(isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User'); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="profileMenu">
                        <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                        <a href="help.php"><i class="fas fa-question-circle"></i> Help</a>
                        <hr>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <!-- SIDEBAR -->
        <?php require __DIR__ . '/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="main-content" id="mainContent">
            <div class="andison-container">
                <div class="page-header">
                    <div class="page-title">
                        <i class="fas fa-truck-fast"></i>
                        Andison Manila Deliveries
                    </div>
                    <div style="display:flex;gap:12px;align-items:center;">
                        <button class="btn-add-record" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Record
                        </button>
                        <a href="javascript:history.back()" class="back-btn">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Transferred</div>
                        <div class="stat-value" id="statTotalTransferred"><?php echo $totalDeliveries; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Units</div>
                        <div class="stat-value" id="statTotalUnits"><?php echo number_format($totalQuantity); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Item Types</div>
                        <div class="stat-value" id="statItemTypes"><?php echo $totalItemTypes; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Sold</div>
                        <div class="stat-value" id="statTotalSold" style="color:#2ecc71;"><?php echo $totalSold; ?></div>
                    </div>
                </div>

                <!-- Items Summary -->
                <div class="section-title">
                    <i class="fas fa-list"></i> Delivery Records
                </div>

                <!-- Filter Tabs -->
                <div class="filter-tabs primary-filter-tabs" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
                    <button class="filter-tab active" id="tabAll" data-primary-filter="all" onclick="setPrimaryFilter('all')">All Records</button>
                    <button class="filter-tab" id="tabSales" data-primary-filter="sales" onclick="setPrimaryFilter('sales')"><i class="fas fa-tag" style="margin-right:5px;"></i>Sales</button>
                </div>

                <div class="group-filter-row" id="groupFilterRow">
                    <span class="group-filter-label"><i class="fas fa-filter"></i> Filter</span>
                    <select id="groupingFilterSelect" class="group-filter-select" onchange="setGroupingFilter(this.value)">
                        <option value="all">All</option>
                        <option value="1a">1A</option>
                        <option value="1b">1B</option>
                        <option value="2a">2A</option>
                        <option value="2b">2B</option>
                        <option value="3a">3A</option>
                        <option value="4a">4A</option>
                    </select>
                </div>

                <?php if ($totalDeliveries > 0): ?>

                <!-- Search Bar -->
                <div class="search-container">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search by invoice, serial no, item, company..." oninput="searchTable()">
                        <i class="fas fa-search"></i>
                    </div>
                    <span class="search-count" id="searchCount">Showing <?php echo $totalDeliveries; ?> records</span>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Invoice No.</th>
                                <th>Date</th>
                                <th>Delivery Month to Andison</th>
                                <th>Delivery Day to Andison</th>
                                <th>Year</th>
                                <th>Item</th>
                                <th>Description</th>
                                <th>Qty.</th>
                                <th>UOM</th>
                                <th>Serial No.</th>
                                <th>Transferred</th>
                                <th>Sold To</th>
                                <th>Date Delivered</th>
                                <th>Transferred Month</th>
                                <th>Transferred Day</th>
                                <th>Remarks</th>
                                <th>Groupings</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($delivery_records as $record):
                                $delivery_date = '';
                                if (!empty($record['delivery_date'])) {
                                    $delivery_date = date('M j, Y', strtotime($record['delivery_date']));
                                }
                                
                                $date_col = '';
                                if (!empty($record['delivery_date'])) {
                                    $date_col = date('m/d/Y', strtotime($record['delivery_date']));
                                }
                                
                                $sold_to_month = !empty($record['sold_to_month']) ? $record['sold_to_month'] : '';
                                $sold_to_day = !empty($record['sold_to_day']) ? $record['sold_to_day'] : '';

                                // Only treat real customer names as sold-to values.
                                $row_sold_to = trim((string) ($record['sold_to'] ?? ''));
                                $sold_to_normalized = strtolower($row_sold_to);
                                if ($sold_to_normalized === 'andison manila' || $sold_to_normalized === 'to andison manila') {
                                    $row_sold_to = '';
                                }
                            ?>
                            <tr data-soldto="<?php echo !empty($row_sold_to) ? '1' : '0'; ?>" data-grouping="<?php echo strtolower(trim((string) ($record['groupings'] ?? ''))); ?>">
                                <td><?php echo htmlspecialchars($record['invoice_no'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($date_col); ?></td>
                                <td><?php echo htmlspecialchars($record['delivery_month'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($record['delivery_day'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($record['delivery_year'] ?? ''); ?></td>
                                <td><span class="item-code"><?php echo htmlspecialchars($record['item_code'] ?? ''); ?></span></td>
                                <td><?php echo htmlspecialchars($record['item_name'] ?? ''); ?></td>
                                <td><span class="quantity"><?php echo (!empty($record['quantity']) && $record['quantity'] > 0) ? htmlspecialchars($record['quantity']) : ''; ?></span></td>
                                <td><?php echo htmlspecialchars($record['uom'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($record['serial_no'] ?? ''); ?></td>
                                <td><?php
                                    // "Transferred" = always "to Andison Manila" indicator
                                    $transferred_display = (!empty($record['transferred_to'])) ? $record['transferred_to'] : ($record['company_name'] ?? '');
                                    echo htmlspecialchars($transferred_display);
                                ?></td>
                                <td><?php
                                    // "Sold To" = actual end customer
                                    echo htmlspecialchars($row_sold_to);
                                ?></td>
                                <td><?php echo htmlspecialchars($delivery_date); ?></td>
                                <td><?php echo htmlspecialchars($sold_to_month); ?></td>
                                <td><?php echo htmlspecialchars($sold_to_day); ?></td>
                                <td><?php echo htmlspecialchars($record['remarks'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($record['groupings'] ?? ''); ?></td>
                                <td style="text-align: center;">
                                    <div class="action-buttons">
                                        <a href="#" class="view-btn" onclick="openModal(event, <?php echo intval($record['id'] ?? 0); ?>)" title="View Record">View</a>
                                        <a href="#" class="edit-btn" onclick="openEditModal(event, <?php echo intval($record['id'] ?? 0); ?>)" title="Edit Record"><i class="fas fa-edit"></i></a>
                                        <a href="#" class="delete-btn" onclick="deleteRecord(event, <?php echo intval($record['id'] ?? 0); ?>, '<?php echo htmlspecialchars($record['serial_no'] ?? ''); ?>')" title="Delete Record"><i class="fas fa-trash-alt"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <p>No delivery records found for Andison Manila</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Record Modal -->
    <div id="addRecordModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header" style="margin-bottom:18px;padding-bottom:12px;">
                <h2><i class="fas fa-plus-circle" style="color:#2ecc71;margin-right:10px;"></i>Add New Delivery Record</h2>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
            </div>
            <form id="addRecordForm" onsubmit="submitAddRecord(event)">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="add_invoice_no">Invoice No.</label>
                        <input type="text" id="add_invoice_no" name="invoice_no" placeholder="e.g., 5268850284">
                    </div>
                    <div class="form-group">
                        <label for="add_date">Date</label>
                        <input type="date" id="add_date" name="date">
                    </div>
                    <div class="form-group">
                        <label for="add_delivery_month">Delivery Month to Andison</label>
                        <select id="add_delivery_month" name="delivery_month">
                            <option value="">Select Month...</option>
                            <option value="January">January</option><option value="February">February</option>
                            <option value="March">March</option><option value="April">April</option>
                            <option value="May">May</option><option value="June">June</option>
                            <option value="July">July</option><option value="August">August</option>
                            <option value="September">September</option><option value="October">October</option>
                            <option value="November">November</option><option value="December">December</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_delivery_day">Delivery Day to Andison</label>
                        <input type="number" id="add_delivery_day" name="delivery_day" placeholder="e.g., 7" min="1" max="31">
                    </div>
                    <div class="form-group">
                        <label for="add_year">Year</label>
                        <input type="number" id="add_year" name="year" placeholder="e.g., 2025" min="2000" max="2100">
                    </div>
                    <div class="form-group">
                        <label for="add_item_code">Item Code</label>
                        <input type="text" id="add_item_code" name="item_code" placeholder="e.g., XT-XWHM-Y-NA">
                    </div>
                    <div class="form-group full-width">
                        <label for="add_item_name">Description</label>
                        <input type="text" id="add_item_name" name="item_name" placeholder="e.g., GasAlertMax XT O2/LEL/H2S/CO">
                    </div>
                    <div class="form-group">
                        <label for="add_quantity">Qty.</label>
                        <input type="number" id="add_quantity" name="quantity" placeholder="e.g., 40" min="0">
                    </div>
                    <div class="form-group">
                        <label for="add_uom">UOM</label>
                        <input type="text" id="add_uom" name="uom" placeholder="e.g., units, pcs">
                    </div>
                    <div class="form-group">
                        <label for="add_serial_no">Serial No.</label>
                        <input type="text" id="add_serial_no" name="serial_no" placeholder="e.g., MA225-000613">
                    </div>
                    <div class="form-group">
                        <label for="add_company_name">Transferred</label>
                        <input type="text" id="add_company_name" name="company_name" placeholder="e.g., to Andison Manila" value="to Andison Manila">
                        <input type="hidden" id="add_transferred_to" name="transferred_to" value="to Andison Manila">
                    </div>
                    <div class="form-group">
                        <label for="add_sold_to">Sold To</label>
                        <input type="text" id="add_sold_to" name="sold_to" placeholder="e.g., ABC Company Ltd">
                        <small class="input-hint">Customer/company buying from Andison Manila</small>
                    </div>
                    <div class="form-group">
                        <label for="add_delivery_date">Date Delivered</label>
                        <input type="date" id="add_delivery_date" name="delivery_date">
                    </div>
                    <div class="form-group">
                        <label for="add_sold_to_month">Transferred Month</label>
                        <select id="add_sold_to_month" name="sold_to_month">
                            <option value="">Select Month...</option>
                            <option value="January">January</option><option value="February">February</option>
                            <option value="March">March</option><option value="April">April</option>
                            <option value="May">May</option><option value="June">June</option>
                            <option value="July">July</option><option value="August">August</option>
                            <option value="September">September</option><option value="October">October</option>
                            <option value="November">November</option><option value="December">December</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_sold_to_day">Transferred Day</label>
                        <input type="number" id="add_sold_to_day" name="sold_to_day" placeholder="e.g., 15" min="1" max="31">
                    </div>
                    <div class="form-group">
                        <label for="add_groupings">Groupings</label>
                        <select id="add_groupings" name="groupings" required>
                            <option value="">Select Grouping</option>
                            <option value="1A" selected>1A</option>
                            <option value="1B">1B</option>
                            <option value="2A">2A</option>
                            <option value="2B">2B</option>
                            <option value="3A">3A</option>
                            <option value="4A">4A</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_highlight_preset">Color Marker</label>
                        <select id="add_highlight_preset" name="highlight_preset" onchange="toggleAddCustomColor()">
                            <option value="">No Color Marker</option>
                            <option value="#D8B4FE">Katay (Purple)</option>
                            <option value="#FDE68A">Send to Andison (Yellow)</option>
                            <option value="#FCA5A5">Warranty Replacement (Red)</option>
                            <option value="#93C5FD">Warranty to Purchase (Blue)</option>
                            <option value="#F9A8D4">Purchase to Warranty (Pink)</option>
                            <option value="custom">Custom Color...</option>
                        </select>
                        <input type="color" id="add_highlight_color" value="#FDE68A" style="display:none; margin-top: 8px;">
                    </div>
                    <div class="form-group">
                        <label for="add_status">Status</label>
                        <select id="add_status" name="status">
                            <option value="Delivered">Delivered</option>
                            <option value="Pending">Pending</option>
                            <option value="In Transit">In Transit</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label for="add_notes">Remarks</label>
                        <textarea id="add_notes" name="notes" rows="3" placeholder="Additional remarks..."></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel-form" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Detail View Modal -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTrackingId">Delivery Details</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-row">
                    <span class="modal-label">Invoice No.</span>
                    <span class="modal-value" id="modalInvoiceNo"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Date</span>
                    <span class="modal-value" id="modalDate"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Delivery Month</span>
                    <span class="modal-value" id="modalDeliveryMonth"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Delivery Day</span>
                    <span class="modal-value" id="modalDeliveryDay"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Year</span>
                    <span class="modal-value" id="modalYear"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Item</span>
                    <span class="modal-value" id="modalItem"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Description</span>
                    <span class="modal-value" id="modalDescription"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Qty.</span>
                    <span class="modal-value" id="modalQty"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">UOM</span>
                    <span class="modal-value" id="modalUom"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Serial No.</span>
                    <span class="modal-value" id="modalSerialNo"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Transferred</span>
                    <span class="modal-value" id="modalSoldTo"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Date Delivered</span>
                    <span class="modal-value" id="modalDeliveryDate"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Transferred Month</span>
                    <span class="modal-value" id="modalSoldToMonth"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Transferred Day</span>
                    <span class="modal-value" id="modalSoldToDay"></span>
                </div>
                <div class="modal-row full-width">
                    <span class="modal-label">Remarks</span>
                    <span class="modal-value" id="modalRemarks"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Groupings</span>
                    <span class="modal-value" id="modalGroupings"></span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Status</span>
                    <span class="modal-value" id="modalStatus"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="delete-modal">
        <div class="delete-modal-content">
            <div class="delete-modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h3 class="delete-modal-title">Delete Record?</h3>
            <p class="delete-modal-message">
                Are you sure you want to delete <strong id="deleteItemName">this record</strong>?<br>
                This action cannot be undone.
            </p>
            <div class="delete-modal-actions">
                <button type="button" class="delete-modal-btn btn-cancel-delete" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="delete-modal-btn btn-confirm-delete" id="confirmDeleteBtn" onclick="confirmDelete()">
                    <i class="fas fa-trash-alt"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Record Modal -->
    <div id="editRecordModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header" style="margin-bottom:18px;padding-bottom:12px;">
                <h2><i class="fas fa-edit" style="color:#f39c12;margin-right:10px;"></i>Edit Delivery Record</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="editRecordForm" onsubmit="submitEditRecord(event)">
                <input type="hidden" id="edit_id" name="id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_invoice_no">Invoice No.</label>
                        <input type="text" id="edit_invoice_no" name="invoice_no" placeholder="e.g., 5268850284">
                    </div>
                    <div class="form-group">
                        <label for="edit_date">Date</label>
                        <input type="date" id="edit_date" name="date">
                    </div>
                    <div class="form-group">
                        <label for="edit_delivery_month">Delivery Month to Andison</label>
                        <select id="edit_delivery_month" name="delivery_month">
                            <option value="">Select Month...</option>
                            <option value="January">January</option><option value="February">February</option>
                            <option value="March">March</option><option value="April">April</option>
                            <option value="May">May</option><option value="June">June</option>
                            <option value="July">July</option><option value="August">August</option>
                            <option value="September">September</option><option value="October">October</option>
                            <option value="November">November</option><option value="December">December</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_delivery_day">Delivery Day to Andison</label>
                        <input type="number" id="edit_delivery_day" name="delivery_day" placeholder="e.g., 7" min="1" max="31">
                    </div>
                    <div class="form-group">
                        <label for="edit_year">Year</label>
                        <input type="number" id="edit_year" name="year" placeholder="e.g., 2025" min="2000" max="2100">
                    </div>
                    <div class="form-group">
                        <label for="edit_item_code">Item Code</label>
                        <input type="text" id="edit_item_code" name="item_code" placeholder="e.g., XT-XWHM-Y-NA">
                    </div>
                    <div class="form-group full-width">
                        <label for="edit_item_name">Description</label>
                        <input type="text" id="edit_item_name" name="item_name" placeholder="e.g., GasAlertMax XT O2/LEL/H2S/CO">
                    </div>
                    <div class="form-group">
                        <label for="edit_quantity">Qty.</label>
                        <input type="number" id="edit_quantity" name="quantity" placeholder="e.g., 40" min="0">
                    </div>
                    <div class="form-group">
                        <label for="edit_uom">UOM</label>
                        <input type="text" id="edit_uom" name="uom" placeholder="e.g., units, pcs">
                    </div>
                    <div class="form-group">
                        <label for="edit_serial_no">Serial No.</label>
                        <input type="text" id="edit_serial_no" name="serial_no" placeholder="e.g., MA225-000613">
                    </div>
                    <div class="form-group">
                        <label for="edit_company_name">Transferred</label>
                        <input type="text" id="edit_company_name" name="company_name" placeholder="e.g., Anden Construction">
                        <input type="hidden" id="edit_transferred_to" name="transferred_to" value="to Andison Manila">
                    </div>
                    <div class="form-group">
                        <label for="edit_sold_to">Sold To</label>
                        <input type="text" id="edit_sold_to" name="sold_to" placeholder="e.g., ABC Company Ltd">
                        <small class="input-hint">Customer/company buying from Andison Manila</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_delivery_date">Date Delivered</label>
                        <input type="date" id="edit_delivery_date" name="delivery_date">
                    </div>
                    <div class="form-group">
                        <label for="edit_sold_to_month">Transferred Month</label>
                        <select id="edit_sold_to_month" name="sold_to_month">
                            <option value="">Select Month...</option>
                            <option value="January">January</option><option value="February">February</option>
                            <option value="March">March</option><option value="April">April</option>
                            <option value="May">May</option><option value="June">June</option>
                            <option value="July">July</option><option value="August">August</option>
                            <option value="September">September</option><option value="October">October</option>
                            <option value="November">November</option><option value="December">December</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_sold_to_day">Transferred Day</label>
                        <input type="number" id="edit_sold_to_day" name="sold_to_day" placeholder="e.g., 15" min="1" max="31">
                    </div>
                    <div class="form-group">
                        <label for="edit_groupings">Groupings</label>
                        <select id="edit_groupings" name="groupings" required>
                            <option value="">Select Grouping</option>
                            <option value="1A">1A</option>
                            <option value="1B">1B</option>
                            <option value="2A">2A</option>
                            <option value="2B">2B</option>
                            <option value="3A">3A</option>
                            <option value="4A">4A</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_highlight_preset">Color Marker</label>
                        <select id="edit_highlight_preset" name="highlight_preset" onchange="toggleEditCustomColor()">
                            <option value="">No Color Marker</option>
                            <option value="#D8B4FE">Katay (Purple)</option>
                            <option value="#FDE68A">Send to Andison (Yellow)</option>
                            <option value="#FCA5A5">Warranty Replacement (Red)</option>
                            <option value="#93C5FD">Warranty to Purchase (Blue)</option>
                            <option value="#F9A8D4">Purchase to Warranty (Pink)</option>
                            <option value="custom">Custom Color...</option>
                        </select>
                        <input type="color" id="edit_highlight_color" value="#FDE68A" style="display:none; margin-top: 8px;">
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status">
                            <option value="Delivered">Delivered</option>
                            <option value="Pending">Pending</option>
                            <option value="In Transit">In Transit</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label for="edit_notes">Remarks</label>
                        <textarea id="edit_notes" name="notes" rows="3" placeholder="Additional remarks..."></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel-form" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-submit" style="background:linear-gradient(135deg,#f39c12,#d68910);"><i class="fas fa-save"></i> Update Record</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/app.js" defer></script>
    <script>
        // Records data from PHP for modals
        let recordsData = <?php echo json_encode($delivery_records); ?>;

        function isRealSoldToValue(value) {
            const normalized = String(value || '').trim().toLowerCase();
            return normalized !== '' && normalized !== 'andison manila' && normalized !== 'to andison manila';
        }

        function formatNumber(n) {
            return Number(n || 0).toLocaleString();
        }

        function updateSummaryStats() {
            const totalTransferred = recordsData.length;
            const totalUnits = recordsData.reduce((sum, r) => sum + (parseInt(r.quantity, 10) || 0), 0);
            const itemTypes = new Set(
                recordsData
                    .map(r => (r.item_code || '').trim())
                    .filter(v => v !== '')
            ).size;
            const totalSoldCount = recordsData.filter(r => isRealSoldToValue(r.sold_to)).length;

            const transferredEl = document.getElementById('statTotalTransferred');
            const unitsEl = document.getElementById('statTotalUnits');
            const itemTypesEl = document.getElementById('statItemTypes');
            const soldEl = document.getElementById('statTotalSold');

            if (transferredEl) transferredEl.textContent = formatNumber(totalTransferred);
            if (unitsEl) unitsEl.textContent = formatNumber(totalUnits);
            if (itemTypesEl) itemTypesEl.textContent = formatNumber(itemTypes);
            if (soldEl) soldEl.textContent = formatNumber(totalSoldCount);
        }

        function updateRecordsEmptyState() {
            const tbody = document.querySelector('table tbody');
            if (!tbody) return;

            const rows = tbody.querySelectorAll('tr');
            const hasRows = rows.length > 0;
            const tableResponsive = document.querySelector('.table-responsive');
            const searchContainer = document.querySelector('.search-container');
            const countEl = document.getElementById('searchCount');

            let emptyState = document.getElementById('dynamicNoData');

            if (!hasRows) {
                if (tableResponsive) tableResponsive.style.display = 'none';
                if (searchContainer) searchContainer.style.display = 'none';
                if (countEl) countEl.textContent = 'Showing 0 records';

                if (!emptyState) {
                    emptyState = document.createElement('div');
                    emptyState.id = 'dynamicNoData';
                    emptyState.className = 'no-data';
                    emptyState.innerHTML = '<i class="fas fa-inbox"></i><p>No delivery records found for Andison Manila</p>';
                    if (tableResponsive && tableResponsive.parentElement) {
                        tableResponsive.parentElement.appendChild(emptyState);
                    }
                }
            } else if (emptyState) {
                emptyState.remove();
                if (tableResponsive) tableResponsive.style.display = '';
                if (searchContainer) searchContainer.style.display = '';
            }
        }

        // Profile dropdown toggle
        document.getElementById('profileBtn').addEventListener('click', function() {
            const menu = document.getElementById('profileMenu');
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.profile-dropdown')) {
                document.getElementById('profileMenu').style.display = 'none';
            }
        });

        // ===== ADD RECORD MODAL =====
        function openAddModal() {
            document.getElementById('addRecordModal').classList.add('show');
            document.body.classList.add('modal-open');
            document.getElementById('add_delivery_date').value = new Date().toISOString().split('T')[0];
            document.getElementById('add_highlight_preset').value = '';
            document.getElementById('add_highlight_color').style.display = 'none';
        }

        function closeAddModal() {
            document.getElementById('addRecordModal').classList.remove('show');
            document.body.classList.remove('modal-open');
            document.getElementById('addRecordForm').reset();
            document.getElementById('add_highlight_color').style.display = 'none';
        }

        function toggleAddCustomColor() {
            const preset = document.getElementById('add_highlight_preset').value;
            document.getElementById('add_highlight_color').style.display = (preset === 'custom') ? 'block' : 'none';
        }

        function toggleEditCustomColor() {
            const preset = document.getElementById('edit_highlight_preset').value;
            document.getElementById('edit_highlight_color').style.display = (preset === 'custom') ? 'block' : 'none';
        }

        function submitAddRecord(event) {
            event.preventDefault();
            showLoadingOverlay(true, 'Saving');

            const formData = {
                invoice_no:     document.getElementById('add_invoice_no').value,
                date:           document.getElementById('add_date').value,
                delivery_month: document.getElementById('add_delivery_month').value,
                delivery_day:   parseInt(document.getElementById('add_delivery_day').value) || 0,
                year:           parseInt(document.getElementById('add_year').value) || 0,
                item_code:      document.getElementById('add_item_code').value,
                item_name:      document.getElementById('add_item_name').value,
                quantity:       parseInt(document.getElementById('add_quantity').value) || 0,
                uom:            document.getElementById('add_uom').value,
                serial_no:      document.getElementById('add_serial_no').value,
                company_name:   document.getElementById('add_company_name').value,
                transferred_to: document.getElementById('add_transferred_to').value,
                sold_to:        document.getElementById('add_sold_to').value,
                delivery_date:  document.getElementById('add_delivery_date').value,
                sold_to_month:  document.getElementById('add_sold_to_month').value,
                sold_to_day:    parseInt(document.getElementById('add_sold_to_day').value) || 0,
                groupings:      document.getElementById('add_groupings').value,
                highlight_color: (() => {
                    const preset = document.getElementById('add_highlight_preset').value;
                    if (preset === 'custom') return document.getElementById('add_highlight_color').value || '';
                    return preset || '';
                })(),
                notes:          document.getElementById('add_notes').value,
                status:         document.getElementById('add_status').value
            };

            fetch('api/add-record.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(result => {
                showLoadingOverlay(false);
                if (result.success) {
                    showToast('Record added successfully!', 'success');
                    closeAddModal();
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showToast('Error: ' + (result.message || 'Failed to add record'), 'error');
                }
            })
            .catch(err => {
                showLoadingOverlay(false);
                showToast('Error adding record. Please try again.', 'error');
            });
        }

        window.addEventListener('click', e => {
            if (e.target === document.getElementById('addRecordModal')) closeAddModal();
        });

        // ===== VIEW MODAL =====
        function openModal(event, recordId) {
            event.preventDefault();
            const record = recordsData.find(r => parseInt(r.id) === parseInt(recordId));
            if (!record) { showToast('Record not found', 'error'); return; }

            let dateCol = '';
            let deliveryDate = '';
            if (record.delivery_date) {
                const d = new Date(record.delivery_date);
                dateCol = d.toLocaleDateString('en-US');
                deliveryDate = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            }

            document.getElementById('modalTrackingId').textContent = record.serial_no ? record.serial_no + ' Details' : 'Delivery Details';
            document.getElementById('modalInvoiceNo').textContent  = record.invoice_no || '';
            document.getElementById('modalDate').textContent        = dateCol;
            document.getElementById('modalDeliveryMonth').textContent = record.delivery_month || '';
            document.getElementById('modalDeliveryDay').textContent   = record.delivery_day || '';
            document.getElementById('modalYear').textContent          = record.delivery_year || '';
            document.getElementById('modalItem').textContent          = record.item_code || '';
            document.getElementById('modalDescription').textContent   = record.item_name || '';
            document.getElementById('modalQty').textContent           = record.quantity || '';
            document.getElementById('modalUom').textContent           = record.uom || '';
            document.getElementById('modalSerialNo').textContent      = record.serial_no || '';
            document.getElementById('modalSoldTo').textContent        = isRealSoldToValue(record.sold_to) ? record.sold_to : '';
            document.getElementById('modalDeliveryDate').textContent  = deliveryDate;
            document.getElementById('modalSoldToMonth').textContent   = record.sold_to_month || '';
            document.getElementById('modalSoldToDay').textContent     = record.sold_to_day || '';
            document.getElementById('modalRemarks').textContent       = record.remarks || '';
            document.getElementById('modalGroupings').textContent     = record.groupings || '';
            document.getElementById('modalStatus').textContent        = record.status || '';

            document.getElementById('detailModal').classList.add('show');
            document.body.classList.add('modal-open');
        }

        function closeModal() {
            document.getElementById('detailModal').classList.remove('show');
            document.body.classList.remove('modal-open');
        }

        window.addEventListener('click', e => {
            if (e.target === document.getElementById('detailModal')) closeModal();
        });

        // ===== DELETE MODAL =====
        let deleteRecordId = null;
        let deleteRecordRow = null;

        function deleteRecord(event, recordId, serialNo) {
            event.preventDefault();
            deleteRecordId = recordId;
            deleteRecordRow = event.target.closest('tr');
            document.getElementById('deleteItemName').textContent = serialNo || 'this record';
            document.getElementById('deleteConfirmModal').classList.add('show');
            document.body.classList.add('modal-open');
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').classList.remove('show');
            document.body.classList.remove('modal-open');
            deleteRecordId = null;
            deleteRecordRow = null;
        }

        function confirmDelete() {
            if (!deleteRecordId) return;
            showLoadingOverlay(true, 'Deleting');

            fetch('api/delete-record.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: deleteRecordId })
            })
            .then(r => r.json())
            .then(result => {
                showLoadingOverlay(false);
                if (result.success) {
                    showToast('Record deleted!', 'success');
                    recordsData = recordsData.filter(r => parseInt(r.id, 10) !== parseInt(deleteRecordId, 10));
                    if (deleteRecordRow) {
                        deleteRecordRow.style.transition = 'all 0.3s ease';
                        deleteRecordRow.style.opacity = '0';
                        deleteRecordRow.style.transform = 'translateX(-20px)';
                        setTimeout(() => {
                            deleteRecordRow.remove();
                            updateSummaryStats();
                            searchTable();
                            updateRecordsEmptyState();
                        }, 300);
                    } else {
                        updateSummaryStats();
                        searchTable();
                        updateRecordsEmptyState();
                    }
                    closeDeleteModal();
                } else {
                    showToast('Error: ' + (result.message || 'Failed to delete record'), 'error');
                }
            })
            .catch(err => {
                showLoadingOverlay(false);
                showToast('Error deleting record. Please try again.', 'error');
            });
        }

        document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });

        // ===== EDIT MODAL =====
        function openEditModal(event, recordId) {
            event.preventDefault();
            const record = recordsData.find(r => parseInt(r.id) === parseInt(recordId));
            if (!record) { showToast('Record not found', 'error'); return; }

            document.getElementById('edit_id').value            = record.id;
            document.getElementById('edit_invoice_no').value    = record.invoice_no || '';
            document.getElementById('edit_serial_no').value     = record.serial_no || '';
            document.getElementById('edit_item_code').value     = record.item_code || '';
            document.getElementById('edit_item_name').value     = record.item_name || '';
            // Always show "to Andison Manila" in the Transferred field
            document.getElementById('edit_company_name').value  = 'to Andison Manila';
            const actualSoldTo = isRealSoldToValue(record.sold_to) ? record.sold_to : '';
            document.getElementById('edit_sold_to').value       = actualSoldTo;
            document.getElementById('edit_quantity').value      = record.quantity || '';
            document.getElementById('edit_uom').value           = record.uom || '';
            document.getElementById('edit_notes').value         = record.remarks || '';
            document.getElementById('edit_groupings').value     = record.groupings || '';
            document.getElementById('edit_status').value        = record.status || 'Delivered';
            {
                const preset = document.getElementById('edit_highlight_preset');
                const custom = document.getElementById('edit_highlight_color');
                const colorValue = String(record.highlight_color || '').trim().toUpperCase();
                const presetOptions = ['', '#D8B4FE', '#FDE68A', '#FCA5A5', '#93C5FD', '#F9A8D4'];
                if (presetOptions.includes(colorValue)) {
                    preset.value = colorValue;
                    custom.style.display = 'none';
                } else if (/^#?[0-9A-F]{6}$/.test(colorValue)) {
                    preset.value = 'custom';
                    custom.value = colorValue.startsWith('#') ? colorValue : `#${colorValue}`;
                    custom.style.display = 'block';
                } else {
                    preset.value = '';
                    custom.style.display = 'none';
                }
            }
            document.getElementById('edit_date').value          = record.delivery_date || '';
            document.getElementById('edit_delivery_date').value = record.delivery_date || '';
            document.getElementById('edit_delivery_month').value = record.delivery_month || '';
            document.getElementById('edit_delivery_day').value  = record.delivery_day || '';
            document.getElementById('edit_year').value          = record.delivery_year || '';
            document.getElementById('edit_sold_to_month').value = record.sold_to_month || '';
            document.getElementById('edit_sold_to_day').value   = record.sold_to_day || '';

            document.getElementById('editRecordModal').classList.add('show');
            document.body.classList.add('modal-open');
        }

        function closeEditModal() {
            document.getElementById('editRecordModal').classList.remove('show');
            document.body.classList.remove('modal-open');
            document.getElementById('editRecordForm').reset();
        }

        function submitEditRecord(event) {
            event.preventDefault();
            showLoadingOverlay(true, 'Updating');

            const formData = {
                id:             document.getElementById('edit_id').value,
                serial_no:      document.getElementById('edit_serial_no').value,
                invoice_no:     document.getElementById('edit_invoice_no').value,
                item_code:      document.getElementById('edit_item_code').value,
                item_name:      document.getElementById('edit_item_name').value,
                company_name:   'to Andison Manila',
                transferred_to: '',
                sold_to:        document.getElementById('edit_sold_to').value,
                quantity:       parseInt(document.getElementById('edit_quantity').value) || 0,
                uom:            document.getElementById('edit_uom').value,
                date:           document.getElementById('edit_date').value,
                delivery_date:  document.getElementById('edit_delivery_date').value,
                delivery_month: document.getElementById('edit_delivery_month').value,
                delivery_day:   document.getElementById('edit_delivery_day').value,
                year:           document.getElementById('edit_year').value,
                sold_to_month:  document.getElementById('edit_sold_to_month').value,
                sold_to_day:    document.getElementById('edit_sold_to_day').value,
                groupings:      document.getElementById('edit_groupings').value,
                highlight_color: (() => {
                    const preset = document.getElementById('edit_highlight_preset').value;
                    if (preset === 'custom') return document.getElementById('edit_highlight_color').value || '';
                    return preset || '';
                })(),
                notes:          document.getElementById('edit_notes').value,
                status:         document.getElementById('edit_status').value
            };

            fetch('api/update-record.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            })
            .then(r => r.json())
            .then(result => {
                showLoadingOverlay(false);
                if (result.success) {
                    showToast('Record updated successfully!', 'success');
                    closeEditModal();
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showToast('Error: ' + (result.message || 'Failed to update record'), 'error');
                }
            })
            .catch(err => {
                showLoadingOverlay(false);
                showToast('Error updating record. Please try again.', 'error');
            });
        }

        window.addEventListener('click', e => {
            if (e.target === document.getElementById('editRecordModal')) closeEditModal();
        });

        // ===== FILTER TABS =====
        let primaryFilter = 'all';
        let groupingFilter = 'all';

        function setPrimaryFilter(filter) {
            primaryFilter = filter;
            document.querySelectorAll('.filter-tab[data-primary-filter]').forEach(button => {
                button.classList.toggle('active', button.dataset.primaryFilter === filter);
            });

            // Reset free-text search when returning to All so rows are not accidentally hidden.
            if (filter === 'all') {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) searchInput.value = '';
            }

            searchTable();
        }

        function setGroupingFilter(filter) {
            groupingFilter = String(filter || 'all').toLowerCase();
            searchTable();
        }

        // ===== SEARCH =====
        function searchTable() {
            const searchInput = document.getElementById('searchInput');
            const filter = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const rows = Array.from(document.querySelectorAll('table tbody tr'));
            let count = 0;
            const hasGroupingFilter = groupingFilter !== 'all';
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const textMatch = filter === '' || text.includes(filter);
                const rowIsSales = row.dataset.soldto === '1';
                const primaryMatch = primaryFilter === 'sales' ? rowIsSales : !rowIsSales;
                const categoryMatch = !hasGroupingFilter
                    || (row.dataset.grouping || '').trim().toLowerCase() === groupingFilter;
                const match = textMatch && primaryMatch && categoryMatch;
                row.style.display = match ? '' : 'none';
                if (match) count++;
            });
            const primaryTotal = rows.filter(row => {
                const rowIsSales = row.dataset.soldto === '1';
                const primaryMatch = primaryFilter === 'sales' ? rowIsSales : !rowIsSales;
                const categoryMatch = !hasGroupingFilter
                    || (row.dataset.grouping || '').trim().toLowerCase() === groupingFilter;
                return primaryMatch && categoryMatch;
            }).length;
            const countEl = document.getElementById('searchCount');
            if (countEl) {
                const modeLabel = primaryFilter === 'sales' ? 'sales' : 'all records';
                const groupLabel = hasGroupingFilter ? ` (${groupingFilter.toUpperCase()})` : '';
                countEl.textContent = filter
                    ? `Showing ${count} of ${primaryTotal} ${modeLabel}${groupLabel}`
                    : `Showing ${primaryTotal} ${modeLabel}${groupLabel}`;
                countEl.style.color = (filter || hasGroupingFilter || primaryFilter === 'sales') ? '#0066cc' : '#666';
            }
        }

        // Apply default tab logic on initial load.
        searchTable();

        // ===== TOAST =====
        function showToast(message, type = 'success') {
            const existing = document.getElementById('toastNotif');
            if (existing) existing.remove();
            const icons  = { success: '&#10003;', error: '&#10007;', warning: '&#9888;' };
            const colors = {
                success: 'linear-gradient(135deg,#1abc9c,#16a085)',
                error:   'linear-gradient(135deg,#e74c3c,#c0392b)',
                warning: 'linear-gradient(135deg,#f39c12,#d68910)'
            };
            const toast = document.createElement('div');
            toast.id = 'toastNotif';
            toast.innerHTML = `<span style="font-size:20px;flex-shrink:0;">${icons[type]||icons.success}</span><span style="flex:1;line-height:1.4;">${message}</span><button onclick="this.parentElement.remove()" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;padding:0;line-height:1;opacity:.8;flex-shrink:0;">&times;</button>`;
            Object.assign(toast.style, {
                position:'fixed', top:'24px', right:'24px', zIndex:'99999',
                display:'flex', alignItems:'center', gap:'12px',
                minWidth:'280px', maxWidth:'400px', padding:'16px 20px',
                borderRadius:'12px', background: colors[type]||colors.success,
                color:'#fff', fontFamily:'inherit', fontSize:'14px', fontWeight:'500',
                boxShadow:'0 8px 32px rgba(0,0,0,0.35)', cursor:'default'
            });
            if (!document.getElementById('toastStyle')) {
                const s = document.createElement('style');
                s.id = 'toastStyle';
                s.textContent = '@keyframes toastSlideIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}';
                document.head.appendChild(s);
            }
            toast.style.animation = 'toastSlideIn .3s ease';
            document.body.appendChild(toast);
            setTimeout(() => { if (toast.parentElement) toast.remove(); }, 4000);
        }

        // ===== LOADING OVERLAY =====
        let dotAnimationInterval;
        function showLoadingOverlay(show = true, message = 'Saving') {
            const container = document.getElementById('gearLoaderContainer');
            const messageSpan = document.getElementById('loaderMessage');
            
            if (show) {
                messageSpan.textContent = message;
                container.classList.add('show');
                
                // Animate dots
                const dots = document.getElementById('loaderDots');
                let dotCount = 1;
                if (dotAnimationInterval) clearInterval(dotAnimationInterval);
                dotAnimationInterval = setInterval(() => {
                    dotCount = (dotCount % 3) + 1;
                    dots.textContent = '.'.repeat(dotCount);
                }, 400);
            } else {
                if (dotAnimationInterval) clearInterval(dotAnimationInterval);
                container.classList.remove('show');
            }
        }
    </script>

    <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.3/dist/dotlottie-wc.js" type="module"></script>
    <!-- Lottie Delivery Loader -->
    <div id="gearLoaderContainer" style="display: none;">
        <div style="
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            gap: 5px;
        ">
            <dotlottie-wc src="https://lottie.host/d531cc06-7998-4c15-ae26-417653645a2b/imlJcgyrR1.lottie" style="width: 300px;height: 200px" speed="0.05" autoplay loop></dotlottie-wc>
            <div style="
                color: #6B21FF;
                font-weight: 700;
                font-size: 18px;
                text-transform: uppercase;
                letter-spacing: 3px;
                text-shadow: 0 0 10px rgba(107, 33, 255, 0.5);
            ">
                <span id="loaderMessage">Saving</span>
                <span id="loaderDots" style="margin-left: 8px;">.</span>
            </div>
        </div>
    </div>
    
    <style>
        /* Lottie Loader Styles */
        dotlottie-wc {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                transform: scale(0.8);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        #gearLoaderContainer {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            display: none !important;
            z-index: 99999 !important;
        }
        
        #gearLoaderContainer.show {
            display: flex !important;
        }
    </style>
</body>
</html>

