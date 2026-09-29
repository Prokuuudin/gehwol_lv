<?php
// Admin preview of a product, news item or article — also when it is a draft.
// The page is rendered like the public one; <base> makes its relative links work from /php/admin/.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/render.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
$html = match ($_GET['type'] ?? '') {
    'product' => render_product($id, true),
    'news' => render_text_page('news', $id, true),
    'article' => render_text_page('articles', $id, true),
    default => null,
};
if ($html === null) {
    http_response_code(404);
    exit('Nav atrasts.');
}
$banner = '<div style="position:sticky;top:0;z-index:1000;padding:8px 16px;background:#b00020;color:#fff;font:14px sans-serif">'
    . 'Priekšskatījums — apmeklētāji šo lapu redz tikai tad, ja tā ir publicēta.</div>';
$html = preg_replace('~<head>~', '<head><base href="../../"><meta name="robots" content="noindex, nofollow">', $html, 1);
echo preg_replace('~<body>~', '<body>' . $banner, $html, 1);
