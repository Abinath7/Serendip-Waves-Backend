<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once './DbConnector.php';
require_once './Main Classes/Mailer.php';

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    $bookingId = $input['booking_id'] ?? '';
    $action = $input['action'] ?? ''; // 'confirm', 'save_pending', 'cancel'
    $selectedFacilities = $input['selected_facilities'] ?? [];
    $quantities = $input['quantities'] ?? [];
    $totalCost = $input['total_cost'] ?? 0;
    $passengerEmail = $input['passenger_email'] ?? '';
    $passengerName = $input['passenger_name'] ?? '';
    $cardDetails = $input['card_details'] ?? null;
    
    if (!$bookingId || !$action) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields'
        ]);
        exit();
    }
    
    // Create facility_preferences table if it doesn't exist
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS facility_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id VARCHAR(50) NOT NULL,
            passenger_name VARCHAR(255),
            selected_facilities JSON,
            quantities JSON,
            total_cost DECIMAL(10,2) DEFAULT 0.00,
            payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
            card_num VARCHAR(20),
            card_type VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
            UNIQUE KEY unique_booking (booking_id)
        )
    ";
    $pdo->exec($createTableQuery);
    
    // Add missing columns if table already exists (for existing installations)
    try {
        // Check if card_num column exists
        $checkColumn = "SHOW COLUMNS FROM facility_preferences LIKE 'card_num'";
        $result = $pdo->query($checkColumn);
        
        if ($result->rowCount() == 0) {
            // Add the missing columns
            $pdo->exec("ALTER TABLE facility_preferences ADD COLUMN card_num VARCHAR(20) AFTER payment_status");
            $pdo->exec("ALTER TABLE facility_preferences ADD COLUMN card_type VARCHAR(50) AFTER card_num");
        }
    } catch (Exception $e) {
        // If ALTER fails, it might be because columns already exist, so continue
        error_log("Column addition warning: " . $e->getMessage());
    }
    
    // Fetch facility data from database for email content
    $facilityQuery = "SELECT facility_id, facility, unit_price FROM facilities WHERE status = 'active'";
    $facilityStmt = $pdo->prepare($facilityQuery);
    $facilityStmt->execute();
    $facilityRows = $facilityStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to associative array for easy lookup
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
    
    // Generate facility details for email
    $facilityDetails = [];
    foreach ($selectedFacilities as $facilityCode => $isSelected) {
        if ($isSelected && isset($facilityMap[$facilityCode])) {
            $quantity = $quantities[$facilityCode] ?? 1;
            $facility = $facilityMap[$facilityCode];
            $unitPrice = $facility['price'];
            $totalPrice = $unitPrice * $quantity;
            
            $facilityDetails[] = [
                'name' => $facility['name'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'unit' => $facility['unit']
            ];
        }
    }
    
    $response = ['success' => false, 'message' => ''];
    
    switch ($action) {
        case 'confirm':
            // Process card details if provided
            $cardNum = '';
            $cardType = '';
            
            if ($cardDetails && isset($cardDetails['cardNumber'])) {
                // Extract last 4 digits for storage
                $cardNumber = preg_replace('/\s+/', '', $cardDetails['cardNumber']);
                $cardNum = '**** **** **** ' . substr($cardNumber, -4);
                
                // Use selected card type from frontend, with fallback to detection
                if (isset($cardDetails['cardType'])) {
                    $cardType = ucfirst($cardDetails['cardType']); // visa -> Visa, mastercard -> Mastercard
                } else {
                    // Fallback: Determine card type based on first digit
                    $firstDigit = substr($cardNumber, 0, 1);
                    switch ($firstDigit) {
                        case '4':
                            $cardType = 'Visa';
                            break;
                        case '5':
                            $cardType = 'Mastercard';
                            break;
                        case '3':
                            $cardType = 'American Express';
                            break;
                        default:
                            $cardType = 'Unknown';
                    }
                }
            }
            
            // Update all pending records for this booking to paid status
            $updateQuery = "
                UPDATE facility_preferences 
                SET payment_status = 'paid', status = 'paid', card_num = ?, card_type = ?, updated_at = NOW()
                WHERE booking_id = ? AND payment_status = 'pending'
            ";
            $updateStmt = $pdo->prepare($updateQuery);
            $result = $updateStmt->execute([$cardNum, $cardType, $bookingId]);
            
            if (!$result) {
                throw new Exception('Failed to update payment status');
            }
            
            $updatedRecords = $updateStmt->rowCount();
            if ($updatedRecords === 0) {
                // No pending records found - this could be direct payment or additional facilities
                
                // Get all existing records for this booking
                $allRecordsQuery = "SELECT selected_facilities, payment_status FROM facility_preferences WHERE booking_id = ?";
                $allStmt = $pdo->prepare($allRecordsQuery);
                $allStmt->execute([$bookingId]);
                $existingRecords = $allStmt->fetchAll();
                
                if (count($existingRecords) > 0) {
                    // Existing records found - check if user is trying to book NEW facilities or same ones
                    
                    // Collect all already paid facilities
                    $alreadyPaidFacilities = [];
                    foreach ($existingRecords as $record) {
                        if ($record['payment_status'] === 'paid') {
                            $facilities = json_decode($record['selected_facilities'], true) ?: [];
                            foreach ($facilities as $facilityCode => $isSelected) {
                                if ($isSelected) {
                                    $alreadyPaidFacilities[$facilityCode] = true;
                                }
                            }
                        }
                    }
                    
                    // Check if user is trying to book facilities they already paid for
                    $duplicateFacilities = [];
                    foreach ($selectedFacilities as $facilityCode => $isSelected) {
                        if ($isSelected && isset($alreadyPaidFacilities[$facilityCode])) {
                            $facilityName = $facilityMap[$facilityCode]['name'] ?? $facilityCode;
                            $duplicateFacilities[] = $facilityName;
                        }
                    }
                    
                    if (!empty($duplicateFacilities)) {
                        // User is trying to book facilities they already paid for
                        echo json_encode([
                            'success' => false,
                            'message' => 'You have already paid for the following facilities: ' . implode(', ', $duplicateFacilities) . '. Please select different facilities.',
                            'already_paid_facilities' => $duplicateFacilities,
                            'duplicate_booking' => true
                        ]);
                        exit();
                    }
                    
                    // User is booking NEW facilities - allow this as additional booking
                    // Fall through to create new booking logic below
                }
                
                // DIRECT PAYMENT SCENARIO: Create new booking for new facilities and pay immediately
                
                // First create the booking as pending
                $insertQuery = "
                    INSERT INTO facility_preferences (booking_id, passenger_name, selected_facilities, quantities, total_cost, payment_status, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', 'pending', NOW(), NOW())
                ";
                $insertStmt = $pdo->prepare($insertQuery);
                $insertResult = $insertStmt->execute([
                    $bookingId,
                    $passengerName,
                    json_encode($selectedFacilities),
                    json_encode($quantities),
                    $totalCost
                ]);
                
                if (!$insertResult) {
                    throw new Exception('Failed to create facility booking for direct payment.');
                }
                
                // Now immediately update it to paid status
                $directPaymentQuery = "
                    UPDATE facility_preferences 
                    SET payment_status = 'paid', status = 'paid', card_num = ?, card_type = ?, updated_at = NOW()
                    WHERE booking_id = ? AND payment_status = 'pending'
                ";
                $directPaymentStmt = $pdo->prepare($directPaymentQuery);
                $directPaymentResult = $directPaymentStmt->execute([$cardNum, $cardType, $bookingId]);
                
                if (!$directPaymentResult) {
                    throw new Exception('Failed to process direct payment.');
                }
                
                $updatedRecords = $directPaymentStmt->rowCount();
                
                if ($updatedRecords === 0) {
                    throw new Exception('Failed to confirm direct payment - no records updated.');
                }
            }
            
            // Get all confirmed facilities for this booking to send in email
            $getAllQuery = "SELECT * FROM facility_preferences WHERE booking_id = ? ORDER BY created_at";
            $getAllStmt = $pdo->prepare($getAllQuery);
            $getAllStmt->execute([$bookingId]);
            $allRecords = $getAllStmt->fetchAll();
            
            // Calculate facility details for email from all records
            $allFacilityDetails = [];
            $totalPaidCost = 0;
            
            foreach ($allRecords as $record) {
                $facilities = json_decode($record['selected_facilities'], true) ?: [];
                $quantities = json_decode($record['quantities'], true) ?: [];
                
                foreach ($facilities as $facilityCode => $isSelected) {
                    if ($isSelected && isset($facilityMap[$facilityCode])) {
                        $quantity = $quantities[$facilityCode] ?? 1;
                        $facilityPrice = $facilityMap[$facilityCode]['price'];
                        $totalPrice = $facilityPrice * $quantity;
                        
                        // Add or update facility in the list
                        if (!isset($allFacilityDetails[$facilityCode])) {
                            $allFacilityDetails[$facilityCode] = [
                                'name' => $facilityMap[$facilityCode]['name'],
                                'quantity' => 0,
                                'unit_price' => $facilityPrice,
                                'total_price' => 0,
                                'unit' => $facilityMap[$facilityCode]['unit'] ?? 'access'
                            ];
                        }
                        $allFacilityDetails[$facilityCode]['quantity'] += $quantity;
                        $allFacilityDetails[$facilityCode]['total_price'] += $totalPrice;
                    }
                }
                
                if ($record['payment_status'] === 'paid') {
                    $totalPaidCost += $record['total_cost'];
                }
            }
            
            // Convert associative array to indexed array for email generation
            $facilityDetailsForEmail = array_values($allFacilityDetails);
            
            // Send confirmation email
            $emailSubject = "Facility Booking Payment Confirmation - Serendip Waves";
            $emailBody = generateConfirmationEmail($passengerName, $bookingId, $facilityDetailsForEmail, $totalPaidCost, $cardNum, $cardType);
            
            $response['success'] = true;
            $response['message'] = "Payment confirmed for $updatedRecords facility booking(s). Total paid: $" . number_format($totalPaidCost, 2);
            break;
            
        case 'save_pending':
            // Save as pending
            $paymentStatus = 'pending';
            
            // Check what facilities are already PAID for this booking_id across all sessions
            $checkQuery = "SELECT selected_facilities, quantities, payment_status, total_cost FROM facility_preferences WHERE booking_id = ?";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->execute([$bookingId]);
            $existingRecords = $checkStmt->fetchAll();
            
            // Check if there are any pending amounts - if so, block new facility additions
            $pendingAmount = 0;
            foreach ($existingRecords as $record) {
                if ($record['payment_status'] === 'pending') {
                    $pendingAmount += $record['total_cost'];
                }
            }
            
            if ($pendingAmount > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You have pending facility bookings totaling $' . number_format($pendingAmount, 2) . '. Please complete payment for existing bookings before adding new facilities.',
                    'pending_amount' => $pendingAmount,
                    'action_required' => 'complete_payment'
                ]);
                exit();
            }
            
            // Collect only PAID facilities across all sessions
            $paidFacilities = [];
            foreach ($existingRecords as $record) {
                // Only check facilities that have been paid for
                if ($record['payment_status'] === 'paid') {
                    $facilities = json_decode($record['selected_facilities'], true) ?: [];
                    foreach ($facilities as $facilityCode => $isSelected) {
                        if ($isSelected) {
                            $paidFacilities[$facilityCode] = true;
                        }
                    }
                }
            }
            
            // Check for facilities that are already paid for in the new booking request
            $alreadyPaidFacilities = [];
            foreach ($selectedFacilities as $facilityCode => $isSelected) {
                if ($isSelected && isset($paidFacilities[$facilityCode])) {
                    $facilityName = $facilityMap[$facilityCode]['name'] ?? $facilityCode;
                    $alreadyPaidFacilities[] = $facilityName;
                }
            }
            
            // If there are paid facilities being requested again, reject the booking
            if (!empty($alreadyPaidFacilities)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You have already paid for the following facilities and cannot book them again: ' . implode(', ', $alreadyPaidFacilities) . '. Please remove them from your selection.',
                    'already_paid_facilities' => $alreadyPaidFacilities
                ]);
                exit();
            }
            
            // No duplicates found, proceed to add new facility booking as separate session
            // Always insert new record to create separate booking sessions
            $insertQuery = "
                INSERT INTO facility_preferences (booking_id, passenger_name, selected_facilities, quantities, total_cost, payment_status, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ";
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->execute([
                $bookingId,
                $passengerName,
                json_encode($selectedFacilities),
                json_encode($quantities),
                $totalCost,
                $paymentStatus,
                $paymentStatus
            ]);
            
            // Send pending booking email
            $emailSubject = "Facility Booking Saved - Payment Pending - Serendip Waves";
            $emailBody = generatePendingEmail($passengerName, $bookingId, $facilityDetails, $totalCost);
            
            $response['success'] = true;
            $response['message'] = 'Facility preferences saved as pending!';
            break;
            
        case 'cancel':
            // Update status to cancelled or delete record
            $checkQuery = "SELECT id FROM facility_preferences WHERE booking_id = ?";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->execute([$bookingId]);
            $exists = $checkStmt->fetch();
            
            if ($exists) {
                $updateQuery = "UPDATE facility_preferences SET payment_status = 'cancelled', status = 'cancelled' WHERE booking_id = ?";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute([$bookingId]);
            }
            
            // Send cancellation email
            $emailSubject = "Facility Booking Cancelled - Serendip Waves";
            $emailBody = generateCancellationEmail($passengerName, $bookingId, $facilityDetails);
            
            $response['success'] = true;
            $response['message'] = 'Facility booking cancelled successfully!';
            break;
            
        default:
            $response['message'] = 'Invalid action';
            echo json_encode($response);
            exit();
    }
    
    // Send email if passenger email is provided
    if ($passengerEmail && !empty($facilityDetails)) {
        try {
            $mailer = new Mailer();
            $mailer->setInfo($passengerEmail, $emailSubject, $emailBody);
            $emailSent = $mailer->send();
            
            if ($emailSent) {
                $response['email_sent'] = true;
            } else {
                $response['email_sent'] = false;
                $response['email_error'] = 'Failed to send email notification';
            }
        } catch (Exception $e) {
            $response['email_sent'] = false;
            $response['email_error'] = 'Email error: ' . $e->getMessage();
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

// Email template functions
function generateConfirmationEmail($passengerName, $bookingId, $facilityDetails, $totalCost, $cardNum = '', $cardType = '') {
    $facilitiesHtml = '';
    foreach ($facilityDetails as $facility) {
        $unitText = $facility['unit_price'] > 0 ? 'per ' . ($facility['unit'] ?? 'access') : 'Free';
        $facilitiesHtml .= "
            <tr>
                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$facility['name']}</td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: center;'>{$facility['quantity']}</td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>$" . number_format($facility['unit_price'], 2) . " {$unitText}</td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>$" . number_format($facility['total_price'], 2) . "</td>
            </tr>
        ";
    }
    
    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .facilities-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .facilities-table th { background-color: #007bff; color: white; padding: 10px; text-align: left; }
            .total { font-weight: bold; font-size: 18px; color: #007bff; }
            .footer { background-color: #6c757d; color: white; padding: 15px; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🌊 Facility Booking Confirmed!</h1>
                <p>Serendip Waves Cruise</p>
            </div>
            <div class='content'>
                <h2>Dear {$passengerName},</h2>
                <p>Great news! Your facility booking has been <strong>confirmed and paid</strong> for booking ID: <strong>{$bookingId}</strong></p>
                
                <h3>✅ Confirmed Facilities:</h3>
                <table class='facilities-table'>
                    <thead>
                        <tr>
                            <th>Facility</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$facilitiesHtml}
                    </tbody>
                </table>
                
                <div class='total'>
                    <p>Total Amount Paid: $" . number_format($totalCost, 2) . "</p>
                </div>" . 
                ($cardNum ? "
                
                <h3>💳 Payment Details:</h3>
                <div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                    <p><strong>Card Used:</strong> " . $cardType . " " . $cardNum . "</p>
                    <p><strong>Payment Status:</strong> ✅ Confirmed</p>
                </div>" : "") . "
                
                <p>🎉 You're all set! Please present this confirmation email at the facility reception on board.</p>
                <p>If you have any questions, please contact our customer service team.</p>
            </div>
            <div class='footer'>
                <p>Thank you for choosing Serendip Waves!</p>
                <p>Have a wonderful cruise experience! 🚢</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

function generatePendingEmail($passengerName, $bookingId, $facilityDetails, $totalCost) {
    $facilitiesHtml = '';
    foreach ($facilityDetails as $facility) {
        $unitText = $facility['unit_price'] > 0 ? 'per ' . $facility['unit'] : 'Free';
        $facilitiesHtml .= "
            <tr>
                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$facility['name']}</td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: center;'>{$facility['quantity']}</td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>$" . number_format($facility['unit_price'], 2) . " {$unitText}</td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>$" . number_format($facility['total_price'], 2) . "</td>
            </tr>
        ";
    }
    
    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #ffc107; color: #333; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .booking-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .booking-table th { background-color: #ffc107; color: #333; padding: 10px; text-align: left; }
            .booking-table td { padding: 8px; border-bottom: 1px solid #ddd; }
            .total { font-weight: bold; font-size: 18px; color: #ffc107; }
            .footer { background-color: #6c757d; color: white; padding: 15px; text-align: center; }
            .info-box { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .warning-box { background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 15px 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>⏳ Facility Booking Saved!</h1>
                <p>Serendip Waves Cruise</p>
            </div>
            <div class='content'>
                <h2>Dear {$passengerName},</h2>
                <p>Great news! Your facility preferences have been <strong>successfully saved</strong> for booking ID: <strong>{$bookingId}</strong></p>
                
                <div class='warning-box'>
                    <h3>⚠️ Payment Required to Confirm</h3>
                    <p><strong>Your booking is currently pending payment.</strong> Please complete the payment to confirm and secure your selected facilities for your cruise experience.</p>
                </div>
                
                <h3>🎯 Your Selected Facilities:</h3>
                <table class='booking-table'>
                    <thead>
                        <tr>
                            <th>Facility</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$facilitiesHtml}
                    </tbody>
                    <tfoot>
                        <tr style='background-color: #fff3cd;'>
                            <th colspan='3' style='text-align: right; padding: 10px;'>Total Amount Due:</th>
                            <th style='text-align: right; padding: 10px; color: #856404;'>$" . number_format($totalCost, 2) . "</th>
                        </tr>
                    </tfoot>
                </table>
                
                <div class='info-box'>
                    <h3>🎉 What's Next?</h3>
                    <p>• Complete your payment to confirm these facilities<br>
                    • You can modify your selection anytime before payment<br>
                    • Visit your customer dashboard to manage your booking<br>
                    • Contact us if you need assistance with your booking</p>
                </div>
                
                <div class='info-box'>
                    <h3>Important Notes:</h3>
                    <p>• Your preferences are saved but not yet confirmed<br>
                    • Facilities are subject to availability until payment is completed<br>
                    • Complete payment soon to avoid disappointment<br>
                    • You can update your preferences anytime before payment</p>
                </div>
                
                <p>💳 Ready to confirm? Complete your payment to secure these amazing facilities for your cruise adventure!</p>
            </div>
            <div class='footer'>
                <p>Best regards,<br>
                <strong>Serendip Waves Facilities Team</strong><br>
                📞 +94771234567<br>
                📧 facilities@serendipwaves.com<br>
                🌐 www.serendipwaves.com</p>
                <p>Thank you for choosing Serendip Waves!</p>
                <p>Complete your payment soon to secure your facilities! 🚢</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

function generateCancellationEmail($passengerName, $bookingId, $facilityDetails) {
    $facilitiesHtml = '';
    foreach ($facilityDetails as $facility) {
        $facilitiesHtml .= "<li>{$facility['name']} (Quantity: {$facility['quantity']})</li>";
    }
    
    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .footer { background-color: #6c757d; color: white; padding: 15px; text-align: center; }
            .info { background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; margin: 15px 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>❌ Facility Booking Cancelled</h1>
                <p>Serendip Waves Cruise</p>
            </div>
            <div class='content'>
                <h2>Dear {$passengerName},</h2>
                <p>Your facility booking has been <strong>cancelled</strong> for booking ID: <strong>{$bookingId}</strong></p>
                
                <h3>🚫 Cancelled Facilities:</h3>
                <ul>
                    {$facilitiesHtml}
                </ul>
                
                <div class='info'>
                    <h3>ℹ️ What happens next?</h3>
                    <p>• If you paid for these facilities, a refund will be processed within 5-7 business days</p>
                    <p>• You can still book new facilities anytime before your cruise departure</p>
                    <p>• Contact our customer service if you need assistance</p>
                </div>
                
                <p>We're sorry to see you cancel these facilities. If you change your mind, you can always make a new booking!</p>
            </div>
            <div class='footer'>
                <p>Thank you for choosing Serendip Waves!</p>
                <p>We hope you still have a wonderful cruise experience! 🚢</p>
            </div>
        </div>
    </body>
    </html>
    ";
}
?>
