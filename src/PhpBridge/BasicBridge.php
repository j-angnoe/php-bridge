<?php

namespace BasicBridge;

/**
 * This is a basic unprotected unsafe bridge. 
 * It does not have CRSF protection
 * Only use this bridge if you know what you are doing.
 * It's highly recommended to use the normal bridge.
 */


namespace PhpBridge;

class BasicBridge {
    static $lastInstance = null;

    protected $target;
    protected $baseUrl = [
        'url' => null
    ];

    static function to($target) {
        return new static($target);
    }

    static function serve($target) {
        return static::to($target)->interrupt();
    }

    function __construct($target = null) {
        static::$lastInstance = &$this;

        if ($target) {
            $this->setTarget($target);
        }
    }

    function setTarget($target) {
        $this->target = $target;
        return $this;
    }

    function generateJavascriptClient($args = null) {

        $args = $args ?? "{
            postMethod: (url, data) => {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                }).then(response => {
                    return response.json().then(data => {
                        response.data = data;
                        return response
                    });  
                });
            },
            baseUrl: \"{$this->baseUrl['url']}\"
        }";

        $javascriptClient = <<<JAVASCRIPT
        (function(exports, options) {
            // define default options: 
            options = Object.assign({
                processResponse: response => response.data['rpc-data'],
            }, options || {});

            
            var { postMethod, baseUrl, processResponse } = options;

            if (!baseUrl) { 
                var anchor = document.createElement('a');
                anchor.href = '#';
                baseUrl = anchor.href.substr(0, anchor.href.length - 1);

                if (~baseUrl.indexOf('?')) {
                    baseUrl += '&';
                } else {
                    baseUrl += '?';
                }
            }

            var call = (apiName, functionName, data) => {
                return postMethod(
                    baseUrl + "api/" + apiName + "/" + functionName, 
                    { 
                        rpc: [apiName, functionName, data]
                    }
                ).then(processResponse);
            };

            exports.api = new Proxy({},{
                get(obj, apiName) {
                    return new Proxy(
                        function (...args) { 
                            return call('\main', apiName, args)
                        },
                        {
                            get(obj, functionName) {
                                return function (...args) {
                                    return call(apiName, functionName, args);
                                };
                            }
                        }
                    );
                }
            });
        })(window, $args)
JAVASCRIPT;

        return $javascriptClient;
    }

    protected $outputted = false;

    /**
     * Output the bridge in a given format.
     */
    function output($flags = null) {
        $this->outputted = true;

        if (is_string($flags)) {
            $flags = func_get_args();
        }

        $output = $this->generateJavascriptClient();
        // remove line-comments
        $output = preg_replace('~//[^\n]+~', '', $output);
        $output = preg_replace('~\s+~', ' ', $output);

        if (in_array('script', $flags)) {
            $output = "<script>\n" . $output . "\n</script>";
        }

        if (in_array('output', $flags)) {
            echo $output;
        } else {
            return $output;
        }
    }

    function outputted() {
        return $this->outputted;
    }
    
    /**
     * This dispatches and may interrupt the request
     * to output the dispatched output.
     */
    function interrupt($callback = null) {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $contentType = '';

            if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
                $contentType = $_SERVER['HTTP_CONTENT_TYPE'];
             } elseif (isset($_SERVER['CONTENT_TYPE'])) {
                 $contentType = $_SERVER['CONTENT_TYPE'];
             }

            if (strpos($contentType, 'application/json') !== false) {
                $input = file_get_contents('php://input');
                if (strpos($input, "rpc") !== false) {
                    $input = json_decode($input, 1);
                } else {
                    // Let it be.
                    return $this;
                }
            } else {
                if (!empty($_POST) && isset($_POST['rpc'])) {
                    $input = $_POST;
                } else {
                    return $this;
                }
            }

            if (isset($input) && isset($input['rpc'])) {
                ob_end_clean();

                if ($callback) {
                    return $callback($input);
                } else {
                    $responseData = $this->dispatch($input['rpc']);

                    $this->sendJson([
                        'rpc-data' => $responseData
                    ]);
                    exit;
                }
            }
        }
        return $this;
    }

    function getTarget($identifier = null) {
        if (is_array($this->target) && count($this->target) > 1) {
            foreach ($this->target as $target) { 
                $target_classname = is_object($target) ? get_class($target) : $target;

                if (stripos(strrev($target_classname), strrev($identifier)) === 0 ||
                    stripos(strrev($target_classname), strrev($identifier.'controller')) === 0
                ) {
                    if (is_object($target)) {
                        return $target;
                    } else {
                        return new $target;
                    }
                }
            }
        }
        if (is_string($this->target) && class_exists($this->target)) {
            return new $this->target;
        } else {
            return $this->target;
        }
    }

    function dispatch($command) { 
        list($object, $method, $args) = $command;
        
        $controller = $this->getTarget($object);

        if (method_exists($controller, $method)) {
            $result = call_user_func_array([$controller, $method], $args);
        } else {
            throw new \Exception($command[0] . ' has no method ' . $method .  ', please use one of the following: ' . join("\n", get_class_methods($controller)));
        }

        if (is_iterable($result) && !is_array($result)) {
            $newResult = [];
            foreach ($result as $r) {
                $newResult[]= $r;
            }
            $result = $newResult;
        }
        return $result;
    }

    function sendJson($data) {
        header('Content-type: application/json');
        $result = json_encode($data);
        if ($result === false) {
            throw new \Exception('JSON Encoding of result failed: ' . json_last_error_msg() . ' ' . print_r($data, true));
        }
        // @todo - Maybe exitting isn't always the preferred way
        // to send our result... (especially when embedded in projects)
        exit($result);
    }

    static function last() {
        return static::$lastInstance;
    }
}