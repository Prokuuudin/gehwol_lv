<?php
// php/bin/render-all.php — write every public page to a folder, as visitors would get them (CLI only).
// Used by the SEO/link check:  php php/bin/render-all.php build/check && node scripts/check-seo.js build/check docs
//   php php/bin/render-all.php <out-dir> [public-dir=docs]

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
[, $out, $public] = $argv + [null, null, $root . '/docs'];
if ($out === null) {
    fwrite(STDERR, "Usage: php {$argv[0]} <out-dir> [public-dir]\n");
    exit(1);
}
putenv('GEHWOL_PUBLIC_DIR=' . realpath($public));
require_once __DIR__ . '/../includes/render.php';

if (!is_dir($out) && !mkdir($out, 0775, true)) {
    fwrite(STDERR, "Cannot create {$out}\n");
    exit(1);
}
foreach (glob($out . '/*.{html,xml,txt}', GLOB_BRACE) ?: [] as $old) {
    unlink($old);
}

$pages = ['index.html' => '/'];
foreach (category_pages() as $c) {
    $pages[$c['link_url']] = '/' . $c['link_url'];
}
foreach (['products' => 'produkts', 'news' => 'jaunums', 'articles' => 'raksts'] as $collection => $prefix) {
    foreach (published($collection) as $row) {
        $pages["{$prefix}-{$row['id']}.html"] = "/{$prefix}-{$row['id']}.html";
    }
}
$pages['sitemap.xml'] = '/sitemap.xml';

$failed = 0;
foreach ($pages as $file => $path) {
    [$status, , $body] = site_response($path);
    if ($status !== 200) {
        fwrite(STDERR, "{$path}: HTTP {$status}\n");
        $failed++;
        continue;
    }
    file_put_contents("{$out}/{$file}", $body);
}
foreach (array_merge(static_pages(), ['robots.txt']) as $file) {
    copy(site_public_dir() . '/' . $file, "{$out}/{$file}");
}
echo count($pages) + count(static_pages()) . " files written to {$out}\n";
exit($failed ? 1 : 0);
