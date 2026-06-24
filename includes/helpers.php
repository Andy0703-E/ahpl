<?php
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
    return $db->querySingle("SELECT * FROM users WHERE id = " . (int)$_SESSION['user_id'], true);
}

function login($username, $password) {
    $db = getDB();
    $user = $db->querySingle("SELECT * FROM users WHERE username = '" . SQLite3::escapeString($username) . "'", true);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        logAction('login', $username);
        return true;
    }
    return false;
}

function logout() {
    logAction('logout', $_SESSION['username'] ?? '');
    session_destroy();
    header('Location: /panel/login.php');
    exit;
}

function jsonResponse($data, $code = 200) {
    ob_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function formatSize($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

function getServerInfo() {
    $info = [];
    
    $info['php_version'] = phpversion();
    $info['uptime'] = 'N/A';
    $info['battery'] = 'N/A';
    $info['temperature'] = 'N/A';
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
    
    $up = @file_get_contents('/proc/uptime');
    if ($up) {
        $sec = (float)explode(' ', $up)[0];
        $d = floor($sec / 86400);
        $h = floor(($sec % 86400) / 3600);
        $m = floor(($sec % 3600) / 60);
        $info['uptime'] = "{$d}d {$h}h {$m}m";
    }
    
    $bat = @file_get_contents('/sys/class/power_supply/battery/capacity');
    if ($bat !== false) $info['battery'] = trim($bat) . '%';
    
    $temp = @file_get_contents('/sys/class/thermal/thermal_zone0/temp');
    if ($temp !== false) $info['temperature'] = round((int)trim($temp) / 1000, 1) . '°C';
    
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
