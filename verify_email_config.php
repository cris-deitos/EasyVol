<?php
/**
 * Email Configuration Verification Script
 * 
 * This script verifies that the email configuration system is working correctly.
 * Run this from command line: php verify_email_config.php
 */

// Color codes for terminal output
define('COLOR_GREEN', "\033[0;32m");
define('COLOR_RED', "\033[0;31m");
define('COLOR_YELLOW', "\033[1;33m");
define('COLOR_RESET', "\033[0m");

echo "\n" . COLOR_YELLOW . "Email Configuration Verification" . COLOR_RESET . "\n";
echo str_repeat("=", 50) . "\n\n";

$errors = [];
$warnings = [];
$passed = 0;
$failed = 0;

// Test 1: Check if migration file exists
echo "1. Checking migration file... ";
if (file_exists(__DIR__ . '/migrations/add_email_config_to_database.sql')) {
    echo COLOR_GREEN . "✓ PASSED" . COLOR_RESET . "\n";
    $passed++;
} else {
    echo COLOR_RED . "✗ FAILED" . COLOR_RESET . "\n";
    $errors[] = "Migration file not found";
    $failed++;
}

// Test 2: Check if App.php has loadEmailConfigFromDatabase method
echo "2. Checking App.php modifications... ";
$appContent = file_get_contents(__DIR__ . '/src/App.php');
if (strpos($appContent, 'loadEmailConfigFromDatabase') !== false) {
    echo COLOR_GREEN . "✓ PASSED" . COLOR_RESET . "\n";
    $passed++;
} else {
    echo COLOR_RED . "✗ FAILED" . COLOR_RESET . "\n";
    $errors[] = "loadEmailConfigFromDatabase method not found in App.php";
    $failed++;
}

// Test 3: Check if settings.php has new email fields
echo "3. Checking settings.php for new fields... ";
$settingsContent = file_get_contents(__DIR__ . '/public/settings.php');
$requiredFields = ['charset', 'encoding', 'sendmail_params', 'additional_headers'];
$allFieldsPresent = true;
$missingFields = [];

foreach ($requiredFields as $field) {
    if (strpos($settingsContent, 'name="' . $field . '"') === false) {
        $allFieldsPresent = false;
        $missingFields[] = $field;
    }
}

if ($allFieldsPresent) {
    echo COLOR_GREEN . "✓ PASSED" . COLOR_RESET . "\n";
    $passed++;
} else {
    echo COLOR_RED . "✗ FAILED" . COLOR_RESET . "\n";
    $errors[] = "Missing fields in settings.php: " . implode(', ', $missingFields);
    $failed++;
}

// Test 4: Check if settings.php saves to database instead of config file
echo "4. Checking database save logic... ";
if (strpos($settingsContent, 'INSERT INTO config') !== false && 
    strpos($settingsContent, 'ON DUPLICATE KEY UPDATE') !== false) {
    echo COLOR_GREEN . "✓ PASSED" . COLOR_RESET . "\n";
    $passed++;
} else {
    echo COLOR_RED . "✗ FAILED" . COLOR_RESET . "\n";
    $errors[] = "Database save logic not found in settings.php";
    $failed++;
}

// Test 5: Check if documentation exists
echo "5. Checking documentation... ";
if (file_exists(__DIR__ . '/EMAIL_CONFIG_DATABASE_GUIDE.md')) {
    echo COLOR_GREEN . "✓ PASSED" . COLOR_RESET . "\n";
    $passed++;
} else {
    echo COLOR_YELLOW . "⚠ WARNING" . COLOR_RESET . "\n";
    $warnings[] = "Documentation file not found";
}

// Test 6: PHP Syntax Check
echo "6. Checking PHP syntax... ";
// Note: exec() with escapeshellarg() is safe here as we're using hardcoded __DIR__ paths
// and this is a development/verification script, not production code
$appPath = __DIR__ . '/src/App.php';
$settingsPath = __DIR__ . '/public/settings.php';

// Validate paths exist and are within expected directory
if (file_exists($appPath) && file_exists($settingsPath)) {
    exec('php -l ' . escapeshellarg($appPath) . ' 2>&1', $appSyntax, $appReturn);
    exec('php -l ' . escapeshellarg($settingsPath) . ' 2>&1', $settingsSyntax, $settingsReturn);
    
    if ($appReturn === 0 && $settingsReturn === 0) {
        echo COLOR_GREEN . "✓ PASSED" . COLOR_RESET . "\n";
        $passed++;
    } else {
        echo COLOR_RED . "✗ FAILED" . COLOR_RESET . "\n";
        $errors[] = "PHP syntax errors detected";
        $failed++;
    }
} else {
    echo COLOR_RED . "✗ FAILED" . COLOR_RESET . "\n";
    $errors[] = "Required PHP files not found";
    $failed++;
}

// Test 7: Check SQL syntax in migration
echo "7. Checking SQL migration syntax... ";
$sqlContent = file_get_contents(__DIR__ . '/migrations/add_email_config_to_database.sql');
$expectedKeys = [
    'email_from_address',
    'email_from_name',
    'email_reply_to',
    'email_return_path',
    'email_charset',
    'email_encoding',
    'email_sendmail_params',
    'email_additional_headers'
];

$allKeysPresent = true;
$missingKeys = [];

foreach ($expectedKeys as $key) {
    if (strpos($sqlContent, "'" . $key . "'") === false) {
        $allKeysPresent = false;
        $missingKeys[] = $key;
    }
}

if ($allKeysPresent && strpos($sqlContent, 'INSERT IGNORE INTO') !== false) {
    echo COLOR_GREEN . "✓ PASSED" . COLOR_RESET . "\n";
    $passed++;
} else {
    echo COLOR_RED . "✗ FAILED" . COLOR_RESET . "\n";
    if (!$allKeysPresent) {
        $errors[] = "Missing config keys in migration: " . implode(', ', $missingKeys);
    }
    if (strpos($sqlContent, 'INSERT IGNORE INTO') === false) {
        $errors[] = "Migration should use INSERT IGNORE INTO";
    }
    $failed++;
}

// Test 8: Check if README was updated
echo "8. Checking migrations README... ";
$readmeContent = file_get_contents(__DIR__ . '/migrations/README.md');
if (strpos($readmeContent, 'add_email_config_to_database.sql') !== false) {
    echo COLOR_GREEN . "✓ PASSED" . COLOR_RESET . "\n";
    $passed++;
} else {
    echo COLOR_YELLOW . "⚠ WARNING" . COLOR_RESET . "\n";
    $warnings[] = "Migration not documented in README.md";
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo COLOR_YELLOW . "SUMMARY" . COLOR_RESET . "\n";
echo str_repeat("=", 50) . "\n\n";

echo "Tests passed: " . COLOR_GREEN . $passed . COLOR_RESET . "\n";
echo "Tests failed: " . ($failed > 0 ? COLOR_RED : COLOR_GREEN) . $failed . COLOR_RESET . "\n";
echo "Warnings: " . ($warnings ? COLOR_YELLOW : COLOR_GREEN) . count($warnings) . COLOR_RESET . "\n\n";

if (!empty($errors)) {
    echo COLOR_RED . "ERRORS:" . COLOR_RESET . "\n";
    foreach ($errors as $error) {
        echo "  ✗ " . $error . "\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo COLOR_YELLOW . "WARNINGS:" . COLOR_RESET . "\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ " . $warning . "\n";
    }
    echo "\n";
}

if ($failed === 0) {
    echo COLOR_GREEN . "✓ All critical tests passed!" . COLOR_RESET . "\n\n";
    echo "Next steps:\n";
    echo "1. Run the migration: migrations/add_email_config_to_database.sql\n";
    echo "2. Access Settings > Email in the web interface\n";
    echo "3. Configure email settings\n";
    echo "4. Test email sending functionality\n\n";
    exit(0);
} else {
    echo COLOR_RED . "✗ Some tests failed. Please fix the errors above." . COLOR_RESET . "\n\n";
    exit(1);
}
