<?php
require __DIR__ . '/bootstrap.php';

$body = readJsonBody();
$user = requireApiKey($pdo, $body['apiKey'] ?? null);

$key = isset($body['key']) ? (string) $body['key'] : '';
if ($key === '') {
    respond(['success' => false, 'error' => 'missing_key'], 400);
}

$stmt = $pdo->prepare('DELETE FROM data_items WHERE user_id = ? AND `key` = ?');
$stmt->execute([$user['id'], $key]);

logApiRequest($pdo, $body['apiKey'], $user['username'], 'delete', ['key' => $key]);
respond(['success' => true]);
