<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

require_once __DIR__ . '/DbConnector.php';

$response = array('success' => false);

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $response['message'] = 'Invalid JSON.';
    echo json_encode($response);
    exit;
}

$ship_id = $input['ship_id'] ?? 0;
$ship_name = $input['ship_name'] ?? '';
$route = $input['route'] ?? '';
$interior_price = $input['interior_price'] ?? 0;
$ocean_view_price = $input['ocean_view_price'] ?? 0;
$balcony_price = $input['balcony_price'] ?? 0;
$suite_price = $input['suite_price'] ?? 0;

// Accept either ship_id or ship_name
if (!$ship_id && !$ship_name) {
    $response['message'] = 'Ship ID or ship name is required.';
    echo json_encode($response);
    exit;
}

if (!$route) {
    $response['message'] = 'Route is required.';
    echo json_encode($response);
    exit;
}

try {
    $db = new DBConnector();
    $pdo = $db->connect();
    
    // If ship_id is provided, get ship_name from it; otherwise validate ship_name and get ship_id
    if ($ship_id) {
        $stmt = $pdo->prepare('SELECT ship_name FROM ship_details WHERE ship_id = ?');
        $stmt->execute([$ship_id]);
        $ship_data = $stmt->fetch();
        if (!$ship_data) {
            $response['message'] = 'Invalid ship ID.';
            echo json_encode($response);
            exit;
        }
        $ship_name = $ship_data['ship_name'];
    } else {
        // Get ship_id from ship_name
        $stmt = $pdo->prepare('SELECT ship_id FROM ship_details WHERE ship_name = ?');
        $stmt->execute([$ship_name]);
        $ship_data = $stmt->fetch();
        if (!$ship_data) {
            $response['message'] = 'Invalid ship name.';
            echo json_encode($response);
            exit;
        }
        $ship_id = $ship_data['ship_id'];
    }
    
    // Check if this ship/route combo exists in itineraries
    $stmt = $pdo->prepare('SELECT 1 FROM itineraries WHERE ship_id = ? AND route = ?');
    $stmt->execute([$ship_id, $route]);
    if (!$stmt->fetch()) {
        $response['message'] = 'Invalid ship or route combination.';
        echo json_encode($response);
        exit;
    }
    
    // Insert new pricing with ship_id
    $stmt = $pdo->prepare('INSERT INTO cabin_type_pricing (ship_id, ship_name, route, interior_price, ocean_view_price, balcony_price, suite_price) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$ship_id, $ship_name, $route, $interior_price, $ocean_view_price, $balcony_price, $suite_price]);
    
    $response['success'] = true;
    $response['message'] = 'Pricing added successfully.';
    $response['ship_id'] = $ship_id;
} catch (Exception $e) {
    $response['message'] = 'Error adding pricing: ' . $e->getMessage();
}

echo json_encode($response);
