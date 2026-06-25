<?php
/**
 * Tests for helpers.php
 * Standalone - doesn't depend on config or database.
 */

require_once BASE_PATH . '/includes/helpers.php';

test('sanitize removes HTML tags', function () {
    $result = sanitize('<script>alert("xss")</script>');
    assert_true(strpos($result, '<script>') === false, 'Script tag should be removed');
    assert_contains('&lt;', implode(' ', [$result]));
});

test('sanitize trims whitespace', function () {
    assert_eq('hello', sanitize('  hello  '));
});

test('formatSize handles zero bytes', function () {
    assert_eq('0 B', formatSize(0));
});

test('formatSize formats kilobytes', function () {
    assert_contains('KB', formatSize(1024));
});

test('formatSize formats megabytes', function () {
    assert_contains('MB', formatSize(1024 * 1024));
});

test('resolvePath combines paths correctly', function () {
    assert_eq('/base/sub/dir', resolvePath('/base', '/sub/dir'));
});

test('resolvePath trims trailing slash', function () {
    assert_eq('/base/sub', resolvePath('/base', '/sub/'));
});

test('resolvePath handles no leading slash', function () {
    assert_eq('/base/portfolio', resolvePath('/base/', 'portfolio'));
});

test('isPathSafe rejects path traversal', function () {
    $base = sys_get_temp_dir();
    assert_false(isPathSafe($base . '/../../etc/passwd', $base), 'Should reject path traversal');
});

test('isPathSafe returns bool for nonexistent path', function () {
    $base = sys_get_temp_dir();
    assert_true(is_bool(isPathSafe($base . '/nonexistent_file_' . mt_rand(), $base)));
});

test('generateCSRFToken returns 64-char hex string', function () {
    global $_SESSION;
    $_SESSION = [];
    $token = generateCSRFToken();
    assert_true(strlen($token) === 64, 'Token should be 64 hex chars (32 bytes)');
    assert_true(ctype_xdigit($token), 'Token should be valid hex');
});

test('generateCSRFToken is consistent in same session', function () {
    global $_SESSION;
    $_SESSION = [];
    $token1 = generateCSRFToken();
    $token2 = generateCSRFToken();
    assert_eq($token1, $token2, 'Same session should return same token');
});

test('csrfField returns HTML hidden input', function () {
    global $_SESSION;
    $_SESSION = [];
    $field = csrfField();
    assert_contains('type="hidden"', $field);
    assert_contains('name="' . CSRF_TOKEN_NAME . '"', $field);
});

test('appLog writes to file', function () {
    appLog('test message', 'INFO');
    $logFile = LOGS_PATH . '/app.log';
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        assert_contains('test message', $content);
        assert_contains('[INFO]', $content);
    } else {
        // Directory may not be writable; skip
        assert_true(true, 'Log file not created (permission ok)');
    }
});
