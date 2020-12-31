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
    protected $target;
    protected $baseUrl = [
        'url' => null
    ];

    static function to($target) {
        return new static($target);
    }

    function __construct($target = null) {
        if ($target) {
            $this->setTarget($target);
        }
    }

    function setTarget($target) {
        $this->target = $target;
        return $this;
    }

    /**
     * The Base Url for the bridge is the url on 
     * which the mount point lives. 
     * lives.
    function setBaseUrl($baseUrl, $baseMethod = 'GET', $baseData = null) {
        $this->baseUrl = [
            'url' => $baseUrl,
            'method' => $baseMethod,
            'data' => $baseData
        ];
        return $this;
    }
    */

    function generateJavascriptClient($args = null) {

        $args = $args ?? "{
            postMethod: (url, data) => {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
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

    /**
     * Output the bridge in a given format.
     */
    function output($flags = null) {
        if (is_string($flags)) {
            $flags = func_get_args();
        }

        $output = $this->generateJavascriptClient();
        
        if (in_array('script', $flags)) {
            $output = "<script>\n" . $output . "\n</script>";
        }

        if (in_array('output', $flags)) {
            echo $output;
        } else {
            return $output;
        }
    }

    /**
     * This dispatches and may interrupt the request
     * to output the dispatched output.
     */
    function interrupt($callback = null) {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (strpos($_SERVER['HTTP_CONTENT_TYPE'],'application/json') !== false) {
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

    
    function refreshToken($clientId) {
        $newToken = $this->random_id();
        $_SESSION['rpc-clients'][$clientId]['token'] = $newToken;
        return $newToken;
    }

    function getClient($clientId) {
        return $_SESSION['rpc-clients'][$clientId] ?? false;
    }

    function generateClient() {
        $clientId = static::random_id();
        $_SESSION['rpc-clients'][$clientId] = [
            'id' => $clientId,
            'created_at' => date('Y-m-d H:i:s'),
            'last_active_at' => date('Y-m-d H:i:s'),
            'token' => static::random_id()
        ];
        return $_SESSION['rpc-clients'][$clientId];
    }

    static function random_id() { 
        return sha1(rand(10000, 99999) . rand(10000, 99999) . rand(10000, 99999) . microtime(true));
    }

    function sendJson($data) {
        header('Content-type: application/json');
        $result = json_encode($data);
        if ($result === false) {
            throw new \Exception('JSON Encoding of result failed: ' . json_last_error_msg() . ' ' . print_r($data, true));
        }
        exit($result);
    }
}