<?php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();
use EasyVol\App;
use EasyVol\Controllers\GdprController;
use EasyVol\Utils\AutoLogger;
$app = App::getInstance();
if (!$app->isLoggedIn()) { header('Location: login.php'); exit; }
AutoLogger::logPageAccess();
$db = $app->getDb();
$config = $app->getConfig();
$controller = new GdprController($db, $config);
$pageTitle = 'Registro Trattamenti';
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title><?php echo $pageTitle; ?> - EasyVol</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
<?php include __DIR__ . '/../src/Views/includes/navbar.php'; ?>
<div class="container-fluid">
<div class="row">
<?php include __DIR__ . '/../src/Views/includes/sidebar.php'; ?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
<h1 class="h2 pt-3">Registro Trattamenti</h1>
<div class="card"><div class="card-body">
<p>Elenco in costruzione - Implementazione completa richiesta</p>
</div></div>
</main>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
