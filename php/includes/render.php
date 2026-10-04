<?php
// php/includes/render.php
// Public pages built from php/data/*.json and the HTML templates that gulp writes to php/templates/.
// Templates contain <x-slot name="..."></x-slot> placeholders; everything else in them is the normal
// built page (header, footer, typography, SEO tags for fixed pages).
//
// URLs are unchanged: produkts-N.html, jaunums-N.html, raksts-N.html, <category>.html, index.html.

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/content.php';
require_once __DIR__ . '/site-config.php';

const TEMPLATE_DIR = __DIR__ . '/../templates';
const HOME_ITEMS_LIMIT = 10;

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
}

function load_template(string $name): string
{
    $path = TEMPLATE_DIR . '/' . $name;
    if (!preg_match('/^[\w.-]+\.html$/', $name) || !is_file($path)) {
        throw new RuntimeException("Template not found: {$name}");
    }
    $html = (string)file_get_contents($path);

    // Keep long-lived browser caches from showing stale layouts after a deploy.
    // The content hash changes only when the built stylesheet changes.
    static $cssVersion;
    if ($cssVersion === null) {
        $cssPath = site_public_dir() . '/css/main.css';
        $hash = is_file($cssPath) ? hash_file('sha256', $cssPath) : false;
        $cssVersion = $hash ? substr($hash, 0, 12) : '1';
    }

    return str_replace('./css/main.css"', './css/main.css?v=' . $cssVersion . '"', $html);
}

function fill_slot(string $html, string $slot, string $content): string
{
    $tag = '<x-slot name="' . $slot . '"></x-slot>';
    if (!str_contains($html, $tag)) {
        throw new RuntimeException("Template slot not found: {$slot}");
    }
    return str_replace($tag, $content, $html);
}

// --- images -------------------------------------------------------------------

/** Folder that holds a site image: admin uploads sit next to php/ (the document root in production), built images in the public folder. */
function image_base_dir(string $src): string
{
    return str_starts_with($src, 'uploads/') ? dirname(__DIR__, 2) : site_public_dir();
}

/** [width, height] of a site image (path relative to the site root), null if unknown. */
function image_size(string $src): ?array
{
    static $cache = [];
    if (!array_key_exists($src, $cache)) {
        $info = @getimagesize(image_base_dir($src) . '/' . $src);
        $cache[$src] = $info ? [$info[0], $info[1]] : null;
    }
    return $cache[$src];
}

/** <img>, wrapped in <picture> with WebP and @2x sources when those files exist (same markup as the gulp build). */
function picture_html(string $src, string $alt, string $loading): string
{
    $src = ltrim(preg_replace('~^\./~', '', $src), '/');
    $size = image_size($src);
    $img = '<img src="./' . e($src) . '" alt="' . e($alt) . '"'
        . ($size ? ' width="' . $size[0] . '" height="' . $size[1] . '"' : '')
        . ' loading="' . $loading . '" decoding="async">';

    $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    $base = substr($src, 0, -strlen($ext) - 1);
    $dir = image_base_dir($src);
    if ($ext === 'webp' || !is_file("{$dir}/{$base}.webp")) {
        return $img;
    }
    $srcset = fn(string $e) => "./{$base}.{$e} 1x" . (is_file("{$dir}/{$base}@2x.{$e}") ? ", ./{$base}@2x.{$e} 2x" : '');
    $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];
    return '<picture><source srcset="' . e($srcset('webp')) . '" type="image/webp">'
        . (isset($types[$ext]) ? '<source srcset="' . e($srcset($ext)) . '" type="' . $types[$ext] . '">' : '')
        . ' ' . $img . '</picture>';
}

/** Adds picture/size/lazy markup to <img> tags inside stored (sanitized) rich text. */
function content_images_html(string $html): string
{
    return preg_replace_callback('~<img\b[^>]*>~i', function (array $m): string {
        preg_match('~\bsrc="([^"]*)"~', $m[0], $src);
        preg_match('~\balt="([^"]*)"~', $m[0], $alt);
        $decode = fn($v) => html_entity_decode($v ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return picture_html($decode($src[1] ?? ''), $decode($alt[1] ?? ''), 'lazy');
    }, $html);
}

// --- data -------------------------------------------------------------------------

function published(string $collection): array
{
    return array_values(array_filter(load_collection($collection), 'is_published'));
}

/** A published record; with $drafts (admin preview) also unpublished ones. */
function find_published(string $collection, int $id, bool $drafts = false): ?array
{
    foreach ($drafts ? load_collection($collection) : published($collection) as $row) {
        if ((int)$row['id'] === $id) {
            return $row;
        }
    }
    return null;
}

function sorted_news(array $news): array
{
    usort($news, fn($a, $b) => [$b['date'] ?? '', (int)$b['id']] <=> [$a['date'] ?? '', (int)$a['id']]);
    return $news;
}

function category_pages(): array
{
    return array_values(array_filter(load_collection('categories'), fn($c) => !empty($c['link_url'])));
}

// --- head (title, description, canonical, Open Graph, JSON-LD — same output as gulp/seo.js) ----

/**
 * $page: path ('' for home), title, heading, description, crumbs (list of [name, path]),
 * og_type, image (site-relative src or null), noindex (bool)
 */
function head_html(array $page): string
{
    $base = SITE_URL;
    $url = $base . '/' . $page['path'];
    $org = SITE_ORGANIZATION;
    $organization = [
        '@type' => 'Organization', '@id' => "{$base}/#organization", 'name' => $org['name'], 'url' => "{$base}/",
        'telephone' => $org['telephone'], 'email' => $org['email'],
        'address' => [
            '@type' => 'PostalAddress', 'streetAddress' => $org['streetAddress'], 'addressLocality' => $org['addressLocality'],
            'postalCode' => $org['postalCode'], 'addressCountry' => $org['addressCountry'],
        ],
    ];
    $graph = [
        $organization,
        ['@type' => 'WebSite', '@id' => "{$base}/#website", 'url' => "{$base}/", 'name' => SITE_NAME, 'inLanguage' => 'lv', 'publisher' => ['@id' => $organization['@id']]],
    ];
    $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Sākums', 'item' => "{$base}/"]];
    foreach ($page['crumbs'] as [$name, $path]) {
        $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $name, 'item' => "{$base}/{$path}"];
    }
    $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $page['heading'], 'item' => $url];
    $graph[] = ['@type' => 'BreadcrumbList', '@id' => "{$url}#breadcrumbs", 'itemListElement' => $items];
    $graph[] = [
        '@type' => 'WebPage', 'name' => $page['heading'], 'description' => $page['description'],
        'url' => $url, 'inLanguage' => 'lv', 'isPartOf' => ['@id' => "{$base}/#website"],
    ];
    if (!empty($page['article'])) {
        $graph[] = array_filter([
            '@type' => 'Article',
            '@id' => "{$url}#article",
            'headline' => mb_substr($page['heading'], 0, 110),
            'description' => $page['description'],
            'datePublished' => $page['article']['published'],
            'dateModified' => max($page['article']['modified'], $page['article']['published']),
            'image' => !empty($page['image']) ? $base . '/' . ltrim(preg_replace('~^\./~', '', $page['image']), '/') : null,
            'inLanguage' => 'lv',
            'mainEntityOfPage' => $url,
            'author' => ['@id' => $organization['@id']],
            'publisher' => ['@id' => $organization['@id']],
        ], fn($v) => $v !== null && $v !== '');
    }
    $jsonLd = json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

    $lines = [
        '<meta name="description" content="' . e($page['description']) . '">',
        '<title>' . e($page['title']) . '</title>',
    ];
    if (!empty($page['noindex'])) {
        $lines[] = '<meta name="robots" content="noindex">';
    } else {
        $lines[] = '<link rel="canonical" href="' . e($url) . '">';
    }
    $lines[] = '<meta property="og:type" content="' . e($page['og_type']) . '">';
    $lines[] = '<meta property="og:locale" content="lv_LV">';
    $lines[] = '<meta property="og:site_name" content="' . e(SITE_NAME) . '">';
    $lines[] = '<meta property="og:title" content="' . e($page['title']) . '">';
    $lines[] = '<meta property="og:description" content="' . e($page['description']) . '">';
    $lines[] = '<meta property="og:url" content="' . e($url) . '">';
    if (!empty($page['image'])) {
        $lines[] = '<meta property="og:image" content="' . e($base . '/' . ltrim(preg_replace('~^\./~', '', $page['image']), '/')) . '">';
        $lines[] = '<meta property="og:image:alt" content="' . e($page['heading']) . '">';
    }
    $lines[] = '<meta name="twitter:card" content="summary">';
    $lines[] = '<script type="application/ld+json">' . $jsonLd . '</script>';
    return implode("\n", $lines);
}

function page_title(string $name): string
{
    return $name . "\u{00A0}— Gehwol";
}

function shell_page(array $page, string $main): string
{
    return fill_slot(fill_slot(load_template('_shell.html'), 'head', head_html($page)), 'main', $main);
}

function breadcrumbs_html(array $links, string $current): string
{
    $html = '<nav class="category__breadcrumbs" aria-label="Breadcrumbs"> <a href="index.html" class="category__crumb">Sākums</a>';
    foreach ($links as [$name, $href]) {
        $html .= ' <span class="category__crumb-sep">/</span> <a href="' . e($href) . '" class="category__crumb">' . e($name) . '</a>';
    }
    return $html . ' <span class="category__crumb-sep">/</span> <span class="category__crumb category__crumb--current">' . e($current) . '</span></nav>';
}

// --- pages ------------------------------------------------------------------------

function product_media_html(array $product): string
{
    $images = $product['images'] ?? [];
    if (!$images) {
        return '<span class="product-detail__placeholder">GEHWOL</span>';
    }
    if (count($images) === 1) {
        return picture_html($images[0], $product['name'], 'eager');
    }
    $slides = '';
    foreach (array_values($images) as $i => $src) {
        $alt = ($product['image_alts'][$i] ?? '') ?: $product['name'] . ', attēls ' . ($i + 1);
        $slides .= '<div class="swiper-slide">' . picture_html($src, $alt, $i === 0 ? 'eager' : 'lazy') . '</div>';
    }
    return '<div class="swiper product-detail__swiper"><div class="swiper-wrapper">' . $slides . '</div>'
        . '<button class="product-detail__swiper-button product-detail__swiper-button--prev" type="button" aria-label="Iepriekšējais attēls"></button>'
        . '<button class="product-detail__swiper-button product-detail__swiper-button--next" type="button" aria-label="Nākamais attēls"></button>'
        . '<div class="product-detail__swiper-pagination"></div></div>';
}

function product_description_default(array $product): string
{
    return mb_substr($product['name'] . ' — ' . ($product['subtitle'] !== '' ? $product['subtitle'] . '.' : ''), 0, 160);
}

function render_product(int $id, bool $drafts = false): ?string
{
    $product = find_published('products', $id, $drafts);
    if ($product === null) {
        return null;
    }
    $category = null;
    foreach (category_pages() as $c) {
        if ((int)$c['id'] === (int)$product['category_id']) {
            $category = $c;
        }
    }
    $crumbs = $category ? [[$category['name'], $category['link_url']]] : [];
    $active = $product['active_ingredients'] !== ''
        ? '<p class="product-detail__active"><strong>Aktīvās vielas:</strong> ' . e($product['active_ingredients']) . '</p>'
        : '';
    $main = '<section class="category"><div class="container category__container">'
        . breadcrumbs_html(array_merge([['Produkti', 'index.html#products']], $crumbs), $product['name'])
        . '<h1 class="category__title">' . e($product['name']) . '</h1>'
        . '<div class="product-detail"><div class="product-detail__top">'
        . '<div class="product-detail__media">' . product_media_html($product) . '</div>'
        . '<div class="product-detail__content"><p class="product-detail__subtitle">' . e($product['subtitle']) . '</p>'
        . '<div class="product-detail__description">' . content_images_html(sanitize_html($product['description'])) . '</div></div></div>'
        . $active
        . '<p class="product-detail__notice">Vēlaties sadarboties? Sazinieties ar' . "\u{00A0}" . 'mums, izmantojot kontaktinformāciju lapas apakšpusē.</p>'
        . '</div></div></section>';

    return shell_page([
        'path' => "produkts-{$id}.html",
        'title' => page_title($product['name']),
        'heading' => $product['name'],
        'description' => ($product['seo_description'] ?? '') ?: product_description_default($product),
        'crumbs' => $crumbs,
        'og_type' => 'website',
        'image' => $product['images'][0] ?? null,
    ], $main);
}

function render_text_page(string $collection, int $id, bool $drafts = false): ?string
{
    $item = find_published($collection, $id, $drafts);
    if ($item === null) {
        return null;
    }
    $isNews = $collection === 'news';
    $media = $item['image']
        ? '<div class="content-detail__media">' . picture_html($item['image'], $item['title'], 'eager') . '</div>'
        : '<div class="content-detail__media" aria-hidden="true"></div>';
    $main = '<section class="category"><div class="container category__container">'
        . breadcrumbs_html([['Jaunumi un' . "\u{00A0}" . 'informācija', 'index.html#news']], $item['title'])
        . '<h1 class="category__title">' . e($item['title']) . '</h1>'
        . '<div class="content-detail">'
        . ($isNews ? '<p class="content-detail__meta">' . e(format_date_lv($item['date'] ?? null)) . '</p>' : '')
        . $media
        . '<div class="content-detail__text">' . content_images_html(sanitize_html($item['text'])) . '</div>'
        . '</div></div></section>';

    return shell_page([
        'path' => ($isNews ? 'jaunums-' : 'raksts-') . $id . '.html',
        'title' => page_title($item['title']),
        'heading' => $item['title'],
        'description' => ($item['seo_description'] ?? '') ?: mb_substr($item['title'] . ' — Gehwol ' . ($isNews ? 'jaunumi.' : 'raksti.'), 0, 160),
        'crumbs' => [],
        'og_type' => 'article',
        'image' => $item['image'] ?: (preg_match('~<img\b[^>]*\bsrc="([^"]+)"~', $item['text'], $m) ? html_entity_decode($m[1]) : null),
        'article' => [
            'published' => ($isNews ? ($item['date'] ?? null) : null) ?: substr((string)($item['created_at'] ?? ''), 0, 10),
            'modified' => substr((string)($item['updated_at'] ?? $item['created_at'] ?? ''), 0, 10),
        ],
    ], $main);
}

function product_card_html(array $product): string
{
    $media = !empty($product['images'])
        ? picture_html($product['images'][0], $product['name'], 'lazy')
        : '<span class="product-card__placeholder">GEHWOL</span>';
    return '<a href="produkts-' . (int)$product['id'] . '.html" class="product-card"><div class="product-card__media">' . $media . '</div>'
        . '<h2 class="product-card__title">' . e($product['name']) . '</h2>'
        . '<span class="product-card__cta btn-link">Uzzināt vairāk →</span></a>';
}

function render_category(array $category): string
{
    $products = sort_rows(array_filter(published('products'), fn($p) => (int)$p['category_id'] === (int)$category['id']));
    $cards = $products
        ? implode('', array_map('product_card_html', $products))
        : '<p>Šajā kategorijā vēl nav produktu.</p>';
    $html = fill_slot(load_template($category['link_url']), 'products', $cards);
    // the built template had no product images, so gulp/seo.js could not add og:image
    $image = $products[0]['images'][0] ?? null;
    if ($image !== null && !str_contains($html, 'property="og:image"') && preg_match('~<h1\b[^>]*>(.*?)</h1>~s', $html, $h1)) {
        $meta = '<meta property="og:image" content="' . e(SITE_URL . '/' . $image) . '">' . "
"
            . '<meta property="og:image:alt" content="' . e(html_entity_decode(strip_tags($h1[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '">' . "
";
        $html = str_replace('<meta name="twitter:card"', $meta . '<meta name="twitter:card"', $html);
    }
    return $html;
}

function news_slide_html(string $href, array $item): string
{
    $image = !empty($item['image'])
        ?'<div class="news__image">' . picture_html($item['image'], $item['title'], 'lazy') . '</div>'
        : '<div class="news__image" aria-hidden="true"></div>';
    return '<div class="news__slide swiper-slide"><a href="' . e($href) . '" class="news__slide-link">' . $image
        . '<h3 class="news__slide-title">' . e($item['title']) . '</h3></a></div>';
}

function render_home(): string
{
    $news = array_slice(sorted_news(published('news')), 0, HOME_ITEMS_LIMIT);
    $articles = array_slice(sort_rows(published('articles')), 0, HOME_ITEMS_LIMIT);
    $html = load_template('index.html');
    $html = fill_slot($html, 'news', implode('', array_map(fn($n) => news_slide_html("jaunums-{$n['id']}.html", $n), $news)));
    return fill_slot($html, 'articles', implode('', array_map(fn($a) => news_slide_html("raksts-{$a['id']}.html", $a), $articles)));
}

function render_not_found(): string
{
    $main = '<section class="category"><div class="container category__container">'
        . '<h1 class="category__title">Lapa nav atrasta</h1>'
        . '<p>Pieprasītā lapa neeksistē vai vairs nav pieejama.</p>'
        . '<p><a href="index.html" class="btn-link">Uz sākumlapu →</a></p>'
        . '</div></section>';
    return shell_page([
        'path' => '404',
        'title' => page_title('Lapa nav atrasta'),
        'heading' => 'Lapa nav atrasta',
        'description' => 'Pieprasītā lapa neeksistē.',
        'crumbs' => [],
        'og_type' => 'website',
        'image' => null,
        'noindex' => true,
    ], $main);
}

/** Static pages published as plain .html files (legal pages etc.). */
function static_pages(): array
{
    $files = array_map('basename', glob(site_public_dir() . '/*.html') ?: []);
    return array_values(array_filter($files, fn($f) => $f[0] !== '_' && $f !== 'index.html'));
}

function render_sitemap(): string
{
    $urls = [['', null]];
    foreach (static_pages() as $file) {
        $urls[] = [$file, null];
    }
    foreach (category_pages() as $c) {
        $urls[] = [$c['link_url'], null];
    }
    foreach (['products' => 'produkts', 'news' => 'jaunums', 'articles' => 'raksts'] as $collection => $prefix) {
        foreach (published($collection) as $row) {
            $urls[] = ["{$prefix}-{$row['id']}.html", substr((string)($row['updated_at'] ?? ''), 0, 10) ?: null];
        }
    }
    $xml = '';
    foreach ($urls as [$path, $lastmod]) {
        $xml .= '<url><loc>' . e(SITE_URL . '/' . $path) . '</loc>' . ($lastmod ? "<lastmod>{$lastmod}</lastmod>" : '') . "</url>\n";
    }
    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$xml}</urlset>\n";
}

// --- routing ------------------------------------------------------------------------

/** @return array{0:int,1:string,2:string,3?:array<string,string>} status, content type, body, optional headers */
function site_response(string $path): array
{
    $html = 'text/html; charset=UTF-8';
    $legacyArticleRedirects = [
        '/raksts-6.html' => '/raksts-1.html',
        '/raksts-7.html' => '/raksts-2.html',
    ];
    if (isset($legacyArticleRedirects[$path])) {
        return [301, $html, '', ['Location' => $legacyArticleRedirects[$path]]];
    }
    if ($path === '/' || $path === '/index.html') {
        return [200, $html, render_home()];
    }
    if ($path === '/sitemap.xml') {
        return [200, 'application/xml; charset=UTF-8', render_sitemap()];
    }
    if (preg_match('~^/produkts-([1-9]\d{0,8})\.html$~', $path, $m)) {
        $body = render_product((int)$m[1]);
    } elseif (preg_match('~^/(jaunums|raksts)-([1-9]\d{0,8})\.html$~', $path, $m)) {
        $body = render_text_page($m[1] === 'jaunums' ? 'news' : 'articles', (int)$m[2]);
    } else {
        $body = null;
        foreach (category_pages() as $c) {
            if ($path === '/' . $c['link_url']) {
                $body = render_category($c);
            }
        }
    }
    return $body === null ? [404, $html, render_not_found()] : [200, $html, $body];
}
