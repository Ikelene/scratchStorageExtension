<?php
date_default_timezone_set('America/Denver');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

$db_host = 'localhost';
$db_name = 'STORAGE_NAME_HERE';
$db_user = 'DB_USER_HERE';
$db_pass = 'DB_PASS_HERE';

try {
    $dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_connection_failed']);
    exit;
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function readJsonBody() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function requireApiKey($pdo, $apiKey) {
    if (!is_string($apiKey) || $apiKey === '') {
        respond(['success' => false, 'error' => 'missing_api_key'], 401);
    }
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE api_key = ? LIMIT 1');
    $stmt->execute([$apiKey]);
    $user = $stmt->fetch();
    if (!$user) {
        respond(['success' => false, 'error' => 'invalid_api_key'], 401);
    }
    return $user;
}

function logApiRequest($pdo, $apiKey, $username, $action, $requestData = null, $responseStatus = 200) {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO api_logs (ip_address, user_agent, api_key, username, action, request_method, request_uri, request_data, response_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $apiKey,
            $username,
            $action,
            $_SERVER['REQUEST_METHOD'] ?? '',
            $_SERVER['REQUEST_URI'] ?? '',
            $requestData !== null ? json_encode($requestData) : null,
            $responseStatus,
        ]);
    } catch (Exception $e) {
        // logging is never allowed to break the request
    }
}

/**
 * Detects whether an incoming key/value pair is an uploaded file
 * (binary data, base64 data URI, raw encoded file, or file extension).
 */
function isFileUpload(string $key, string $value): bool {
    // 1. Block file extensions in the key
    $fileExtensions = '/\.(jpg|jpeg|png|gif|webp|svg|bmp|ico|pdf|zip|rar|tar|gz|7z|exe|bin|iso|dmg|deb|rpm|mp3|mp4|wav|avi|mov|mkv|ogg|flac|docx?|xlsx?|pptx?|php|phtml|phar|sh|bat|cmd|apk)$/i';
    if (preg_match($fileExtensions, $key)) {
        return true;
    }

    // 2. Block base64 Data URIs (e.g. data:image/png;base64,...)
    if (preg_match('/^data:[a-zA-Z0-9\/\-+.]+;base64,/i', $value)) {
        return true;
    }

    // 3. Block raw binary data containing null bytes
    if (str_contains($value, "\0")) {
        return true;
    }

    // 4. Magic byte inspection for binary files (PNG, JPEG, GIF, PDF, ZIP, RAR, EXE, ELF)
    $magicSignatures = [
        "\x89PNG\r\n\x1a\n", // PNG
        "\xFF\xD8\xFF",       // JPEG
        "GIF87a",             // GIF
        "GIF89a",             // GIF
        "%PDF-",              // PDF
        "PK\x03\x04",         // ZIP (and DOCX/XLSX/JAR/APK)
        "PK\x05\x06",         // ZIP empty archive
        "Rar!\x1a\x07",       // RAR
        "\x7FELF",            // ELF (Linux binary)
        "MZ",                 // DOS / Windows PE executable
        "7z\xBC\xAF\x27\x1C", // 7-Zip
    ];

    foreach ($magicSignatures as $sig) {
        if (str_starts_with($value, $sig)) {
            return true;
        }
    }

    // 5. Detect base64-encoded files (inspect header bytes after decode)
    $cleanValue = trim($value);
    if (strlen($cleanValue) >= 64 && preg_match('/^[a-zA-Z0-9\/+=\r\n]+$/', substr($cleanValue, 0, 128))) {
        $decodedHeader = base64_decode(substr($cleanValue, 0, 64), true);
        if ($decodedHeader !== false && strlen($decodedHeader) > 4) {
            foreach ($magicSignatures as $sig) {
                if (str_starts_with($decodedHeader, $sig)) {
                    return true;
                }
            }
        }
    }

    // 6. Native Fileinfo check if available
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detectedMime = finfo_buffer($finfo, substr($value, 0, 2048));
            finfo_close($finfo);

            // Anything outside standard text formats is blocked
            $allowedTextTypes = [
                'text/plain',
                'application/json',
                'text/csv',
            ];

            if ($detectedMime && !in_array($detectedMime, $allowedTextTypes, true)) {
                return true;
            }
        }
    }

    return false;
}
