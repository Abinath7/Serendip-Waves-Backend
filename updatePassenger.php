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
    $fields = [
        'passenger_name', 'email',
        'ship_id', 'ship_name', 'route', 'cabin_id', 'age', 'gender', 'citizenship'
    ];
$updates = [];
$params = [];

foreach ($fields as $f) {
    if (isset($data[$f])) {
        $updates[] = "$f = ?";
        $params[] = $data[$f];
    }
}

if (!$updates) {
    echo json_encode(['success' => false, 'message' => 'No fields to update']);
    exit();
}

$params[] = $passenger_id;
$sql = 'UPDATE passenger_management SET ' . implode(', ', $updates) . ' WHERE passenger_id = ?';
$stmt = $pdo->prepare($sql);

if ($stmt->execute($params)) {
    echo json_encode(['success' => true, 'message' => 'Passenger updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update passenger']);
}

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
