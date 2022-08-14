<?php
namespace PhpBridge;

interface BridgeInterface {
    public function setContext(Server $context): void;
    public function dispatch(/* resource */ $handle): bool;
    public function hasOutputted(): bool;
    public function generateClient(): string;
}
