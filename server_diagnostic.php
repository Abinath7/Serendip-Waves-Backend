<?php
/**
 * Server Diagnostic Script for Profile Update Issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include CORS headers
require_once 'includes/cors_headers.php';

echo json_encode([
    'server_info' => [
        'php_version' => phpversion(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
        'http_origin' => $_SERVER['HTTP_ORIGIN'] ?? 'No Origin Header',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ],
    'cors_headers_sent' => [
        'access_control_allow_origin' => 'http://localhost:5173',
        'access_control_allow_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'access_control_allow_headers' => 'Content-Type, Authorization, X-Requested-With, Accept',
        'access_control_allow_credentials' => 'true'
    ],
    'database_test' => testDatabase(),
    'file_permissions' => testFilePermissions(),
    'timestamp' => date('Y-m-d H:i:s')
]);

function testDatabase() {
    try {
        require_once 'DbConnector.php';
        $db = (new DBConnector())->connect();
        
        // Test users table
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        return [
            'status' => 'success',
            'user_count' => $userCount,
            'connection' => 'active'
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

function testFilePermissions() {
    $files = [
        'updateProfile.php',
        'DbConnector.php',
        'includes/cors_headers.php'
    ];
    
    $permissions = [];
    foreach ($files as $file) {
        if (file_exists($file)) {
            $permissions[$file] = [
                'exists' => true,
                'readable' => is_readable($file),
                'writable' => is_writable($file),
                'size' => filesize($file)
            ];
        } else {
            $permissions[$file] = ['exists' => false];
        }
    }
    
    return $permissions;
}
?>