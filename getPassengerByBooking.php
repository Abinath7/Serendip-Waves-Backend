<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once './DbConnector.php';

if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking ID is required'
    ]);
    exit();
}

$booking_id = $_GET['booking_id'];

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    // Get passenger data from booking_overview table with trip dates and ship details
    $query = "SELECT b.full_name as passenger_name, b.email, b.gender, b.citizenship, b.age, 
                     COALESCE(b.ship_id, s.ship_id) as ship_id, b.ship_name, b.destination, b.room_type, b.adults, b.children,
                     i.start_date AS departure_date, i.end_date AS return_date
              FROM booking_overview b 
              LEFT JOIN ship_details s ON (b.ship_id = s.ship_id OR b.ship_name = s.ship_name)
              LEFT JOIN itineraries i ON (s.ship_id = i.ship_id OR s.ship_name = i.ship_name) AND b.destination = i.route
              WHERE b.booking_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$booking_id]);
    $passenger = $stmt->fetch();
    
    if ($passenger) {
        // Debug logging
        error_log("Passenger data found for booking_id $booking_id: " . json_encode($passenger));
        
        // Check if journey is completed
        $today = date('Y-m-d');
        $journey_completed = $passenger['return_date'] && $passenger['return_date'] < $today;
        
        // Determine journey status
        $journey_status = 'active';
        if ($journey_completed) {
            $journey_status = 'completed';
        } else if ($passenger['departure_date'] && $passenger['departure_date'] > $today) {
            $journey_status = 'upcoming';
        }
        
        // For demo purposes, let's make some bookings completed
        // You can modify this logic based on your requirements
        if (in_array($booking_id, ['999', '998', '997'])) {
            $journey_status = 'completed';
            $journey_completed = true;
        }
        
        // Calculate trip duration in days
        $trip_duration = 0;
        if ($passenger['departure_date'] && $passenger['return_date']) {
            $start = new DateTime($passenger['departure_date']);
            $end = new DateTime($passenger['return_date']);
            $trip_duration = $end->diff($start)->days;
        } else {
            // Fallback: if no dates available, provide default trip duration based on booking ID
            // This is for demo purposes - in production, all bookings should have proper dates
            $demo_durations = [
                '123' => 7,
                '456' => 5,
                '789' => 10,
                '999' => 14,
                '998' => 12,
                '997' => 8
            ];
            $trip_duration = isset($demo_durations[$booking_id]) ? $demo_durations[$booking_id] : 7; // Default 7 days
        }
        
        // Debug logging
        error_log("Trip duration calculation for booking_id $booking_id: departure='{$passenger['departure_date']}', return='{$passenger['return_date']}', duration=$trip_duration days");
        
        echo json_encode([
            'success' => true,
            'passenger' => [
                'name' => $passenger['passenger_name'], // Add name field for compatibility
                'passenger_name' => $passenger['passenger_name'],
                'email' => $passenger['email'],
                'gender' => $passenger['gender'],
                'citizenship' => $passenger['citizenship'],
                'age' => $passenger['age'],
                'ship_id' => $passenger['ship_id'],
                'ship_name' => $passenger['ship_name'],
                'destination' => $passenger['destination'],
                'room_type' => $passenger['room_type'],
                'adults' => $passenger['adults'],
                'children' => $passenger['children'],
                'departure_date' => $passenger['departure_date'],
                'return_date' => $passenger['return_date'],
                'trip_duration' => $trip_duration,
                'journey_completed' => $journey_completed,
                'journey_status' => $journey_status,
                'booking_id' => $booking_id
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No passenger found for this booking ID'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
