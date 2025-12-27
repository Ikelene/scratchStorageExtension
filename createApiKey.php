<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'bootstrap.php';

function ensureUsersSchema($pdo) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'username'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(32) NOT NULL");
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'api_key'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN api_key VARCHAR(40) NOT NULL");
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'created_at'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Schema error: ' . $e->getMessage()
        ]);
        exit;
    }
}

ensureUsersSchema($pdo);

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON body'
    ]);
    exit;
}

$username = trim($input['username'] ?? '');

if (strlen($username) < 3 || strlen($username) > 32) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Username must be 3-32 characters'
    ]);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Username can only contain letters, numbers, -, _'
    ]);
    exit;
}

try {
    if (!function_exists('random_bytes')) {
        throw new Exception('random_bytes() not available');
    }

    $apiKey = bin2hex(random_bytes(20));

    $stmt = $pdo->prepare('INSERT INTO users (username, api_key, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([$username, $apiKey]);

    echo json_encode([
        'success' => true,
        'apiKey' => $apiKey,
        'message' => 'API key created successfully'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
