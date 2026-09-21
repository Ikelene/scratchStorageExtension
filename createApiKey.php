<?php
require __DIR__ . '/bootstrap.php';

$body = readJsonBody();
$username = isset($body['username']) ? trim((string) $body['username']) : '';

if ($username === '' || strlen($username) > 32) {
    respond(['success' => false, 'error' => 'invalid_username'], 400);
}

$apiKey = bin2hex(random_bytes(20));

$stmt = $pdo->prepare('INSERT INTO users (username, api_key) VALUES (?, ?)');
$stmt->execute([$username, $apiKey]);

respond(['success' => true, 'username' => $username, 'apiKey' => $apiKey]);
