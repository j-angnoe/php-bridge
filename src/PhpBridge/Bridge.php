<?php

/**
 * PHP Bridge
 * 
 * Build a bridge between for the frontend
 * to the supplies php object.
 */

namespace PhpBridge;

require_once __DIR__ . '/BasicBridge.php';

class Bridge extends BasicBridge {
    function generateJavascriptClient($args = null) {
        $client = $this->generateClient();

        $args = $args ?? "{
            postMethod: (function() {
                var token = \"{$client['token']}\";
                var clientId = \"{$client['id']}\";
                
                return (url, data) => {
                    data.client = clientId;
                    data.token = token;

                    return fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    }).then(response => {
                        return response.json().then(data => {
                            if (data && data['rpc-next-token']) {
                                token = data['rpc-next-token'];
                            }
                            response.data = data;
                            return response
                        });  
                    });
                } 
            })(),
            baseUrl: \"{$this->baseUrl['url']}\"
        }";

        return parent::generateJavascriptClient($args);
    }

    /**
     * This dispatches and may interrupt the request
     * to output the dispatched output.
     */
    function interrupt($callback = null) {
        return parent::interrupt(function ($input) use ($callback) { 
            $client = $this->getClient($input['client']);
            if (!$client) {
                header('HTTP/1.1 403 Forbidden, invalid client');
                exit;
            }
            // validate token:
            if ($client['token'] !== $input['token']) {
                header('HTTP/1.1 403 Forbidden, invalid token');
                exit;
            }

            if ($callback) {
                return $callback($input);
            } else { 
                $responseData = $this->dispatch($input['rpc']);

                $this->sendJson([
                    'rpc-data' => $responseData,
                    'rpc-next-token' => $this->refreshToken($client['id'])
                ]);
                exit;
            }
        });            
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
}