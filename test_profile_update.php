<?php
// Simple test file to debug the profile update issue
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo json_encode([
    'status' => 'success',
    'message' => 'Test endpoint working',
    'php_version' => phpversion(),
    'post_data' => $_POST ?? 'No POST data',
    'get_data' => $_GET ?? 'No GET data'
]);
?>