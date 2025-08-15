<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$conn = new mysqli("localhost", "root", "", "serendip");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "DB connection failed: " . $conn->connect_error]);
    exit();
}

// Include country_image and ship_id in the SELECT with JOIN to ship_details
$sql = "SELECT i.id, i.ship_id, i.ship_name, i.route, i.departure_port, i.start_date, i.end_date, i.notes, i.country_image,
               s.ship_id as ship_details_id, s.class as ship_class, s.year_built
        FROM itineraries i 
        LEFT JOIN ship_details s ON (i.ship_id = s.ship_id OR i.ship_name = s.ship_name)
        ORDER BY i.id DESC";
$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(["message" => "Query failed: " . $conn->error]);
    exit();
}

$itineraries = [];
while ($row = $result->fetch_assoc()) {
    // Calculate nights from start and end dates
    if (!empty($row['start_date']) && !empty($row['end_date'])) {
        $start = new DateTime($row['start_date']);
        $end = new DateTime($row['end_date']);
        $interval = $start->diff($end);
        $row['nights'] = $interval->days;
    } else {
        $row['nights'] = null;
    }
    // Add default values for frontend compatibility
    $row['flag'] = '';
    $row['description'] = '';
    $row['price'] = '';
    $itineraries[] = $row;
}

echo json_encode($itineraries);

$conn->close();
?> 