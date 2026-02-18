<?php
// bootstrap.php - FINAL TIMEZONE FIXED - Added action column migration
date_default_timezone_set('America/Denver');  // MST

$host = 'localhost';
$dbname = 'YOUR_DB_NAME';
$username = 'YOUR_DB_USER';
$password = 'YOUR_DB_PASS';

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

$pdo->exec("CREATE TABLE IF NOT EXISTS api_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NOT NULL,
           user_agent TEXT,
           api_key VARCHAR(40),
           username VARCHAR(255),
           action TEXT NOT NULL,
           request_method VARCHAR(10) NOT NULL,
           request_uri VARCHAR(500) NOT NULL,
           request_data JSON,
           response_status INT,
           INDEX idx_timestamp (timestamp),
           INDEX idx_ip (ip_address),
           INDEX idx_api_key (api_key)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
           request_count INT UNSIGNED DEFAULT 1,
           window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
           action VARCHAR(20) DEFAULT 'request',
           UNIQUE KEY unique_ip_window (ip_address, window_start),
           INDEX idx_ip (ip_address),
           INDEX idx_action (action)
)");

try {
    $pdo->exec("ALTER TABLE rate_limits ADD COLUMN action VARCHAR(20) DEFAULT 'request'");
} catch (PDOException $e) {
    // column exists or other error, ignore
}

function getClientIP() {
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function checkRateLimit($pdo) {
    // Rate limit is 40 requests per 60 seconds, ban 3 minutes on violation. Change to your liking.
    $RATE_LIMIT_WINDOW = 60;
    $RATE_LIMIT_MAX = 40;
    $BAN_DURATION = 180;

    $ip = getClientIP();
    $now = date('Y-m-d H:i:s');
    $window_start = date('Y-m-d H:i:s', time() - $RATE_LIMIT_WINDOW);
    $ban_end = date('Y-m-d H:i:s', time() - $BAN_DURATION);

    $pdo->exec("DELETE FROM rate_limits WHERE window_start < '$window_start'");

    $stmt = $pdo->prepare("SELECT id FROM rate_limits WHERE ip_address = ? AND action = 'ban' AND window_start > ?");
    $stmt->execute([$ip, $ban_end]);
    if ($stmt->fetch()) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded. Try again later.'
        ]);
        logApiAction($pdo, 'rate_limited_ban');
        exit;
    }

    $stmt = $pdo->prepare("SELECT SUM(request_count) as total FROM rate_limits WHERE ip_address = ? AND window_start >= ? AND action != 'ban'");
    $stmt->execute([$ip, $window_start]);
    $total = ($stmt->fetch()['total'] ?? 0) ?: 0;

    if ($total >= $RATE_LIMIT_MAX) {
        $stmt = $pdo->prepare("INSERT INTO rate_limits (ip_address, request_count, window_start, action) VALUES (?, 1, ?, 'ban') ON DUPLICATE KEY UPDATE request_count = request_count + 1");
        $stmt->execute([$ip, $now]);

        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded. Try again later.'
        ]);
        logApiAction($pdo, 'rate_limit_violation');
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO rate_limits (ip_address, request_count, window_start, action) VALUES (?, 1, ?, 'request') ON DUPLICATE KEY UPDATE request_count = request_count + 1");
    $stmt->execute([$ip, $now]);
}

function logApiAction($pdo, $action, $responseStatus = null, $requestData = null) {
    $ip = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? $_POST['api_key'] ?? $_REQUEST['api_key'] ?? null;
    $username = null;

    if ($api_key) {
        $stmt = $pdo->prepare('SELECT username FROM users WHERE api_key = ?');
        $stmt->execute([$api_key]);
        $user = $stmt->fetch();
        $username = $user['username'] ?? null;
    }

    $request_method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    $stmt = $pdo->prepare("
    INSERT INTO api_logs (ip_address, user_agent, api_key, username, action, request_method, request_uri, request_data, response_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $data_to_log = $requestData ?? (json_decode(file_get_contents('php://input'), true) ?: file_get_contents('php://input') ?: null);
    $stmt->execute([$ip, $user_agent, $api_key, $username, $action, $request_method, $request_uri, $data_to_log ? json_encode($data_to_log) : null, $responseStatus]);
}

function getUserByApiKey($pdo, $apiKey) {
    $stmt = $pdo->prepare('SELECT id, username, api_key FROM users WHERE api_key = ?');
    $stmt->execute([$apiKey]);
    return $stmt->fetch();
}
?>
