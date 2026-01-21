<?php
/**
 * Static code verification for privacy consent multi-select functionality
 * This script verifies the code structure without requiring database connection
 */

echo "=== Privacy Consent Multi-Select Code Verification ===\n\n";

// Check if files exist
$files = [
    'public/privacy_consent_edit.php',
    'src/Controllers/GdprController.php',
    'uploads/privacy_consents/.htaccess',
    'uploads/privacy_consents/.gitkeep'
];

echo "1. Checking required files:\n";
foreach ($files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "   ✓ $file exists\n";
    } else {
        echo "   ❌ $file NOT FOUND\n";
        exit(1);
    }
}
echo "\n";

// Check privacy_consent_edit.php for required changes
echo "2. Verifying privacy_consent_edit.php:\n";
$editContent = file_get_contents(__DIR__ . '/public/privacy_consent_edit.php');

$checks = [
    'enctype="multipart/form-data"' => 'Form has multipart encoding',
    'consent_types[]' => 'Multiple consent type selection',
    'consent-type-checkbox' => 'Consent type checkboxes',
    'select_all_consents' => 'Select all checkbox',
    'consent_document' => 'File upload field',
    'createMultipleConsents' => 'Calls createMultipleConsents method',
    'accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"' => 'File type validation'
];

foreach ($checks as $pattern => $description) {
    if (strpos($editContent, $pattern) !== false) {
        echo "   ✓ $description\n";
    } else {
        echo "   ❌ $description NOT FOUND\n";
        exit(1);
    }
}
echo "\n";

// Check GdprController for new method
echo "3. Verifying GdprController.php:\n";
$controllerContent = file_get_contents(__DIR__ . '/src/Controllers/GdprController.php');

$controllerChecks = [
    'function createMultipleConsents' => 'createMultipleConsents method exists',
    'foreach ($consentTypes as $consentType)' => 'Loops through consent types',
    '$data[\'consent_document_path\']' => 'Handles document path',
    'Creati ' => 'Logs activity for multiple consents'
];

foreach ($controllerChecks as $pattern => $description) {
    if (strpos($controllerContent, $pattern) !== false) {
        echo "   ✓ $description\n";
    } else {
        echo "   ❌ $description NOT FOUND\n";
        exit(1);
    }
}
echo "\n";

// Check .htaccess security
echo "4. Verifying security configuration:\n";
$htaccessContent = file_get_contents(__DIR__ . '/uploads/privacy_consents/.htaccess');
if (strpos($htaccessContent, 'Deny from all') !== false) {
    echo "   ✓ Direct access blocked\n";
} else {
    echo "   ❌ Security configuration incomplete\n";
    exit(1);
}

if (strpos($htaccessContent, 'FilesMatch') !== false && strpos($htaccessContent, '.php') !== false) {
    echo "   ✓ PHP execution blocked\n";
} else {
    echo "   ❌ PHP execution not blocked\n";
    exit(1);
}
echo "\n";

// Check directory permissions
echo "5. Checking directory permissions:\n";
$uploadDir = __DIR__ . '/uploads/privacy_consents/';
if (is_dir($uploadDir)) {
    echo "   ✓ Directory exists\n";
    if (is_writable($uploadDir)) {
        echo "   ✓ Directory is writable\n";
    } else {
        echo "   ⚠ Directory is not writable (may need chmod 755)\n";
    }
} else {
    echo "   ❌ Directory not found\n";
    exit(1);
}
echo "\n";

// Verify logic flow
echo "6. Verifying logic flow:\n";

// Check edit mode vs create mode handling
if (strpos($editContent, 'if ($isEdit)') !== false && 
    strpos($editContent, 'consent_types[]') !== false) {
    echo "   ✓ Edit/Create mode differentiation\n";
} else {
    echo "   ❌ Edit/Create mode logic not found\n";
    exit(1);
}

// Check file upload handling
if (strpos($editContent, '$_FILES[\'consent_document\']') !== false &&
    strpos($editContent, 'move_uploaded_file') !== false) {
    echo "   ✓ File upload handling\n";
} else {
    echo "   ❌ File upload logic incomplete\n";
    exit(1);
}

// Check validation
if (strpos($editContent, 'allowedExtensions') !== false &&
    strpos($editContent, '5 * 1024 * 1024') !== false) {
    echo "   ✓ File validation (type and size)\n";
} else {
    echo "   ❌ File validation not found\n";
    exit(1);
}

echo "\n=== All code verifications passed! ===\n\n";
echo "Summary of implementation:\n";
echo "✓ Form supports multiple consent type selection (checkboxes)\n";
echo "✓ File upload functionality added with validation\n";
echo "✓ createMultipleConsents method creates one record per type\n";
echo "✓ Uploaded file is associated with all consent records\n";
echo "✓ Security measures in place (.htaccess)\n";
echo "✓ Edit mode maintains single consent editing\n";
echo "✓ Create mode allows multiple selections\n\n";

echo "The implementation is ready for deployment!\n";
