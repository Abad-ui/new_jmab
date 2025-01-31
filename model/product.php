<?php
require_once '../config/database.php';

class Product {
    private $conn;
    private $table = 'products';

    public $product_id;
    public $name;
    public $description;
    public $category;
    public $subcategory;
    public $price;
    public $stock;
    public $image_url;
    public $brand;
    public $size;
    public $voltage;
    public $tags;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    
    private function validateInput() {
        $errors = [];

        if (empty($this->name)) $errors[] = 'Product name is required.';
        if (empty($this->category)) $errors[] = 'Product category is required.';
        if (empty($this->price)) $errors[] = 'Product price is required.';
        if (empty($this->stock)) $errors[] = 'Product stock is required.';
        if (empty($this->image_url)) $errors[] = 'Product image URL is required.';
        if (empty($this->brand)) $errors[] = 'Product brand is required.';

        if ($this->category === 'Tires' && empty($this->size)) {
            $errors[] = 'Size is required for tires.';
        }
        if ($this->category === 'Batteries' && empty($this->voltage)) {
            $errors[] = 'Voltage is required for batteries.';
        }

        return $errors;
    }

    public function getProducts() {
        $query = 'SELECT * FROM ' . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductById($product_id) {
        $query = 'SELECT * FROM ' . $this->table . ' WHERE product_id = :product_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createProduct() {
        $errors = $this->validateInput();
    
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
    
        $query = 'INSERT INTO ' . $this->table . ' 
                  (name, description, category, subcategory, price, stock, image_url, brand, size, voltage, tags) 
                  VALUES (:name, :description, :category, :subcategory, :price, :stock, :image_url, :brand, :size, :voltage, :tags)';
    
        $stmt = $this->conn->prepare($query);
    
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':category', $this->category);
        $stmt->bindParam(':subcategory', $this->subcategory);
        $stmt->bindParam(':price', $this->price);
        $stmt->bindParam(':stock', $this->stock);
        $stmt->bindParam(':image_url', $this->image_url);
        $stmt->bindParam(':brand', $this->brand);
        $stmt->bindParam(':size', $this->size);
        $stmt->bindParam(':voltage', $this->voltage);
    
        $tagsJson = json_encode($this->tags);
        $stmt->bindParam(':tags', $tagsJson);
    
        try {
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Product created successfully.'];
            }
        } catch (PDOException $e) {
            error_log('Database Error: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Something went wrong. Please try again.']];
        }
    
        return ['success' => false, 'errors' => ['Unknown error occurred.']];
    }
    

    public function updateProduct($product_id, $data) {
        $productExists = $this->getProductById($product_id);
        if (!$productExists) {
            return ['success' => false, 'errors' => ['Product not found.']];
        }

        $query = 'UPDATE ' . $this->table . ' SET ';
        $updates = [];
        $params = [':product_id' => $product_id];

        if (isset($data['name'])) {
            $updates[] = 'name = :name';
            $params[':name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updates[] = 'description = :description';
            $params[':description'] = $data['description'];
        }
        if (isset($data['category'])) {
            $updates[] = 'category = :category';
            $params[':category'] = $data['category'];
        }
        if (isset($data['subcategory'])) {
            $updates[] = 'subcategory = :subcategory';
            $params[':subcategory'] = $data['subcategory'];
        }
        if (isset($data['price'])) {
            $updates[] = 'price = :price';
            $params[':price'] = $data['price'];
        }
        if (isset($data['stock'])) {
            $updates[] = 'stock = :stock';
            $params[':stock'] = $data['stock'];
        }
        if (isset($data['image_url'])) {
            $updates[] = 'image_url = :image_url';
            $params[':image_url'] = $data['image_url'];
        }
        if (isset($data['brand'])) {
            $updates[] = 'brand = :brand';
            $params[':brand'] = $data['brand'];
        }
        if (isset($data['size'])) {
            $updates[] = 'size = :size';
            $params[':size'] = $data['size'];
        }
        if (isset($data['voltage'])) {
            $updates[] = 'voltage = :voltage';
            $params[':voltage'] = $data['voltage'];
        }
        if (isset($data['tags'])) {
            $updates[] = 'tags = :tags';
            $params[':tags'] = json_encode($data['tags']);
        }

        if (!empty($updates)) {
            $query .= implode(', ', $updates) . ' WHERE product_id = :product_id';
        } else {
            return ['success' => false, 'errors' => ['No fields to update.']];
        }

        $stmt = $this->conn->prepare($query);

        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }

        try {
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Product updated successfully.'];
            }
        } catch (PDOException $e) {
            error_log('Database Error: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Something went wrong. Please try again.']];
        }

        return ['success' => false, 'errors' => ['Unknown error occurred.']];
    }

    public function deleteProduct($product_id) {
        $query = 'DELETE FROM ' . $this->table . ' WHERE product_id = :product_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);

        try {
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Product deleted successfully.'];
            }
        } catch (PDOException $e) {
            error_log('Database Error: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Something went wrong. Please try again.']];
        }

        return ['success' => false, 'errors' => ['Unknown error occurred.']];
    }

    public function searchProducts($filters = []) {
        $query = 'SELECT * FROM ' . $this->table . ' WHERE 1=1';
        $params = [];
    
        if (!empty($filters['brand'])) {
            $query .= ' AND brand LIKE :brand';
            $params[':brand'] = '%' . $filters['brand'] . '%'; 
        }
        if (!empty($filters['name'])) {
            $query .= ' AND name LIKE :name';
            $params[':name'] = '%' . $filters['name'] . '%';
        }
        if (!empty($filters['category'])) {
            $query .= ' AND category = :category';
            $params[':category'] = $filters['category'];
        }
        if (!empty($filters['subcategory'])) {
            $query .= ' AND subcategory = :subcategory';
            $params[':subcategory'] = $filters['subcategory'];
        }
        if (!empty($filters['tags'])) {
            $query .= ' AND JSON_CONTAINS(tags, :tags)';
            $params[':tags'] = json_encode($filters['tags']);
        }
    
        $stmt = $this->conn->prepare($query);
    
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
    
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>