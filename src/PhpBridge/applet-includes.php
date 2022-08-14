<?php
// Define this in the global namespace.
if (!function_exists('bridge')) { 
    function bridge($name, $object = null) {
        global $applet;
        if ($applet) {
            call_user_func_array([$applet,'bridge'], func_get_args());
        }
        return $applet;
    }
}

if (!function_exists('anon')) { 
    /**
     * Gives you a bridge to call the given anonymous function.
     * Can be usefull for any given situation.
     * @usage:
     * <script>
     *      var do_calculation = <?= anon(function($a, $b) { return 'result = ' . ($a+$b); } ?>;
     *      do_calculation(1,3).then(result => alert(result));
     * </script>
     */
    function anon(Closure $function) {
        global $applet;
        // A hashmap that counts the number of anons per file.
        global $PHPBRIDGE_ANONS;

        if (!isset($applet)) {
            throw new \Exception('No applet given, this should be run inside the context of a phpbridge applet');
        }

        $refl = new ReflectionFunction($function);
        $filename = $refl->getFileName();
        $PHPBRIDGE_ANONS[$filename] ??= 0;
        $PHPBRIDGE_ANONS[$filename]++;
        $anonName = '__bridge_anons.'.'a'.substr(sha1($filename.' '.($_SERVER['REQUEST_URI']??'(someuri)').' '.($_SERVER['HTTP_USER_AGENT']??'(someagent)')), 16, 8);
        call_user_func_array([$applet,'bridge'], [$anonName, $function]);

        return $anonName;

    }
}

if (!function_exists('iterator_map')) { 
    // @fixme - Find a better place for iterator_map
    /**
     * like array_map.
     * Filter out rows:
     *  return iterator_skip()
     * Stop iterating completely:
     *  return iterator_stop()
     */
    class IteratorSkip { }
    class IteratorStop { }
    function iterator_skip() {
        return new IteratorSkip;
    }
    function iterator_stop() {
        return new IteratorStop;
    }

    function iterator_map(\Closure $closure, Traversable $tr) { 
        foreach ($tr as $key=>$value) {
            $result = $closure($value, $key);
            if ($result instanceof IteratorSkip) {
                continue;
            }
            if ($result instanceof IteratorStop) {
                break;
            }
            yield $result;
        }
    }
}