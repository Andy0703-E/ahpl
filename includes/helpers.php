<?php

// --- CSRF Protection ---

function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
}

function csrfToken() {
    return generateCSRFToken();
}

function verifyCSRFToken() {
    $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || empty($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function requireCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
        if (!verifyCSRFToken()) {
            jsonResponse(['error' => 'Invalid CSRF token'], 403);
        }
    }
}

// --- Auth ---

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /panel/login.php');
        exit;
    }
}

function currentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindValue(':id', (int)$_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    $result->finalize();
    return $user ?: null;
}

function needsPasswordChange() {
    $user = currentUser();
    if (!$user) return false;
    return empty($user['password_changed']);
}

function login($username, $password) {
    // Rate limiting
    $identifier = 'login:' . $username;
    if (isRateLimited($identifier)) {
        return false;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    $result->finalize();

    if ($user && password_verify($password, $user['password'])) {
        resetRateLimit($identifier);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        logAction('login', $username);
        return true;
    }

    recordAttempt($identifier);
    return false;
}

function logout() {
    logAction('logout', $_SESSION['username'] ?? '');
    session_destroy();
    header('Location: /panel/login.php');
    exit;
}

function changePassword($userId, $newPassword) {
    $db = getDB();
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password = :p, password_changed = 1 WHERE id = :id");
    $stmt->bindValue(':p', $hash, SQLITE3_TEXT);
    $stmt->bindValue(':id', (int)$userId, SQLITE3_INTEGER);
    $stmt->execute();
}

// --- Rate Limiting ---

function isRateLimited($identifier) {
    $db = getDB();
    $windowStart = time() - RATE_LIMIT_WINDOW;
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE identifier = :id AND window_start < :ws");
    $stmt->bindValue(':id', $identifier, SQLITE3_TEXT);
    $stmt->bindValue(':ws', $windowStart, SQLITE3_INTEGER);
    $stmt->execute();

    $stmt = $db->prepare("SELECT SUM(attempts) FROM rate_limits WHERE identifier = :id");
    $stmt->bindValue(':id', $identifier, SQLITE3_TEXT);
    $result = $stmt->execute();
    $count = (int)($result->fetchArray(SQLITE3_NUM)[0] ?? 0);
    $result->finalize();
    return $count >= RATE_LIMIT_MAX_ATTEMPTS;
}

function recordAttempt($identifier) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO rate_limits (identifier, attempts, window_start) VALUES (:id, 1, :ws)");
    $stmt->bindValue(':id', $identifier, SQLITE3_TEXT);
    $stmt->bindValue(':ws', time(), SQLITE3_INTEGER);
    $stmt->execute();
}

function resetRateLimit($identifier) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE identifier = :id");
    $stmt->bindValue(':id', $identifier, SQLITE3_TEXT);
    $stmt->execute();
}

// --- Response ---

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    while (ob_get_level()) ob_end_clean();
    echo json_encode($data);
    exit;
}

// --- Helpers ---

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function formatSize($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

function resolvePath($base, $path) {
    return rtrim(rtrim($base, '/') . '/' . ltrim($path, '/'), '/');
}

function getServerInfo() {
    $info = [];

    $info['php_version'] = phpversion();
    $info['mem_total'] = 0;
    $info['mem_used'] = 0;
    $info['mem_free'] = 0;

    $info['disk_total'] = @disk_total_space(SERVER_PATH) ?: 0;
    $info['disk_free'] = @disk_free_space(SERVER_PATH) ?: 0;
    $info['disk_used'] = $info['disk_total'] - $info['disk_free'];

    $mem = @file_get_contents('/proc/meminfo');
    if ($mem) {
        preg_match('/MemTotal:\s+(\d+)/', $mem, $m);
        $info['mem_total'] = ($m[1] ?? 0) * 1024;
        preg_match('/MemAvailable:\s+(\d+)/', $mem, $m);
        $info['mem_free'] = ($m[1] ?? 0) * 1024;
        $info['mem_used'] = $info['mem_total'] - $info['mem_free'];
    }

    return $info;
}

function countFiles($dir) {
    if (!is_dir($dir)) return 0;
    $count = 0;
    $scan = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($scan as $file) {
        if ($file->isFile()) $count++;
    }
    return $count;
}

function isPathSafe($path, $base) {
    $real = realpath($path);
    $realBase = realpath($base);
    if ($real === false || $realBase === false) return false;
    return strpos($real, $realBase) === 0;
}

function getSetting($key) {
    $db = getDB();
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = :key");
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $result = $stmt->execute();
    $value = $result->fetchArray(SQLITE3_NUM)[0] ?? null;
    $result->finalize();
    return $value;
}

function setSetting($key, $value) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE key = :k");
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $result = $stmt->execute();
    $existing = (int)($result->fetchArray(SQLITE3_NUM)[0] ?? 0);
    $result->finalize();

    if ($existing) {
        $stmt = $db->prepare("UPDATE settings SET value = :v WHERE key = :k");
    } else {
        $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (:k, :v)");
    }
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $stmt->bindValue(':v', $value, SQLITE3_TEXT);
    $stmt->execute();
}

function getCerebrasKey() {
    $encrypted = getSetting(SETTING_CEREBRAS_KEY);
    if (empty($encrypted)) return null;
    return decryptApiKey($encrypted);
}

function setCerebrasKey($key) {
    setSetting(SETTING_CEREBRAS_KEY, encryptApiKey($key));
}

function encryptApiKey($plaintext) {
    if (empty($plaintext)) return '';
    $key = defined('APP_NAME') ? APP_NAME : 'AHPL';
    $result = '';
    for ($i = 0; $i < strlen($plaintext); $i++) {
        $result .= chr(ord($plaintext[$i]) ^ ord($key[$i % strlen($key)]));
    }
    return base64_encode($result);
}

function decryptApiKey($encoded) {
    if (empty($encoded)) return '';
    $data = base64_decode($encoded, true);
    if ($data === false) return $encoded; // fallback: return as-is for legacy keys
    $key = defined('APP_NAME') ? APP_NAME : 'AHPL';
    $result = '';
    for ($i = 0; $i < strlen($data); $i++) {
        $result .= chr(ord($data[$i]) ^ ord($key[$i % strlen($key)]));
    }
    return $result;
}

function appLog($message, $level = 'INFO') {
    if (!is_dir(LOGS_PATH)) @mkdir(LOGS_PATH, 0755, true);
    $file = LOGS_PATH . '/app.log';
    $line = date('Y-m-d H:i:s') . " [$level] $message" . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}
