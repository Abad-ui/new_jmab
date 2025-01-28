<?php
require_once '../config/database.php';

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
        $query = 'SELECT COUNT(*) FROM ' . $this->table . ' WHERE email = :email';
        if ($excludeUserId) {
            $query .= ' AND id != :excludeUserId';
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        if ($excludeUserId) {
            $stmt->bindParam(':excludeUserId', $excludeUserId);
        }
        $stmt->execute();
    
        return $stmt->fetchColumn() > 0;
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
    public function login($email, $password) {
        $result = $this->authenticate($email, $password);
        
        if ($result['success']) {
            $user = $result['user'];
            // You could return a JWT here if you implement token authentication
            return [
                'success' => true,
                'message' => 'Login successful.',
                'user' => $user
            ];
        }

        return $result; // Returns the failure result from authenticate method
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
    
        // Validate email format if provided
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
    
        // Build the SQL query dynamically based on provided fields
        $query = 'UPDATE ' . $this->table . ' SET ';
        $updates = [];
        $params = [':id' => $id];
    
        // Add fields to update if they are provided
        if (isset($data['first_name'])) {
            $updates[] = 'first_name = :first_name';
            $params[':first_name'] = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $updates[] = 'last_name = :last_name';
            $params[':last_name'] = $data['last_name'];
        }
        if (isset($data['email'])) {
            $updates[] = 'email = :email';
            $params[':email'] = $data['email'];
        }
        if (isset($data['password'])) {
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
            $updates[] = 'password = :password';
            $params[':password'] = $hashedPassword;
        }
    
        // If no fields are provided to update, return an error
        if (empty($updates)) {
            return ['success' => false, 'errors' => ['No fields provided for update.']];
        }
    
        // Append the updates to the query
        $query .= implode(', ', $updates);
        $query .= ' WHERE id = :id';
    
        // Prepare the statement
        $stmt = $this->conn->prepare($query);
    
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
    
        try {
            if ($stmt->execute()) {
                // Check if any rows were actually updated
                if ($stmt->rowCount() > 0) {
                    return ['success' => true, 'message' => 'User updated successfully.'];
                } else {
                    return ['success' => false, 'errors' => ['No changes made or user not found.']];
                }
            }
        } catch (PDOException $e) {
            error_log('Database Error: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Something went wrong. Please try again.']];
        }
    
        return ['success' => false, 'errors' => ['Unknown error occurred.']];
    }
    // Helper method to check if email exists (excluding the current user)
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

