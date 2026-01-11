<?php
/**
 * Web-accessible Cron Job: Email Queue Processor
 * 
 * This endpoint allows the email queue cron job to be executed via HTTP/HTTPS.
 * Useful for shared hosting environments (like Aruba) where CLI execution is limited.
 * 
 * Usage:
 *   https://yourdomain.com/public/cron/email_queue.php?token=YOUR_SECRET_TOKEN
 * 
 * OR with curl:
 *   curl -X GET "https://yourdomain.com/public/cron/email_queue.php?token=YOUR_SECRET_TOKEN"
 * 
 * Security: Requires valid secret token configured in config.php (cron.secret_token)
 */

define('CRON_JOB_NAME', 'email_queue');
require_once __DIR__ . '/_cron_base.php';

// Execute the actual cron job
$cronFilePath = __DIR__ . '/../../cron/email_queue.php';
$result = executeCronJob($cronFilePath);

// Send response
sendJsonResponse([
    'success' => $result['success'],
    'cron_job' => CRON_JOB_NAME,
    'message' => $result['message'],
    'output' => $result['output'],
    'timestamp' => date('Y-m-d H:i:s')
], $result['success'] ? 200 : 500);
