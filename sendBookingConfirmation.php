<?php
// Fix CORS: Always send headers before any output
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/Main Classes/Mailer.php';

$data = json_decode(file_get_contents("php://input"), true);

// Add debug l
// Required fields - basic fields that should always be present
$required = ['email', 'full_name', 'booking_id', 'total_price'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        echo json_encode(["success" => false, "message" => "Missing required field: $field"]);
        exit();
    }
}

// Extract common fields
$email = $data['email'];
$full_name = $data['full_name'];
$booking_id = $data['booking_id'];
$total_price = $data['total_price'];
$special_requests = $data['special_requests'] ?? 'None';
$adults = $data['adults'] ?? 1;
$children = $data['children'] ?? 0;

// Cruise booking fields
$cruise_title = $data['cruise_title'] ?? 'Luxury Cruise Experience';
$cabin_type = $data['cabin_type'] ?? '';
$cabin_number = $data['cabin_number'] ?? '';
$departure_date = $data['departure_date'] ?? '';
$return_date = $data['return_date'] ?? '';
$ship_name = $data['ship_name'] ?? '';
$destination = $data['destination'] ?? '';

// Cruise booking email template with modern HTML design
$subject = 'Your Serendip Waves Booking Confirmation';
$body = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .booking-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .booking-table th { background-color: #007bff; color: white; padding: 10px; text-align: left; }
        .booking-table td { padding: 8px; border-bottom: 1px solid #ddd; }
        .total { font-weight: bold; font-size: 18px; color: #007bff; }
        .footer { background-color: #6c757d; color: white; padding: 15px; text-align: center; }
        .info-box { background-color: #e8f4f8; border: 1px solid #bee5eb; padding: 15px; margin: 15px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🌊 Booking Confirmed!</h1>
            <p>Serendip Waves Cruise</p>
        </div>
        <div class='content'>
            <h2>Dear {$full_name},</h2>
            <p>Thank you for choosing <strong>Serendip Waves</strong> for your upcoming cruise adventure! We're thrilled to confirm your booking and look forward to giving you an unforgettable experience at sea.</p>
            
            <h3>🧾 Booking Details:</h3>
            <table class='booking-table'>
                <tr><td><strong>Booking ID:</strong></td><td>#{$booking_id}</td></tr>
                <tr><td><strong>Full Name:</strong></td><td>{$full_name}</td></tr>
                <tr><td><strong>Email:</strong></td><td>{$email}</td></tr>
                <tr><td><strong>Cruise Title:</strong></td><td>{$cruise_title}</td></tr>
                <tr><td><strong>Cabin Type:</strong></td><td>{$cabin_type}</td></tr>
                <tr><td><strong>Cabin Number:</strong></td><td>{$cabin_number}</td></tr>
                <tr><td><strong>Number of Adults:</strong></td><td>{$adults}</td></tr>
                <tr><td><strong>Number of Children:</strong></td><td>{$children}</td></tr>
                <tr><td><strong>Departure Date:</strong></td><td>{$departure_date}</td></tr>
                <tr><td><strong>Return Date:</strong></td><td>{$return_date}</td></tr>
                <tr><td><strong>Total Price:</strong></td><td>{$total_price}</td></tr>
            </table>
            
            <h3>🛳️ Cruise Information:</h3>
            <table class='booking-table'>
                <tr><td><strong>Ship Name:</strong></td><td>{$ship_name}</td></tr>
                <tr><td><strong>Destination:</strong></td><td>{$destination}</td></tr>
            </table>
            
            <div class='info-box'>
                <h3>📌 Special Requests:</h3>
                <p>{$special_requests}</p>
            </div>
            
            <div class='info-box'>
                <h3>🎉 What's Next?</h3>
                <p>• Check-in opens 2 hours before departure<br>
                • Bring a valid passport and this confirmation email<br>
                • Arrive at the port at least 90 minutes before departure<br>
                • Contact us if you need to make any changes</p>
            </div>
            
            <p>We can't wait to welcome you on board! If you have any questions or need to make changes to your booking, please don't hesitate to contact us.</p>
        </div>
        <div class='footer'>
            <p>Best regards,<br>
            <strong>Serendip Waves Booking Team</strong><br>
            📞 +94771234567<br>
            📧 info@serendipwaves.com<br>
            🌐 www.serendipwaves.com</p>
            <p>Thank you for choosing Serendip Waves!</p>
            <p>Have a wonderful cruise experience! 🚢</p>
        </div>
    </div>
</body>
</html>
";

$mailer = new Mailer();
$mailer->setInfo($email, $subject, $body);
$sent = $mailer->send();

if ($sent === true) {
    echo json_encode(["success" => true, "message" => "Booking confirmation email sent successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to send confirmation email."]);
}
?>
