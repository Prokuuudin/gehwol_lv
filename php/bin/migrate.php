<?php
// php/bin/migrate.php — one-time import into php/data (CLI only, safe to re-run).
//   php php/bin/migrate.php          show what would change
//   php php/bin/migrate.php --write  save (previous versions go to php/data/backups)
//
// 1. products.json rows in the old shape -> current shape (ids and texts kept).
// 2. Products that exist only as src/html/produkts-N.html -> products.json.
// 3. src/html/jaunums-N.html -> news.json, src/html/raksts-N.html -> articles.json.
// Every imported text is compared with its source; any difference stops the write.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/content.php';

const SRC_HTML = __DIR__ . '/../../src/html';

$write = in_array('--write', $argv, true);
$problems = [];

function page_params(string $file, string $block): ?array
{
    $source = file_get_contents($file);
    $re = "/@@include\\('blocks\\/" . preg_quote($block, '/') . "\\.html',\\s*(\\{.*\\})\\s*\\)/s";
    if (!preg_match($re, $source, $m)) {
        return null;
    }
    $params = json_decode($m[1], true);
    return is_array($params) ? $params : null;
}

function plain(string $html): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

/** Same markup? Compares normalized HTML so harmless serializer differences don't count. */
function same_markup(string $a, string $b): bool
{
    $norm = function (string $html): string {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('~<br\s*/?>~i', '<br>', $html);
        return preg_replace('/\s+/u', ' ', preg_replace('/>\s+</', '><', trim($html)));
    };
    return $norm($a) === $norm($b);
}

function added_at(string $file): string
{
    $date = trim((string)shell_exec('git log --diff-filter=A --format=%cs -- ' . escapeshellarg($file) . ' 2>' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null')));
    $date = preg_split('/\s+/', $date)[0] ?? '';
    return (normalize_date($date) ?? date('Y-m-d')) . ' 00:00:00';
}

function page_id(string $file): int
{
    preg_match('/-(\d+)\.html$/', $file, $m);
    return (int)$m[1];
}

function by_id(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        $out[(int)$row['id']] = $row;
    }
    ksort($out);
    return $out;
}

// --- products -------------------------------------------------------------
// The published site is built from src/html/produkts-N.html, and some of those pages were
// edited by hand after generation, so page text wins over the old JSON text. JSON keeps
// category (checked against the page), sort order and dates.
$categories = load_collection('categories');
$categoryByPage = array_column(array_filter($categories, fn($c) => $c['link_url']), 'id', 'link_url');

function product_page_fields(string $file, array $categoryByPage, array &$problems): ?array
{
    $p = page_params($file, 'product-detail-page');
    if ($p === null) {
        $problems[] = basename($file) . ': no product-detail-page include';
        return null;
    }
    $categoryId = $categoryByPage[$p['categoryHref']] ?? null;
    if ($categoryId === null) {
        $problems[] = basename($file) . ": unknown category {$p['categoryHref']}";
        return null;
    }
    $description = sanitize_html($p['description']);
    if (!same_markup($description, $p['description'])) {
        $problems[] = basename($file) . ': description changed by sanitizer';
    }
    preg_match_all('/<img\b[^>]*\bsrc="([^"]+)"/', $p['media'], $m);
    return [
        'category_id' => (int)$categoryId,
        'name' => plain($p['name']),
        'subtitle' => plain($p['subtitle']),
        'description' => $description,
        'active_ingredients' => preg_replace('/^Aktīvās vielas:\s*/u', '', plain($p['activeIngredients'])),
        'images' => array_map(fn($src) => preg_replace('~^\./~', '', $src), $m[1]),
    ];
}

$products = by_id(load_collection('products'));
$converted = 0;
$imported = [];
foreach ($products as $id => $row) {
    if (!is_legacy_product($row)) {
        continue;
    }
    $product = product_from_legacy($row);
    $file = SRC_HTML . "/produkts-{$id}.html";
    if (is_file($file) && ($page = product_page_fields($file, $categoryByPage, $problems))) {
        if ($page['category_id'] !== $product['category_id']) {
            $problems[] = "product {$id}: category {$product['category_id']} in JSON, {$page['category_id']} on page";
        }
        $product = array_merge($product, $page);
    }
    $products[$id] = $product;
    $converted++;
}

foreach (glob(SRC_HTML . '/produkts-*.html') as $file) {
    $id = page_id($file);
    if (isset($products[$id]) || !($page = product_page_fields($file, $categoryByPage, $problems))) {
        continue;
    }
    $created = added_at($file);
    $products[$id] = ['id' => $id] + $page + [
        'sort_order' => $id,
        'published' => true,
        'created_at' => $created,
        'updated_at' => $created,
    ];
    $imported[] = $id;
}

// --- news & articles ------------------------------------------------------
function import_pages(string $pattern, string $block, array $existing, array &$problems): array
{
    $rows = by_id($existing);
    $new = [];
    foreach (glob(SRC_HTML . '/' . $pattern) as $file) {
        $id = page_id($file);
        if (isset($rows[$id])) {
            continue;
        }
        $p = page_params($file, $block);
        if ($p === null) {
            $problems[] = basename($file) . ": no {$block} include";
            continue;
        }
        // news text is plain text inside <p>; article text is HTML
        $source = $block === 'news-detail-page' ? '<p>' . $p['text'] . '</p>' : $p['text'];
        $text = sanitize_html($source);
        if (!same_markup($text, $source)) {
            $problems[] = basename($file) . ': text changed by sanitizer';
        }
        $created = added_at($file);
        $row = ['id' => $id, 'title' => plain($p['title'])];
        if ($block === 'news-detail-page') {
            $row['date'] = normalize_date($p['date']);
            if ($row['date'] === null) {
                $problems[] = basename($file) . ": bad date {$p['date']}";
            }
        }
        $rows[$id] = $row + [
            'text' => $text,
            'image' => null,
            'sort_order' => $block === 'news-detail-page' ? 0 : $id,
            'published' => true,
            'created_at' => $created,
            'updated_at' => $created,
        ];
        $new[] = $id;
    }
    return [$rows, $new];
}

[$news, $newsImported] = import_pages('jaunums-*.html', 'news-detail-page', load_collection('news'), $problems);
[$articles, $articlesImported] = import_pages('raksts-*.html', 'article-detail-page', load_collection('articles'), $problems);

// --- report & save --------------------------------------------------------
$validCategories = array_column($categories, 'id');
foreach ($products as $p) {
    if (!in_array($p['category_id'], $validCategories, true)) {
        $problems[] = "product {$p['id']}: unknown category_id {$p['category_id']}";
    }
}

printf("products: %d total, %d converted, %d imported from HTML%s\n", count($products), $converted, count($imported), $imported ? ' (' . implode(',', $imported) . ')' : '');
printf("news:     %d total, %d imported\n", count($news), count($newsImported));
printf("articles: %d total, %d imported\n", count($articles), count($articlesImported));

if ($problems) {
    fwrite(STDERR, "\nProblems (nothing saved):\n  " . implode("\n  ", $problems) . "\n");
    exit(1);
}
if (!$write) {
    echo "\nDry run. Re-run with --write to save.\n";
    exit(0);
}
if ($converted || $imported) {
    save_collection('products', array_values($products));
}
if ($newsImported) {
    save_collection('news', array_values($news));
}
if ($articlesImported) {
    save_collection('articles', array_values($articles));
}
echo "Saved.\n";
