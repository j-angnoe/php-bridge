<?php

namespace PhpBridge;
use Closure;

class AppletExecutionContext {
    protected $file;
    protected $layers = [];
    protected $bridges = [];

    function __construct(string $file, Server $server) { 
        $this->file = $file;
        $this->server = $server;
    }

    function getFile() {
        return $this->file;
    }

    function layer($name): self { 

        $dependentLayers = [];
        $this->server->resolveLayer($name, $dependentLayers);
        
        // print_r($dependentLayers);
        foreach ($dependentLayers as $layer) { 
            // error_log('loading dep layer ' . $layer . ' for ' . $name);
            $this->layers[] = $layer;
            if (file_exists("$layer/vendor/autoload.php")) {
                require_once "$layer/vendor/autoload.php";
            }
            if (file_exists("$layer/autoload.php")) {
                require_once "$layer/autoload.php";
            }
            if (file_exists("$layer/bootstrap.php")) {
                require_once "$layer/bootstrap.php";
            }

            // @fixme - even de order nalopen.
            set_include_path($layer . PATH_SEPARATOR . get_include_path());
            // echo "SET INCLUDE PATH TO " . get_include_path() . "<br>";
        }
        return $this;

    }

    // bridge(name, classOrFunction)
    // bridge(classOrFunction) 
    function bridge($name, $subject = null): self { 
        if (is_array($name)) {
            foreach ($name as $key=>$value) { 
                if (is_numeric($key)) {
                    $this->bridge($value);
                } else {
                    $this->bridge($key, $value);
                }
            }
            return $this;
        }
        if (func_num_args() == 1) { 
            $subject = $name;
            $name = is_object($name) ? get_class($name) : $name;
        }
        $pieces = explode("\\", $name);
        $localAlias = end($pieces);
        $this->bridges[$localAlias] = $subject;

        return $this;
    }

    public function getBridges() {
        return $this->bridges;
    }

    public function getLayers() { 
        return $this->layers;
    }

    // Allow applets to define a layout function.
    public function layout(Closure $layoutFn) {
        $this->server->layout($layoutFn);
    }

    protected $befores = [];
    public function before($fns) {
        $this->befores[] = $fns;
    }

    protected $afters = [];
    public function after($fns) { 
        $this->afters[] = $fns;
    }

    protected $runner; 
    protected $hasRun = false;
    public function overwriteRun(\Closure $runner) {
        if ($this->hasRun) { 
            throw new \Exception('Call to overwriteRun() after the thing has run...');
        }
        $this->runner = $runner;
    }

    function __invoke() { 
        $this->hasRun = true;
        
        // echo "VENDOR AUTOLOAD " . realpath('vendor/autoload.php');
        if (file_exists(getcwd() . '/vendor/autoload.php')) { 
            require_once getcwd() . '/vendor/autoload.php';
        }
        array_map('call_user_func', $this->befores);
        ob_start();

        if ($this->runner) { 
            call_user_func($this->runner, $this->file);
        } else { 
            include($this->file);
        }
        $content = ob_get_clean();

        array_map(fn($fn) => call_user_func_array($fn, [&$content]), $this->afters);
        return $content;
    }

    public function downloads($a,$b): self { 
        $this->server->downloads($a,$b);
        return $this;
    }

    protected $overwrites = [];
    public function overwrite($method, $implementation) {
        $this->overwrites[$method] = $implementation;
    }

    // Require - gebruik je om php shit te laden.
    public function require($file) {         
        if (isset($this->overwrites[__FUNCTION__])) {
            return $this->overwrites[__FUNCTION__](...func_get_args());
        }
        ob_start();
        require_once($file);
        ob_end_clean();
    }

    // Include gebruik je om php + html te laden.
    public function include($file) { 
        if (isset($this->overwrites[__FUNCTION__])) {
            return $this->overwrites[__FUNCTION__](...func_get_args());
        }
        require_once $file;
    }
    /* 
    protected $calls = [];
    protected $implementations = [];
    function __call($method, $args) { 
        if (isset($this->implementations[$method])) { 
            call_user_func_array($this->implementations[$method], $args);
        } else { 
            $this->calls[] = [$method, $args];
        }
    }
    function implement(string $methodName, \Closure $implementation) { 
        $this->implementations[$methodName] = $implementation;
        $this->calls = array_filter($this->calls, function($call) use ($implementation, $methodName) {
            if ($call[0] === $methodName) {
                call_user_func_array($implementation, $call[1]);
                return false;
            }
            return true;
        });
    }
    */
}
