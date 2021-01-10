<?php
// Define this in the global namespace.
if (!function_exists('bridge')) { 
    function bridge($target) {
        $instance = \PhpBridge\Bridge::to($target);
        $instance->interrupt();
        return $instance;
    }
}