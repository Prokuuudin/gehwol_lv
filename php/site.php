<?php
// php/site.php — front controller for the public pages that come from the admin data.
// The web server sends here every request that does not match an existing file (see src/.htaccess).

require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/runtime-migrations.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

try {
    migrate_text_content_ids_v2();
    migrate_article_products_v1();
} catch (Throwable $e) {
    error_log(sprintf('[gehwol-migration] %s: %s', get_class($e), $e->getMessage()));
}

try {
    $response = site_response($path);
    [$status, $type, $body] = $response;
    $headers = $response[3] ?? [];
} catch (Throwable $e) {
    error_log(sprintf('[gehwol-site] %s: %s at %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    [$status, $type, $body] = [500, 'text/html; charset=UTF-8',
        '<!DOCTYPE html><html lang="lv"><head><meta charset="UTF-8"><meta name="robots" content="noindex"><title>Kļūda</title></head>'
        . '<body><p>Lapa īslaicīgi nav pieejama. Lūdzu, mēģiniet vēlāk.</p></body></html>'];
    $headers = [];
}

http_response_code($status);
header('Content-Type: ' . $type);
header('X-Content-Type-Options: nosniff');
foreach ($headers as $name => $value) {
    header($name . ': ' . $value);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    echo $body;
}
