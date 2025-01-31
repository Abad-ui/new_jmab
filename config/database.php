<?php
require '../vendor/autoload.php';
class Database {

    private $host = 'localhost';
    private $db_name = 'business-jmab';
    private $username = 'root';
    private $password = '';
    private $conn;

    
    public function connect() {
        $this->conn = null;

        try {
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=utf8mb4';
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_PERSISTENT, true); 
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            echo 'Connection Error. Please try again later.';
        }

        return $this->conn;
    }
}
?>


