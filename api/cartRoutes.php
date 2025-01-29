<?php
require_once '../model/cart.php';
require '../vendor/autoload.php';

// Handle API routes
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'add') {
    $data = json_decode(file_get_contents('php://input'), true);

    $cart = new Cart();
    $cart->user_id = $data['user_id'] ?? '';
    $cart->product_id = $data['product_id'] ?? '';
    $cart->quantity = $data['quantity'] ?? 1;

    $result = $cart->addToCart();

    if ($result['success']) {
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => $result['message']]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $result['errors']]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' && $_GET['endpoint'] === 'update') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['cart_id']) || empty($data['quantity'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => ['Cart ID and quantity are required.']]);
        exit;
    }

    $cart = new Cart();
    $result = $cart->updateCartItem($data['cart_id'], $data['quantity']);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => $result['message']]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $result['errors']]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $_GET['endpoint'] === 'remove') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['cart_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => ['Cart ID is required.']]);
        exit;
    }

    $cart = new Cart();
    $result = $cart->removeFromCart($data['cart_id']);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => $result['message']]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $result['errors']]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['endpoint'] === 'items') {
    if (empty($_GET['user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => ['User ID is required.']]);
        exit;
    }

    $cart = new Cart();
    $cartItems = $cart->getCartItems($_GET['user_id']);

    if (!empty($cartItems)) {
        echo json_encode(['success' => true, 'cart_items' => $cartItems]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'errors' => ['No items found in the cart.']]);
    }

    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'checkout') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => ['User ID is required.']]);
        exit;
    }

    $cart = new Cart();
    $result = $cart->checkout($data['user_id']);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => $result['message'], 'order_id' => $result['order_id']]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $result['errors']]);
    }
} 
else {
    header('HTTP/1.0 404 Not Found');
    echo json_encode(['success' => false, 'errors' => ['Invalid endpoint.']]);
}
?>