<?php
require __DIR__ . '/bootstrap.php';

$body = readJsonBody();
$user = requireApiKey($pdo, $body['apiKey'] ?? null);

$stmt = $pdo->prepare('SELECT `key` FROM data_items WHERE user_id = ? ORDER BY `key`');
$stmt->execute([$user['id']]);
$keys = array_column($stmt->fetchAll(), 'key');

logApiRequest($pdo, $body['apiKey'], $user['username'], 'listKeys');
respond(['success' => true, 'keys' => $keys]);
