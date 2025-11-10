<?php
declare(strict_types=1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$releasesDir = __DIR__ . '/../releases';
$files = glob($releasesDir . '/app-*.apk');

if (empty($files)) {
    echo json_encode(['status' => false, 'message' => 'no_release']); 
    exit;
}

$latest = basename(end($files));
if (preg_match('/app-(\d+)\.apk/', $latest, $matches)) {
    $versionCode = (int)$matches[1];
    $currentVersion = (int)($_GET['version'] ?? 0);
    
    // Build absolute URL
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $url = $scheme . '://' . $host . '/nioplayer/releases/' . $latest;
    
    echo json_encode([
        'status' => ($versionCode > $currentVersion),
        'versionCode' => $versionCode,
        'versionName' => 'v' . $versionCode,
        'url' => $url,
        'changelog' => 'Nova versão ' . $versionCode . ' disponível',
        'publishedAt' => date('Y-m-d H:i:s')
    ]);
}



