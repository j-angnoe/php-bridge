<?php

// Global namespace ;-)

class PhpBridge {
    static function setLayersDir($directory) {
        if (!is_dir($directory)) { 
            throw new \Exception(sprintf('%s: directory `%s` does not exist', __METHOD__, $directory));
        }
        $_ENV['PHPBRIDGE_LAYERS_DIR'] = $directory;
    }
    static protected $setups = [];

    static function registerSetup(string $name, $setupFunction) {
        if (!is_callable($setupFunction)) { 
            throw new \Exception(sprintf('%s: supplied setupFunction should be callable.', __METHOD__));
        }
        static::$setups[$name] = $setupFunction;
    }
    static function registerDefaultSetup($setupFunction) {
        return static::registerSetup('default', $setupFunction);
    }

    /**
     * createSetupFunction
     * @param string|array $setupFunction 
     *      string: get a registered setup function by name
     *      array: resolve all named setup functions and return a single
     *          closure.
     * 
     * @access public 
     */
    // Must remain public, because we must be able to call this inside a Server macro.
    static function createSetupFunction($setupFunction) {
        if (is_array($setupFunction)) { 
            $fns = array_map('static::createSetupFunction', array_filter($setupFunction));
            return function (...$args) use ($fns) { 
                foreach ($fns as $fn) {
                    $fn = $fn->bindTo($this);
                    $fn(...$args);
                }
            };
        }


        // Resolve registered setups
        if (is_string($setupFunction) && isset(static::$setups[$setupFunction])) {
            $setupFunction = static::$setups[$setupFunction];
        }

        if ($setupFunction === null && isset(static::$setups['default'])) {
            $setupFunction = static::$setups['default'];
        }
        return $setupFunction;
    }

    static protected $registeredMounts = [];

    static function mount($baseUrl, $appletPath, $setupFunction = null) {
        foreach (static::$registeredMounts as $registered) {
            if (strpos($baseUrl, $registered) === 0) {
                throw new \Exception(sprintf(
                    "%s: Error mounting %s".
                    "\nThis app will be blocked by an earlier registered applet at  %s".
                    "\nTo fix this, please make sure this applet is registered earlier than %s",
                    __METHOD__,
                    $baseUrl, 
                    $registered,
                    $registered
                ));
            }
        }
        static::$registeredMounts[] = $baseUrl;

        $uri = '/' . ltrim($_SERVER['REQUEST_URI'],'/');
        $baseUrl = '/' . ltrim($baseUrl, '/');

        if (is_callable($appletPath)) {
            $setupFunction = $appletPath;
            $appletPath = null;
        }
        $setupFunction = static::createSetupFunction($setupFunction);
                
        $run = function () use ($appletPath, $baseUrl, $setupFunction) {
            $server = new \PhpBridge\Server($appletPath, $baseUrl);
            $server->macro('setup', function(string $setupName) {
                // use PhpBridge:: instead of static::, that will be bound to PhpBridge\Server.
                $fn = PhpBridge::createSetupFunction($setupName);
                $fn = $fn->bindTo($this);
                $fn();
            });
            if ($setupFunction instanceof Closure) { 
                $setupFunction = $setupFunction->bindTo($server);
                $setupFunction($server);
            } else if (is_callable($setupFunction)) {
                call_user_func($setupFunction, $server);
            } 

            if (true === $server->dispatch()) {
                // ... 
                exit(0);
            } else {
                // een throw of iets?
                // of gewoon laten.
            }
        };

        if (class_exists('Route')) { 
            // echo "Register $baseUrl\n";
            Route::any(rtrim($baseUrl,'/').'/{path?}', $run)
                ->where('path','.*');
        } else if (strpos($uri, $baseUrl) === 0) {
            $run();    
        }
    }

    
}
