<?php
/**
 * AHPL Test Runner
 * Usage: php tests/run.php
 */

define('BASE_PATH', dirname(__DIR__));
define('TESTS_PATH', __DIR__);
define('APP_NAME', 'AHPL');
define('APP_VERSION', '1.1.0');
define('SETTING_CEREBRAS_KEY', 'cerebras_key');
define('CSRF_TOKEN_NAME', 'ahpl_csrf_token');
define('RATE_LIMIT_WINDOW', 300);
define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('MAX_ZIP_EXTRACT_SIZE', 500 * 1024 * 1024);
define('MAX_ZIP_DEPTH', 5);
define('SERVER_PATH', sys_get_temp_dir() . '/ahpl_test_server');
define('WEBSITES_PATH', SERVER_PATH . '/websites');
define('LOGS_PATH', SERVER_PATH . '/logs');

$_SESSION = [];

$passed = 0;
$failed = 0;

function test($name, $fn) {
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "  \033[32m✓\033[0m $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  \033[31m✗\033[0m $name\n    " . $e->getMessage() . "\n";
    }
}

function assert_eq($expected, $actual, $msg = '') {
    if ($expected !== $actual) {
        throw new RuntimeException($msg ?: "Expected " . var_export($expected, true) . ", got " . var_export($actual, true));
    }
}

function assert_true($val, $msg = '') {
    assert_eq(true, (bool)$val, $msg);
}

function assert_false($val, $msg = '') {
    assert_eq(false, (bool)$val, $msg);
}

function assert_contains($needle, $haystack, $msg = '') {
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($msg ?: "Expected '$needle' to be contained in '$haystack'");
    }
}

echo "\033[1mAHPL Test Suite v" . APP_VERSION . "\033[0m\n\n";

$testFiles = glob(TESTS_PATH . '/test_*.php');
foreach ($testFiles as $file) {
    if (substr_count(file_get_contents($file, false, null, 0, 100), '<?php') == 0) continue;
    echo "\033[36m" . basename($file) . "\033[0m\n";
    require $file;
    echo "\n";
}

echo "\033[1m" . str_repeat('-', 40) . "\033[0m\n";
$total = $passed + $failed;
echo "Total: $total | ";
if ($total === 0) {
    echo "\033[33mNo tests ran\033[0m\n";
} elseif ($failed === 0) {
    echo "\033[32mAll passed!\033[0m\n";
} else {
    echo "\033[31m$failed failed\033[0m, \033[32m$passed passed\033[0m\n";
}
echo "\n";

exit($failed > 0 ? 1 : 0);
