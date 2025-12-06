<?php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

$app = App::getInstance();

// Log activity
$app->logActivity('logout', 'auth');

// Destroy session
session_start();
session_destroy();

// Redirect to login
header("Location: login.php");
exit;
