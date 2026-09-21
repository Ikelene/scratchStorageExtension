<?php
require __DIR__ . '/bootstrap.php';

$body = readJsonBody();
$user = requireApiKey($pdo, $body['apiKey'] ?? null);

$stmt = $pdo->prepare('SELECT `key`, `value` FROM data_items WHERE user_id = ? ORDER BY `key`');
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();

logApiRequest($pdo, $body['apiKey'], $user['username'], 'getAll');
respond(['success' => true, 'items' => $items]);
