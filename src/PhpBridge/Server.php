<?php

// Create a phpbridge server for a directory
// contains a phpbridge project and mount it on the url supplied to mount.
namespace PhpBridge;

use PhpBridge\Bridge;
use PhpBridge\Utils;

class Server {
    var $path;
    var $baseUrl;
    var $layers;

    function __construct($path = null, $baseUrl = null) {
        if ($path) {
            $this->setPath($path);
        }

        if ($baseUrl) {
            $this->setBaseUrl($baseUrl);
        }
        
    }

    function setPath($path) {
        $this->path = $path;
    }

    function setBaseUrl($baseUrl) {
        $this->baseUrl = $baseUrl;
    }

    function setLayers($layers) {
        $this->layers = $layers;
    }

    function getLayers() {
        // Either return the pre-set layers or glob the _layers/ directory.
        if (isset($this->layers)) {
            return $this->layers;
        } else {
            $dir = is_file($this->path) ? dirname($this->path) : $this->path;

            return glob("$dir/_layers/**", GLOB_ONLYDIR);
        }
    }

    function runFile($file) {
        // Make the bridge function available to our
        // subject scripts.
        require_once __DIR__ . '/define-bridge-fn.php';
        
        // Start session it hasn't been started already.
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }    
    
        // Bootstrap all layers (including current)
        $cwd = is_file($this->path) ? dirname($this->path) : $this->path;

        $directories = array_unique([$cwd, dirname($file), ...$this->getLayers()]);
    
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

        if ($this->baseUrl) {
            $content = Utils::inject('head', '<base href="'.rtrim($this->baseUrl,'/').'/">', $content);
        }
    
        // Output the content.
        echo $content;
    
    }
    
    function dispatch($url = null) {
        $url = $_SERVER['REQUEST_URI'];

        if ($this->baseUrl) {
            $url = preg_replace($x='~^' . preg_quote($this->baseUrl.'/').'~', '', $url, 1);
        }

        // url can also contain a query-string, we should strip this out.
        $url = parse_url($url, PHP_URL_PATH);
        
        $index = ['index.php','index.html'];

        $pathDir = $this->path;
        if (is_file($this->path)) {
            array_unshift($index, basename($this->path));
            $pathDir = dirname($this->path);
        }
        
        $handleExtensions = ['php', 'html'];

        if (!$url || $url === '/') {
            $tryUrls = $index;
        } else {
            $tryUrls = [$url];
        }
        
        foreach ($tryUrls as $url) {

            if (file_exists($pathDir . '/' . $url)) {
                $extension = pathinfo($url, PATHINFO_EXTENSION);
                if (in_array($extension, $handleExtensions)) {
                    $this->runFile($pathDir . '/' . $url);
                    return true;
                } else {
                    // let php handle this.
                    $this->serveFile($pathDir . '/'. $url);
                    return false;
                }
            }
        }
        
        $layers = $this->getLayers();
        // Laatste kans:
        foreach ($layers as $l) {             
            if (file_exists($l . '/' . $url)) {
                $this->serveFile($l .'/' . $url);
            } 
        }

        header('HTTP/1.1 404 Not Found');
        echo "The requested file was not found.";
        exit(1);
    }

    function serveFile($file) {
        // deny to dotfiles and directory
        if (preg_match('~/\.~', $file) || substr($file, 0, 1) === '.') {
            header('HTTP/1.1 404 Not Found');
            header('Content-Type: text/plain');
            echo "Request page was not found.";
            exit(1);
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        
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
            readfile($file);
            exit;
        } else {
            header('HTTP/1.1 403 Forbidden');
            exit(1);
        }
    }
}