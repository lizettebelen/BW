<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

// Function to identify grouping based on item name
function identifyGrouping($itemName) {
    $lowerName = strtolower($itemName);
    
    // Multi Gas indicators
    if (strpos($lowerName, 'multi') !== false || 
        strpos($lowerName, 'quattro') !== false || 
        strpos($lowerName, 'quad') !== false ||
        strpos($lowerName, 'o2/lel/h2s/co') !== false ||
        preg_match('/o2.*lel|lel.*o2/', $lowerName)) {
        return 'Group B - Multi Gas';
    }
    
    // Single Gas indicators
    if (strpos($lowerName, 'single') !== false ||
        preg_match('/\b(O2|LEL|H2S|CO)\b/i', $lowerName)) {
        return 'Group A - Single Gas';
    }
    
    // Default to Single Gas if unclear
    return 'Group A - Single Gas';
}

// Initialize search and filter variables
$searchItem = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest'; // newest, name, code, stock
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'desc'; // asc, desc
$filterStock = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, critical, low, adequate, high

// Build search filter
$searchFilter = '';
if ($searchItem) {
    $searchItemEscape = $conn->real_escape_string($searchItem);
    $searchFilter = " AND (item_code LIKE '%{$searchItemEscape}%' OR item_name LIKE '%{$searchItemEscape}%')";
}

// Get all items with stock and delivery counts
$items = [];
$highlightItemCode = null;

// Check for item to highlight (from purchase order auto-add)
if (isset($_SESSION['highlight_item_code'])) {
    $highlightItemCode = strtoupper(trim($_SESSION['highlight_item_code']));
    unset($_SESSION['highlight_item_code']);
} elseif (isset($_GET['highlight'])) {
    $highlightItemCode = strtoupper(trim($_GET['highlight']));
}
$result = $conn->query("
    SELECT 
        id,
        item_code,
        item_name,
        box_code,
        model_no,
        quantity as current_stock,
        notes as source_filename,
        COALESCE(updated_at, created_at, datetime('now')) as last_updated
    FROM delivery_records
    WHERE company_name = 'Stock Addition' {$searchFilter}
    ORDER BY COALESCE(updated_at, created_at) DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $itemName = $row['item_name'];
        $lastUpdated = $row['last_updated'] ?? '';
        
        // Ensure timestamp is in a parseable format
        if (!empty($lastUpdated)) {
            $lastUpdated = trim($lastUpdated);
            // If it doesn't look like a proper datetime string, use current time
            if (!preg_match('/\d{4}-\d{2}-\d{2}/', $lastUpdated)) {
                $lastUpdated = date('Y-m-d H:i:s');
            }
        }
        
        $items[] = [
            'id' => $row['id'],
            'code' => $row['item_code'],
            'name' => $itemName,
            'box' => $row['box_code'],
            'model' => $row['model_no'],
            'stock' => intval($row['current_stock']),
            'actual_stock' => intval($row['current_stock']),
            'source_file' => $row['source_filename'] ? str_replace('File: ', '', $row['source_filename']) : '',
            'last_updated' => $lastUpdated,
            'grouping' => identifyGrouping($itemName),
            'is_highlighted' => ($highlightItemCode && strtoupper($row['item_code']) === $highlightItemCode)
        ];
    }
}

// Apply sorting
if ($sortBy === 'code') {
    usort($items, fn($a, $b) => strcmp($a['code'], $b['code']));
    if ($sortOrder === 'desc') {
        $items = array_reverse($items);
    }
} elseif ($sortBy === 'stock') {
    usort($items, fn($a, $b) => $a['stock'] - $b['stock']);
    if ($sortOrder === 'desc') {
        $items = array_reverse($items);
    }
} elseif ($sortBy === 'newest') {
    // Always show newest first (don't reverse)
    usort($items, fn($a, $b) => strtotime($b['last_updated']) - strtotime($a['last_updated']));
} else {
    usort($items, fn($a, $b) => strcmp($a['name'], $b['name']));
    if ($sortOrder === 'desc') {
        $items = array_reverse($items);
    }
}

// Apply stock status filter
if ($filterStock !== 'all') {
    $items = array_filter($items, function($item) use ($filterStock) {
        $stock = $item['stock'];
        switch ($filterStock) {
            case 'critical': return $stock <= 5;
            case 'low': return $stock > 5 && $stock <= 20;
            case 'adequate': return $stock > 20 && $stock <= 100;
            case 'high': return $stock > 100;
            default: return true;
        }
    });
    $items = array_values($items);
}

// Float highlighted item to the top
if ($highlightItemCode) {
    $highlightedItem = null;
    $otherItems = [];
    
    foreach ($items as $item) {
        if ($item['is_highlighted']) {
            $highlightedItem = $item;
        } else {
            $otherItems[] = $item;
        }
    }
    
    if ($highlightedItem) {
        $items = array_merge([$highlightedItem], $otherItems);
    }
}

// Calculate stats
$totalItems = count($items);
$totalStock = array_sum(array_column($items, 'stock'));
$criticalItems = count(array_filter($items, fn($i) => $i['stock'] <= 5));
$lowItems = count(array_filter($items, fn($i) => $i['stock'] > 5 && $i['stock'] <= 20));
$highStock = count(array_filter($items, fn($i) => $i['stock'] > 100));
$avgStock = $totalItems > 0 ? intval($totalStock / $totalItems) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <style>
        html, body {
            overflow-x: hidden;
        }

        .inventory-container,
        .section-title,
        .page-header {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
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
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title i {
            color: #f4d03f;
        }

        .search-box {
            display: flex;
            gap: 10px;
            align-items: center;
            flex: 1;
            min-width: 300px;
        }

        .search-box input {
            padding: 12px 16px;
            border-radius: 8px;
            border: 2px solid rgba(244, 208, 63, 0.3);
            background: linear-gradient(145deg, rgba(30, 42, 56, 0.95), rgba(20, 30, 45, 0.98));
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            min-width: 250px;
            transition: all 0.3s ease;
        }

        .search-box input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .search-box input:focus {
            outline: none;
            border-color: #f4d03f;
            box-shadow: 0 0 20px rgba(244, 208, 63, 0.2);
        }

        .search-box button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #f4d03f 0%, #f9d76a 100%);
            border: none;
            border-radius: 8px;
            color: #1a3a5c;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .search-box button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(244, 208, 63, 0.3);
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #fff;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f4d03f;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #f4d03f;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #f4d03f, transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: #f4d03f;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card .icon {
            font-size: 36px;
            color: #f4d03f;
            margin-bottom: 12px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 13px;
            color: #a0a0a0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .inventory-table-container {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 25px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        /* Column widths */
        .inventory-table th:nth-child(1),
        .inventory-table td:nth-child(1) {
            width: 50px;
            min-width: 50px;
        }

        .inventory-table th:nth-child(2),
        .inventory-table td:nth-child(2) {
            width: 80px;
            min-width: 80px;
        }

        .inventory-table th:nth-child(3),
        .inventory-table td:nth-child(3) {
            width: 120px;
            min-width: 120px;
        }

        .inventory-table th:nth-child(4),
        .inventory-table td:nth-child(4) {
            width: 160px;
            min-width: 160px;
        }

        .inventory-table th:nth-child(5),
        .inventory-table td:nth-child(5) {
            width: 130px;
            min-width: 130px;
        }

        .inventory-table th:nth-child(6),
        .inventory-table td:nth-child(6) {
            width: 80px;
            min-width: 80px;
        }

        .inventory-table th:nth-child(7),
        .inventory-table td:nth-child(7) {
            width: 70px;
            min-width: 70px;
        }

        .inventory-table th:nth-child(8),
        .inventory-table td:nth-child(8) {
            width: 130px;
            min-width: 130px;
        }

        .inventory-table th:nth-child(9),
        .inventory-table td:nth-child(9) {
            width: 100px;
            min-width: 100px;
            text-align: center;
        }

        .inventory-table thead th {
            background: rgba(47, 95, 167, 0.3);
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            color: #fff;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #f4d03f;
            cursor: pointer;
            transition: all 0.3s ease;
            user-select: none;
        }

        .inventory-table thead th:hover {
            background: rgba(47, 95, 167, 0.5);
            color: #f4d03f;
        }

        .inventory-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #e0e0e0;
            font-size: 13px;
            vertical-align: middle;
        }

        .inventory-table tbody tr:hover {
            background: rgba(47, 95, 167, 0.15);
        }

        .item-code {
            background: rgba(244, 208, 63, 0.15);
            color: #f4d03f;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }

        .stock-quantity {
            font-weight: 600;
            color: #2ecc71;
            font-size: 16px;
        }

        .stock-low {
            color: #ff6b6b;
        }

        .stock-warning {
            background: rgba(255, 107, 107, 0.15);
            color: #ff6b6b;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .stock-good {
            color: #2ecc71;
        }

        .delivery-info {
            font-size: 12px;
            color: #a0a0a0;
            margin-top: 4px;
        }

        .action-btn {
            padding: 6px 10px;
            background: linear-gradient(135deg, #f4d03f 0%, #f9d76a 100%);
            border: none;
            border-radius: 6px;
            color: #1a3a5c;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(244, 208, 63, 0.3);
        }

        .action-btn i {
            font-size: 12px;
        }

        .action-view {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: #fff;
        }

        .action-view:hover {
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
        }

        .action-add {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: #fff;
        }

        .action-add:hover {
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
        }

        .action-delete {
            background: linear-gradient(135deg, #ff6b6b 0%, #e74c3c 100%);
            color: #fff;
        }

        .action-delete:hover {
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);
        }

        .action-btn-horizontal {
            padding: 6px 8px !important;
            font-size: 11px !important;
            border-radius: 4px !important;
            display: inline-flex !important;
            align-items: center;
            gap: 3px !important;
        }

        .action-btn-horizontal i {
            font-size: 11px !important;
        }
            background: rgba(0, 217, 255, 0.15);
            color: #00d9ff;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
        }

        .last-delivery {
            font-size: 12px;
            color: #a0a0a0;
        }

        .no-items {
            text-align: center;
            padding: 40px 20px;
            color: #a0a0a0;
        }

        .no-items i {
            font-size: 48px;
            color: #666;
            margin-bottom: 15px;
            display: block;
        }

        .btn-submit, .btn-cancel {
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            margin-right: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Arial', sans-serif;
        }

        .btn-submit {
            background: linear-gradient(135deg, #2c5aa0 0%, #1e3a8a 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(44, 90, 160, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(44, 90, 160, 0.5);
            background: linear-gradient(135deg, #3d7bc4 0%, #2c5aa0 100%);
        }

        .btn-cancel {
            background: #e8eef7;
            color: #1e3a8a;
            border: 1px solid #2c5aa0;
            font-weight: 700;
        }

        .btn-cancel:hover {
            background: #d8e5f0;
            border-color: #1e3a8a;
            color: #1e3a8a;
        }

        .modal-body {
            padding: 20px;
        }

        .init-stock-item {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
            padding: 18px;
            background: linear-gradient(135deg, rgba(44, 90, 160, 0.08) 0%, rgba(30, 58, 138, 0.06) 100%);
            border: 1px solid #2c5aa0;
            border-radius: 10px;
            position: relative;
            box-shadow: 0 4px 12px rgba(44, 90, 160, 0.1);
            transition: all 0.3s ease;
        }

        .init-stock-item:hover {
            border-color: #3d7bc4;
            box-shadow: 0 6px 16px rgba(44, 90, 160, 0.15);
            background: linear-gradient(135deg, rgba(44, 90, 160, 0.12) 0%, rgba(30, 58, 138, 0.1) 100%);
        }

        .init-stock-item label {
            font-size: 12px;
            color: #1e3a8a;
            display: block;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            font-family: 'Arial', sans-serif;
        }

        .init-stock-item input[type="text"],
        .init-stock-item input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #2c5aa0;
            border-radius: 6px;
            background: #f0f4f8;
            color: #000;
            font-size: 14px;
            font-family: 'Arial', sans-serif;
            transition: all 0.2s ease;
        }

        .init-stock-item input[type="text"]:focus,
        .init-stock-item input[type="number"]:focus {
            background: #fff;
            border-color: #3d7bc4;
            outline: none;
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.15);
        }

        .init-stock-item input[type="text"] {
            color: #000;
            font-weight: 600;
        }

        .init-stock-item input[type="number"] {
            color: #000;
            font-weight: 700;
            font-size: 18px;
        }

        .init-stock-item input[type="number"]::placeholder {
            color: #ccc;
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
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.8) 0%, rgba(20, 30, 45, 0.7) 100%);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.9; }
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .modal-content {
            background: linear-gradient(145deg, #253547 0%, #1a2638 50%, #1a2638 100%);
            margin: 50px auto;
            padding: 45px;
            border: 2px solid #f4d03f;
            border-radius: 20px;
            width: 90%;
            max-width: 550px;
            animation: slideDown 0.3s ease;
            box-shadow: 0 30px 100px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 0 40px rgba(244, 208, 63, 0.15);
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes newItemGlow {
            0% {
                background: linear-gradient(90deg, rgba(255, 215, 0, 0.3) 0%, rgba(255, 235, 59, 0.2) 50%, rgba(255, 215, 0, 0.3) 100%);
                box-shadow: 0 0 25px rgba(255, 215, 0, 0.4), inset 0 0 25px rgba(255, 235, 59, 0.15), 0 4px 12px rgba(255, 215, 0, 0.2);
            }
            50% {
                background: linear-gradient(90deg, rgba(255, 235, 59, 0.25) 0%, rgba(255, 215, 0, 0.3) 50%, rgba(255, 235, 59, 0.25) 100%);
                box-shadow: 0 0 35px rgba(255, 215, 0, 0.5), inset 0 0 30px rgba(255, 235, 59, 0.2), 0 6px 16px rgba(255, 215, 0, 0.3);
            }
            100% {
                background: linear-gradient(90deg, rgba(255, 215, 0, 0.3) 0%, rgba(255, 235, 59, 0.2) 50%, rgba(255, 215, 0, 0.3) 100%);
                box-shadow: 0 0 25px rgba(255, 215, 0, 0.4), inset 0 0 25px rgba(255, 235, 59, 0.15), 0 4px 12px rgba(255, 215, 0, 0.2);
            }
        }

        .new-item-highlight {
            animation: newItemGlow 1.5s ease-in-out infinite !important;
            background: linear-gradient(90deg, rgba(255, 215, 0, 0.25) 0%, rgba(255, 235, 59, 0.15) 50%, rgba(255, 215, 0, 0.25) 100%) !important;
            border-left: 5px solid #ffd700 !important;
            border-top: 2px solid rgba(255, 215, 0, 0.8) !important;
            border-bottom: 2px solid rgba(255, 215, 0, 0.8) !important;
            box-shadow: inset 0 0 20px rgba(255, 215, 0, 0.2), 0 0 15px rgba(255, 215, 0, 0.3) !important;
            position: relative;
        }

        .new-item-highlight td {
            background: inherit !important;
        }

        .new-item-highlight td:first-child::before {
            content: '✨';
            display: inline-block;
            margin-right: 6px;
            font-size: 14px;
            animation: pulse 2s ease-in-out infinite;
        }

        .purchase-order-highlight {
            outline: 3px solid rgba(52, 152, 219, 0.85);
            outline-offset: -2px;
        }

        .purchase-order-highlight td:first-child::after {
            content: ' NEW';
            display: inline-block;
            margin-left: 6px;
            color: #3498db;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.8px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding-bottom: 18px;
            border-bottom: 2px solid #f4d03f;
        }

        .modal-header h2 {
            color: #ffffff;
            font-size: 26px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .modal-header h2 i {
            color: #f4d03f;
            font-size: 24px;
        }

        .close-btn {
            background: none;
            border: none;
            color: #8a9ab5;
            font-size: 32px;
            cursor: pointer;
            transition: all 0.3s;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-btn:hover {
            color: #f4d03f;
            transform: scale(1.15) rotate(90deg);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #b8c5d6;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 10px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border-radius: 10px;
            border: 1px solid rgba(244, 208, 63, 0.25);
            background: linear-gradient(135deg, rgba(30, 42, 56, 0.6) 0%, rgba(20, 30, 45, 0.8) 100%);
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: rgba(255, 255, 255, 0.35);
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #f4d03f;
            background: linear-gradient(135deg, rgba(30, 42, 56, 0.8) 0%, rgba(20, 30, 45, 0.95) 100%);
            box-shadow: 0 0 15px rgba(244, 208, 63, 0.3), inset 0 0 8px rgba(244, 208, 63, 0.05);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group small {
            color: #8a9ab5 !important;
            font-size: 12px !important;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }

        .form-actions button {
            flex: 1;
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            letter-spacing: 0.5px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #f4d03f 0%, #f9d76a 100%);
            color: #1a2332;
            font-weight: 800;
            box-shadow: 0 6px 20px rgba(244, 208, 63, 0.3);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .btn-submit:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 30px rgba(244, 208, 63, 0.4);
        }

        .btn-submit:active {
            transform: translateY(-1px) scale(1);
        }

        .btn-cancel {
            background: linear-gradient(135deg, rgba(100, 120, 150, 0.4) 0%, rgba(80, 100, 130, 0.3) 100%);
            color: #e0e0e0;
            border: 2px solid rgba(200, 210, 230, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .btn-cancel:hover {
            background: linear-gradient(135deg, rgba(120, 140, 170, 0.5) 0%, rgba(100, 120, 150, 0.4) 100%);
            border-color: rgba(220, 230, 250, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .btn-cancel:active {
            transform: translateY(0);
        }

        .alert {
            padding: 15px 18px;
            border-radius: 10px;
            margin-bottom: 18px;
            display: none;
            animation: slideDown 0.3s ease;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert i {
            font-size: 16px;
            flex-shrink: 0;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.12) 0%, rgba(46, 204, 113, 0.06) 100%);
            border: 1px solid rgba(46, 204, 113, 0.25);
            color: #2ecc71;
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.12) 0%, rgba(255, 107, 107, 0.06) 100%);
            border: 1px solid rgba(255, 107, 107, 0.25);
            color: #ff6b6b;
        }

        .add-stock-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #f4d03f 0%, #f9d76a 100%);
            border: none;
            border-radius: 8px;
            color: #1a3a5c;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .add-stock-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(244, 208, 63, 0.3);
        }

        /* Light Mode Modal */
        html.light-mode .modal-content,
        body.light-mode .modal-content {
            background: linear-gradient(145deg, #ffffff, #f5f7fa);
            border: 2px solid #2c5aa0;
        }

        html.light-mode .modal-header h2,
        body.light-mode .modal-header h2 {
            color: #1e3a8a;
        }

        html.light-mode .modal-header,
        body.light-mode .modal-header {
            border-bottom: 2px solid #2c5aa0;
        }

        html.light-mode .form-group label,
        body.light-mode .form-group label {
            color: #1e3a8a;
        }

        html.light-mode .form-group input,
        body.light-mode .form-group input,
        html.light-mode .form-group textarea,
        body.light-mode .form-group textarea {
            background: linear-gradient(145deg, #ffffff, #f0f4f8);
            border: 2px solid #2c5aa0;
            color: #000;
        }

        html.light-mode .form-group input::placeholder,
        body.light-mode .form-group input::placeholder,
        html.light-mode .form-group textarea::placeholder,
        body.light-mode .form-group textarea::placeholder {
            color: rgba(0, 0, 0, 0.4);
        }

        html.light-mode .form-group input:focus,
        body.light-mode .form-group input:focus,
        html.light-mode .form-group textarea:focus,
        body.light-mode .form-group textarea:focus {
            border-color: #1e3a8a;
            box-shadow: 0 0 15px rgba(44, 90, 160, 0.2);
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 100px auto;
                padding: 20px;
            }

            .modal-header h2 {
                font-size: 18px;
            }
        }
        html.light-mode .page-title,
        body.light-mode .page-title,
        html.light-mode .section-title,
        body.light-mode .section-title {
            color: #1a3a5c;
        }

        html.light-mode .stat-card,
        body.light-mode .stat-card,
        html.light-mode .inventory-table-container,
        body.light-mode .inventory-table-container {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border: 1px solid #c5ddf0;
        }

        html.light-mode .stat-card .value,
        body.light-mode .stat-card .value {
            color: #1a3a5c;
        }

        html.light-mode .stat-card .label,
        body.light-mode .stat-card .label {
            color: #5a6a7a;
        }

        html.light-mode .inventory-table thead th,
        body.light-mode .inventory-table thead th {
            background: rgba(30, 136, 229, 0.1);
            color: #1a3a5c;
            border-bottom: 2px solid #1e88e5;
        }

        html.light-mode .inventory-table tbody td,
        body.light-mode .inventory-table tbody td {
            color: #333;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle !important;
        }

        html.light-mode .inventory-table tbody tr,
        body.light-mode .inventory-table tbody tr {
            background: #fff;
        }

        html.light-mode .inventory-table tbody tr:hover,
        body.light-mode .inventory-table tbody tr:hover {
            background: rgba(30, 136, 229, 0.05);
        }

        html.light-mode .item-code,
        body.light-mode .item-code {
            background: rgba(30, 136, 229, 0.15);
            color: #1e88e5;
        }

        html.light-mode .stock-quantity,
        body.light-mode .stock-quantity {
            color: #2ecc71;
        }

        html.light-mode .delivery-count,
        body.light-mode .delivery-count {
            background: rgba(30, 136, 229, 0.15);
            color: #1e88e5;
        }

        html.light-mode .last-delivery,
        body.light-mode .last-delivery {
            color: #5a6a7a;
        }

        html.light-mode .search-box input,
        body.light-mode .search-box input {
            background: linear-gradient(145deg, #ffffff, #f0f7ff);
            border: 2px solid rgba(30, 136, 229, 0.3);
            color: #1a3a5c;
        }

        html.light-mode .search-box input::placeholder,
        body.light-mode .search-box input::placeholder {
            color: rgba(0, 0, 0, 0.4);
        }

        html.light-mode .search-box input:focus,
        body.light-mode .search-box input:focus {
            border-color: #1e88e5;
            box-shadow: 0 0 20px rgba(30, 136, 229, 0.2);
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .stats-cards {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 1200px) {
            .stats-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 992px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-box {
                width: 100%;
                flex-direction: column;
                min-width: unset;
            }

            .search-box input {
                width: 100%;
                min-width: unset;
            }

            .stats-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 15px;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-card .value {
                font-size: 26px;
            }

            .inventory-table thead th,
            .inventory-table tbody td {
                padding: 12px 10px;
                font-size: 12px;
                vertical-align: middle !important;
            }

            .add-stock-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 24px;
            }

            .stats-cards {
                grid-template-columns: minmax(0, 1fr);
                gap: 12px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card .icon {
                font-size: 28px;
            }

            .stat-card .value {
                font-size: 22px;
            }

            .stat-card .label {
                font-size: 11px;
            }

            .page-header {
                margin-bottom: 20px;
            }

            .inventory-table thead th,
            .inventory-table tbody td {
                padding: 10px 8px;
                font-size: 11px;
                vertical-align: middle !important;
            }

            .section-title {
                font-size: 18px;
            }
        }

        @media (max-width: 576px) {
            .page-title {
                font-size: 20px;
            }

            .stat-card {
                padding: 15px;
                display: flex;
                align-items: center;
                gap: 12px;
                text-align: left;
            }

            .stat-card .icon {
                font-size: 24px;
            }

            .inventory-table {
                font-size: 12px;
            }

            .inventory-table thead th,
            .inventory-table tbody td {
                padding: 8px 6px;
                vertical-align: middle !important;
            }
        }
        
        
        /* Tab Styles */
        .tab-navigation {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 0;
        }

        .tab-btn {
            padding: 12px 24px;
            background: transparent;
            border: none;
            color: #999;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 0;
        }

        .tab-btn:hover {
            color: #fff;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #f4d03f 0%, #f9d76a 100%);
            color: #1a3a5c;
            font-weight: 700;
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(135deg, #f4d03f 0%, #f9d76a 100%);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeInTab 0.3s ease-in-out;
        }

        @keyframes fadeInTab {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        html.light-mode .tab-btn,
        body.light-mode .tab-btn {
            color: #666;
        }

        html.light-mode .tab-btn:hover,
        body.light-mode .tab-btn:hover {
            color: #333;
        }

        html.light-mode .tab-navigation,
        body.light-mode .tab-navigation {
            border-bottom: 2px solid #dfe8f0;
        }

        
        
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

        /* Create Order Modal Styles (from orders.php) - For Andison's internal Purchase Orders */
        .create-order-modal {
            position: fixed;
            inset: 0;
            background: rgba(7, 12, 22, 0.58);
            backdrop-filter: blur(3px);
            z-index: 1300;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .create-order-modal.show {
            display: flex;
        }
        .create-order-dialog,
        .form-card {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border: 1px solid rgba(255,255,255,0.08);
            width: min(1120px, 96vw);
            max-height: 88vh;
            overflow: auto;
            border-radius: 14px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
            padding: 24px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #d0dce6;
            font-size: 13px;
            font-weight: 600;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.05);
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #f4d03f;
            background: rgba(255,255,255,0.08);
            box-shadow: 0 0 12px rgba(244, 208, 63, 0.15);
        }
        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: rgba(255,255,255,0.3);
        }
        .form-group textarea { 
            min-height: 100px; 
            resize: vertical;
            font-family: 'Poppins', sans-serif;
        }
        .form-actions { 
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        .save-order-btn {
            flex: 1;
            min-width: 140px;
            justify-content: center;
            background: linear-gradient(135deg, #f4d03f 0%, #f9d76a 100%);
            color: #1a3a5c;
            border: none;
            padding: 14px 24px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .save-order-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(244, 208, 63, 0.3);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 12px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-title {
            margin: 0;
            color: #fff;
            font-size: 22px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .modal-title i {
            color: #f4d03f;
        }
        .close-btn {
            border: 1px solid rgba(255,255,255,0.16);
            background: rgba(255,255,255,0.06);
            color: #d0dce6;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .close-btn:hover {
            background: rgba(255,255,255,0.13);
            color: #fff;
        }

        /* Light Mode Styling */
        html.light-mode .create-order-modal,
        body.light-mode .create-order-modal {
            background: rgba(24, 61, 96, 0.22);
        }
        html.light-mode .create-order-dialog,
        html.light-mode .form-card,
        body.light-mode .create-order-dialog,
        body.light-mode .form-card {
            background: #ffffff;
            border-color: #d8e2ef;
        }
        html.light-mode .modal-header,
        body.light-mode .modal-header {
            border-bottom-color: #e0e8f0;
        }
        html.light-mode .modal-title,
        body.light-mode .modal-title {
            color: #1a1a1a;
        }
        html.light-mode .form-group label,
        body.light-mode .form-group label {
            color: #2f4c68;
        }
        html.light-mode .form-group input,
        html.light-mode .form-group select,
        html.light-mode .form-group textarea,
        body.light-mode .form-group input,
        body.light-mode .form-group select,
        body.light-mode .form-group textarea {
            background: #f5f9fc;
            border-color: #c8dae8;
            color: #1a1a1a;
        }
        html.light-mode .form-group input:focus,
        html.light-mode .form-group select:focus,
        html.light-mode .form-group textarea:focus,
        body.light-mode .form-group input:focus,
        body.light-mode .form-group select:focus,
        body.light-mode .form-group textarea:focus {
            border-color: #1e88e5;
            background: #ffffff;
            box-shadow: 0 0 12px rgba(30, 136, 229, 0.15);
        }
        html.light-mode .form-group input::placeholder,
        html.light-mode .form-group textarea::placeholder,
        body.light-mode .form-group input::placeholder,
        body.light-mode .form-group textarea::placeholder {
            color: #9db3c4;
        }
        html.light-mode .save-order-btn,
        body.light-mode .save-order-btn {
            background: linear-gradient(135deg, #f4d03f 0%, #f9d76a 100%);
            color: #1a3a5c;
        }
        html.light-mode .close-btn,
        body.light-mode .close-btn {
            border-color: #c8dae8;
            background: #f5f9fc;
            color: #2f4c68;
        }
        html.light-mode .close-btn:hover,
        body.light-mode .close-btn:hover {
            background: #eef6fd;
            color: #1e4f7a;
        }

        @media (max-width: 860px) {
            .create-order-dialog {
                width: 100%;
                max-height: 92vh;
            }
        }

        /* Filter Chips Styling (for Orders tab) */
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .filter-chip {
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.2);
            color: #9fb1c5;
            text-decoration: none;
            font-size: 13px;
            background: rgba(255,255,255,0.04);
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-block;
        }
        .filter-chip:hover {
            color: #d0dce6;
            border-color: #f4d03f;
        }
        .filter-chip.active {
            background: #2f5fa7;
            border-color: #2f5fa7;
            color: #fff;
        }
        html.light-mode .filter-chip,
        body.light-mode .filter-chip {
            background: #ffffff;
            color: #3a6a8a;
            border-color: #b8d4e8;
        }
        html.light-mode .filter-chip:hover,
        body.light-mode .filter-chip:hover {
            background: #eef6fd;
            color: #1e4f7a;
            border-color: #1e88e5;
        }
        html.light-mode .filter-chip.active,
        body.light-mode .filter-chip.active {
            background: #2f5fa7;
            border-color: #2f5fa7;
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(47, 95, 167, 0.25);
        }
    </style>
    <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.3/dist/dotlottie-wc.js" type="module"></script>
</head>
<body>
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
            <dotlottie-wc src="https://lottie.host/d531cc06-7998-4c15-ae26-417653645a2b/imlJcgyrR1.lottie" style="width: 300px;height: 200px" speed="1.9" autoplay loop></dotlottie-wc>
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
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <ul class="sidebar-menu">
                    <li class="menu-item">
                        <a href="index.php" class="menu-link">
                            <i class="fas fa-chart-line"></i>
                            <span class="menu-label">Dashboard</span>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="sales-overview.php" class="menu-link">
                            <i class="fas fa-chart-pie"></i>
                            <span class="menu-label">Sales Overview</span>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="sales-records.php" class="menu-link">
                            <i class="fas fa-calendar-alt"></i>
                            <span class="menu-label">Sales Records</span>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="delivery-records.php" class="menu-link">
                            <i class="fas fa-truck"></i>
                            <span class="menu-label">Delivery Records</span>
                        </a>
                    </li>

                    <li class="menu-item active">
                        <a href="inventory.php" class="menu-link">
                            <i class="fas fa-boxes"></i>
                            <span class="menu-label">Inventory</span>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="andison-manila.php" class="menu-link">
                            <i class="fas fa-truck-fast"></i>
                            <span class="menu-label">Andison Manila</span>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="client-companies.php" class="menu-link">
                            <i class="fas fa-building"></i>
                            <span class="menu-label">Client Companies</span>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="models.php" class="menu-link">
                            <i class="fas fa-cube"></i>
                            <span class="menu-label">Models</span>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="reports.php" class="menu-link">
                            <i class="fas fa-file-alt"></i>
                            <span class="menu-label">Reports</span>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="upload-data.php" class="menu-link">
                            <i class="fas fa-upload"></i>
                            <span class="menu-label">Upload Data</span>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="settings.php" class="menu-link">
                            <i class="fas fa-cog"></i>
                            <span class="menu-label">Settings</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <p class="company-info">Andison Industrial</p>
                <p class="company-year">© 2025</p>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content" id="mainContent">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-boxes"></i>
                    Inventory
                </h1>
                <div class="search-box">
                    <form method="get" style="display: flex; gap: 10px; width: 100%; align-items: center;">
                        <input 
                            type="text" 
                            name="search" 
                            placeholder="Search by item code or name..." 
                            value="<?php echo htmlspecialchars($searchItem); ?>"
                        >
                        <button type="submit"><i class="fas fa-search"></i> Search</button>
                        <?php if ($searchItem): ?>
                            <a href="inventory.php" style="padding: 12px 24px; background: #666; border-radius: 8px; color: #fff; text-decoration: none; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                <button class="add-stock-btn" style="background: linear-gradient(135deg, #f4d03f 0%, #f9d76a 100%);" onclick="openAddItemModal()">
                    <i class="fas fa-plus"></i> Add New Item
                </button>
                <button class="add-stock-btn" style="background: linear-gradient(135deg, #f4d03f 0%, #f9d76a 100%); display: none;" id="toggleCreateBtn">
                    <i class="fas fa-plus"></i> Create Order
                </button>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <button class="tab-btn active" onclick="openTab(event, 'inventory-tab')">
                    <i class="fas fa-boxes" style="margin-right: 6px;"></i> Inventory
                </button>
                <button class="tab-btn" onclick="openTab(event, 'orders-tab')">
                    <i class="fas fa-shopping-cart" style="margin-right: 6px;"></i> Purchase Orders
                </button>
            </div>

            <!-- Inventory Tab Content -->
            <div id="inventory-tab" class="tab-content active">

            <!-- Filter and Sort Controls Container -->
            <div style="background: linear-gradient(135deg, rgba(15, 20, 25, 0.6) 0%, rgba(26, 31, 46, 0.4) 100%); border: 1px solid rgba(23, 162, 184, 0.2); border-radius: 14px; padding: 0; margin-bottom: 16px; overflow: hidden;">
                
                <!-- Filter Section -->
                <div style="padding: 12px 20px; background: rgba(255, 255, 255, 0.02); border-bottom: 1px solid rgba(255, 255, 255, 0.06);">
                    <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                        <span style="color: #f4d03f; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px; white-space: nowrap; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-filter" style="font-size: 12px;"></i> FILTER
                        </span>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; flex: 1;">
                            <a href="<?php echo '?filter=all' . ($searchItem ? '&search=' . urlencode($searchItem) : ''); ?>" 
                               class="filter-btn <?php echo $filterStock === 'all' ? 'active' : ''; ?>" 
                               style="padding: 8px 14px; border-radius: 7px; background: <?php echo $filterStock === 'all' ? 'linear-gradient(135deg, #f4d03f 0%, #f9d76a 100%)' : 'rgba(255,255,255,0.06)'; ?>; color: <?php echo $filterStock === 'all' ? '#1a3a5c' : '#e8e8e8'; ?>; text-decoration: none; border: 1px solid <?php echo $filterStock === 'all' ? 'rgba(244,208,63,0.3)' : 'rgba(255,255,255,0.1)'; ?>; cursor: pointer; font-weight: 600; font-size: 11px; transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); white-space: nowrap; box-shadow: <?php echo $filterStock === 'all' ? '0 8px 20px rgba(244,208,63,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 28px rgba(244,208,63,0.3)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='<?php echo $filterStock === 'all' ? '0 8px 20px rgba(244,208,63,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>'">
                                All Items
                            </a>
                            <a href="<?php echo '?filter=critical' . ($searchItem ? '&search=' . urlencode($searchItem) : ''); ?>" 
                               class="filter-btn <?php echo $filterStock === 'critical' ? 'active' : ''; ?>" 
                               style="padding: 8px 14px; border-radius: 7px; background: <?php echo $filterStock === 'critical' ? '#ff6b6b' : 'rgba(255,107,107,0.1)'; ?>; color: #fff; text-decoration: none; border: 1px solid <?php echo $filterStock === 'critical' ? 'rgba(255,107,107,0.4)' : 'rgba(255,107,107,0.2)'; ?>; cursor: pointer; font-weight: 600; font-size: 11px; transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); white-space: nowrap; box-shadow: <?php echo $filterStock === 'critical' ? '0 8px 20px rgba(255,107,107,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 28px rgba(255,107,107,0.3)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='<?php echo $filterStock === 'critical' ? '0 8px 20px rgba(255,107,107,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>'">
                                <i class="fas fa-circle-xmark" style="margin-right: 5px;"></i> Critical
                            </a>
                            <a href="<?php echo '?filter=low' . ($searchItem ? '&search=' . urlencode($searchItem) : ''); ?>" 
                               class="filter-btn <?php echo $filterStock === 'low' ? 'active' : ''; ?>" 
                               style="padding: 8px 14px; border-radius: 7px; background: <?php echo $filterStock === 'low' ? '#ffa500' : 'rgba(255,165,0,0.1)'; ?>; color: #fff; text-decoration: none; border: 1px solid <?php echo $filterStock === 'low' ? 'rgba(255,165,0,0.4)' : 'rgba(255,165,0,0.2)'; ?>; cursor: pointer; font-weight: 600; font-size: 11px; transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); white-space: nowrap; box-shadow: <?php echo $filterStock === 'low' ? '0 8px 20px rgba(255,165,0,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 28px rgba(255,165,0,0.3)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='<?php echo $filterStock === 'low' ? '0 8px 20px rgba(255,165,0,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>'">
                                <i class="fas fa-exclamation-triangle" style="margin-right: 5px;"></i> Low
                            </a>
                            <a href="<?php echo '?filter=adequate' . ($searchItem ? '&search=' . urlencode($searchItem) : ''); ?>" 
                               class="filter-btn <?php echo $filterStock === 'adequate' ? 'active' : ''; ?>" 
                               style="padding: 8px 14px; border-radius: 7px; background: <?php echo $filterStock === 'adequate' ? '#4a90e2' : 'rgba(74,144,226,0.1)'; ?>; color: #fff; text-decoration: none; border: 1px solid <?php echo $filterStock === 'adequate' ? 'rgba(74,144,226,0.4)' : 'rgba(74,144,226,0.2)'; ?>; cursor: pointer; font-weight: 600; font-size: 11px; transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); white-space: nowrap; box-shadow: <?php echo $filterStock === 'adequate' ? '0 8px 20px rgba(74,144,226,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 28px rgba(74,144,226,0.3)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='<?php echo $filterStock === 'adequate' ? '0 8px 20px rgba(74,144,226,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>'">
                                <i class="fas fa-check-circle" style="margin-right: 5px;"></i> Adequate
                            </a>
                            <a href="<?php echo '?filter=high' . ($searchItem ? '&search=' . urlencode($searchItem) : ''); ?>" 
                               class="filter-btn <?php echo $filterStock === 'high' ? 'active' : ''; ?>" 
                               style="padding: 8px 14px; border-radius: 7px; background: <?php echo $filterStock === 'high' ? '#2ecc71' : 'rgba(46,204,113,0.1)'; ?>; color: #fff; text-decoration: none; border: 1px solid <?php echo $filterStock === 'high' ? 'rgba(46,204,113,0.4)' : 'rgba(46,204,113,0.2)'; ?>; cursor: pointer; font-weight: 600; font-size: 11px; transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); white-space: nowrap; box-shadow: <?php echo $filterStock === 'high' ? '0 8px 20px rgba(46,204,113,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 28px rgba(46,204,113,0.3)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='<?php echo $filterStock === 'high' ? '0 8px 20px rgba(46,204,113,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>'">
                                <i class="fas fa-arrow-up" style="margin-right: 5px;"></i> High
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Sort Section -->
                <div style="padding: 12px 20px;">
                    <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                        <span style="color: #17a2b8; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px; white-space: nowrap; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-arrow-down-arrow-up" style="font-size: 12px;"></i> SORT
                        </span>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; flex: 1;">
                            <a href="<?php echo '?sort=newest' . ($searchItem ? '&search=' . urlencode($searchItem) : '') . ($filterStock !== 'all' ? '&filter=' . $filterStock : ''); ?>" 
                               class="filter-btn <?php echo $sortBy === 'newest' ? 'active' : ''; ?>" 
                               style="padding: 8px 14px; border-radius: 7px; background: <?php echo $sortBy === 'newest' ? 'linear-gradient(135deg, #17a2b8 0%, #138496 100%)' : 'rgba(255,255,255,0.06)'; ?>; color: #fff; text-decoration: none; border: 1px solid <?php echo $sortBy === 'newest' ? 'rgba(23,162,184,0.4)' : 'rgba(255,255,255,0.1)'; ?>; cursor: pointer; font-weight: 600; font-size: 11px; transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); white-space: nowrap; box-shadow: <?php echo $sortBy === 'newest' ? '0 8px 20px rgba(23,162,184,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 28px rgba(23,162,184,0.3)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='<?php echo $sortBy === 'newest' ? '0 8px 20px rgba(23,162,184,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>'">
                                <i class="fas fa-clock" style="margin-right: 5px;"></i> Newest
                            </a>
                            <a href="<?php echo '?sort=name' . ($searchItem ? '&search=' . urlencode($searchItem) : '') . ($filterStock !== 'all' ? '&filter=' . $filterStock : ''); ?>" 
                               class="filter-btn <?php echo $sortBy === 'name' ? 'active' : ''; ?>" 
                               style="padding: 8px 14px; border-radius: 7px; background: <?php echo $sortBy === 'name' ? 'linear-gradient(135deg, #17a2b8 0%, #138496 100%)' : 'rgba(255,255,255,0.06)'; ?>; color: #fff; text-decoration: none; border: 1px solid <?php echo $sortBy === 'name' ? 'rgba(23,162,184,0.4)' : 'rgba(255,255,255,0.1)'; ?>; cursor: pointer; font-weight: 600; font-size: 11px; transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); white-space: nowrap; box-shadow: <?php echo $sortBy === 'name' ? '0 8px 20px rgba(23,162,184,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 28px rgba(23,162,184,0.3)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='<?php echo $sortBy === 'name' ? '0 8px 20px rgba(23,162,184,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>'">
                                <i class="fas fa-font" style="margin-right: 5px;"></i> A-Z
                            </a>
                            <a href="<?php echo '?sort=code' . ($searchItem ? '&search=' . urlencode($searchItem) : '') . ($filterStock !== 'all' ? '&filter=' . $filterStock : ''); ?>" 
                               class="filter-btn <?php echo $sortBy === 'code' ? 'active' : ''; ?>" 
                               style="padding: 8px 14px; border-radius: 7px; background: <?php echo $sortBy === 'code' ? 'linear-gradient(135deg, #17a2b8 0%, #138496 100%)' : 'rgba(255,255,255,0.06)'; ?>; color: #fff; text-decoration: none; border: 1px solid <?php echo $sortBy === 'code' ? 'rgba(23,162,184,0.4)' : 'rgba(255,255,255,0.1)'; ?>; cursor: pointer; font-weight: 600; font-size: 11px; transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); white-space: nowrap; box-shadow: <?php echo $sortBy === 'code' ? '0 8px 20px rgba(23,162,184,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 28px rgba(23,162,184,0.3)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='<?php echo $sortBy === 'code' ? '0 8px 20px rgba(23,162,184,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>'">
                                <i class="fas fa-barcode" style="margin-right: 5px;"></i> Box
                            </a>
                            <a href="<?php echo '?sort=stock' . ($searchItem ? '&search=' . urlencode($searchItem) : '') . ($filterStock !== 'all' ? '&filter=' . $filterStock : ''); ?>" 
                               class="filter-btn <?php echo $sortBy === 'stock' ? 'active' : ''; ?>" 
                               style="padding: 8px 14px; border-radius: 7px; background: <?php echo $sortBy === 'stock' ? 'linear-gradient(135deg, #17a2b8 0%, #138496 100%)' : 'rgba(255,255,255,0.06)'; ?>; color: #fff; text-decoration: none; border: 1px solid <?php echo $sortBy === 'stock' ? 'rgba(23,162,184,0.4)' : 'rgba(255,255,255,0.1)'; ?>; cursor: pointer; font-weight: 600; font-size: 11px; transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); white-space: nowrap; box-shadow: <?php echo $sortBy === 'stock' ? '0 8px 20px rgba(23,162,184,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 28px rgba(23,162,184,0.3)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='<?php echo $sortBy === 'stock' ? '0 8px 20px rgba(23,162,184,0.2)' : '0 2px 8px rgba(0,0,0,0.2)'; ?>'">
                                <i class="fas fa-chart-column" style="margin-right: 5px;"></i> Qty
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock Distribution Bar -->
            <div style="background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%); border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1); padding: 20px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <h3 style="font-size: 13px; font-weight: 600; color: #ffffff !important; text-transform: uppercase; margin: 0; letter-spacing: 0.5px;">Stock Level Distribution</h3>
                    <span style="font-size: 11px; color: #a0a0a0; cursor: help;" title="Shows the breakdown of inventory items by stock level">
                        <i class="fas fa-info-circle"></i> 
                    </span>
                </div>
                <div style="background: rgba(244, 208, 63, 0.1); border: 1px solid rgba(244, 208, 63, 0.2); border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; font-size: 12px; color: #e0e0e0; line-height: 1.4;">
                    <i class="fas fa-lightbulb" style="color: #f4d03f; margin-right: 6px;"></i>
                    <strong style="color: #f4d03f;">Stock Levels:</strong> 
                    <span style="color: #ff6b6b;">🔴 Critical (≤5)</span> - 
                    <span style="color: #ffa500;">🟠 Low (5-20)</span> - 
                    <span style="color: #4a90e2;">🔵 Adequate (20-100)</span> - 
                    <span style="color: #2ecc71;">🟢 High (>100)</span>
                </div>
                <div style="display: flex; gap: 8px; height: 30px; border-radius: 6px; overflow: hidden; background: rgba(0,0,0,0.3);">
                    <?php 
                    $allItems = $items;
                    // Reset filters to get all items for distribution
                    $allItemsQ = $conn->query("
                        SELECT 
                            item_code,
                            MAX(item_name) as item_name,
                            COALESCE(SUM(quantity), 0) as current_stock
                        FROM delivery_records
                        WHERE company_name = 'Stock Addition'
                        GROUP BY item_code
                    ");
                    
                    $critical_all = 0;
                    $low_all = 0;
                    $adequate_all = 0;
                    $high_all = 0;
                    
                    while ($r = $allItemsQ->fetch_assoc()) {
                        $s = intval($r['current_stock']);
                        if ($s <= 5) $critical_all++;
                        elseif ($s <= 20) $low_all++;
                        elseif ($s <= 100) $adequate_all++;
                        else $high_all++;
                    }
                    
                    $total_all = $critical_all + $low_all + $adequate_all + $high_all;
                    $pct_critical = $total_all > 0 ? ($critical_all / $total_all) * 100 : 0;
                    $pct_low = $total_all > 0 ? ($low_all / $total_all) * 100 : 0;
                    $pct_adequate = $total_all > 0 ? ($adequate_all / $total_all) * 100 : 0;
                    $pct_high = $total_all > 0 ? ($high_all / $total_all) * 100 : 0;
                    ?>
                    <div style="background: #ff6b6b; width: <?php echo $pct_critical; ?>%; transition: width 0.3s ease;" title="Critical: <?php echo $critical_all; ?> items"></div>
                    <div style="background: #ffa500; width: <?php echo $pct_low; ?>%; transition: width 0.3s ease;" title="Low: <?php echo $low_all; ?> items"></div>
                    <div style="background: #4a90e2; width: <?php echo $pct_adequate; ?>%; transition: width 0.3s ease;" title="Adequate: <?php echo $adequate_all; ?> items"></div>
                    <div style="background: #2ecc71; width: <?php echo $pct_high; ?>%; transition: width 0.3s ease;" title="High: <?php echo $high_all; ?> items"></div>
                </div>
                <div style="display: flex; gap: 15px; margin-top: 10px; flex-wrap: wrap; font-size: 12px; color: #ffffff !important;">
                    <span style="color: #ffffff !important; cursor: help;" title="Items with 5 or fewer units in stock - Urgent reorder needed"><span style="display: inline-block; width: 12px; height: 12px; background: #ff6b6b; border-radius: 2px;"></span> Critical: <?php echo $critical_all; ?></span>
                    <span style="color: #ffffff !important; cursor: help;" title="Items with 5-20 units - Consider reordering soon"><span style="display: inline-block; width: 12px; height: 12px; background: #ffa500; border-radius: 2px;"></span> Low: <?php echo $low_all; ?></span>
                    <span style="color: #ffffff !important; cursor: help;" title="Items with 20-100 units - Normal stock level"><span style="display: inline-block; width: 12px; height: 12px; background: #4a90e2; border-radius: 2px;"></span> Adequate: <?php echo $adequate_all; ?></span>
                    <span style="color: #ffffff !important; cursor: help;" title="Items with more than 100 units - Plenty in stock"><span style="display: inline-block; width: 12px; height: 12px; background: #2ecc71; border-radius: 2px;"></span> High: <?php echo $high_all; ?></span>
                </div>
            </div>

            <!-- Inventory Table -->
            <h2 class="section-title" style="display: flex; align-items: center; justify-content: space-between;">
                <span>
                    <i class="fas fa-table"></i>
                    Item Inventory Details
                </span>
                <span style="font-size: 14px; font-weight: 400; color: #000000; text-transform: none;">
                    Showing <?php echo count($items); ?> of <span id="totalCount"><?php 
                        // Get total count - count ALL rows (not just distinct codes)
                        $totalCountResult = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'");
                        $totalCountRow = $totalCountResult->fetch_assoc();
                        echo $totalCountRow['cnt'];
                    ?></span> items
                </span>
            </h2>

            <div class="inventory-table-container">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">#</th>
                            <th style="cursor: pointer; user-select: none; position: relative;" onclick="sortTable('code')">
                                BOX 
                                <?php if ($sortBy === 'code'): ?>
                                    <i class="fas fa-arrow-<?php echo $sortOrder === 'asc' ? 'down' : 'up'; ?>" style="margin-left: 5px; font-size: 11px; opacity: 0.7;"></i>
                                <?php endif; ?>
                            </th>
                            <th style="cursor: pointer; user-select: none; position: relative;" onclick="sortTable('code')">
                                ITEMS
                                <?php if ($sortBy === 'code'): ?>
                                    <i class="fas fa-arrow-<?php echo $sortOrder === 'asc' ? 'down' : 'up'; ?>" style="margin-left: 5px; font-size: 11px; opacity: 0.7;"></i>
                                <?php endif; ?>
                            </th>
                            <th style="cursor: pointer; user-select: none;" onclick="sortTable('name')">
                                DESCRIPTION
                                <?php if ($sortBy === 'name'): ?>
                                    <i class="fas fa-arrow-<?php echo $sortOrder === 'asc' ? 'down' : 'up'; ?>" style="margin-left: 5px; font-size: 11px; opacity: 0.7;"></i>
                                <?php endif; ?>
                            </th>
                            <th>GROUP</th>
                            <th>UOM</th>
                            <th style="cursor: pointer; user-select: none; color: #ff4444;" onclick="sortTable('stock')">
                                INVENTORY
                                <?php if ($sortBy === 'stock'): ?>
                                    <i class="fas fa-arrow-<?php echo $sortOrder === 'asc' ? 'down' : 'up'; ?>" style="margin-left: 5px; font-size: 11px; opacity: 0.7;"></i>
                                <?php endif; ?>
                            </th>
                            <th>STATUS</th>
                            <th style="text-align: center;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="inventory-tbody">
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #a0a0a0;">
                                    <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                    <p style="font-size: 14px;">No items found matching your criteria</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($items as $index => $item): 
                            $stock = $item['stock'];
                            $boxDisplay = $item['box'] ?: 'N/A';
                            $itemsDisplay = $item['model'] ?: $item['code'];
                            $rowNum = $index + 1;
                            $fileTooltip = $item['source_file'] ? "title='From: {$item['source_file']}'" : '';
                            $isHidden = $index >= 50 ? 'display: none;' : '';
                            
                            // Check if item was added in last 30 minutes
                            $isNewItem = false;
                            if (!empty($item['last_updated'])) {
                                try {
                                    $updatedTime = strtotime($item['last_updated']);
                                    $currentTime = time();
                                    if ($updatedTime !== false) {
                                        $timeDiff = ($currentTime - $updatedTime) / 60; // difference in minutes
                                        $isNewItem = ($timeDiff >= 0 && $timeDiff <= 30);
                                    }
                                } catch (Exception $e) {
                                    $isNewItem = false;
                                }
                            }
                            $isPurchaseHighlight = (bool) ($item['is_highlighted'] ?? false);
                            $newItemClass = ($isNewItem || $isPurchaseHighlight) ? 'new-item-highlight' : '';
                            $purchaseHighlightClass = $isPurchaseHighlight ? 'purchase-order-highlight' : '';
                            
                            // Determine status badge
                            if ($stock <= 5) {
                                $statusBadge = '<span style="background: #ff6b6b; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block;">Critical</span>';
                                $statusClass = 'stock-low';
                            } elseif ($stock > 5 && $stock <= 20) {
                                $statusBadge = '<span style="background: #ffa500; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block;">Low</span>';
                                $statusClass = 'stock-low';
                            } elseif ($stock > 20 && $stock <= 100) {
                                $statusBadge = '<span style="background: #4a90e2; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block;">Adequate</span>';
                                $statusClass = 'stock-good';
                            } else {
                                $statusBadge = '<span style="background: #2ecc71; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block;">High</span>';
                                $statusClass = 'stock-good';
                            }
                        ?>
                        <tr class="inventory-row <?php echo trim($newItemClass . ' ' . $purchaseHighlightClass); ?>" data-row-index="<?php echo $index; ?>" data-added-time="<?php echo htmlspecialchars((string) ($item['last_updated'] ?? '')); ?>" style="<?php echo $isHidden; ?>">
                            <td style="text-align: center; font-weight: bold; color: #f4d03f;"><?php echo $rowNum; ?></td>
                            <td><span class="item-code" style="background: rgba(244, 208, 63, 0.1); color: #f4d03f;"><?php echo htmlspecialchars($boxDisplay); ?></span></td>
                            <td <?php echo $fileTooltip; ?> style="<?php echo $item['source_file'] ? 'cursor: help; text-decoration: underline dotted; text-decoration-color: #888;' : ''; ?>"><?php echo htmlspecialchars($itemsDisplay); ?></td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td style="text-align: center; font-weight: 500;">
                                <span style="color: #4a90e2; font-size: 13px;">
                                    <?php 
                                        if (strpos($item['grouping'], 'Multi') !== false) {
                                            echo '🔵 ' . htmlspecialchars($item['grouping']);
                                        } else {
                                            echo '🟡 ' . htmlspecialchars($item['grouping']);
                                        }
                                    ?>
                                </span>
                            </td>
                            <td style="text-align: center;">UNITS</td>
                            <td style="text-align: center;">
                                <span class="stock-quantity <?php echo $statusClass; ?>" style="font-size: 16px;"><?php echo number_format($item['stock']); ?></span>
                            </td>
                            <td style="text-align: center;">
                                <?php echo $statusBadge; ?>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <button class="action-btn action-view action-btn-horizontal" title="View" onclick="viewItemDetails('<?php echo htmlspecialchars($item['code']); ?>', '<?php echo htmlspecialchars($item['name']); ?>', <?php echo $item['stock']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="action-btn action-add action-btn-horizontal" title="Edit Stock" onclick="openEditStockModal('<?php echo htmlspecialchars($item['code']); ?>', '<?php echo htmlspecialchars($item['name']); ?>', <?php echo $item['stock']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn action-delete action-btn-horizontal" title="Delete" onclick="confirmDeleteItem('<?php echo htmlspecialchars($item['code']); ?>', '<?php echo htmlspecialchars($item['name']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- See More Button -->
                <?php if (count($items) > 50): ?>
                <div style="display: flex; justify-content: center; margin-top: 30px; margin-bottom: 20px;">
                    <button id="seeMoreBtn" onclick="loadMoreItems()" style="
                        background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
                        color: #fff;
                        border: none;
                        padding: 14px 40px;
                        border-radius: 10px;
                        font-size: 14px;
                        font-weight: 700;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        box-shadow: 0 10px 30px rgba(74, 144, 226, 0.3);
                        letter-spacing: 0.5px;
                        text-transform: uppercase;
                    " onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 15px 40px rgba(74, 144, 226, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 10px 30px rgba(74, 144, 226, 0.3)'">
                        📋 See More Items (<?php echo count($items) - 50; ?> more)
                    </button>
                </div>
                <?php endif; ?>
            </div>

            </div><!-- End of Inventory Tab -->

            <!-- Orders Tab Content -->
            <div id="orders-tab" class="tab-content">
                <div style="margin-bottom: 20px;">
                    <button class="add-stock-btn" style="background: linear-gradient(135deg, #f4d03f 0%, #f9d76a 100%);" onclick="document.getElementById('toggleCreateBtn').click()">
                        <i class="fas fa-plus"></i> Create Purchase Order
                    </button>
                </div>

                <!-- Order Filters -->
                <div class="filters">
                    <a class="filter-chip <?php echo (!isset($_GET['filter']) || $_GET['filter'] === 'all') ? 'active' : ''; ?>" href="#" onclick="applyOrderFilter(event, 'all'); return false;" data-filter="all">All</a>
                    <a class="filter-chip <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'with_po') ? 'active' : ''; ?>" href="#" onclick="applyOrderFilter(event, 'with_po'); return false;" data-filter="with_po">With PO</a>
                    <a class="filter-chip <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'no_po') ? 'active' : ''; ?>" href="#" onclick="applyOrderFilter(event, 'no_po'); return false;" data-filter="no_po">No PO</a>
                    <a class="filter-chip <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'delivered') ? 'active' : ''; ?>" href="#" onclick="applyOrderFilter(event, 'delivered'); return false;" data-filter="delivered">Delivered</a>
                </div>

                <!-- Orders Table -->
                <div class="inventory-table-container">
                    <table class="inventory-table" style="min-width: 980px;">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th>Reference No.</th>
                                <th>PO Status</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Get orders for the Orders tab
                            $orderFilter = isset($_GET['filter']) && in_array($_GET['filter'], ['all', 'with_po', 'no_po', 'delivered']) ? $_GET['filter'] : 'all';
                            $orderWhere = "company_name = 'Orders'";
                            
                            if ($orderFilter === 'with_po') {
                                $orderWhere .= " AND ((po_number IS NOT NULL AND po_number != '') OR po_status IN ('Pending', 'Received'))";
                            } elseif ($orderFilter === 'no_po') {
                                $orderWhere .= " AND ((po_number IS NULL OR po_number = '') AND (po_status IS NULL OR po_status = '' OR po_status = 'No PO'))";
                            } elseif ($orderFilter === 'delivered') {
                                $orderWhere .= " AND status = 'Delivered'";
                            }
                            
                            $linkedInvoices = [];
                            $linkedOrderRefs = [];
                            $linkedItemCodes = [];
                            $linkedPoNumbers = [];
                            $linkedResult = $conn->query("SELECT invoice_no, notes, item_code, po_number FROM delivery_records WHERE company_name != 'Orders'");
                            if ($linkedResult) {
                                while ($linkedRow = $linkedResult->fetch_assoc()) {
                                    $linkedInvoice = trim((string) ($linkedRow['invoice_no'] ?? ''));
                                    if ($linkedInvoice !== '') {
                                        $linkedInvoices[$linkedInvoice] = true;
                                    }
                                    $linkedNotes = (string) ($linkedRow['notes'] ?? '');
                                    if ($linkedNotes !== '' && preg_match_all('/SO-\d+/i', $linkedNotes, $matches)) {
                                        foreach ($matches[0] as $ref) {
                                            $linkedOrderRefs[strtoupper($ref)] = true;
                                        }
                                    }
                                    // Also track item_code and po_number combinations
                                    $itemCode = trim((string) ($linkedRow['item_code'] ?? ''));
                                    $poNumber = trim((string) ($linkedRow['po_number'] ?? ''));
                                    if ($itemCode !== '') {
                                        $linkedItemCodes[$itemCode] = true;
                                    }
                                    if ($poNumber !== '') {
                                        $linkedPoNumbers[$poNumber] = true;
                                    }
                                }
                            }
                            
                            $ordersListSql = "SELECT id, order_customer, order_date, item_code, item_name, quantity, unit_price, total_amount, invoice_no, po_number, po_status, status, created_at
                                            FROM delivery_records
                                            WHERE $orderWhere
                                            ORDER BY id DESC";
                            $ordersListResult = $conn->query($ordersListSql);
                            $tabOrders = [];
                            if ($ordersListResult) {
                                while ($orderRow = $ordersListResult->fetch_assoc()) {
                                    $orderInvoice = trim((string) ($orderRow['invoice_no'] ?? ''));
                                    $orderRef = 'SO-' . str_pad((string) $orderRow['id'], 3, '0', STR_PAD_LEFT);
                                    $orderItemCode = trim((string) ($orderRow['item_code'] ?? ''));
                                    $orderPoNumber = trim((string) ($orderRow['po_number'] ?? ''));
                                    
                                    // Skip if: invoice matched, OR order ref matched, OR item_code in inventory, OR po_number in inventory
                                    if (($orderInvoice !== '' && isset($linkedInvoices[$orderInvoice])) 
                                        || isset($linkedOrderRefs[$orderRef])
                                        || (isset($linkedItemCodes[$orderItemCode]) && $orderItemCode !== '')
                                        || (isset($linkedPoNumbers[$orderPoNumber]) && $orderPoNumber !== '')) {
                                        continue;
                                    }
                                    
                                    $tabOrders[] = $orderRow;
                                }
                            }
                            
                            if (empty($tabOrders)):
                            ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #a0a0a0;">
                                    <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                    <p style="font-size: 14px;">No orders found</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($tabOrders as $order): 
                                    $poClass = 'no-po';
                                    if (($order['po_status'] ?? '') === 'Pending') {
                                        $poClass = 'pending';
                                    } elseif (($order['po_status'] ?? '') === 'Received') {
                                        $poClass = 'received';
                                    }
                                    $soId = 'SO-' . str_pad((string) $order['id'], 3, '0', STR_PAD_LEFT);
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($soId); ?></td>
                                    <td><?php echo htmlspecialchars($order['item_code']); ?></td>
                                    <td><?php echo htmlspecialchars($order['item_name']); ?></td>
                                    <td style="text-align: center;"><?php echo htmlspecialchars($order['quantity']); ?></td>
                                    <td>PHP <?php echo number_format(floatval($order['total_amount'] ?? 0), 2); ?></td>
                                    <td>
                                        <?php $referenceNo = trim((string) ($order['invoice_no'] ?? '')); ?>
                                        <?php if ($referenceNo !== ''): ?>
                                            <?php echo htmlspecialchars($referenceNo); ?>
                                        <?php else: ?>
                                            <span style="color: #ffcc80; font-weight: 600;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span style="display: inline-flex; align-items: center; gap: 6px; border-radius: 6px; font-size: 12px; padding: 6px 12px; font-weight: 600; <?php 
                                            if ($poClass === 'pending') {
                                                echo 'background: #f39c12; color: #fff;';
                                            } elseif ($poClass === 'received') {
                                                echo 'background: #27ae60; color: #fff;';
                                            } else {
                                                echo 'background: #e74c3c; color: #fff;';
                                            }
                                        ?>">
                                            <?php echo htmlspecialchars($order['po_status'] ?: 'No PO'); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <div style="display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                                            <a style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; color: #60a8ff; background: rgba(96, 168, 255, 0.14); transition: all 0.2s ease; cursor: pointer;" href="order-details.php?id=<?php echo intval($order['id']); ?>" onclick="event.stopPropagation();"><i class="fas fa-eye"></i> View</a>
                                            <a style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; color: #f3be4d; background: rgba(243, 190, 77, 0.14); transition: all 0.2s ease; cursor: pointer;" href="order-details.php?id=<?php echo intval($order['id']); ?>" onclick="event.stopPropagation();"><i class="fas fa-pen"></i> Edit</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- Create Order Modal (from orders.php) -->
    <div class="create-order-modal" id="createOrderModal" aria-hidden="true">
        <div class="form-card create-order-dialog" role="dialog" aria-modal="true" aria-labelledby="createOrderTitle">
            <div class="modal-header">
                <h2 class="modal-title" id="createOrderTitle"><i class="fas fa-plus-circle"></i> Create New Purchase Order</h2>
                <button type="button" class="close-btn" id="closeCreateBtn" aria-label="Close create order">&times;</button>
            </div>

            <form method="post" action="orders.php" id="createOrderForm">
                <input type="hidden" name="action" value="create_order">
                <input type="hidden" name="customer" value="Andison Internal Order">
                
                <!-- Order Level Fields -->
                <div class="form-grid" style="grid-template-columns: repeat(2, 1fr); margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="order_date">Order Date</label>
                        <input id="order_date" name="order_date" type="date" required>
                    </div>
                    <div class="form-group">
                        <label for="po_status">PO Status</label>
                        <select id="po_status" name="po_status">
                            <option>No PO</option>
                            <option>Pending</option>
                            <option>Received</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="po_number">PO Number</label>
                        <input id="po_number" name="po_number" type="text" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" placeholder="Optional notes" style="resize: vertical; min-height: 60px;"></textarea>
                    </div>
                </div>

                <!-- Products Section -->
                <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <label style="font-weight: 700; color: #f4d03f; font-size: 14px;">Products</label>
                        <button type="button" class="add-product-btn" onclick="addProductRow()" style="background: rgba(244,208,63,0.2); border: 1px solid rgba(244,208,63,0.4); color: #f4d03f; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                    </div>
                    
                    <div id="products-container" style="display: flex; flex-direction: column; gap: 15px;">
                        <!-- Product rows will be added here -->
                    </div>
                </div>

                <div class="form-actions">
                    <button class="action-btn save-order-btn" type="submit" id="saveOrderBtn">
                        <span class="btn-loader" aria-hidden="true"></span>
                        <i class="fas fa-save btn-icon" aria-hidden="true"></i>
                        <span class="btn-text">Save Order</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add New Item Modal -->
    <div id="addItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-box-plus"></i> Add New Item</h2>
                <button class="close-btn" onclick="closeAddItemModal()">&times;</button>
            </div>
            <div id="itemModalAlert" class="alert"></div>
            <form id="addItemForm" onsubmit="submitAddItem(event)">
                <div class="form-group">
                    <label for="boxCode">Box</label>
                    <input 
                        type="text" 
                        id="boxCode" 
                        name="box_code" 
                        placeholder="E.g., BOX-001, KB-001" 
                        required
                    >
                </div>
                <div class="form-group">
                    <label for="items">Items</label>
                    <input 
                        type="text" 
                        id="items" 
                        name="items" 
                        placeholder="E.g., BW-001, 2 Year Carbon Monoxide Detector" 
                        required
                    >
                </div>
                <div class="form-group">
                    <label for="itemDescription">Description</label>
                    <textarea 
                        id="itemDescription" 
                        name="item_description" 
                        placeholder="Enter item description..." 
                        style="resize: vertical; min-height: 80px;"
                    ></textarea>
                </div>
                <div class="form-group">
                    <label for="oum">OUM (Unit of Measure)</label>
                    <input 
                        type="text" 
                        id="oum" 
                        name="oum" 
                        placeholder="E.g., PCS, UNIT, BOX" 
                        required
                    >
                </div>
                <div class="form-group">
                    <label for="inventory">Inventory</label>
                    <input 
                        type="number" 
                        id="inventory" 
                        name="inventory_qty" 
                        placeholder="Enter inventory quantity" 
                        min="0"
                    >
                    <small style="color: #8a9ab5; margin-top: 5px; display: block;">Optional: Set the inventory quantity now</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-check"></i> Create Item
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeAddItemModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Order Info Modal -->
    <div id="addOrderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-shopping-cart"></i> Add Order Info</h2>
                <button class="close-btn" onclick="closeAddOrderModal()">&times;</button>
            </div>
            <div id="orderModalAlert" class="alert"></div>
            <form id="addOrderForm" onsubmit="submitAddOrder(event)">
                <div class="form-group">
                    <label for="orderItem">Item</label>
                    <input 
                        type="text" 
                        id="orderItem" 
                        name="item_code" 
                        placeholder="Type item code or select from list..." 
                        list="itemList"
                        required
                        style="width: 100%; padding: 14px 16px; border-radius: 10px; border: 1px solid rgba(244, 208, 63, 0.25); background: linear-gradient(135deg, rgba(30, 42, 56, 0.6) 0%, rgba(20, 30, 45, 0.8) 100%); color: #ffffff; font-family: 'Poppins', sans-serif; font-size: 14px; box-sizing: border-box; transition: all 0.3s ease;"
                    >
                    <datalist id="itemList">
                        <?php 
                        $itemsQuery = $conn->query("
                            SELECT DISTINCT item_code, item_name
                            FROM delivery_records
                            WHERE company_name = 'Stock Addition' AND (box_code IS NULL OR box_code = '')
                            ORDER BY item_code ASC
                        ");
                        if ($itemsQuery) {
                            while ($itemRow = $itemsQuery->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($itemRow['item_code']) . '" label="' . htmlspecialchars($itemRow['item_name']) . '"></option>';
                            }
                        }
                        ?>
                    </datalist>
                </div>

                <div class="form-group">
                    <label for="orderQuantity">Quantity</label>
                    <input 
                        type="number" 
                        id="orderQuantity" 
                        name="quantity" 
                        placeholder="Enter quantity" 
                        min="1"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="orderStatus">Status</label>
                    <select 
                        id="orderStatus" 
                        name="status" 
                        required
                        style="width: 100%; padding: 14px 16px; border-radius: 10px; border: 1px solid rgba(244, 208, 63, 0.25); background: linear-gradient(135deg, rgba(30, 42, 56, 0.6) 0%, rgba(20, 30, 45, 0.8) 100%); color: #ffffff; font-family: 'Poppins', sans-serif; font-size: 14px; cursor: pointer; box-sizing: border-box; transition: all 0.3s ease;"
                    >
                        <option value="">-- Select Status --</option>
                        <option value="Pending">Pending</option>
                        <option value="Processing">Processing</option>
                        <option value="Shipped">Shipped</option>
                        <option value="Received">Received</option>
                        <option value="Delivered">Delivered</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="orderDate">Order Date</label>
                    <input 
                        type="date" 
                        id="orderDate" 
                        name="order_date"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="expectedDelivery">Expected Delivery Date</label>
                    <input 
                        type="date" 
                        id="expectedDelivery" 
                        name="expected_delivery"
                    >
                </div>

                <div class="form-group">
                    <label for="orderNotes">Notes</label>
                    <textarea 
                        id="orderNotes" 
                        name="notes" 
                        placeholder="Add any additional notes..."
                        rows="3"
                    ></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-check"></i> Add Order
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeAddOrderModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Order Modal -->
    <div id="editOrderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-pencil-alt"></i> Edit Order</h2>
                <button class="close-btn" onclick="closeEditOrderModal()">&times;</button>
            </div>
            <div id="editOrderModalAlert" class="alert"></div>
            <form id="editOrderForm" onsubmit="submitEditOrder(event)">
                <input type="hidden" id="editOrderId" name="order_id">
                
                <div class="form-group">
                    <label>Item Code</label>
                    <input type="text" id="editOrderItemCode" disabled style="background: rgba(255,255,255,0.05);">
                </div>

                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" id="editOrderItemName" disabled style="background: rgba(255,255,255,0.05);">
                </div>

                <div class="form-group">
                    <label>Quantity</label>
                    <input type="text" id="editOrderQuantity" disabled style="background: rgba(255,255,255,0.05);">
                </div>

                <div class="form-group">
                    <label for="editOrderStatus">Status</label>
                    <select 
                        id="editOrderStatus" 
                        name="status" 
                        required
                        style="width: 100%; padding: 14px 16px; border-radius: 10px; border: 1px solid rgba(244, 208, 63, 0.25); background: linear-gradient(135deg, rgba(30, 42, 56, 0.6) 0%, rgba(20, 30, 45, 0.8) 100%); color: #ffffff; font-family: 'Poppins', sans-serif; font-size: 14px; cursor: pointer; box-sizing: border-box; transition: all 0.3s ease;"
                    >
                        <option value="Pending">Pending</option>
                        <option value="Processing">Processing</option>
                        <option value="Shipped">Shipped</option>
                        <option value="Received">Received</option>
                        <option value="Delivered">Delivered</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div style="background: linear-gradient(135deg, rgba(100, 150, 255, 0.15) 0%, rgba(100, 150, 255, 0.08) 100%); border: 1px solid rgba(100, 150, 255, 0.2); border-radius: 8px; padding: 12px; margin-bottom: 20px; font-size: 12px; color: #a0a0a0;">
                    <i class="fas fa-info-circle" style="color: #4a90e2; margin-right: 8px;"></i>
                    <strong style="color: #4a90e2;">Tip:</strong> When you mark an order as <strong>Received</strong>, it will automatically be added to your Inventory!
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-check"></i> Update Order
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeEditOrderModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/app.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Hamburger menu toggle
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const sidebar = document.getElementById('sidebar');
            
            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }

            // Check if we need to open Orders tab from URL query parameter
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam === 'orders') {
                const ordersTabBtn = document.querySelector('button[onclick="openTab(event, \'orders-tab\')"]');
                if (ordersTabBtn) {
                    ordersTabBtn.click();
                }
            }
        });

        // Tab Navigation Functions
        function openTab(evt, tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show the selected tab and mark button as active
            document.getElementById(tabName).classList.add('active');
            evt.currentTarget.classList.add('active');
            
            // Show/hide toggleCreateBtn based on active tab
            const toggleCreateBtn = document.getElementById('toggleCreateBtn');
            if (toggleCreateBtn) {
                if (tabName === 'orders-tab') {
                    toggleCreateBtn.style.display = 'inline-flex';
                } else {
                    toggleCreateBtn.style.display = 'none';
                }
            }
        }

        // Add New Item Modal Functions
        function openAddItemModal() {
            document.getElementById('addItemModal').style.display = 'block';
            document.getElementById('addItemForm').reset();
            document.getElementById('itemModalAlert').style.display = 'none';
        }

        function closeAddItemModal() {
            document.getElementById('addItemModal').style.display = 'none';
            document.getElementById('addItemForm').reset();
            document.getElementById('itemModalAlert').style.display = 'none';
        }

        // Add Order Modal Functions
        function openAddOrderModal() {
            document.getElementById('addOrderModal').style.display = 'block';
            document.getElementById('addOrderForm').reset();
            document.getElementById('orderModalAlert').style.display = 'none';
            // Set today's date as default order date
            document.getElementById('orderDate').valueAsDate = new Date();
        }

        function closeAddOrderModal() {
            document.getElementById('addOrderModal').style.display = 'none';
            document.getElementById('addOrderForm').reset();
            document.getElementById('orderModalAlert').style.display = 'none';
        }

        // Submit add order form
        function submitAddOrder(event) {
            event.preventDefault();
            
            const formData = new FormData(document.getElementById('addOrderForm'));
            const alertDiv = document.getElementById('orderModalAlert');
            const submitBtn = document.querySelector('#addOrderForm .btn-submit');
            const originalBtnHTML = submitBtn.innerHTML;

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Order...';
            alertDiv.style.display = 'none';

            fetch('api/add-order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertDiv.className = 'alert alert-success';
                    alertDiv.innerHTML = `<i class="fas fa-check-circle"></i> Order added successfully! Refreshing...`;
                    alertDiv.style.display = 'block';
                    
                    // Refresh page after 1.5 seconds
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    alertDiv.className = 'alert alert-error';
                    alertDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.error || 'Error adding order'}`;
                    alertDiv.style.display = 'block';
                    
                    // Reset button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHTML;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alertDiv.className = 'alert alert-error';
                alertDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.`;
                alertDiv.style.display = 'block';
                
                // Reset button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHTML;
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const addItemModal = document.getElementById('addItemModal');
            const addOrderModal = document.getElementById('addOrderModal');
            const editOrderModal = document.getElementById('editOrderModal');
            
            if (event.target === addItemModal) {
                closeAddItemModal();
            }
            if (event.target === addOrderModal) {
                closeAddOrderModal();
            }
            if (event.target === editOrderModal) {
                closeEditOrderModal();
            }
        }

        // Submit add item form
        function submitAddItem(event) {
            event.preventDefault();
            
            const formData = new FormData(document.getElementById('addItemForm'));
            const alertDiv = document.getElementById('itemModalAlert');
            const submitBtn = document.querySelector('#addItemForm .btn-submit');
            const originalBtnHTML = submitBtn.innerHTML;

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Item...';
            alertDiv.style.display = 'none';

            fetch('api/add-item.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertDiv.className = 'alert alert-success';
                    alertDiv.innerHTML = `<i class="fas fa-check-circle"></i> Item created successfully! Adding to table...`;
                    alertDiv.style.display = 'block';
                    
                    // Add new item to the top of the table
                    const tbody = document.getElementById('inventory-tbody');
                    
                    // Create new row
                    const newRow = document.createElement('tr');
                    newRow.className = 'inventory-row new-item-highlight';
                    newRow.style.animation = 'slideIn 0.4s ease-out';
                    
                    let statusBadge = '';
                    let qty = data.inventory_qty || 0;
                    if (qty <= 5) {
                        statusBadge = '<span style="background: #ff6b6b; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block;">Critical</span>';
                    } else if (qty > 5 && qty <= 20) {
                        statusBadge = '<span style="background: #ffa500; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block;">Low</span>';
                    } else if (qty > 20 && qty <= 100) {
                        statusBadge = '<span style="background: #4a90e2; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block;">Adequate</span>';
                    } else {
                        statusBadge = '<span style="background: #2ecc71; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block;">High</span>';
                    }
                    
                    newRow.innerHTML = `
                        <td style="text-align: center; font-weight: bold; color: #f4d03f;">1</td>
                        <td><span class="item-code" style="background: rgba(244, 208, 63, 0.1); color: #f4d03f;">${data.box_code}</span></td>
                        <td>${data.items}</td>
                        <td>${data.item_description || '-'}</td>
                        <td style="text-align: center; font-weight: 500;"></td>
                        <td>${data.oum}</td>
                        <td style="color: ${qty > 20 ? '#2ecc71' : qty > 5 ? '#ffa500' : '#ff6b6b'}; font-weight: 600;">${data.inventory_qty}</td>
                        <td>${statusBadge}</td>
                        <td style="text-align: center;">
                            <button onclick="deleteInventoryItem('${data.box_code}')" title="Delete" style="color: #ff6b6b; background: rgba(255, 107, 107, 0.1); border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600;">DELETE</button>
                        </td>
                    `;
                    
                    // Remove empty message if present
                    const emptyMsg = tbody.querySelector('tr td[colspan="8"]');
                    if (emptyMsg) {
                        emptyMsg.parentElement.remove();
                    }
                    
                    // Insert at top
                    tbody.insertBefore(newRow, tbody.firstChild);
                    
                    // Re-number rows
                    const rows = tbody.querySelectorAll('tr');
                    rows.forEach((row, index) => {
                        const firstTd = row.querySelector('td:first-child');
                        if (firstTd) {
                            firstTd.textContent = index + 1;
                        }
                    });
                    
                    // Update total count
                    const totalCount = document.getElementById('totalCount');
                    if (totalCount) {
                        totalCount.textContent = parseInt(totalCount.textContent) + 1;
                    }
                    
                    // Close modal after 1 second
                    setTimeout(() => {
                        closeAddItemModal();
                        alertDiv.style.display = 'none';
                    }, 1000);
                } else {
                    alertDiv.className = 'alert alert-error';
                    alertDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.error || 'Error creating item'}`;
                    alertDiv.style.display = 'block';
                    
                    // Reset button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHTML;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alertDiv.className = 'alert alert-error';
                alertDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error creating item`;
                alertDiv.style.display = 'block';
                
                // Reset button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHTML;
            });
        }

        // Inventory Upload Functions
        function resetInventoryUpload() {
            document.getElementById('inventoryFileInput').value = '';
            document.getElementById('inventoryUploadStatus').innerHTML = '';
        }

        function clearAllInventory() {
            if (!confirm('⚠️ WARNING!\n\nThis will DELETE ALL inventory data.\n\nThis action CANNOT be undone.\n\nAre you sure you want to continue?')) {
                return;
            }

            if (!confirm('Please confirm again that you want to permanently delete all inventory data.')) {
                return;
            }

            const statusDiv = document.getElementById('inventoryUploadStatus');
            statusDiv.innerHTML = `
                <div style="background: rgba(244, 208, 63, 0.15); border: 1px solid #f4d03f; border-radius: 8px; padding: 12px; color: #f4d03f; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Clearing inventory...</span>
                </div>
            `;

            fetch('api/clear-inventory.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'clear_all' })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    statusDiv.innerHTML = `
                        <div style="background: rgba(46, 204, 113, 0.15); border: 1px solid #2ecc71; border-radius: 8px; padding: 12px; color: #2ecc71; font-size: 13px;">
                            <i class="fas fa-check-circle"></i> 
                            <strong>${result.message}</strong>
                        </div>
                    `;
                    
                    // Refresh page after 2 seconds
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    statusDiv.innerHTML = `
                        <div style="background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; border-radius: 8px; padding: 12px; color: #ff6b6b; font-size: 13px;">
                            <i class="fas fa-exclamation-circle"></i> 
                            <strong>Error:</strong> ${result.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                statusDiv.innerHTML = `
                    <div style="background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; border-radius: 8px; padding: 12px; color: #ff6b6b; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Error: ${error.message}</span>
                    </div>
                `;
            });
        }

        // Sorting function
        function sortTable(sortBy) {
            const currentSort = new URLSearchParams(window.location.search).get('sort') || 'newest';
            const currentOrder = new URLSearchParams(window.location.search).get('order') || 'desc';
            const search = new URLSearchParams(window.location.search).get('search') || '';
            const filter = new URLSearchParams(window.location.search).get('filter') || 'all';
            
            let newOrder = 'asc';
            // Newest should always be desc
            if (sortBy === 'newest') {
                newOrder = 'desc';
            } else if (currentSort === sortBy && currentOrder === 'asc') {
                newOrder = 'desc';
            }
            
            let url = `inventory.php?sort=${sortBy}&order=${newOrder}`;
            if (search) url += `&search=${encodeURIComponent(search)}`;
            if (filter !== 'all') url += `&filter=${filter}`;
            
            window.location.href = url;
        }

        // Manage highlight duration for new items (30 minutes)
        function manageNewItemHighlights() {
            const rows = document.querySelectorAll('.new-item-highlight');
            
            rows.forEach(row => {
                const addedTimeStr = row.getAttribute('data-added-time');
                if (!addedTimeStr) return;
                
                const addedTime = new Date(addedTimeStr).getTime();
                const currentTime = new Date().getTime();
                const timeDiffMs = currentTime - addedTime;
                const timeDiffMins = timeDiffMs / (1000 * 60);
                
                // Remove highlight after 30 minutes
                if (timeDiffMins >= 30) {
                    row.classList.remove('new-item-highlight');
                } else {
                    // Schedule removal for when 30 minutes have passed
                    const remainingMs = (30 - timeDiffMins) * 60 * 1000;
                    setTimeout(() => {
                        row.classList.remove('new-item-highlight');
                    }, remainingMs);
                }
            });
        }

        // Initialize highlights on page load
        document.addEventListener('DOMContentLoaded', function() {
            manageNewItemHighlights();
        });

        // Re-check highlights every minute
        setInterval(manageNewItemHighlights, 60000);

        // Load More Items
        function loadMoreItems() {
            const rows = document.querySelectorAll('.inventory-row');
            const seeMoreBtn = document.getElementById('seeMoreBtn');
            let hiddenCount = 0;
            let showCount = 0;
            
            rows.forEach(row => {
                const isHidden = row.style.display === 'none';
                if (isHidden && showCount < 50) {
                    row.style.display = '';
                    row.style.animation = 'fadeIn 0.4s ease-out';
                    showCount++;
                } else if (isHidden) {
                    hiddenCount++;
                }
            });
            
            // Update button text or hide it
            if (hiddenCount === 0) {
                seeMoreBtn.style.opacity = '0';
                seeMoreBtn.style.pointerEvents = 'none';
                seeMoreBtn.style.marginTop = '-60px';
            } else {
                seeMoreBtn.textContent = `📋 See More Items (${hiddenCount} more)`;
            }
        }

        // View Item Details
        function viewItemDetails(itemCode, itemName, currentStock) {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.6);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 3000;
                animation: fadeIn 0.3s ease-out;
            `;
            
            const stockStatus = currentStock === 0 ? 'OUT OF STOCK' : currentStock < 10 ? 'LOW STOCK' : 'IN STOCK';
            const stockColor = currentStock === 0 ? '#e74c3c' : currentStock < 10 ? '#f39c12' : '#2ecc71';
            
            // Build order details HTML placeholder
            let orderDetailsHTML = `
                <!-- Order Details Section (Logbook) -->
                <div style="margin-bottom: 24px; padding: 20px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.12) 0%, rgba(52, 152, 219, 0.05) 100%); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 14px;">
                    <label style="
                        font-size: 13px;
                        color: #a0a0a0;
                        text-transform: uppercase;
                        letter-spacing: 1.5px;
                        display: block;
                        margin-bottom: 18px;
                        font-weight: 700;
                    "><i class="fas fa-book" style="margin-right: 8px; color: #3498db;"></i>Order Logbook</label>
                    <div style="
                        padding: 20px;
                        background: rgba(255, 255, 255, 0.02);
                        border: 1px solid rgba(52, 152, 219, 0.2);
                        border-radius: 12px;
                        text-align: center;
                    ">
                        <span style="font-size: 14px; color: #a0a0a0;"><i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i>Loading order history...</span>
                    </div>
                </div>
            `;
            
            modal.innerHTML = `
                <div style="
                    background: linear-gradient(135deg, #0f1419 0%, #1a1f2e 100%);
                    border-radius: 20px;
                    padding: 0;
                    max-width: 90vw;
                    max-height: 90vh;
                    width: 90%;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 1px rgba(255, 255, 255, 0.1);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
                    overflow: hidden;
                    overflow-y: auto;
                    scrollbar-width: none;
                    -ms-overflow-style: none;
                ">
                <style>
                    div::-webkit-scrollbar {
                        display: none;
                    }
                </style>
                    <!-- Header with gradient bar -->
                    <div style="
                        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
                        padding: 24px;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    ">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="
                                width: 40px;
                                height: 40px;
                                background: rgba(255, 255, 255, 0.2);
                                border-radius: 10px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 20px;
                            ">
                                👁️
                            </div>
                            <h2 style="margin: 0; font-size: 22px; color: #fff; font-weight: 700;">Item Details</h2>
                        </div>
                        <button onclick="this.closest('div').parentElement.parentElement.remove()" style="
                            background: rgba(255, 255, 255, 0.2);
                            border: none;
                            color: #fff;
                            font-size: 24px;
                            cursor: pointer;
                            padding: 8px 12px;
                            border-radius: 8px;
                            transition: all 0.3s ease;
                        " onmouseover="this.style.background='rgba(255, 255, 255, 0.3)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <!-- Content -->
                    <div style="padding: 50px;">
                        <!-- Item Code -->
                        <div style="margin-bottom: 24px;">
                            <label style="
                                font-size: 12px;
                                color: #a0a0a0;
                                text-transform: uppercase;
                                letter-spacing: 1px;
                                display: block;
                                margin-bottom: 10px;
                                font-weight: 600;
                            ">Item Code</label>
                            <div style="
                                padding: 14px 16px;
                                background: rgba(255, 255, 255, 0.05);
                                border: 1px solid rgba(255, 255, 255, 0.1);
                                border-radius: 12px;
                                color: #f4d03f;
                                font-size: 16px;
                                font-weight: 700;
                                font-family: 'Courier New', monospace;
                                letter-spacing: 0.5px;
                            ">${itemCode}</div>
                        </div>
                        
                        <!-- Item Name -->
                        <div style="margin-bottom: 24px;">
                            <label style="
                                font-size: 12px;
                                color: #a0a0a0;
                                text-transform: uppercase;
                                letter-spacing: 1px;
                                display: block;
                                margin-bottom: 10px;
                                font-weight: 600;
                            ">Item Name</label>
                            <div style="
                                padding: 14px 16px;
                                background: rgba(255, 255, 255, 0.05);
                                border: 1px solid rgba(255, 255, 255, 0.1);
                                border-radius: 12px;
                                color: #e0e0e0;
                                font-size: 15px;
                                line-height: 1.5;
                                word-break: break-word;
                            ">${itemName}</div>
                        </div>
                        
                        <!-- Current Stock (with status) -->
                        <div style="margin-bottom: 24px;">
                            <label style="
                                font-size: 12px;
                                color: #a0a0a0;
                                text-transform: uppercase;
                                letter-spacing: 1px;
                                display: block;
                                margin-bottom: 10px;
                                font-weight: 600;
                            ">Current Stock</label>
                            <div style="
                                padding: 16px;
                                background: linear-gradient(135deg, rgba(23, 162, 184, 0.1) 0%, rgba(19, 132, 150, 0.1) 100%);
                                border: 2px solid rgba(23, 162, 184, 0.3);
                                border-radius: 12px;
                                text-align: center;
                            ">
                                <div style="
                                    font-size: 32px;
                                    font-weight: 700;
                                    color: #17a2b8;
                                    margin-bottom: 8px;
                                ">${currentStock.toLocaleString()}</div>
                                <div style="
                                    font-size: 12px;
                                    color: #a0a0a0;
                                    font-weight: 600;
                                    margin-bottom: 8px;
                                ">UNITS</div>
                                <div style="
                                    display: inline-block;
                                    padding: 6px 12px;
                                    background: ${stockColor};
                                    color: #fff;
                                    border-radius: 20px;
                                    font-size: 11px;
                                    font-weight: 700;
                                    letter-spacing: 0.5px;
                                ">
                                    ${stockStatus}
                                </div>
                            </div>
                        </div>
                        
                        <div id="orderDetailsContainer">${orderDetailsHTML}</div>
                        
                        <!-- Info Box -->
                        <div style="
                            margin-bottom: 28px;
                            padding: 16px;
                            background: rgba(255, 255, 255, 0.03);
                            border-left: 4px solid rgba(23, 162, 184, 0.5);
                            border-radius: 8px;
                        ">
                            <div style="
                                font-size: 13px;
                                color: #a0a0a0;
                                line-height: 1.6;
                            ">
                                <i class="fas fa-info-circle" style="margin-right: 8px; color: #17a2b8;"></i>
                                Use the <strong>Edit</strong> button to modify item details, <strong>Add</strong> to increase stock, and <strong>Remove</strong> to decrease stock.
                            </div>
                        </div>
                        
                        <!-- Close Button -->
                        <button onclick="this.closest('div').parentElement.parentElement.remove()" style="
                            width: 100%;
                            padding: 14px;
                            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
                            border: none;
                            border-radius: 12px;
                            color: #fff;
                            font-weight: 700;
                            cursor: pointer;
                            transition: all 0.3s ease;
                            font-size: 14px;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                            box-shadow: 0 10px 30px rgba(23, 162, 184, 0.3);
                        " onmouseover="this.style.boxShadow='0 15px 40px rgba(23, 162, 184, 0.5)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 10px 30px rgba(23, 162, 184, 0.3)'; this.style.transform='translateY(0)'">
                            <i class="fas fa-check" style="margin-right: 6px;"></i>Got It
                        </button>
                    </div>
                </div>
                
                <style>
                    @keyframes fadeIn {
                        from {
                            opacity: 0;
                        }
                        to {
                            opacity: 1;
                        }
                    }
                    
                    @keyframes slideUp {
                        from {
                            transform: translateY(30px);
                            opacity: 0;
                        }
                        to {
                            transform: translateY(0);
                            opacity: 1;
                        }
                    }
                </style>
            `;
            
            document.body.appendChild(modal);
            modal.onclick = (e) => {
                if (e.target === modal) modal.remove();
            };
            
            // Now fetch order details asynchronously in the background
            fetchOrderDetailsAsync(itemCode, modal);
        }
        
        // Async function to fetch order details without blocking modal display
        async function fetchOrderDetailsAsync(itemCode, modal) {
            try {
                const response = await fetch('api/get-item-orders.php?item_code=' + encodeURIComponent(itemCode));
                const data = await response.json();
                
                if (data.success && data.orders && data.orders.length > 0) {
                    updateOrderDetailsInModal(modal, data.orders);
                }
            } catch (error) {
                console.log('Could not fetch order details:', error);
                // Keep placeholder if error
            }
        }
        
        // Update the modal with fetched order details - now displays ALL orders
        function updateOrderDetailsInModal(modal, allOrders) {
            let orderDetailsHTML = `
                <!-- Order Details Section (Logbook) -->
                <div style="margin-bottom: 24px; padding: 20px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.12) 0%, rgba(52, 152, 219, 0.05) 100%); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 14px;">
                    <label style="
                        font-size: 13px;
                        color: #a0a0a0;
                        text-transform: uppercase;
                        letter-spacing: 1.5px;
                        display: block;
                        margin-bottom: 18px;
                        font-weight: 700;
                    "><i class="fas fa-book" style="margin-right: 8px; color: #3498db;"></i>Order Logbook (${allOrders.length} records)</label>
                    
                    <!-- Logbook Entries List -->
            `;
            
            // Loop through all orders and create an entry for each
            allOrders.forEach((orderDetails, index) => {
                const orderDate = orderDetails.order_date ? new Date(orderDetails.order_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';
                let deliveryDate = 'N/A';
                let deliveryLabel = 'Expected Delivery:';
                
                if (orderDetails.status === 'In Inventory') {
                    deliveryDate = orderDetails.expected_delivery_date ? new Date(orderDetails.expected_delivery_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';
                    deliveryLabel = 'Delivered Date:';
                } else {
                    deliveryDate = orderDetails.expected_delivery_date ? new Date(orderDetails.expected_delivery_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';
                    deliveryLabel = 'Expected Delivery:';
                }
                
                let statusColor = '#95a5a6';
                if (orderDetails.status === 'In Inventory') {
                    statusColor = '#2ecc71';
                } else if (orderDetails.status === 'Delivered') {
                    statusColor = '#2ecc71';
                } else if (orderDetails.status === 'Processing') {
                    statusColor = '#3498db';
                } else if (orderDetails.status === 'Shipped') {
                    statusColor = '#f39c12';
                } else if (orderDetails.status === 'Pending') {
                    statusColor = '#95a5a6';
                } else {
                    statusColor = '#e74c3c';
                }
                
                const entryNumber = index + 1;
                
                orderDetailsHTML += `
                    <!-- Logbook Entry Card {${entryNumber}} -->
                    <div style="
                        background: rgba(255, 255, 255, 0.02);
                        border: 1px solid rgba(52, 152, 219, 0.2);
                        border-radius: 12px;
                        padding: 14px;
                        margin-bottom: 10px;
                    ">
                        <div style="font-size: 10px; color: #7a7a7a; margin-bottom: 10px; font-weight: 600;">📌 Record #${entryNumber}</div>
                        
                        <!-- Row 1: Dates -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px;">
                            <!-- Order Date -->
                            <div>
                                <div style="font-size: 10px; color: #7a7a7a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; font-weight: 600;">📋 Order Date</div>
                                <div style="font-size: 13px; color: #f4d03f; font-weight: 700;">${orderDate}</div>
                            </div>
                            
                            <!-- Delivered/Expected Date -->
                            <div>
                                <div style="font-size: 10px; color: #7a7a7a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; font-weight: 600;">📦 ${deliveryLabel.split(':')[0]}</div>
                                <div style="font-size: 13px; color: #17a2b8; font-weight: 700;">${deliveryDate}</div>
                            </div>
                        </div>
                        
                        <!-- Row 2: Quantity & Status -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: center;">
                            <!-- Quantity -->
                            <div>
                                <div style="font-size: 10px; color: #7a7a7a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; font-weight: 600;">📊 Qty</div>
                                <div style="font-size: 13px; color: #2ecc71; font-weight: 700;">${orderDetails.quantity} units</div>
                            </div>
                            
                            <!-- Status -->
                            <div>
                                <div style="font-size: 10px; color: #7a7a7a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; font-weight: 600;">🔔 Status</div>
                                <span style="
                                    display: inline-block;
                                    padding: 4px 10px;
                                    background: ${statusColor};
                                    color: #fff;
                                    border-radius: 16px;
                                    font-size: 10px;
                                    font-weight: 700;
                                    letter-spacing: 0.5px;
                                ">${orderDetails.status}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            orderDetailsHTML += `
                </div>
            `;
            
            const container = modal.querySelector('#orderDetailsContainer');
            if (container) {
                container.innerHTML = orderDetailsHTML;
            }
        }

        // Open Edit Stock Modal
        function openEditStockModal(itemCode, itemName, currentStock) {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.6);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 3000;
                animation: fadeIn 0.3s ease-out;
            `;
            
            modal.innerHTML = `
                <div style="
                    background: linear-gradient(135deg, #0f1419 0%, #1a1f2e 100%);
                    border-radius: 20px;
                    padding: 0;
                    max-width: 480px;
                    width: 90%;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 1px rgba(255, 255, 255, 0.1);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
                    overflow: hidden;
                ">
                    <!-- Header with gradient bar -->
                    <div style="
                        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
                        padding: 24px;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    ">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="
                                width: 40px;
                                height: 40px;
                                background: rgba(255, 255, 255, 0.2);
                                border-radius: 10px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 20px;
                            ">
                                ✏️
                            </div>
                            <h2 style="margin: 0; font-size: 22px; color: #fff; font-weight: 700;">Edit Stock Details</h2>
                        </div>
                        <button onclick="this.closest('div').parentElement.parentElement.remove()" style="
                            background: rgba(255, 255, 255, 0.2);
                            border: none;
                            color: #fff;
                            font-size: 24px;
                            cursor: pointer;
                            padding: 8px 12px;
                            border-radius: 8px;
                            transition: all 0.3s ease;
                        " onmouseover="this.style.background='rgba(255, 255, 255, 0.3)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <!-- Content -->
                    <div style="padding: 32px;">
                        <form onsubmit="handleEditStock(event, '${itemCode.replace(/'/g, "\\'")}')">
                            <!-- Item Code (Editable) -->
                            <div style="margin-bottom: 24px;">
                                <label style="
                                    font-size: 12px;
                                    color: #a0a0a0;
                                    text-transform: uppercase;
                                    letter-spacing: 1px;
                                    display: block;
                                    margin-bottom: 10px;
                                    font-weight: 600;
                                ">Item Code</label>
                                <input type="text" id="editItemCode" value="${itemCode}" style="
                                    width: 100%;
                                    padding: 14px 16px;
                                    background: rgba(255, 255, 255, 0.05);
                                    border: 2px solid rgba(255, 255, 255, 0.1);
                                    border-radius: 12px;
                                    color: #f4d03f;
                                    font-size: 15px;
                                    font-weight: 600;
                                    font-family: 'Courier New', monospace;
                                    transition: all 0.3s ease;
                                " onfocus="this.style.borderColor='rgba(52, 152, 219, 0.5); this.style.background='rgba(52, 152, 219, 0.08)'" onblur="this.style.borderColor='rgba(255, 255, 255, 0.1)'; this.style.background='rgba(255, 255, 255, 0.05)'">
                            </div>
                            
                            <!-- Item Name (Editable) -->
                            <div style="margin-bottom: 24px;">
                                <label style="
                                    font-size: 12px;
                                    color: #a0a0a0;
                                    text-transform: uppercase;
                                    letter-spacing: 1px;
                                    display: block;
                                    margin-bottom: 10px;
                                    font-weight: 600;
                                ">Item Name</label>
                                <input type="text" id="editItemName" value="${itemName}" style="
                                    width: 100%;
                                    padding: 14px 16px;
                                    background: rgba(255, 255, 255, 0.05);
                                    border: 2px solid rgba(255, 255, 255, 0.1);
                                    border-radius: 12px;
                                    color: #e0e0e0;
                                    font-size: 15px;
                                    transition: all 0.3s ease;
                                " onfocus="this.style.borderColor='rgba(52, 152, 219, 0.5); this.style.background='rgba(52, 152, 219, 0.08)'" onblur="this.style.borderColor='rgba(255, 255, 255, 0.1)'; this.style.background='rgba(255, 255, 255, 0.05)'">
                            </div>
                            
                            <!-- Current Stock (Editable) -->
                            <div style="margin-bottom: 28px;">
                                <label style="
                                    font-size: 12px;
                                    color: #a0a0a0;
                                    text-transform: uppercase;
                                    letter-spacing: 1px;
                                    display: block;
                                    margin-bottom: 10px;
                                    font-weight: 600;
                                ">Current Stock</label>
                                <input type="number" id="editCurrentStock" value="${currentStock}" min="0" style="
                                    width: 100%;
                                    padding: 14px 16px;
                                    background: linear-gradient(135deg, rgba(46, 204, 113, 0.1) 0%, rgba(39, 174, 96, 0.1) 100%);
                                    border: 2px solid rgba(46, 204, 113, 0.3);
                                    border-radius: 12px;
                                    color: #2ecc71;
                                    font-size: 15px;
                                    font-weight: 600;
                                    transition: all 0.3s ease;
                                " onfocus="this.style.borderColor='rgba(46, 204, 113, 0.6)'" onblur="this.style.borderColor='rgba(46, 204, 113, 0.3)'">
                            </div>
                            
                            <!-- Adjustment Input -->
                            <div style="margin-bottom: 24px;">
                                <label style="
                                    font-size: 12px;
                                    color: #a0a0a0;
                                    text-transform: uppercase;
                                    letter-spacing: 1px;
                                    display: block;
                                    margin-bottom: 10px;
                                    font-weight: 600;
                                ">Quick Adjustment</label>
                                <div style="display: flex; gap: 10px;">
                                    <button type="button" onclick="adjustStock(-10, this.closest('form'))" style="
                                        flex: 1;
                                        padding: 10px;
                                        background: rgba(231, 76, 60, 0.2);
                                        border: 1px solid rgba(231, 76, 60, 0.4);
                                        border-radius: 8px;
                                        color: #e74c3c;
                                        font-weight: 600;
                                        cursor: pointer;
                                        transition: all 0.3s ease;
                                        font-size: 12px;
                                    ">- 10</button>
                                    <button type="button" onclick="adjustStock(-1, this.closest('form'))" style="
                                        flex: 1;
                                        padding: 10px;
                                        background: rgba(231, 76, 60, 0.2);
                                        border: 1px solid rgba(231, 76, 60, 0.4);
                                        border-radius: 8px;
                                        color: #e74c3c;
                                        font-weight: 600;
                                        cursor: pointer;
                                        transition: all 0.3s ease;
                                        font-size: 12px;
                                    ">- 1</button>
                                    <button type="button" onclick="adjustStock(1, this.closest('form'))" style="
                                        flex: 1;
                                        padding: 10px;
                                        background: rgba(46, 204, 113, 0.2);
                                        border: 1px solid rgba(46, 204, 113, 0.4);
                                        border-radius: 8px;
                                        color: #2ecc71;
                                        font-weight: 600;
                                        cursor: pointer;
                                        transition: all 0.3s ease;
                                        font-size: 12px;
                                    ">+ 1</button>
                                    <button type="button" onclick="adjustStock(10, this.closest('form'))" style="
                                        flex: 1;
                                        padding: 10px;
                                        background: rgba(46, 204, 113, 0.2);
                                        border: 1px solid rgba(46, 204, 113, 0.4);
                                        border-radius: 8px;
                                        color: #2ecc71;
                                        font-weight: 600;
                                        cursor: pointer;
                                        transition: all 0.3s ease;
                                        font-size: 12px;
                                    ">+ 10</button>
                                </div>
                            </div>
                            
                            <!-- Buttons -->
                            <div style="display: flex; gap: 12px;">
                                <button type="button" onclick="this.closest('form').parentElement.parentElement.parentElement.remove()" style="
                                    flex: 1;
                                    padding: 14px;
                                    background: rgba(255, 255, 255, 0.08);
                                    border: 1.5px solid rgba(255, 255, 255, 0.15);
                                    border-radius: 12px;
                                    color: #a0a0a0;
                                    font-weight: 600;
                                    cursor: pointer;
                                    transition: all 0.3s ease;
                                    font-size: 14px;
                                    text-transform: uppercase;
                                    letter-spacing: 0.5px;
                                " onmouseover="this.style.background='rgba(255, 255, 255, 0.12); this.style.color='#fff'" onmouseout="this.style.background='rgba(255, 255, 255, 0.08); this.style.color='#a0a0a0'">
                                    <i class="fas fa-times" style="margin-right: 6px;"></i>Cancel
                                </button>
                                <button type="submit" style="
                                    flex: 1;
                                    padding: 14px;
                                    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
                                    border: none;
                                    border-radius: 12px;
                                    color: #fff;
                                    font-weight: 700;
                                    cursor: pointer;
                                    transition: all 0.3s ease;
                                    font-size: 14px;
                                    text-transform: uppercase;
                                    letter-spacing: 0.5px;
                                    box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3);
                                " onmouseover="this.style.boxShadow='0 15px 40px rgba(52, 152, 219, 0.5); this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 10px 30px rgba(52, 152, 219, 0.3); this.style.transform='translateY(0)'">
                                    <i class="fas fa-save" style="margin-right: 6px;"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <style>
                    @keyframes fadeIn {
                        from {
                            opacity: 0;
                        }
                        to {
                            opacity: 1;
                        }
                    }
                    
                    @keyframes slideUp {
                        from {
                            transform: translateY(30px);
                            opacity: 0;
                        }
                        to {
                            transform: translateY(0);
                            opacity: 1;
                        }
                    }
                </style>
            `;
            
            document.body.appendChild(modal);
            modal.onclick = (e) => {
                if (e.target === modal) modal.remove();
            };
            
            document.getElementById('editCurrentStock').focus();
        }

        // Adjust Stock by Amount
        function adjustStock(amount, form) {
            const currentStockInput = form.querySelector('#editCurrentStock');
            let currentValue = parseInt(currentStockInput.value) || 0;
            const newValue = Math.max(0, currentValue + amount);
            currentStockInput.value = newValue;
        }

        // Show Custom Notification
        function showNotification(message, type = 'success', duration = 3000) {
            const notification = document.createElement('div');
            const isSuccess = type === 'success';
            const bgColor = isSuccess ? '#2ecc71' : '#e74c3c';
            const icon = isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            notification.style.cssText = `
                position: fixed;
                bottom: 30px;
                right: 30px;
                background: linear-gradient(135deg, ${bgColor} 0%, ${isSuccess ? '#27ae60' : '#c0392b'} 100%);
                color: #fff;
                padding: 16px 24px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                display: flex;
                align-items: center;
                gap: 12px;
                z-index: 9999;
                animation: slideInRight 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
                font-weight: 600;
                font-size: 14px;
                border: 2px solid rgba(255, 255, 255, 0.2);
                max-width: 400px;
            `;
            
            notification.innerHTML = `
                <i class="fas ${icon}" style="font-size: 18px;"></i>
                <span>${message}</span>
                <style>
                    @keyframes slideInRight {
                        from {
                            transform: translateX(400px);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    @keyframes slideOutRight {
                        from {
                            transform: translateX(0);
                            opacity: 1;
                        }
                        to {
                            transform: translateX(400px);
                            opacity: 0;
                        }
                    }
                </style>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after duration
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
                setTimeout(() => {
                    notification.remove();
                }, 400);
            }, duration);
        }

        // Show Loading Animation
        let dotAnimationInterval = null;
        let loaderStartTime = null;
        const LOADER_MIN_DISPLAY_TIME = 3000; // 3 seconds in milliseconds
        
        function showLoadingOverlay(show = true, message = 'Saving') {
            const container = document.getElementById('gearLoaderContainer');
            const messageSpan = document.getElementById('loaderMessage');
            
            if (show) {
                loaderStartTime = Date.now(); // Record start time
                messageSpan.textContent = message;
                container.style.display = 'flex';
                
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
                
                // Calculate elapsed time and add delay if needed
                const elapsedTime = Date.now() - (loaderStartTime || Date.now());
                const remainingTime = Math.max(0, LOADER_MIN_DISPLAY_TIME - elapsedTime);
                
                if (remainingTime > 0) {
                    setTimeout(() => {
                        container.style.display = 'none';
                    }, remainingTime);
                } else {
                    container.style.display = 'none';
                }
            }
        }

        // Handle Edit Stock
        function handleEditStock(event, originalItemCode) {
            event.preventDefault();
            const newItemCode = document.getElementById('editItemCode').value.trim();
            const newItemName = document.getElementById('editItemName').value.trim();
            const newCurrentStock = parseInt(document.getElementById('editCurrentStock').value) || 0;
            
            if (!newItemCode || !newItemName) {
                showNotification('Please fill in all fields', 'error');
                return;
            }
            
            showLoadingOverlay(true, 'Saving');
            
            fetch('api/edit-stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    original_item_code: originalItemCode,
                    item_code: newItemCode,
                    item_name: newItemName,
                    current_stock: newCurrentStock
                })
            })
            .then(response => response.json())
            .then(data => {
                showLoadingOverlay(false);
                if (data.success) {
                    showNotification('✓ Stock details updated successfully!', 'success', 2000);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('✗ ' + (data.error || data.message || 'Failed to update stock'), 'error', 4000);
                }
            })
            .catch(error => {
                showLoadingOverlay(false);
                showNotification('✗ Error: ' + error.message, 'error', 4000);
            });
        }

        // Update New Total Preview
        function updateNewTotal(currentStock) {
            const quantityInput = document.getElementById('stockQuantity');
            const newTotalDiv = document.getElementById('newTotal');
            const quantity = parseInt(quantityInput.value) || 0;
            
            // Check if we're in reduce mode
            const modalDiv = document.querySelector('[style*="position: fixed"]');
            const submitBtn = modalDiv?.querySelector('[type="submit"]');
            const mode = submitBtn?.dataset.mode || 'add';
            
            let newTotal;
            if (mode === 'reduce') {
                newTotal = Math.max(0, currentStock - quantity);
            } else {
                newTotal = currentStock + quantity;
            }
            
            newTotalDiv.textContent = newTotal.toLocaleString() + ' UNITS';
        }

        // Handle Add Stock
        function handleAddStock(event, itemCode, itemName, currentStock) {
            event.preventDefault();
            const quantity = parseInt(document.getElementById('stockQuantity').value);
            const submitBtn = event.target.closest('form').querySelector('[type="submit"]');
            const mode = submitBtn.dataset.mode || 'add';
            
            const endpoint = mode === 'add' ? 'api/add-stock.php' : 'api/reduce-stock.php';
            const loaderMessage = mode === 'add' ? 'Adding' : 'Reducing';
            
            showLoadingOverlay(true, loaderMessage);
            
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    item_code: itemCode,
                    quantity: quantity,
                    mode: mode
                })
            })
            .then(response => response.json())
            .then(data => {
                showLoadingOverlay(false);
                if (data.success) {
                    const msg = mode === 'add' ? '✓ Stock added successfully!' : '✓ Stock reduced successfully!';
                    showNotification(msg, 'success', 2000);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('✗ ' + (data.error || data.message || 'Operation failed'), 'error', 4000);
                }
            })
            .catch(error => {
                showLoadingOverlay(false);
                showNotification('✗ Error: ' + error.message, 'error', 4000);
            });
        }

        // Show Custom Confirmation Dialog
        function showConfirmDialog(title, message, onConfirm) {
            const dialog = document.createElement('div');
            dialog.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.6);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 3001;
                animation: fadeIn 0.3s ease-out;
            `;
            
            dialog.innerHTML = `
                <div style="
                    background: linear-gradient(135deg, #0f1419 0%, #1a1f2e 100%);
                    border-radius: 16px;
                    padding: 32px;
                    max-width: 450px;
                    width: 90%;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(231, 76, 60, 0.15);
                    border: 1px solid rgba(231, 76, 60, 0.2);
                    text-align: center;
                    animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
                ">
                    <div style="
                        font-size: 56px; 
                        margin-bottom: 20px;
                        animation: pulse 2s infinite;
                    ">🗑️</div>
                    <h2 style="
                        margin: 0 0 14px 0;
                        font-size: 22px;
                        color: #fff;
                        font-weight: 700;
                        letter-spacing: 0.3px;
                    ">${title}</h2>
                    <div style="
                        background: rgba(231, 76, 60, 0.05);
                        border-left: 3px solid #e74c3c;
                        padding: 12px 16px;
                        border-radius: 6px;
                        margin: 0 0 20px 0;
                        text-align: left;
                    ">
                        <p style="
                            margin: 0;
                            color: #a0a0a0;
                            font-size: 13px;
                            line-height: 1.7;
                            white-space: pre-wrap;
                        ">${message}</p>
                    </div>
                    <div style="
                        background: rgba(255, 255, 255, 0.02);
                        padding: 12px;
                        border-radius: 8px;
                        margin-bottom: 20px;
                        font-size: 12px;
                        color: #ff6b6b;
                        border: 1px solid rgba(255, 107, 107, 0.15);
                    ">
                        ⚠️ This action cannot be undone!
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button onmouseover="this.style.background='rgba(255, 255, 255, 0.12)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.08)'" onclick="this.closest('div').parentElement.remove()" style="
                            flex: 1;
                            padding: 13px 16px;
                            background: rgba(255, 255, 255, 0.08);
                            border: 1px solid rgba(255, 255, 255, 0.15);
                            border-radius: 10px;
                            color: #a0a0a0;
                            font-weight: 600;
                            cursor: pointer;
                            transition: all 0.3s ease;
                            font-size: 13px;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                        ">Cancel</button>
                        <button id="confirmBtn" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 16px 40px rgba(231, 76, 60, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 10px 30px rgba(231, 76, 60, 0.3)'" style="
                            flex: 1;
                            padding: 13px 16px;
                            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
                            border: none;
                            border-radius: 10px;
                            color: #fff;
                            font-weight: 700;
                            cursor: pointer;
                            transition: all 0.3s ease;
                            font-size: 13px;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                            box-shadow: 0 10px 30px rgba(231, 76, 60, 0.3);
                        ">🗑️ Delete</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(dialog);
            dialog.onclick = (e) => {
                if (e.target === dialog) dialog.remove();
            };
            
            document.getElementById('confirmBtn').onclick = (e) => {
                e.stopPropagation();
                dialog.remove();
                onConfirm();
            };
        }

        // Confirm Delete Item
        function confirmDeleteItem(itemCode, itemName) {
            showConfirmDialog(
                'Delete Item?',
                `Are you sure you want to delete "${itemName}" (${itemCode})?\n\nThis action cannot be undone.`,
                () => {
                    showLoadingOverlay(true, 'Deleting');
                    
                    fetch('api/delete-item.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            item_code: itemCode
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        showLoadingOverlay(false);
                        if (data.success) {
                            showNotification('Item deleted successfully!', 'success', 3000);
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            showNotification('✗ ' + (data.message || 'Failed to delete item'), 'error', 4000);
                        }
                    })
                    .catch(error => {
                        showLoadingOverlay(false);
                        showNotification('✗ Error: ' + error.message, 'error', 4000);
                    });
                }
            );
        }

        // Delete entire dataset from inventory
        function deleteInventoryDataset(filename, displayName) {
            closeDeleteModal();
            showLoadingOverlay(true, 'Deleting');
            
            fetch('api/delete-inventory-dataset.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    source_file: `Upload: ${filename}`
                })
            })
            .then(response => response.json())
            .then(data => {
                showLoadingOverlay(false);
                if (data.success) {
                    showNotification('✓ Dataset deleted successfully!', 'success', 2000);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('✗ ' + (data.message || 'Failed to delete dataset'), 'error', 4000);
                }
            })
            .catch(error => {
                showLoadingOverlay(false);
                showNotification('✗ Error: ' + error.message, 'error', 4000);
            });
        }
        
        function showDeleteConfirmModal(filename) {
            const modal = document.getElementById('deleteConfirmModal');
            const filenameDisplay = document.getElementById('deleteFileName');
            filenameDisplay.textContent = filename;
            modal.style.display = 'block';
        }
        
        function closeDeleteModal() {
            const modal = document.getElementById('deleteConfirmModal');
            modal.style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('deleteConfirmModal');
            if (event.target == modal) {
                closeDeleteModal();
            }
        }

        // Order functions
        function openEditOrderModal(orderId, itemCode, itemName, quantity, currentStatus) {
            document.getElementById('editOrderId').value = orderId;
            document.getElementById('editOrderItemCode').value = itemCode;
            document.getElementById('editOrderItemName').value = itemName;
            document.getElementById('editOrderQuantity').value = quantity;
            document.getElementById('editOrderStatus').value = currentStatus;
            document.getElementById('editOrderModal').style.display = 'block';
            document.getElementById('editOrderModalAlert').style.display = 'none';
        }

        function closeEditOrderModal() {
            document.getElementById('editOrderModal').style.display = 'none';
            document.getElementById('editOrderForm').reset();
            document.getElementById('editOrderModalAlert').style.display = 'none';
        }

        function submitEditOrder(event) {
            event.preventDefault();
            
            const formData = new FormData();
            formData.append('id', document.getElementById('editOrderId').value);
            formData.append('status', document.getElementById('editOrderStatus').value);
            
            const alertDiv = document.getElementById('editOrderModalAlert');
            const submitBtn = document.querySelector('#editOrderForm .btn-submit');
            const originalBtnHTML = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            alertDiv.style.display = 'none';

            fetch('api/update-order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertDiv.className = 'alert alert-success';
                    const message = data.moved_to_inventory ? 'Order marked as received and moved to inventory!' : 'Order updated successfully!';
                    alertDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message} Refreshing...`;
                    alertDiv.style.display = 'block';
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    alertDiv.className = 'alert alert-error';
                    alertDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.error || 'Error updating order'}`;
                    alertDiv.style.display = 'block';
                    
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHTML;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alertDiv.className = 'alert alert-error';
                alertDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.`;
                alertDiv.style.display = 'block';
                
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHTML;
            });
        }

        function viewOrderDetails(orderId) {
            alert('Order details for ID: ' + orderId + '\n\nFull details view coming soon!');
        }

        function confirmDeleteOrder(orderId, itemCode) {
            if (confirm(`Are you sure you want to delete the order for item ${itemCode}?`)) {
                deleteOrder(orderId);
            }
        }

        function deleteOrder(orderId) {
            const formData = new FormData();
            formData.append('id', orderId);

            fetch('api/delete-order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✓ Order deleted successfully!', 'success', 3000);
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    showNotification('✗ Error: ' + (data.error || 'Failed to delete order'), 'error', 3000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('✗ Error deleting order', 'error', 3000);
            });
        }

        function showNotification(message, type = 'success', duration = 3000) {
            const notification = document.createElement('div');
            
            // Determine icon and color based on type
            let icon = '✓';
            let bgColor = 'rgba(46, 204, 113, 0.15)';
            let borderColor = 'rgba(46, 204, 113, 0.3)';
            let textColor = '#2ecc71';
            let shadowColor = 'rgba(46, 204, 113, 0.2)';
            
            if (type === 'error') {
                icon = '✗';
                bgColor = 'rgba(231, 76, 60, 0.15)';
                borderColor = 'rgba(231, 76, 60, 0.3)';
                textColor = '#e74c3c';
                shadowColor = 'rgba(231, 76, 60, 0.2)';
            } else if (type === 'warning') {
                icon = '⚠️';
                bgColor = 'rgba(241, 196, 15, 0.15)';
                borderColor = 'rgba(241, 196, 15, 0.3)';
                textColor = '#f1c40f';
                shadowColor = 'rgba(241, 196, 15, 0.2)';
            }
            
            notification.style.cssText = `
                position: fixed;
                top: 30px;
                right: 30px;
                background: linear-gradient(135deg, #0f1419 0%, #1a1f2e 100%);
                border: 1px solid ${borderColor};
                border-left: 3px solid ${textColor};
                border-radius: 12px;
                padding: 16px 24px;
                max-width: 400px;
                width: 90%;
                box-shadow: 0 15px 40px ${shadowColor}, 0 0 1px rgba(255, 255, 255, 0.1);
                z-index: 9999;
                animation: slideInRight 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
                font-size: 14px;
                color: #fff;
                font-weight: 500;
                letter-spacing: 0.3px;
                display: flex;
                align-items: center;
                gap: 12px;
            `;
            
            notification.innerHTML = `
                <span style="font-size: 20px; flex-shrink: 0;">${icon}</span>
                <span style="flex: 1;">${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after duration
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
                setTimeout(() => {
                    notification.remove();
                }, 400);
            }, duration);
        }

        // Create Order Modal Functions (from orders.php)
        const toggleCreateBtn = document.getElementById('toggleCreateBtn');
        const createOrderModal = document.getElementById('createOrderModal');
        const closeCreateBtn = document.getElementById('closeCreateBtn');
        const createOrderForm = document.getElementById('createOrderForm');
        const saveOrderBtn = document.getElementById('saveOrderBtn');

        function addProductRow() {
            const container = document.getElementById('products-container');
            const rowIndex = container.children.length;
            
            const productRow = document.createElement('div');
            productRow.className = 'product-row';
            productRow.style.cssText = `
                display: grid;
                grid-template-columns: repeat(5, 1fr) auto;
                gap: 10px;
                padding: 12px;
                border: 1px solid rgba(255,255,255,0.1);
                border-radius: 8px;
                background: rgba(255,255,255,0.02);
            `;
            
            productRow.innerHTML = `
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 12px; color: #aaa; margin-bottom: 4px; display: block;">Product Code</label>
                    <input type="text" name="products[${rowIndex}][item_code]" placeholder="Code" required style="padding: 10px; border-radius: 6px; border: 1px solid rgba(244,208,63,0.25); background: rgba(30,42,56,0.6); color: #fff; font-size: 13px;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 12px; color: #aaa; margin-bottom: 4px; display: block;">Product Name</label>
                    <input type="text" name="products[${rowIndex}][item_name]" placeholder="Name" required style="padding: 10px; border-radius: 6px; border: 1px solid rgba(244,208,63,0.25); background: rgba(30,42,56,0.6); color: #fff; font-size: 13px;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 12px; color: #aaa; margin-bottom: 4px; display: block;">Qty</label>
                    <input type="number" name="products[${rowIndex}][quantity]" placeholder="1" min="1" value="1" required style="padding: 10px; border-radius: 6px; border: 1px solid rgba(244,208,63,0.25); background: rgba(30,42,56,0.6); color: #fff; font-size: 13px;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 12px; color: #aaa; margin-bottom: 4px; display: block;">Peso Cost</label>
                    <input type="number" name="products[${rowIndex}][unit_price]" placeholder="0.00" min="0" step="0.01" value="0" style="padding: 10px; border-radius: 6px; border: 1px solid rgba(244,208,63,0.25); background: rgba(30,42,56,0.6); color: #fff; font-size: 13px;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 12px; color: #aaa; margin-bottom: 4px; display: block;">Foreign Cost</label>
                    <input type="number" name="products[${rowIndex}][foreign_cost]" placeholder="0.00" min="0" step="0.01" value="0" style="padding: 10px; border-radius: 6px; border: 1px solid rgba(244,208,63,0.25); background: rgba(30,42,56,0.6); color: #fff; font-size: 13px;">
                </div>
                <button type="button" class="remove-product-btn" onclick="removeProductRow(this)" style="background: rgba(255,76,76,0.2); border: 1px solid rgba(255,76,76,0.4); color: #ff4c4c; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; align-self: flex-end; margin-bottom: 0; white-space: nowrap;">
                    <i class="fas fa-trash"></i> Remove
                </button>
            `;
            
            container.appendChild(productRow);
        }

        function removeProductRow(btn) {
            btn.closest('.product-row').remove();
        }

        function openCreateOrderModal() {
            if (!createOrderModal) return;
            
            // Add one empty product row if none exist
            const container = document.getElementById('products-container');
            if (container.children.length === 0) {
                addProductRow();
            }
            
            // Reset form
            createOrderForm.reset();
            
            createOrderModal.classList.add('show');
            createOrderModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeCreateOrderModal() {
            if (!createOrderModal) return;
            const container = document.getElementById('products-container');
            container.innerHTML = ''; // Clear all product rows
            createOrderForm.reset();
            createOrderModal.classList.remove('show');
            createOrderModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        if (toggleCreateBtn && createOrderModal) {
            toggleCreateBtn.addEventListener('click', function () {
                openCreateOrderModal();
            });

            if (closeCreateBtn) {
                closeCreateBtn.addEventListener('click', closeCreateOrderModal);
            }

            createOrderModal.addEventListener('click', function (event) {
                if (event.target === createOrderModal) {
                    closeCreateOrderModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && createOrderModal.classList.contains('show')) {
                    closeCreateOrderModal();
                }
            });

            if (saveOrderBtn && createOrderForm) {
                saveOrderBtn.classList.add('action-btn');
                saveOrderBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (createOrderForm.checkValidity() === false) {
                        e.stopPropagation();
                        createOrderForm.classList.add('was-validated');
                    } else {
                        createOrderForm.submit();
                    }
                });
            }
        }

        // Apply order filter for Orders tab
        function applyOrderFilter(event, filter) {
            event.preventDefault();
            const url = new URL(window.location);
            url.searchParams.set('filter', filter);
            url.searchParams.set('tab', 'orders');
            window.location.href = url.toString();
        }
        
        // Remove highlight after 60 seconds for items added from purchase orders
        (function() {
            const highlightedRows = document.querySelectorAll('.purchase-order-highlight');
            if (highlightedRows.length > 0) {
                const urlParams = new URLSearchParams(window.location.search);
                const isFromPurchaseOrder = urlParams.has('highlight');
                
                if (isFromPurchaseOrder) {
                    // Bring the newest highlighted item into view immediately.
                    const highlightedRow = highlightedRows[0];
                    if (highlightedRow) {
                        highlightedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }

                    // Clean up query parameter so refresh won't retrigger forced highlight behavior.
                    urlParams.delete('highlight');
                    const cleanUrl = `${window.location.pathname}?${urlParams.toString()}`.replace(/\?$/, '');
                    window.history.replaceState({}, document.title, cleanUrl);
                    
                    // Remove purchase-order spotlight after 60 seconds.
                    setTimeout(function() {
                        highlightedRows.forEach(function(row) {
                            row.classList.remove('purchase-order-highlight');
                            row.classList.remove('new-item-highlight');
                        });
                    }, 60000);
                }
            }
        })();
    </script>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="modal" style="
        display: none;
        position: fixed;
        z-index: 1001;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(0, 0, 0, 0.8) 0%, rgba(20, 30, 45, 0.7) 100%);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        animation: fadeIn 0.4s ease;
    ">
        <div style="
            background: linear-gradient(145deg, #253547 0%, #1a2638 50%, #1a2638 100%);
            margin: 80px auto;
            padding: 45px;
            border: 2px solid #f4d03f;
            border-radius: 20px;
            width: 90%;
            max-width: 520px;
            animation: slideDown 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 
                0 30px 100px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 0 40px rgba(244, 208, 63, 0.15);
            position: relative;
            overflow: hidden;
        ">
            <!-- Background accent -->
            <div style="
                position: absolute;
                top: -50%;
                right: -50%;
                width: 300px;
                height: 300px;
                background: radial-gradient(circle, rgba(244, 208, 63, 0.1) 0%, transparent 70%);
                border-radius: 50%;
                pointer-events: none;
            "></div>
            
            <!-- Icon container with animation -->
            <div style="
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 30px;
                font-size: 56px;
                color: #ff6b6b;
                position: relative;
                z-index: 1;
                animation: pulse 2s ease-in-out infinite;
            " id="warningIcon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            
            <style>
                @keyframes pulse {
                    0%, 100% {
                        transform: scale(1);
                        filter: drop-shadow(0 0 0 rgba(255, 107, 107, 0));
                    }
                    50% {
                        transform: scale(1.05);
                        filter: drop-shadow(0 0 15px rgba(255, 107, 107, 0.5));
                    }
                }
                
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                
                @keyframes slideDown {
                    from {
                        transform: translateY(-40px);
                        opacity: 0;
                    }
                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }
                
                /* Styled scrollbar for modal */
                #deleteConfirmModal > div {
                    scrollbar-width: thin;
                    scrollbar-color: #f4d03f transparent;
                }

                #deleteConfirmModal > div::-webkit-scrollbar {
                    width: 8px;
                }

                #deleteConfirmModal > div::-webkit-scrollbar-track {
                    background: transparent;
                }

                #deleteConfirmModal > div::-webkit-scrollbar-thumb {
                    background: linear-gradient(135deg, #f4d03f 0%, #d4a93f 100%);
                    border-radius: 4px;
                    border: 1px solid rgba(244, 208, 63, 0.3);
                    box-shadow: 0 0 6px rgba(244, 208, 63, 0.2);
                }

                #deleteConfirmModal > div::-webkit-scrollbar-thumb:hover {
                    background: linear-gradient(135deg, #fff4c1 0%, #f4d03f 100%);
                    box-shadow: 0 0 10px rgba(244, 208, 63, 0.4);
                }
            </style>
            
            <h2 style="
                color: #ffffff;
                text-align: center;
                font-size: 28px;
                margin: 0 0 12px 0;
                font-weight: 800;
                letter-spacing: 0.5px;
                position: relative;
                z-index: 1;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            ">Delete Dataset?</h2>
            
            <p style="
                color: #b8c5d6;
                text-align: center;
                font-size: 14px;
                margin: 0 0 12px 0;
                line-height: 1.6;
                position: relative;
                z-index: 1;
                font-weight: 500;
            ">You are about to permanently delete:</p>
            
            <div style="
                background: linear-gradient(135deg, rgba(244, 208, 63, 0.15) 0%, rgba(244, 208, 63, 0.08) 100%);
                border: 1px solid rgba(244, 208, 63, 0.3);
                border-radius: 12px;
                padding: 16px;
                margin: 0 0 28px 0;
                position: relative;
                z-index: 1;
                box-shadow: inset 0 1px 3px rgba(244, 208, 63, 0.1);
            ">
                <p style="
                    color: #f4d03f;
                    text-align: center;
                    font-size: 15px;
                    margin: 0;
                    font-weight: 700;
                    word-break: break-word;
                    font-family: 'Poppins', sans-serif;
                    letter-spacing: 0.3px;
                " id="deleteFileName"></p>
            </div>
            
            <div style="
                color: #ffaaaa;
                text-align: center;
                font-size: 13px;
                margin: 0 0 32px 0;
                background: linear-gradient(135deg, rgba(255, 107, 107, 0.15) 0%, rgba(255, 107, 107, 0.08) 100%);
                padding: 16px;
                border-radius: 12px;
                border: 1px solid rgba(255, 107, 107, 0.25);
                border-left: 3px solid #ff6b6b;
                position: relative;
                z-index: 1;
                line-height: 1.6;
                box-shadow: inset 0 1px 3px rgba(255, 107, 107, 0.1);
            ">
                <div style='font-weight: 700; margin-bottom: 6px;'>
                    <i class='fas fa-triangle-exclamation' style='margin-right: 8px;'></i>This action cannot be undone
                </div>
                <div style='font-size: 12px; opacity: 0.9;'>All items in this dataset will be permanently removed from the inventory system.</div>
            </div>
            
            <div style="
                display: flex;
                gap: 14px;
                margin-top: 32px;
                position: relative;
                z-index: 1;
            ">
                <button onclick="closeDeleteModal()" style="
                    flex: 1;
                    padding: 16px 24px;
                    background: linear-gradient(135deg, rgba(100, 120, 150, 0.4) 0%, rgba(80, 100, 130, 0.3) 100%);
                    border: 2px solid rgba(200, 210, 230, 0.3);
                    color: #e0e0e0;
                    border-radius: 10px;
                    font-weight: 700;
                    cursor: pointer;
                    font-size: 15px;
                    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
                    letter-spacing: 0.5px;
                    font-family: 'Poppins', sans-serif;
                "
                onmouseover="this.style.background='linear-gradient(135deg, rgba(120, 140, 170, 0.5) 0%, rgba(100, 120, 150, 0.4) 100%)'; this.style.borderColor='rgba(220, 230, 250, 0.5)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(0, 0, 0, 0.3)';"
                onmouseout="this.style.background='linear-gradient(135deg, rgba(100, 120, 150, 0.4) 0%, rgba(80, 100, 130, 0.3) 100%)'; this.style.borderColor='rgba(200, 210, 230, 0.3)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(0, 0, 0, 0.2)';">
                    <i class="fas fa-times" style="margin-right: 10px; font-weight: 700;"></i><span style="font-weight: 700;">Cancel</span>
                </button>
                <button id="confirmDeleteBtn" style="
                    flex: 1;
                    padding: 16px 24px;
                    background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 50%, #ee5a52 100%);
                    border: none;
                    color: #fff;
                    border-radius: 10px;
                    font-weight: 800;
                    cursor: pointer;
                    font-size: 15px;
                    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                    box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.2);
                    letter-spacing: 0.5px;
                    font-family: 'Poppins', sans-serif;
                    position: relative;
                    overflow: hidden;
                "
                onmouseover="this.style.transform='translateY(-3px) scale(1.02)'; this.style.boxShadow='0 10px 30px rgba(255, 107, 107, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.3)';"
                onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 6px 20px rgba(255, 107, 107, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.2)';">
                    <i class="fas fa-trash-alt" style="margin-right: 10px; font-weight: 700;"></i><span style="font-weight: 800;">Delete</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('confirmDeleteBtn').onclick = function() {
            const filename = document.getElementById('deleteFileName').textContent;
            deleteInventoryDataset(filename);
        };
    </script>
    <style>
        @keyframes slideInRight {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }
    </style>
