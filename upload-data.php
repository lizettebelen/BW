<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

// Get total record count from database
require_once 'db_config.php';
$totalRecords = 0;

if ($conn) {
    $result = @$conn->query("SELECT COUNT(*) as total FROM delivery_records");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && isset($row['total'])) {
            $totalRecords = intval($row['total']);
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
    <title>Upload Data - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .upload-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .upload-section {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 40px;
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: #f4d03f;
            font-size: 26px;
        }

        .section-subtitle {
            font-size: 13px;
            color: #a0a0a0;
            margin-bottom: 25px;
        }

        /* Upload Zone */
        .upload-zone {
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 50px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.02);
        }

        .upload-zone:hover,
        .upload-zone.dragover {
            border-color: #f4d03f;
            background: rgba(244, 208, 63, 0.08);
        }

        .upload-icon {
            font-size: 48px;
            color: #f4d03f;
            margin-bottom: 15px;
        }

        .upload-zone h3 {
            color: #fff;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .upload-zone p {
            color: #a0a0a0;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .upload-formats {
            color: #7a8a9a;
            font-size: 12px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        #fileInput {
            display: none;
        }

        /* File Info */
        .file-info {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            display: none;
            gap: 12px;
            align-items: center;
        }

        .file-info.show {
            display: flex;
        }

        .file-icon {
            font-size: 24px;
            color: #00d9ff;
        }

        .file-details {
            flex: 1;
            text-align: left;
        }

        .file-name {
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .file-size {
            color: #a0a0a0;
            font-size: 12px;
        }

        .file-remove {
            background: rgba(255, 107, 107, 0.2);
            border: none;
            color: #ff6b6b;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .file-remove:hover {
            background: rgba(255, 107, 107, 0.4);
        }

        /* Buttons */
        .upload-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            justify-content: center;
        }

        .btn-upload,
        .btn-cancel,
        .btn-import {
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .btn-upload {
            background: linear-gradient(135deg, #f4d03f 0%, #f1bf10 100%);
            color: #000;
        }

        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(244, 208, 63, 0.3);
        }

        .btn-upload:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-cancel {
            background: rgba(255, 255, 255, 0.1);
            color: #a0a0a0;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        .btn-import {
            background: linear-gradient(135deg, #51cf66 0%, #37b24d 100%);
            color: #fff;
            display: none;
            pointer-events: auto !important;
        }

        .btn-import:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(81, 207, 102, 0.3);
        }

        /* Preview Section */
        .preview-section {
            display: none;
        }

        .preview-section.show {
            display: block;
        }

        .preview-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(81, 207, 102, 0.1);
            border-left: 4px solid #51cf66;
            border-radius: 8px;
        }

        .preview-stats {
            display: flex;
            gap: 30px;
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-label {
            color: #a0a0a0;
            font-size: 12px;
        }

        .stat-value {
            color: #51cf66;
            font-weight: 700;
            font-size: 18px;
        }

        /* Data Preview Table */
        .table-container {
            background: #13172c;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow-x: auto;
            margin-bottom: 20px;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-container thead {
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .table-container th {
            padding: 12px;
            text-align: left;
            font-size: 13px;
            color: #a0a0a0;
            text-transform: uppercase;
            font-weight: 600;
        }

        .table-container td {
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
            font-size: 13px;
        }

        .table-container tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .row-number {
            color: #7a8a9a;
            font-size: 12px;
            text-align: center;
        }

        /* Status Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 12px;
            font-size: 13px;
        }

        .alert.show {
            display: flex;
        }

        .alert-icon {
            font-size: 16px;
        }

        .alert-success {
            background: rgba(81, 207, 102, 0.2);
            border-left: 4px solid #51cf66;
            color: #51cf66;
        }

        .alert-error {
            background: rgba(255, 107, 107, 0.2);
            border-left: 4px solid #ff6b6b;
            color: #ff6b6b;
        }

        .alert-warning {
            background: rgba(255, 214, 10, 0.2);
            border-left: 4px solid #ffd60a;
            color: #ffd60a;
        }

        .alert-info {
            background: rgba(0, 217, 255, 0.2);
            border-left: 4px solid #00d9ff;
            color: #00d9ff;
        }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #f4d03f;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Template Section */
        .template-section {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .template-info {
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        .template-icon {
            font-size: 24px;
            color: #00d9ff;
            flex-shrink: 0;
        }

        .template-content h4 {
            color: #fff;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .template-content p {
            color: #a0a0a0;
            font-size: 12px;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .template-columns {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 12px;
            font-size: 12px;
            color: #7a8a9a;
        }

        .template-columns span {
            padding: 8px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            border-left: 3px solid #f4d03f;
            padding-left: 10px;
        }

        .btn-download-template {
            background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-download-template:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 217, 255, 0.3);
        }

        /* Hide parse button (parsing is automatic) */
        #parseBtn {
            display: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .upload-section {
                padding: 25px;
            }

            .upload-zone {
                padding: 30px;
            }

            .upload-actions {
                flex-direction: column;
            }

            .btn-upload,
            .btn-cancel,
            .btn-import {
                width: 100%;
            }

            .preview-stats {
                flex-direction: column;
                gap: 15px;
            }

            .template-columns {
                grid-template-columns: 1fr;
            }
        }

        /* Success Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.show {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            max-width: 450px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            animation: slideUp 0.4s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #51cf66 0%, #37b24d 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px;
            animation: bounce 0.5s ease 0.3s;
        }

        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .modal-icon i {
            font-size: 40px;
            color: #fff;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #51cf66;
            margin-bottom: 10px;
        }

        .modal-message {
            color: #a0a0a0;
            font-size: 14px;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .modal-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 25px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
        }

        .modal-stat {
            text-align: center;
        }

        .modal-stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #51cf66;
        }

        .modal-stat-label {
            font-size: 12px;
            color: #7a8a9a;
            margin-top: 5px;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn-modal {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .btn-modal-primary {
            background: linear-gradient(135deg, #51cf66 0%, #37b24d 100%);
            color: #fff;
        }

        .btn-modal-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(81, 207, 102, 0.3);
        }

        .btn-modal-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #a0a0a0;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-modal-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        /* Delete Confirm Modal Readability */
        #deleteConfirmModal .modal-content {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border: 1px solid rgba(255, 107, 107, 0.35);
        }

        #deleteConfirmModal .modal-title {
            color: #ffd1d1;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0.2px;
        }

        #deleteConfirmModal .modal-message {
            color: #e7edf6;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.55;
            margin-bottom: 16px;
        }

        #deleteConfirmList {
            max-height: 160px;
            overflow-y: auto;
            text-align: left;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid rgba(255, 107, 107, 0.35);
            background: rgba(255, 107, 107, 0.12);
            color: #ffe4e4;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        #deleteConfirmModal .btn-modal {
            font-size: 13px;
            padding: 11px 18px;
        }

        @media (max-width: 768px) {
            #deleteConfirmModal .modal-title {
                font-size: 22px;
            }

            #deleteConfirmModal .modal-message {
                font-size: 14px;
            }

            #deleteConfirmList {
                font-size: 13px;
            }
        }

        .light-mode #deleteConfirmModal .modal-content {
            background: #ffffff;
            border: 1px solid #d4deea;
            box-shadow: 0 18px 40px rgba(21, 44, 73, 0.18);
        }

        .light-mode #deleteConfirmModal .modal-title {
            color: #1f3a5a;
        }

        .light-mode #deleteConfirmModal .modal-message {
            color: #223b57;
        }

        .light-mode #deleteConfirmList {
            background: #fff5f5;
            border-color: #f0c5c5;
            color: #8b1f2b;
        }

        .light-mode #deleteConfirmModal .btn-modal-secondary {
            background: #eef3f9;
            border-color: #cbd8e6;
            color: #2f4a68;
        }

        .light-mode #deleteConfirmModal .btn-modal-secondary:hover {
            background: #e1ebf7;
            color: #1f3a5a;
        }

        /* Light Mode Overrides */
        .light-mode .template-section {
            background: rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .light-mode .template-icon {
            color: #0077b6;
        }

        .light-mode .template-content h4 {
            color: #1a1a2e;
        }

        .light-mode .template-content p {
            color: #555;
        }

        .light-mode .template-columns {
            color: #444;
        }

        .light-mode .template-columns span {
            background: rgba(0, 0, 0, 0.04);
            border-left-color: #e6a700;
        }

        .light-mode .btn-download-template {
            background: linear-gradient(135deg, #0077b6 0%, #005f8a 100%);
        }

        /* Delete Section Light Mode */
        .delete-section {
            border: 1px solid rgba(255, 107, 107, 0.3);
            background: linear-gradient(135deg, #2a1a1a 0%, #3a1f1f 100%);
        }

        .light-mode .delete-section {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
            border: 1px solid rgba(255, 107, 107, 0.4);
        }

        .delete-section-inner {
            background: rgba(255, 107, 107, 0.1);
        }

        .light-mode .delete-section-inner {
            background: rgba(255, 107, 107, 0.08);
        }

        .delete-section-title {
            color: #fff;
        }

        .light-mode .delete-section-title {
            color: #1a1a2e;
        }

        .delete-section-subtitle {
            color: #a0a0a0;
        }

        .light-mode .delete-section-subtitle {
            color: #666;
        }

        /* Light Mode for Dataset Name Input */
        .light-mode .dataset-name-input {
            background: rgba(244, 208, 63, 0.08) !important;
            border-color: rgba(180, 150, 0, 0.3) !important;
        }

        .light-mode .dataset-name-input label {
            color: #b49600 !important;
        }

        .light-mode .dataset-name-input input {
            background: #fff !important;
            border-color: #d0d5dd !important;
            color: #1a1a2e !important;
        }

        .light-mode .dataset-name-input input::placeholder {
            color: #888 !important;
        }

        .light-mode .dataset-name-input p {
            color: #666 !important;
        }

        /* Delete Modal Animations */
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

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

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

        /* Styled scrollbar for modal */
        #deleteModal > div {
            scrollbar-width: thin;
            scrollbar-color: #f4d03f transparent;
        }

        #deleteModal > div::-webkit-scrollbar {
            width: 8px;
        }

        #deleteModal > div::-webkit-scrollbar-track {
            background: transparent;
        }

        #deleteModal > div::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #f4d03f 0%, #d4a93f 100%);
            border-radius: 4px;
            border: 1px solid rgba(244, 208, 63, 0.3);
            box-shadow: 0 0 6px rgba(244, 208, 63, 0.2);
        }

        #deleteModal > div::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #fff4c1 0%, #f4d03f 100%);
            box-shadow: 0 0 10px rgba(244, 208, 63, 0.4);
        }

        /* Styled scrollbar for datasets list */
        #deleteDatasetsList {
            scrollbar-width: thin;
            scrollbar-color: #f4d03f transparent;
        }

        #deleteDatasetsList::-webkit-scrollbar {
            width: 6px;
        }

        #deleteDatasetsList::-webkit-scrollbar-track {
            background: transparent;
        }

        #deleteDatasetsList::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #f4d03f 0%, #d4a93f 100%);
            border-radius: 3px;
            box-shadow: 0 0 4px rgba(244, 208, 63, 0.15);
        }

        #deleteDatasetsList::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #fff4c1 0%, #f4d03f 100%);
            box-shadow: 0 0 8px rgba(244, 208, 63, 0.3);
        }

        /* Shared Truck Loader (matches other pages) */
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
    <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.3/dist/dotlottie-wc.js" type="module"></script>
</head>
<body>
    <!-- Truck Loader (shared style) -->
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

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" onclick="if(event.target===this)closeDeleteModal()" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background: linear-gradient(135deg, rgba(0, 0, 0, 0.8) 0%, rgba(20, 30, 45, 0.7) 100%); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); justify-content:center; align-items:center; z-index:9999; padding: 20px;">
        <div style="background: linear-gradient(145deg, #253547 0%, #1a2638 50%, #1a2638 100%); border-radius:20px; padding:45px; max-width:540px; width:100%; border: 2px solid #f4d03f; box-shadow: 0 30px 100px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 0 40px rgba(244, 208, 63, 0.15); position: relative; animation: slideDown 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);">
            
            <!-- Background accent -->
            <div style="position: absolute; top: -50%; right: -50%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(244, 208, 63, 0.1) 0%, transparent 70%); border-radius: 50%; pointer-events: none; z-index: 0;"></div>
            
            <!-- Icon and Title -->
            <div style="display:flex; align-items:center; gap:16px; margin-bottom:28px; position: relative; z-index: 1;">
                <div style="width:60px; height:60px; border-radius:50%; background:linear-gradient(135deg, #ff6b6b 0%, #ff5252 50%, #ee5a52 100%); display:flex; justify-content:center; align-items:center; box-shadow: 0 8px 24px rgba(255, 107, 107, 0.3); animation: pulse 2s ease-in-out infinite;">
                    <i class="fas fa-trash-alt" style="font-size:28px; color:#fff; font-weight: 700;"></i>
                </div>
                <h2 style="font-size:26px; font-weight:800; color:#ffffff !important; margin:0; text-shadow: 0 2px 4px rgba(0,0,0,0.3); letter-spacing:0.5px;">Delete Dataset(s)?</h2>
            </div>
            
            <!-- Datasets list -->
            <div id="deleteDatasetsList" style="margin-bottom:28px; background: linear-gradient(135deg, rgba(244, 208, 63, 0.12) 0%, rgba(244, 208, 63, 0.06) 100%); border: 1px solid rgba(244, 208, 63, 0.2); border-radius: 12px; padding: 18px; max-height: 250px; overflow-y: auto; position: relative; z-index: 1;">
                <div style="text-align: center; color: #b8c5d6; font-size: 13px; font-weight: 500;" id="deleteDatasetsSummary">
                    <i class='fas fa-spinner fa-spin' style='margin-right: 8px;'></i>Loading datasets...
                </div>
            </div>
            
            <!-- Warning message -->
            <div style="background: linear-gradient(135deg, rgba(255, 107, 107, 0.15) 0%, rgba(255, 107, 107, 0.08) 100%); border: 1px solid rgba(255, 107, 107, 0.25); border-left: 3px solid #ff6b6b; border-radius: 12px; padding: 16px; margin-bottom: 28px; text-align: center; color: #ffaaaa; font-size: 13px; line-height: 1.6; position: relative; z-index: 1;">
                <div style='font-weight: 700; margin-bottom: 8px;'>
                    <i class='fas fa-triangle-exclamation' style='margin-right: 8px;'></i>This action cannot be undone
                </div>
                <div style='font-size: 12px; opacity: 0.9;'>All records in the selected dataset(s) will be permanently deleted from the system.</div>
            </div>
            
            <!-- Buttons -->
            <div style="display:flex; gap:14px; justify-content:center; position: relative; z-index: 1;">
                <button onclick="closeDeleteModal()" style="flex: 1; background: linear-gradient(135deg, rgba(100, 120, 150, 0.4) 0%, rgba(80, 100, 130, 0.3) 100%); color:#e0e0e0; padding:14px 24px; border: 2px solid rgba(200, 210, 230, 0.3); border-radius:10px; cursor:pointer; font-weight:700; font-size:15px; font-family:'Poppins',sans-serif; transition:all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); letter-spacing: 0.5px;" onmouseover="this.style.background='linear-gradient(135deg, rgba(120, 140, 170, 0.5) 0%, rgba(100, 120, 150, 0.4) 100%)'; this.style.borderColor='rgba(220, 230, 250, 0.5)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(0, 0, 0, 0.3)';" onmouseout="this.style.background='linear-gradient(135deg, rgba(100, 120, 150, 0.4) 0%, rgba(80, 100, 130, 0.3) 100%)'; this.style.borderColor='rgba(200, 210, 230, 0.3)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(0, 0, 0, 0.2)';">
                    <i class="fas fa-times" style="margin-right: 10px; font-weight: 700;"></i><span style="font-weight: 700;">Cancel</span>
                </button>
                <button id="confirmDeleteBtn" onclick="confirmDeleteSelected()" style="flex: 1; background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 50%, #ee5a52 100%); color:#fff; padding:14px 24px; border:none; border-radius:10px; cursor:pointer; font-weight:800; font-size:15px; font-family:'Poppins',sans-serif; transition:all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); text-shadow: 0 1px 2px rgba(0,0,0,0.2); letter-spacing: 0.5px; box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.2); position: relative; overflow: hidden;" onmouseover="this.style.transform='translateY(-3px) scale(1.02)'; this.style.boxShadow='0 10px 30px rgba(255, 107, 107, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.3)';" onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 6px 20px rgba(255, 107, 107, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.2)';">
                    <i class="fas fa-trash-alt" style="margin-right: 10px; font-weight: 700;"></i><span style="font-weight: 800;">Delete Permanently</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal-overlay" id="successModal">
        <div class="modal-content">
            <div class="modal-icon">
                <i class="fas fa-check"></i>
            </div>
            <h2 class="modal-title">Import Successful!</h2>
            <p class="modal-message" id="modalMessage">Your data has been imported successfully.</p>
            <div class="modal-stats">
                <div class="modal-stat">
                    <div class="modal-stat-value" id="modalImported">0</div>
                    <div class="modal-stat-label">Records Imported</div>
                </div>
                <div class="modal-stat">
                    <div class="modal-stat-value" id="modalFailed">0</div>
                    <div class="modal-stat-label">Failed</div>
                </div>
            </div>
            <div class="modal-buttons">
                <button class="btn-modal btn-modal-primary" onclick="goToDeliveryRecords(lastImportedDataset)">
                    <i class="fas fa-list"></i> View Records
                </button>
                <button class="btn-modal btn-modal-secondary" onclick="closeSuccessModal()">
                    <i class="fas fa-plus"></i> Import More
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div class="modal-overlay" id="deleteConfirmModal" onclick="if(event.target===this)closeDeleteConfirmModal()">
        <div class="modal-content" style="max-width: 520px;">
            <div class="modal-icon" style="background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 100%);">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <h2 class="modal-title">Confirm Delete</h2>
            <p class="modal-message" id="deleteConfirmMessage">
                You are about to permanently delete selected dataset(s).
            </p>
            <div id="deleteConfirmList"></div>
            <div class="modal-buttons">
                <button class="btn-modal btn-modal-secondary" id="deleteConfirmCancelBtn" onclick="closeDeleteConfirmModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn-modal btn-modal-primary" id="deleteConfirmProceedBtn" onclick="confirmDeleteFromModal()" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);">
                    <i class="fas fa-trash-alt"></i> Delete Permanently
                </button>
            </div>
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

            <!-- Center Title -->
            <div class="navbar-center">
                <h1 class="dashboard-title">Upload Data</h1>
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

                <!-- Sales Records -->
                <li class="menu-item">
                    <a href="sales-records.php" class="menu-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="menu-label">Sales Records</span>
                    </a>
                </li>

                <li class="menu-item">
                    <a href="inquiry.php" class="menu-link">
                        <i class="fas fa-file-invoice"></i>
                        <span class="menu-label">Inquiry</span>
                    </a>
                </li>

                <!-- Delivery Records -->
                <li class="menu-item">
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

                <!-- Upload Data (NEW) -->
                <li class="menu-item active">
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
        <div class="upload-container">
            <!-- Template Information -->
            <div class="upload-section">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Expected File Format
                </h2>
                <p class="section-subtitle">Ensure your Excel file matches the required format below</p>

                <div class="template-section">
                    <div class="template-info">
                        <div class="template-icon">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <div class="template-content">
                            <h4>📊 Excel File Requirements</h4>
                            <p>Your Excel file should contain the following columns (in any order). The system automatically maps common column names:</p>
                            <div class="template-columns">
                                <span><strong>Invoice No.</strong></span>
                                <span><strong>Date</strong></span>
                                <span><strong>Item</strong></span>
                                <span><strong>Description</strong></span>
                                <span><strong>Qty.</strong></span>
                                <span><strong>Serial No.</strong></span>
                                <span><strong>Date Delivered</strong></span>
                                <span><strong>Remarks</strong> (Optional)</span>
                            </div>
                            <p style="margin-top: 15px; font-style: italic;">
                                <i class="fas fa-lightbulb" style="color: #f4d03f;"></i>
                                The system also supports: Item_Code, Item_Name, Quantity, Status, Company_Name, <strong style="color: #f4d03f;">Category/By Color/Color/Type</strong> (for color groupings), etc.
                            </p>
                            <button class="btn-download-template" onclick="downloadTemplate()">
                                <i class="fas fa-download"></i> Download Template
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Messages -->
            <div id="alertContainer"></div>

            <!-- Upload Section -->
            <div class="upload-section">
                <h2 class="section-title">
                    <i class="fas fa-cloud-upload-alt"></i>
                    Upload Excel Files
                </h2>
                <p class="section-subtitle">
                    <i class="fas fa-info-circle" style="color: #f4d03f;"></i>
                    To keep data separate, import files and sheets ONE AT A TIME
                </p>

                <div class="upload-zone" id="uploadZone">
                    <div class="upload-icon">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <h3>Drag Excel files here</h3>
                    <p>or click to select from your computer</p>
                    <div class="upload-formats">
                        Supported formats: <strong>.xlsx, .xls, .csv</strong> (Max 20MB per file) | <strong>Import one file at a time</strong>
                    </div>
                    <input type="file" id="fileInput" accept=".xlsx,.xls,.csv" multiple />
                </div>

                <div class="files-list" id="filesList" style="margin-top: 20px; display: none;"></div>

                <!-- Sheet Selector (for multi-sheet Excel files) -->
                <div class="sheet-selector" id="sheetSelector" style="display: none; margin-top: 20px;">
                    <div id="sheetList" style="display: flex; flex-direction: column; gap: 10px;"></div>
                </div>

                <div class="upload-actions">
                    <button class="btn-upload" id="uploadBtn" onclick="parseAllFiles()" style="flex: 1; display: none;">
                        <i class="fas fa-cloud-upload-alt"></i> Upload Data
                    </button>
                    <button class="btn-cancel" id="cancelBtn" onclick="resetUpload()" style="flex: 1;">
                        <i class="fas fa-times"></i> Clear Files
                    </button>
                </div>
            </div>

            <!-- Inventory Upload Section -->
            <div class="upload-section">
                <h2 class="section-title">
                    <i class="fas fa-boxes"></i>
                    Import Inventory Datasets
                </h2>
                <p class="section-subtitle">
                    <i class="fas fa-lightbulb" style="color: #f4d03f;"></i>
                    Upload Excel files to bulk import or update inventory items
                </p>

                <!-- Template Info for Inventory -->
                <div class="template-section">
                    <div class="template-info">
                        <div class="template-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="template-content">
                            <h4>📦 Inventory File Format</h4>
                            <p>Your Excel file should contain the following columns. The system will automatically detect and map them:</p>
                            <div class="template-columns">
                                <span><strong>BOX</strong> (Optional box code)</span>
                                <span><strong>ITEMS</strong> (Required)</span>
                                <span><strong>DESCRIPTION</strong> (Item name)</span>
                                <span><strong>UOM</strong> (Unit of measure)</span>
                                <span><strong>INVENTORY</strong> (Stock quantity)</span>
                                <span><strong>STATUS</strong> (Optional)</span>
                            </div>
                            <p style="margin-top: 15px; font-style: italic;">
                                <i class="fas fa-lightbulb" style="color: #f4d03f;"></i>
                                The system automatically creates item codes from BOX and ITEMS or uses ITEMS alone if BOX is empty.
                            </p>
                            <button class="btn-download-template" onclick="downloadInventoryTemplate()">
                                <i class="fas fa-download"></i> Download Inventory Template
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Inventory Upload Zone -->
                <div class="upload-zone" id="inventoryUploadZone" style="margin-top: 20px;">
                    <div class="upload-icon">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <h3>Drag inventory Excel file here</h3>
                    <p>or click to select from your computer</p>
                    <div class="upload-formats">
                        Supported formats: <strong>.xlsx, .xls</strong> (Max 10MB) | Supports multiple items in one file
                    </div>
                    <input type="file" id="inventoryFileInput" accept=".xlsx,.xls" style="display: none;" onchange="handleInventoryFileSelect(event)" />
                </div>

                <!-- File Info Display -->
                <div class="file-info" id="inventoryFileInfo" style="margin-top: 20px;">
                    <div class="file-icon">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <div class="file-details">
                        <div class="file-name" id="inventoryFileName">Selected file</div>
                        <div class="file-size" id="inventoryFileSize">0 bytes</div>
                    </div>
                    <button class="file-remove" onclick="resetInventoryFileInput()">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>

                <!-- Status and Action Buttons -->
                <div class="upload-actions" style="margin-top: 25px;">
                    <button class="btn-upload" id="inventoryImportBtn" onclick="importInventoryFile()" style="display: none; flex: 1;">
                        <i class="fas fa-cloud-upload-alt"></i> Import Inventory
                    </button>
                    <button class="btn-cancel" onclick="resetInventoryFileInput()" style="flex: 1;">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>

                <!-- Upload Status -->
                <div id="inventoryUploadStatus" style="margin-top: 15px;"></div>
            </div>

            <!-- Orders Upload Section -->
            <div class="upload-section">
                <h2 class="section-title">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Import Orders Datasets
                </h2>
                <p class="section-subtitle">
                    <i class="fas fa-lightbulb" style="color: #f4d03f;"></i>
                    Upload Excel files to import directly to Orders only
                </p>

                <div class="template-section">
                    <div class="template-info">
                        <div class="template-icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="template-content">
                            <h4>Order File Format</h4>
                            <p>Use one row per order line. Common aliases are supported automatically.</p>
                            <div class="template-columns">
                                <span><strong>Customer</strong> or <strong>Order Customer</strong></span>
                                <span><strong>Order Date</strong> or <strong>Date</strong></span>
                                <span><strong>Item Code</strong> / <strong>Item</strong></span>
                                <span><strong>Item Name</strong> / <strong>Description</strong></span>
                                <span><strong>Quantity</strong> / <strong>Qty</strong></span>
                                <span><strong>Unit Price</strong> / <strong>Price</strong></span>
                                <span><strong>PO Number</strong> (Optional)</span>
                                <span><strong>PO Status</strong> (Optional)</span>
                            </div>
                            <p style="margin-top: 15px; font-style: italic;">
                                <i class="fas fa-info-circle" style="color: #f4d03f;"></i>
                                All imported rows are saved under company name <strong>Orders</strong>.
                            </p>
                            <button class="btn-download-template" onclick="downloadOrdersTemplate()">
                                <i class="fas fa-download"></i> Download Orders Template
                            </button>
                        </div>
                    </div>
                </div>

                <div class="upload-zone" id="ordersUploadZone" style="margin-top: 20px;">
                    <div class="upload-icon">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <h3>Drag orders Excel file here</h3>
                    <p>or click to select from your computer</p>
                    <div class="upload-formats">
                        Supported formats: <strong>.xlsx, .xls, .csv</strong> (Max 10MB)
                    </div>
                    <input type="file" id="ordersFileInput" accept=".xlsx,.xls,.csv" style="display: none;" onchange="handleOrdersFileSelect(event)" />
                </div>

                <div class="file-info" id="ordersFileInfo" style="margin-top: 20px;">
                    <div class="file-icon">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <div class="file-details">
                        <div class="file-name" id="ordersFileName">Selected file</div>
                        <div class="file-size" id="ordersFileSize">0 bytes</div>
                    </div>
                    <button class="file-remove" onclick="resetOrdersFileInput()">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>

                <div class="upload-actions" style="margin-top: 25px;">
                    <button class="btn-upload" id="ordersImportBtn" onclick="importOrdersFile()" style="display: none; flex: 1;">
                        <i class="fas fa-cloud-upload-alt"></i> Import Orders
                    </button>
                    <button class="btn-cancel" onclick="resetOrdersFileInput()" style="flex: 1;">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>

                <div id="ordersUploadStatus" style="margin-top: 15px;"></div>
            </div>

            <!-- Preview Section -->
            <div class="upload-section preview-section" id="previewSection">
                <h2 class="section-title">
                    <i class="fas fa-eye"></i>
                    Data Preview
                </h2>
                <p class="section-subtitle">Review the data before importing</p>
                <p class="section-subtitle">Review the data before importing</p>

                <div class="preview-info" id="previewInfo">
                    <div class="preview-stats">
                        <div class="stat">
                            <span class="stat-label">Total Rows:</span>
                            <span class="stat-value" id="totalRows">0</span>
                        </div>
                        <div class="stat">
                            <span class="stat-label">Columns:</span>
                            <span class="stat-value" id="totalColumns">0</span>
                        </div>
                        <div class="stat">
                            <span class="stat-label">Valid Records:</span>
                            <span class="stat-value" id="validRecords">0</span>
                        </div>
                    </div>
                </div>

                <!-- Dataset Name Input -->
                <div class="dataset-name-input" style="margin-top: 20px; padding: 15px; background: rgba(244, 208, 63, 0.1); border: 1px solid rgba(244, 208, 63, 0.3); border-radius: 10px;">
                    <label style="display: flex; align-items: center; gap: 10px; color: #f4d03f; font-weight: 600; font-size: 14px; margin-bottom: 10px;">
                        <i class="fas fa-tag"></i> Dataset Name
                    </label>
                    <input type="text" id="datasetNameInput" placeholder="Enter a name for this dataset (e.g., January Data, 2024 Sales)" 
                        style="width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.3); color: #fff; font-size: 14px; font-family: 'Poppins', sans-serif;">
                    <p style="color: #8a9ab5; font-size: 12px; margin-top: 8px; margin-bottom: 0;">
                        <i class="fas fa-info-circle"></i> This name will help identify this dataset. Leave blank to auto-generate.
                    </p>
                </div>

                <div class="table-container">
                    <table id="previewTable">
                        <thead id="tableHead"></thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>

                <!-- Chart Preview Section -->
                <div class="chart-preview-section" id="chartPreviewSection" style="display: none; margin-top: 30px;">
                    <h3 style="color: #f4d03f; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-chart-bar"></i> Data Visualization Preview
                    </h3>
                    <div class="charts-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
                        <!-- Summary Donut Charts -->
                        <div class="chart-card chart-expandable" onclick="openChartPreview('statusChart','Total Quantity by Status')" style="background: rgba(0,0,0,0.2); border-radius: 12px; padding: 20px; position:relative;">
                            <h4 style="color: #fff; margin-bottom: 15px; font-size: 14px;">Total Quantity by Status</h4>
                            <canvas id="statusChart" height="200"></canvas>
                            <span class="chart-expand-hint"><i class="fas fa-expand-alt"></i></span>
                        </div>
                        <div class="chart-card chart-expandable" onclick="openChartPreview('monthlyChart','Records by Month')" style="background: rgba(0,0,0,0.2); border-radius: 12px; padding: 20px; position:relative;">
                            <h4 style="color: #fff; margin-bottom: 15px; font-size: 14px;">Records by Month</h4>
                            <canvas id="monthlyChart" height="200"></canvas>
                            <span class="chart-expand-hint"><i class="fas fa-expand-alt"></i></span>
                        </div>
                        <div class="chart-card chart-expandable" onclick="openChartPreview('companyChart','Top Companies by Quantity')" style="background: rgba(0,0,0,0.2); border-radius: 12px; padding: 20px; position:relative;">
                            <h4 style="color: #fff; margin-bottom: 15px; font-size: 14px;">Top Companies by Quantity</h4>
                            <canvas id="companyChart" height="200"></canvas>
                            <span class="chart-expand-hint"><i class="fas fa-expand-alt"></i></span>
                        </div>
                        <div class="chart-card chart-expandable" onclick="openChartPreview('itemChart','Quantity by Item/Model')" style="background: rgba(0,0,0,0.2); border-radius: 12px; padding: 20px; position:relative;">
                            <h4 style="color: #fff; margin-bottom: 15px; font-size: 14px;">Quantity by Item/Model</h4>
                            <canvas id="itemChart" height="200"></canvas>
                            <span class="chart-expand-hint"><i class="fas fa-expand-alt"></i></span>
                        </div>
                    </div>
                </div>

                <div class="upload-actions">
                    <button class="btn-import" id="importBtn" onclick="doImport()" style="display: none; cursor: pointer; position: relative; z-index: 10;">
                        <i class="fas fa-upload"></i> Import Data
                    </button>
                    <button class="btn-cancel" onclick="resetUpload()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>

                <!-- Merge Options Section -->
                <div style="margin-top: 30px; padding: 20px; background: rgba(0, 217, 255, 0.1); border: 1px solid rgba(0, 217, 255, 0.3); border-radius: 10px; display: none;" id="mergeOptionsSection">
                    <label style="display: flex; align-items: center; gap: 10px; color: #00d9ff; font-weight: 600; font-size: 14px; margin-bottom: 15px;">
                        <i class="fas fa-code-branch"></i> Merge Options
                    </label>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: rgba(0, 217, 255, 0.05); border-radius: 8px; border: 1px solid rgba(0, 217, 255, 0.2); transition: all 0.3s ease;">
                            <input type="radio" name="mergeChoice" value="separate" checked style="cursor: pointer; width: 18px; height: 18px;">
                            <span style="flex: 1; color: #fff;">
                                <strong>Keep Separate</strong>
                                <div style="font-size: 12px; color: #8a9ab5; margin-top: 4px;">Store this as an independent dataset (e.g., "2018 TO NOW BW SALES RECORD")</div>
                            </span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: rgba(81, 207, 102, 0.05); border-radius: 8px; border: 1px solid rgba(81, 207, 102, 0.2); transition: all 0.3s ease;">
                            <input type="radio" name="mergeChoice" value="merge" style="cursor: pointer; width: 18px; height: 18px;">
                            <span style="flex: 1; color: #fff;">
                                <strong>Merge to ALL DATA</strong>
                                <div style="font-size: 12px; color: #8a9ab5; margin-top: 4px;">Combine with existing data - can be viewed together or separately as needed</div>
                            </span>
                        </label>
                    </div>
                    <p style="color: #7a8a9a; font-size: 12px; margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(0, 217, 255, 0.2); margin-bottom: 0;">
                        <i class="fas fa-info-circle"></i> You can change your choice any time from the Dashboard
                    </p>
                </div>
            </div>

            <!-- Delete All Data Section -->
            <div class="upload-section delete-section">
                <h2 class="section-title" style="color: #ff6b6b;">
                    <i class="fas fa-trash-alt"></i>
                    Manage Uploaded Data
                </h2>
                <p class="section-subtitle">Select and delete specific datasheets or datasets</p>

                <div class="delete-section-inner" style="display: flex; align-items: center; gap: 20px; padding: 20px; border-radius: 12px;">
                    <div style="flex: 1;">
                        <p class="delete-section-title" style="margin-bottom: 5px; font-weight: 600;">
                            <i class="fas fa-database" style="color: #ff6b6b; margin-right: 8px;"></i>
                            Total Records: <span id="currentRecordCount" style="color: #ff6b6b; font-size: 20px;"><?php echo number_format($totalRecords); ?></span>
                        </p>
                        <p class="delete-section-subtitle" style="font-size: 12px;">Choose which datasets to remove and start fresh</p>
                    </div>
                    <button id="deleteAllBtn" type="button" onclick="showDeleteModal()" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); color: #fff; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; font-family: 'Poppins', sans-serif; transition: all 0.3s ease; display: flex; align-items: center; gap: 10px; white-space: nowrap; position: relative; z-index: 10;">
                        <i class="fas fa-trash-alt"></i> Manage Data
                    </button>
                    <style>
                        button[onclick="showDeleteModal()"]:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
                        }
                        button[onclick="showDeleteModal()"]:active {
                            transform: translateY(0);
                        }
                    </style>
                </div>
            </div>
        </div>
    </main>

    <script src="js/app.js" defer></script>
    <!-- SheetJS XLSX library - local copy -->
    <script src="js/xlsx.min.js"></script>
    <!-- Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script>
        // Error handler to catch any issues
        window.addEventListener('error', function(e) {
            console.error('Global error:', e.error);
        });
        
        // Debug: Check if XLSX loaded
        console.log('XLSX library loaded:', typeof XLSX !== 'undefined');
        
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const filesList = document.getElementById('filesList');
        const previewSection = document.getElementById('previewSection');
        const importBtn = document.getElementById('importBtn');
        const alertContainer = document.getElementById('alertContainer');
        const sheetSelector = document.getElementById('sheetSelector');
        const sheetList = document.getElementById('sheetList');
        const chartPreviewSection = document.getElementById('chartPreviewSection');

        let selectedFiles = [];
        let parsedData = null;
        let allParsedData = [];
        let workbookSheets = {}; // Store workbook sheets for selection
        let previewCharts = {}; // Store chart instances
        let pendingDeleteDatasets = [];
        let dotAnimationInterval = null;
        let loaderStartTime = null;
        const LOADER_MIN_DISPLAY_TIME = 3000; // 3 seconds in milliseconds

        // Initialize database on page load
        window.addEventListener('load', () => {
            console.log('Page loaded. Import button:', importBtn);
            fetch('api/setup-db.php')
                .then(response => response.json())
                .catch(error => console.log('Database setup complete'));
        });

        // File Upload Handlers
        uploadZone.addEventListener('click', () => fileInput.click());

        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        function handleFiles(files) {
            if (files.length === 0) return;

            const maxSize = 20 * 1024 * 1024; // 20MB
            let validFiles = [];
            let errors = [];

            // Validate each file
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                if (!file.name.match(/\.(xlsx|xls|csv)$/i)) {
                    errors.push(`${file.name}: Invalid format`);
                    continue;
                }

                if (file.size > maxSize) {
                    errors.push(`${file.name}: File too large (max 20MB)`);
                    continue;
                }

                // Check if file already exists
                if (!selectedFiles.find(f => f.name === file.name)) {
                    validFiles.push(file);
                }
            }

            if (errors.length > 0) {
                showAlert('error', errors.join('<br>'));
            }

            if (validFiles.length > 0) {
                selectedFiles = [...selectedFiles, ...validFiles];
                updateFilesList();
                // Show upload button
                document.getElementById('uploadBtn').style.display = 'block';
            }
        }

        function updateFilesList() {
            if (selectedFiles.length === 0) {
                filesList.style.display = 'none';
                return;
            }

            filesList.style.display = 'block';
            let html = '<div style="margin-bottom: 10px; font-weight: 600; color: #f4d03f;"><i class="fas fa-files"></i> ' + selectedFiles.length + ' file(s) selected</div>';
            
            selectedFiles.forEach((file, index) => {
                html += `
                    <div class="file-info show" style="display: flex; margin-bottom: 10px;">
                        <div class="file-icon">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <div class="file-details">
                            <div class="file-name">${file.name}</div>
                            <div class="file-size">${(file.size / 1024 / 1024).toFixed(2)} MB</div>
                        </div>
                        <button class="file-remove" onclick="removeFile(${index})">Remove</button>
                    </div>
                `;
            });
            
            filesList.innerHTML = html;
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFilesList();
            if (selectedFiles.length === 0) {
                resetUpload();
            }
        }

        async function parseAllFiles() {
            if (selectedFiles.length === 0) {
                showAlert('error', 'No files selected.');
                return;
            }
            
            showAlert('info', `Processing ${selectedFiles.length} file(s)... Please wait.`);
            
            allParsedData = [];
            workbookSheets = {};
            
            // Check if any file has multiple sheets
            let hasMultiSheetFile = false;
            let allFilesInfo = [];
            
            for (const file of selectedFiles) {
                try {
                    const result = await parseFileWithSheets(file);
                    if (result.hasMultipleSheets) {
                        hasMultiSheetFile = true;
                        workbookSheets[file.name] = result.sheets;
                        allFilesInfo.push({ 
                            fileName: file.name, 
                            type: 'multi', 
                            sheets: result.sheets,
                            sheetNames: Object.keys(result.sheets)
                        });
                    } else {
                        // Single sheet file
                        allFilesInfo.push({
                            fileName: file.name,
                            type: 'single',
                            data: result.data,
                            rowCount: result.data.length
                        });
                    }
                } catch (error) {
                    showAlert('error', `Error parsing ${file.name}: ${error.message}`);
                    return;
                }
            }
            
            // Show combined file/sheet selector for all files
            showMultiFileSelector(allFilesInfo);
        }

        function parseFileWithSheets(file) {
            return new Promise((resolve, reject) => {
                if (file.name.endsWith('.csv')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            const data = e.target.result;
                            const rows = parseCSV(data);
                            resolve({ hasMultipleSheets: false, data: rows });
                        } catch (error) {
                            reject(error);
                        }
                    };
                    reader.onerror = reject;
                    reader.readAsText(file);
                } else {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            const data = e.target.result;
                            const workbook = XLSX.read(data, { type: 'binary', cellDates: true, cellStyles: true });
                            
                            // Check for multiple sheets
                            if (workbook.SheetNames.length > 1) {
                                const sheets = {};
                                workbook.SheetNames.forEach(sheetName => {
                                    const worksheet = workbook.Sheets[sheetName];
                                    const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1, raw: false });
                                    const rowCount = jsonData.length > 0 ? jsonData.length - 1 : 0;
                                    sheets[sheetName] = {
                                        worksheet: worksheet,
                                        rowCount: rowCount,
                                        workbook: workbook
                                    };
                                });
                                resolve({ hasMultipleSheets: true, sheets: sheets, workbook: workbook });
                            } else {
                                // Single sheet - parse directly
                                const firstSheet = workbook.SheetNames[0];
                                const rows = parseWorksheet(workbook.Sheets[firstSheet]);
                                resolve({ hasMultipleSheets: false, data: rows });
                            }
                        } catch (error) {
                            reject(error);
                        }
                    };
                    reader.onerror = reject;
                    reader.readAsBinaryString(file);
                }
            });
        }

        function parseWorksheet(worksheet) {
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1, raw: false });
            if (jsonData.length < 2) return [];
            
            const headers = jsonData[0].map(h => String(h || '').trim()).filter(h => h !== '');
            if (headers.length === 0) return [];

            const range = XLSX.utils.decode_range(worksheet['!ref'] || 'A1:A1');

            function normalizeExcelColor(rawColor) {
                if (!rawColor) return '';

                const indexedPalette = {
                    0: '#000000', 1: '#FFFFFF', 2: '#FF0000', 3: '#00FF00', 4: '#0000FF', 5: '#FFFF00', 6: '#FF00FF', 7: '#00FFFF',
                    8: '#000000', 9: '#FFFFFF', 10: '#FF0000', 11: '#00FF00', 12: '#0000FF', 13: '#FFFF00', 14: '#FF00FF', 15: '#00FFFF',
                    16: '#800000', 17: '#008000', 18: '#000080', 19: '#808000', 20: '#800080', 21: '#008080', 22: '#C0C0C0', 23: '#808080',
                    24: '#9999FF', 25: '#993366', 26: '#FFFFCC', 27: '#CCFFFF', 28: '#660066', 29: '#FF8080', 30: '#0066CC', 31: '#CCCCFF',
                    32: '#000080', 33: '#FF00FF', 34: '#FFFF00', 35: '#00FFFF', 36: '#800080', 37: '#800000', 38: '#008080', 39: '#0000FF',
                    40: '#00CCFF', 41: '#CCFFFF', 42: '#CCFFCC', 43: '#FFFF99', 44: '#99CCFF', 45: '#FF99CC', 46: '#CC99FF', 47: '#FFCC99',
                    48: '#3366FF', 49: '#33CCCC', 50: '#99CC00', 51: '#FFCC00', 52: '#FF9900', 53: '#FF6600', 54: '#666699', 55: '#969696',
                    56: '#003366', 57: '#339966', 58: '#003300', 59: '#333300', 60: '#993300', 61: '#993366', 62: '#333399', 63: '#333333'
                };

                const themePalette = {
                    0: '#FFFFFF', 1: '#000000', 2: '#EEECE1', 3: '#1F497D', 4: '#4F81BD',
                    5: '#C0504D', 6: '#9BBB59', 7: '#8064A2', 8: '#4BACC6', 9: '#F79646'
                };

                const clampByte = (n) => Math.max(0, Math.min(255, Math.round(n)));

                const applyTint = (hexColor, tint) => {
                    const hex = String(hexColor || '').replace('#', '');
                    if (!/^[0-9A-Fa-f]{6}$/.test(hex) || typeof tint !== 'number' || Number.isNaN(tint)) {
                        return hexColor;
                    }
                    const r = parseInt(hex.slice(0, 2), 16);
                    const g = parseInt(hex.slice(2, 4), 16);
                    const b = parseInt(hex.slice(4, 6), 16);

                    const shade = (channel) => {
                        if (tint < 0) return clampByte(channel * (1 + tint));
                        return clampByte(channel * (1 - tint) + (255 * tint));
                    };

                    const nr = shade(r).toString(16).padStart(2, '0');
                    const ng = shade(g).toString(16).padStart(2, '0');
                    const nb = shade(b).toString(16).padStart(2, '0');
                    return `#${(nr + ng + nb).toUpperCase()}`;
                };

                if (typeof rawColor === 'object') {
                    if (rawColor.rgb || rawColor.argb) {
                        let color = String(rawColor.rgb || rawColor.argb).trim();
                        if (color.startsWith('#')) color = color.slice(1);
                        if (color.length === 8 && color.toUpperCase().startsWith('FF')) color = color.slice(2);
                        if (/^[0-9A-Fa-f]{6}$/.test(color)) {
                            const base = `#${color.toUpperCase()}`;
                            return applyTint(base, Number(rawColor.tint));
                        }
                    }

                    if (typeof rawColor.indexed !== 'undefined' && indexedPalette.hasOwnProperty(rawColor.indexed)) {
                        const base = indexedPalette[rawColor.indexed];
                        return applyTint(base, Number(rawColor.tint));
                    }

                    if (typeof rawColor.theme !== 'undefined' && themePalette.hasOwnProperty(rawColor.theme)) {
                        const base = themePalette[rawColor.theme];
                        return applyTint(base, Number(rawColor.tint));
                    }
                }

                let color = String(rawColor).trim();
                if (color.startsWith('#')) color = color.slice(1);
                if (color.length === 8 && color.toUpperCase().startsWith('FF')) {
                    color = color.slice(2);
                }
                if (/^[0-9A-Fa-f]{6}$/.test(color)) {
                    return `#${color.toUpperCase()}`;
                }

                return '';
            }

            function isNeutralColor(color) {
                const normalized = String(color || '').toUpperCase();
                return normalized === '#000000' || normalized === '#FFFFFF';
            }

            function extractCellStyle(cell) {
                if (!cell || !cell.s) return null;

                const fillColor = normalizeExcelColor(cell.s.fill?.fgColor || cell.s.fill?.bgColor);
                const fontColor = normalizeExcelColor(cell.s.font?.color);

                const style = {};
                if (fillColor && !isNeutralColor(fillColor)) {
                    style.bg = fillColor;
                }
                if (fontColor && !isNeutralColor(fontColor)) {
                    style.text = fontColor;
                }

                if (Object.keys(style).length > 0) {
                    return style;
                }

                // Keep non-empty fill as fallback if sheet intentionally uses neutral fill markers.
                if (fillColor) {
                    return { bg: fillColor };
                }

                return null;
            }

            function extractCellColor(cell) {
                const style = extractCellStyle(cell);
                if (!style) return '';
                return style.bg || style.text || '';
            }

            function extractCellFillColor(cell) {
                if (!cell || !cell.s) return '';
                return normalizeExcelColor(cell.s.fill?.fgColor || cell.s.fill?.bgColor);
            }

            function getRowHighlightColor(rowNumber) {
                const colorCounts = {};

                for (let col = range.s.c; col <= range.e.c; col++) {
                    const cellRef = XLSX.utils.encode_cell({ r: rowNumber, c: col });
                    const cell = worksheet[cellRef];
                    const fillColor = extractCellFillColor(cell);
                    if (fillColor) {
                        colorCounts[fillColor] = (colorCounts[fillColor] || 0) + 1;
                    }
                }

                let topColor = '';
                let topCount = 0;
                Object.entries(colorCounts).forEach(([color, count]) => {
                    if (count > topCount) {
                        topColor = color;
                        topCount = count;
                    }
                });

                return topColor;
            }
            
            const rows = [];
            for (let i = 1; i < jsonData.length; i++) {
                const rowData = jsonData[i];
                if (!rowData || rowData.length === 0) continue;
                
                const row = {};
                const cellStyles = {};
                const sheetRowNumber = range.s.r + i;

                headers.forEach((header, index) => {
                    let value = rowData[index];
                    if (value instanceof Date) {
                        value = value.toISOString().split('T')[0];
                    }
                    row[header] = value !== undefined ? value : '';

                    const cellRef = XLSX.utils.encode_cell({ r: sheetRowNumber, c: index });
                    const cell = worksheet[cellRef];
                    const cellStyle = extractCellStyle(cell);
                    if (cellStyle) {
                        cellStyles[header] = cellStyle;
                    }
                });

                const rowHighlightColor = getRowHighlightColor(sheetRowNumber);
                if (Object.keys(cellStyles).length > 0) {
                    row.cell_styles = cellStyles;
                }
                if (rowHighlightColor) {
                    row.highlight_color = rowHighlightColor;
                }
                
                if (Object.values(row).some(v => v !== '')) {
                    rows.push(row);
                }
            }
            return rows;
        }

        function showSheetSelector(fileName, sheets) {
            sheetSelector.style.display = 'block';
            const sheetNames = Object.keys(sheets);

            let html = `
                <div style="background:#1b2838; border-radius:12px; padding:20px; border:1px solid rgba(255,255,255,0.12);">
                    <div style="background:rgba(244,208,63,0.1); border:1px solid rgba(244,208,63,0.3); border-radius:8px; padding:14px; margin-bottom:16px;">
                        <p style="color:#f4d03f; margin:0 0 6px; font-weight:700; font-size:13px;"><i class="fas fa-layer-group"></i> Select Sheets to Import</p>
                        <p style="color:#9ab0c4; margin:0; font-size:12px;">File "<strong style="color:#e0e0e0;">${fileName}</strong>" has ${sheetNames.length} sheets. Select up to <strong style="color:#f4d03f;">5 sheets</strong> — each will be imported separately, data will NOT be merged.</p>
                    </div>
                    <p style="color:#7a8a9a; margin-bottom:10px; font-size:12px; font-weight:600;"><i class="fas fa-check-square"></i> Available Sheets &nbsp;·&nbsp; <span id="sheetSelectCount" style="color:#f4d03f;">0 / 5 selected</span></p>
                    <div id="sheetCheckList">
            `;

            sheetNames.forEach((sheetName) => {
                const info = sheets[sheetName];
                html += `
                    <div class="sheet-item" style="background:rgba(255,255,255,0.04); border:2px solid rgba(255,255,255,0.08); border-radius:8px; margin-bottom:10px; transition:all 0.2s ease; overflow:hidden;">
                        <label class="sheet-radio" style="display:flex; align-items:center; gap:10px; padding:11px 15px; cursor:pointer;">
                            <input type="checkbox" class="sheet-check" data-file="${fileName}" data-sheet="${sheetName}" onchange="updateSelectedSheets(this)" style="width:16px; height:16px; cursor:pointer; accent-color:#f4d03f; flex-shrink:0;">
                            <span style="color:#dce8f0; font-weight:500; flex:1; font-size:13px;">${sheetName}</span>
                            <span style="color:#607080; font-size:12px; white-space:nowrap;">${info.rowCount} rows</span>
                        </label>
                        <div class="sheet-name-edit" style="display:none; padding:0 15px 12px 41px;">
                            <label style="font-size:11px; color:#8a9ab5; display:block; margin-bottom:5px;"><i class="fas fa-tag"></i> Dataset Name (optional):</label>
                            <input type="text" class="custom-dataset-name" data-sheet="${sheetName}" value="${sheetName}" placeholder="Custom name..." 
                                style="width:100%; padding:8px 12px; border-radius:6px; border:1px solid rgba(255,255,255,0.15); background:rgba(0,0,0,0.3); color:#fff; font-size:13px; font-family:'Poppins',sans-serif;">
                        </div>
                    </div>
                `;
            });

            html += `
                    </div>
                    <div style="margin-top:18px; display:flex; gap:10px;">
                        <button id="importSheetsBtn" onclick="parseSelectedSheets()" style="flex:1; background:linear-gradient(135deg,#f4d03f 0%,#f9d76a 100%); color:#1a3a5c; border:none; padding:12px 20px; border-radius:8px; cursor:pointer; font-weight:700; font-size:13px; font-family:'Poppins',sans-serif; display:flex; align-items:center; justify-content:center; gap:8px;">
                            <i class="fas fa-file-import"></i> Import Selected Sheets
                        </button>
                        <button onclick="resetUpload()" style="background:rgba(255,255,255,0.07); color:#8a9ab0; border:1px solid rgba(255,255,255,0.12); padding:12px 20px; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px; font-family:'Poppins',sans-serif;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            `;
            sheetList.innerHTML = html;
        }

        // Store parsed file info for multi-file import
        let allFilesInfoStore = [];

        function showMultiFileSelector(allFilesInfo) {
            allFilesInfoStore = allFilesInfo;
            sheetSelector.style.display = 'block';
            
            let totalItems = 0;
            allFilesInfo.forEach(f => {
                if (f.type === 'single') totalItems++;
                else totalItems += f.sheetNames.length;
            });
            
            let html = `
                <div style="background:#1b2838; border-radius:12px; padding:20px; border:1px solid rgba(255,255,255,0.12);">
                    <div style="background:rgba(244,208,63,0.1); border:1px solid rgba(244,208,63,0.3); border-radius:8px; padding:14px; margin-bottom:16px;">
                        <p style="color:#f4d03f; margin:0 0 6px; font-weight:700; font-size:13px;"><i class="fas fa-layer-group"></i> Select Data to Import</p>
                        <p style="color:#9ab0c4; margin:0; font-size:12px;">You have <strong style="color:#e0e0e0;">${allFilesInfo.length} file(s)</strong>. Each selected item will be imported as a <strong style="color:#f4d03f;">SEPARATE dataset</strong>.</p>
                    </div>
                    <p style="color:#7a8a9a; margin-bottom:10px; font-size:12px; font-weight:600; display:flex; align-items:center; gap:10px;"><i class="fas fa-check-square"></i> Select datasets to import &nbsp;·&nbsp; <span id="sheetSelectCount" style="color:#f4d03f;">0 selected</span> <button onclick="toggleAllSheets(false)" style="margin-left:auto; padding:3px 10px; border-radius:5px; border:1px solid rgba(255,100,100,0.4); background:rgba(255,100,100,0.1); color:#ff8080; font-size:11px; cursor:pointer; font-family:inherit;">Deselect All</button> <button onclick="toggleAllSheets(true)" style="padding:3px 10px; border-radius:5px; border:1px solid rgba(244,208,63,0.4); background:rgba(244,208,63,0.1); color:#f4d03f; font-size:11px; cursor:pointer; font-family:inherit;">Select All</button></p>
                    <div id="sheetCheckList">
            `;
            
            allFilesInfo.forEach((fileInfo, fileIdx) => {
                if (fileInfo.type === 'single') {
                    // Single sheet file - show as one item
                    const baseName = fileInfo.fileName.replace(/\.(xlsx|xls|csv)$/i, '');
                    html += `
                        <div class="sheet-item" style="background:rgba(255,255,255,0.04); border:2px solid rgba(255,255,255,0.08); border-radius:8px; margin-bottom:10px; transition:all 0.2s ease; overflow:hidden;">
                            <label class="sheet-radio" style="display:flex; align-items:center; gap:10px; padding:11px 15px; cursor:pointer;">
                                <input type="checkbox" class="sheet-check" data-file="${fileInfo.fileName}" data-type="single" data-idx="${fileIdx}" onchange="updateMultiSelect()" style="width:16px; height:16px; cursor:pointer; accent-color:#f4d03f; flex-shrink:0;" checked>
                                <i class="fas fa-file-excel" style="color:#27ae60;"></i>
                                <span style="color:#dce8f0; font-weight:500; flex:1; font-size:13px;">${fileInfo.fileName}</span>
                                <span style="color:#607080; font-size:12px; white-space:nowrap;">${fileInfo.rowCount} rows</span>
                            </label>
                            <div class="sheet-name-edit" style="display:block; padding:0 15px 12px 41px;">
                                <label style="font-size:11px; color:#8a9ab5; display:block; margin-bottom:5px;"><i class="fas fa-tag"></i> Dataset Name:</label>
                                <input type="text" class="custom-dataset-name" data-file="${fileInfo.fileName}" data-type="single" value="${baseName}" placeholder="Dataset name..." 
                                    style="width:100%; padding:8px 12px; border-radius:6px; border:1px solid rgba(255,255,255,0.15); background:rgba(0,0,0,0.3); color:#fff; font-size:13px; font-family:'Poppins',sans-serif;">
                            </div>
                        </div>
                    `;
                } else {
                    // Multi-sheet file - show file header then sheets
                    html += `
                        <div style="margin-bottom:10px;">
                            <div style="background:rgba(0,217,255,0.1); padding:10px 15px; border-radius:8px 8px 0 0; border:1px solid rgba(0,217,255,0.2); border-bottom:none; display:flex; align-items:center; gap:10px;">
                                <i class="fas fa-file-excel" style="color:#00d9ff;"></i>
                                <span style="color:#00d9ff; font-weight:600; font-size:13px;">${fileInfo.fileName}</span>
                                <span style="color:#607080; font-size:11px;">(${fileInfo.sheetNames.length} sheets)</span>
                            </div>
                    `;
                    
                    fileInfo.sheetNames.forEach((sheetName, sheetIdx) => {
                        const sheetInfo = fileInfo.sheets[sheetName];
                        const isLast = sheetIdx === fileInfo.sheetNames.length - 1;
                        html += `
                            <div class="sheet-item" style="background:rgba(255,255,255,0.04); border:2px solid rgba(255,255,255,0.08); ${isLast ? 'border-radius:0 0 8px 8px;' : ''} margin-bottom:0; border-top:none; transition:all 0.2s ease; overflow:hidden;">
                                <label class="sheet-radio" style="display:flex; align-items:center; gap:10px; padding:11px 15px 11px 30px; cursor:pointer;">
                                    <input type="checkbox" class="sheet-check" data-file="${fileInfo.fileName}" data-sheet="${sheetName}" data-type="sheet" data-idx="${fileIdx}" onchange="updateMultiSelect()" style="width:16px; height:16px; cursor:pointer; accent-color:#f4d03f; flex-shrink:0;" checked>
                                    <i class="fas fa-table" style="color:#8a9ab5; font-size:12px;"></i>
                                    <span style="color:#dce8f0; font-weight:500; flex:1; font-size:13px;">${sheetName}</span>
                                    <span style="color:#607080; font-size:12px; white-space:nowrap;">${sheetInfo.rowCount} rows</span>
                                </label>
                                <div class="sheet-name-edit" style="display:block; padding:0 15px 12px 56px;">
                                    <label style="font-size:11px; color:#8a9ab5; display:block; margin-bottom:5px;"><i class="fas fa-tag"></i> Dataset Name:</label>
                                    <input type="text" class="custom-dataset-name" data-file="${fileInfo.fileName}" data-sheet="${sheetName}" data-type="sheet" value="${sheetName}" placeholder="Dataset name..." 
                                        style="width:100%; padding:8px 12px; border-radius:6px; border:1px solid rgba(255,255,255,0.15); background:rgba(0,0,0,0.3); color:#fff; font-size:13px; font-family:'Poppins',sans-serif;">
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `</div>`;
                }
            });
            
            html += `
                    </div>
                    <div style="margin-top:18px; display:flex; gap:10px;">
                        <button id="importSheetsBtn" onclick="importMultipleDatasets()" style="flex:1; background:linear-gradient(135deg,#f4d03f 0%,#f9d76a 100%); color:#1a3a5c; border:none; padding:12px 20px; border-radius:8px; cursor:pointer; font-weight:700; font-size:13px; font-family:'Poppins',sans-serif; display:flex; align-items:center; justify-content:center; gap:8px;">
                            <i class="fas fa-file-import"></i> Import Selected as Separate Datasets
                        </button>
                        <button onclick="resetUpload()" style="background:rgba(255,255,255,0.07); color:#8a9ab0; border:1px solid rgba(255,255,255,0.12); padding:12px 20px; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px; font-family:'Poppins',sans-serif;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            `;
            sheetList.innerHTML = html;
            updateMultiSelect();
        }

        function toggleAllSheets(select) {
            document.querySelectorAll('.sheet-check').forEach(cb => cb.checked = select);
            updateMultiSelect();
        }

        function updateMultiSelect() {
            const checked = document.querySelectorAll('.sheet-check:checked').length;
            const countEl = document.getElementById('sheetSelectCount');
            if (countEl) countEl.textContent = checked + ' selected';
            
            // Update visual state of items
            document.querySelectorAll('.sheet-item').forEach(item => {
                const input = item.querySelector('.sheet-check');
                const nameEdit = item.querySelector('.sheet-name-edit');
                if (input && input.checked) {
                    item.style.background = 'rgba(244,208,63,0.13)';
                    item.style.borderColor = '#f4d03f';
                    if (nameEdit) nameEdit.style.display = 'block';
                } else {
                    item.style.background = 'rgba(255,255,255,0.04)';
                    item.style.borderColor = 'rgba(255,255,255,0.08)';
                    if (nameEdit) nameEdit.style.display = 'none';
                }
            });
        }

        async function importMultipleDatasets() {
            const checkedBoxes = Array.from(document.querySelectorAll('.sheet-check:checked'));
            if (checkedBoxes.length === 0) {
                showAlert('error', 'Please select at least one item to import.');
                return;
            }

            const btn = document.getElementById('importSheetsBtn');
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Importing...'; }

            // Always get next available dataset number from the server
            let nextNum = 1;
            try {
                const dsRes = await fetch('api/get-datasets.php');
                const dsData = await dsRes.json();
                if (dsData.success) nextNum = dsData.next_num;
            } catch (e) { /* use default 1 */ }

            let totalImported = 0;
            let totalFailed = 0;
            const importedDatasets = [];

            for (let i = 0; i < checkedBoxes.length; i++) {
                const checkbox = checkedBoxes[i];
                const fileName = checkbox.dataset.file;
                const itemType = checkbox.dataset.type;
                const fileIdx = parseInt(checkbox.dataset.idx);
                
                // Get the custom dataset name from the input field
                const itemDiv = checkbox.closest('.sheet-item');
                const customNameInput = itemDiv ? itemDiv.querySelector('.custom-dataset-name') : null;
                const datasetName = customNameInput && customNameInput.value.trim() ? customNameInput.value.trim() : ('data' + (nextNum + i));
                
                let rows = [];
                
                if (itemType === 'single') {
                    const fileInfo = allFilesInfoStore[fileIdx];
                    rows = fileInfo.data;
                } else {
                    const sheetName = checkbox.dataset.sheet;
                    if (workbookSheets[fileName] && workbookSheets[fileName][sheetName]) {
                        rows = parseWorksheet(workbookSheets[fileName][sheetName].worksheet);
                    }
                }

                if (rows.length === 0) {
                    showAlert('warning', `Item ${i + 1} has no data, skipping.`);
                    continue;
                }

                showAlert('info', `Importing ${i + 1} of ${checkedBoxes.length} as "${datasetName}"...`);

                try {
                    const response = await fetch('api/import-data.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            data: rows,
                            fileName: fileName,
                            dataset_name: datasetName,
                            timestamp: new Date().toISOString()
                        })
                    });
                    const result = await response.json();
                    if (result.success) {
                        totalImported += result.imported || rows.length;
                        totalFailed += result.failed || 0;
                        importedDatasets.push(datasetName);
                    } else {
                        showAlert('error', `Error importing item ${i + 1}: ${result.message}`);
                        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-import"></i> Import Selected as Separate Datasets'; }
                        return;
                    }
                } catch (err) {
                    showAlert('error', `Failed to import item ${i + 1}: ${err.message}`);
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-import"></i> Import Selected as Separate Datasets'; }
                    return;
                }
            }

            const firstDataset = importedDatasets[0] || null;
            showSuccessModal(totalImported, totalFailed, importedDatasets.join(', '), firstDataset);
        }

        function updateSelectedSheets(checkbox) {
            // Enforce max 5 selections
            const checked = document.querySelectorAll('.sheet-check:checked');
            if (checked.length > 5) {
                checkbox.checked = false;
            }
            const actualCount = document.querySelectorAll('.sheet-check:checked').length;
            const countEl = document.getElementById('sheetSelectCount');
            if (countEl) countEl.textContent = actualCount + ' / 5 selected';

            // Update styles and show/hide name edit
            document.querySelectorAll('.sheet-item').forEach(item => {
                const input = item.querySelector('.sheet-check');
                const nameEdit = item.querySelector('.sheet-name-edit');
                if (input && input.checked) {
                    item.style.background = 'rgba(244,208,63,0.13)';
                    item.style.borderColor = '#f4d03f';
                    if (nameEdit) nameEdit.style.display = 'block';
                } else {
                    item.style.background = 'rgba(255,255,255,0.04)';
                    item.style.borderColor = 'rgba(255,255,255,0.08)';
                    if (nameEdit) nameEdit.style.display = 'none';
                }
            });
        }

        async function parseSelectedSheets() {
            const checkedBoxes = Array.from(document.querySelectorAll('.sheet-check:checked'));
            if (checkedBoxes.length === 0) {
                showAlert('error', 'Please select at least one sheet to import.');
                return;
            }

            const btn = document.getElementById('importSheetsBtn');
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Importing...'; }

            // Fetch the next available dataset number from the server
            let nextNum = 1;
            try {
                const dsRes = await fetch('api/get-datasets.php');
                const dsData = await dsRes.json();
                if (dsData.success) nextNum = dsData.next_num;
            } catch (e) { /* use default 1 */ }

            let totalImported = 0;
            let totalFailed = 0;
            let sheetsOk = 0;
            const importedDatasets = [];

            for (let i = 0; i < checkedBoxes.length; i++) {
                const checkbox = checkedBoxes[i];
                const fileName   = checkbox.dataset.file;
                const sheetName  = checkbox.dataset.sheet;
                
                // Get the custom dataset name from the input field
                const itemDiv = checkbox.closest('.sheet-item');
                const customNameInput = itemDiv ? itemDiv.querySelector('.custom-dataset-name') : null;
                const datasetName = customNameInput && customNameInput.value.trim() ? customNameInput.value.trim() : ('data' + (nextNum + i));

                showAlert('info', `Importing sheet ${i + 1} of ${checkedBoxes.length}: "${sheetName}" → ${datasetName}...`);

                let rows = [];
                if (workbookSheets[fileName] && workbookSheets[fileName][sheetName]) {
                    rows = parseWorksheet(workbookSheets[fileName][sheetName].worksheet);
                }

                if (rows.length === 0) {
                    showAlert('warning', `Sheet "${sheetName}" has no data, skipping.`);
                    continue;
                }

                try {
                    const response = await fetch('api/import-data.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            data: rows,
                            fileName: sheetName,
                            dataset_name: datasetName,
                            timestamp: new Date().toISOString()
                        })
                    });
                    const result = await response.json();
                    if (result.success) {
                        totalImported += result.imported || rows.length;
                        totalFailed   += result.failed  || 0;
                        sheetsOk++;
                        importedDatasets.push(datasetName);
                    } else {
                        showAlert('error', `Error importing "${sheetName}": ${result.message}`);
                        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-import"></i> Import Selected Sheets'; }
                        return;
                    }
                } catch (err) {
                    showAlert('error', `Failed to import "${sheetName}": ${err.message}`);
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-import"></i> Import Selected Sheets'; }
                    return;
                }
            }

            const firstDataset = importedDatasets[0] || null;
            showSuccessModal(totalImported, totalFailed, importedDatasets.join(', '), firstDataset);
        }

        function generatePreviewCharts(data) {
            if (!data || data.length === 0) return;
            
            // Destroy existing charts
            Object.values(previewCharts).forEach(chart => {
                if (chart) chart.destroy();
            });
            previewCharts = {};
            
            chartPreviewSection.style.display = 'block';
            
            // Analyze data for chart generation
            const headers = Object.keys(data[0]);
            
            // Find relevant columns
            const monthCol = headers.find(h => h.toLowerCase().includes('month') || h.toLowerCase().includes('delivery month'));
            const companyCol = headers.find(h => h.toLowerCase().includes('company') || h.toLowerCase().includes('sold to') || h.toLowerCase().includes('client'));
            const qtyCol = headers.find(h => h.toLowerCase().includes('qty') || h.toLowerCase().includes('quantity'));
            const itemCol = headers.find(h => h.toLowerCase().includes('item') || h.toLowerCase().includes('description') || h.toLowerCase().includes('model'));
            const statusCol = headers.find(h => h.toLowerCase().includes('status'));
            
            // 1. Monthly Distribution Chart
            if (monthCol) {
                const monthData = {};
                data.forEach(row => {
                    const month = row[monthCol] || 'Unknown';
                    const qty = parseInt(row[qtyCol]) || 1;
                    monthData[month] = (monthData[month] || 0) + qty;
                });
                
                const ctx = document.getElementById('monthlyChart').getContext('2d');
                previewCharts.monthly = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(monthData),
                        datasets: [{
                            label: 'Quantity per Month',
                            data: Object.values(monthData),
                            backgroundColor: ['#f39c12', '#3498db', '#e74c3c', '#2ecc71', '#9b59b6', '#1abc9c', '#34495e', '#f1c40f', '#e67e22', '#95a5a6', '#d35400', '#c0392b']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { color: '#a0a0a0' }, grid: { color: 'rgba(255,255,255,0.1)' } },
                            x: { ticks: { color: '#a0a0a0' }, grid: { display: false } }
                        }
                    }
                });
            }
            
            // 2. Top Companies Chart
            if (companyCol) {
                const companyData = {};
                data.forEach(row => {
                    const company = row[companyCol] || 'Unknown';
                    const qty = parseInt(row[qtyCol]) || 1;
                    companyData[company] = (companyData[company] || 0) + qty;
                });
                
                // Sort and get top 10
                const sorted = Object.entries(companyData).sort((a, b) => b[1] - a[1]).slice(0, 10);
                
                const ctx = document.getElementById('companyChart').getContext('2d');
                previewCharts.company = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: sorted.map(s => s[0].substring(0, 25)),
                        datasets: [{
                            label: 'Quantity',
                            data: sorted.map(s => s[1]),
                            backgroundColor: '#3498db'
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { beginAtZero: true, ticks: { color: '#a0a0a0' }, grid: { color: 'rgba(255,255,255,0.1)' } },
                            y: { ticks: { color: '#a0a0a0', font: { size: 10 } }, grid: { display: false } }
                        }
                    }
                });
            }
            
            // 3. Items/Models Chart
            if (itemCol) {
                const itemData = {};
                data.forEach(row => {
                    const item = row[itemCol] || 'Unknown';
                    const qty = parseInt(row[qtyCol]) || 1;
                    itemData[item] = (itemData[item] || 0) + qty;
                });
                
                // Sort and get top 10
                const sorted = Object.entries(itemData).sort((a, b) => b[1] - a[1]).slice(0, 10);
                
                const ctx = document.getElementById('itemChart').getContext('2d');
                previewCharts.item = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: sorted.map(s => s[0].substring(0, 20)),
                        datasets: [{
                            label: 'Quantity',
                            data: sorted.map(s => s[1]),
                            backgroundColor: '#e74c3c'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { color: '#a0a0a0' }, grid: { color: 'rgba(255,255,255,0.1)' } },
                            x: { ticks: { color: '#a0a0a0', maxRotation: 45, font: { size: 9 } }, grid: { display: false } }
                        }
                    }
                });
            }
            
            // 4. Status/Summary Donut Chart
            const totalQty = data.reduce((sum, row) => sum + (parseInt(row[qtyCol]) || 1), 0);
            const totalRecords = data.length;
            
            const ctx = document.getElementById('statusChart').getContext('2d');
            previewCharts.status = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Total Records', 'Total Quantity'],
                    datasets: [{
                        data: [totalRecords, totalQty],
                        backgroundColor: ['#f39c12', '#3498db']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#a0a0a0' } }
                    }
                }
            });
        }

        function parseFileAsync(file) {
            return new Promise((resolve, reject) => {
                if (file.name.endsWith('.csv')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            const data = e.target.result;
                            const rows = parseCSV(data);
                            resolve(rows);
                        } catch (error) {
                            reject(error);
                        }
                    };
                    reader.onerror = function(error) {
                        reject(error);
                    };
                    reader.readAsText(file);
                } else {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            const data = e.target.result;
                            const workbook = XLSX.read(data, { type: 'binary', cellDates: true, cellStyles: true });
                            const firstSheet = workbook.SheetNames[0];
                            const worksheet = workbook.Sheets[firstSheet];
                            const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1, raw: false });
                            
                            if (jsonData.length < 2) {
                                reject(new Error('File is empty or has no data rows'));
                                return;
                            }
                            
                            const headers = jsonData[0].map(h => String(h).trim());
                            const rows = [];
                            
                            for (let i = 1; i < jsonData.length; i++) {
                                const rowData = jsonData[i];
                                if (!rowData || rowData.length === 0) continue;
                                
                                const row = {};
                                headers.forEach((header, index) => {
                                    let value = rowData[index];
                                    if (value instanceof Date) {
                                        value = value.toISOString().split('T')[0];
                                    }
                                    row[header] = value !== undefined ? value : '';
                                });
                                
                                if (Object.values(row).some(v => v !== '')) {
                                    rows.push(row);
                                }
                            }
                            
                            resolve(rows);
                        } catch (error) {
                            reject(error);
                        }
                    };
                    reader.onerror = function(error) {
                        reject(error);
                    };
                    reader.readAsBinaryString(file);
                }
            });
        }

        function parseCSV(data) {
            const lines = data.split('\n');
            const headers = lines[0].split(',').map(h => h.trim());
            const rows = [];

            for (let i = 1; i < lines.length; i++) {
                if (lines[i].trim() === '') continue;
                const values = lines[i].split(',').map(v => v.trim());
                const row = {};
                headers.forEach((header, index) => {
                    row[header] = values[index] || '';
                });
                rows.push(row);
            }

            return rows;
        }

        function displayPreview(rows) {
            if (rows.length === 0) {
                showAlert('warning', 'No data found in the file.');
                return;
            }

            parsedData = rows;
            const headers = Object.keys(rows[0]);

            // Update stats
            document.getElementById('totalRows').textContent = rows.length;
            document.getElementById('totalColumns').textContent = headers.length;
            document.getElementById('validRecords').textContent = rows.length;

            // Create table header
            const thead = document.getElementById('tableHead');
            thead.innerHTML = '<tr><th class="row-number">#</th>';
            headers.forEach(header => {
                thead.innerHTML += `<th>${header}</th>`;
            });
            thead.innerHTML += '</tr>';

            // Create table body (first 10 rows)
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '';
            rows.slice(0, 10).forEach((row, index) => {
                let tr = `<tr><td class="row-number">${index + 1}</td>`;
                headers.forEach(header => {
                    tr += `<td>${row[header] || '-'}</td>`;
                });
                tr += '</tr>';
                tbody.innerHTML += tr;
            });

            previewSection.classList.add('show');
            importBtn.style.display = 'block';
            document.getElementById('mergeOptionsSection').style.display = 'block';
            console.log('Preview shown. Import button visible:', importBtn.style.display);

            if (rows.length > 10) {
                showAlert('info', `Showing first 10 rows of ${rows.length} records.`);
            }
        }

        async function doImport() {
            console.log('Import button clicked! parsedData:', parsedData ? parsedData.length : 0);
            if (!parsedData || parsedData.length === 0) {
                showAlert('error', 'No data to import. Please select file(s) first.');
                return;
            }

            importBtn.innerHTML = '<span class="spinner"></span> Importing...';
            importBtn.disabled = true;

            // Get merge choice
            const mergeChoice = document.querySelector('input[name="mergeChoice"]:checked');
            const shouldMerge = mergeChoice && mergeChoice.value === 'merge';

            console.log('Merge choice element:', mergeChoice);
            console.log('Merge choice value:', mergeChoice ? mergeChoice.value : 'NONE');
            console.log('Should merge:', shouldMerge);

            // Determine dataset name based on merge choice
            let datasetName = 'data1';
            if (shouldMerge) {
                datasetName = 'ALL_DATA';
                console.log('USER CHOSE: Merge to ALL_DATA');
            } else {
                // Get next available dataset number from the server (for separate data)
                try {
                    const dsRes = await fetch('api/get-datasets.php');
                    const dsData = await dsRes.json();
                    if (dsData.success) datasetName = dsData.next_name;
                    console.log('USER CHOSE: Keep Separate, assigned:', datasetName);
                } catch (e) { /* use default data1 */ }
            }

            // Prepare data for import
            const fileNames = selectedFiles.map(f => f.name).join(', ');
            const importData = {
                data: parsedData,
                fileName: fileNames,
                dataset_name: datasetName,
                merge_choice: shouldMerge ? 'merge' : 'separate',
                timestamp: new Date().toISOString()
            };

            console.log('🔹 SENDING TO API:');
            console.log('   - Rows:', parsedData.length);
            console.log('   - Dataset Name:', datasetName);
            console.log('   - Merge Choice:', importData.merge_choice);

            // Send to backend
            fetch('api/import-data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(importData)
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(result => {
                console.log('🔹 RESPONSE FROM API:');
                console.log('   - Success:', result.success);
                console.log('   - Dataset Used:', result.dataset_name);
                console.log('   - Records Imported:', result.imported);
                console.log(result);
                
                importBtn.innerHTML = '<i class="fas fa-upload"></i> Import Data';
                importBtn.disabled = false;

                if (result.success) {
                    // Use the actual dataset name returned from server
                    const actualDatasetName = result.dataset_name || datasetName;
                    console.log('✅ Import successful! Final dataset:', actualDatasetName);
                    
                    // Show success popup modal with actual dataset name
                    showSuccessModal(result.imported, result.failed || 0, fileNames, actualDatasetName);
                    // Don't auto-reset - let user click 'View Records' or 'Import More'
                } else {
                    showAlert('error', result.message || 'Import failed. Please try again.');
                }
            })
            .catch(error => {
                console.error('Import error:', error);
                importBtn.innerHTML = '<i class="fas fa-upload"></i> Import Data';
                importBtn.disabled = false;
                showAlert('error', 'Error during import: ' + error.message);
            });
        }

        function resetUpload() {
            selectedFiles = [];
            parsedData = null;
            allParsedData = [];
            workbookSheets = {};
            fileInput.value = '';
            filesList.style.display = 'none';
            filesList.innerHTML = '';
            sheetSelector.style.display = 'none';
            sheetList.innerHTML = '';
            chartPreviewSection.style.display = 'none';
            // Destroy charts
            Object.values(previewCharts).forEach(chart => {
                if (chart) chart.destroy();
            });
            previewCharts = {};
            previewSection.classList.remove('show');
            document.getElementById('uploadBtn').style.display = 'none';
            document.getElementById('mergeOptionsSection').style.display = 'none';
            importBtn.innerHTML = '<i class="fas fa-upload"></i> Import Data';
            importBtn.disabled = false;
            importBtn.style.display = 'none';
            alertContainer.innerHTML = '';
        }

        function showAlert(type, message) {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} show`;
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };

            alert.innerHTML = `
                <i class="fas ${icons[type]} alert-icon"></i>
                <span>${message}</span>
            `;

            alertContainer.innerHTML = '';
            alertContainer.appendChild(alert);

            // Auto-remove success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => alert.remove(), 5000);
            }
        }

        let lastImportedDataset = null;

        function showSuccessModal(imported, failed, fileName, datasetName) {
            lastImportedDataset = datasetName || null;
            document.getElementById('modalImported').textContent = imported;
            document.getElementById('modalFailed').textContent = failed;
            const dsLabel = datasetName ? ` → saved as <strong style="color:#f4d03f">${datasetName.toUpperCase()}</strong>` : '';
            document.getElementById('modalMessage').innerHTML = 
                `Successfully imported ${imported} records from "${fileName}"${dsLabel}`;
            document.getElementById('successModal').classList.add('show');
        }

        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('show');
            resetUpload();
            // Scroll back to upload section
            const uploadSection = document.querySelector('.upload-section');
            if (uploadSection) {
                uploadSection.scrollIntoView({ behavior: 'smooth' });
            }
        }

        function goToDeliveryRecords(dataset) {
            if (dataset) {
                window.location.href = 'delivery-records.php?dataset=' + encodeURIComponent(dataset);
            } else {
                window.location.href = 'delivery-records.php';
            }
        }

        function downloadTemplate() {
            // Create empty template with headers only
            const templateData = [
                {
                    'Invoice No.': '',
                    'Date': '',
                    'Item': '',
                    'Description': '',
                    'Qty.': '',
                    'Serial No.': '',
                    'Date Delivered': '',
                    'Remarks': ''
                }
            ];

            if (typeof XLSX === 'undefined') {
                showAlert('error', 'Excel library not loaded. Please refresh the page.');
                return;
            }
            
            createAndDownloadTemplate(templateData);
        }

        function createAndDownloadTemplate(data) {
            const workbook = XLSX.utils.book_new();
            const worksheet = XLSX.utils.json_to_sheet(data, { skipHeader: false });
            
            // Remove the empty row, keep only headers
            const range = XLSX.utils.decode_range(worksheet['!ref']);
            range.e.r = 0; // Set end row to 0 (headers only)
            worksheet['!ref'] = XLSX.utils.encode_range(range);
            
            // Delete the empty data row cells
            for (let col = range.s.c; col <= range.e.c; col++) {
                const cellAddress = XLSX.utils.encode_cell({ r: 1, c: col });
                delete worksheet[cellAddress];
            }
            
            // Insert title row at the top
            const titleValue = `BW Gas Detector - Import Template (${new Date().toLocaleDateString()})`;
            worksheet['A1'] = {
                v: titleValue,
                t: 's'
            };
            
            // Shift all content down by 2 rows (for title and spacer)
            const newWorksheet = {};
            for (const key in worksheet) {
                if (key.startsWith('!')) {
                    newWorksheet[key] = worksheet[key];
                } else {
                    const match = key.match(/([A-Z]+)(\d+)/);
                    if (match) {
                        const col = match[1];
                        const row = parseInt(match[2]) + 2;
                        newWorksheet[col + row] = worksheet[key];
                    }
                }
            }
            
            // Update range
            const numCols = range.e.c + 1;
            newWorksheet['!ref'] = `A1:${XLSX.utils.encode_col(range.e.c)}${range.e.r + 2}`;
            
            // Define title style
            const titleStyle = {
                fill: { fgColor: { rgb: 'FF1a3a5c' }, patternType: 'solid' },
                font: { bold: true, color: { rgb: 'FFFFFFFF' }, sz: 14, name: 'Calibri' },
                alignment: { horizontal: 'left', vertical: 'center' }
            };
            
            // Apply title formatting
            newWorksheet['A1'].fill = titleStyle.fill;
            newWorksheet['A1'].font = titleStyle.font;
            newWorksheet['A1'].alignment = titleStyle.alignment;
            
            // Merge title cells
            newWorksheet['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: numCols - 1 } }];
            
            // Define header style with professional blue color
            const headerStyle = {
                fill: { fgColor: { rgb: 'FF2f5fa7' }, patternType: 'solid' },
                font: { bold: true, color: { rgb: 'FFFFFFFF' }, sz: 12 },
                alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
                border: {
                    top: { style: 'thin', color: { rgb: 'FF1a3a5c' } },
                    bottom: { style: 'thin', color: { rgb: 'FF1a3a5c' } },
                    left: { style: 'thin', color: { rgb: 'FF1a3a5c' } },
                    right: { style: 'thin', color: { rgb: 'FF1a3a5c' } }
                }
            };
            
            // Apply header formatting (now at row 3)
            const headerRow = 2;
            for (let col = range.s.c; col <= range.e.c; col++) {
                const cellRef = XLSX.utils.encode_cell({ r: headerRow, c: col });
                if (newWorksheet[cellRef]) {
                    newWorksheet[cellRef].fill = headerStyle.fill;
                    newWorksheet[cellRef].font = headerStyle.font;
                    newWorksheet[cellRef].alignment = headerStyle.alignment;
                    newWorksheet[cellRef].border = headerStyle.border;
                }
            }
            
            // Set row heights
            newWorksheet['!rows'] = [
                { hpx: 28 },   // Title row
                { hpx: 15 }    // Spacer row
            ];
            
            // Set column widths
            newWorksheet['!cols'] = [
                { wch: 15 },
                { wch: 12 },
                { wch: 18 },
                { wch: 35 },
                { wch: 8 },
                { wch: 18 },
                { wch: 15 },
                { wch: 20 }
            ];
            
            // Freeze header and title rows
            newWorksheet['!freeze'] = { xSplit: 0, ySplit: 3 };

            XLSX.utils.book_append_sheet(workbook, newWorksheet, 'Template');
            XLSX.writeFile(workbook, 'BW_Gas_Detector_Import_Template.xlsx');
        }

        // Sidebar toggle (from main theme)
        // Sidebar toggle is handled by app.js

        // Load current record count and refresh periodically
        async function loadRecordCount() {
            try {
                const response = await fetch('api/check-data.php?nocache=' + Date.now());
                const data = await response.json();
                const count = data && typeof data.total_records !== 'undefined' ? parseInt(data.total_records) : 0;
                document.getElementById('currentRecordCount').textContent = count.toLocaleString();
                return count;
            } catch (error) {
                console.error('Error loading record count:', error);
                // Keep existing count if API fails
            }
        }

        // Refresh record count every 5 seconds
        window.addEventListener('load', () => {
            setInterval(loadRecordCount, 5000);
        });

        // Show delete modal with dataset selection
        function showDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'flex';
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.onclick = confirmDeleteSelected;
            }
            loadDatasetsForDeletion();
        }

        // Load datasets for deletion
        function loadDatasetsForDeletion() {
            const listDiv = document.getElementById('deleteDatasetsList');
            
            fetch('api/get-datasets.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Datasets loaded:', data);
                    
                    if (!data.success || !data.datasets || data.datasets.length === 0) {
                        listDiv.innerHTML = '<div style="text-align:center; color:#fff; padding:20px;">No datasets found</div>';
                        document.getElementById('confirmDeleteBtn').disabled = true;
                        return;
                    }
                    
                    let html = '<div style="display:flex; flex-direction:column; gap:12px;">';
                    
                    data.datasets.forEach((dataset, index) => {
                        const datasetId = 'dataset_' + index;
                        const recordCount = dataset.count || dataset.record_count || 0;
                        const isDefault = dataset.is_merged || dataset.is_default || dataset.name === 'ALL_DATA';
                        
                        html += `
                            <label style="display:flex; align-items:center; gap:12px; padding:12px; border:1px solid rgba(255,255,255,0.1); border-radius:8px; cursor:pointer; transition:all 0.2s; background:rgba(255,255,255,0.02);" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='rgba(255,255,255,0.02)'">
                                <input type="checkbox" id="${datasetId}" value="${dataset.name}" data-count="${recordCount}" style="width:18px; height:18px; cursor:pointer;">
                                <div style="flex:1; text-align:left;">
                                    <div style="color:#fff; font-weight:600;">${dataset.name}</div>
                                    <div style="color:#fff; font-size:12px; opacity:0.7;">${recordCount.toLocaleString()} records</div>
                                </div>
                                ${isDefault ? '<span style="color:#f4d03f; font-size:11px; background:rgba(244,208,63,0.2); padding:4px 8px; border-radius:4px;">Default</span>' : ''}
                            </label>
                        `;
                    });
                    
                    html += '</div>';
                    listDiv.innerHTML = html;
                    document.getElementById('confirmDeleteBtn').disabled = false;
                })
                .catch(error => {
                    console.error('Error loading datasets:', error);
                    listDiv.innerHTML = '<div style="text-align:center; color:#ff6b6b; padding:20px;"><i class="fas fa-exclamation-circle"></i> Error loading datasets</div>';
                    document.getElementById('confirmDeleteBtn').disabled = true;
                });
        }

        // Confirm delete selected datasets
        function confirmDeleteSelected() {
            const checkboxes = document.querySelectorAll('#deleteDatasetsList input[type="checkbox"]:checked');
            
            if (checkboxes.length === 0) {
                showAlert('error', 'Please select at least one dataset to delete');
                return;
            }
            
            const selectedDatasets = Array.from(checkboxes).map(cb => cb.value);
            const totalRecords = Array.from(checkboxes).reduce((sum, cb) => {
                return sum + parseInt(cb.dataset.count || '0', 10);
            }, 0);

            showDeleteConfirmModal(selectedDatasets, totalRecords);
        }

        function showDeleteConfirmModal(datasets, recordCount) {
            pendingDeleteDatasets = datasets.slice();
            const message = document.getElementById('deleteConfirmMessage');
            const list = document.getElementById('deleteConfirmList');
            const modal = document.getElementById('deleteConfirmModal');

            message.textContent = `Delete ${datasets.length} dataset(s) with ${recordCount.toLocaleString()} record(s)? This action cannot be undone.`;
            list.innerHTML = datasets.map(name => `<div style="padding: 6px 0; border-bottom: 1px dashed rgba(255,255,255,0.1);">• ${name}</div>`).join('');
            modal.classList.add('show');
        }

        function closeDeleteConfirmModal() {
            const modal = document.getElementById('deleteConfirmModal');
            const proceedBtn = document.getElementById('deleteConfirmProceedBtn');
            const cancelBtn = document.getElementById('deleteConfirmCancelBtn');

            modal.classList.remove('show');
            proceedBtn.disabled = false;
            cancelBtn.disabled = false;
            proceedBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete Permanently';
        }

        function confirmDeleteFromModal() {
            if (!pendingDeleteDatasets || pendingDeleteDatasets.length === 0) {
                closeDeleteConfirmModal();
                showAlert('error', 'No selected dataset found. Please try again.');
                return;
            }

            const proceedBtn = document.getElementById('deleteConfirmProceedBtn');
            const cancelBtn = document.getElementById('deleteConfirmCancelBtn');
            proceedBtn.disabled = true;
            cancelBtn.disabled = true;
            proceedBtn.innerHTML = '<span class="spinner"></span> Deleting...';
            showLoadingOverlay(true, 'Deleting');

            deleteSelectedDatasets(pendingDeleteDatasets)
                .finally(() => {
                    showLoadingOverlay(false);
                    closeDeleteConfirmModal();
                });
        }

        // Delete selected datasets
        function deleteSelectedDatasets(datasets) {
            showAlert('info', `Deleting ${datasets.length} dataset(s)... Please wait.`);
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            if (confirmBtn) {
                confirmBtn.disabled = true;
            }
            
            const deletePromises = datasets.map(datasetName => 
                fetch('api/manage-dataset.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', dataset_name: datasetName })
                }).then(r => r.json())
            );
            
            return Promise.all(deletePromises)
                .then(results => {
                    const successful = results.filter(r => r.success);
                    const failed = results.filter(r => !r.success);
                    if (confirmBtn) {
                        confirmBtn.disabled = false;
                    }
                    
                    if (successful.length > 0) {
                        const totalDeleted = successful.reduce((sum, r) => {
                            const match = r.message.match(/(\d+) records/);
                            return sum + (match ? parseInt(match[1]) : 0);
                        }, 0);
                        
                        showAlert('success', `Successfully deleted ${successful.length} dataset(s) with ${totalDeleted.toLocaleString()} records! Reloading...`);
                        setTimeout(() => location.reload(), 1500);
                    } else if (failed.length > 0) {
                        showAlert('error', `Error: ${failed[0].message}`);
                    }
                })
                .catch(error => {
                    if (confirmBtn) {
                        confirmBtn.disabled = false;
                    }
                    showAlert('error', 'Error: ' + error.message);
                    console.error('Deletion error:', error);
                });
        }

        // Close delete modal
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function showLoadingOverlay(show = true, message = 'Saving') {
            const container = document.getElementById('gearLoaderContainer');
            const messageSpan = document.getElementById('loaderMessage');
            const dots = document.getElementById('loaderDots');
            if (!container || !messageSpan || !dots) {
                return;
            }

            if (show) {
                loaderStartTime = Date.now(); // Record start time
                messageSpan.textContent = message;
                container.classList.add('show');

                let dotCount = 1;
                if (dotAnimationInterval) {
                    clearInterval(dotAnimationInterval);
                }
                dotAnimationInterval = setInterval(() => {
                    dotCount = (dotCount % 3) + 1;
                    dots.textContent = '.'.repeat(dotCount);
                }, 400);
            } else {
                if (dotAnimationInterval) {
                    clearInterval(dotAnimationInterval);
                }
                
                // Calculate elapsed time and add delay if needed
                const elapsedTime = Date.now() - (loaderStartTime || Date.now());
                const remainingTime = Math.max(0, LOADER_MIN_DISPLAY_TIME - elapsedTime);
                
                if (remainingTime > 0) {
                    setTimeout(() => {
                        container.classList.remove('show');
                    }, remainingTime);
                } else {
                    container.classList.remove('show');
                }
            }
        }

        // Inventory Upload Functions
        function handleInventoryFileSelect(event) {
            const file = event.target.files[0];
            const statusDiv = document.getElementById('inventoryUploadStatus');
            const fileInfo = document.getElementById('inventoryFileInfo');
            const importBtn = document.getElementById('inventoryImportBtn');
            
            if (!file) return;
            
            // Validate file format
            const validExtensions = ['xlsx', 'xls'];
            const fileExt = file.name.split('.').pop().toLowerCase();
            
            if (!validExtensions.includes(fileExt)) {
                statusDiv.innerHTML = `
                    <div style="background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; border-radius: 8px; padding: 12px; color: #ff6b6b; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Please upload an Excel file (.xlsx or .xls)</span>
                    </div>
                `;
                return;
            }

            // Validate file size (10MB max)
            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                statusDiv.innerHTML = `
                    <div style="background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; border-radius: 8px; padding: 12px; color: #ff6b6b; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>File size exceeds 10MB limit (File size: ${(file.size / 1024 / 1024).toFixed(2)}MB)</span>
                    </div>
                `;
                return;
            }
            
            // Show loading status
            statusDiv.innerHTML = `
                <div style="background: rgba(244, 208, 63, 0.15); border: 1px solid #f4d03f; border-radius: 8px; padding: 12px; color: #f4d03f; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Reading file...</span>
                </div>
            `;

            // Display file info
            document.getElementById('inventoryFileName').textContent = file.name;
            document.getElementById('inventoryFileSize').textContent = (file.size / 1024).toFixed(2) + ' KB';
            fileInfo.classList.add('show');
            
            // Read and parse Excel file
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    // Using xlsx library
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const worksheet = workbook.Sheets[workbook.SheetNames[0]];
                    const jsonData = XLSX.utils.sheet_to_json(worksheet);
                    
                    // Validate data
                    if (jsonData.length === 0) {
                        statusDiv.innerHTML = `
                            <div style="background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; border-radius: 8px; padding: 12px; color: #ff6b6b; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>File is empty or contains no data</span>
                            </div>
                        `;
                        importBtn.style.display = 'none';
                        return;
                    }

                    // Show preview and enable import
                    statusDiv.innerHTML = `
                        <div style="background: rgba(81, 207, 102, 0.15); border: 1px solid #2ecc71; border-radius: 8px; padding: 12px; color: #2ecc71; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-check-circle"></i>
                            <span><strong>${jsonData.length}</strong> items detected in file. Ready to import.</span>
                        </div>
                    `;
                    importBtn.style.display = 'block';
                    
                    // Store data for import
                    window.pendingInventoryData = { data: jsonData, filename: file.name };
                    
                } catch (error) {
                    statusDiv.innerHTML = `
                        <div style="background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; border-radius: 8px; padding: 12px; color: #ff6b6b; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>Error reading file: ${error.message}</span>
                        </div>
                    `;
                    importBtn.style.display = 'none';
                }
            };
            reader.readAsArrayBuffer(file);
        }

        function importInventoryFile() {
            if (!window.pendingInventoryData) {
                alert('No file data available. Please select a file first.');
                return;
            }

            const statusDiv = document.getElementById('inventoryUploadStatus');
            const importBtn = document.getElementById('inventoryImportBtn');
            
            // Disable button and show loading
            importBtn.disabled = true;
            importBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';
            
            statusDiv.innerHTML = `
                <div style="background: rgba(244, 208, 63, 0.15); border: 1px solid #f4d03f; border-radius: 8px; padding: 12px; color: #f4d03f; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Importing inventory data...</span>
                </div>
            `;

            console.log('Sending inventory data:', window.pendingInventoryData);

            fetch('api/import-inventory.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(window.pendingInventoryData)
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers.get('content-type'));
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    if (!text) {
                        throw new Error('Empty response from server');
                    }
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                    }
                });
            })
            .then(result => {
                console.log('Parsed result:', result);
                importBtn.disabled = false;
                importBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Import Inventory';

                if (result.success) {
                    statusDiv.innerHTML = `
                        <div style="background: rgba(46, 204, 113, 0.15); border: 1px solid #2ecc71; border-radius: 8px; padding: 15px; color: #2ecc71; font-size: 13px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                <i class="fas fa-check-circle"></i> 
                                <strong>${result.imported} items imported successfully!</strong>
                            </div>
                            <div style="margin: 8px 0; font-size: 12px;">
                                ✓ Verified in database: ${result.verified_total} items
                            </div>
                            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(46, 204, 113, 0.3); display: flex; gap: 10px;">
                                <a href="inventory.php" style="color: #2ecc71; text-decoration: underline; cursor: pointer; font-weight: 600;">
                                    <i class="fas fa-boxes"></i> View Inventory →
                                </a>
                            </div>
                        </div>
                    `;
                    
                    // Reset after 3 seconds
                    setTimeout(() => {
                        resetInventoryFileInput();
                        statusDiv.innerHTML = '';
                    }, 3000);
                } else {
                    let errorMsg = result.message || 'Unknown error';
                    if (result.errors && result.errors.length > 0) {
                        errorMsg += '<br/><br/><strong>Errors:</strong><ul style="margin: 10px 0; padding-left: 20px;">';
                        result.errors.slice(0, 10).forEach(err => {
                            errorMsg += '<li>' + err + '</li>';
                        });
                        if (result.errors.length > 10) {
                            errorMsg += '<li>... and ' + (result.errors.length - 10) + ' more</li>';
                        }
                        errorMsg += '</ul>';
                    }
                    
                    statusDiv.innerHTML = `
                        <div style="background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; border-radius: 8px; padding: 15px; color: #ff6b6b; font-size: 13px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                <i class="fas fa-exclamation-circle"></i> 
                                <strong>Import Failed</strong>
                            </div>
                            <div>${errorMsg}</div>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                importBtn.disabled = false;
                importBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Import Inventory';
                
                statusDiv.innerHTML = `
                    <div style="background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; border-radius: 8px; padding: 12px; color: #ff6b6b; font-size: 13px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>${error.message}</span>
                    </div>
                `;
            });
        }

        function resetInventoryFileInput() {
            document.getElementById('inventoryFileInput').value = '';
            document.getElementById('inventoryFileInfo').classList.remove('show');
            document.getElementById('inventoryUploadStatus').innerHTML = '';
            document.getElementById('inventoryImportBtn').style.display = 'none';
            window.pendingInventoryData = null;
        }

        function handleOrdersFileSelect(event) {
            const file = event.target.files[0];
            const statusDiv = document.getElementById('ordersUploadStatus');
            const fileInfo = document.getElementById('ordersFileInfo');
            const importBtn = document.getElementById('ordersImportBtn');

            if (!file) return;

            const validExtensions = ['xlsx', 'xls', 'csv'];
            const fileExt = file.name.split('.').pop().toLowerCase();
            if (!validExtensions.includes(fileExt)) {
                statusDiv.innerHTML = `
                    <div style="background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; border-radius: 8px; padding: 12px; color: #ff6b6b; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Please upload an Excel/CSV file (.xlsx, .xls, .csv)</span>
                    </div>
                `;
                return;
            }

            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                statusDiv.innerHTML = `
                    <div style="background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; border-radius: 8px; padding: 12px; color: #ff6b6b; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>File size exceeds 10MB limit (File size: ${(file.size / 1024 / 1024).toFixed(2)}MB)</span>
                    </div>
                `;
                return;
            }

            statusDiv.innerHTML = `
                <div style="background: rgba(244, 208, 63, 0.15); border: 1px solid #f4d03f; border-radius: 8px; padding: 12px; color: #f4d03f; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Reading file...</span>
                </div>
            `;

            document.getElementById('ordersFileName').textContent = file.name;
            document.getElementById('ordersFileSize').textContent = (file.size / 1024).toFixed(2) + ' KB';
            fileInfo.classList.add('show');

            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    let jsonData = [];

                    if (fileExt === 'csv') {
                        jsonData = parseCSV(e.target.result);
                    } else {
                        const data = new Uint8Array(e.target.result);
                        const workbook = XLSX.read(data, { type: 'array' });
                        const worksheet = workbook.Sheets[workbook.SheetNames[0]];
                        jsonData = XLSX.utils.sheet_to_json(worksheet);
                    }

                    if (!jsonData || jsonData.length === 0) {
                        statusDiv.innerHTML = `
                            <div style="background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; border-radius: 8px; padding: 12px; color: #ff6b6b; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>File is empty or contains no data</span>
                            </div>
                        `;
                        importBtn.style.display = 'none';
                        return;
                    }

                    statusDiv.innerHTML = `
                        <div style="background: rgba(81, 207, 102, 0.15); border: 1px solid #2ecc71; border-radius: 8px; padding: 12px; color: #2ecc71; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-check-circle"></i>
                            <span><strong>${jsonData.length}</strong> order rows detected. Ready to import.</span>
                        </div>
                    `;
                    importBtn.style.display = 'block';
                    window.pendingOrdersData = { data: jsonData, filename: file.name };
                } catch (error) {
                    statusDiv.innerHTML = `
                        <div style="background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; border-radius: 8px; padding: 12px; color: #ff6b6b; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>Error reading file: ${error.message}</span>
                        </div>
                    `;
                    importBtn.style.display = 'none';
                }
            };

            if (fileExt === 'csv') {
                reader.readAsText(file);
            } else {
                reader.readAsArrayBuffer(file);
            }
        }

        function importOrdersFile() {
            if (!window.pendingOrdersData) {
                alert('No file data available. Please select a file first.');
                return;
            }

            const statusDiv = document.getElementById('ordersUploadStatus');
            const importBtn = document.getElementById('ordersImportBtn');

            importBtn.disabled = true;
            importBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';

            statusDiv.innerHTML = `
                <div style="background: rgba(244, 208, 63, 0.15); border: 1px solid #f4d03f; border-radius: 8px; padding: 12px; color: #f4d03f; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Importing orders data...</span>
                </div>
            `;

            fetch('api/import-orders.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(window.pendingOrdersData)
            })
            .then(response => response.text().then(text => {
                if (!text) throw new Error('Empty response from server');
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                }
            }))
            .then(result => {
                importBtn.disabled = false;
                importBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Import Orders';

                if (result.success) {
                    statusDiv.innerHTML = `
                        <div style="background: rgba(46, 204, 113, 0.15); border: 1px solid #2ecc71; border-radius: 8px; padding: 15px; color: #2ecc71; font-size: 13px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                <i class="fas fa-check-circle"></i>
                                <strong>${result.imported} orders imported successfully!</strong>
                            </div>
                            <div style="margin: 8px 0; font-size: 12px;">
                                Failed rows: ${result.failed || 0}
                            </div>
                            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(46, 204, 113, 0.3); display: flex; gap: 10px;">
                                <a href="orders.php" style="color: #2ecc71; text-decoration: underline; cursor: pointer; font-weight: 600;">
                                    <i class="fas fa-file-invoice-dollar"></i> View Orders ->
                                </a>
                            </div>
                        </div>
                    `;

                    setTimeout(() => {
                        resetOrdersFileInput();
                    }, 3000);
                } else {
                    let errorMsg = result.message || 'Unknown error';
                    if (result.errors && result.errors.length > 0) {
                        errorMsg += '<br><br><strong>Errors:</strong><ul style="margin: 10px 0; padding-left: 20px;">';
                        result.errors.slice(0, 10).forEach(err => {
                            errorMsg += '<li>' + err + '</li>';
                        });
                        if (result.errors.length > 10) {
                            errorMsg += '<li>... and ' + (result.errors.length - 10) + ' more</li>';
                        }
                        errorMsg += '</ul>';
                    }

                    statusDiv.innerHTML = `
                        <div style="background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; border-radius: 8px; padding: 15px; color: #ff6b6b; font-size: 13px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                <i class="fas fa-exclamation-circle"></i>
                                <strong>Import Failed</strong>
                            </div>
                            <div>${errorMsg}</div>
                        </div>
                    `;
                }
            })
            .catch(error => {
                importBtn.disabled = false;
                importBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Import Orders';
                statusDiv.innerHTML = `
                    <div style="background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; border-radius: 8px; padding: 12px; color: #ff6b6b; font-size: 13px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>${error.message}</span>
                    </div>
                `;
            });
        }

        function resetOrdersFileInput() {
            document.getElementById('ordersFileInput').value = '';
            document.getElementById('ordersFileInfo').classList.remove('show');
            document.getElementById('ordersUploadStatus').innerHTML = '';
            document.getElementById('ordersImportBtn').style.display = 'none';
            window.pendingOrdersData = null;
        }

        // Drag and drop for inventory upload zone
        const inventoryUploadZone = document.getElementById('inventoryUploadZone');
        if (inventoryUploadZone) {
            inventoryUploadZone.addEventListener('click', () => {
                document.getElementById('inventoryFileInput').click();
            });

            inventoryUploadZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                inventoryUploadZone.classList.add('dragover');
            });

            inventoryUploadZone.addEventListener('dragleave', () => {
                inventoryUploadZone.classList.remove('dragover');
            });

            inventoryUploadZone.addEventListener('drop', (e) => {
                e.preventDefault();
                inventoryUploadZone.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    document.getElementById('inventoryFileInput').files = files;
                    handleInventoryFileSelect({ target: { files: files } });
                }
            });
        }

        const ordersUploadZone = document.getElementById('ordersUploadZone');
        if (ordersUploadZone) {
            ordersUploadZone.addEventListener('click', () => {
                document.getElementById('ordersFileInput').click();
            });

            ordersUploadZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                ordersUploadZone.classList.add('dragover');
            });

            ordersUploadZone.addEventListener('dragleave', () => {
                ordersUploadZone.classList.remove('dragover');
            });

            ordersUploadZone.addEventListener('drop', (e) => {
                e.preventDefault();
                ordersUploadZone.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    document.getElementById('ordersFileInput').files = files;
                    handleOrdersFileSelect({ target: { files: files } });
                }
            });
        }

        function downloadInventoryTemplate() {
            const ws = XLSX.utils.aoa_to_sheet([
                ['BOX', 'ITEMS', 'DESCRIPTION', 'UOM', 'INVENTORY', 'NOTES'],
                ['M1', 'M1-AF-K1', 'Motor - AC Type 1', 'UNITS', 15, 'In stock'],
                ['M2', 'M2-AF-K2', 'Motor - AC Type 2', 'UNITS', 8, 'Low stock'],
                ['M3', 'M3-AF-K3', 'Motor - AC Type 3', 'UNITS', 25, 'Adequate'],
                ['M5', 'M5-AF-K2', 'Motor - AC Type 5', 'UNITS', 2, 'Critical'],
            ]);
            
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Inventory');
            
            // Format header row
            ws['!cols'] = [
                { wch: 12 },
                { wch: 15 },
                { wch: 30 },
                { wch: 10 },
                { wch: 12 },
                { wch: 20 }
            ];
            
            XLSX.writeFile(wb, 'Inventory_Template.xlsx');
        }

        function downloadOrdersTemplate() {
            const ws = XLSX.utils.aoa_to_sheet([
                ['Order Customer', 'Order Date', 'Item Code', 'Item Name', 'Quantity', 'Unit Price', 'PO Number', 'PO Status', 'Invoice No', 'Notes'],
                ['Sample Customer Inc.', '2025-01-15', 'M1-AF-K1', 'Multi Gas Detector', 2, 12500, 'PO-2025-001', 'Pending', '', 'Urgent order'],
                ['ABC Industrial', '2025-01-16', 'M2-AF-K2', 'Single Gas Detector', 1, 9800, '', 'No PO', '', '']
            ]);

            ws['!cols'] = [
                { wch: 25 },
                { wch: 14 },
                { wch: 18 },
                { wch: 30 },
                { wch: 10 },
                { wch: 12 },
                { wch: 16 },
                { wch: 12 },
                { wch: 14 },
                { wch: 24 }
            ];

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Orders');
            XLSX.writeFile(wb, 'Orders_Import_Template.xlsx');
        }

        // Profile dropdown
        
        // (Delete button uses onclick="showDeleteModal()" directly on the element)
    </script>

    <!-- ===== CHART PREVIEW MODAL ===== -->
    <div id="chartPreviewOverlay" onclick="closeChartPreview(event)" style="display:none;position:fixed;inset:0;z-index:9999;backdrop-filter:blur(8px);align-items:center;justify-content:center;padding:16px;box-sizing:border-box;">
        <div id="chartPreviewBox" style="border-radius:16px;width:min(1200px,97vw);height:90vh;display:flex;flex-direction:column;box-shadow:0 32px 80px rgba(0,0,0,0.6);overflow:hidden;">
            <div id="chartPreviewHeader" style="display:flex;align-items:center;justify-content:space-between;padding:18px 26px;flex-shrink:0;">
                <h3 id="chartPreviewTitle" style="margin:0;font-size:18px;font-weight:700;"></h3>
                <button id="chartPreviewCloseBtn" onclick="closeChartPreviewBtn()" style="width:34px;height:34px;border-radius:9px;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;border:none;transition:background 0.2s;"><i class="fas fa-times"></i></button>
            </div>
            <div style="padding:10px 26px 26px;flex:1;min-height:0;position:relative;">
                <canvas id="chartPreviewCanvas" style="width:100% !important;height:100% !important;"></canvas>
            </div>
        </div>
    </div>
    <style>
    .chart-expandable{cursor:pointer;}
    .chart-expand-hint{position:absolute;top:8px;right:8px;background:rgba(244,208,63,0.15);border:1px solid rgba(244,208,63,0.3);color:#f4d03f;width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;opacity:0;transition:opacity 0.2s;pointer-events:none;}
    .chart-expandable:hover .chart-expand-hint{opacity:1;}
    .chart-expandable:hover canvas{opacity:0.88;}
    #chartPreviewOverlay.cp-dark{background:rgba(4,8,18,0.92);}
    #chartPreviewBox.cp-dark{background:#131c2b;border:1px solid #2a3c55;}
    #chartPreviewHeader.cp-dark{border-bottom:1px solid #2a3c55;}
    #chartPreviewTitle.cp-dark{color:#e2ecf8;}
    #chartPreviewCloseBtn.cp-dark{background:rgba(255,255,255,0.07);color:#a0b4c8;}
    #chartPreviewOverlay.cp-light{background:rgba(180,195,215,0.72);}
    #chartPreviewBox.cp-light{background:#ffffff;border:1px solid #d0daea;}
    #chartPreviewHeader.cp-light{border-bottom:1px solid #e0eaf4;}
    #chartPreviewTitle.cp-light{color:#1a2a3a;}
    #chartPreviewCloseBtn.cp-light{background:#f0f4fa;color:#3a4a5a;}
    </style>
    <script>
    function openChartPreview(canvasId,title){
        const src=document.getElementById(canvasId);if(!src)return;
        const sc=(typeof Chart!=='undefined')&&Chart.getChart?Chart.getChart(src):null;if(!sc)return;
        const isLight=document.body.classList.contains('light-mode');
        const tc=isLight?'cp-light':'cp-dark';
        const tickC=isLight?'#4a5a6a':'#8a9ab5',gridC=isLight?'rgba(0,0,0,0.07)':'rgba(255,255,255,0.06)',legC=isLight?'#2a3a4a':'#c0d0e0';
        ['chartPreviewOverlay','chartPreviewBox','chartPreviewHeader','chartPreviewTitle','chartPreviewCloseBtn'].forEach(function(id){const el=document.getElementById(id);el.classList.remove('cp-dark','cp-light');el.classList.add(tc);});
        document.getElementById('chartPreviewTitle').textContent=title;
        const ov=document.getElementById('chartPreviewOverlay');ov.style.display='flex';document.body.style.overflow='hidden';
        const pc=document.getElementById('chartPreviewCanvas');const ex=Chart.getChart(pc);if(ex)ex.destroy();
        try{
            const cfg={type:sc.config.type,data:JSON.parse(JSON.stringify(sc.config.data)),options:JSON.parse(JSON.stringify(sc.config.options||{}))};
            cfg.options.responsive=true;cfg.options.maintainAspectRatio=false;cfg.options.animation={duration:400};
            cfg.options.plugins=cfg.options.plugins||{};
            cfg.options.plugins.legend=cfg.options.plugins.legend||{};
            cfg.options.plugins.legend.labels=cfg.options.plugins.legend.labels||{};
            cfg.options.plugins.legend.labels.color=legC;cfg.options.plugins.legend.labels.font={size:14};
            if(cfg.options.plugins.title)cfg.options.plugins.title.color=legC;
            if(cfg.options.scales){Object.values(cfg.options.scales).forEach(function(s){s.ticks=s.ticks||{};s.ticks.color=tickC;s.ticks.font={size:13};s.grid=s.grid||{};s.grid.color=gridC;});}
            new Chart(pc,cfg);
        }catch(e){console.error('Chart preview error:',e);}
    }
    function closeChartPreviewBtn(){document.getElementById('chartPreviewOverlay').style.display='none';document.body.style.overflow='';const c=Chart.getChart(document.getElementById('chartPreviewCanvas'));if(c)c.destroy();}
    function closeChartPreview(e){if(e&&e.target!==document.getElementById('chartPreviewOverlay'))return;closeChartPreviewBtn();}
    document.addEventListener('keydown',function(e){if(e.key==='Escape')closeChartPreviewBtn();});
    </script>
</body>
</html>
