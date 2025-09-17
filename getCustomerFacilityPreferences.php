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

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    // Get booking_id from query parameter
    $bookingId = $_GET['booking_id'] ?? null;
    
    if (!$bookingId) {
        echo json_encode([
            'success' => false,
            'message' => 'Booking ID is required'
        ]);
        exit();
    }
    
    // Get facility preferences for specific customer
    $query = "
        SELECT 
            fp.id,
            fp.booking_id,
            fp.selected_facilities,
            fp.quantities,
            fp.total_cost,
            fp.payment_status,
            fp.created_at,
            fp.updated_at,
            b.full_name as passenger_name,
            b.email as passenger_email,
            b.ship_name,
            b.destination,
            b.room_type,
            b.adults,
            b.children,
            i.start_date as departure_date,
            i.end_date as return_date
        FROM facility_preferences fp
        LEFT JOIN booking_overview b ON fp.booking_id = b.booking_id
        LEFT JOIN itineraries i ON b.ship_name = i.ship_name AND b.destination = i.route
        WHERE fp.booking_id = ?
        ORDER BY fp.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$bookingId]);
    $preferences = $stmt->fetchAll();
    
    if (!$preferences || empty($preferences)) {
        echo json_encode([
            'success' => false,
            'message' => 'No facility preferences found for this booking'
        ]);
        exit();
    }
    
    // Use the first record as base and merge others
    $preference = $preferences[0];
    
    // If there are multiple records, we need to aggregate them properly
    if (count($preferences) > 1) {
        $allBookingSessions = [];
        $totalPaidAmount = 0;
        $totalPendingAmount = 0;
        $hasPaidPayment = false;
        $hasPendingPayment = false;
        
        // Collect all booking sessions with their details
        foreach ($preferences as $pref) {
            $facilities = json_decode($pref['selected_facilities'] ?? '{}', true);
            $quantities = json_decode($pref['quantities'] ?? '{}', true);
            
            $allBookingSessions[] = [
                'id' => $pref['id'],
                'facilities' => $facilities,
                'quantities' => $quantities,
                'cost' => $pref['total_cost'],
                'payment_status' => $pref['payment_status'],
                'created_at' => $pref['created_at']
            ];
            
            // Track payment amounts
            if ($pref['payment_status'] === 'paid') {
                $totalPaidAmount += $pref['total_cost'];
                $hasPaidPayment = true;
            } elseif ($pref['payment_status'] === 'pending') {
                $totalPendingAmount += $pref['total_cost'];
                $hasPendingPayment = true;
            }
        }
        
        // Store booking sessions for detailed display
        $preference['booking_sessions'] = $allBookingSessions;
        
        // Don't merge facilities - keep them separate per booking session
        // This prevents quantity aggregation issues
        $allSelectedFacilities = [];
        $allQuantities = [];
        
        // Get all unique facilities across all sessions for the selected_facilities field
        foreach ($allBookingSessions as $session) {
            foreach ($session['facilities'] as $code => $selected) {
                if ($selected) {
                    $allSelectedFacilities[$code] = true;
                    // Set quantity to 1 for compatibility - actual quantities shown in facility_details
                    $allQuantities[$code] = 1;
                }
            }
        }
        
        $preference['selected_facilities'] = json_encode($allSelectedFacilities);
        $preference['quantities'] = json_encode($allQuantities);
        $preference['total_cost'] = $totalPaidAmount + $totalPendingAmount;
        $preference['paid_amount'] = $totalPaidAmount;
        $preference['pending_amount'] = $totalPendingAmount;
        
        // Determine overall payment status
        if ($hasPendingPayment && !$hasPaidPayment) {
            $preference['payment_status'] = 'pending';
        } elseif ($hasPaidPayment && !$hasPendingPayment) {
            $preference['payment_status'] = 'paid';
        } elseif ($hasPaidPayment && $hasPendingPayment) {
            $preference['payment_status'] = 'partial'; // New status for mixed payments
        }
    } else {
        // Single record, just add paid/pending amounts for consistency
        if ($preference['payment_status'] === 'paid') {
            $preference['paid_amount'] = $preference['total_cost'];
            $preference['pending_amount'] = 0;
        } else {
            $preference['paid_amount'] = 0;
            $preference['pending_amount'] = $preference['total_cost'];
        }
    }
    
    // Process the JSON fields and add calculated fields
    $preference['selected_facilities'] = json_decode($preference['selected_facilities'] ?? '{}', true);
    $preference['quantities'] = json_decode($preference['quantities'] ?? '{}', true);
    
    // Calculate trip duration if dates are available
    if ($preference['departure_date'] && $preference['return_date']) {
        $start = new DateTime($preference['departure_date']);
        $end = new DateTime($preference['return_date']);
        $preference['trip_duration'] = $end->diff($start)->days;
    } else {
        $preference['trip_duration'] = null;
    }
    
    // Check if journey is completed
    $today = date('Y-m-d');
    $preference['journey_completed'] = $preference['return_date'] && $preference['return_date'] < $today;
    
    // Add facility details for customer dashboard display
    $facilityDetails = [];
    $selectedFacilities = $preference['selected_facilities'];
    $quantities = $preference['quantities'];
    
    // Fetch facility data from database
    $facilityQuery = "SELECT facility_id, facility, unit_price FROM facilities WHERE status = 'active'";
    $facilityStmt = $pdo->prepare($facilityQuery);
    $facilityStmt->execute();
    $facilityRows = $facilityStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Map facility codes to readable names and prices
    $facilityMap = [];
    foreach ($facilityRows as $facility) {
        // Create facility code from facility name for backward compatibility
        $facilityCode = strtolower(str_replace([' ', '&', "'"], ['_', 'and', ''], $facility['facility']));
        $facilityCode = preg_replace('/[^a-z0-9_]/', '', $facilityCode);
        
        $facilityMap[$facilityCode] = [
            'name' => $facility['facility'],
            'price' => floatval($facility['unit_price']),
            'unit' => 'access' // Default unit
        ];
    }
    
    // Add common facility mappings that might not be in database
    $commonFacilities = [
        'spa_access' => ['name' => 'Spa Access', 'price' => 30.00, 'unit' => 'access'],
        'wifi' => ['name' => 'WiFi Access', 'price' => 25.00, 'unit' => 'access'],
        'private_cabana_rental' => ['name' => 'Private Cabana Rental', 'price' => 150.00, 'unit' => 'access'],
        'specialty_dining' => ['name' => 'Specialty Dining', 'price' => 75.00, 'unit' => 'access'],
        'laundry_service' => ['name' => 'Laundry Service', 'price' => 45.00, 'unit' => 'access'],
        'room_service' => ['name' => 'Room Service', 'price' => 25.00, 'unit' => 'service']
    ];
    
    foreach ($commonFacilities as $code => $details) {
        if (!isset($facilityMap[$code])) {
            $facilityMap[$code] = $details;
        }
    }
    
    // Generate facility details from individual booking sessions
    $facilityDetails = [];
    $facilitySessionMap = []; // Track which sessions each facility appears in
    
    if (isset($preference['booking_sessions'])) {
        // Multiple booking sessions - show each facility with session details
        foreach ($preference['booking_sessions'] as $sessionIndex => $session) {
            foreach ($session['facilities'] as $facilityCode => $isSelected) {
                if ($isSelected && isset($facilityMap[$facilityCode])) {
                    $quantity = $session['quantities'][$facilityCode] ?? 1;
                    $facility = $facilityMap[$facilityCode];
                    $totalPrice = $facility['price'] * $quantity;
                    
                    $facilityDetails[] = [
                        'code' => $facilityCode,
                        'name' => $facility['name'],
                        'quantity' => $quantity,
                        'unit_price' => $facility['price'],
                        'total_price' => $totalPrice,
                        'unit' => $facility['unit'],
                        'unit_text' => $facility['price'] > 0 ? 'per ' . $facility['unit'] : 'Free',
                        'session_id' => $session['id'],
                        'session_index' => $sessionIndex + 1,
                        'payment_status' => $session['payment_status'],
                        'booked_at' => $session['created_at']
                    ];
                }
            }
        }
    } else {
        // Single booking session - use original logic
        foreach ($selectedFacilities as $facilityCode => $isSelected) {
            if ($isSelected && isset($facilityMap[$facilityCode])) {
                $quantity = $quantities[$facilityCode] ?? 1;
                $facility = $facilityMap[$facilityCode];
                $totalPrice = $facility['price'] * $quantity;
                
                $facilityDetails[] = [
                    'code' => $facilityCode,
                    'name' => $facility['name'],
                    'quantity' => $quantity,
                    'unit_price' => $facility['price'],
                    'total_price' => $totalPrice,
                    'unit' => $facility['unit'],
                    'unit_text' => $facility['price'] > 0 ? 'per ' . $facility['unit'] : 'Free',
                    'payment_status' => $preference['payment_status'],
                    'session_index' => 1
                ];
            }
        }
    }
    
    $preference['facility_details'] = $facilityDetails;
    $preference['total_facilities'] = count($facilityDetails);
    
    // Recalculate totals from facility details to ensure accuracy
    $recalculatedTotal = 0;
    $recalculatedPaid = 0;
    $recalculatedPending = 0;
    
    foreach ($facilityDetails as $facility) {
        $recalculatedTotal += $facility['total_price'];
        
        if ($facility['payment_status'] === 'paid') {
            $recalculatedPaid += $facility['total_price'];
        } elseif ($facility['payment_status'] === 'pending') {
            $recalculatedPending += $facility['total_price'];
        }
    }
    
    // Use recalculated totals instead of database stored totals
    $preference['total_cost'] = $recalculatedTotal;
    $preference['paid_amount'] = $recalculatedPaid;
    $preference['pending_amount'] = $recalculatedPending;
    
    // Check if customer can still modify preferences
    $canModify = !$preference['journey_completed'] && $preference['payment_status'] !== 'paid';
    $preference['can_modify'] = $canModify;
    
    echo json_encode([
        'success' => true,
        'preference' => $preference
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
