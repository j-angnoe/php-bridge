<?php

namespace PhpBridge;

use JsonSerializable;
use Iterator;

class JsonSerializableDispatchResult implements JsonSerializable {
    private $result;
    function __construct($result) {
        $this->result = $result;
    }

    function jsonSerialize() {
        $result = $this->result;
        if (is_array($result)) { 
            foreach ($result as $key=>$value) {
                if ($value instanceof \Closure) {
                    $result[$key] = $value = $value();
                } 
                
                if (is_array($value)) {
                    $result[$key] = new JsonSerializableArray($value);
                    $keys = array_keys($value);
                    $isNumeric = true;
                    for($i=0;$i<log(count($keys)) && $isNumeric;$i++){
                        $isNumeric = $isNumeric && is_numeric($keys[$i]);
                    }
                } else if (is_iterable($value)) {
                    $result[$key] = new JsonSerializableIterator($value);
                }
            }
            $result = new JsonSerializableArray($result);
        } else if (is_iterable($result)) {
            $result = new JsonSerializableIterator($result);
        } 
        return $result;
    }
}

class JsonSerializableIterator implements JsonSerializable {
    private $iterator;
    function __construct(Iterator $iterator) {
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
