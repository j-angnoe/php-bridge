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
error_log("[pid " . getmypid() . "] " . $_SERVER["REQUEST_METHOD"] . ' ' . $_SERVER['REQUEST_URI']);

if (!isset($_ENV['PHPBRIDGE_PATH'])) {
    error_log('Please run this with ./bin/phpbridge');
    exit(1);
}

$layers = array_filter(array_map('trim', explode(',', $_ENV['PHPBRIDGE_LAYER'] ?? '')));

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Deny dot files and directories.
if (preg_match('~/\.~', $path)) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/plain');
    echo "Request page was not found.";
    exit(1);
}


$include = function ($file) {

    // Make the bridge function available to our
    // subject scripts.
    function bridge($target) {
        $instance = PhpBridge\Bridge::to($target);
    
        $instance->interrupt();
    
        return $instance;
    }
    
    // Start session
    session_start();

    global $layers;

    // Bootstrap all layers (including current)
    $directories = array_unique([getcwd(), dirname($file), ...$layers]);

    foreach ($directories as $d) {
        if (file_exists("$d/vendor/autoload.php")) {
            require_once "$d/vendor/autoload.php";
        }
        if (file_exists("$d/autoload.php")) {
            require_once "$d/autoload.php";
        }
        set_include_path(get_include_path() . PATH_SEPARATOR . $d);
    }

    // Run the requested file.
    ob_start();
    include($file);
    $content = ob_get_clean();
    
    // Listen to the --class option
    if (!Bridge::last() && isset($_ENV['PHPBRIDGE_CLASS'])) {
        bridge($_ENV['PHPBRIDGE_CLASS'])->interrupt();
    }

    // Decorate with layout
    foreach ($directories as $l) {
        $file = "$l/layout.php";
        if (file_exists($file)) {
            
            ob_start();
            include($file);
            $content = ob_get_clean();
            break;
        }
    } 

    // Inject the api bridge if it hasn't been done before.
    if (Bridge::last() && !Bridge::last()->outputted()) { 
        if (strpos($content, '<head')) {
            $content = Utils::inject('head', Bridge::last()->output('script'), $content);
        } else {
            $content = Bridge::last()->output('script') . "\n" . $content;
        }
    }

    // Output the content.
    echo $content;
};

if (is_file($_ENV['PHPBRIDGE_PATH'])) {

    if (is_file(getcwd() . $path)) {
        // Let php handle this.
        return false;
    } elseif ($path === '/') {         
        $include($_ENV['PHPBRIDGE_PATH']);
        return;
    }
} else if (is_dir($_ENV['PHPBRIDGE_PATH'])) {
    if (!$path || $path === '/') {
        $path = '/index.php';
    }

    if (is_file(getcwd() . '/' . $path)) {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if ($extension !== 'php') {
            // Let php handle this.
            return false;
        } else { 
            $include(getcwd() . '/' . $path);
            return;
        }
    } 
}

// Laatste kans:
foreach ($layers as $l) { 
    if (file_exists($l . '/' . $path)) {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        // copied from web phar...
        $mimes = array(
            'phps' => 2,
            'txt' => 'text/plain',
            'xsd' => 'text/plain',
            'php' => 1,
            'inc' => 1,
            'avi' => 'video/avi',
            'bmp' => 'image/bmp',
            'css' => 'text/css',
            'gif' => 'image/gif',
            'htm' => 'text/html',
            'html' => 'text/html',
            'htmls' => 'text/html',
            'ico' => 'image/x-ico',
            'jpe' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'js' => 'application/x-javascript',
            'midi' => 'audio/midi',
            'mid' => 'audio/midi',
            'mod' => 'audio/mod',
            'mov' => 'movie/quicktime',
            'mp3' => 'audio/mp3',
            'mpg' => 'video/mpeg',
            'mpeg' => 'video/mpeg',
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'swf' => 'application/shockwave-flash',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'wav' => 'audio/wav',
            'xbm' => 'image/xbm',
            'xml' => 'text/xml',
        );
        
        if (isset($mimes[$ext]) && !is_numeric($mimes[$ext])) {
            header('Content-type: ' . $mimes[$ext]);
            readfile($l . '/' . $path);
            return;
        } else {
            header('HTTP/1.1 403 Forbidden');
            exit(1);
        }
    } else {
    }
}
header('HTTP/1.1 404 Not Found');
echo "The requested file was not found.";
exit(1);