<?php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

$app = App::getInstance();

// Redirect to install if not configured
if (!$app->isInstalled()) {
    header("Location: install.php");
    exit;
}

// Redirect to login if not logged in
if (!$app->isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Redirect to dashboard
header("Location: dashboard.php");
exit;
