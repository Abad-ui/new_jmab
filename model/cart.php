<?php
require_once '../config/database.php';
require '../vendor/autoload.php';

class Cart {
    private $conn;
    private $table = 'cart';

    // Cart properties
    public $cart_id;
    public $user_id;
    public $product_id;
    public $quantity;

    // Constructor
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    // Validate cart inputs
    private function validateInput() {
        $errors = [];

        // Check required fields
        if (empty($this->user_id)) $errors[] = 'User ID is required.';
        if (empty($this->product_id)) $errors[] = 'Product ID is required.';
        if (empty($this->quantity) || $this->quantity < 1) $errors[] = 'Quantity must be at least 1.';

        return $errors;
    }

    // Add a product to the cart
    public function addToCart() {
        $errors = $this->validateInput();

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Check if the product already exists in the user's cart
        $query = 'SELECT * FROM ' . $this->table . ' WHERE user_id = :user_id AND product_id = :product_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':product_id', $this->product_id);
        $stmt->execute();

        $existingCartItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingCartItem) {
            // Update the quantity if the product already exists in the cart
            $newQuantity = $existingCartItem['quantity'] + $this->quantity;
            return $this->updateCartItem($existingCartItem['cart_id'], $newQuantity);
        } else {
            // Insert new cart item
            $query = 'INSERT INTO ' . $this->table . ' 
                      (user_id, product_id, quantity) 
                      VALUES (:user_id, :product_id, :quantity)';
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':user_id', $this->user_id);
            $stmt->bindParam(':product_id', $this->product_id);
            $stmt->bindParam(':quantity', $this->quantity);

            try {
                if ($stmt->execute()) {
                    return ['success' => true, 'message' => 'Product added to cart successfully.'];
                }
            } catch (PDOException $e) {
                error_log('Database Error: ' . $e->getMessage());
                return ['success' => false, 'errors' => ['Something went wrong. Please try again.']];
            }
        }

        return ['success' => false, 'errors' => ['Unknown error occurred.']];
    }

    // Update cart item quantity
    public function updateCartItem($cart_id, $quantity) {
        if ($quantity < 1) {
            return $this->removeFromCart($cart_id);
        }

        $query = 'UPDATE ' . $this->table . ' SET quantity = :quantity WHERE cart_id = :cart_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':cart_id', $cart_id);

        try {
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Cart item updated successfully.'];
            }
        } catch (PDOException $e) {
            error_log('Database Error: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Something went wrong. Please try again.']];
        }

        return ['success' => false, 'errors' => ['Unknown error occurred.']];
    }

    // Remove a product from the cart
    public function removeFromCart($cart_id) {
        $query = 'DELETE FROM ' . $this->table . ' WHERE cart_id = :cart_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cart_id', $cart_id);

        try {
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Product removed from cart successfully.'];
            }
        } catch (PDOException $e) {
            error_log('Database Error: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Something went wrong. Please try again.']];
        }

        return ['success' => false, 'errors' => ['Unknown error occurred.']];
    }

    // Get all cart items for a user
    public function getCartItems($user_id) {
        $query = 'SELECT c.cart_id, c.product_id, c.quantity, p.name, p.price, p.image_url 
                  FROM ' . $this->table . ' c 
                  JOIN products p ON c.product_id = p.product_id 
                  WHERE c.user_id = :user_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
    
        $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        // Calculate total price for each item and the overall cart total
        $totalCartPrice = 0;
        foreach ($cartItems as &$item) {
            $item['total_price'] = $item['price'] * $item['quantity']; // Total price for this item
            $totalCartPrice += $item['total_price']; // Add to the overall cart total
        }
    
        return [
            'cart_items' => $cartItems,
            'total_cart_price' => $totalCartPrice
        ];
    }


    public function checkout($user_id) {
        // Step 1: Get cart items for the user
        $cartItems = $this->getCartItems($user_id)['cart_items'];
    
        if (empty($cartItems)) {
            return ['success' => false, 'errors' => ['Your cart is empty.']];
        }
    
        // Step 2: Validate stock availability
        foreach ($cartItems as $item) {
            $product = $this->getProductStock($item['product_id']);
            if (!$product || $product['stock'] < $item['quantity']) {
                return ['success' => false, 'errors' => ['Product ' . $item['name'] . ' is out of stock or insufficient stock.']];
            }
        }
    
        // Step 3: Create an order
        $order_id = $this->createOrder($user_id, $cartItems);
    
        if (!$order_id) {
            return ['success' => false, 'errors' => ['Failed to create order.']];
        }
    
        // Step 4: Clear the cart
        $this->clearCart($user_id);
    
        return ['success' => true, 'message' => 'Checkout successful.', 'order_id' => $order_id];
    }
    
    private function getProductStock($product_id) {
        $query = 'SELECT stock FROM products WHERE product_id = :product_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function createOrder($user_id, $cartItems) {
        // Insert order into the orders table
        $query = 'INSERT INTO orders (user_id, total_price, created_at) VALUES (:user_id, :total_price, NOW())';
        $stmt = $this->conn->prepare($query);
    
        // Calculate total price
        $totalPrice = array_reduce($cartItems, function ($carry, $item) {
            return $carry + ($item['price'] * $item['quantity']);
        }, 0);
    
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':total_price', $totalPrice);
    
        if ($stmt->execute()) {
            $order_id = $this->conn->lastInsertId();
    
            // Insert order items into the order_items table
            foreach ($cartItems as $item) {
                $this->addOrderItem($order_id, $item);
            }
    
            return $order_id;
        }
    
        return false;
    }
    
    private function addOrderItem($order_id, $item) {
        $query = 'INSERT INTO order_items (order_id, product_id, quantity, price) 
                  VALUES (:order_id, :product_id, :quantity, :price)';
        $stmt = $this->conn->prepare($query);
    
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':product_id', $item['product_id']);
        $stmt->bindParam(':quantity', $item['quantity']);
        $stmt->bindParam(':price', $item['price']);
    
        $stmt->execute();
    }
    
    private function clearCart($user_id) {
        $query = 'DELETE FROM cart WHERE user_id = :user_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
    }
}
?>