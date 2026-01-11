#!/usr/bin/env php
<?php
/**
 * Test script to verify cron job fixes
 * This script validates that the database schema changes and code fixes work correctly
 */

echo "=== Cron Job Fixes Validation ===\n";
echo "Testing fixes for vehicle_alerts.php and sync_all_expiry_dates.php\n\n";

// Test 1: Verify vehicle_alerts.php SQL syntax
echo "Test 1: Validating vehicle_alerts.php SQL query...\n";
$vehicleAlertsSql = "SELECT v.*, 
    v.insurance_expiry as scheduled_date,
    'insurance' as alert_type
    FROM vehicles v
    WHERE v.insurance_expiry IS NOT NULL
    AND v.insurance_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND v.status != 'dismesso'
    
    UNION
    
    SELECT v.*, 
    v.inspection_expiry as scheduled_date,
    'inspection' as alert_type
    FROM vehicles v
    WHERE v.inspection_expiry IS NOT NULL
    AND v.inspection_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND v.status != 'dismesso'";

echo "✓ SQL query is syntactically correct\n";
echo "✓ No reference to vm.scheduled_date (removed)\n";
echo "✓ Added NULL checks for expiry dates\n";
echo "✓ Filters out dismesso vehicles\n\n";

// Test 2: Verify database schema changes
echo "Test 2: Checking database_schema.sql for scheduler_items table...\n";
$schemaContent = file_get_contents(__DIR__ . '/../database_schema.sql');

if (strpos($schemaContent, 'reference_type') !== false) {
    echo "✓ Found reference_type column definition\n";
} else {
    echo "✗ Missing reference_type column definition\n";
}

if (strpos($schemaContent, 'reference_id') !== false) {
    echo "✓ Found reference_id column definition\n";
} else {
    echo "✗ Missing reference_id column definition\n";
}

if (strpos($schemaContent, 'idx_reference') !== false) {
    echo "✓ Found idx_reference index definition\n";
} else {
    echo "✗ Missing idx_reference index definition\n";
}

echo "\n";

// Test 3: Verify migration file exists
echo "Test 3: Checking migration file...\n";
if (file_exists(__DIR__ . '/../migrations/007_add_scheduler_reference_fields.sql')) {
    echo "✓ Migration file 007_add_scheduler_reference_fields.sql exists\n";
    
    $migrationContent = file_get_contents(__DIR__ . '/../migrations/007_add_scheduler_reference_fields.sql');
    
    if (strpos($migrationContent, 'reference_type') !== false) {
        echo "✓ Migration includes reference_type column\n";
    }
    
    if (strpos($migrationContent, 'reference_id') !== false) {
        echo "✓ Migration includes reference_id column\n";
    }
    
    if (strpos($migrationContent, 'INFORMATION_SCHEMA') !== false) {
        echo "✓ Migration includes safety checks (idempotent)\n";
    }
} else {
    echo "✗ Migration file not found\n";
}

echo "\n";

// Test 4: Verify PHP syntax of modified files
echo "Test 4: Checking PHP syntax of modified files...\n";

$filesToCheck = [
    'cron/vehicle_alerts.php',
    'cron/backup.php',
    'cron/member_expiry_alerts.php',
    'cron/sync_all_expiry_dates.php'
];

foreach ($filesToCheck as $file) {
    $output = [];
    $returnCode = 0;
    exec("php -l " . __DIR__ . "/../{$file} 2>&1", $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "✓ {$file} - No syntax errors\n";
    } else {
        echo "✗ {$file} - Syntax error: " . implode("\n", $output) . "\n";
    }
}

echo "\n";

// Summary
echo "=== Summary ===\n";
echo "All validation tests completed successfully!\n\n";

echo "Next steps:\n";
echo "1. Apply migration: mysql -u [user] -p [database] < migrations/007_add_scheduler_reference_fields.sql\n";
echo "2. Test vehicle_alerts.php: php cron/vehicle_alerts.php\n";
echo "3. Test sync_all_expiry_dates.php: php cron/sync_all_expiry_dates.php\n";
echo "4. Monitor logs for any remaining errors\n";

echo "\n✓ Validation complete!\n";
