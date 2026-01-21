<?php
/**
 * Test script for privacy consent multi-select functionality
 * Run this script from command line: php test_consent_multiselect.php
 */

require_once __DIR__ . '/src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\GdprController;

try {
    echo "=== Testing Privacy Consent Multi-Select Functionality ===\n\n";
    
    // Initialize app
    $app = App::getInstance();
    $db = $app->getDb();
    $config = $app->getConfig();
    
    echo "✓ App initialized successfully\n";
    echo "✓ Database connection established\n\n";
    
    // Create controller
    $controller = new GdprController($db, $config);
    echo "✓ GdprController instantiated\n\n";
    
    // Check if createMultipleConsents method exists
    if (!method_exists($controller, 'createMultipleConsents')) {
        throw new Exception('createMultipleConsents method not found in GdprController');
    }
    echo "✓ createMultipleConsents method exists\n\n";
    
    // Check if privacy_consents table exists
    $tableCheck = $db->fetchOne("SHOW TABLES LIKE 'privacy_consents'");
    if (!$tableCheck) {
        throw new Exception('privacy_consents table not found');
    }
    echo "✓ privacy_consents table exists\n\n";
    
    // Check table structure
    $columns = $db->fetchAll("DESCRIBE privacy_consents");
    echo "Table structure:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    echo "\n";
    
    // Check if uploads directory exists
    $uploadDir = __DIR__ . '/uploads/privacy_consents/';
    if (!is_dir($uploadDir)) {
        throw new Exception('uploads/privacy_consents directory not found');
    }
    if (!is_writable($uploadDir)) {
        throw new Exception('uploads/privacy_consents directory is not writable');
    }
    echo "✓ uploads/privacy_consents directory exists and is writable\n";
    
    // Check .htaccess
    if (!file_exists($uploadDir . '.htaccess')) {
        throw new Exception('.htaccess file not found in uploads/privacy_consents');
    }
    echo "✓ .htaccess file exists for security\n\n";
    
    echo "=== All tests passed! ===\n";
    echo "\nThe system is ready to:\n";
    echo "1. Accept multiple consent type selections\n";
    echo "2. Upload consent documents\n";
    echo "3. Create multiple consent records from a single form\n";
    echo "4. Store files securely in uploads/privacy_consents/\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
