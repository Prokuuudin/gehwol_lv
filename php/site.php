<?php
// php/site.php — front controller for the public pages that come from the admin data.
// The web server sends here every request that does not match an existing file (see src/.htaccess).

require_once __DIR__ . '/includes/render.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

try {
    [$status, $type, $body] = site_response($path);
} catch (Throwable $e) {
    error_log(sprintf('[gehwol-site] %s: %s at %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    [$status, $type, $body] = [500, 'text/html; charset=UTF-8',
        '<!DOCTYPE html><html lang="lv"><head><meta charset="UTF-8"><meta name="robots" content="noindex"><title>Kļūda</title></head>'
        . '<body><p>Lapa īslaicīgi nav pieejama. Lūdzu, mēģiniet vēlāk.</p></body></html>'];
}

http_response_code($status);
header('Content-Type: ' . $type);
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    echo $body;
}
