<?php
// API Handler

require_once '../model/user.php';

// Authenticate API request
function authenticateAPI() {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
        header('WWW-Authenticate: Basic realm="API Access"');
        header('HTTP/1.0 401 Unauthorized');
        echo json_encode(['success' => false, 'errors' => ['Unauthorized.']]);
        exit;
    }

    // Get credentials from the request
    $username = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];

    // Check against hardcoded credentials
    $validCredentials = [
        'admin' => 'password123',
    ];

    if (!isset($validCredentials[$username]) || $validCredentials[$username] !== $password) {
        header('HTTP/1.0 401 Unauthorized');
        echo json_encode(['success' => false, 'errors' => ['Invalid username or password.']]);
        exit;
    }
}

// Handle API routes
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);

    $user = new User();
    $user->first_name = $data['first_name'] ?? '';
    $user->last_name = $data['last_name'] ?? '';
    $user->email = $data['email'] ?? '';
    $user->password = $data['password'] ?? '';

    $result = $user->register();

    if ($result['success']) {
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => $result['message']]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $result['errors']]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);

    $user = new User();
    $result = $user->login($data['email'], $data['password']);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => $result['message'], 'user' => $result['user']]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $result['errors']]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['endpoint'] === 'users') {
    authenticateAPI();
    $user = new User();
    $users = $user->getUsers();
    
    echo json_encode(['success' => true, 'users' => $users]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['endpoint']) && $_GET['endpoint'] === 'user' && isset($_GET['id'])) {
    authenticateAPI();
    $user = new User();
    $userInfo = $user->getUserById($_GET['id']);
    
    if ($userInfo) {
        echo json_encode(['success' => true, 'user' => $userInfo]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'errors' => ['User not found.']]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' && $_GET['endpoint'] === 'update') {
    authenticateAPI(); // Ensure the request is authenticated

    // Get the input data
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate that the user ID is provided
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => ['User ID is required.']]);
        exit;
    }

    // Create a User instance
    $user = new User();

    // Call the update method with the provided data
    $result = $user->update($data['id'], $data);

    // Return the result
    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => $result['message']]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $result['errors']]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $_GET['endpoint'] === 'delete') {
    authenticateAPI();
    $data = json_decode(file_get_contents('php://input'), true);

    $user = new User();
    $id = $data['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => ['User ID is required.']]);
        exit;
    }

    $result = $user->delete($id);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => $result['message']]);
    } else {
        http_response_code(400);  // or 404 if you want a not found error code
        echo json_encode(['success' => false, 'errors' => $result['errors']]);
    }
}

else {
    header('HTTP/1.0 404 Not Found');
    echo json_encode(['success' => false, 'errors' => ['Invalid endpoint.']]);
}


?>
