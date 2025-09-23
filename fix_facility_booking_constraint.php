<?php
/**
 * Fix facility booking constraint issue
 * This script removes the unique constraint on booking_id to allow multiple facility booking sessions
 */

require_once './DbConnector.php';

try {
    $dbConnector = new DbConnector();
    $pdo = $dbConnector->connect();
    
    echo "Starting facility booking constraint fix...\n";
    
    // Check if the unique constraint exists
    $checkConstraint = "
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'facility_preferences' 
        AND CONSTRAINT_TYPE = 'UNIQUE'
        AND CONSTRAINT_NAME = 'unique_booking'
    ";
    
    $result = $pdo->query($checkConstraint);
    
    if ($result->rowCount() > 0) {
        echo "Found unique_booking constraint. Removing it...\n";
        
        // Drop the unique constraint
        $dropConstraint = "ALTER TABLE facility_preferences DROP INDEX unique_booking";
        $pdo->exec($dropConstraint);
        
        echo "Successfully removed unique_booking constraint.\n";
    } else {
        echo "unique_booking constraint not found. No action needed.\n";
    }
    
    // Add a session_id column if it doesn't exist to help distinguish between different facility booking sessions
    $checkSessionColumn = "SHOW COLUMNS FROM facility_preferences LIKE 'session_id'";
    $result = $pdo->query($checkSessionColumn);
    
    if ($result->rowCount() == 0) {
        echo "Adding session_id column...\n";
        $addSessionColumn = "ALTER TABLE facility_preferences ADD COLUMN session_id VARCHAR(50) DEFAULT NULL AFTER booking_id";
        $pdo->exec($addSessionColumn);
        echo "Successfully added session_id column.\n";
    } else {
        echo "session_id column already exists.\n";
    }
    
    // Update existing records to have unique session_ids
    echo "Updating existing records with session IDs...\n";
    $updateSessionIds = "
        UPDATE facility_preferences 
        SET session_id = CONCAT(booking_id, '_', id) 
        WHERE session_id IS NULL
    ";
    $pdo->exec($updateSessionIds);
    
    echo "Facility booking constraint fix completed successfully!\n";
    echo "Multiple facility booking sessions are now allowed for the same booking_id.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Please check your database connection and try again.\n";
}
?>