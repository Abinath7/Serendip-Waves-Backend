<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$conn = new mysqli("localhost", "root", "", "serendip");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit();
}

// Get ship identifier and route from query parameters - supports both ship_id and ship_name
$ship_id = $_GET['ship_id'] ?? '';
$ship_name = $_GET['ship_name'] ?? '';
$route = $_GET['route'] ?? '';

if ((empty($ship_id) && empty($ship_name)) || empty($route)) {
    echo json_encode(["success" => false, "message" => "Ship identifier (ship_id or ship_name) and route are required"]);
    exit();
}

try {
    // Get ship details to understand capacity - prefer ship_id over ship_name
    if (!empty($ship_id)) {
        $ship_sql = "SELECT ship_id, ship_name, passenger_count FROM ship_details WHERE ship_id = ?";
        $ship_stmt = $conn->prepare($ship_sql);
        $ship_stmt->bind_param("i", $ship_id);
    } else {
        $ship_sql = "SELECT ship_id, ship_name, passenger_count FROM ship_details WHERE ship_name = ?";
        $ship_stmt = $conn->prepare($ship_sql);
        $ship_stmt->bind_param("s", $ship_name);
    }
    
    $ship_stmt->execute();
    $ship_result = $ship_stmt->get_result();
    $ship_data = $ship_result->fetch_assoc();
    
    if (!$ship_data) {
        echo json_encode(["success" => false, "message" => "Ship not found"]);
        exit();
    }
    
    $ship_id = $ship_data['ship_id'];
    $ship_name = $ship_data['ship_name'];
    $total_passengers = (int)$ship_data['passenger_count'];
    
    // Calculate cabin capacity based on passenger capacity
    // Typical distribution: Interior 40%, Ocean View 30%, Balcony 25%, Suite 5%
    // Maximum 4 passengers per cabin
    $defaultCapacity = [
        'Interior' => (int)($total_passengers * 0.40 / 4), // Assuming max 4 passengers per cabin
        'Ocean View' => (int)($total_passengers * 0.30 / 4),
        'Balcony' => (int)($total_passengers * 0.25 / 4),
        'Suite' => (int)($total_passengers * 0.05 / 4)
    ];
    
    // Get booked cabins for this ship and route - prefer ship_id
    $sql = "SELECT room_type, COUNT(*) as booked_count 
            FROM booking_overview 
            WHERE (ship_id = ? OR ship_name = ?) AND destination = ?
            GROUP BY room_type";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $ship_id, $ship_name, $route);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookedCabins = [];
    while ($row = $result->fetch_assoc()) {
        $bookedCabins[$row['room_type']] = (int)$row['booked_count'];
    }
    
    // Calculate available cabins
    $availability = [];
    foreach ($defaultCapacity as $cabinType => $totalCapacity) {
        $booked = $bookedCabins[$cabinType] ?? 0;
        $available = max(0, $totalCapacity - $booked);
        
        $availability[] = [
            'cabin_type' => $cabinType,
            'total_capacity' => $totalCapacity,
            'booked' => $booked,
            'available' => $available,
            'availability_percentage' => $totalCapacity > 0 ? round(($available / $totalCapacity) * 100, 1) : 0
        ];
    }
    
    echo json_encode([
        "success" => true,
        "ship_id" => $ship_id,
        "ship_name" => $ship_name,
        "route" => $route,
        "total_passenger_capacity" => $total_passengers,
        "cabin_availability" => $availability
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error fetching cabin availability: " . $e->getMessage()
    ]);
}

$conn->close();
?>
