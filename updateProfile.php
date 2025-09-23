<?php
/**
 * User Profile Update Endpoint
 * Handles updating user profile information with proper CORS support
 */

// Start session to update session data
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 for debugging, 0 for production

// Include CORS headers
require_once 'includes/cors_headers.php';

require_once 'DbConnector.php';

// Initialize database connection
try {
    $db = (new DBConnector())->connect();
} catch (Exception $e) {
    sendErrorResponse('Database connection failed: ' . $e->getMessage(), 500);
}

/**
 * Update user profile in database
 * Note: phone_number and country are excluded for security reasons
 */
function updateProfile($db, $userId, $fullName, $email, $dateOfBirth, $gender, $passportNumber) {
    try {
        // Handle empty date of birth
        $dateOfBirth = !empty($dateOfBirth) ? $dateOfBirth : null;
        
        // Exclude phone_number and country from updates for security reasons
        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, date_of_birth = ?, gender = ?, passport_number = ? WHERE id = ?");
        $result = $stmt->execute([$fullName, $email, $dateOfBirth, $gender, $passportNumber, $userId]);
        
        if (!$result) {
            error_log("Profile update failed for user ID: $userId");
            return false;
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("SQL Error in updateProfile: " . $e->getMessage());
        return false;
    }
}

// Handle POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Invalid request method. POST required.', 405);
}

// Debug: Log what we received
error_log("updateProfile.php - Received POST data:");
error_log("POST: " . print_r($_POST, true));
error_log("FILES: " . print_r($_FILES, true));

// Extract and validate input data
$userId = intval($_POST['id'] ?? 0);
$fullName = trim($_POST['full_name'] ?? '');
$dateOfBirth = trim($_POST['date_of_birth'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$passportNumber = trim($_POST['passport_number'] ?? '');
// Note: phone_number and country are intentionally excluded from updates

// Debug: Log extracted values
error_log("Extracted values - User ID: $userId, Full Name: '$fullName'");

// Validate required fields
if ($userId <= 0) {
    sendErrorResponse('Invalid user ID provided');
}

if (empty($fullName)) {
    sendErrorResponse('Full name is required');
}

// Check if user exists and get current email
try {
    $stmt = $db->prepare("SELECT id, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingUser) {
        sendErrorResponse('User not found', 404);
    }
    
    // Use the existing email from database (users cannot change email)
    $email = $existingUser['email'];
} catch (PDOException $e) {
    error_log("Error checking user existence: " . $e->getMessage());
    sendErrorResponse('Database error occurred', 500);
}

// Attempt to update profile
$updateResult = updateProfile($db, $userId, $fullName, $email, $dateOfBirth, $gender, $passportNumber);

if ($updateResult) {
    try {
        // Fetch updated user data (including phone_number and country for display)
        $stmt = $db->prepare("SELECT id, full_name, email, date_of_birth, gender, phone_number, passport_number, country, created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($updatedUser) {
            // Debug: Log session info
            error_log("Session ID: " . session_id());
            error_log("Session user before update: " . print_r($_SESSION['user'] ?? 'No session user', true));
            
            // Update session data with the new user information
            if (isset($_SESSION['user']) && $_SESSION['user']['id'] == $userId) {
                $_SESSION['user'] = array_merge($_SESSION['user'], $updatedUser);
                error_log("Session updated for user ID: $userId");
                error_log("Session user after update: " . print_r($_SESSION['user'], true));
            } else {
                error_log("No matching session found for user ID: $userId");
                error_log("Current session user ID: " . ($_SESSION['user']['id'] ?? 'No session'));
            }
            
            sendSuccessResponse($updatedUser, 'Profile updated successfully');
        } else {
            sendErrorResponse('Failed to retrieve updated profile data');
        }
    } catch (PDOException $e) {
        error_log("Error fetching updated user data: " . $e->getMessage());
        sendErrorResponse('Profile updated but failed to retrieve updated data');
    }
} else {
    sendErrorResponse('Failed to update profile. Please try again.');
}
?>