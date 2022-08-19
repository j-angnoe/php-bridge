<?php

namespace PhpBridge;

use JsonSerializable;
use Closure;
use Traversable;

class JsonSerializableDispatchResult implements JsonSerializable {
    private $result;
    function __construct($result) {
        $this->result = $result;
    }

    function jsonSerialize() {
        $result = $this->result;
        if (is_array($result)) { 
            $result = array_map(function($value) { 
                if ($value instanceof Closure) {
                    return new JsonSerializableClosureResult($value);
                }

                if (is_array($value)) {
                    return new JsonSerializableArray($value);
                } 
                if ($value instanceof JsonSerializable) {
                    return $value;
                }
                
                if ($value instanceof Traversable) {
                    return new JsonSerializableIterator($value);
                }
                return $value;
            }, $result);

            $result = new JsonSerializableArray($result);
        } else if ($result instanceof JsonSerializable) {
            return $result;
        } else if ($result instanceof Traversable) {
            $result = new JsonSerializableIterator($result);
        } 
        return $result;
    }
}

class JsonSerializableIterator implements JsonSerializable {
    private $iterator;
    function __construct(Traversable $iterator) {
        $this->iterator = $iterator;
    }

    function jsonSerialize() {
        return iterator_to_array($this->iterator);
    }
}

/**
 * Checks if an array is associative or not.
 * If it's not associative then we ensure that 
 * it will be an array in the generated json.
 * 
 * When using things like sort, array_filter and export the
 * result to json you may get a json object instead of an array,
 * this has caused a lot of suprises. 
 * 
 */
class JsonSerializableArray implements JsonSerializable {
    private $array;
    function __construct(array $array) {
        $this->array = $array;
    }

    function jsonSerialize() {
        $n = max(1, sqrt(count($this->array)));
        for($i=0; $i < $n; $i++) {
            $key = key($this->array);
            if (!is_numeric($key)) {
                return $this->array;
            }
            next($this->array);
        }
        return array_values($this->array);
    }
}


class JsonSerializableClosureResult implements JsonSerializable {
    private $closure;
    function __construct(Closure $closure) {
        $this->closure = $closure;
    }

    function jsonSerialize() {
        return new JsonSerializableDispatchResult(call_user_func($this->closure));
    }
}
