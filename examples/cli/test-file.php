<?php
bridge(Controller::class);

class Controller {
    function hello($a, $b) {
        return ['sum' => $a+$b];
    }
}

?>

<h1>PHP Bridge prototype</h1>
<pre id="debug"></pre>

<button onclick="execute()">Execute</button>
<button onclick="secondExecute()">Second execute</button>

<script>
    async function execute() {
        var result = await api.controller.hello(1,2);
        if (typeof result == 'string') { 
            debug.innerHTML = result;
        } else {
            debug.innerHTML = JSON.stringify(result, null, 3);
        }
    }
    async function secondExecute() {
        var result = await api.blabla.hello(3,4);
        if (typeof result == 'string') { 
            debug.innerHTML = result;
        } else {
            debug.innerHTML = JSON.stringify(result, null, 3);
        }
    }
</script>
