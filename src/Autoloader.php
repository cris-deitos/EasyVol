<?php
namespace EasyVol;

/**
 * Simple PSR-4 compatible autoloader
 */
class Autoloader {
    private static $registered = false;
    
    public static function register() {
        if (self::$registered) {
            return;
        }
        
        // Load Composer's vendor autoloader for third-party dependencies
        $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($vendorAutoload)) {
            require_once $vendorAutoload;
        }
        
        spl_autoload_register(function ($class) {
            // Project namespace
            $prefix = 'EasyVol\\';
            $baseDir = __DIR__ . '/';
            
            // Does the class use the namespace prefix?
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                // No, move to the next registered autoloader
                return;
            }
            
            // Get the relative class name
            $relativeClass = substr($class, $len);
            
            // Replace namespace separators with directory separators
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            
            // If the file exists, require it
            if (file_exists($file)) {
                require $file;
            }
        });
        
        self::$registered = true;
    }
}
