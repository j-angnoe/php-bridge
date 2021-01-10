<?php

namespace PhpBridge;

class Utils {
    static function inject($tag, $inject, $content, $limit = 1) {
        return preg_replace_callback('~</'.$tag . '>~', function() use ($tag, $inject) {
            return $inject . "\n</". $tag . ">";
        }, $content, $limit);
    }

    static function autoCompleteHtmlDocument($content) {
        if (stripos($content, '<body') === false) { 
            $content = "<body>\n" . $content . "\n</body>";
        }

        if (stripos($content, '<head>') === false) {
            $content = "<head>\n</head>\n" . $content;
        }

        if (stripos($content, '<html') === false) {
            $content = "<html>\n" . $content . "\n</html>";
        }

        if (stripos($content, '<!DOCTYPE') === false) {
            $content = "<!DOCTYPE html>\n" . $content;
        }

        if (stripos($content, '<meta charset') === false) {
            $content = static::inject('head', '<meta charset="utf-8">', $content);
        }
        return $content;
    }
}