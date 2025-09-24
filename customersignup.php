<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once './Main Classes/Customer.php';

// Get form data via $_POST (since using multipart/form-data)
$full_name = isset($_POST['name']) ? htmlspecialchars(strip_tags($_POST['name'])) : null;
$email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : null;
$password = isset($_POST['password']) ? $_POST['password'] : null;
$phone_number = isset($_POST['phone_number']) ? htmlspecialchars(strip_tags($_POST['phone_number'])) : null;
$date_of_birth = isset($_POST['date_of_birth']) ? htmlspecialchars(strip_tags($_POST['date_of_birth'])) : null;
$gender = isset($_POST['gender']) ? htmlspecialchars(strip_tags($_POST['gender'])) : null;
$passport_number = isset($_POST['passport_number']) ? htmlspecialchars(strip_tags($_POST['passport_number'])) : null;
$country = isset($_POST['country']) ? htmlspecialchars(strip_tags($_POST['country'])) : null;

// Validate required fields (passport is optional)
if (!$full_name || !$email || !$password || !$phone_number || !$date_of_birth || !$gender) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "All required fields must be filled."]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email format."]);
    exit();
}

$hashed_password = password_hash($password, PASSWORD_BCRYPT);

$customerRegister = new Customer();

// Check if email already exists before attempting registration
if ($customerRegister->checkEmailExists($email)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email is already registered"]);
    exit();
}

$result = $customerRegister->registerUser($full_name, $email, $hashed_password, $phone_number, $date_of_birth, $gender, $passport_number, $country);

if ($result) {
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Customer was successfully registered."]);
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Unable to register the Customer."]);
}

































// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Methods: POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type, Authorization");

// if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
//     http_response_code(200);
//     exit();
// }

// require_once './Main Classes/Customer.php';

// // Debugging input data (uncomment for debugging)
// // file_put_contents("debug.log", print_r($_POST, true), FILE_APPEND);
// // file_put_contents("debug.log", print_r($_FILES, true), FILE_APPEND);

// // Sanitize and validate inputs
// $name = (isset($_POST['firstName']) && isset($_POST['lastName'])) 
//     ? htmlspecialchars(strip_tags($_POST['firstName'] . ' ' . $_POST['lastName'])) 
//     : null;

// $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : null;
// $password = isset($_POST['password']) ? $_POST['password'] : null;
// $contact_number = isset($_POST['phone']) ? htmlspecialchars(strip_tags($_POST['phone'])) : null;
// $address = (isset($_POST['city']) && isset($_POST['district']) && isset($_POST['postalCode'])) 
//     ? htmlspecialchars(strip_tags($_POST['city'] . ', ' . $_POST['district'] . ', ' . $_POST['postalCode'])) 
//     : null;

// // Check for missing fields
// if (!$name || !$email || !$password || !$contact_number || !$address) {
//     http_response_code(400);
//     echo json_encode(["status" => "error", "message" => "All fields are required."]);
//     exit();
// }

// // Validate email format
// if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
//     http_response_code(400);
//     echo json_encode(["status" => "error", "message" => "Invalid email format."]);
//     exit();
// }

// // Hash password
// $hashed_password = password_hash($password, PASSWORD_BCRYPT);

// $customerRegister = new Customer();

// try {
//     $result = $customerRegister->registerUser($name, $email, $hashed_password, $contact_number, $address);
//     if ($result) {
//         http_response_code(200);
//         echo json_encode(["status" => "success", "message" => "Customer was successfully registered."]);
//     } else {
//         http_response_code(400);
//         echo json_encode(["status" => "error", "message" => "Unable to register the Customer."]);
//     }
// } catch (PDOException $e) {
//     http_response_code(500);
//     echo json_encode([
//         "status" => "error",
//         "message" => "Database error: " . $e->getMessage()
//     ]);
// } catch (Exception $e) {
//     http_response_code(500);
//     echo json_encode([
//         "status" => "error",
//         "message" => "Server error: " . $e->getMessage()
//     ]);
// }