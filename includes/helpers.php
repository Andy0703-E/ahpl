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
    if (!isset($_SESSION['user_id'])) return false;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
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
        $_SESSION['last_activity'] = time();
        logAction('login', $username . ' from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
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
    $realBase = realpath($base);
    if ($realBase === false) return false;
    $real = realpath($path);
    if ($real === false) {
        $real = realpath(dirname($path)) . '/' . basename($path);
    }
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

// --- Website Status ---

function websiteStatus($folder) {
    $dir = WEBSITES_PATH . '/' . $folder;
    if (!is_dir($dir)) return 'offline';
    foreach (['index.html', 'index.php', 'index.htm'] as $f) {
        if (file_exists($dir . '/' . $f)) return 'online';
    }
    return 'offline';
}

// --- Storage ---

function dirSize($dir) {
    $size = 0;
    if (!is_dir($dir)) return 0;
    $scan = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($scan as $file) {
        if ($file->isFile()) $size += $file->getSize();
    }
    return $size;
}

function websiteStorage() {
    $db = getDB();
    $sites = $db->query("SELECT id, name, folder FROM websites ORDER BY name");
    $result = [];
    while ($site = $sites->fetchArray(SQLITE3_ASSOC)) {
        $siteDir = WEBSITES_PATH . '/' . $site['folder'];
        $result[] = [
            'id' => $site['id'],
            'name' => $site['name'],
            'folder' => $site['folder'],
            'size' => dirSize($siteDir),
            'files' => countFiles($siteDir),
        ];
    }
    return $result;
}

// --- Backup ---

function createBackup($type = 'websites') {
    if (!is_dir(BACKUPS_PATH)) mkdir(BACKUPS_PATH, 0755, true);
    $date = date('Ymd_His');
    $filename = "backup_{$type}_{$date}.zip";
    $filepath = BACKUPS_PATH . '/' . $filename;

    $zip = new ZipArchive();
    if ($zip->open($filepath, ZipArchive::CREATE) !== true) {
        return ['error' => 'Gagal membuat ZIP'];
    }

    if ($type === 'websites' || $type === 'full') {
        $sites = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(WEBSITES_PATH, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($sites as $file) {
            $localPath = 'websites/' . substr($file->getPathname(), strlen(WEBSITES_PATH) + 1);
            $zip->addFile($file->getPathname(), $localPath);
        }
    }

    if ($type === 'database' || $type === 'full') {
        if (file_exists(DB_FILE)) {
            $zip->addFile(DB_FILE, 'database/ahpl.db');
        }
    }

    $zip->close();
    return ['success' => true, 'file' => $filename, 'size' => filesize($filepath)];
}

// --- File Tree ---

function buildTreeHtml($dir, $basePath, $currentFile) {
    $html = '<ul>';
    $items = scandir($dir);
    sort($items);
    foreach ($items as $item) {
        if ($item[0] === '.') continue;
        $full = $dir . '/' . $item;
        $rel = ltrim($basePath . '/' . $item, '/');
        $encRel = htmlspecialchars($rel);
        if (is_dir($full)) {
            $html .= '<li class="tree-folder"><span class="tree-toggle" onclick="treeToggle(this)">&#9656;</span> <span class="tree-name">' . htmlspecialchars($item) . '</span>';
            $html .= '<span class="tree-actions"><button class="btn-icon" onclick="event.stopPropagation();renameFileByPath(\'' . $encRel . '\')" title="Rename"><i class="fas fa-i-cursor"></i></button><button class="btn-icon del" onclick="event.stopPropagation();deleteFileByPath(\'' . $encRel . '\')" title="Delete"><i class="fas fa-trash"></i></button></span>';
            $html .= buildTreeHtml($full, $rel, $currentFile);
            $html .= '</li>';
        } else {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $editable = in_array($ext, ['html','htm','css','js','php','json','txt','xml','md','svg']);
            if (!$editable) continue;
            $active = ($rel === $currentFile) ? ' active' : '';
            $iconMap = ['html'=>'<i class="fas fa-file-code" style="color:#e44d26"></i>','htm'=>'<i class="fas fa-file-code" style="color:#e44d26"></i>','css'=>'<i class="fas fa-file-code" style="color:#264de4"></i>','js'=>'<i class="fas fa-file-code" style="color:#f7df1e"></i>','php'=>'<i class="fas fa-file-code" style="color:#8892bf"></i>','json'=>'<i class="fas fa-file-code" style="color:#28a745"></i>'];
            $icon = $iconMap[$ext] ?? '<i class="fas fa-file"></i>';
            $html .= '<li class="tree-file' . $active . '" data-path="' . $encRel . '" onclick="loadFile(\'' . $encRel . '\')">' . $icon . ' ' . htmlspecialchars($item) . '<span class="tree-actions"><button class="btn-icon" onclick="event.stopPropagation();renameFileByPath(\'' . $encRel . '\')" title="Rename"><i class="fas fa-i-cursor"></i></button><button class="btn-icon del" onclick="event.stopPropagation();deleteFileByPath(\'' . $encRel . '\')" title="Delete"><i class="fas fa-trash"></i></button></span></li>';
        }
    }
    $html .= '</ul>';
    return $html;
}

// --- Cloudflared Tunnel URL ---

function getTunnelUrl() {
    $logFile = TUNNEL_LOG;
    if (!file_exists($logFile)) return null;
    $content = @file_get_contents($logFile);
    if (!$content) return null;
    if (preg_match('/https?:\/\/[a-zA-Z0-9][-a-zA-Z0-9]*\.trycloudflare\.com/', $content, $m)) {
        return $m[0];
    }
    return null;
}

function saveTunnelUrl() {
    $url = getTunnelUrl();
    if ($url) {
        $db = getDB();
        $db->exec("DELETE FROM tunnel_domains");
        $stmt = $db->prepare("INSERT INTO tunnel_domains (url) VALUES (:url)");
        $stmt->bindValue(':url', $url, SQLITE3_TEXT);
        $stmt->execute();
        return $url;
    }
    return null;
}

function deleteTunnelUrl() {
    $db = getDB();
    $db->exec("DELETE FROM tunnel_domains");
}

// --- Service Manager ---

function isProcessRunning($name) {
    if ($name === 'nginx') {
        $conn = @fsockopen('127.0.0.1', 8080, $e, $s, 1);
        if ($conn) { fclose($conn); return true; }
        $out = @shell_exec("pidof nginx 2>/dev/null");
        if (!empty(trim($out ?? ''))) return true;
        return false;
    }
    if ($name === 'php-fpm') {
        // Current request IS served by PHP-FPM
        if (php_sapi_name() === 'fpm-fcgi') return true;
        $conn = @fsockopen('127.0.0.1', 9000, $e, $s, 1);
        if ($conn) { fclose($conn); return true; }
        $socks = [
            '/data/data/com.termux/files/usr/var/run/php-fpm.sock',
            '/var/run/php-fpm.sock',
            '/run/php-fpm.sock',
        ];
        foreach ($socks as $s) {
            if (file_exists($s)) return true;
        }
        $pidFile = '/data/data/com.termux/files/usr/var/run/php-fpm.pid';
        if (file_exists($pidFile) && is_numeric(trim(@file_get_contents($pidFile)))) return true;
        $out = @shell_exec("pidof php-fpm 2>/dev/null");
        if (!empty(trim($out ?? ''))) return true;
        return false;
    }
    if ($name === 'cloudflared') {
        $out = @shell_exec("pidof cloudflared 2>/dev/null || pgrep cloudflared 2>/dev/null");
        if (!empty(trim($out ?? ''))) return true;
        return false;
    }
    if ($name === 'mariadb') {
        $out = @shell_exec("pidof mariadbd mysqld 2>/dev/null");
        if (!empty(trim($out ?? ''))) return true;
        $out2 = @shell_exec("pgrep -x mariadbd 2>/dev/null; pgrep -x mysqld 2>/dev/null");
        if (!empty(trim($out2 ?? ''))) return true;
        $socket = '/data/data/com.termux/files/usr/var/run/mysqld.sock';
        if (file_exists($socket)) return true;
        return false;
    }
    return false;
}

function checkServiceStatus($service) {
    if (PHP_OS_FAMILY === 'Windows') return 'unknown';
    return isProcessRunning($service) ? 'running' : 'stopped';
}

function startService($service) {
    if (checkServiceStatus($service) === 'running') return ['success' => true, 'status' => 'running'];
    switch ($service) {
        case 'nginx':
            $out = @shell_exec('nginx 2>&1');
            break;
        case 'php-fpm':
            $out = @shell_exec('php-fpm -R 2>&1');
            break;
        case 'mariadb':
            $datadir = '/data/data/com.termux/files/usr/var/lib/mysql';
            $logFile = '/data/data/com.termux/files/usr/var/lib/mysql/mariadb-start.log';
            @file_put_contents($logFile, '');
            if (!file_exists($datadir . '/mysql')) {
                @shell_exec('mysql_install_db --datadir=' . escapeshellarg($datadir) . ' 2>/dev/null');
            }
            $pidfile = '/data/data/com.termux/files/usr/var/run/mysqld.pid';
            @unlink('/data/data/com.termux/files/usr/var/run/mysqld.sock');
            @unlink('/data/data/com.termux/files/usr/var/run/mysqld.sock.lock');
            @unlink($pidfile);
            $bin = is_executable('/data/data/com.termux/files/usr/bin/mariadbd-safe') ? 'mariadbd-safe' : 'mysqld_safe';
            @shell_exec('nohup ' . $bin . ' --skip-grant-tables > ' . escapeshellarg($logFile) . ' 2>&1 &');
            $socket = '/data/data/com.termux/files/usr/var/run/mysqld.sock';
            for ($i = 0; $i < 15; $i++) {
                if (file_exists($socket)) break;
                sleep(1);
            }
            if (!file_exists($socket)) {
                $err = file_exists($logFile) ? trim(file_get_contents($logFile)) : 'Tidak ada output';
                return ['error' => 'Gagal start mariadb: ' . substr($err, 0, 300)];
            }
            sleep(1);
            return ['success' => true, 'status' => 'running'];
        case 'cloudflared':
            $cfBin = null;
            $cfPaths = ['/data/data/com.termux/files/usr/bin/cloudflared', '/usr/bin/cloudflared', '/bin/cloudflared'];
            foreach ($cfPaths as $p) { if (is_executable($p)) { $cfBin = $p; break; } }
            if (!$cfBin) {
                $which = @shell_exec("command -v cloudflared 2>/dev/null || which cloudflared 2>/dev/null");
                if ($which) $cfBin = trim($which);
            }
            if (!$cfBin) {
                return ['error' => 'cloudflared tidak ditemukan. Install: pkg install cloudflared'];
            }
            $localUrl = 'http://localhost:8080';
            $logFile = TUNNEL_LOG;
            @shell_exec("nohup " . escapeshellarg($cfBin) . " tunnel --url $localUrl > " . escapeshellarg($logFile) . " 2>&1 &");
            sleep(2);
            if (!isProcessRunning('cloudflared')) {
                $err = file_exists($logFile) ? trim(file_get_contents($logFile)) : 'Tidak ada output';
                return ['error' => 'cloudflared gagal start: ' . substr($err, 0, 200)];
            }
            for ($i = 0; $i < 15; $i++) {
                $url = saveTunnelUrl();
                if ($url) break;
                sleep(1);
            }
            return ['success' => true, 'status' => 'running'];
        default:
            return ['error' => 'Service tidak dikenal'];
    }
    sleep(1);
    $status = checkServiceStatus($service);
    return $status === 'running'
        ? ['success' => true, 'status' => $status]
        : ['error' => "Gagal start $service", 'output' => $out ?? ''];
}

function stopService($service) {
    if (checkServiceStatus($service) === 'stopped') return ['success' => true, 'status' => 'stopped'];
    switch ($service) {
        case 'nginx':
            $out = @shell_exec('nginx -s stop 2>&1');
            break;
        case 'php-fpm':
            $pidFile = '/data/data/com.termux/files/usr/var/run/php-fpm.pid';
            if (file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                $out = @shell_exec("kill -QUIT $pid 2>&1");
            } else {
                $out = @shell_exec('pkill -f "php-fpm: master" 2>&1');
            }
            break;
        case 'mariadb':
            $out = @shell_exec('mysqladmin -u root shutdown 2>&1');
            sleep(1);
            if (checkServiceStatus('mariadb') !== 'stopped') {
                $out2 = @shell_exec('pkill -f mariadbd 2>&1; pkill -f mysqld 2>&1');
                $out = ($out ?: '') . "\n" . ($out2 ?: '');
            }
            break;
        case 'cloudflared':
            deleteTunnelUrl();
            $out = @shell_exec('pkill cloudflared 2>&1');
            break;
        default:
            return ['error' => 'Service tidak dikenal'];
    }
    sleep(1);
    $status = checkServiceStatus($service);
    return $status === 'stopped'
        ? ['success' => true, 'status' => $status]
        : ['error' => "Gagal stop $service", 'output' => $out ?? ''];
}

// --- GitHub Deploy ---

function findGit() {
    $paths = ['/data/data/com.termux/files/usr/bin/git', '/usr/bin/git', '/bin/git', '/usr/local/bin/git'];
    foreach ($paths as $p) { if (is_executable($p)) return $p; }
    $which = @shell_exec("command -v git 2>/dev/null");
    if ($which) return trim($which);
    $which = @shell_exec("which git 2>/dev/null");
    if ($which) return trim($which);
    return null;
}

function deployGithub($repoUrl, $destDir) {
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    $gitBin = findGit();
    if (!$gitBin) {
        return ['error' => 'Git tidak terinstall. Install git di Termux: pkg install git && pkg upgrade'];
    }

    $tmpDir = sys_get_temp_dir() . '/ahpl_gh_' . uniqid();
    $cmd = escapeshellarg($gitBin) . " clone --depth 1 " . escapeshellarg($repoUrl) . " " . escapeshellarg($tmpDir) . " 2>&1";
    $output = @shell_exec($cmd);

    if (!is_dir($tmpDir)) {
        $errMsg = $output ? trim($output) : 'Tidak ada output';
        return ['error' => 'Gagal clone repository: ' . $errMsg];
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $copied = 0;
    foreach ($it as $file) {
        if ($file->getFilename() === '.git') continue;
        $relPath = substr($file->getPathname(), strlen($tmpDir) + 1);
        $dest = $destDir . '/' . $relPath;
        $dir = dirname($dest);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        copy($file->getPathname(), $dest);
        $copied++;
    }

    // Cleanup
    $rmCmd = PHP_OS_FAMILY === 'Windows' ? "rmdir /s /q " : "rm -rf ";
    @shell_exec($rmCmd . escapeshellarg($tmpDir));

    return ['success' => true, 'copied' => $copied];
}

// --- Visitor Log ---

function logVisit() {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $page = $_SERVER['REQUEST_URI'] ?? '';
    $stmt = $db->prepare("INSERT INTO visitor_logs (ip, user_agent, page) VALUES (:ip, :ua, :page)");
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->bindValue(':ua', $ua, SQLITE3_TEXT);
    $stmt->bindValue(':page', $page, SQLITE3_TEXT);
    $stmt->execute();
}

// --- Login History ---

function getLoginHistory($limit = 20) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM logs WHERE action = 'login' ORDER BY id DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $logs = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $logs[] = $row;
    }
    $result->finalize();
    return $logs;
}
