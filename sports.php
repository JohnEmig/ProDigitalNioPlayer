<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';

header('Cache-Control: no-store');

try {
  $row = DB::one('SELECT url FROM webview_pages WHERE active=1 ORDER BY id DESC LIMIT 1');
  if (!empty($row['url'])) {
    header('Location: ' . $row['url'], true, 302);
    exit;
  }
  http_response_code(404);
  header('Content-Type: text/plain; charset=UTF-8');
  echo 'No active WebView page configured';
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=UTF-8');
  echo 'Server error';
}
