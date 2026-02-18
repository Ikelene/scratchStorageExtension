<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once 'bootstrap.php';
checkRateLimit($pdo);

$action = 'delete_key';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['apiKey']) || !isset($input['key'])) {
    http_response_code(400);
    logApiAction($pdo, $action, 400, $input);
    echo json_encode(['success' => false, 'error' => 'Missing required fields: apiKey, key']);
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
$key = substr(trim($input['key']), 0, 255);

try {
    $stmt = $pdo->prepare('DELETE FROM data_items WHERE user_id = ? AND project_id IS NULL AND `key` = ?');
    $stmt->execute([$userId, $key]);
    $deletedCount = $stmt->rowCount();
    $success = $deletedCount > 0;

    logApiAction($pdo, $action, $success ? 200 : 200, $input, null, null, null, null, ['deleted_count' => $deletedCount, 'key' => $key]);
    echo json_encode([
        'success' => true,
        'deleted' => $deletedCount,
        'key' => $key
    ]);
} catch (Exception $e) {
    http_response_code(500);
    logApiAction($pdo, $action, 500, $input);
    echo json_encode(['success' => false, 'error' => 'Delete failed']);
}
?>
