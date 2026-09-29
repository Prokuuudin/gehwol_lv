<?php
// Local preview with PHP's built-in server (it ignores .htaccess). Run from the project root:
//   php -S 127.0.0.1:8010 php/bin/dev-router.php
// Static files come from docs/, the admin from php/admin/, uploads from uploads/,
// everything else goes to php/site.php — the same split as src/.htaccess in production.

$root = dirname(__DIR__, 2);
putenv('GEHWOL_PUBLIC_DIR=' . $root . '/docs');

$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (preg_match('~^/(php/admin|uploads)/~', $path)) {
    return false; // served from the project root
}
if (str_starts_with($path, '/php/') || str_contains($path, '..')) {
    http_response_code(404);
    return true;
}

$file = $root . '/docs' . $path;
if ($path !== '/' && is_file($file)) {
    $types = [
        'html' => 'text/html; charset=UTF-8', 'css' => 'text/css', 'js' => 'text/javascript', 'svg' => 'image/svg+xml',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'ico' => 'image/x-icon', 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'txt' => 'text/plain', 'pdf' => 'application/pdf',
    ];
    header('Content-Type: ' . ($types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
    readfile($file);
    return true;
}

require $root . '/php/site.php';
