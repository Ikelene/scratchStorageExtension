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

$action = 'get_single_key';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['apiKey'])) {
    http_response_code(400);
    logApiAction($pdo, $action, 400, $input);
    echo json_encode(['success' => false, 'error' => 'Missing API key']);
    exit;
}

$user = getUserByApiKey($pdo, $input['apiKey']);
if (!$user) {
    http_response_code(401);
    logApiAction($pdo, $action, 401, $input);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$key = isset($input['key']) ? substr(trim($input['key']), 0, 255) : null;
$found = false;
$size = 0;

$params = [$user['id']];
$sql = 'SELECT `key`, value, mime_type, size, updated_at FROM data_items WHERE user_id = ? AND project_id IS NULL';

if ($key) {
    $sql .= ' AND `key` = ?';
    $params[] = $key;
    $action .= '_specific';
}

$sql .= ' ORDER BY updated_at DESC LIMIT 1';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetch();

if ($data) {
    $found = true;
    $size = $data['size'] ?? strlen($data['value'] ?? '');
}

$responseData = [
    'success' => true,
'data' => $data ?: null,
'found' => $found,
'size_bytes' => $size
];

logApiAction($pdo, $action, 200, $input, null, null, null, null, ['found' => $found, 'size' => $size]);  echo json_encode($responseData);
?>
