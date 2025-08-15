<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/DbConnector.php';

$response = array('success' => false);

// Accept both POST and DELETE methods
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    $response['message'] = 'Invalid request method. Use POST or DELETE.';
    echo json_encode($response);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $response['message'] = 'Invalid JSON input.';
    echo json_encode($response);
    exit;
}

$id = $input['id'] ?? null;

if (!$id) {
    $response['message'] = 'Missing pricing record ID.';
    echo json_encode($response);
    exit;
}

try {
    $db = new DBConnector();
    $pdo = $db->connect();
    
    // First check if the pricing record exists
    $checkStmt = $pdo->prepare('SELECT id, ship_id, ship_name, route FROM cabin_type_pricing WHERE id = ?');
    $checkStmt->execute([$id]);
    $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingRecord) {
        $response['message'] = 'Pricing record not found.';
        echo json_encode($response);
        exit;
    }
    
    // Delete the pricing record
    $stmt = $pdo->prepare('DELETE FROM cabin_type_pricing WHERE id = ?');
    $result = $stmt->execute([$id]);
    
    if ($result && $stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Pricing record deleted successfully.';
        $response['deleted_record'] = [
            'id' => $id,
            'ship_id' => $existingRecord['ship_id'],
            'ship_name' => $existingRecord['ship_name'],
            'route' => $existingRecord['route']
        ];
    } else {
        $response['message'] = 'Failed to delete pricing record.';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error deleting pricing: ' . $e->getMessage();
    error_log('Delete cabin type pricing error: ' . $e->getMessage());
}

echo json_encode($response);
?>
