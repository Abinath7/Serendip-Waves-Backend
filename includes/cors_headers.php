<?php
/**
 * Universal CORS Headers for Project-I Backend
 * Include this file at the top of any PHP file that needs CORS support
 */

// Set CORS headers
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=utf-8");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Function to send JSON response with proper headers
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// Function to handle errors consistently
function sendErrorResponse($message, $statusCode = 400) {
    sendJsonResponse(['success' => false, 'message' => $message], $statusCode);
}

// Function to handle success responses consistently
function sendSuccessResponse($data = null, $message = 'Success', $statusCode = 200) {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    sendJsonResponse($response, $statusCode);
}
?>