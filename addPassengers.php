<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
require_once __DIR__ . '/DbConnector.php';

$data = json_decode(file_get_contents('php://input'), true);

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();

    $required = ['booking_id', 'route', 'cabin_id', 'passengerList'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
            exit();
        }
    }

    // Require either ship_id or ship_name
    if (empty($data['ship_id']) && empty($data['ship_name'])) {
        echo json_encode(['success' => false, 'message' => "Either ship_id or ship_name is required"]);
        exit();
    }

    $booking_id = $data['booking_id'];
    $ship_id = $data['ship_id'] ?? null;
    $ship_name = $data['ship_name'] ?? null;
    $route = $data['route'];
    $cabin_id = $data['cabin_id'];
    $passengerList = $data['passengerList'];

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
        // Note: We don't exit if ship_id is not found here for backward compatibility
    }

    $sql = "INSERT INTO passenger_management (
        booking_id, passenger_name, email,
        ship_id, ship_name, route, cabin_id, age, gender, citizenship,
        is_primary, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $pdo->prepare($sql);
    foreach ($passengerList as $idx => $p) {
        $is_primary = ($idx === 0) ? 1 : 0;
        $stmt->execute([
            $booking_id,
            $p['passenger_name'],
            $p['email'],
            $ship_id,
            $ship_name,
            $route,
            $cabin_id,
            $p['age'],
            $p['gender'],
            $p['citizenship'],
            $is_primary
        ]);
    }
    echo json_encode(['success' => true, 'message' => 'Passengers added successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>




