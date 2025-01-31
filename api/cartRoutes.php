<?php
require_once '../model/cart.php';
require '../model/user.php';
require '../vendor/autoload.php';

header('Content-Type: application/json');

function authenticateAPI() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        header('HTTP/1.0 401 Unauthorized');
        echo json_encode(['success' => false, 'errors' => ['Authorization token is required.']]);
        exit;
    }

    $authHeader = $headers['Authorization'];
    $token = str_replace('Bearer ', '', $authHeader);

    $userData = User::validateJWT($token);
    if (!$userData) {
        header('HTTP/1.0 401 Unauthorized');
        echo json_encode(['success' => false, 'errors' => ['Invalid or expired token.']]);
        exit;
    }

    return $userData;
}

if($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['endpoint']) && $_GET['endpoint'] === 'cart' && isset($_GET['user_id'])) {
    authenticateAPI();
    $user_id = $_GET['user_id'];
    $cart = new Cart();
    $cartInfo = $cart->getCartByUserId($user_id);

    if (!empty($cartInfo)) {
        echo json_encode(['success' => true, 'cart' => $cartInfo]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'errors' => ['Cart not found.']]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'createCart') {
    authenticateAPI();
    $data = json_decode(file_get_contents('php://input'), true);
    $cart = new Cart();
    $user_id = isset($data['user_id']) ? (int)$data['user_id'] : null;
    $product_id = isset($data['product_id']) ? (int)$data['product_id'] : null;
    $quantity = isset($data['quantity']) ? (int)$data['quantity'] : null;

    $result = $cart->createCart($user_id, $product_id, $quantity);

    if ($result['success']) {
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => $result['message']]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $result['errors']]);
    }
}else {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Invalid request.']]);
}


?>
