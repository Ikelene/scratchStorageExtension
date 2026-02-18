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

$action = 'store_data';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['apiKey']) || !isset($input['key']) || !isset($input['value'])) {
    http_response_code(400);
    logApiAction($pdo, $action, 400, $input);
    echo json_encode(['success' => false, 'error' => 'Missing required fields: apiKey, key, value']);
    exit;
}

$user = getUserByApiKey($pdo, $input['apiKey']);
if (!$user) {
    http_response_code(401);
    logApiAction($pdo, $action, 401, $input);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$userId = $user['user_id'] ?? $user['id'];
$projectId = null;

$key = substr(trim($input['key']), 0, 255);
$value = $input['value'];
$mimeType = isset($input['mimeType']) ? $input['mimeType'] : 'application/json';
$size = strlen($value);

const MAX_SIZE = 262144;

if ($size > MAX_SIZE) {
    http_response_code(413);
    logApiAction($pdo, $action, 413, $input);
    echo json_encode(['success' => false, 'error' => "Data too large (max 256KB, got {$size} bytes)"]);
    exit;
}

if (strlen($key) < 1 || strlen($key) > 255) {
    http_response_code(400);
    logApiAction($pdo, $action, 400, $input);
    echo json_encode(['success' => false, 'error' => 'Key must be 1-255 characters']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('
    INSERT INTO data_items (user_id, project_id, `key`, value, mime_type, size, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
    value = VALUES(value),
                          mime_type = VALUES(mime_type),
                          size = VALUES(size),
                          updated_at = NOW()
    ');
    $stmt->execute([$userId, $projectId, $key, $value, $mimeType, $size]);

    $pdo->commit();

    logApiAction($pdo, $action, 200, $input);
    echo json_encode([
        'success' => true,
        'size' => $size
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    logApiAction($pdo, $action, 500, $input);
    echo json_encode(['success' => false, 'error' => 'Storage failed']);
}
?>
