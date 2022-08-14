<?php

namespace PhpBridge;

class Utils {
    static function inject($tag, $inject, $content, $limit = 1) {
        $content = preg_replace_callback('~</'.$tag . '>~', function() use ($tag, $inject) {
            return $inject . "\n</". $tag . ">";
        }, $content, $limit, $count);

        if ($count == 0) {
            $content .= "\n$inject\n";
        }
        return $content;
    }

    static function addStylesheet($href, $content) { 
        $tag = '<link rel="stylesheet" href="'.$href.'">';
        return Utils::inject('head', $tag, $content);
    }
    static function addScript($src, $content) {
        $tag = '<script src="'.$src.'"></script>';
        return Utils::inject('body', $tag, $content);
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

    static function extract($tag, $content) { 
        $readKey = 0;
        if (preg_match('~>?\s*\*$~', $tag)) { 
            $readKey = 'innerHTML';
            $tag = rtrim($tag, ' >*');
        }
        if (preg_match('~<'.$tag.'[^>]*>(?<innerHTML>.+?)</'.$tag.'>~is', $content, $match)) {
            return $match[$readKey];
        }
        return '';
    }

    static function resolveLayerDirectory(string $layer) {
        if (strpos($layer, '/') !== false) {
            if (is_dir($layer)) { 
                return realpath($layer);
            } else if (is_file($layer)) {
                return dirname(realpath($layer));
            }
        }
        if (is_dir('./_layers/' . $layer)) { 
            return realpath('./_layers/' . $layer);
        } else if ($_ENV['PHPBRIDGE_LAYERS_DIR'] ?? false) {
            if (is_dir($_ENV['PHPBRIDGE_LAYERS_DIR'] . '/' . $layer)) { 
                return realpath($_ENV['PHPBRIDGE_LAYERS_DIR'] . '/' . $layer);
            }
        }   
        throw new \Exception('Layer `'.$layer.'` could not be found.');
    }
}
