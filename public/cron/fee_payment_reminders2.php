<?php
/**
 * Web-accessible Cron Job: Fee Payment Reminders
 * 
 * This endpoint allows the fee payment reminders cron job to be executed via HTTP/HTTPS.
 * Useful for shared hosting environments (like Aruba) where CLI execution is limited.
 * 
 * Sends automated reminders for unpaid membership fees.
 * Respects 20-day cooldown period to prevent spam.
 * 
 * Usage:
 *   https://yourdomain.com/public/cron/fee_payment_reminders.php?token=YOUR_SECRET_TOKEN
 * 
 * Security: Requires valid secret token configured in config.php (cron.secret_token)
 * 
 * Schedule: Monthly (recommended: 1st day of each month)
 */

define('CRON_JOB_NAME', 'fee_payment_reminders');
require_once __DIR__ . '/_cron_base.php';

// Execute the actual cron job
$cronFilePath = __DIR__ . '/../../cron/fee_payment_reminders.php';
$result = executeCronJob($cronFilePath);

// Send response
sendJsonResponse([
    'success' => $result['success'],
    'cron_job' => CRON_JOB_NAME,
    'message' => $result['message'],
    'output' => $result['output'],
    'timestamp' => date('Y-m-d H:i:s')
], $result['success'] ? 200 : 500);
