#!/usr/bin/env php
<?php
/**
 * Migration Runner Script
 * 
 * Usage: php migrations/run_migration.php migrations/add_password_reset_functionality.sql
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Get the migration file from command line argument
if ($argc < 2) {
    echo "Usage: php run_migration.php <migration_file.sql>\n";
    echo "Example: php run_migration.php add_password_reset_functionality.sql\n";
    exit(1);
}

$migrationFile = $argv[1];

// If it's just the filename, look in migrations directory
if (!file_exists($migrationFile)) {
    $migrationFile = __DIR__ . '/' . basename($migrationFile);
}

if (!file_exists($migrationFile)) {
    echo "Error: Migration file not found: $migrationFile\n";
    exit(1);
}

echo "Running migration: " . basename($migrationFile) . "\n";
echo str_repeat("=", 60) . "\n";

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

try {
    $app = App::getInstance();
    $db = $app->getDb();
    
    // Read the migration file
    $sql = file_get_contents($migrationFile);
    
    if (empty($sql)) {
        throw new Exception("Migration file is empty");
    }
    
    echo "Reading migration file...\n";
    
    // Split by semicolons and execute each statement
    // This is a simple approach - for complex migrations you might need a better parser
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            // Skip empty statements and comments
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        try {
            $db->execute($statement);
            $successCount++;
            
            // Show first line of each statement for progress
            $firstLine = explode("\n", trim($statement))[0];
            if (strlen($firstLine) > 60) {
                $firstLine = substr($firstLine, 0, 57) . '...';
            }
            echo "✓ " . $firstLine . "\n";
            
        } catch (Exception $e) {
            $errorCount++;
            echo "✗ Error in statement: " . substr($statement, 0, 100) . "...\n";
            echo "  " . $e->getMessage() . "\n";
            
            // Ask if we should continue
            echo "\nContinue with remaining statements? (y/n): ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            fclose($handle);
            if (trim($line) != 'y') {
                echo "Migration aborted.\n";
                exit(1);
            }
        }
    }
    
    echo str_repeat("=", 60) . "\n";
    echo "Migration completed!\n";
    echo "Successful statements: $successCount\n";
    if ($errorCount > 0) {
        echo "Failed statements: $errorCount\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
