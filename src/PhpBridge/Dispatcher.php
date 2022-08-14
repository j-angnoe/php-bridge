<?php
namespace PhpBridge;

class Dispatcher {
    static function call($callable, $args) {
        $result = call_user_func_array($callable, $args);
        
        if (!headers_sent()) { 
            if (!preg_grep('/Content-type:/i', headers_list())) {
                header('Content-type: application/json');
            }
        }

        if ($result > '') { 
            if (preg_grep('/Content-type:\s+application\/json/i', headers_list())) {
                $result = new JsonSerializableDispatchResult($result);

                // This newline is outputted to ensure 
                // our json parser recoverer works.
                // even if php erros where outputted an such.
                echo "\n".json_encode($result);
            } else {
                echo $result;
            }
        }
        return true;
    }
}