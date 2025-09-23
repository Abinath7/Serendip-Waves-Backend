<?php
/**
 * Remove unnecessary session_id column from facility_preferences table
 * The session_id was added to solve the unique constraint issue, but just removing
 * the constraint is sufficient. The auto-increment id already serves as unique identifier.
 */

require_once './DbConnector.php';

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    echo "Starting session_id column removal...\n";
    
    // Check if session_id column exists
    $checkColumn = "SHOW COLUMNS FROM facility_preferences LIKE 'session_id'";
    $result = $pdo->query($checkColumn);
    
    if ($result->rowCount() > 0) {
        echo "Found session_id column. Removing it...\n";
        
        // Drop the session_id column
        $dropColumn = "ALTER TABLE facility_preferences DROP COLUMN session_id";
        $pdo->exec($dropColumn);
        
        echo "Successfully removed session_id column.\n";
    } else {
        echo "session_id column not found. No action needed.\n";
    }
    
    echo "Table cleanup completed successfully!\n";
    echo "Multiple facility bookings are still allowed without session_id.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Please check your database connection and try again.\n";
}
?>