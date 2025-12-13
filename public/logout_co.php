<?php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

$app = App::getInstance();

// Log activity
$app->logActivity('logout_co', 'auth', null, 'EasyCO user logged out');

// Destroy session
session_start();
session_destroy();

// Redirect to EasyCO login
header("Location: login_co.php");
exit;
