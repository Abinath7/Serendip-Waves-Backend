<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Accept multipart/form-data
$id = $_POST['id'] ?? '';
$ship_name = $_POST['ship_name'] ?? '';
$route = $_POST['route'] ?? '';
$departure_port = $_POST['departure_port'] ?? '';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$notes = $_POST['notes'] ?? '';

if (empty($id) || empty($ship_name) || empty($route) || empty($departure_port) || empty($start_date) || empty($end_date)) {
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

// Handle image upload if a new file is provided
$country_image_path = '';
$updateImage = false;
if (isset($_FILES['country_image']) && $_FILES['country_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'country_images/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $file_extension = strtolower(pathinfo($_FILES['country_image']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($file_extension, $allowed_extensions)) {
        $filename = uniqid() . '.' . $file_extension;
        $filepath = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['country_image']['tmp_name'], $filepath)) {
            $country_image_path = $filepath;
            $updateImage = true;
        }
    }
}

// Check if country_image column exists
$checkColumn = $conn->query("SHOW COLUMNS FROM itineraries LIKE 'country_image'");
$hasCountryImage = $checkColumn->num_rows > 0;

if ($hasCountryImage && $updateImage) {
    $stmt = $conn->prepare("UPDATE itineraries SET ship_id=?, ship_name=?, route=?, departure_port=?, start_date=?, end_date=?, notes=?, country_image=? WHERE id=?");
    $stmt->bind_param("isssssssi", $ship_id, $ship_name, $route, $departure_port, $start_date, $end_date, $notes, $country_image_path, $id);
} else {
    $stmt = $conn->prepare("UPDATE itineraries SET ship_id=?, ship_name=?, route=?, departure_port=?, start_date=?, end_date=?, notes=? WHERE id=?");
    $stmt->bind_param("issssssi", $ship_id, $ship_name, $route, $departure_port, $start_date, $end_date, $notes, $id);
}

if ($stmt->execute()) {
    echo json_encode(["message" => "Itinerary updated successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Update failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?> 