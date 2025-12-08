<?php
/**
 * Bulk Sync All Expiry Dates to Scheduler
 * 
 * This script synchronizes all expiry dates from various tables to the scheduler
 * Run this once to populate the scheduler with existing expiry dates
 * Can also be run periodically via cron to ensure consistency
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\SchedulerSyncController;

$app = App::getInstance();
$db = $app->getDb();
$config = $app->getConfig();

$syncController = new SchedulerSyncController($db, $config);

$stats = [
    'qualifications' => ['synced' => 0, 'errors' => 0],
    'licenses' => ['synced' => 0, 'errors' => 0],
    'insurance' => ['synced' => 0, 'errors' => 0],
    'inspection' => ['synced' => 0, 'errors' => 0],
    'vehicle_documents' => ['synced' => 0, 'errors' => 0]
];

echo "=== Bulk Sync All Expiry Dates to Scheduler ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Sync Member Qualifications/Courses
echo "1. Syncing member qualifications/courses...\n";
$sql = "SELECT id, member_id FROM member_courses WHERE expiry_date IS NOT NULL";
$courses = $db->fetchAll($sql);
foreach ($courses as $course) {
    if ($syncController->syncQualificationExpiry($course['id'], $course['member_id'])) {
        $stats['qualifications']['synced']++;
    } else {
        $stats['qualifications']['errors']++;
    }
}
echo "   Synced: {$stats['qualifications']['synced']}, Errors: {$stats['qualifications']['errors']}\n\n";

// 2. Sync Member Licenses
echo "2. Syncing member licenses...\n";
$sql = "SELECT id, member_id FROM member_licenses WHERE expiry_date IS NOT NULL";
$licenses = $db->fetchAll($sql);
foreach ($licenses as $license) {
    if ($syncController->syncLicenseExpiry($license['id'], $license['member_id'])) {
        $stats['licenses']['synced']++;
    } else {
        $stats['licenses']['errors']++;
    }
}
echo "   Synced: {$stats['licenses']['synced']}, Errors: {$stats['licenses']['errors']}\n\n";

// 3. Sync Vehicle Insurance
echo "3. Syncing vehicle insurance...\n";
$sql = "SELECT id FROM vehicles WHERE insurance_expiry IS NOT NULL";
$vehicles = $db->fetchAll($sql);
foreach ($vehicles as $vehicle) {
    if ($syncController->syncInsuranceExpiry($vehicle['id'])) {
        $stats['insurance']['synced']++;
    } else {
        $stats['insurance']['errors']++;
    }
}
echo "   Synced: {$stats['insurance']['synced']}, Errors: {$stats['insurance']['errors']}\n\n";

// 4. Sync Vehicle Inspection
echo "4. Syncing vehicle inspection...\n";
$sql = "SELECT id FROM vehicles WHERE inspection_expiry IS NOT NULL";
$vehicles = $db->fetchAll($sql);
foreach ($vehicles as $vehicle) {
    if ($syncController->syncInspectionExpiry($vehicle['id'])) {
        $stats['inspection']['synced']++;
    } else {
        $stats['inspection']['errors']++;
    }
}
echo "   Synced: {$stats['inspection']['synced']}, Errors: {$stats['inspection']['errors']}\n\n";

// 5. Sync Vehicle Documents
echo "5. Syncing vehicle documents...\n";
$sql = "SELECT id, vehicle_id FROM vehicle_documents WHERE expiry_date IS NOT NULL";
$documents = $db->fetchAll($sql);
foreach ($documents as $document) {
    if ($syncController->syncVehicleDocumentExpiry($document['id'], $document['vehicle_id'])) {
        $stats['vehicle_documents']['synced']++;
    } else {
        $stats['vehicle_documents']['errors']++;
    }
}
echo "   Synced: {$stats['vehicle_documents']['synced']}, Errors: {$stats['vehicle_documents']['errors']}\n\n";

// Summary
$totalSynced = array_sum(array_column($stats, 'synced'));
$totalErrors = array_sum(array_column($stats, 'errors'));

echo "=== Summary ===\n";
echo "Total items synced: $totalSynced\n";
echo "Total errors: $totalErrors\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";

exit(0);
