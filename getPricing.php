<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/Main Classes/Booking.php';

try {
    // Get query parameters - support both ship_id and ship_name
    $ship_id = isset($_GET['ship_id']) ? (int)$_GET['ship_id'] : 0;
    $ship_name = $_GET['ship_name'] ?? '';
    $route = $_GET['route'] ?? '';
    $room_type = $_GET['room_type'] ?? '';
    $number_of_guests = intval($_GET['number_of_guests'] ?? 1);
    
    // Require either ship_id or ship_name
    if (empty($ship_id) && empty($ship_name)) {
        echo json_encode(['success' => false, 'message' => 'Either ship_id or ship_name parameter is required']);
        exit();
    }
    
    if (empty($route)) {
        echo json_encode(['success' => false, 'message' => 'route parameter is required']);
        exit();
    }
    
    $booking = new Booking();
    
    // Use ship_id if provided, otherwise use ship_name
    $ship_identifier = $ship_id ? $ship_id : $ship_name;
    
    // Get pricing for the ship/route (used by both specific and all room types)
    $pricing_result = $booking->getPricingForShipRoute($ship_identifier, $route);
    
    if (!$pricing_result['success']) {
        echo json_encode($pricing_result);
        exit();
    }
    
    $pricing = $pricing_result['pricing'];
    
    if (!empty($room_type)) {
        // Get pricing for specific room type
        
        // Map room types to prices
        $price_map = [
            'Interior' => $pricing['interior_price'],
            'Ocean View' => $pricing['ocean_view_price'],
            'Balcony' => $pricing['balcony_price'],
            'Suite' => $pricing['suite_price']
        ];
        
        if (!isset($price_map[$room_type])) {
            echo json_encode(['success' => false, 'message' => 'Invalid room type']);
            exit();
        }
        
        $base_price = floatval($price_map[$room_type]);
        $total_price = $base_price * $number_of_guests;
        
        echo json_encode([
            'success' => true,
            'ship_id' => $ship_id,
            'ship_name' => $ship_name,
            'route' => $route,
            'room_type' => $room_type,
            'base_price_per_person' => $base_price,
            'number_of_guests' => $number_of_guests,
            'total_amount' => $total_price
        ]);
        
    } else {
        // Use the already fetched pricing for all room types
        echo json_encode([
            'success' => true,
            'ship_id' => $ship_id,
            'ship_name' => $ship_name,
            'route' => $route,
            'pricing' => [
                    'interior' => [
                        'price_per_person' => floatval($pricing['interior_price']),
                        'total_for_guests' => floatval($pricing['interior_price']) * $number_of_guests
                    ],
                    'ocean_view' => [
                        'price_per_person' => floatval($pricing['ocean_view_price']),
                        'total_for_guests' => floatval($pricing['ocean_view_price']) * $number_of_guests
                    ],
                    'balcony' => [
                        'price_per_person' => floatval($pricing['balcony_price']),
                        'total_for_guests' => floatval($pricing['balcony_price']) * $number_of_guests
                    ],
                    'suite' => [
                        'price_per_person' => floatval($pricing['suite_price']),
                        'total_for_guests' => floatval($pricing['suite_price']) * $number_of_guests
                    ]
                ],
                'number_of_guests' => $number_of_guests
            ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
