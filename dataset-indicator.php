<?php
// Dataset indicator helper - include this in all pages to show active dataset
// This should be included after session_start() and before any HTML output

// Get selected dataset from GET/SESSION
$active_dataset = isset($_GET['dataset']) ? trim(strval($_GET['dataset'])) : (isset($_SESSION['active_dataset']) ? $_SESSION['active_dataset'] : null);

// Update session if dataset is passed via GET
if (isset($_GET['dataset'])) {
    $_SESSION['active_dataset'] = $active_dataset;
}

// Function to render the dataset indicator HTML
function renderDatasetIndicator($dataset_name) {
    if (empty($dataset_name)) {
        return '';
    }
    
    $current_url = strtok($_SERVER["REQUEST_URI"], '?');
    return '
    <div style="display:inline-flex; align-items:center; gap:10px; margin-left:15px; padding-left:15px; border-left:1px solid rgba(255,255,255,0.1);">
        <span style="font-size:12px; color:#f4d03f; background:rgba(244,208,63,0.15); padding:5px 12px; border-radius:20px; border:1px solid rgba(244,208,63,0.3); white-space:nowrap;">
            <i class="fas fa-filter"></i> <strong>' . htmlspecialchars(strtoupper($dataset_name)) . '</strong>
        </span>
        <a href="' . htmlspecialchars($current_url) . '" style="font-size:11px; color:#8a9ab5; text-decoration:none; padding:5px 8px; transition:all 0.2s; cursor:pointer;" onmouseover="this.style.color=\'#f4d03f\'" onmouseout="this.style.color=\'#8a9ab5\'">
            <i class="fas fa-times"></i> Clear
        </a>
    </div>
    ';
}
