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

    // Check if we're using MySQL or SQLite
    if ($conn instanceof mysqli) {
        // MySQL backup using mysqldump
        $db_name = 'bw_gas_detector';
        $db_user = 'root';
        $db_pass = '';
        $db_host = 'localhost';

        // Build mysqldump command
        $command = sprintf(
            'mysqldump -h %s -u %s %s %s > %s 2>&1',
            escapeshellarg($db_host),
            escapeshellarg($db_user),
            ($db_pass ? '-p' . escapeshellarg($db_pass) : ''),
            escapeshellarg($db_name),
            escapeshellarg($backup_file)
        );

        // If password is empty, adjust command
        if (empty($db_pass)) {
            $command = sprintf(
                'mysqldump -h %s -u %s %s > %s 2>&1',
                escapeshellarg($db_host),
                escapeshellarg($db_user),
                escapeshellarg($db_name),
                escapeshellarg($backup_file)
            );
        }

        // Execute backup
        $output = null;
        $return_var = null;
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            throw new Exception('mysqldump failed: ' . implode("\n", $output));
        }

        // Verify backup file exists and has content
        if (!file_exists($backup_file) || filesize($backup_file) === 0) {
            throw new Exception('Backup file is empty or could not be created');
        }

    } else {
        // SQLite backup - copy the database file
        $sqlite_file = __DIR__ . '/../bw_gas_detector.sqlite';
        if (!file_exists($sqlite_file)) {
            throw new Exception('SQLite database file not found');
        }

        // For SQLite, export as SQL dump
        $sql_dump = fopen($backup_file, 'w');
        if ($sql_dump === false) {
            throw new Exception('Could not create backup file');
        }

        // Get all tables and data
        fwrite($sql_dump, "-- SQLite Database Backup\n");
        fwrite($sql_dump, "-- Created: " . date('Y-m-d H:i:s') . "\n\n");

        // Get all tables
        $tables_query = "SELECT name FROM sqlite_master WHERE type='table'";
        $result = $conn->query($tables_query);

        while ($table = $result->fetch_assoc()) {
            $table_name = $table['name'];

            // Get CREATE TABLE statement
            $create_query = "SELECT sql FROM sqlite_master WHERE type='table' AND name = '" . $table_name . "'";
            $create_result = $conn->query($create_query);
            if ($create_row = $create_result->fetch_assoc()) {
                fwrite($sql_dump, $create_row['sql'] . ";\n\n");
            }

            // Get table data
            $data_query = "SELECT * FROM " . $table_name;
            $data_result = $conn->query($data_query);
            
            while ($row = $data_result->fetch_assoc()) {
                $values = array_map(function($v) {
                    return $v === null ? 'NULL' : "'" . addslashes($v) . "'";
                }, array_values($row));
                
                $cols = implode(', ', array_keys($row));
                fwrite($sql_dump, "INSERT INTO " . $table_name . " (" . $cols . ") VALUES (" . implode(", ", $values) . ");\n");
            }
            fwrite($sql_dump, "\n");
        }

        fclose($sql_dump);
    }

    // Save backup timestamp to database
    $backup_date = date('Y-m-d H:i:s');
    
    // Check if settings table exists and update it
    $tables_query = "SHOW TABLES LIKE 'settings'" . (isset($conn) && !($conn instanceof mysqli) ? " COLLATE NOCASE" : "");
    
    // Try to update settings table if it exists
    try {
        // For both MySQL and SQLite
        if ($conn instanceof mysqli) {
            $settings_check = $conn->query("SHOW TABLES LIKE 'settings'");
            $table_exists = $settings_check && $settings_check->num_rows > 0;
        } else {
            $settings_check = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
            $table_exists = $settings_check && $settings_check->num_rows > 0;
        }

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
