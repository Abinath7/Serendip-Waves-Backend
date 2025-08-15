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
$required = ['booking_id', 'passenger_name', 'email', 'route', 'cabin_id', 'age', 'gender', 'citizenship'];

foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit();
    }
}

// Require either ship_id or ship_name
if (empty($data['ship_id']) && empty($data['ship_name'])) {
    echo json_encode(['success' => false, 'message' => "Either ship_id or ship_name is required"]);
    exit();
}

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    $ship_id = $data['ship_id'] ?? null;
    $ship_name = $data['ship_name'] ?? null;
    
    // If ship_id is provided, get ship_name; if ship_name is provided, get ship_id
    if ($ship_id) {
        $ship_stmt = $pdo->prepare("SELECT ship_name FROM ship_details WHERE ship_id = ?");
        $ship_stmt->execute([$ship_id]);
        $ship_result = $ship_stmt->fetch(PDO::FETCH_ASSOC);
        if ($ship_result) {
            $ship_name = $ship_result['ship_name'];
        } else {
            echo json_encode(['success' => false, 'message' => "Invalid ship_id provided"]);
            exit();
        }
    } elseif ($ship_name) {
        $ship_stmt = $pdo->prepare("SELECT ship_id FROM ship_details WHERE ship_name = ?");
        $ship_stmt->execute([$ship_name]);
        $ship_result = $ship_stmt->fetch(PDO::FETCH_ASSOC);
        if ($ship_result) {
            $ship_id = $ship_result['ship_id'];
        }
    }
    
    $sql = 'INSERT INTO passenger_management (
        booking_id, passenger_name, email, ship_id, ship_name, route, cabin_id, age, gender, citizenship, is_primary, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())';
    
    $stmt = $pdo->prepare($sql);
    $params = [
        $data['booking_id'],
        $data['passenger_name'],
        $data['email'],
        $ship_id,
        $ship_name,
        $data['route'],
        $data['cabin_id'],
        $data['age'],
        $data['gender'],
        $data['citizenship']
    ];
    
    if ($stmt->execute($params)) {
        echo json_encode(['success' => true, 'message' => 'Passenger created', 'id' => $pdo->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create passenger']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
