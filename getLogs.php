<?php
date_default_timezone_set('America/Denver');

$host = 'localhost'; $dbname = 'ikelene_storage'; $username = 'ikelene_storage'; $password = 'k@Jc3x007j7}';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
PDO::ATTR_EMULATE_PREPARES => false
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die('DB failed: ' . $e->getMessage());
}

function relativeTime($timestamp) {
    $now = time();
    $ts = strtotime($timestamp);
    $diff = $now - $ts;

    if ($diff < 0) return '?';
    if ($diff < 60) return round($diff) . 's';
    if ($diff < 3600) return round($diff/60) . 'm';
    if ($diff < 86400) return round($diff/3600) . 'h';
    return round($diff/86400, 1) . 'd';
}

function extractApiKeyFromRequestData($requestData) {
    if (!$requestData) return null;

    if (is_array($requestData)) {
        $k = $requestData['apiKey'] ?? $requestData['api_key'] ?? null;
        return is_string($k) && $k !== '' ? $k : null;
    }

    if (!is_string($requestData)) return null;

    $decoded = json_decode($requestData, true);
    if (!is_array($decoded)) return null;

    $k = $decoded['apiKey'] ?? $decoded['api_key'] ?? null;
    return is_string($k) && $k !== '' ? $k : null;
}

$search_ip = $_GET['ip'] ?? '';
$search_action = $_GET['action'] ?? '';
$search_username = $_GET['username'] ?? '';
$search_status = $_GET['status'] ?? '';
$search_date_from = $_GET['date_from'] ?? '';
$search_date_to = $_GET['date_to'] ?? '';
$limit = max(1, min(500, intval($_GET['limit'] ?? 100)));

$params = [];
$where_conditions = [];

if ($search_ip) { $where_conditions[] = 'ip_address LIKE ?'; $params[] = '%' . $search_ip . '%'; }
if ($search_action) { $where_conditions[] = 'action LIKE ?'; $params[] = '%' . $search_action . '%'; }

if ($search_username) { $where_conditions[] = 'username LIKE ?'; $params[] = '%' . $search_username . '%'; }

if ($search_status && $search_status !== 'all') {
    if ($search_status === 'fail') $where_conditions[] = '(response_status >= 400 OR response_status IS NULL)';
    else { $where_conditions[] = 'response_status = ?'; $params[] = intval($search_status); }
}
if ($search_date_from) { $where_conditions[] = 'timestamp >= ?'; $params[] = $search_date_from . ' 00:00:00'; }
if ($search_date_to) { $where_conditions[] = 'timestamp <= ?'; $params[] = $search_date_to . ' 23:59:59'; }

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
$sql = "SELECT id, timestamp, ip_address, user_agent, api_key, username, action, request_method, request_uri, request_data, response_status
FROM api_logs
$where_clause
ORDER BY timestamp DESC
LIMIT ?";
$params[] = $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$apiKeys = [];
foreach ($logs as $log) {
    $k = $log['api_key'] ?? null;
    if (!$k) $k = extractApiKeyFromRequestData($log['request_data'] ?? null);
    if (is_string($k) && $k !== '') $apiKeys[] = $k;
}
$apiKeys = array_values(array_unique($apiKeys));

$userByApiKey = [];
if (!empty($apiKeys)) {
    $placeholders = implode(',', array_fill(0, count($apiKeys), '?'));
    $userSql = "SELECT api_key, username FROM users WHERE api_key IN ($placeholders)";
    $userStmt = $pdo->prepare($userSql);
    $userStmt->execute($apiKeys);
    foreach ($userStmt->fetchAll() as $u) {
        if (!empty($u['api_key'])) $userByApiKey[$u['api_key']] = $u['username'] ?? null;
    }
}

foreach ($logs as &$log) {
    $effectiveApiKey = $log['api_key'] ?? null;
    if (!$effectiveApiKey) $effectiveApiKey = extractApiKeyFromRequestData($log['request_data'] ?? null);

    $resolved = $log['username'] ?? null;
    if (!$resolved && $effectiveApiKey && isset($userByApiKey[$effectiveApiKey]) && $userByApiKey[$effectiveApiKey]) {
        $resolved = $userByApiKey[$effectiveApiKey];
    }

    $log['_effective_api_key'] = $effectiveApiKey;
    $log['_resolved_username'] = $resolved;
}
unset($log);

if ($search_username) {
    $needle = mb_strtolower($search_username);
    $logs = array_values(array_filter($logs, function($log) use ($needle) {
        $u = $log['_resolved_username'] ?? '';
        return mb_strpos(mb_strtolower((string)$u), $needle) !== false;
    }));
}

$lastRequest = $logs ? $logs[0]['timestamp'] : null;
$lastRequestAgo = $lastRequest ? relativeTime($lastRequest) : 'Never';
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API Logs</title>
<link rel="icon" type="image/gif" href="https://ikelene.dev/favicon.gif">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { darkMode: 'class', theme: { extend: { animation: { 'pulse-slow': 'pulse 3s infinite' } } } }
</script>
<style>
[data-theme="dark"] { color-scheme: dark; }
.json-pre { white-space: pre-wrap; font-family: 'Courier New', monospace; font-size: 0.75rem; }
.status-error { animation: pulse-slow; }
.time-badge { font-variant-numeric: tabular-nums; }
</style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-4 sm:p-6">
<div class="max-w-6xl mx-auto">
<?php if ($lastRequest): ?>
<div class="mb-4 p-2 bg-gray-800/50 border border-gray-600 rounded text-xs flex items-center justify-between">
<span class="font-mono">⏱️ <?= date('M j H:i:s', strtotime($lastRequest)) ?> (<?= $lastRequestAgo ?>)</span>
<span class="px-2 py-0.5 rounded-full text-xs <?=
strtotime($lastRequest) > time() - 300 ? 'bg-emerald-500/20 text-emerald-300' : 'bg-orange-500/20 text-orange-300'
?>">
<?= strtotime($lastRequest) > time() - 300 ? 'Live' : 'Stale' ?>
</span>
</div>
<?php endif; ?>

<header class="mb-6 text-center">
<h1 class="text-2xl font-bold bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent mb-1">API Logs</h1>
<p class="text-gray-400 text-xs">Search API activity</p>
</header>

<form method="GET" class="bg-gray-800/30 border border-gray-700 rounded-lg p-4 mb-4">
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
<input type="text" name="ip" value="<?= htmlspecialchars($search_ip) ?>" placeholder="IP" class="px-3 py-1.5 bg-gray-900 border border-gray-600 rounded text-xs focus:ring-1 focus:ring-purple-500">
<input type="text" name="action" value="<?= htmlspecialchars($search_action) ?>" placeholder="Action" class="px-3 py-1.5 bg-gray-900 border border-gray-600 rounded text-xs focus:ring-1 focus:ring-purple-500">
<input type="text" name="username" value="<?= htmlspecialchars($search_username) ?>" placeholder="Username" class="px-3 py-1.5 bg-gray-900 border border-gray-600 rounded text-xs focus:ring-1 focus:ring-purple-500">
<select name="status" class="px-3 py-1.5 bg-gray-900 border border-gray-600 rounded text-xs focus:ring-1 focus:ring-purple-500">
<option value="all">Status</option>
<option value="200" <?= $search_status === '200' ? 'selected' : '' ?>>200</option>
<option value="201" <?= $search_status === '201' ? 'selected' : '' ?>>201</option>
<option value="401" <?= $search_status === '401' ? 'selected' : '' ?>>401</option>
<option value="fail" <?= $search_status === 'fail' ? 'selected' : '' ?>>Fail</option>
</select>
</div>
<div class="flex gap-2 mt-3 pt-3 border-t border-gray-700">
<input type="date" name="date_from" value="<?= htmlspecialchars($search_date_from) ?>" class="px-3 py-1.5 bg-gray-900 border border-gray-600 rounded text-xs flex-1">
<input type="date" name="date_to" value="<?= htmlspecialchars($search_date_to) ?>" class="px-3 py-1.5 bg-gray-900 border border-gray-600 rounded text-xs flex-1 max-w-[140px]">
<input type="number" name="limit" value="<?= $limit ?>" min="1" max="500" class="px-3 py-1.5 bg-gray-900 border border-gray-600 rounded text-xs w-20">
<button type="submit" class="px-4 py-1.5 bg-purple-600 hover:bg-purple-700 rounded font-medium text-white text-xs whitespace-nowrap flex-1 sm:flex-none">Search</button>
<?php if (array_filter([$search_ip, $search_action, $search_username, $search_status, $search_date_from, $search_date_to])): ?>
<a href="." class="px-4 py-1.5 bg-gray-600 hover:bg-gray-700 rounded text-xs font-medium text-gray-200 whitespace-nowrap">Clear</a>
<?php endif; ?>
</div>
</form>

<?php if (!empty($logs)): ?>
<div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden mb-6">
<div class="bg-gray-900/50 px-3 py-1.5 text-xs font-mono text-gray-400 border-b border-gray-700">
<?= count($logs) ?> logs
<?php $errorCount = count(array_filter($logs, fn($log) => ($log['response_status'] ?? 0) >= 400));
if ($errorCount): ?>
    <span class="ml-2 px-1.5 py-0.5 bg-red-500/20 text-red-400 rounded text-xs font-bold"><?= $errorCount ?> Fail</span>
    <?php endif; ?>
    · Latest <?= date('H:i:s', strtotime($logs[0]['timestamp'])) ?> (<?= relativeTime($logs[0]['timestamp']) ?>)
    </div>

    <div class="overflow-x-auto">
    <table class="w-full text-xs">
    <thead class="bg-gray-900/30">
    <tr>
    <th class="px-3 py-1.5 text-left font-semibold text-gray-300 w-24">Time</th>
    <th class="px-2 py-1.5 text-left font-semibold text-gray-300 min-w-[140px]">IP</th>
    <th class="px-2 py-1.5 text-left font-semibold text-gray-300 min-w-[90px]">Action</th>
    <th class="px-2 py-1.5 text-left font-semibold text-gray-300 min-w-[60px]">Status</th>
    <th class="px-2 py-1.5 text-left font-semibold text-gray-300 min-w-[120px]">User</th>
    <th class="px-2 py-1.5 text-left font-semibold text-gray-300 min-w-[100px]">Method/URI</th>
    <th class="px-2 py-1.5 text-left font-semibold text-gray-300 w-40">Data</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-700/30">
    <?php foreach ($logs as $log): $status = $log['response_status'] ?? 0; $isError = $status >= 400; ?>
    <tr class="hover:bg-gray-800/30 transition-colors group <?php if($isError) echo 'border-l-2 border-red-500 bg-red-950/10'; ?>">
    <td class="px-3 py-1.5">
    <div class="font-mono">
    <div class="font-bold time-badge"><?= date('M j H:i:s', strtotime($log['timestamp'])) ?></div>
    <div class="text-gray-500 text-[10px]"><?= relativeTime($log['timestamp']) ?></div>
    </div>
    </td>

    <td class="px-2 py-1.5">
    <span class="text-[10px] font-mono bg-gray-700 px-1.5 py-0.5 rounded whitespace-nowrap <?= $isError ? 'ring-1 ring-red-500/30' : '' ?>"
    title="<?= htmlspecialchars($log['ip_address']) ?>">
    <?= htmlspecialchars($log['ip_address']) ?>
    </span>
    </td>

    <td class="px-2 py-1.5">
    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium
    <?= $isError ? 'bg-red-500/20 text-red-300 border border-red-500/40' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' ?>">
    <?= htmlspecialchars($log['action']) ?>
    </span>
    </td>

    <td class="px-2 py-1.5">
    <?php if ($log['response_status']): ?>
    <span class="status-badge px-2 py-0.5 rounded text-[11px] font-bold border
    <?= $isError
    ? 'bg-red-600/90 text-red-50 border-red-400/50 shadow-sm shadow-red-900/30 status-error'
    : 'bg-emerald-600/90 text-emerald-50 border-emerald-400/50 shadow-sm shadow-emerald-900/30' ?>">
    <?= $log['response_status'] ?>
    </span>
    <?php else: ?>
    <span class="text-gray-500 px-1.5 py-0.5 rounded text-[10px] bg-gray-800/50">-</span>
    <?php endif; ?>
    </td>

    <td class="px-2 py-1.5 text-[11px] font-medium <?= $isError ? 'text-red-400' : 'text-emerald-400' ?>">
    <?php
    $displayUser = $log['_resolved_username'] ?? '';
    if (!$displayUser) {
        // fallback to api key preview if we have it, otherwise "—"
        $k = $log['_effective_api_key'] ?? ($log['api_key'] ?? '');
        $displayUser = $k ? (substr($k, 0, 6) . '...') : '—';
    }
    ?>
    <span title="<?= htmlspecialchars((string)($log['_effective_api_key'] ?? '')) ?>">
    <?= htmlspecialchars((string)$displayUser) ?>
    </span>
    </td>

    <td class="px-2 py-1.5 text-[10px]">
    <div class="font-mono text-gray-400 truncate max-w-[120px]">
    <?= htmlspecialchars($log['request_method']) ?> <?= htmlspecialchars(substr($log['request_uri'], 0, 35)) . '...' ?>
    </div>
    </td>

    <td class="px-2 py-1.5">
    <?php if ($log['request_data']): ?>
    <details class="cursor-pointer">
    <summary class="text-[10px] px-1.5 py-0.5 rounded hover:bg-gray-700/30 transition-all">JSON</summary>
    <pre class="json-pre mt-1 p-1.5 bg-gray-900 rounded text-[10px] border border-gray-700 max-h-32 overflow-auto"><?= htmlspecialchars($log['request_data']) ?></pre>
    </details>
    <?php else: ?>
    <span class="text-[10px] text-gray-500 px-1.5 py-0.5 rounded bg-gray-800/50">—</span>
    <?php endif; ?>
    </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>
    </div>
    <?php endif; ?>

    <?php if (empty($logs)): ?>
    <div class="text-center py-12 text-gray-500">
    <div class="text-2xl mb-2 opacity-25">📊</div>
    <p class="text-sm">No logs match</p>
    <a href="." class="text-purple-400 hover:text-purple-300 text-xs mt-1 inline-block">Clear filters</a>
    </div>
    <?php endif; ?>

    <div class="mt-6 text-center text-[10px] text-gray-500 opacity-75">
    ikelene_storage.api_logs · <?= date('M j, Y H:i') ?>
    </div>
    </div>
    </body>
    </html>
