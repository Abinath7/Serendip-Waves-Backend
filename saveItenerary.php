<?php
// Allow CORS from your frontend port (5174)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Use $_POST for form fields and $_FILES for file upload
$ship_name = $_POST['ship_name'] ?? '';
$route = $_POST['route'] ?? '';
$departure_port = $_POST['departure_port'] ?? '';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$notes = $_POST['notes'] ?? null;

// Handle image upload
$country_image = null;
if (isset($_FILES['country_image']) && $_FILES['country_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'country_images/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $filename = uniqid() . '_' . basename($_FILES['country_image']['name']);
    $targetFile = $uploadDir . $filename;
    if (move_uploaded_file($_FILES['country_image']['tmp_name'], $targetFile)) {
        $country_image = $targetFile;
    }
}

if (empty($ship_name) || empty($route) || empty($departure_port) || empty($start_date) || empty($end_date)) {
    http_response_code(400);
    echo json_encode(["message" => "Please fill all required fields."]);
    exit();
}

$conn = new mysqli("localhost", "root", "", "serendip");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "DB connection failed: " . $conn->connect_error]);
    exit();
}

// Get ship_id from ship_details table based on ship_name
$ship_id = null;
$shipQuery = $conn->prepare("SELECT ship_id FROM ship_details WHERE ship_name = ?");
$shipQuery->bind_param("s", $ship_name);
$shipQuery->execute();
$shipResult = $shipQuery->get_result();
if ($shipRow = $shipResult->fetch_assoc()) {
    $ship_id = $shipRow['ship_id'];
}
$shipQuery->close();

if ($ship_id === null) {
    http_response_code(400);
    echo json_encode(["message" => "Ship not found: " . $ship_name]);
    exit();
}

$stmt = $conn->prepare("INSERT INTO itineraries (ship_id, ship_name, route, departure_port, start_date, end_date, notes, country_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isssssss", $ship_id, $ship_name, $route, $departure_port, $start_date, $end_date, $notes, $country_image);

if ($stmt->execute()) {
    echo json_encode(["message" => "Itinerary saved successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Save failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

