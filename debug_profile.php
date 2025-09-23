<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5174');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

require_once 'DbConnector.php';

try {
    $db = (new DBConnector())->connect();
    echo json_encode([
        'status' => 'success',
        'message' => 'Database connection successful',
        'session_id' => session_id(),
        'session_data' => $_SESSION ?? 'No session data',
        'post_data' => $_POST ?? 'No POST data'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
}
?>