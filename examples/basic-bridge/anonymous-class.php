<?php
    ini_set('display_errors', 'on');
    error_reporting(E_ALL);
    session_start();

    require_once __DIR__ . '/../../vendor/autoload.php';

    $bridgeJs = PhpBridge\BasicBridge::to(new class {
        function hello($a, $b) {
            return ['sum' => $a+$b];
        }
    })
    ->interrupt()
    ->output('script');
?>
<h1>Anonymous class example</h1>

<?php include('_simple_interface.html') ?>

