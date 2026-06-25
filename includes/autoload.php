<?php
/**
 * Simple PSR-4 autoloader for AHPL
 */
spl_autoload_register(function ($class) {
    // Map AHPL namespace to project root
    $prefix = 'AHPL\\';
    $baseDir = BASE_PATH;

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
