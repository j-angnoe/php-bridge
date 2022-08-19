<?php

use PhpBridge\BaseBridge;

return new class extends BaseBridge {
    function generateClient(): string {
        $this->outputted = true;
        ob_start()?>
        <script>
            var _createBridge = function(name) { 
                var call = (bridge, args) => fetch(document.location, {
                    method: 'POST',
                    body: JSON.stringify({
                        bridge,
                        args
                    })
                }).then(response => {
                    var contentType = response.headers.get('content-type');
                    if (contentType.match(/application\/json/)) { 
                        contentBeforeJson = '';
                        return response.text().then(text => {
                            while(true) {
                                try { 
                                    var data = JSON.parse(text);
                                    return data;
                                } catch(e) {
                                    if (e instanceof SyntaxError) {
                                        var nextNewline = text.indexOf("\n");
                                        if (nextNewline === -1) {
                                            throw e;
                                        } else {
                                            contentBeforeJson += text.substr(0,nextNewline + 1);
                                            text = text.substr(nextNewline + 1);
                                        }
                                    }                                    
                                }
                            }
                        }).finally(result => {
                            if (contentBeforeJson) {
                                console.error("Encountered content BEFORE json");
                                console.info(contentBeforeJson);
                            }
                            return result;
                        })
                    } else {
                        return response.text();
                    }
                });

                return new Proxy(function(...args) {
                    return call(name, args);
                }, {
                    set(object, key, value) { 
                        object[key] = value;
                    },
                    get(object, key) { 
                        if (object[key]) { 
                            return object[key];
                        }
                        return function(...args) { 
                            return call([name, key], args);
                        };
                    }
                });
            }
        </script>
        <?php 
        $clientString = str_replace('            ','',preg_replace("/\s+/m",' ', ob_get_clean()));
        $clientString .= "\n<script>\n";
        ksort($this->bridges);
        foreach ($this->bridges as $localAlias => $class) { 
            $built = [];
            foreach (array_slice(explode('.', $localAlias),0, -1) as $dir) {
                $built[] = $dir;
                $clientString .= 'window.'.implode('.', $built).' = window.'.implode('.', $built).' || {};'.PHP_EOL;
            }
            $clientString .= 'window.'.$localAlias.' = _createBridge("'.$localAlias.'");'.PHP_EOL;
        } 
        $clientString .= "</script>\n";

        return $clientString;
    }     
};