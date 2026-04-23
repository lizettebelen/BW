<?php
// ── Database Backup API ────────────────────────────────────────────────────
session_start();
header('Content-Type: application/json');

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

require_once __DIR__ . '/../db_config.php';

// Determine database type and create backup
try {
    // Create backups directory if it doesn't exist
    $backup_dir = __DIR__ . '/../backups';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    $timestamp = date('Y-m-d_H-i-s');
    $backup_file = $backup_dir . '/backup_' . $timestamp . '.sql';

    // MySQL backup using mysqldump
    $db_name = 'bw_gas_detector';
    $db_user = 'root';
    $db_pass = '';
    $db_host = 'localhost';

    $command = sprintf(
        'mysqldump -h %s -u %s %s > %s 2>&1',
        escapeshellarg($db_host),
        escapeshellarg($db_user),
        escapeshellarg($db_name),
        escapeshellarg($backup_file)
    );

    if (!empty($db_pass)) {
        $command = sprintf(
            'mysqldump -h %s -u %s -p%s %s > %s 2>&1',
            escapeshellarg($db_host),
            escapeshellarg($db_user),
            escapeshellarg($db_pass),
            escapeshellarg($db_name),
            escapeshellarg($backup_file)
        );
    }

    $output = null;
    $return_var = null;
    exec($command, $output, $return_var);

    if ($return_var !== 0) {
        throw new Exception('mysqldump failed: ' . implode("\n", $output));
    }

    if (!file_exists($backup_file) || filesize($backup_file) === 0) {
        throw new Exception('Backup file is empty or could not be created');
    }

    // Save backup timestamp to database
    $backup_date = date('Y-m-d H:i:s');
    
    // Check if settings table exists and update it
    try {
        $settings_check = $conn->query("SHOW TABLES LIKE 'settings'");
        $table_exists = $settings_check && $settings_check->num_rows > 0;

        if ($table_exists) {
            $update_query = "UPDATE settings SET last_backup = '" . date('Y-m-d H:i:s') . "' LIMIT 1";
            $conn->query($update_query);
        } else {
            // Create settings table with backup timestamp
            $create_settings = "CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY,
                last_backup VARCHAR(255)
            )";
            $conn->query($create_settings);
            
            $insert_query = "INSERT INTO settings (last_backup) VALUES ('" . date('Y-m-d H:i:s') . "')";
            $conn->query($insert_query);
        }
    } catch (Exception $e) {
        // Settings table update failed, but backup still succeeded
        error_log("Warning: Could not update settings table: " . $e->getMessage());
    }

    // Success response
    http_response_code(200);
    die(json_encode([
        'success' => true,
        'message' => 'Database backup created successfully',
        'filename' => basename($backup_file),
        'timestamp' => date('M d, Y h:i A'),
        'size' => filesize($backup_file),
        'path' => $backup_file
    ]));

} catch (Exception $e) {
    error_log("Backup error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Backup failed: ' . $e->getMessage()
    ]));
}
?>
