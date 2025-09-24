<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS, GET, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Handle preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Test endpoint - check if server is reachable
if (isset($_GET['test'])) {
    echo json_encode(["success" => true, "message" => "Server is reachable", "timestamp" => date('Y-m-d H:i:s')]);
    exit();
}

// Get JSON input
$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput);

// Add detailed logging
error_log("=== updateStock.php DEBUG ===");
error_log("Raw input: " . $rawInput);
error_log("Decoded input: " . json_encode($input));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);

if (!$input && $_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("ERROR: Failed to decode JSON input");
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON input.", "raw_input" => $rawInput]);
    exit();
}

// Database connection
try {
    $conn = new mysqli("localhost", "root", "", "serendip");
    
    if ($conn->connect_error) {
        error_log("ERROR: Database connection failed: " . $conn->connect_error);
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Database connection failed: " . $conn->connect_error
        ]);
        exit();
    }
    
    error_log("Database connection successful");
    
} catch (Exception $e) {
    error_log("ERROR: Exception during database connection: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection error: " . $e->getMessage()
    ]);
    exit();
}

try {
    // Check if this is a status change action (delete/activate)
    if (isset($input->action) && isset($input->item_id)) {
        $action = $input->action;
        $item_id = intval($input->item_id);
        
        if ($action === 'delete') {
            $sql = "UPDATE food_inventory SET item_status = 'inactive' WHERE item_id = ?";
            $message = "Item deactivated successfully.";
        } elseif ($action === 'activate') {
            $sql = "UPDATE food_inventory SET item_status = 'active' WHERE item_id = ?";
            $message = "Item reactivated successfully.";
        } else {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid action: $action"]);
            exit();
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        $stmt->bind_param("i", $item_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(["success" => true, "message" => $message]);
            } else {
                echo json_encode(["success" => false, "message" => "No item found with ID: $item_id"]);
            }
        } else {
            throw new Exception("Failed to execute statement: " . $stmt->error);
        }
        
        $stmt->close();
        
    } else {
        // This is a full item update
        error_log("Processing full item update...");
        
        $item_id = isset($input->item_id) ? intval($input->item_id) : null;
        $food_item_name = $input->food_item_name ?? '';
        $category = $input->category ?? '';
        $quantity_in_stock = isset($input->quantity_in_stock) ? intval($input->quantity_in_stock) : 0;
        $unit = $input->unit ?? 'kg';
        $unit_price = isset($input->unit_price) ? floatval($input->unit_price) : 0.0;
        $expiry_date = $input->expiry_date ?? '';
        $purchase_date = $input->purchase_date ?? null;
        $supplier_name = $input->supplier_name ?? '';
        $supplier_contact = $input->supplier_contact ?? '';
        $supplier_email = $input->supplier_email ?? '';
        $status = $input->status ?? 'In Stock';

        error_log("Extracted data:");
        error_log("item_id: $item_id");
        error_log("food_item_name: $food_item_name");
        error_log("category: $category");
        error_log("quantity_in_stock: $quantity_in_stock");
        error_log("unit: $unit");
        error_log("unit_price: $unit_price");
        error_log("expiry_date: $expiry_date");

        // Validation for full update
        if (!$item_id) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Missing item_id for update"
            ]);
            exit();
        }

        if (empty($food_item_name) || empty($category)) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Missing required fields: food_item_name or category"
            ]);
            exit();
        }

        // Update the food inventory item
        $sql = "UPDATE food_inventory SET 
                    food_item_name = ?, 
                    category = ?, 
                    quantity_in_stock = ?, 
                    unit = ?, 
                    unit_price = ?, 
                    expiry_date = ?, 
                    purchase_date = ?, 
                    supplier_name = ?, 
                    supplier_contact = ?, 
                    supplier_email = ?, 
                    status = ?
                WHERE item_id = ?";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        // Correct parameter binding: s=string, i=integer, d=double
        $stmt->bind_param(
            "ssisdssssssi", 
            $food_item_name,     // s - string
            $category,           // s - string
            $quantity_in_stock,  // i - integer
            $unit,               // s - string
            $unit_price,         // d - double
            $expiry_date,        // s - string
            $purchase_date,      // s - string
            $supplier_name,      // s - string
            $supplier_contact,   // s - string
            $supplier_email,     // s - string
            $status,             // s - string
            $item_id             // i - integer
        );
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    "success" => true,
                    "message" => "Food item updated successfully."
                ]);
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => "No changes made or item not found with ID: $item_id"
                ]);
            }
        } else {
            throw new Exception("Failed to execute update: " . $stmt->error);
        }
        
        $stmt->close();
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>