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

    $bridgeJs = PhpBridge\Bridge::to(MyController::class)
    ->interrupt()
    ->output('script');
?>
<h1>NormalBridge: Class name example</h1>

<?php include('_simple_interface.html') ?>
