<?php

declare(strict_types=1);

// Zero-dependency test runner
spl_autoload_register(function (string $class) {
    $prefix = 'EidCloud\\ModelRouter\\';
    $baseDir = dirname(__DIR__) . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    if (str_starts_with($relativeClass, 'Tests\\')) {
        $file = dirname(__DIR__) . '/tests/' . str_replace('\\', '/', substr($relativeClass, 6)) . '.php';
    } else {
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    }

    if (file_exists($file)) {
        require_once $file;
    }
});

use EidCloud\ModelRouter\Tests\ModelRouterTest;

$test = new ModelRouterTest();
$passed = $test->run();

exit($passed ? 0 : 1);
