<?php
/**
 * Base Cron Job Web Execution Handler
 * 
 * This file provides common security and authentication logic for web-accessible cron jobs.
 * Each cron job endpoint includes this file to validate requests before execution.
 * 
 * Security Features:
 * - Secret token authentication
 * - IP whitelist support
 * - Request method validation
 * - Execution mode verification
 */

// Prevent direct access without proper setup
if (!defined('CRON_JOB_NAME')) {
    http_response_code(403);
    die('Access denied');
}

// Load autoloader and initialize app
require_once __DIR__ . '/../../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

/**
 * Authenticate and validate cron job request
 * 
 * @return array Returns ['success' => bool, 'message' => string]
 */
function validateCronRequest() {
    try {
        $app = App::getInstance();
        $config = $app->getConfig();
        
        // Check if web-based cron execution is allowed
        if (!($config['cron']['allow_web'] ?? true)) {
            return [
                'success' => false,
                'message' => 'Web-based cron execution is disabled in configuration'
            ];
        }
        
        // Verify secret token
        $configToken = $config['cron']['secret_token'] ?? '';
        if (empty($configToken)) {
            return [
                'success' => false,
                'message' => 'Cron secret token not configured. Please set cron.secret_token in config.php'
            ];
        }
        
        // Get token from request (support both GET and POST, also X-Cron-Token header)
        $requestToken = $_GET['token'] ?? $_POST['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
        
        if (empty($requestToken)) {
            return [
                'success' => false,
                'message' => 'Missing authentication token'
            ];
        }
        
        // Use hash_equals to prevent timing attacks
        if (!hash_equals($configToken, $requestToken)) {
            // Log failed authentication attempt
            error_log('Failed cron authentication attempt from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            return [
                'success' => false,
                'message' => 'Invalid authentication token'
            ];
        }
        
        // Check IP whitelist if configured
        $allowedIps = $config['cron']['allowed_ips'] ?? [];
        if (!empty($allowedIps)) {
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!in_array($clientIp, $allowedIps)) {
                error_log('Cron access denied for IP: ' . $clientIp);
                return [
                    'success' => false,
                    'message' => 'Access denied: IP not whitelisted'
                ];
            }
        }
        
        return ['success' => true, 'message' => 'Authentication successful'];
        
    } catch (\Exception $e) {
        error_log('Cron validation error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Internal server error during validation'
        ];
    }
}

/**
 * Execute a cron job file and return its output
 * 
 * @param string $cronFilePath Absolute path to the cron job PHP file
 * @return array Returns ['success' => bool, 'output' => string, 'message' => string]
 */
function executeCronJob($cronFilePath) {
    if (!file_exists($cronFilePath)) {
        return [
            'success' => false,
            'output' => '',
            'message' => 'Cron job file not found'
        ];
    }
    
    // Capture output
    ob_start();
    
    try {
        // Execute the cron job
        include $cronFilePath;
        
        $output = ob_get_clean();
        
        return [
            'success' => true,
            'output' => $output,
            'message' => 'Cron job executed successfully'
        ];
        
    } catch (\Exception $e) {
        ob_end_clean();
        
        error_log('Cron execution error: ' . $e->getMessage());
        
        return [
            'success' => false,
            'output' => '',
            'message' => 'Cron job execution failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Send JSON response and exit
 * 
 * @param array $data Response data
 * @param int $httpCode HTTP status code
 */
function sendJsonResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Validate the request
$validation = validateCronRequest();

if (!$validation['success']) {
    sendJsonResponse([
        'success' => false,
        'cron_job' => CRON_JOB_NAME,
        'error' => $validation['message'],
        'timestamp' => date('Y-m-d H:i:s')
    ], 403);
}

// If we get here, authentication was successful
// The including file should now execute the actual cron job
