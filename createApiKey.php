<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'bootstrap.php';
checkRateLimit($pdo);

$action = 'create_api_key';

function ensureUsersSchema($pdo) {
    try {
        $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS username VARCHAR(32) NOT NULL,
                   ADD COLUMN IF NOT EXISTS api_key VARCHAR(40) NOT NULL UNIQUE,
                   ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                   ");
    } catch (Exception $e) {
    }
}

ensureUsersSchema($pdo);

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);

if (!is_array($input)) {
    http_response_code(400);
    logApiAction($pdo, $action, 400, ['raw_input' => substr($inputRaw, 0, 100)]);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON body'
    ]);
    exit;
}

$username = trim($input['username'] ?? '');

if (strlen($username) < 3 || strlen($username) > 32) {
    http_response_code(400);
    logApiAction($pdo, $action, 400, $input);
    echo json_encode([
        'success' => false,
        'error' => 'Username must be 3-32 characters'
    ]);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
    http_response_code(400);
    logApiAction($pdo, $action, 400, $input);
    echo json_encode([
        'success' => false,
        'error' => 'Username can only contain letters, numbers, -, _'
    ]);
    exit;
}

try {
    // Check if username already exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        http_response_code(409);
        logApiAction($pdo, $action, 409, $input);
        echo json_encode([
            'success' => false,
            'error' => 'Username already exists'
        ]);
        exit;
    }

    if (!function_exists('random_bytes')) {
        throw new Exception('random_bytes() not available');
    }

    $apiKey = bin2hex(random_bytes(20));

    $stmt = $pdo->prepare('INSERT INTO users (username, api_key, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([$username, $apiKey]);

    logApiAction($pdo, $action, 201, $input, null, null, $apiKey, $username);
    echo json_encode([
        'success' => true,
        'apiKey' => $apiKey,
        'username' => $username,
        'message' => 'API key created successfully'
    ]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        http_response_code(409);
        logApiAction($pdo, $action, 409, $input);
        echo json_encode(['success' => false, 'error' => 'Username or API key already exists']);
    } else {
        http_response_code(500);
        logApiAction($pdo, $action, 500, $input);
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
} catch (Exception $e) {
    http_response_code(500);
    logApiAction($pdo, $action, 500, $input);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
