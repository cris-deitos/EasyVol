<?php
/**
 * Web-accessible Cron Job: Generate Recurring Deadlines
 * 
 * This endpoint allows the recurring deadlines generation cron job to be executed via HTTP/HTTPS.
 * Useful for shared hosting environments (like Aruba) where CLI execution is limited.
 * 
 * Usage:
 *   https://yourdomain.com/public/cron/generate_recurring_deadlines.php?token=YOUR_SECRET_TOKEN
 * 
 * Security: Requires valid secret token configured in config.php (cron.secret_token)
 */

define('CRON_JOB_NAME', 'generate_recurring_deadlines');
require_once __DIR__ . '/_cron_base.php';

// Execute the actual cron job
$cronFilePath = __DIR__ . '/../../cron/generate_recurring_deadlines.php';
$result = executeCronJob($cronFilePath);

// Send response
sendJsonResponse([
    'success' => $result['success'],
    'cron_job' => CRON_JOB_NAME,
    'message' => $result['message'],
    'output' => $result['output'],
    'timestamp' => date('Y-m-d H:i:s')
], $result['success'] ? 200 : 500);
