<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\PathHelper;

$app = App:: getInstance();
$association = $app->getAssociation();

// Verifica che il logo esista
if (empty($association['logo'])) {
    http_response_code(404);
    die('Logo non trovato');
}

// Costruisci il percorso completo del file
$logoPath = PathHelper:: relativeToAbsolute($association['logo']);

// Verifica che il file esista fisicamente
if (!file_exists($logoPath)) {
    http_response_code(404);
    die('File logo non trovato');
}

// Determina il mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $logoPath);
finfo_close($finfo);

// Serve il file
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($logoPath));
header('Cache-Control:  public, max-age=86400'); // Cache per 1 giorno
readfile($logoPath);
exit;
