<?php

use PhpBridge\Utils;
if (stripos($content, '<head')) { 
    echo Utils::inject('head', $bridge->generateClient(), $content);
} else {
    echo $bridge->generateClient();
    echo $content;
}
