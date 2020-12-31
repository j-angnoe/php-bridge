<?php
require_once __DIR__ . '/autoload.php';

// Log each url
error_log($_SERVER["REQUEST_METHOD"] . ' ' . $_SERVER['REQUEST_URI']);

if (!isset($_ENV['PHPBRIDGE_PATH'])) {
    error_log('Please run this with ./bin/phpbridge');
    exit(1);
}

session_start();

function bridge($target) {
    $instance = PhpBridge\Bridge::to($target);
    $instance->interrupt();

    ob_start(function($chunk) use (&$instance) {
        return preg_replace_callback('~</head>~', function() use (&$instance) {
            return $instance->output('script') . '</head>';
        }, $chunk);
    });
}

if (is_file($_ENV['PHPBRIDGE_PATH'])) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if (is_file(getcwd() . $path)) {
        // Let php handle this.
        return false;
    }

    include($_ENV['PHPBRIDGE_PATH']);
} else if (is_dir($_ENV['PHPBRIDGE_PATH'])) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if (!$path) {
        $path = 'index.php';
    }

    if (is_file(getcwd() . $path)) {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if ($extension !== 'php') {
            // Let php handle this.
            return false;
        } else { 
            include(getcwd() . $path);
        }
    } else {
        return false;
    }

}