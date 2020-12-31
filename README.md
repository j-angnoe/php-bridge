# PHP Bridge

PHP Bridges allows you to interact with a designated php object in javascript
without having to deal with routing and marshalling arguments. Spend less time writing 
boilerplate and fiddling with frameworks, spend more time developing your idea.

An example php object
```php 
class MyController {
    function performSomeAction($argument1, $argument2) {
        // ...
    }
}
```

Interacting with this object in javascript:
```js
var result = await api.performSomeAction(arg1, arg2);
```

By adding:
```php 
PhpBridge\Bridge::serve(MyController::class)
```

## Usage:

PhpBridge is a library and comes with an executable for quickly
spinning up local prototypes.

### Using the phpbridge executable

PhpBridge will launch a webserver on an available port serving the
file (or directory) you specified. It will autoload PhpBridge and 
provide the `bridge` function so you can write: 

```sh
./bin/phpbridge file.php
```

```html
// file.php
<?php
bridge(MyController::class);

class MyController {
    function fn() { } 
}
?>
<button onclick="execute()">Execute</button>
<script>
    async function execute() { 
        var result = await api.fn();
        alert(result);
    }
</script>
```

### Using the library:

```html
<?php


class MyController {
    function fn() { } 
}

$bridge = PhpBridge\Bridge::to(Mycontroller);
$bridge->interrupt();

// Will write <script>window.api = .... </script>
echo $bridge->output('script');
?>

<button onclick="execute()">Execute</button>
<script>
    async function execute() { 
        var result = await api.fn();
        alert(result);
    }
</script>
```