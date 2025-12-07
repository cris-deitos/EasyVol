#!/usr/bin/env php
<?php
/**
 * Verification Script for User Management Updates
 * 
 * This script verifies that all required changes have been properly implemented.
 * Run this after applying the migration to ensure everything is working.
 * 
 * Usage: php verify_implementation.php
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

echo "EasyVol User Management Updates - Verification Script\n";
echo str_repeat("=", 70) . "\n\n";

$errors = [];
$warnings = [];
$success = [];

// Check if autoloader exists
if (!file_exists(__DIR__ . '/src/Autoloader.php')) {
    $errors[] = "Autoloader not found";
    echo "✗ Critical: Autoloader not found\n";
    exit(1);
}

require_once __DIR__ . '/src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\UserController;

// 1. Check Database Connection
echo "1. Checking database connection...\n";
try {
    $app = App::getInstance();
    $db = $app->getDb();
    $success[] = "Database connection successful";
    echo "   ✓ Database connection successful\n";
} catch (Exception $e) {
    $errors[] = "Database connection failed: " . $e->getMessage();
    echo "   ✗ Database connection failed\n";
    echo "   Error: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Check for must_change_password column
echo "\n2. Checking database schema (must_change_password column)...\n";
try {
    $result = $db->fetchOne("SHOW COLUMNS FROM users LIKE 'must_change_password'");
    if ($result) {
        $success[] = "must_change_password column exists";
        echo "   ✓ must_change_password column exists in users table\n";
    } else {
        $errors[] = "must_change_password column not found in users table";
        echo "   ✗ must_change_password column not found\n";
        echo "   Please run the migration: migrations/add_password_reset_functionality.sql\n";
    }
} catch (Exception $e) {
    $errors[] = "Error checking schema: " . $e->getMessage();
    echo "   ✗ Error checking schema\n";
}

// 3. Check for password_reset_tokens table
echo "\n3. Checking password_reset_tokens table...\n";
try {
    $result = $db->fetchOne("SHOW TABLES LIKE 'password_reset_tokens'");
    if ($result) {
        $success[] = "password_reset_tokens table exists";
        echo "   ✓ password_reset_tokens table exists\n";
    } else {
        $warnings[] = "password_reset_tokens table not found (optional for future use)";
        echo "   ⚠ password_reset_tokens table not found\n";
    }
} catch (Exception $e) {
    $warnings[] = "Error checking password_reset_tokens table";
    echo "   ⚠ Error checking table\n";
}

// 4. Check email templates
echo "\n4. Checking email templates...\n";
try {
    $welcomeTemplate = $db->fetchOne("SELECT * FROM email_templates WHERE template_name = 'user_welcome'");
    if ($welcomeTemplate) {
        $success[] = "user_welcome email template exists";
        echo "   ✓ user_welcome email template exists\n";
    } else {
        $errors[] = "user_welcome email template not found";
        echo "   ✗ user_welcome email template not found\n";
    }
    
    $resetTemplate = $db->fetchOne("SELECT * FROM email_templates WHERE template_name = 'password_reset'");
    if ($resetTemplate) {
        $success[] = "password_reset email template exists";
        echo "   ✓ password_reset email template exists\n";
    } else {
        $errors[] = "password_reset email template not found";
        echo "   ✗ password_reset email template not found\n";
    }
} catch (Exception $e) {
    $errors[] = "Error checking email templates: " . $e->getMessage();
    echo "   ✗ Error checking email templates\n";
}

// 5. Check required files exist
echo "\n5. Checking required files...\n";
$requiredFiles = [
    'public/user_edit.php' => 'User edit page',
    'public/change_password.php' => 'Change password page',
    'public/reset_password.php' => 'Password reset page',
    'public/role_edit.php' => 'Role edit page',
    'src/Controllers/UserController.php' => 'User controller',
    'src/Utils/EmailSender.php' => 'Email sender utility',
    'migrations/add_password_reset_functionality.sql' => 'Migration file',
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $success[] = "$description exists";
        echo "   ✓ $description exists\n";
    } else {
        $errors[] = "$description not found: $file";
        echo "   ✗ $description not found: $file\n";
    }
}

// 6. Check UserController methods
echo "\n6. Checking UserController methods...\n";
try {
    $config = $app->getConfig();
    $controller = new UserController($db, $config);
    
    $methods = [
        'create' => 'User creation',
        'resetPassword' => 'Password reset',
        'createRole' => 'Role creation',
        'updateRole' => 'Role update',
    ];
    
    foreach ($methods as $method => $description) {
        if (method_exists($controller, $method)) {
            $success[] = "$description method exists";
            echo "   ✓ $description method exists\n";
        } else {
            $errors[] = "$description method not found";
            echo "   ✗ $description method not found: $method\n";
        }
    }
} catch (Exception $e) {
    $errors[] = "Error checking UserController: " . $e->getMessage();
    echo "   ✗ Error checking UserController\n";
}

// 7. Check email configuration
echo "\n7. Checking email configuration...\n";
try {
    $config = $app->getConfig();
    if (isset($config['email']['enabled']) && $config['email']['enabled']) {
        $success[] = "Email is enabled";
        echo "   ✓ Email is enabled\n";
        
        if (!empty($config['email']['from_email'])) {
            $success[] = "From email configured";
            echo "   ✓ From email configured: " . $config['email']['from_email'] . "\n";
        } else {
            $warnings[] = "From email not configured";
            echo "   ⚠ From email not configured\n";
        }
        
        if ($config['email']['method'] === 'smtp') {
            if (!empty($config['email']['smtp_host'])) {
                $success[] = "SMTP host configured";
                echo "   ✓ SMTP host configured: " . $config['email']['smtp_host'] . "\n";
            } else {
                $warnings[] = "SMTP host not configured";
                echo "   ⚠ SMTP host not configured\n";
            }
        }
    } else {
        $warnings[] = "Email is disabled - email features will not work";
        echo "   ⚠ Email is disabled\n";
        echo "   Note: Enable email in config/config.php to use email features\n";
    }
} catch (Exception $e) {
    $warnings[] = "Error checking email config";
    echo "   ⚠ Error checking email configuration\n";
}

// Summary
echo "\n" . str_repeat("=", 70) . "\n";
echo "VERIFICATION SUMMARY\n";
echo str_repeat("=", 70) . "\n\n";

if (count($success) > 0) {
    echo "✓ Successful checks: " . count($success) . "\n";
}

if (count($warnings) > 0) {
    echo "⚠ Warnings: " . count($warnings) . "\n";
    foreach ($warnings as $warning) {
        echo "  - $warning\n";
    }
    echo "\n";
}

if (count($errors) > 0) {
    echo "✗ Errors: " . count($errors) . "\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    echo "\n";
    echo "Please fix the errors above before using the system.\n";
    exit(1);
} else {
    echo "\n✓ All critical checks passed!\n";
    echo "\nNext steps:\n";
    echo "1. If you haven't already, apply the migration:\n";
    echo "   php migrations/run_migration.php add_password_reset_functionality.sql\n";
    echo "2. Configure email settings in config/config.php\n";
    echo "3. Test user creation and password reset features\n";
    echo "4. Review USER_MANAGEMENT_UPDATES.md for detailed documentation\n";
    exit(0);
}
