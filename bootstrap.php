<?php
date_default_timezone_set('America/Denver');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

$db_host = 'localhost';
$db_name = 'STORAGE_NAME_HERE';
$db_user = 'DB_USER_HERE';
$db_pass = 'DB_PASS_HERE';

try {
    $dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_connection_failed']);
    exit;
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function readJsonBody() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function requireApiKey($pdo, $apiKey) {
    if (!is_string($apiKey) || $apiKey === '') {
        respond(['success' => false, 'error' => 'missing_api_key'], 401);
    }
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE api_key = ? LIMIT 1');
    $stmt->execute([$apiKey]);
    $user = $stmt->fetch();
    if (!$user) {
        respond(['success' => false, 'error' => 'invalid_api_key'], 401);
    }
    return $user;
}

function logApiRequest($pdo, $apiKey, $username, $action, $requestData = null, $responseStatus = 200) {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO api_logs (ip_address, user_agent, api_key, username, action, request_method, request_uri, request_data, response_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $apiKey,
            $username,
            $action,
            $_SERVER['REQUEST_METHOD'] ?? '',
            $_SERVER['REQUEST_URI'] ?? '',
            $requestData !== null ? json_encode($requestData) : null,
            $responseStatus,
        ]);
    } catch (Exception $e) {
        // logging is never allowed to break the request
    }
}
