<?php
require_once __DIR__ . '/autoload.php';

// This may be removed.
ini_set('display_errors', 'on');
error_reporting(E_ALL);

use PhpBridge\Server;

if (!isset($_ENV['PHPBRIDGE_PATH'])) {
    error_log('Please run this with ./bin/phpbridge');
    exit(1);
}

// Log each call.
error_log("[" . basename($_ENV['PHPBRIDGE_PATH']) . ", pid " . getmypid() . "] " . $_SERVER["REQUEST_METHOD"] . ' ' . $_SERVER['REQUEST_URI']);
$layers = array_filter(array_map('trim', explode(',', $_ENV['PHPBRIDGE_LAYER'] ?? '')));


$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$server = new Server($_ENV['PHPBRIDGE_PATH']);

// @fixme - server cache
// necessary when you have an applet that defines a layer.
// subsequent calls to static content may not `run` the
// applet and thus those resources cannot be server.
// This was a simple fix but i'm not sure if i want to keep it this way.
$serverCacheFile = isset($_ENV['PHPBRIDGE_SESSION']) ? ($_ENV['PHPBRIDGE_SESSION'] . '/server.json') : false;

$server->setBaseUrl('/');
if ($serverCacheFile && file_exists($serverCacheFile)) {
    $server->cache = json_decode(file_get_contents($serverCacheFile),1);
}


if (!empty($layers)) { 
    foreach ($layers as $layer) { 
        $server->resolveLayer($layer);
    }
}

$result = $server->dispatch($_SERVER['REQUEST_URI']);
if (false === $result) {
    return false;
} else if ($result === true) {
    if ($server->cache && $serverCacheFile) { 
        file_put_contents($serverCacheFile, json_encode($server->cache));
    }
    return;
}

header('HTTP/1.1 404 Not Found');
echo "The requested file was not found. [f]";
exit(1);