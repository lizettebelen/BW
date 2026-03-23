<?php
// ── Get Last Backup Time API ────────────────────────────────────────────────────
session_start();
header('Content-Type: application/json');

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

require_once __DIR__ . '/../db_config.php';

try {
    $lastBackup = 'Never';

    // Try to get last backup time from settings table
    try {
        if ($conn instanceof mysqli) {
            // Check if settings table exists
            $result = $conn->query("SHOW TABLES LIKE 'settings'");
            if ($result && $result->num_rows > 0) {
                $query = "SELECT last_backup FROM settings ORDER BY id DESC LIMIT 1";
                $result = $conn->query($query);
                
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if ($row['last_backup']) {
                        // Format the timestamp
                        $date = new DateTime($row['last_backup']);
                        $lastBackup = $date->format('M d, Y h:i A');
                    }
                }
            }
        } else {
            // SQLite
            $result = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
            if ($result && $result->num_rows > 0) {
                $query = "SELECT last_backup FROM settings ORDER BY id DESC LIMIT 1";
                $result = $conn->query($query);
                
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if ($row['last_backup']) {
                        // Format the timestamp
                        $date = new DateTime($row['last_backup']);
                        $lastBackup = $date->format('M d, Y h:i A');
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Settings table may not exist or other error, just return 'Never'
        error_log("Warning: Could not retrieve last backup time: " . $e->getMessage());
    }

    // Also check for backup files in the backups directory
    $backupDir = __DIR__ . '/../backups';
    if (is_dir($backupDir)) {
        $files = array_filter(scandir($backupDir), function($f) { 
            return preg_match('/^backup_.*\.sql$/', $f); 
        });
        
        if (!empty($files)) {
            // Get the most recent backup file
            rsort($files);
            $mostRecent = array_shift($files);
            $filePath = $backupDir . '/' . $mostRecent;
            $fileTime = filemtime($filePath);
            
            if ($fileTime) {
                $date = new DateTime();
                $date->setTimestamp($fileTime);
                $lastBackup = $date->format('M d, Y h:i A');
            }
        }
    }

    http_response_code(200);
    die(json_encode([
        'success' => true,
        'lastBackup' => $lastBackup
    ]));

} catch (Exception $e) {
    error_log("Get backup time error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Failed to retrieve backup time: ' . $e->getMessage()
    ]));
}
?>
