<?php

// Create a phpbridge server for a directory
// contains a phpbridge project and mount it on the url supplied to mount.
namespace PhpBridge;

use PhpBridge\Utils;
use Closure;

require_once(__DIR__ . '/applet-includes.php');
class Server {
    protected $path;
    protected $baseUrl;
    protected $layers = [];
    protected $macros = [];

    function macro($method, callable $implementation) {
        $this->macros[$method] = $implementation;
    }
    function __call($method, $args) { 
        if (isset($this->macros[$method])) { 
            $fn = $this->macros[$method];
            if ($fn instanceof Closure) {
                $fn = $fn->bindTo($this);
            }
            return call_user_func_array($fn, $args);
        } else {
            throw new \Exception(sprintf('Call to undefined method `%s::%s`', __CLASS__, $method));
        }
    }

    function __construct($path = null, $baseUrl = null) {
        if ($path) {
            $this->setPath($path);
        }

        if ($baseUrl) {
            $this->setBaseUrl($baseUrl);
        }
    }

    function fileExists($path) {
        return file_exists($this->path . '/' . $path);
    }
    function fileGetContents($path) { 
        return file_get_contents($this->path . '/' . $path);
    }
    function fileOpen($path, $mode = 'r') { 
        return fopen($this->path . '/' . $path, $mode);
    }

    // alias for setPath
    function path($path) {
        return $this->setPath($path);
    }
    function setPath($path) {
        $this->path = $path;
        // could be a directory, could be a file.
        if (!file_exists($path)) {
            throw new \Exception(__METHOD__ . ' path does not exist: `'.$this->path.'`');
        }
        $this->resolveLayer($path);
    }

    function getPath($x = null) {
        return $this->path . ($x ? "/$x" : '');
    }

    function setBaseUrl($baseUrl) {
        $this->baseUrl = $baseUrl;
    }

    protected $downloads = [];
    function downloads($prefix, $directory): self { 
        $this->downloads[$prefix] = realpath($directory);
        return $this;
    }

    // Voor het publiek.
    function layer($layer) { 
        $this->resolveLayer($layer);
    }
    /**
     * Resolve the layer and its dependencies.
     * @fixme - deze wordt nogal een aantal keer aangeroepen waardoor shit dubbel komt.
     */
    function resolveLayer($layer, &$stack = []): string { 
        //echo "RESOLVE LAYER $layer<br>";

        if (is_array($layer)) { 
            foreach ($layer as $l) { 
                $this->resolveLayer($l, $stack);
            }

            // return the last layer.
            return $l;
        }

        try { 
            $layer = Utils::resolveLayerDirectory($layer);
        } catch (\Exception $e) { 
            if (is_dir($this->path . '/_layers/' . $layer)) {
                $layer = realpath($this->path . '/_layers/' . $layer);
            } else {
                throw $e;
            }
        }

        if (file_exists("$layer/package.json")) { 
            $json = json_decode(file_get_contents("$layer/package.json"),1);
            foreach ($json['layers'] ?? [] as $l) { 
                $this->resolveLayer($l, $stack);
            }
        }

        if (!in_array($layer, $stack)) { 
        $stack[] = $layer;
        }
        $this->layers[basename($layer)] = $layer;
        if (file_exists($layer.'/layout.php')) { 
            $file = realpath($layer.'/layout.php');
            $this->layouts[$file] = function ($content) use ($file) { 
                include($file);
            };
        }
        return $layer;
    }

    function getLayers() {
        // Either return the pre-set layers or glob the _layers/ directory.
        return array_values($this->layers);
    }

    public $cache;

    protected function runForContent(Closure $callback, $content = null) {
        ob_start();
        $result = $callback($content);
        $obcontent = ob_get_clean();

        if ($result && strlen($result)>2 && !$obcontent) { 
            return $result;
        } else {
            return $obcontent;
        }
    }
    protected function runFile($file) {
        // Make the bridge function available to our
        // subject scripts.

        // This is necessary for global functions bridge/anon to work.
        global $applet;
                
        // Bootstrap all layers (including current)
        $cwd = is_file($this->path) ? dirname($this->path) : $this->path;

        $directories = array_unique(array_merge([$cwd, dirname($file)], $this->getLayers()));
    
        

        $applet = new AppletExecutionContext($file, $this);
        foreach ($this->getLayers() as $layer) { 
            $applet->layer($layer);
        }

        $content = $applet();
        
        // Voorkom dat we dubbelen hebben.
        $directories = array_values(array_filter(array_unique(array_map('realpath', explode(PATH_SEPARATOR, get_include_path())))));

        $bridge = null;
        foreach ($directories as $l) {
            $file = "$l/bridge.php";
            if (!file_exists($file)) { 
                continue;
            }
            $tmp = include($file);
            if (!is_object($tmp)) { 
                continue;
            }
                
            $bridge = $tmp;
        }

        if (!isset($bridge)) { 
            error_log('No bridge, using none-bridge');
            $bridge = include(__DIR__.'/../../layers/none/bridge.php');
        }

        $bridge->setContext($applet);

        if ($bridge && $_SERVER['REQUEST_METHOD'] === 'POST') { 
            if (true === $bridge->dispatch(fopen('php://input','r'))) {
                exit;
            }
        }

        // Write this data, so we can serve appropriately on subsequent requests.
        $this->cache = [
            'layers' => $this->layers,
            'downloads' => $this->downloads
        ];

        foreach ($this->layouts as $layoutFn) {
            $content = $this->runForContent($layoutFn, $content);
        }

        if (!$bridge->hasOutputted()) { 
            error_log('No bridge output, using none-layout');
            ob_start();
            include(__DIR__ . '/../../layers/none/layout.php');
            $content = ob_get_clean();
        }
    
        if ($this->baseUrl) {
            $content = Utils::inject('head', '<base href="'.rtrim($this->baseUrl,'/').'/">', $content);
        }
    
        // Output the content.
        echo $content;    
    }
    
    protected $layouts = [];
    function layout(\Closure $layout) {
        $this->layouts[] = $layout;
    }

    function dispatch($url = null) {
        $url ??= $_SERVER['REQUEST_URI'];

        if ($this->baseUrl) {
            // Use case:
            // /htools/vue-conv should work, but /htools/vue-conv/ should also work.
            $url = preg_replace($x='~^' . preg_quote($this->baseUrl).'~', '', $url, 1);
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
                } 
            }
        }
        
        //// HET SERVEER STUK 

        foreach ($this->cache['downloads'] ?? [] as $prefix => $downloadDir) { 
            $prefix = '/'.trim($prefix,'/').'/';
            $url = '/' . ltrim($url, '/');

            if (strpos($url, $prefix) === 0) {
                $requestedDownload = $downloadDir .'/' . urldecode(substr($url, strlen($prefix)));
                if (is_file($requestedDownload)) { 
                    return $this->serveFile($requestedDownload);
                } 
                return;
            }
        }
        $layers = array_values(array_filter(array_unique(array_map('realpath', array_merge($this->layers, $this->cache['layers'] ?? [])))));
        
        // Laatste kans:
        foreach ($layers as $l) {  
            if (file_exists($l . '/' . $url)) {
                $this->serveFile($l .'/' . $url);
            } 
        }

        // @fixme - beter een throw doen.
        header('HTTP/1.1 404 Not Found');
        echo "1. The requested path `$url` was not found.";
        exit(1);
    }

    function serveFile($file) {
        // deny to dotfiles and directory
        if (preg_match('~/\.~', $file) || substr($file, 0, 1) === '.') {
            // @todo - dotfiles en shit moet nog getest worden
            header('HTTP/1.1 404 Not Found');
            header('Content-Type: text/plain');
            echo "2. Requested path `$url` was not found.";
            exit(1);
        }

        // @fixme - beter een throw doen.

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
