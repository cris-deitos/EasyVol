<?php
/**
 * Manual Autoloader for EasyVol
 * 
 * This autoloader handles PHPMailer 7.x, mPDF, PhpSpreadsheet, QR Code, and reCAPTCHA
 * libraries when uploaded via FTP without Composer.
 * 
 * @package EasyVol
 * @author cris-deitos
 * @updated 2025-12-07 - PHPMailer 7.x support
 */

// Define vendor directory
if (!defined('VENDOR_DIR')) {
    define('VENDOR_DIR', __DIR__);
}

/**
 * PSR-4 Autoloader for namespace-based classes
 * Handles PHPMailer 7.x, Endroid, Google reCAPTCHA
 */
spl_autoload_register(function ($class) {
    // Map of namespace prefixes to base directories
    $prefixes = [
        'PHPMailer\\PHPMailer\\' => VENDOR_DIR . '/phpmailer/phpmailer/src/',
        'Endroid\\QrCode\\' => VENDOR_DIR . '/endroid/qr-code/src/',
        'ReCaptcha\\' => VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/',
        'Mpdf\\' => VENDOR_DIR . '/mpdf/mpdf/src/',
        'PhpOffice\\PhpSpreadsheet\\' => VENDOR_DIR . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/',
    ];
    
    // Check each namespace prefix
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        // Get the relative class name
        $relativeClass = substr($class, $len);
        
        // Replace namespace separators with directory separators
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        // If the file exists, require it
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    
    return false;
});

/**
 * PHPMailer 7.x Manual Loader
 * Ensures all core PHPMailer classes are loaded
 */
$phpmailerSrc = VENDOR_DIR . '/phpmailer/phpmailer/src/';
if (file_exists($phpmailerSrc . 'PHPMailer.php')) {
    // Core classes - load in dependency order
    $phpmailerClasses = [
        'Exception. php',
        'PHPMailer.php',
        'SMTP.php',
    ];
    
    foreach ($phpmailerClasses as $class) {
        $file = $phpmailerSrc . $class;
        if (file_exists($file)) {
            require_once $file;
        }
    }
    
    // Optional PHPMailer 7.x components
    $optionalClasses = [
        'OAuth.php',
        'POP3.php',
        'DSNConfigurator.php',
    ];
    
    foreach ($optionalClasses as $class) {
        $file = $phpmailerSrc . $class;
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

/**
 * Endroid QR Code Loader
 */
$endroidSrc = VENDOR_DIR . '/endroid/qr-code/src/';
if (is_dir($endroidSrc)) {
    // Try to load main QrCode class
    if (file_exists($endroidSrc . 'QrCode. php')) {
        require_once $endroidSrc . 'QrCode.php';
    }
    
    // Load common classes
    $endroidClasses = [
        'Builder/Builder.php',
        'Builder/BuilderInterface.php',
        'Color/Color.php',
        'Color/ColorInterface.php',
        'Encoding/Encoding.php',
        'Encoding/EncodingInterface.php',
        'ErrorCorrectionLevel/ErrorCorrectionLevel.php',
        'ErrorCorrectionLevel/ErrorCorrectionLevelInterface.php',
        'Label/Label.php',
        'Label/LabelInterface.php',
        'Logo/Logo.php',
        'Logo/LogoInterface.php',
        'RoundBlockSizeMode/RoundBlockSizeMode. php',
        'RoundBlockSizeMode/RoundBlockSizeModeInterface.php',
        'Writer/WriterInterface.php',
        'Writer/Result/ResultInterface.php',
    ];
    
    foreach ($endroidClasses as $class) {
        $file = $endroidSrc . $class;
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

/**
 * Google reCAPTCHA Loader
 */
$recaptchaSrc = VENDOR_DIR .  '/google/recaptcha/src/ReCaptcha/';
if (file_exists($recaptchaSrc . 'ReCaptcha. php')) {
    $recaptchaClasses = [
        'ReCaptcha. php',
        'RequestMethod. php',
        'RequestParameters. php',
        'Response.php',
        'RequestMethod/Post.php',
        'RequestMethod/Curl.php',
        'RequestMethod/CurlPost.php',
        'RequestMethod/Socket. php',
        'RequestMethod/SocketPost.php',
    ];
    
    foreach ($recaptchaClasses as $class) {
        $file = $recaptchaSrc . $class;
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

/**
 * mPDF Loader (if needed)
 * Note: mPDF has many dependencies, best installed via Composer
 */
$mpdfSrc = VENDOR_DIR . '/mpdf/mpdf/src/';
if (file_exists($mpdfSrc .  'Mpdf.php')) {
    // Only load if mPDF vendor autoload exists
    $mpdfAutoload = VENDOR_DIR . '/mpdf/mpdf/vendor/autoload.php';
    if (file_exists($mpdfAutoload)) {
        require_once $mpdfAutoload;
    }
    
    // Load main Mpdf class
    require_once $mpdfSrc .  'Mpdf.php';
}

/**
 * PhpSpreadsheet Loader (if needed)
 * Note: PhpSpreadsheet has many dependencies, best installed via Composer
 */
$spreadsheetSrc = VENDOR_DIR .  '/phpoffice/phpspreadsheet/src/';
if (is_dir($spreadsheetSrc)) {
    // Check for Bootstrap file
    if (file_exists($spreadsheetSrc . 'Bootstrap.php')) {
        require_once $spreadsheetSrc . 'Bootstrap.php';
    }
    
    // Load vendor autoload if exists
    $spreadsheetAutoload = VENDOR_DIR . '/phpoffice/phpspreadsheet/vendor/autoload.php';
    if (file_exists($spreadsheetAutoload)) {
        require_once $spreadsheetAutoload;
    }
}

/**
 * PSR Interfaces Loader (if present)
 */
$psrInterfaces = [
    '/psr/log/Psr/Log/LoggerInterface.php',
    '/psr/http-message/src/MessageInterface. php',
    '/psr/http-client/src/ClientInterface.php',
];

foreach ($psrInterfaces as $interface) {
    $file = VENDOR_DIR . $interface;
    if (file_exists($file)) {
        require_once $file;
    }
}

/**
 * Symfony Polyfills Loader (if present)
 */
$polyfills = [
    '/symfony/polyfill-mbstring/bootstrap. php',
    '/symfony/polyfill-php72/bootstrap. php',
    '/symfony/polyfill-php80/bootstrap. php',
    '/symfony/polyfill-ctype/bootstrap.php',
    '/symfony/polyfill-iconv/bootstrap.php',
];

foreach ($polyfills as $polyfill) {
    $file = VENDOR_DIR . $polyfill;
    if (file_exists($file)) {
        require_once $file;
    }
}

/**
 * Class Aliases for backward compatibility
 */
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    if (! class_exists('PHPMailer', false)) {
        class_alias('PHPMailer\PHPMailer\PHPMailer', 'PHPMailer');
    }
    if (!class_exists('PHPMailerException', false)) {
        class_alias('PHPMailer\PHPMailer\Exception', 'PHPMailerException');
    }
    if (!class_exists('SMTP', false)) {
        class_alias('PHPMailer\PHPMailer\SMTP', 'SMTP');
    }
}

// Success indicator
if (!defined('VENDOR_AUTOLOAD_LOADED')) {
    define('VENDOR_AUTOLOAD_LOADED', true);
}

// Return true to indicate successful load
return true;
