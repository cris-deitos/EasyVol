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
        // BUT ONLY if the vendor folder is complete (not just autoload.php)
        $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
        $vendorComposer = __DIR__ . '/../vendor/composer/autoload_real.php';
        
        if (file_exists($vendorAutoload) && file_exists($vendorComposer)) {
            // Vendor folder is complete - load it
            require_once $vendorAutoload;
        } else {
            // Vendor folder missing or incomplete - skip it
            // This is OK - TCPDF and other libs are loaded manually if needed
            error_log('INFO: Composer vendor autoloader skipped (folder incomplete).  ' .
                      'This is normal if Composer was not used.  ' .
                      'TCPDF and custom classes will be loaded via custom autoloader.');
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
            
            // Debug log
            error_log("Autoloader: Trying to load class '$class' from file: $file");
            
            // If the file exists, require it
            if (file_exists($file)) {
                require $file;
                error_log("Autoloader: Successfully loaded $file");
            } else {
                error_log("Autoloader: File NOT FOUND: $file");
            }
        });
        
        self::$registered = true;
    }
}
