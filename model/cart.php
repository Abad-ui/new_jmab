<?php
require_once '../config/database.php';

class Cart {
    private $conn;
    private $table = 'cart';

    public $cart_id;
    public $user_id;
    public $product_id;
    public $quantity;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }
    
    public function getCartByUserId($user_id) {
        $query = 'SELECT c.cart_id, c.user_id, c.product_id, c.quantity, 
                 p.name AS product_name, p.image_url AS product_image, p.price AS product_price, 
                 p.stock AS product_stock
          FROM ' . $this->table . ' c
          JOIN products p ON c.product_id = p.product_id
          WHERE c.user_id = :user_id';
    
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
    
        $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        foreach ($cartItems as &$item) {
            if ($item['quantity'] > $item['product_stock']) {
                $item['quantity'] == $item['product_stock'];
            }else
            $item['total_price'] = $item['quantity'] * $item['product_price'];
        }
    
        return $cartItems;
    }
    
    public function createCart($user_id, $product_id, $quantity) {
        $query = 'SELECT quantity FROM ' . $this->table . ' WHERE user_id = :user_id AND product_id = :product_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->execute();
        $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);

        $query = 'SELECT stock FROM products WHERE product_id = :product_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return ['success' => false, 'errors' => ['Product not found.']];
        }

        $newQuantity = $quantity;
        if ($cartItem) {
            $newQuantity += $cartItem['quantity'];
        }

        if ($newQuantity > $product['stock']) {
            return ['success' => false, 'errors' => ['Quantity exceeds available stock.']];
        }

        try {
            if ($cartItem) {
                $query = 'UPDATE ' . $this->table . ' SET quantity = :quantity WHERE user_id = :user_id AND product_id = :product_id';
            } else {
                $query = 'INSERT INTO ' . $this->table . ' (user_id, product_id, quantity) VALUES (:user_id, :product_id, :quantity)';
            }
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $newQuantity, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Cart updated successfully.'];
            }
        } catch (PDOException $e) {
            error_log('Database Error: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Something went wrong. Please try again.']];
        }

        return ['success' => false, 'errors' => ['Unknown error occurred.']];
    }

}
?>
