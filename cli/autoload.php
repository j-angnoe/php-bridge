<?php 

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else if (file_exists(__DIR__ . '/../../../autoload.php')) {
    // When running as a composer package.
    require_once __DIR__ . '/../../../autoload.php';
} 

if (!function_exists('findClosestFile')) { 
    /**
     * Super handy function to search for the closest
     * file given some path.
     * 
     * findClosestFile('package.json', '/path/to/my/project/app/some/folder')
     * might return /path/to/my/project/package.json
     */
    function findClosestFile($filename, $path = null) 
    {
        // paths from .git, package.json, composer.json

        $tryFiles = !is_array($filename) ? [$filename] : $filename;
        // print_R($tryFiles);

        $currentPath = realpath($path) ?: getcwd() . "/" . $path;

        while($currentPath > '/home' && $currentPath > '/') {
            // echo $currentPath . "\n";
            foreach ($tryFiles as $file) {
                // echo "$currentPath/$file\n";

                if (is_dir($currentPath . "/" . $file) || is_file($currentPath . "/" . $file)) {
                    return $currentPath . '/' . $file;
                }

            }    
            $currentPath = dirname($currentPath);
        }
        return false;
    }
}
