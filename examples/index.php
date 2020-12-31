<?php
$files = glob(__DIR__ . '/**/*.php');
echo '<b>Available pages:</b><br>';
foreach ($files as $f) {
    $filename = basename($f);
    if (strpos($filename, '.inc') !== false) { 
        continue;
    }
    if (substr($filename, 0, 1) === '_') {
        continue;
    }

    echo '<a href="' . str_replace(__DIR__, '', $f) . '">' . $f .'</a><br>';
}