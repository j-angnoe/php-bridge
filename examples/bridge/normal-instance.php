<?php
    ini_set('display_errors', 'on');
    error_reporting(E_ALL);
    session_start();
    
    require_once __DIR__ . '/../../vendor/autoload.php';

    class MyController {
        function hello($a, $b) {
            return ['sum' => $a+$b];
        }
    }

    $controller = new MyController;

    $bridgeJs = PhpBridge\Bridge::to($controller)
    ->interrupt()
    ->output('script');
?>
<h1>NormalBridge: Provided instance example</h1>

<?php include('_simple_interface.html') ?>
