<?php
require __DIR__ . '/bootstrap.php';

// 1. Block any direct multipart/form-data uploads
if (!empty($_FILES)) {
    respond(['success' => false, 'error' => 'file_uploads_not_allowed'], 400);
}

$body = readJsonBody();
$user = requireApiKey($pdo, $body['apiKey'] ?? null);

$key = isset($body['key']) ? trim((string) $body['key']) : '';
$value = isset($body['value']) ? (string) $body['value'] : null;

if ($key === '' || strlen($key) > 255 || $value === null) {
    respond(['success' => false, 'error' => 'invalid_key_or_value'], 400);
}

// 2. Reject if detected as a file upload (binary, data URI, base64 file, or file extension)
if (isFileUpload($key, $value)) {
    respond(['success' => false, 'error' => 'file_uploads_not_allowed'], 400);
}

// 3. Ensure the content is valid UTF-8 text (reject non-text/corrupt binary streams)
if (!mb_check_encoding($value, 'UTF-8')) {
    respond(['success' => false, 'error' => 'invalid_text_encoding'], 400);
}

$size = strlen($value); // bytes, matches the client's TextEncoder byte-length check
if ($size > 262144) {
    respond(['success' => false, 'error' => 'data_too_large'], 400);
}

// Force the mime_type to text/plain (prevent client from requesting binary types)
$mimeType = 'text/plain';

$stmt = $pdo->prepare(
    'INSERT INTO data_items (user_id, `key`, `value`, mime_type, size)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), mime_type = VALUES(mime_type), size = VALUES(size)'
);
$stmt->execute([$user['id'], $key, $value, $mimeType, $size]);

logApiRequest($pdo, $body['apiKey'], $user['username'], 'store', ['key' => $key, 'size' => $size]);
respond(['success' => true]);
