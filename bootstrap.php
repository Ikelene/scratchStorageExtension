<?php
// Database configuration - UPDATE THESE CREDENTIALS
$host = 'localhost';
$dbname = 'YOUR_DB';
$username = 'YOUR_DB_USER';
$password = 'YOUR_DB_PASSWORD';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

function getUserByApiKey($pdo, $apiKey) {
    $stmt = $pdo->prepare('SELECT id, username, api_key FROM users WHERE api_key = ?');
    $stmt->execute([$apiKey]);
    return $stmt->fetch();
}
?>
