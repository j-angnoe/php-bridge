<?php
namespace PhpBridge;
use PhpBridge\BridgeInterface;

abstract class BaseBridge implements BridgeInterface {
    protected $outputted = false;

    function setContext($context): void { 
        $this->bridges = $context->getBridges();
    }

    function makeCallable($bridgeParameter) {
        if (is_array($bridgeParameter)) { 
            $resolved = $this->bridges[$bridgeParameter[0]];
        } else {
            $resolved = $this->bridges[$bridgeParameter];
        }
        if ($resolved instanceof \Closure) { 
            return $resolved;
        }
        if (is_string($resolved)) {
            $resolved = new $resolved();
        } 
        return [$resolved, $bridgeParameter[1]];
    }
    function dispatch($handle): bool {
        $obj = json_decode(stream_get_contents($handle), 1);

        if ($obj['bridge'] ?? false) {
            Dispatcher::call($this->makeCallable($obj['bridge']), $obj['args']);

            return true;
        }    
        return false;
    }

    function hasOutputted(): bool {
        return $this->outputted;
    }

    abstract function generateClient(): string;
}