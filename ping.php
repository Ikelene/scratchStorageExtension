<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'bootstrap.php';
checkRateLimit($pdo);

$action = 'health_ping';

try {
    $stmt = $pdo->query('SELECT 1');
    $row = $stmt->fetch();

    if ($row) {
        logApiAction($pdo, $action, 200);
        echo json_encode([
            'success' => true,
            'status' => 'ok'
        ]);
    } else {
        http_response_code(500);
        logApiAction($pdo, $action, 500);
        echo json_encode([
            'success' => false,
            'status' => 'db_failed'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    logApiAction($pdo, $action, 500);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'database error'
    ]);
}
?>
