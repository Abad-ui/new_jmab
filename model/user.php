<?php
require_once '../config/database.php';
require '../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
// User.php
class User {
    private $conn;
    private $table = 'users';

    // User properties
    public $id;
    public $first_name;
    public $last_name;
    public $email;
    public $password;
    public $roles = 'customer'; // Default role

    // Constructor
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    private function generateJWT($user) {
        $secretKey = 'testkey'; // 
        $issuedAt = time();
        $expirationTime = $issuedAt + 3600; 

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'sub' => $user['id'],
            'email' => $user['email'],
            'roles' => $user['roles']
        ];

        return JWT::encode($payload, $secretKey, 'HS256');
    }

    // Validate user inputs
    private function validateInput() {
        $errors = [];

        // Check required fields
        if (empty($this->first_name)) $errors[] = 'First name is required.';
        if (empty($this->last_name)) $errors[] = 'Last name is required.';
        if (empty($this->email)) $errors[] = 'Email is required.';
        if (empty($this->password)) $errors[] = 'Password is required.';

        // Email format validation
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        }

        return $errors;
    }

    // Check if email is already registered
    private function isEmailExists($email, $excludeUserId = null) {
    $query = 'SELECT id FROM ' . $this->table . ' WHERE email = :email';
    
    if ($excludeUserId !== null) {
        $query .= ' AND id != :excludeUserId';
    }

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':email', $email);
    
    if ($excludeUserId !== null) {
        $stmt->bindParam(':excludeUserId', $excludeUserId, PDO::PARAM_INT);
    }

    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

    // Register a new user
    public function register() {
        $errors = $this->validateInput();

        // Check if there are validation errors
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Check if email is already registered
        if ($this->isEmailExists($this->email)) {
            return ['success' => false, 'errors' => ['Email is already registered.']];
        }

        // Hash the password
        $hashedPassword = password_hash($this->password, PASSWORD_BCRYPT);

        // Insert user into database
        $query = 'INSERT INTO ' . $this->table . ' 
                  (first_name, last_name, email, password, roles, created_at) 
                  VALUES (:first_name, :last_name, :email, :password, :roles, NOW())';

        $stmt = $this->conn->prepare($query);

        // Bind parameters
        $stmt->bindParam(':first_name', $this->first_name);
        $stmt->bindParam(':last_name', $this->last_name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':roles', $this->roles);

        try {
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'User registered successfully.'];
            }
        } catch (PDOException $e) {
            error_log('Database Error: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Something went wrong. Please try again.']];
        }

        return ['success' => false, 'errors' => ['Unknown error occurred.']];
    }

    // Login user
    // Login user and return JWT
    public function login($email, $password) {
        $result = $this->authenticate($email, $password);
        
        if ($result['success']) {
            $user = $result['user'];
            $token = $this->generateJWT($user);
            return [
                'success' => true,
                'message' => 'Login successful.',
                'token' => $token,
                'user' => $user
            ];
        }

        return $result; // Returns the failure result from authenticate method
    }

    public static function validateJWT($token) {
        $secretKey = 'testkey'; // Replace with a secure key
        try {
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }
    
    // Authenticate user
    public function authenticate($email, $password) {
        $query = 'SELECT * FROM ' . $this->table . ' WHERE email = :email';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'email' => $user['email'],
                    'roles' => $user['roles']
                ],
            ];
        }

        return ['success' => false, 'errors' => ['Invalid email or password.']];
    }

    // Get all users
    public function getUsers() {
        $query = 'SELECT id, first_name, last_name, email, roles FROM ' . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get user by ID
    public function getUserById($id) {
        $query = 'SELECT id, first_name, last_name, email, roles FROM ' . $this->table . ' WHERE id = :id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update user details (supports partial updates for any field)
    public function update($id, $data) {
    // Check if the user exists
    $userExists = $this->getUserById($id);
    if (!$userExists) {
        return ['success' => false, 'errors' => ['User not found.']];
    }

    $errors = [];

    // Validate email format
    if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    // Check if the new email is already registered (excluding the current user)
    if (isset($data['email']) && $this->isEmailExists($data['email'], $id)) {
        $errors[] = 'Email is already registered.';
    }

    // If there are validation errors, return them
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

  
    $updates = [];
    $params = [':id' => $id];

    foreach ($data as $key => $value) {
        if ($key !== 'id' && $key !== 'roles') {
            
            if ($key === 'password') {
                $hashedPassword = password_hash($value, PASSWORD_BCRYPT);
                $updates[] = "{$key} = :{$key}";
                $params[":{$key}"] = $hashedPassword;
            } else {
                if (isset($userExists[$key]) && $userExists[$key] != $value) {
                    $updates[] = "{$key} = :{$key}";
                    $params[":{$key}"] = $value;
                }
            }
        }
    }

    if (empty($updates)) {
        return ['success' => false, 'errors' => ['No changes detected.']];
    }

    $query = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $updates) . ' WHERE id = :id';

    $stmt = $this->conn->prepare($query);

    
    foreach ($params as $param_key => $value) {
        $stmt->bindValue($param_key, $value);
    }

    try {
       
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'User updated successfully.'];
        }
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return ['success' => false, 'errors' => ['An error occurred. Please try again.']];
    }

    return ['success' => false, 'errors' => ['Unknown error occurred.']];
}

    
    public function delete($id) {
        $query = 'DELETE FROM ' . $this->table . ' WHERE id = :id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
    
        try {
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return ['success' => true, 'message' => 'User deleted successfully.'];
                } else {
                    return ['success' => false, 'errors' => ['User not found.']];
                }
            }
        } catch (PDOException $e) {
            error_log('Database Error: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Something went wrong. Please try again.']];
        }
    
        return ['success' => false, 'errors' => ['Unknown error occurred.']];
    }
}



