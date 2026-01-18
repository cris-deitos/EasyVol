<?php
/**
 * Enhanced Print Template Editor (Redirect)
 * 
 * Redirects to print_template_editor.php for backward compatibility
 */

// Get entity parameter if provided
$entity = $_GET['entity'] ?? '';
$id = $_GET['id'] ?? '';

// Build redirect URL
$redirectUrl = 'print_template_editor.php';
$params = [];

if (!empty($entity)) {
    $params[] = 'entity=' . urlencode($entity);
}

if (!empty($id)) {
    $params[] = 'id=' . urlencode($id);
}

if (!empty($params)) {
    $redirectUrl .= '?' . implode('&', $params);
}

// Safe relative redirect (URL is hardcoded to print_template_editor.php)
header('Location: ' . $redirectUrl);
exit;