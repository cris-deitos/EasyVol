#!/usr/bin/env php
<?php
/**
 * Token Generator for Cron Jobs
 * 
 * This script generates a secure random token for authenticating web-based cron job requests.
 * 
 * Usage:
 *   php generate_token.php
 * 
 * The generated token should be added to config/config.php under 'cron' => 'secret_token'
 */

echo "===========================================\n";
echo "EasyVol - Cron Job Token Generator\n";
echo "===========================================\n\n";

// Generate a secure random token (64 characters)
$token = bin2hex(random_bytes(32));

echo "Your secure token:\n\n";
echo "  " . $token . "\n\n";

echo "===========================================\n";
echo "Configuration:\n";
echo "===========================================\n\n";

echo "Add this to your config/config.php file:\n\n";

echo "'cron' => [\n";
echo "    'secret_token' => '" . $token . "',\n";
echo "    'allow_cli' => true,\n";
echo "    'allow_web' => true,\n";
echo "    'allowed_ips' => [], // Empty = allow all IPs\n";
echo "],\n\n";

echo "===========================================\n";
echo "Usage Examples:\n";
echo "===========================================\n\n";

echo "Test with curl:\n";
echo "  curl \"https://yourdomain.com/public/cron/email_queue.php?token=" . $token . "\"\n\n";

echo "Aruba cron command (wget):\n";
echo "  wget -q -O /dev/null \"https://yourdomain.com/public/cron/email_queue.php?token=" . $token . "\"\n\n";

echo "Aruba cron command (curl):\n";
echo "  curl -s \"https://yourdomain.com/public/cron/email_queue.php?token=" . $token . "\" > /dev/null\n\n";

echo "===========================================\n";
echo "Security Tips:\n";
echo "===========================================\n\n";

echo "1. Keep this token secret and secure\n";
echo "2. Use HTTPS to protect the token in transit\n";
echo "3. Change the token periodically\n";
echo "4. Consider using IP whitelist for extra security\n";
echo "5. Monitor logs for unauthorized access attempts\n\n";

echo "For complete documentation, see:\n";
echo "  - public/cron/README.md\n";
echo "  - cron/README.md\n\n";
