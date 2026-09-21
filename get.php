<?php
require __DIR__ . '/bootstrap.php';

$body = readJsonBody();
$user = requireApiKey($pdo, $body['apiKey'] ?? null);

$key = isset($body['key']) ? (string) $body['key'] : '';
if ($key === '') {
    respond(['success' => false, 'error' => 'missing_key'], 400);
}

$stmt = $pdo->prepare('SELECT `value`, mime_type FROM data_items WHERE user_id = ? AND `key` = ? LIMIT 1');
$stmt->execute([$user['id'], $key]);
$row = $stmt->fetch();

logApiRequest($pdo, $body['apiKey'], $user['username'], 'get', ['key' => $key]);

if (!$row) {
    respond(['success' => true, 'data' => null]);
}
respond(['success' => true, 'data' => ['value' => $row['value'], 'mimeType' => $row['mime_type']]]);
