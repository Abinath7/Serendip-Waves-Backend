<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Include database connection
require_once 'DbConnector.php';

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['booking_id'])) {
        echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
        exit;
    }
    
    $booking_id = $data['booking_id'];
    
    // Validate booking ID
    if (!is_numeric($booking_id) || $booking_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
        exit;
    }
    
    $db = new DBConnector();
    $conn = $db->connect();
    
    try {
        // First, check if the booking exists
        $checkStmt = $conn->prepare("SELECT booking_id FROM booking_overview WHERE booking_id = ?");
        $checkStmt->execute([$booking_id]);
        $result = $checkStmt->fetch();
        
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
            exit;
        }
        
        // Delete related passengers first (if there's a passenger_management table)
        $deletePassengersStmt = $conn->prepare("DELETE FROM passenger_management WHERE booking_id = ?");
        $deletePassengersStmt->execute([$booking_id]);
        
        // Delete the booking
        $deleteBookingStmt = $conn->prepare("DELETE FROM booking_overview WHERE booking_id = ?");
        $deleteBookingStmt->execute([$booking_id]);
        
        if ($deleteBookingStmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Booking deleted successfully',
                'booking_id' => $booking_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete booking']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
