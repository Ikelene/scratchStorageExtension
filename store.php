<?php
require __DIR__ . '/bootstrap.php';

$body = readJsonBody();
$user = requireApiKey($pdo, $body['apiKey'] ?? null);

$key = isset($body['key']) ? (string) $body['key'] : '';
$value = isset($body['value']) ? (string) $body['value'] : null;
$mimeType = isset($body['mimeType']) && is_string($body['mimeType']) ? $body['mimeType'] : 'text/plain';

if ($key === '' || strlen($key) > 255 || $value === null) {
    respond(['success' => false, 'error' => 'invalid_key_or_value'], 400);
}

$size = strlen($value); // bytes, matches the client's TextEncoder byte-length check
if ($size > 262144) {
    respond(['success' => false, 'error' => 'data_too_large'], 400);
}

$stmt = $pdo->prepare(
    'INSERT INTO data_items (user_id, `key`, `value`, mime_type, size)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), mime_type = VALUES(mime_type), size = VALUES(size)'
);
$stmt->execute([$user['id'], $key, $value, $mimeType, $size]);

logApiRequest($pdo, $body['apiKey'], $user['username'], 'store', ['key' => $key, 'size' => $size]);
respond(['success' => true]);
