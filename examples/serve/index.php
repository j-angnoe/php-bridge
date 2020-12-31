<?php
    ini_set('display_errors', 'on');
    error_reporting(E_ALL);
    session_start();

    require_once __DIR__ . '/../../vendor/autoload.php';

    class Controller {
        function hello($a, $b) {
            return ['sum' => $a+$b];
        }
    }

    PhpBridge\Bridge::serve(Controller::class);
?>
<h1>Serve unit</h1>
<?php include(__DIR__ . '/_simple_interface.html'); ?>