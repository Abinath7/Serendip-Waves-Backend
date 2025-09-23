<?php
require_once 'DbConnector.php';

abstract class User
{
    protected $id;
    protected $full_name;
    protected $email;
    protected $password;
    protected $phone_number;
    protected $date_of_birth;
    protected $gender;
    protected $passport_number;
    protected $country;
    protected $pdo;
    

    public function __construct()
    {
        $db = new DBConnector();
        $this->pdo = $db->connect();
    }

    public function isAlreadyExists()
    {
        $query = "SELECT email FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(":email", $this->email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function checkEmailExists($email)
{
    try {
        $query = "SELECT email FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Failed to verify email. " . $e->getMessage()]);
        return false; // Make sure to return false on exception
    }
}


public function registerUser($full_name, $email, $password, $phone_number, $date_of_birth, $gender, $passport_number = null, $country = null)
{
    $this->full_name = $full_name;
    $this->email = $email;
    $this->password = $password;
    $this->phone_number = $phone_number;
    $this->date_of_birth = $date_of_birth;
    $this->gender = $gender;
    $this->passport_number = $passport_number;
    $this->country = $country;

    if ($this->isAlreadyExists()) {
        // Optionally: throw error or log that user exists
        // Just returning false here means frontend will show generic message
        return false;
    }

    try {
        $sql = "INSERT INTO users (full_name, email, password, phone_number, date_of_birth, gender, passport_number, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(1, $this->full_name);
        $stmt->bindParam(2, $this->email);
        $stmt->bindParam(3, $this->password);
        $stmt->bindParam(4, $this->phone_number);
        $stmt->bindParam(5, $this->date_of_birth);
        $stmt->bindParam(6, $this->gender);
        $stmt->bindParam(7, $this->passport_number);    
        $stmt->bindParam(8, $this->country);    
        $rs = $stmt->execute();

        if ($rs) {
            return true;
        }
        return false;
    } catch (PDOException $e) {
        // Log error to PHP error log for debugging
        error_log("PDOException in registerUser: " . $e->getMessage());
        // Return false after catching exception
        return false;
    }
}

public function login($email, $password)
{
    try {
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']); // Don't send password to frontend
            return [
                "success" => true,
                "message" => "Login successful",
                "user" => [
                    "id" => $user['id'],
                    "full_name" => $user['full_name'],
                    "email" => $user['email'],
                    "phone_number" => $user['phone_number'] ?? $user['phone'] ?? null,
                    "date_of_birth" => $user['date_of_birth'] ?? $user['dob'] ?? null,
                    "gender" => $user['gender'] ?? null,
                    "passport_number" => $user['passport_number'] ?? $user['passport'] ?? null,
                    "country" => $user['country'] ?? null,
                    "profile_image" => $user['profile_image'] ?? null,
                    "created_at" => $user['created_at'] ?? null,
                    "updated_at" => $user['updated_at'] ?? null,
                    "role" => $user['role'] ?? null
                ],
                "role" => $user['role']
            ];
        }
        
            return [
                "success" => false,
                "message" => "Invalid email or password"
            ];
    } catch (PDOException $e) {
        return [
            "success" => false,
            "message" => "Database error: " . $e->getMessage()
        ];
    }
}

public function forgotPassword($email, $password){
    $this->email = $email;
    $this->password = $password;
    try {
        $sql = "UPDATE users SET password = :password WHERE email = :email";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':password', $this->password);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();

        error_log("Rows affected: " . $stmt->rowCount());

        if ($stmt->rowCount() > 0) {
            return true;
        } else {
            error_log("No rows updated - email may not exist or password unchanged");
            return false;
        }
    } catch (PDOException $e) {
        error_log("Password reset error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["message" => "Failed to change password. " . $e->getMessage()]);
        return false;
    }
}

// Booking methods
public function createBooking($user_id, $full_name, $email, $cruise_title, $cabin_type, $adults, $children, $booking_date, $departure_date, $return_date, $total_price, $special_requests = null) {
    try {
        // First check if bookings table exists, if not create it
        $this->createBookingsTableIfNotExists();
        
        $total_guests = $adults + $children;
        $cabin_number = 'CAB' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        $sql = "INSERT INTO bookings (user_id, full_name, email, cruise_title, cabin_type, cabin_number, adults, children, total_guests, booking_date, departure_date, return_date, total_price, special_requests) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $user_id, $full_name, $email, $cruise_title, $cabin_type, $cabin_number,
            $adults, $children, $total_guests, $booking_date, $departure_date, $return_date,
            $total_price, $special_requests
        ]);
        
        return [
            'success' => true,
            'booking_id' => $this->pdo->lastInsertId(),
            'cabin_number' => $cabin_number
        ];
    } catch (PDOException $e) {
        error_log("Booking creation error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to create booking'];
    }
}

public function getUserBookings($email) {
    try {
        $sql = "SELECT bo.*, it.start_date AS departure_date, it.end_date AS return_date
                FROM booking_overview bo
                LEFT JOIN itineraries it ON bo.ship_name = it.ship_name AND bo.destination = it.route
                WHERE bo.email = ?
                ORDER BY bo.booking_id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get user bookings error: " . $e->getMessage());
        return [];
    }
}

private function createBookingsTableIfNotExists() {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            cruise_title VARCHAR(255) NOT NULL,
            cabin_type VARCHAR(50) NOT NULL,
            cabin_number VARCHAR(20),
            adults INT NOT NULL DEFAULT 1,
            children INT NOT NULL DEFAULT 0,
            total_guests INT NOT NULL,
            booking_date DATE NOT NULL,
            departure_date DATE NOT NULL,
            return_date DATE NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
            booking_status ENUM('confirmed', 'pending', 'cancelled') DEFAULT 'pending',
            special_requests TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
    } catch (PDOException $e) {
        error_log("Create bookings table error: " . $e->getMessage());
    }
}
}
