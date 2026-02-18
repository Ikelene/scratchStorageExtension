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

$action = 'get_all_data';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['apiKey'])) {
    http_response_code(400);
    logApiAction($pdo, $action, 400, $input);
    echo json_encode(['success' => false, 'error' => 'Missing API key']);
    exit;
}

$user = getUserByApiKey($pdo, $action, $input['apiKey']);
if (!$user) {
    http_response_code(401);
    logApiAction($pdo, $action, 401, $input);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$userId = $user['user_id'] ?? $user['id'];

try {
    $stmt = $pdo->prepare('SELECT `key`, value, mime_type, size, updated_at FROM data_items WHERE user_id = ? ORDER BY `key` ASC');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    $totalSize = array_sum(array_column($rows, 'size') ?? [0]);
    $itemCount = count($rows);

    logApiAction($pdo, $action, 200, $input, null, null, null, null, ['count' => $itemCount, 'total_size' => $totalSize]);
    echo json_encode([
        'success' => true,
        'items' => $rows,
        'count' => $itemCount,
        'total_size' => $totalSize
    ]);
} catch (Exception $e) {
    http_response_code(500);
    logApiAction($pdo, $action, 500, $input);
    echo json_encode(['success' => false, 'error' => 'Fetch failed']);
}
?>
