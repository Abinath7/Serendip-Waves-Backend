<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "", "serendip");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed"]);
    exit();
}

$result = $conn->query("SELECT ship_id, ship_name, passenger_count, pool_count, deck_count, restaurant_count, about_ship, ship_image, class, year_built FROM ship_details ORDER BY ship_id");
$ships = [];
while ($row = $result->fetch_assoc()) {
    $ships[] = $row;
}
echo json_encode($ships);

$conn->close();
?> 