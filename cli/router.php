<?php
require_once __DIR__ . '/autoload.php';

// This may be removed.
ini_set('display_errors', 'on');
error_reporting(E_ALL);

use PhpBridge\Bridge;
use PhpBridge\Utils;
use PhpBridge\Server;

$ob_size = ini_get('output_buffering');
if ($ob_size && $ob_size < (16*1024)) {
    file_put_contents('php://stderr', 
        'Warning: Maximum output buffering size is set to ' . $ob_size . ' bytes. ' . PHP_EOL .
        'This may cause `Headers already sent errors` when working with larger documents. ' . PHP_EOL . 
        'You may increase this limit (in php.ini) to above 16K for instance.'
    );
}

// Log each url

if (!isset($_ENV['PHPBRIDGE_PATH'])) {
    error_log('Please run this with ./bin/phpbridge');
    exit(1);
}

error_log("[" . basename($_ENV['PHPBRIDGE_PATH']) . ", pid " . getmypid() . "] " . $_SERVER["REQUEST_METHOD"] . ' ' . $_SERVER['REQUEST_URI']);
$layers = array_filter(array_map('trim', explode(',', $_ENV['PHPBRIDGE_LAYER'] ?? '')));

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$server = new Server($_ENV['PHPBRIDGE_PATH']);
$server->setBaseUrl('/');

if (!empty($layers)) { 
    $server->setLayers($layers);
}

$result = $server->dispatch($_SERVER['REQUEST_URI']);
if (false === $result) {
    return false;
} else if ($result === true) {
    return;
}

header('HTTP/1.1 404 Not Found');
echo "The requested file was not found. [f]";
exit(1);