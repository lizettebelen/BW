<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db_config.php';

try {
    if (!$conn) throw new Exception('Database connection failed');
    
    $json = file_get_contents('php://input');
    $request = json_decode($json, true);
    
    $action = $request['action'] ?? '';
    
    if ($action === 'rename') {
        $oldName = trim($request['old_name'] ?? '');
        $newName = trim($request['new_name'] ?? '');
        
        if (empty($oldName) || empty($newName)) {
            throw new Exception('Both old_name and new_name are required');
        }
        
        // Sanitize new name
        $newName = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $newName);
        $newName = substr($newName, 0, 50);
        
        if (empty($newName)) {
            throw new Exception('Invalid new name');
        }
        
        // Check if new name already exists
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM delivery_records WHERE dataset_name = ?");
        $stmt->bind_param('s', $newName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['cnt'] > 0 && $oldName !== $newName) {
            throw new Exception('A dataset with this name already exists');
        }
        
        // Rename the dataset
        $stmt = $conn->prepare("UPDATE delivery_records SET dataset_name = ? WHERE dataset_name = ?");
        $stmt->bind_param('ss', $newName, $oldName);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to rename dataset');
        }
        
        $affected = $stmt->affected_rows;
        
        echo json_encode([
            'success' => true,
            'message' => "Renamed '$oldName' to '$newName' ($affected records updated)",
            'refresh_required' => true,
            'new_dataset_name' => $newName
        ]);
        
    } elseif ($action === 'delete') {
        $datasetName = trim($request['dataset_name'] ?? '');
        
        if (empty($datasetName)) {
            throw new Exception('dataset_name is required');
        }
        
        $stmt = $conn->prepare("DELETE FROM delivery_records WHERE dataset_name = ?");
        $stmt->bind_param('s', $datasetName);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete dataset');
        }
        
        $affected = $stmt->affected_rows;
        
        echo json_encode([
            'success' => true,
            'message' => "Deleted dataset '$datasetName' ($affected records removed)",
            'refresh_required' => true,
            'deleted_dataset' => $datasetName
        ]);
        
    } else {
        throw new Exception('Invalid action. Use "rename" or "delete"');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
