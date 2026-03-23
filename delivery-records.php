<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

// Include database configuration
require_once 'db_config.php';

// Include dataset indicator helper
require_once 'dataset-indicator.php';

// Get selected dataset from URL parameter or session
$selected_dataset = isset($_GET['dataset']) ? trim($_GET['dataset']) : (isset($_SESSION['active_dataset']) ? $_SESSION['active_dataset'] : 'all');

// Update session if dataset is passed via GET
if (isset($_GET['dataset'])) {
    $_SESSION['active_dataset'] = $selected_dataset;
}

// Build dataset filter clause for queries
$dataset_filter = "";
if ($selected_dataset !== 'all' && $selected_dataset !== '') {
    $safe_dataset = $conn->real_escape_string($selected_dataset);
    $dataset_filter = " AND dataset_name = '$safe_dataset'";
}

// Get statistics from database
$stats = [
    'total_delivered' => 0,
    'in_transit' => 0,
    'pending' => 0,
    'total_records' => 0,
    'total_quantity' => 0
];

// Count by status
$result = $conn->query("SELECT status, COUNT(*) as count, COALESCE(SUM(quantity), 0) as qty FROM delivery_records WHERE 1=1$dataset_filter GROUP BY status");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $status = strtolower($row['status']);
        if (strpos($status, 'deliver') !== false) {
            $stats['total_delivered'] += intval($row['count']);
            $stats['total_quantity'] += intval($row['qty']);
        } elseif (strpos($status, 'transit') !== false) {
            $stats['in_transit'] += intval($row['count']);
        } elseif (strpos($status, 'pending') !== false) {
            $stats['pending'] += intval($row['count']);
        }
        $stats['total_records'] += intval($row['count']);
    }
}

// Calculate success rate
$success_rate = $stats['total_records'] > 0 ? round(($stats['total_delivered'] / $stats['total_records']) * 100, 1) : 0;

// Get all delivery records (newest first)
$delivery_records = [];
$result = $conn->query("SELECT * FROM delivery_records WHERE 1=1$dataset_filter ORDER BY COALESCE(created_at, '1970-01-01 00:00:00') DESC, id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $delivery_records[] = $row;
    }
}

// Get distinct dataset names (data1, data2, ...)
$datasets = [];
try {
    $dsResult = $conn->query("SELECT DISTINCT dataset_name FROM delivery_records WHERE dataset_name IS NOT NULL AND dataset_name != '' ORDER BY dataset_name ASC");
    if ($dsResult) {
        while ($row = $dsResult->fetch_assoc()) {
            $datasets[] = $row['dataset_name'];
        }
    }
} catch (Exception $e) { /* column may not exist yet */ }

// Get all unique items for dropdown (excluding months and empty codes)
$monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
               'July', 'August', 'September', 'October', 'November', 'December'];
$allItems = [];
$itemResult = $conn->query("
    SELECT DISTINCT item_code, item_name
    FROM delivery_records
    WHERE item_code IS NOT NULL 
      AND item_code != ''
      AND item_name IS NOT NULL
      AND item_name != ''
      AND (box_code IS NULL OR box_code = '')
    ORDER BY item_code ASC
");

if ($itemResult) {
    while ($row = $itemResult->fetch_assoc()) {
        $code = trim($row['item_code']);
        $name = trim($row['item_name']);
        
        // Skip if item_code is just a month name or only whitespace
        if (!in_array($code, $monthNames) && !empty($code) && !empty($name)) {
            $allItems[] = [
                'code' => $code,
                'name' => $name
            ];
        }
    }
}

// If no items found, fetch from inventory (Stock Addition records)
if (empty($allItems)) {
    $inventoryResult = $conn->query("
        SELECT DISTINCT item_code, item_name
        FROM delivery_records
        WHERE company_name = 'Stock Addition'
          AND item_code IS NOT NULL 
          AND item_code != ''
          AND item_name IS NOT NULL
          AND item_name != ''
          AND (box_code IS NULL OR box_code = '')
        ORDER BY item_code ASC
    ");
    
    if ($inventoryResult) {
        while ($row = $inventoryResult->fetch_assoc()) {
            $code = trim($row['item_code']);
            $name = trim($row['item_name']);
            
            if (!in_array($code, $monthNames) && !empty($code) && !empty($name)) {
                $allItems[] = [
                    'code' => $code,
                    'name' => $name
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Records - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .filters {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        /* Search Bar Styles */
        .search-container {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #2f5fa7;
            background: rgba(255, 255, 255, 0.08);
        }
        
        .search-box input::placeholder {
            color: #7a8a9a;
        }
        
        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7a8a9a;
        }
        
        .search-count {
            color: #a0a0a0;
            font-size: 13px;
            padding: 10px 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }
        
        .filter-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e0e0e0;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            background: #2f5fa7;
            border-color: #2f5fa7;
            color: #fff;
        }
        
        .table-container {
            background: #13172c;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow-x: auto;
        }
        
        /* Hidden rows */
        tr.hidden-row {
            display: none;
        }
        
        /* Load More Button */
        .load-more-btn {
            background: linear-gradient(135deg, #2f5fa7, #1e3a6e);
            color: #fff;
            border: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .load-more-btn:hover {
            background: linear-gradient(135deg, #3a6fbd, #2a4a8e);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(47, 95, 167, 0.4);
        }
        
        .load-more-btn i {
            transition: transform 0.3s ease;
        }
        
        .load-more-btn:hover i {
            transform: translateY(3px);
        }
        
        .load-more-btn #hiddenCount {
            font-weight: 400;
            opacity: 0.8;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table thead {
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        table th {
            padding: 16px;
            text-align: left;
            font-size: 13px;
            color: #a0a0a0;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.6px;
        }

        table td {
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
            font-size: 14px;
            vertical-align: middle;
        }

        table th:last-child {
            text-align: center;
        }
        
        table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }
        
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge.delivered {            /* Keep table badges subtle; modal uses a stronger style */
            background: rgba(81, 207, 102, 0.2);
            color: #51cf66;
            padding: 6px 6px;
            font-weight: 700;
            border-radius: 10px;
        }
        
        .badge.in-transit {
            background: rgba(0, 217, 255, 0.2);
            color: #00d9ff;
        }
        
        .badge.pending {
            background: rgba(255, 214, 10, 0.2);
            color: #ffd60a;
        }
        
        .badge.cancelled {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .summary-label {
            font-size: 11px;
            color: #a0a0a0;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
        }
        
        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters {
                flex-direction: column;
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            overflow-y: auto;
            overflow-x: hidden;
        }

        .modal.show {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 20px;
        }
        
        body.modal-open {
            overflow: hidden;
            padding-right: 0;
        }

        .modal-content {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            padding: 30px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            width: 90%;
            max-width: 560px;
            color: #e0e0e0;
        }

        /* Larger modal for Add Record */
        .modal-content.modal-large {
            max-width: 750px;
            padding: 30px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            box-sizing: border-box;
            margin: 20px 0;
        }
        
        .modal-content.modal-large::-webkit-scrollbar {
            width: 6px;
        }
        
        .modal-content.modal-large::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 3px;
        }
        
        .modal-content.modal-large::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
        }

        .modal-header h2 {
            color: #fff;
            margin: 0;
            font-size: 18px;
            letter-spacing: 0.3px;
        }

        .close-btn {
            background: none;
            border: none;
            color: #a0a0a0;
            font-size: 28px;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: #fff;
        }

        .modal-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .modal-row {
            display: flex;
            flex-direction: column;
        }

        .modal-label {
            font-size: 11px;
            color: #a0a0a0;
            text-transform: uppercase;
            margin-bottom: 6px; 
            letter-spacing: 0.5px;
            flex: 0 0 0px; /* smaller fixed column for label */
        }

        .modal-value {
            font-size: 14px;
            color: #fff;
            font-weight: 600;
        }

        .modal-row.full {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: 96px 1fr;
            grid-auto-rows: auto;
            gap: 6px 12px;
        }

        /* place the badge under the left label column so status appears on the left */
        .modal-row.full .modal-label {
            grid-column: 1 / 2;
            align-self: start;
            margin-bottom: 0;
        }

        .modal-row.full .modal-badge {
            grid-column: 1 / 2;
            justify-self: start;
            align-self: center;
            display: inline-block;
            padding: 3px 6px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            color: #ffffff;
            background: linear-gradient(90deg, #2ecc71 0%, #51cf66 100%);
            box-shadow: 0 1px 3px rgba(49, 128, 60, 0.08);
            white-space: nowrap;
            margin-top: 6px;
            width: fit-content;
            letter-spacing: 0;
        }

        /* Export Button */
        .btn-export {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(39, 174, 96, 0.3);
        }

        .btn-export i {
            font-size: 15px;
        }

        /* Add Record Button */
        .btn-add-record {
            background: linear-gradient(135deg, #2f5fa7 0%, #1e88e5 100%);
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-add-record:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(47, 95, 167, 0.3);
        }

        /* Form styles */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 20px;
            width: 100%;
            box-sizing: border-box;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 11px;
            color: #a0a0a0;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            width: 100%;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2f5fa7;
            background: rgba(255, 255, 255, 0.12);
        }

        .form-group select option {
            background: #1e2a38;
            color: #fff;
            padding: 12px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }

        .input-hint {
            display: block;
            font-size: 11px;
            color: #8899a8;
            margin-top: 4px;
            font-style: italic;
        }

        html.light-mode .input-hint,
        body.light-mode .input-hint {
            color: #6a7a8a;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            justify-content: flex-end;
        }

        .btn-submit {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: #fff;
            border: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(46, 204, 113, 0.35);
        }

        .btn-cancel-form {
            background: rgba(255, 255, 255, 0.1);
            color: #a0a0a0;
            border: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-cancel-form:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        /* Action Buttons in Table */
        .action-cell {
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }

        .action-buttons .view-btn {
            color: #f4d03f;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 6px;
            background: rgba(244, 208, 63, 0.1);
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .action-buttons .view-btn:hover {
            color: #fff;
            background: rgba(244, 208, 63, 0.25);
            text-decoration: none;
        }

        .action-buttons .delete-btn {
            color: #e74c3c;
            text-decoration: none;
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 6px;
            transition: all 0.2s ease;
            background: rgba(231, 76, 60, 0.1);
            white-space: nowrap;
        }

        .action-buttons .delete-btn:hover {
            background: rgba(231, 76, 60, 0.25);
            color: #ff6b5b;
        }
        
        .action-buttons .edit-btn {
            color: #f39c12;
            text-decoration: none;
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 6px;
            transition: all 0.2s ease;
            background: rgba(243, 156, 18, 0.1);
            white-space: nowrap;
        }

        .action-buttons .edit-btn:hover {
            background: rgba(243, 156, 18, 0.25);
            color: #f5b041;
        }

        /* Delete Confirmation Modal */
        .delete-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .delete-modal.show {
            display: flex;
            opacity: 1;
        }

        .delete-modal-content {
            background: linear-gradient(145deg, #1e2a38, #16202c);
            border-radius: 16px;
            padding: 35px 40px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .delete-modal-icon {
            font-size: 60px;
            color: #e74c3c;
            margin-bottom: 20px;
        }

        .delete-modal-title {
            font-size: 22px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 12px;
        }

        .delete-modal-message {
            font-size: 15px;
            color: #a0a0a0;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .delete-modal-message strong {
            color: #f4d03f;
        }

        .delete-modal-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .delete-modal-btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-confirm-delete {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: #fff;
        }

        .btn-confirm-delete:hover {
            background: linear-gradient(135deg, #ff6b5b, #e74c3c);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
        }

        .btn-cancel-delete {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-cancel-delete:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        /* Light mode styles for action buttons */
        html.light-mode .action-buttons .view-btn,
        body.light-mode .action-buttons .view-btn {
            color: #1e88e5;
            background: rgba(30, 136, 229, 0.08);
        }

        html.light-mode .action-buttons .view-btn:hover,
        body.light-mode .action-buttons .view-btn:hover {
            color: #fff;
            background: #1e88e5;
        }

        html.light-mode .action-buttons .delete-btn,
        body.light-mode .action-buttons .delete-btn {
            color: #c0392b;
            background: rgba(231, 76, 60, 0.08);
        }

        html.light-mode .action-buttons .delete-btn:hover,
        body.light-mode .action-buttons .delete-btn:hover {
            background: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
        }

        html.light-mode .action-buttons .edit-btn,
        body.light-mode .action-buttons .edit-btn {
            color: #d68910;
            background: rgba(243, 156, 18, 0.08);
        }

        html.light-mode .action-buttons .edit-btn:hover,
        body.light-mode .action-buttons .edit-btn:hover {
            background: rgba(243, 156, 18, 0.15);
            color: #f39c12;
        }

        html.light-mode .delete-modal-content,
        body.light-mode .delete-modal-content {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        html.light-mode .delete-modal-title,
        body.light-mode .delete-modal-title {
            color: #1a3a5c;
        }

        html.light-mode .delete-modal-message,
        body.light-mode .delete-modal-message {
            color: #5a6a7a;
        }

        html.light-mode .delete-modal-message strong,
        body.light-mode .delete-modal-message strong {
            color: #1e88e5;
        }

        html.light-mode .btn-cancel-delete,
        body.light-mode .btn-cancel-delete {
            background: #e8f4fc;
            color: #1a3a5c;
            border: 1px solid #c5ddf0;
        }

        html.light-mode .btn-cancel-delete:hover,
        body.light-mode .btn-cancel-delete:hover {
            background: #d0e7f7;
        }

        /* Andison Manila Highlight */
        tr.andison-manila-row {
            background: linear-gradient(90deg, rgba(255, 214, 10, 0.15) 0%, rgba(255, 214, 10, 0.08) 100%) !important;
            border-left: 4px solid #ffd60a;
        }

        tr.andison-manila-row:hover {
            background: linear-gradient(90deg, rgba(255, 214, 10, 0.25) 0%, rgba(255, 214, 10, 0.15) 100%) !important;
        }

        tr.andison-manila-row td {
            color: #fff;
            font-weight: 500;
        }

        html.light-mode tr.andison-manila-row,
        body.light-mode tr.andison-manila-row {
            background: linear-gradient(90deg, rgba(255, 193, 7, 0.15) 0%, rgba(255, 193, 7, 0.08) 100%) !important;
            border-left: 4px solid #ffc107;
        }

        html.light-mode tr.andison-manila-row:hover,
        body.light-mode tr.andison-manila-row:hover {
            background: linear-gradient(90deg, rgba(255, 193, 7, 0.25) 0%, rgba(255, 193, 7, 0.15) 100%) !important;
        }

        html.light-mode tr.andison-manila-row td,
        body.light-mode tr.andison-manila-row td {
            color: #1a3a5c;
        }

        /* Loader Styles */
        #recordsLoader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 99999;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.3s cubic-bezier(0.22, 1, 0.36, 1), visibility 0.3s cubic-bezier(0.22, 1, 0.36, 1);
        }

        #recordsLoader.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .records-loader-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .records-loader-animation {
            width: 300px;
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .records-loader-animation dotlottie-wc {
            width: 300px;
            height: 300px;
            transform: translateZ(0);
            will-change: transform, opacity;
            filter: saturate(1.02);
        }

        .records-loader-fallback {
            display: none;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(196, 214, 226, 0.95);
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 10px rgba(196, 214, 226, 0.25);
        }

        .records-loader-fallback i {
            color: #0b5f94;
            font-size: 84px;
        }

        #recordsLoader.use-fallback dotlottie-wc {
            display: none;
        }

        #recordsLoader.use-fallback .records-loader-fallback {
            display: flex;
        }

        .records-loader-text {
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            letter-spacing: 1px;
        }

        .records-loader-dots {
            display: inline-block;
            margin-left: 4px;
            min-width: 18px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
    <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.3/dist/dotlottie-wc.js" type="module"></script>
</head>
<body>
    <!-- LOADER -->
    <div id="recordsLoader">
        <div class="records-loader-content">
            <div class="records-loader-animation">
                <dotlottie-wc src="https://lottie.host/d531cc06-7998-4c15-ae26-417653645a2b/imlJcgyrR1.lottie" style="width: 300px;height: 300px" speed="0.05" autoplay loop></dotlottie-wc>
                <div class="records-loader-fallback" id="recordsLoaderFallback" aria-hidden="true">
                    <i class="fas fa-truck"></i>
                </div>
            </div>
            <div class="records-loader-text" id="recordsLoaderText">LOADING<span class="records-loader-dots"></span></div>
        </div>
    </div>

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
                        <span class="profile-name"><?php echo htmlspecialchars(isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User'); ?></span>
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

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <!-- Sidebar Menu -->
            <ul class="sidebar-menu">
                <!-- Dashboard -->
                <li class="menu-item">
                    <a href="index.php" class="menu-link">
                        <i class="fas fa-chart-line"></i>
                        <span class="menu-label">Dashboard</span>
                    </a>
                </li>

                <!-- Sales Overview -->
                <li class="menu-item">
                    <a href="sales-overview.php" class="menu-link">
                        <i class="fas fa-chart-pie"></i>
                        <span class="menu-label">Sales Overview</span>
                    </a>
                </li>

                <li class="menu-item">
    <a href="orders.php" class="menu-link">
        <i class="fas fa-file-invoice-dollar"></i>
        <span class="menu-label">Orders</span>
    </a>
</li>

                <!-- Sales Records -->
                <li class="menu-item">
                    <a href="sales-records.php" class="menu-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="menu-label">Sales Records</span>
                    </a>
                </li>

                <!-- Delivery Records -->
                <li class="menu-item active">
                    <a href="delivery-records.php" class="menu-link">
                        <i class="fas fa-truck"></i>
                        <span class="menu-label">Delivery Records</span>
                    </a>
                </li>

                <!-- Inventory -->
                <li class="menu-item">
                    <a href="inventory.php" class="menu-link">
                        <i class="fas fa-boxes"></i>
                        <span class="menu-label">Inventory</span>
                    </a>
                </li>

                <!-- Andison Manila -->
                <li class="menu-item">
                    <a href="andison-manila.php" class="menu-link">
                        <i class="fas fa-truck-fast"></i>
                        <span class="menu-label">Andison Manila</span>
                    </a>
                </li>

                <!-- Client Companies -->
                <li class="menu-item">
                    <a href="client-companies.php" class="menu-link">
                        <i class="fas fa-building"></i>
                        <span class="menu-label">Client Companies</span>
                    </a>
                </li>

                <!-- Models -->
                <li class="menu-item">
                    <a href="models.php" class="menu-link">
                        <i class="fas fa-cube"></i>
                        <span class="menu-label">Models</span>
                    </a>
                </li>

                <!-- Reports -->
                <li class="menu-item">
                    <a href="reports.php" class="menu-link">
                        <i class="fas fa-file-alt"></i>
                        <span class="menu-label">Reports</span>
                    </a>
                </li>

                <!-- Upload Data -->
                <li class="menu-item">
                    <a href="upload-data.php" class="menu-link">
                        <i class="fas fa-upload"></i>
                        <span class="menu-label">Upload Data</span>
                    </a>
                </li>

                <!-- Settings -->
                <li class="menu-item">
                    <a href="settings.php" class="menu-link">
                        <i class="fas fa-cog"></i>
                        <span class="menu-label">Settings</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <p class="company-info">Andison Industrial</p>
            <p class="company-year">© 2025</p>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content" id="mainContent">
        <div class="page-title">
            <i class="fas fa-truck"></i> Delivery Records
        </div>
        
        <!-- Dataset Indicator Banner -->
        <div style="background: linear-gradient(90deg, #2a3f5f 0%, #1e2a38 100%); border-left: 4px solid #f4d03f; padding: 12px 16px; margin-bottom: 20px; border-radius: 6px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-database" style="color: #f4d03f; font-size: 14px;"></i>
            <span style="color: #8a9ab5; font-size: 12px;">Current Dataset:</span>
            <strong style="color: #fff; font-size: 13px;"><?php echo $selected_dataset === 'all' ? 'ALL DATA' : htmlspecialchars(strtoupper($selected_dataset)); ?></strong>
            <?php if ($selected_dataset !== 'all'): ?>
            <a href="delivery-records.php" style="margin-left: auto; color: #f4d03f; font-size: 12px; text-decoration: none; opacity: 0.8; transition: opacity .2s;" title="View all datasets">
                <i class="fas fa-times-circle"></i> Clear
            </a>
            <?php endif; ?>
        </div>

        <!-- Summary -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Total Records</div>
                <div class="summary-value"><?php echo number_format($stats['total_records']); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Total Delivered</div>
                <div class="summary-value"><?php echo number_format($stats['total_delivered']); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">In Transit</div>
                <div class="summary-value"><?php echo number_format($stats['in_transit']); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Pending</div>
                <div class="summary-value"><?php echo number_format($stats['pending']); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Total Quantity</div>
                <div class="summary-value"><?php echo number_format($stats['total_quantity']); ?></div>
            </div>
        </div>

        <p style="margin-bottom: 15px; color: #a0a0a0; font-size: 13px;">
            Showing <span id="visibleRowCount"><?php echo min(30, count($delivery_records)); ?></span> of <?php echo number_format($stats['total_records']); ?> records (latest first)
        </p>

        <!-- Search Bar -->
        <div class="search-container">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search by serial no, invoice, item, company..." onkeyup="searchTable()">
                <i class="fas fa-search"></i>
            </div>
            <div class="search-count" id="searchCount">Showing all records</div>
            <button class="btn-export" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export
            </button>
            <button class="btn-add-record" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add Record
            </button>
        </div>

        <!-- Filters -->
        <div class="filters">
            <button class="filter-btn active">All</button>
            <button class="filter-btn">Delivered</button>
            <button class="filter-btn">In Transit</button>
            <button class="filter-btn">Pending</button>
            <button class="filter-btn">Cancelled</button>
        </div>

        <!-- Delivery Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Invoice No.</th>
                        <th>Date</th>
                        <th>Delivery Month to Andison</th>
                        <th>Delivery Day to Andison</th>
                        <th>Year</th>
                        <th id="itemHeader">Item</th>
                        <th>Description</th>
                        <th>Qty.</th>
                        <th>Unit Price</th>
                        <th>UOM</th>
                        <th>Serial No.</th>
                        <th id="soldToHeader">Sold To</th>
                        <th>Date Delivered</th>
                        <th id="soldToMonthHeader">Sold To Month</th>
                        <th id="soldToDayHeader">Sold To Day</th>
                        <th>Groupings</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <th style="min-width: 160px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($delivery_records)): ?>
                    <tr>
                        <td colspan="19" style="text-align: center; padding: 40px; color: #a0a0a0;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                            No delivery records found. <a href="upload-data.php" style="color: #f4d03f;">Upload data</a> to get started.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $row_index = 0; foreach ($delivery_records as $record): 
                        $delivery_date = '';
                        if (!empty($record['delivery_date'])) {
                            $delivery_date = date('M j, Y', strtotime($record['delivery_date']));
                        }
                        
                        // Get sold_to fields - only show if they have actual values
                        $sold_to_month = !empty($record['sold_to_month']) ? $record['sold_to_month'] : '';
                        $sold_to_day = !empty($record['sold_to_day']) ? $record['sold_to_day'] : '';
                        
                        // Format the Date column from delivery_date
                        $date_col = '';
                        if (!empty($record['delivery_date'])) {
                            $date_col = date('m/d/Y', strtotime($record['delivery_date']));
                        }
                        
                        // Hide rows beyond initial limit (30)
                        $hidden_class = ($row_index >= 30) ? 'hidden-row' : '';
                        
                        // Check if this is Andison Manila (old records: company_name; new records: transferred_to)
                        $is_andison = (isset($record['company_name']) && $record['company_name'] === 'to Andison Manila')
                                   || (isset($record['transferred_to']) && $record['transferred_to'] === 'to Andison Manila');
                        $andison_class = $is_andison ? 'andison-manila-row' : '';
                        // Resolve actual end customer for Andison rows:
                        // - sold_to: old-style / normalized (company_name='to Andison Manila', sold_to=customer)
                        // - transferred_to='to Andison Manila': new-style, customer stored in company_name
                        // - transferred_to=customer (not 'to Andison Manila'): added from delivery-records.php
                        if (!empty($record['sold_to'])) {
                            $andison_sold_to = $record['sold_to'];
                        } elseif (!empty($record['transferred_to']) && $record['transferred_to'] === 'to Andison Manila'
                                  && !empty($record['company_name']) && $record['company_name'] !== 'to Andison Manila') {
                            // New-style: transferred_to='to Andison Manila', customer in company_name
                            $andison_sold_to = $record['company_name'];
                        } elseif (!empty($record['transferred_to']) && $record['transferred_to'] !== 'to Andison Manila') {
                            $andison_sold_to = $record['transferred_to'];
                        } else {
                            $andison_sold_to = '';
                        }

                        $statusText = trim((string) ($record['status'] ?? 'Pending'));
                        if ($statusText === '') {
                            $statusText = 'Pending';
                        }
                        $statusClass = 'pending';
                        $statusLower = strtolower($statusText);
                        if (strpos($statusLower, 'deliver') !== false) {
                            $statusClass = 'delivered';
                        } elseif (strpos($statusLower, 'transit') !== false) {
                            $statusClass = 'in-transit';
                        } elseif (strpos($statusLower, 'cancel') !== false) {
                            $statusClass = 'cancelled';
                        }
                    ?>
                    <tr data-record-id="<?php echo htmlspecialchars($record['id'] ?? ''); ?>" data-row-index="<?php echo $row_index; ?>" data-dataset="<?php echo htmlspecialchars($record['dataset_name'] ?? '', ENT_QUOTES); ?>" data-sold-to="<?php echo htmlspecialchars($is_andison ? $andison_sold_to : (isset($record['sold_to']) ? $record['sold_to'] : ''), ENT_QUOTES); ?>" class="<?php echo $hidden_class . ' ' . $andison_class; ?>">
                        <td><?php echo htmlspecialchars($record['invoice_no'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($date_col); ?></td>
                        <td><?php echo htmlspecialchars($record['delivery_month'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($record['delivery_day'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($record['delivery_year'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($record['item_code'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($record['item_name'] ?? ''); ?></td>
                        <td><?php echo (!empty($record['quantity']) && $record['quantity'] > 0) ? htmlspecialchars($record['quantity']) : ''; ?></td>
                        <td><?php echo (isset($record['unit_price']) && floatval($record['unit_price']) > 0) ? 'PHP ' . number_format(floatval($record['unit_price']), 2) : ''; ?></td>
                        <td><?php echo htmlspecialchars($record['uom'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($record['serial_no'] ?? ''); ?></td>
                        <td><?php
                            if ($is_andison) {
                                echo htmlspecialchars($andison_sold_to);
                            } else {
                                echo htmlspecialchars(!empty($record['sold_to']) ? $record['sold_to'] : ($record['company_name'] ?? ''));
                            }
                        ?></td>
                        <td><?php echo htmlspecialchars($delivery_date); ?></td>
                        <td><?php echo htmlspecialchars($sold_to_month); ?></td>
                        <td><?php echo htmlspecialchars($sold_to_day); ?></td>
                        <td><?php echo htmlspecialchars($record['groupings'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                        <td><span class="badge <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($statusText); ?></span></td>
                        <td class="action-cell">
                            <div class="action-buttons">
                                <a href="#" class="view-btn" onclick="openModal(event, <?php echo intval($record['id'] ?? 0); ?>)">View</a>
                                <a href="#" class="edit-btn" onclick="openEditModal(event, <?php echo intval($record['id'] ?? 0); ?>)" title="Edit Record"><i class="fas fa-edit"></i></a>
                                <a href="#" class="delete-btn" onclick="deleteRecord(event, <?php echo intval($record['id'] ?? 0); ?>, '<?php echo htmlspecialchars($record['serial_no'] ?? ''); ?>')" title="Delete Record"><i class="fas fa-trash-alt"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php $row_index++; endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if (count($delivery_records) > 30): ?>
            <div id="loadMoreContainer" style="text-align: center; margin-top: 20px; display: flex; gap: 15px; justify-content: center; align-items: center;">
                <button id="loadMoreBtn" class="load-more-btn" onclick="loadMoreRows()">
                    <i class="fas fa-chevron-down"></i> See More 
                    <span id="hiddenCount">(<?php echo count($delivery_records) - 30; ?> more records)</span>
                </button>
                <button class="load-more-btn" style="background: linear-gradient(135deg, #51cf66, #37a050);" onclick="showAllRows()">
                    <i class="fas fa-list"></i> Show All
                </button>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add Record Modal -->
    <div id="addRecordModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header" style="margin-bottom: 18px; padding-bottom: 12px;">
                <h2 style="font-size: 20px;"><i class="fas fa-plus-circle" style="color: #2ecc71; margin-right: 10px; font-size: 20px;"></i>Add New Delivery Record</h2>
                <button class="close-btn" onclick="closeAddModal()" style="font-size: 28px;">&times;</button>
            </div>
            <form id="addRecordForm" onsubmit="submitAddRecord(event)">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="add_invoice_no">Invoice No.</label>
                        <input type="text" id="add_invoice_no" name="invoice_no" placeholder="e.g., 5268850284">
                        <small class="input-hint">Unique invoice number from supplier/vendor</small>
                    </div>
                    <div class="form-group">
                        <label for="add_date">Date</label>
                        <input type="date" id="add_date" name="date">
                        <small class="input-hint">Date when record was created</small>
                    </div>
                    <div class="form-group">
                        <label for="add_delivery_month">Delivery Month to Andison</label>
                        <select id="add_delivery_month" name="delivery_month">
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
                        <small class="input-hint">Month item was delivered to Andison Industrial</small>
                    </div>
                    <div class="form-group">
                        <label for="add_delivery_day">Delivery Day to Andison</label>
                        <input type="number" id="add_delivery_day" name="delivery_day" placeholder="e.g., 7" min="1" max="31">
                        <small class="input-hint">Day of the month (1-31)</small>
                    </div>
                    <div class="form-group">
                        <label for="add_year">Year</label>
                        <input type="number" id="add_year" name="year" placeholder="e.g., 2025" min="2000" max="2100">
                        <small class="input-hint">Year of delivery for sales tracking</small>
                    </div>
                    <div class="form-group">
                        <label for="add_item_code">Item</label>
                        <select id="add_item_code" name="item_code" required>
                            <option value="">-- Select Item --</option>
                            <?php foreach ($allItems as $item): ?>
                                <option value="<?php echo htmlspecialchars($item['code']); ?>" data-name="<?php echo htmlspecialchars($item['name']); ?>">
                                    <?php echo htmlspecialchars($item['code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="input-hint">Select from existing product codes</small>
                    </div>
                    <div class="form-group full-width">
                        <label for="add_item_name">Description</label>
                        <input type="text" id="add_item_name" name="item_name" placeholder="e.g., GasAlertMax XT O2/LEL/H2S/CO">
                        <small class="input-hint">Full product name or description</small>
                    </div>
                    <div class="form-group">
                        <label for="add_quantity">Qty.</label>
                        <input type="number" id="add_quantity" name="quantity" placeholder="e.g., 40" min="0">
                        <small class="input-hint">Number of units delivered</small>
                    </div>
                    <div class="form-group">
                        <label for="add_unit_price">Unit Price</label>
                        <input type="number" id="add_unit_price" name="unit_price" placeholder="e.g., 1999.50" min="0" step="0.01">
                        <small class="input-hint">Price per unit</small>
                    </div>
                    <div class="form-group">
                        <label for="add_uom">UOM</label>
                        <input type="text" id="add_uom" name="uom" placeholder="e.g., units, pcs">
                        <small class="input-hint">Unit of measurement (pcs, units, etc.)</small>
                    </div>
                    <div class="form-group">
                        <label for="add_serial_no">Serial No.</label>
                        <input type="text" id="add_serial_no" name="serial_no" placeholder="e.g., MA225-000613">
                        <small class="input-hint">Product serial number for tracking</small>
                    </div>
                    <div class="form-group">
                        <label for="add_company_name">Sold To</label>
                        <input type="text" id="add_company_name" name="company_name" placeholder="e.g., to Andison Manila">
                        <small class="input-hint">Original delivery recipient (e.g., to Andison Manila)</small>
                    </div>
                    <div class="form-group">
                        <label for="add_delivery_date">Date Delivered</label>
                        <input type="date" id="add_delivery_date" name="delivery_date">
                        <small class="input-hint">Actual delivery date to client</small>
                    </div>
                    <div class="form-group">
                        <label for="add_sold_to_month">Sold To Month</label>
                        <select id="add_sold_to_month" name="sold_to_month">
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
                        <small class="input-hint">Month when sold to the client</small>
                    </div>
                    <div class="form-group">
                        <label for="add_sold_to_day">Sold To Day</label>
                        <input type="number" id="add_sold_to_day" name="sold_to_day" placeholder="e.g., 15" min="1" max="31">
                        <small class="input-hint">Day of the month (1-31)</small>
                    </div>
                    <div class="form-group">
                        <label for="add_groupings">Groupings</label>
                        <input type="text" id="add_groupings" name="groupings" placeholder="e.g., A, B, C">
                        <small class="input-hint">Category or batch grouping</small>
                    </div>
                    <div class="form-group">
                        <label for="add_status">Status</label>
                        <select id="add_status" name="status">
                            <option value="Delivered">Delivered</option>
                            <option value="Pending">Pending</option>
                            <option value="In Transit">In Transit</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                        <small class="input-hint">Current delivery status</small>
                    </div>
                    <div class="form-group full-width">
                        <label for="add_notes">Remarks</label>
                        <textarea id="add_notes" name="notes" rows="3" placeholder="Additional remarks..."></textarea>
                        <small class="input-hint">Any additional notes or comments about this delivery</small>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel-form" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Detail Modal -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTrackingId">Delivery Details</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-row">
                    <span class="modal-label">Invoice No.</span>
                    <span class="modal-value" id="modalInvoiceNo">-</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Date</span>
                    <span class="modal-value" id="modalDate">-</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Delivery Month</span>
                    <span class="modal-value" id="modalDeliveryMonth">-</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Delivery Day</span>
                    <span class="modal-value" id="modalDeliveryDay">-</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Year</span>
                    <span class="modal-value" id="modalYear">-</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Item</span>
                    <span class="modal-value" id="modalItem">-</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Description</span>
                    <span class="modal-value" id="modalDescription">-</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Qty.</span>
                    <span class="modal-value" id="modalQty">0</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Unit Price</span>
                    <span class="modal-value" id="modalUnitPrice">-</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">UOM</span>
                    <span class="modal-value" id="modalUom">-</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Serial No.</span>
                    <span class="modal-value" id="modalSerialNo">-</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Sold To</span>
                    <span class="modal-value" id="modalSoldTo">-</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Date Delivered</span>
                    <span class="modal-value" id="modalDeliveryDate">-</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Sold To Month</span>
                    <span class="modal-value" id="modalSoldToMonth">-</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Sold To Day</span>
                    <span class="modal-value" id="modalSoldToDay">-</span>
                </div>
                <div class="modal-row full-width">
                    <span class="modal-label">Remarks</span>
                    <span class="modal-value" id="modalRemarks">-</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Groupings</span>
                    <span class="modal-value" id="modalGroupings">-</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="delete-modal">
        <div class="delete-modal-content">
            <div class="delete-modal-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
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
            <div class="modal-header" style="margin-bottom: 18px; padding-bottom: 12px;">
                <h2 style="font-size: 20px;"><i class="fas fa-edit" style="color: #f39c12; margin-right: 10px; font-size: 20px;"></i>Edit Delivery Record</h2>
                <button class="close-btn" onclick="closeEditModal()" style="font-size: 28px;">&times;</button>
            </div>
            <form id="editRecordForm" onsubmit="submitEditRecord(event)">
                <input type="hidden" id="edit_id" name="id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_invoice_no">Invoice No.</label>
                        <input type="text" id="edit_invoice_no" name="invoice_no" placeholder="e.g., 5268850284">
                        <small class="input-hint">Unique invoice number from supplier/vendor</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_date">Date</label>
                        <input type="date" id="edit_date" name="date">
                        <small class="input-hint">Date when record was created</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_delivery_month">Delivery Month to Andison</label>
                        <select id="edit_delivery_month" name="delivery_month">
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
                        <small class="input-hint">Month item was delivered to Andison Industrial</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_delivery_day">Delivery Day to Andison</label>
                        <input type="number" id="edit_delivery_day" name="delivery_day" placeholder="e.g., 7" min="1" max="31">
                        <small class="input-hint">Day of the month (1-31)</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_year">Year</label>
                        <input type="number" id="edit_year" name="year" placeholder="e.g., 2025" min="2000" max="2100">
                        <small class="input-hint">Year of delivery for sales tracking</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_item_code">Item</label>
                        <select id="edit_item_code" name="item_code">
                            <option value="">-- Select Item --</option>
                            <?php foreach ($allItems as $item): ?>
                                <option value="<?php echo htmlspecialchars($item['code']); ?>" data-name="<?php echo htmlspecialchars($item['name']); ?>">
                                    <?php echo htmlspecialchars($item['code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="input-hint">Select from existing product codes</small>
                    </div>
                    <div class="form-group full-width">
                        <label for="edit_item_name">Description</label>
                        <input type="text" id="edit_item_name" name="item_name" placeholder="e.g., GasAlertMax XT O2/LEL/H2S/CO">
                        <small class="input-hint">Full product name or description</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_quantity">Qty.</label>
                        <input type="number" id="edit_quantity" name="quantity" placeholder="e.g., 40" min="0">
                        <small class="input-hint">Number of units delivered</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_unit_price">Unit Price</label>
                        <input type="number" id="edit_unit_price" name="unit_price" placeholder="e.g., 1999.50" min="0" step="0.01">
                        <small class="input-hint">Price per unit</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_uom">UOM</label>
                        <input type="text" id="edit_uom" name="uom" placeholder="e.g., units, pcs">
                        <small class="input-hint">Unit of measurement (pcs, units, etc.)</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_serial_no">Serial No.</label>
                        <input type="text" id="edit_serial_no" name="serial_no" placeholder="e.g., MA225-000613">
                        <small class="input-hint">Product serial number for tracking</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_company_name">Sold To</label>
                        <input type="text" id="edit_company_name" name="company_name" placeholder="e.g., to Andison Manila">
                        <small class="input-hint">Original delivery recipient (e.g., to Andison Manila)</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_delivery_date">Date Delivered</label>
                        <input type="date" id="edit_delivery_date" name="delivery_date">
                        <small class="input-hint">Actual delivery date to client</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_sold_to_month">Sold To Month</label>
                        <select id="edit_sold_to_month" name="sold_to_month">
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
                        <small class="input-hint">Month when sold to the client</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_sold_to_day">Sold To Day</label>
                        <input type="number" id="edit_sold_to_day" name="sold_to_day" placeholder="e.g., 15" min="1" max="31">
                        <small class="input-hint">Day of the month (1-31)</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_groupings">Groupings</label>
                        <input type="text" id="edit_groupings" name="groupings" placeholder="e.g., A, B, C">
                        <small class="input-hint">Category or batch grouping</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status">
                            <option value="Delivered">Delivered</option>
                            <option value="Pending">Pending</option>
                            <option value="In Transit">In Transit</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                        <small class="input-hint">Current delivery status</small>
                    </div>
                    <div class="form-group full-width">
                        <label for="edit_notes">Remarks</label>
                        <textarea id="edit_notes" name="notes" rows="3" placeholder="Additional remarks..."></textarea>
                        <small class="input-hint">Any additional notes or comments about this delivery</small>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel-form" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-submit" style="background: linear-gradient(135deg, #f39c12, #d68910);"><i class="fas fa-save"></i> Update Record</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/app.js" defer></script>
    <script src="js/xlsx.min.js"></script>
    <script>
        // Loader Functions
        let recordsLoaderDotsInterval = null;
        let recordsLoaderStartTime = null;
        const LOADER_MIN_DISPLAY_TIME = 3000; // 3 seconds in milliseconds

        function showRecordsLoader(message = 'LOADING') {
            const loader = document.getElementById('recordsLoader');
            const loaderText = document.getElementById('recordsLoaderText');
            if (!loader || !loaderText) return;

            recordsLoaderStartTime = Date.now(); // Record start time

            // If dotlottie web component is unavailable, force fallback truck icon.
            if (!window.customElements || !window.customElements.get('dotlottie-wc')) {
                loader.classList.add('use-fallback');
            } else {
                loader.classList.remove('use-fallback');
            }

            loaderText.innerHTML = message + '<span class="records-loader-dots" id="recordsLoaderDots">.</span>';
            loader.classList.add('show');

            const dots = document.getElementById('recordsLoaderDots');
            let dotCount = 1;
            if (recordsLoaderDotsInterval) clearInterval(recordsLoaderDotsInterval);
            recordsLoaderDotsInterval = setInterval(() => {
                dotCount = (dotCount % 3) + 1;
                if (dots) dots.textContent = '.'.repeat(dotCount);
            }, 400);
        }

        function hideRecordsLoader() {
            const loader = document.getElementById('recordsLoader');
            if (recordsLoaderDotsInterval) {
                clearInterval(recordsLoaderDotsInterval);
                recordsLoaderDotsInterval = null;
            }
            if (!loader) return;

            // Calculate elapsed time and add delay if needed
            const elapsedTime = Date.now() - (recordsLoaderStartTime || Date.now());
            const remainingTime = Math.max(0, LOADER_MIN_DISPLAY_TIME - elapsedTime);

            if (remainingTime > 0) {
                setTimeout(() => {
                    loader.classList.remove('show');
                }, remainingTime);
            } else {
                loader.classList.remove('show');
            }
        }

        // Store all records for modal viewing
        const recordsData = <?php echo json_encode($delivery_records); ?>;
        
        function openModal(event, id) {
            event.preventDefault();
            
            // Find record by ID
            const record = recordsData.find(r => parseInt(r.id) === parseInt(id));
            if (!record) {
                showToast('Record not found', 'error');
                return;
            }
            
            // Format dates - show empty if no data
            let dateCol = '';
            let deliveryDate = '';
            if (record.delivery_date) {
                const d = new Date(record.delivery_date);
                dateCol = d.toLocaleDateString('en-US');
                deliveryDate = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            }
            
            // Populate modal - show empty if no data
            document.getElementById('modalTrackingId').textContent = record.serial_no ? `${record.serial_no} Details` : 'Delivery Details';
            document.getElementById('modalInvoiceNo').textContent = record.invoice_no || '';
            document.getElementById('modalDate').textContent = dateCol;
            document.getElementById('modalDeliveryMonth').textContent = record.delivery_month || '';
            document.getElementById('modalDeliveryDay').textContent = record.delivery_day || '';
            document.getElementById('modalYear').textContent = record.delivery_year || '';
            document.getElementById('modalItem').textContent = record.item_code || '';
            document.getElementById('modalDescription').textContent = record.item_name || '';
            document.getElementById('modalQty').textContent = record.quantity || '';
            document.getElementById('modalUnitPrice').textContent = record.unit_price ? parseFloat(record.unit_price).toFixed(2) : '-';
            document.getElementById('modalUom').textContent = record.uom || '';
            document.getElementById('modalSerialNo').textContent = record.serial_no || '';
            document.getElementById('modalSoldTo').textContent = record.company_name || '';
            document.getElementById('modalDeliveryDate').textContent = deliveryDate;
            document.getElementById('modalSoldToMonth').textContent = record.sold_to_month || '';
            document.getElementById('modalSoldToDay').textContent = record.sold_to_day || '';
            document.getElementById('modalRemarks').textContent = record.notes || '';
            document.getElementById('modalGroupings').textContent = record.groupings || '';
            
            document.getElementById('detailModal').classList.add('show');
            document.body.classList.add('modal-open');
        }

        function closeModal() {
            document.getElementById('detailModal').classList.remove('show');
            document.body.classList.remove('modal-open');
        }

        // Delete Record Functions
        let deleteRecordId = null;
        let deleteRecordRow = null;

        function deleteRecord(event, recordId, itemCode) {
            event.preventDefault();
            deleteRecordId = recordId;
            deleteRecordRow = event.target.closest('tr');
            
            document.getElementById('deleteItemName').textContent = itemCode || 'this record';
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
            
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const originalText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            confirmBtn.disabled = true;
            showRecordsLoader('DELETING');
            
            fetch('api/delete-record.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: deleteRecordId })
            })
            .then(response => response.json())
            .then(result => {
                hideRecordsLoader();
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
                
                if (result.success) {
                    showToast('Record deleted!', 'success');
                    // Remove the row from table with animation
                    if (deleteRecordRow) {
                        deleteRecordRow.style.transition = 'all 0.3s ease';
                        deleteRecordRow.style.opacity = '0';
                        deleteRecordRow.style.transform = 'translateX(-20px)';
                        setTimeout(() => {
                            deleteRecordRow.remove();
                            updateSearchCount();
                        }, 300);
                    }
                    closeDeleteModal();
                } else {
                    showToast('Error: ' + (result.message || 'Failed to delete record'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideRecordsLoader();
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
                showToast('Error deleting record. Please try again.', 'error');
            });
        }

        // Close delete modal when clicking outside
        document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Add Record Modal Functions
        function openAddModal() {
            document.getElementById('addRecordModal').classList.add('show');
            document.body.classList.add('modal-open');
            // Set default date to today
            document.getElementById('add_delivery_date').value = new Date().toISOString().split('T')[0];
        }

        function closeAddModal() {
            document.getElementById('addRecordModal').classList.remove('show');
            document.body.classList.remove('modal-open');
            document.getElementById('addRecordForm').reset();
        }

        function submitAddRecord(event) {
            event.preventDefault();
            
            const form = document.getElementById('addRecordForm');
            const submitBtn = form.querySelector('.btn-submit');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;
            showRecordsLoader('SAVING');
            
            // Gather ALL form data
            const formData = {
                invoice_no: document.getElementById('add_invoice_no').value,
                date: document.getElementById('add_date').value,
                delivery_month: document.getElementById('add_delivery_month').value,
                delivery_day: parseInt(document.getElementById('add_delivery_day').value) || 0,
                year: parseInt(document.getElementById('add_year').value) || 0,
                item_code: document.getElementById('add_item_code').value,
                item_name: document.getElementById('add_item_name').value,
                quantity: parseInt(document.getElementById('add_quantity').value) || 0,
                unit_price: parseFloat(document.getElementById('add_unit_price').value) || 0,
                uom: document.getElementById('add_uom').value,
                serial_no: document.getElementById('add_serial_no').value,
                company_name: document.getElementById('add_company_name').value,
                delivery_date: document.getElementById('add_delivery_date').value,
                sold_to_month: document.getElementById('add_sold_to_month').value,
                sold_to_day: parseInt(document.getElementById('add_sold_to_day').value) || 0,
                groupings: document.getElementById('add_groupings').value,
                notes: document.getElementById('add_notes').value,
                status: document.getElementById('add_status').value,
                dataset_name: '<?php echo isset($selected_dataset) ? htmlspecialchars($selected_dataset) : "all"; ?>'
            };
            
            // Send to API
            fetch('api/add-record.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(result => {
                hideRecordsLoader();
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                if (result.success) {
                    showToast('Record added successfully!', 'success');
                    closeAddModal();
                    
                    // Add new record to the table at the top
                    if (result.record) {
                        const newRecord = result.record;
                        recordsData.unshift(newRecord); // Add to beginning of array
                        
                        // Format dates for display
                        const delivery_date = newRecord.delivery_date ? new Date(newRecord.delivery_date).toLocaleDateString('en-US') : '';
                        const date_col = newRecord.delivery_date ? new Date(newRecord.delivery_date).toLocaleDateString('en-US') : '';
                        const unit_price = newRecord.unit_price ? `PHP ${parseFloat(newRecord.unit_price).toFixed(2)}` : '';
                        
                        // Determine status badge
                        const status = newRecord.status || 'Delivered';
                        let badgeClass = 'delivered';
                        if (status.toLowerCase().includes('transit')) badgeClass = 'in-transit';
                        else if (status.toLowerCase().includes('pending')) badgeClass = 'pending';
                        else if (status.toLowerCase().includes('cancel')) badgeClass = 'cancelled';
                        
                        // Create new table row
                        const newRow = document.createElement('tr');
                        newRow.style.animation = 'slideIn 0.3s ease';
                        newRow.innerHTML = `
                            <td>${newRecord.invoice_no || ''}</td>
                            <td>${date_col}</td>
                            <td>${newRecord.delivery_month || ''}</td>
                            <td>${newRecord.delivery_day || ''}</td>
                            <td>${newRecord.delivery_year || ''}</td>
                            <td>${newRecord.item_code || ''}</td>
                            <td>${newRecord.item_name || ''}</td>
                            <td style="text-align:center;">${newRecord.quantity || ''}</td>
                            <td>${unit_price}</td>
                            <td>${newRecord.uom || ''}</td>
                            <td>${newRecord.serial_no || ''}</td>
                            <td>${newRecord.company_name || ''}</td>
                            <td>${delivery_date}</td>
                            <td>${newRecord.sold_to_month || ''}</td>
                            <td>${newRecord.sold_to_day || ''}</td>
                            <td>${newRecord.groupings || ''}</td>
                            <td>${newRecord.notes || ''}</td>
                            <td><span class="badge ${badgeClass}">${status}</span></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="#" class="view-btn" onclick="openModal(event, ${newRecord.id})" title="View Record"><i class="fas fa-eye"></i> View</a>
                                    <a href="#" class="edit-btn" onclick="openEditModal(event, ${newRecord.id})" title="Edit Record"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="#" class="delete-btn" onclick="deleteRecord(event, ${newRecord.id}, '${newRecord.serial_no || ''}')" title="Delete Record"><i class="fas fa-trash-alt"></i> Delete</a>
                                </div>
                            </td>
                        `;
                        
                        // Insert at top of table
                        const tableBody = document.querySelector('table tbody');
                        if (tableBody) {
                            tableBody.insertBefore(newRow, tableBody.firstChild);
                            
                            // Update load more logic: if we had 30 rows showing, the 31st becomes hidden now
                            const allRows = tableBody.querySelectorAll('tr:not([style*="display: none"])');
                            const visibleNonHiddenRows = Array.from(allRows).filter(r => !r.classList.contains('hidden-row')).length;
                            
                            if (visibleNonHiddenRows > 30) {
                                // Hide the last visible row that was previously at position 30
                                const rows = Array.from(allRows);
                                if (rows[30]) {
                                    rows[30].classList.add('hidden-row');
                                }
                            }
                            
                            // Update visible count
                            updateVisibleCount();
                            updateSearchCount();
                        }
                    }
                } else {
                    showToast('Error: ' + (result.message || 'Failed to add record'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideRecordsLoader();
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                showToast('Error adding record. Please try again.', 'error');
            });
        }

        // Close Add Modal when clicking outside
        window.addEventListener('click', (e) => {
            const addModal = document.getElementById('addRecordModal');
            if (e.target === addModal) {
                closeAddModal();
            }
        });

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            const modal = document.getElementById('detailModal');
            if (e.target === modal) {
                closeModal();
            }
        });

        // Edit Record Modal Functions
        function openEditModal(event, id) {
            event.preventDefault();
            
            // Find record by ID
            const record = recordsData.find(r => parseInt(r.id) === parseInt(id));
            if (!record) {
                showToast('Record not found', 'error');
                return;
            }
            
            // Populate form fields
            document.getElementById('edit_id').value = record.id;
            document.getElementById('edit_invoice_no').value = record.invoice_no || '';
            document.getElementById('edit_serial_no').value = record.serial_no || '';
            document.getElementById('edit_item_code').value = record.item_code || '';
            document.getElementById('edit_item_name').value = record.item_name || '';
            document.getElementById('edit_company_name').value = record.company_name || '';
            document.getElementById('edit_quantity').value = record.quantity || '';
            document.getElementById('edit_unit_price').value = record.unit_price || '';
            document.getElementById('edit_uom').value = record.uom || '';
            document.getElementById('edit_notes').value = record.notes || '';
            document.getElementById('edit_groupings').value = record.groupings || '';
            document.getElementById('edit_status').value = record.status || 'Delivered';
            
            // Date fields
            if (record.delivery_date) {
                document.getElementById('edit_date').value = record.delivery_date;
                document.getElementById('edit_delivery_date').value = record.delivery_date;
            } else {
                document.getElementById('edit_date').value = '';
                document.getElementById('edit_delivery_date').value = '';
            }
            
            // Month/Day fields
            document.getElementById('edit_delivery_month').value = record.delivery_month || '';
            document.getElementById('edit_delivery_day').value = record.delivery_day || '';
            document.getElementById('edit_year').value = record.delivery_year || '';
            document.getElementById('edit_sold_to_month').value = record.sold_to_month || '';
            document.getElementById('edit_sold_to_day').value = record.sold_to_day || '';
            
            // Show modal
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
            
            const form = document.getElementById('editRecordForm');
            const submitBtn = form.querySelector('.btn-submit');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
            showRecordsLoader('UPDATING');
            
            // Gather form data
            const formData = {
                id: document.getElementById('edit_id').value,
                serial_no: document.getElementById('edit_serial_no').value,
                invoice_no: document.getElementById('edit_invoice_no').value,
                item_code: document.getElementById('edit_item_code').value,
                item_name: document.getElementById('edit_item_name').value,
                company_name: document.getElementById('edit_company_name').value,
                quantity: parseInt(document.getElementById('edit_quantity').value) || 0,
                unit_price: parseFloat(document.getElementById('edit_unit_price').value) || 0,
                uom: document.getElementById('edit_uom').value,
                date: document.getElementById('edit_date').value,
                delivery_date: document.getElementById('edit_delivery_date').value,
                delivery_month: document.getElementById('edit_delivery_month').value,
                delivery_day: document.getElementById('edit_delivery_day').value,
                year: document.getElementById('edit_year').value,
                sold_to_month: document.getElementById('edit_sold_to_month').value,
                sold_to_day: document.getElementById('edit_sold_to_day').value,
                groupings: document.getElementById('edit_groupings').value,
                notes: document.getElementById('edit_notes').value,
                status: document.getElementById('edit_status').value
            };
            
            // Send to API
            fetch('api/update-record.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(result => {
                hideRecordsLoader();
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                if (result.success) {
                    showToast('Record updated successfully!', 'success');
                    closeEditModal();
                    // Reload page to show updated record
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showToast('Error: ' + (result.message || 'Failed to update record'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideRecordsLoader();
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                showToast('Error updating record. Please try again.', 'error');
            });
        }
        
        // Close Edit Modal when clicking outside
        window.addEventListener('click', (e) => {
            const editModal = document.getElementById('editRecordModal');
            if (e.target === editModal) {
                closeEditModal();
            }
        });

        // Hide Andison Manila rows on initial load ("All" is the default filter)
        document.querySelectorAll('table tbody tr.andison-manila-row').forEach(row => {
            row.style.display = 'none';
        });

        // Filter functionality
        const filterBtns = document.querySelectorAll('.filter-btn');

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active class from all buttons
                filterBtns.forEach(b => b.classList.remove('active'));
                // Add active class to clicked button
                this.classList.add('active');

                const filterValue = this.textContent.trim().toLowerCase();
                const tableRows = document.querySelectorAll('table tbody tr');
                const soldToHeader = document.getElementById('soldToHeader');
                const soldToMonthHeader = document.getElementById('soldToMonthHeader');
                const soldToDayHeader = document.getElementById('soldToDayHeader');
                const itemHeader = document.getElementById('itemHeader');

                // Show/hide rows based on filter
                tableRows.forEach(row => {
                    // Skip rows with colspan (empty state)
                    if (row.querySelector('td[colspan]')) return;
                    
                    if (filterValue === 'all') {
                        row.style.display = row.classList.contains('hidden-row') ? 'none' : '';
                    } else {
                        // Check for badge if it exists
                        const badge = row.querySelector('.badge');
                        if (badge) {
                            const badgeText = badge.textContent.trim().toLowerCase();
                            if (badgeText === filterValue || badgeText.includes(filterValue)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        } else {
                            // No badge, show all for "All" filter
                            row.style.display = '';
                        }
                    }
                });
                
                // Update header text based on filter
                if (itemHeader) itemHeader.textContent = 'Item';
                if (soldToMonthHeader) soldToMonthHeader.textContent = 'Sold To Month';
                if (soldToDayHeader) soldToDayHeader.textContent = 'Sold To Day';
                
                // Clear search box when filter changes
                document.getElementById('searchInput').value = '';
                updateSearchCount();
            });
        });

        // Search functionality
        let searchActive = false;
        
        function searchTable() {
            const searchInput = document.getElementById('searchInput');
            const filter = searchInput.value.toLowerCase().trim();
            const table = document.querySelector('table tbody');
            const rows = table.querySelectorAll('tr');
            
            let visibleCount = 0;
            
            // When searching, show all rows first (remove hidden-row class temporarily)
            if (filter !== '') {
                searchActive = true;
                rows.forEach(row => {
                    row.classList.remove('hidden-row');
                    row.style.display = '';
                });
            } else if (searchActive) {
                // Search cleared - restore hidden rows
                searchActive = false;
                rows.forEach((row, index) => {
                    if (row.querySelector('td[colspan]')) return;
                    const rowIndex = parseInt(row.getAttribute('data-row-index') || index);
                    if (rowIndex >= currentVisibleRows) {
                        row.classList.add('hidden-row');
                    }
                    row.style.display = '';
                });
            }
            
            rows.forEach((row, index) => {
                // Skip empty state row
                if (row.querySelector('td[colspan]')) {
                    return;
                }
                
                const cells = row.querySelectorAll('td');
                let found = false;
                
                cells.forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(filter)) {
                        found = true;
                    }
                });
                
                if (filter === '') {
                    // No filter - respect hidden-row class
                    if (!row.classList.contains('hidden-row')) {
                        visibleCount++;
                    }
                } else if (found) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            updateSearchCount(visibleCount, filter);
            
            // Hide load more button when searching
            const loadMoreContainer = document.getElementById('loadMoreContainer');
            if (loadMoreContainer) {
                loadMoreContainer.style.display = (filter !== '') ? 'none' : 'block';
            }
        }

        function updateSearchCount(count, filter) {
            const searchCount = document.getElementById('searchCount');
            const totalRows = document.querySelectorAll('table tbody tr:not([style*=\"display: none\"])').length;
            
            if (filter && filter !== '') {
                searchCount.innerHTML = `<i class="fas fa-filter"></i> Found <strong>${count}</strong> matching records`;
                searchCount.style.background = 'rgba(47, 95, 167, 0.2)';
                searchCount.style.color = '#6ba3eb';
            } else {
                searchCount.innerHTML = 'Showing all records';
                searchCount.style.background = 'rgba(255, 255, 255, 0.05)';
                searchCount.style.color = '#a0a0a0';
            }
        }

        // Load More functionality
        let currentVisibleRows = 30;
        const rowsPerLoad = 30;
        const totalRecords = <?php echo count($delivery_records); ?>;
        
        function updateVisibleCount() {
            const visibleCountEl = document.getElementById('visibleRowCount');
            if (visibleCountEl) {
                visibleCountEl.textContent = Math.min(currentVisibleRows, totalRecords);
            }
        }
        
        function loadMoreRows() {
            const hiddenRows = document.querySelectorAll('table tbody tr.hidden-row');
            let shown = 0;
            
            hiddenRows.forEach(row => {
                if (shown < rowsPerLoad) {
                    row.classList.remove('hidden-row');
                    shown++;
                }
            });
            
            currentVisibleRows += shown;
            updateVisibleCount();
            
            // Update hidden count or hide button
            const remainingHidden = document.querySelectorAll('table tbody tr.hidden-row').length;
            const hiddenCountSpan = document.getElementById('hiddenCount');
            const loadMoreContainer = document.getElementById('loadMoreContainer');
            
            if (remainingHidden === 0) {
                loadMoreContainer.innerHTML = '<p style="color: #51cf66; font-weight: 600;"><i class="fas fa-check-circle"></i> All records loaded</p>';
            } else {
                hiddenCountSpan.textContent = `(${remainingHidden} more records)`;
            }
        }
        
        function showAllRows() {
            const hiddenRows = document.querySelectorAll('table tbody tr.hidden-row');
            hiddenRows.forEach(row => {
                row.classList.remove('hidden-row');
            });
            
            currentVisibleRows = totalRecords;
            updateVisibleCount();
            
            const loadMoreContainer = document.getElementById('loadMoreContainer');
            if (loadMoreContainer) {
                loadMoreContainer.innerHTML = '<p style="color: #51cf66; font-weight: 600;"><i class="fas fa-check-circle"></i> All records loaded</p>';
            }
        }

        // Export to Excel function - Uses server-side generation for header colors
        function exportToExcel() {
            // Show loading state
            const exportBtn = document.querySelector('.btn-export');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
            exportBtn.disabled = true;
            
            // Use PHP endpoint for formatted Excel with header colors
            window.location.href = 'api/export-delivery.php';
            
            // Reset button after a short delay
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 2000);
        }

        // Sidebar toggle is handled by app.js

        // Toast notification
        function showToast(message, type = 'success') {
            const existing = document.getElementById('toastNotif');
            if (existing) existing.remove();

            const icons = {
                success: '&#10003;',
                error:   '&#10007;',
                warning: '&#9888;'
            };
            const colors = {
                success: 'linear-gradient(135deg,#1abc9c,#16a085)',
                error:   'linear-gradient(135deg,#e74c3c,#c0392b)',
                warning: 'linear-gradient(135deg,#f39c12,#d68910)'
            };

            const toast = document.createElement('div');
            toast.id = 'toastNotif';
            toast.innerHTML = `
                <span class="toast-icon">${icons[type] || icons.success}</span>
                <span class="toast-msg">${message}</span>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            `;
            Object.assign(toast.style, {
                position:     'fixed',
                top:          '24px',
                right:        '24px',
                zIndex:       '99999',
                display:      'flex',
                alignItems:   'center',
                gap:          '12px',
                minWidth:     '280px',
                maxWidth:     '400px',
                padding:      '16px 20px',
                borderRadius: '12px',
                background:   colors[type] || colors.success,
                color:        '#fff',
                fontFamily:   'inherit',
                fontSize:     '14px',
                fontWeight:   '500',
                boxShadow:    '0 8px 32px rgba(0,0,0,0.35)',
                animation:    'toastSlideIn .3s ease',
                cursor:       'default'
            });

            // Style inner elements
            toast.querySelector('.toast-icon').style.cssText = 'font-size:20px;flex-shrink:0;';
            toast.querySelector('.toast-msg').style.cssText  = 'flex:1;line-height:1.4;';
            toast.querySelector('.toast-close').style.cssText = 'background:none;border:none;color:#fff;font-size:20px;cursor:pointer;padding:0;line-height:1;opacity:.8;flex-shrink:0;';

            if (!document.getElementById('toastStyle')) {
                const s = document.createElement('style');
                s.id = 'toastStyle';
                s.textContent = '@keyframes toastSlideIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}';
                document.head.appendChild(s);
            }

            document.body.appendChild(toast);
            setTimeout(() => { if (toast.parentElement) toast.remove(); }, 4000);
        }

        // Dataset Management Functions
        let currentDatasetToManage = '';
        
        function openRenameModal(datasetName) {
            currentDatasetToManage = datasetName;
            document.getElementById('currentDatasetName').textContent = datasetName.toUpperCase();
            document.getElementById('newDatasetName').value = datasetName;
            document.getElementById('datasetManageModal').style.display = 'flex';
        }
        
        function closeDatasetModal() {
            document.getElementById('datasetManageModal').style.display = 'none';
            currentDatasetToManage = '';
        }
        
        async function renameDataset() {
            const newName = document.getElementById('newDatasetName').value.trim();
            if (!newName) {
                alert('Please enter a new name');
                return;
            }
            
            try {
                const response = await fetch('api/manage-dataset.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'rename',
                        old_name: currentDatasetToManage,
                        new_name: newName
                    })
                });
                const result = await response.json();
                
                if (result.success) {
                    // Update localStorage if this was the selected dataset
                    const selectedDataset = localStorage.getItem('selectedDataset');
                    if (selectedDataset === currentDatasetToManage) {
                        localStorage.setItem('selectedDataset', result.new_dataset_name);
                    }
                    showToast(result.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Error: ' + err.message, 'error');
            }
            closeDatasetModal();
        }
        
        async function deleteDataset() {
            if (!confirm(`Are you sure you want to DELETE all records in "${currentDatasetToManage.toUpperCase()}"? This cannot be undone!`)) {
                return;
            }
            
            try {
                const response = await fetch('api/manage-dataset.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        dataset_name: currentDatasetToManage
                    })
                });
                const result = await response.json();
                
                if (result.success) {
                    // Clear localStorage if this was the selected dataset
                    const selectedDataset = localStorage.getItem('selectedDataset');
                    if (selectedDataset === currentDatasetToManage) {
                        localStorage.setItem('selectedDataset', 'all');
                    }
                    showToast(result.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Error: ' + err.message, 'error');
            }
            closeDatasetModal();
        }

        // Handle item code dropdown selection - auto-fill item name
        document.getElementById('add_item_code').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const itemName = selectedOption.getAttribute('data-name');
            if (itemName) {
                document.getElementById('add_item_name').value = itemName;
            }
        });

        document.getElementById('edit_item_code').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const itemName = selectedOption.getAttribute('data-name');
            if (itemName) {
                document.getElementById('edit_item_name').value = itemName;
            }
        });
    </script>
    
    <!-- Dataset Management Modal -->
    <div id="datasetManageModal" onclick="if(event.target===this)closeDatasetModal()" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); justify-content:center; align-items:center; z-index:9999;">
        <div style="background:linear-gradient(135deg,#1e2a38 0%,#2a3f5f 100%); border-radius:16px; padding:30px; max-width:420px; width:90%; border:1px solid rgba(255,255,255,0.1); box-shadow:0 20px 40px rgba(0,0,0,0.4);">
            <h3 style="color:#f4d03f; margin:0 0 20px; font-size:18px; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-database"></i> Manage Dataset
            </h3>
            <p style="color:#8a9ab5; margin-bottom:20px; font-size:13px;">
                Current dataset: <strong id="currentDatasetName" style="color:#fff;"></strong>
            </p>
            
            <div style="margin-bottom:20px;">
                <label style="color:#a0b0c0; font-size:12px; display:block; margin-bottom:8px;">
                    <i class="fas fa-tag"></i> New Name:
                </label>
                <input type="text" id="newDatasetName" placeholder="Enter new dataset name..." 
                    style="width:100%; padding:12px 15px; border-radius:8px; border:1px solid rgba(255,255,255,0.2); background:rgba(0,0,0,0.3); color:#fff; font-size:14px; font-family:'Poppins',sans-serif; box-sizing:border-box;">
            </div>
            
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button onclick="renameDataset()" style="flex:1; background:linear-gradient(135deg,#f4d03f 0%,#e2b800 100%); color:#1a1a2e; border:none; padding:12px 20px; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px; font-family:'Poppins',sans-serif;">
                    <i class="fas fa-save"></i> Save Name
                </button>
                <button onclick="deleteDataset()" style="background:linear-gradient(135deg,#ff6b6b 0%,#ee5a24 100%); color:#fff; border:none; padding:12px 20px; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px; font-family:'Poppins',sans-serif;">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button onclick="closeDatasetModal()" style="background:rgba(255,255,255,0.1); color:#a0a0a0; border:1px solid rgba(255,255,255,0.2); padding:12px 20px; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px; font-family:'Poppins',sans-serif;">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</body>
</html>
