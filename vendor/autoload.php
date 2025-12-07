<?php
/**
 * Manual Autoloader for EasyVol
 * 
 * This autoloader handles PHPMailer, mPDF, PhpSpreadsheet, QR Code, and reCAPTCHA
 * libraries when uploaded via FTP without Composer.
 * 
 * @package EasyVol
 * @author cris-deitos
 * @created 2025-12-07
 */

// Define vendor directory
define('VENDOR_DIR', __DIR__);

/**
 * SPL Autoloader for namespace-based classes
 */
spl_autoload_register(function ($class) {
    // Normalize namespace separator
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    
    // Array of possible library paths
    $paths = [
        // PHPMailer
        VENDOR_DIR . '/phpmailer/phpmailer/src/' . $class . '.php',
        
        // mPDF
        VENDOR_DIR . '/mpdf/mpdf/src/' . $class . '.php',
        
        // PhpSpreadsheet
        VENDOR_DIR . '/phpoffice/phpspreadsheet/src/' . $class . '.php',
        
        // QR Code (endroid)
        VENDOR_DIR . '/endroid/qr-code/src/' . $class . '.php',
        
        // QR Code (chillerlan)
        VENDOR_DIR . '/chillerlan/php-qrcode/src/' . $class . '.php',
        
        // reCAPTCHA
        VENDOR_DIR . '/google/recaptcha/src/' . $class . '.php',
        
        // Generic PSR-4 style path
        VENDOR_DIR . '/' . $class . '.php',
    ];
    
    // Try to load the class from available paths
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    
    return false;
});

/**
 * PHPMailer Manual Autoloader
 */
if (file_exists(VENDOR_DIR . '/phpmailer/phpmailer/src/PHPMailer.php')) {
    require_once VENDOR_DIR . '/phpmailer/phpmailer/src/Exception.php';
    require_once VENDOR_DIR . '/phpmailer/phpmailer/src/PHPMailer.php';
    require_once VENDOR_DIR . '/phpmailer/phpmailer/src/SMTP.php';
    
    // Optional PHPMailer components
    if (file_exists(VENDOR_DIR . '/phpmailer/phpmailer/src/OAuth.php')) {
        require_once VENDOR_DIR . '/phpmailer/phpmailer/src/OAuth.php';
    }
    if (file_exists(VENDOR_DIR . '/phpmailer/phpmailer/src/POP3.php')) {
        require_once VENDOR_DIR . '/phpmailer/phpmailer/src/POP3.php';
    }
}

/**
 * mPDF Manual Autoloader
 */
if (file_exists(VENDOR_DIR . '/mpdf/mpdf/src/Mpdf.php')) {
    require_once VENDOR_DIR . '/mpdf/mpdf/src/Mpdf.php';
    
    // Load mPDF dependencies if they exist
    $mpdfDirs = [
        '/mpdf/mpdf/src',
        '/mpdf/mpdf/src/Config',
        '/mpdf/mpdf/src/Conversion',
        '/mpdf/mpdf/src/Writer',
        '/mpdf/mpdf/src/Pdf',
    ];
    
    foreach ($mpdfDirs as $dir) {
        $fullPath = VENDOR_DIR . $dir;
        if (is_dir($fullPath)) {
            foreach (glob($fullPath . '/*.php') as $file) {
                require_once $file;
            }
        }
    }
}

/**
 * PhpSpreadsheet Manual Autoloader
 */
if (file_exists(VENDOR_DIR . '/phpoffice/phpspreadsheet/src/Bootstrap.php')) {
    require_once VENDOR_DIR . '/phpoffice/phpspreadsheet/src/Bootstrap.php';
} elseif (is_dir(VENDOR_DIR . '/phpoffice/phpspreadsheet/src')) {
    // Alternative: Load main PhpSpreadsheet classes
    $spreadsheetFiles = [
        '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php',
        '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/IOFactory.php',
        '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer/IWriter.php',
        '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer/Xlsx.php',
        '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer/Csv.php',
        '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Reader/IReader.php',
        '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Reader/Xlsx.php',
        '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Reader/Csv.php',
    ];
    
    foreach ($spreadsheetFiles as $file) {
        $fullPath = VENDOR_DIR . $file;
        if (file_exists($fullPath)) {
            require_once $fullPath;
        }
    }
}

/**
 * QR Code Library Manual Autoloader (endroid/qr-code)
 */
if (file_exists(VENDOR_DIR . '/endroid/qr-code/src/QrCode.php')) {
    require_once VENDOR_DIR . '/endroid/qr-code/src/QrCode.php';
    
    // Load additional QR code classes if they exist
    $qrCodeDirs = [
        '/endroid/qr-code/src',
        '/endroid/qr-code/src/Writer',
    ];
    
    foreach ($qrCodeDirs as $dir) {
        $fullPath = VENDOR_DIR . $dir;
        if (is_dir($fullPath)) {
            foreach (glob($fullPath . '/*.php') as $file) {
                require_once $file;
            }
        }
    }
}

/**
 * QR Code Library Manual Autoloader (chillerlan/php-qrcode)
 */
if (file_exists(VENDOR_DIR . '/chillerlan/php-qrcode/src/QRCode.php')) {
    require_once VENDOR_DIR . '/chillerlan/php-qrcode/src/QRCode.php';
    
    // Load additional classes
    if (is_dir(VENDOR_DIR . '/chillerlan/php-qrcode/src')) {
        foreach (glob(VENDOR_DIR . '/chillerlan/php-qrcode/src/*.php') as $file) {
            require_once $file;
        }
    }
}

/**
 * Google reCAPTCHA Manual Autoloader
 */
if (file_exists(VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/ReCaptcha.php')) {
    require_once VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/ReCaptcha.php';
    require_once VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/RequestMethod.php';
    require_once VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/RequestParameters.php';
    require_once VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/Response.php';
    
    // Load request methods
    if (file_exists(VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/RequestMethod/Post.php')) {
        require_once VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/RequestMethod/Post.php';
    }
    if (file_exists(VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/RequestMethod/Curl.php')) {
        require_once VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/RequestMethod/Curl.php';
    }
    if (file_exists(VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/RequestMethod/CurlPost.php')) {
        require_once VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/RequestMethod/CurlPost.php';
    }
    if (file_exists(VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/RequestMethod/Socket.php')) {
        require_once VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/RequestMethod/Socket.php';
    }
    if (file_exists(VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/RequestMethod/SocketPost.php')) {
        require_once VENDOR_DIR . '/google/recaptcha/src/ReCaptcha/RequestMethod/SocketPost.php';
    }
}

/**
 * Additional common dependencies autoloader
 */
$commonDependencies = [
    // PSR interfaces
    '/psr/log/Psr/Log/LoggerInterface.php',
    '/psr/http-message/src/MessageInterface.php',
    '/psr/http-client/src/ClientInterface.php',
    
    // Symfony components (often used by libraries)
    '/symfony/polyfill-mbstring/bootstrap.php',
    '/symfony/polyfill-php72/bootstrap.php',
    '/symfony/polyfill-php80/bootstrap.php',
];

foreach ($commonDependencies as $file) {
    $fullPath = VENDOR_DIR . $file;
    if (file_exists($fullPath)) {
        require_once $fullPath;
    }
}

/**
 * Class alias helper for common use cases
 */
if (!class_exists('PHPMailer') && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    class_alias('PHPMailer\PHPMailer\PHPMailer', 'PHPMailer');
    class_alias('PHPMailer\PHPMailer\Exception', 'PHPMailerException');
    class_alias('PHPMailer\PHPMailer\SMTP', 'SMTP');
}

// Success indicator
define('VENDOR_AUTOLOAD_LOADED', true);

return true;
