<?php
require_once '../config/database.php';
require '../vendor/autoload.php';
require_once '../config/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $first_name;
    public $last_name;
    public $email;
    public $password;
    public $roles = 'customer';

    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    private static function getSecretKey() {
        
        return JWT_SECRET_KEY;
    }
    
    private function generateJWT($user) {
        $secretKey = self::getSecretKey(); 
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

    
    private function validateInput() {
        $errors = [];

        
        if (empty($this->first_name)) $errors[] = 'First name is required.';
        if (empty($this->last_name)) $errors[] = 'Last name is required.';
        if (empty($this->email)) $errors[] = 'Email is required.';
        if (empty($this->password)) $errors[] = 'Password is required.';

        
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        }

        return $errors;
    }

    
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

    
    public function register() {
        $errors = $this->validateInput();

        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        
        if ($this->isEmailExists($this->email)) {
            return ['success' => false, 'errors' => ['Email is already registered.']];
        }

        $hashedPassword = password_hash($this->password, PASSWORD_BCRYPT);

        $query = 'INSERT INTO ' . $this->table . ' 
                  (first_name, last_name, email, password, roles, created_at) 
                  VALUES (:first_name, :last_name, :email, :password, :roles, NOW())';

        $stmt = $this->conn->prepare($query);

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

        return $result; 
    }

    public static function validateJWT($token) {
        $secretKey = self::getSecretKey(); 
        try {
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }
    
   
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

    
    public function getUsers() {
        $query = 'SELECT id, first_name, last_name, email, roles FROM ' . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    public function getUserById($id) {
        $query = 'SELECT id, first_name, last_name, email, roles FROM ' . $this->table . ' WHERE id = :id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    
    public function update($id, $data) {
    
    $userExists = $this->getUserById($id);
    if (!$userExists) {
        return ['success' => false, 'errors' => ['User not found.']];
    }

    $errors = [];

    
    if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    
    if (isset($data['email']) && $this->isEmailExists($data['email'], $id)) {
        $errors[] = 'Email is already registered.';
    }

    
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



