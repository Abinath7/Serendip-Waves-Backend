<?php
include_once 'Main Classes/User.php';

class Customer extends User {

    public function __construct() {
        parent::__construct();
    }

    public function login($email, $password) {
        $query = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($query); // use $this->pdo
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'phone_number' => $user['phone_number'] ?? $user['phone'] ?? null,
                    'date_of_birth' => $user['date_of_birth'] ?? $user['dob'] ?? null,
                    'gender' => $user['gender'] ?? null,
                    'passport_number' => $user['passport_number'] ?? $user['passport'] ?? null,
                    'profile_image' => $user['profile_image'] ?? null,
                    'created_at' => $user['created_at'] ?? null,
                    'updated_at' => $user['updated_at'] ?? null,
                    'role' => $user['role'] ?? null
                ]
            ];
        }

        return ['success' => false, 'message' => 'Invalid credentials'];
    }

    public function getUserById($id) {
        $query = "SELECT * FROM users WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            unset($user['password']);
            return $user;
        }
        return null;
    }
}
