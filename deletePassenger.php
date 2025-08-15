<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/DbConnector.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['passenger_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing passenger id']);
    exit();
}

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    $passenger_id = intval($data['passenger_id']);
    
    // Optional: Validate ship context if provided for extra security
    $ship_validation = '';
    $params = [$passenger_id];
    
    if (isset($data['ship_id']) && !empty($data['ship_id'])) {
        $ship_validation = ' AND ship_id = ?';
        $params[] = $data['ship_id'];
    } elseif (isset($data['ship_name']) && !empty($data['ship_name'])) {
        $ship_validation = ' AND ship_name = ?';
        $params[] = $data['ship_name'];
    }
    
    // Get passenger details before deletion for audit/logging
    $auditSql = "SELECT passenger_name, ship_id, ship_name, route FROM passenger_management WHERE passenger_id = ?" . $ship_validation;
    $auditStmt = $pdo->prepare($auditSql);
    $auditStmt->execute($params);
    $passengerDetails = $auditStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$passengerDetails) {
        echo json_encode(['success' => false, 'message' => 'Passenger not found or ship validation failed']);
        exit();
    }
    
    // Delete the passenger
    $sql = 'DELETE FROM passenger_management WHERE passenger_id = ?' . $ship_validation;
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute($params)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Passenger deleted successfully',
            'deleted_passenger' => [
                'name' => $passengerDetails['passenger_name'],
                'ship_id' => $passengerDetails['ship_id'],
                'ship_name' => $passengerDetails['ship_name'],
                'route' => $passengerDetails['route']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete passenger']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
