<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['apiKey'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing API key']);
    exit;
}

$user = getUserByApiKey($pdo, $input['apiKey']);
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$userId = $user['user_id'] ?? $user['id'];

try {
    $stmt = $pdo->prepare('SELECT `key`, value, mime_type FROM data_items WHERE user_id = ?');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'items' => $rows
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fetch failed']);
}
?>
