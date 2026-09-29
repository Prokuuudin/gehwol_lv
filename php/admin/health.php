<?php
// Server check: PHP version and extensions, writable folders, data files, templates.
// Open without login only while no admin user exists (first installation); afterwards admins only.

require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/includes/layout.php';

$installMode = !is_file(storage_path('admin_users')) || load_collection('admin_users') === [];
if (!$installMode) {
    require_login();
}

function ini_bytes(string $value): int
{
    $number = (int)$value;
    return match (strtoupper(substr(trim($value), -1))) {
        'G' => $number * 1024 ** 3,
        'M' => $number * 1024 ** 2,
        'K' => $number * 1024,
        default => $number,
    };
}

$root = dirname(__DIR__, 2);
$checks = []; // [label, ok(bool|null for optional), detail]

$release = is_file(__DIR__ . '/../includes/release.php') ? require __DIR__ . '/../includes/release.php' : null;
$checks[] = ['Versija', $release ? true : null, $release ? "commit {$release['commit']}, būvēta {$release['built']} UTC" : 'izstrādes kopija'];
$checks[] = ['PHP ≥ 8.1', version_compare(PHP_VERSION, '8.1', '>='), PHP_VERSION];
foreach (['json', 'mbstring', 'dom', 'session'] as $ext) {
    $checks[] = ["Paplašinājums {$ext}", extension_loaded($ext), ''];
}
$checks[] = ['GD ar WebP (attēlu apstrāde)', image_processing_available(), 'bez tā attēli netiek samazināti'];
$checks[] = ['fileinfo (faila tipa pārbaude)', function_exists('finfo_open') ? true : null, 'ieteicams'];
$checks[] = ['exif (foto pagriešana)', function_exists('exif_read_data') ? true : null, 'ieteicams'];
$checks[] = ['display_errors izslēgts', !filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN), (string)ini_get('display_errors')];
$checks[] = ['upload_max_filesize ≥ 10M', ini_bytes((string)ini_get('upload_max_filesize')) >= UPLOAD_MAX_BYTES, (string)ini_get('upload_max_filesize')];
$checks[] = ['HTTPS', is_https() ? true : null, is_https() ? '' : 'savienojums nav šifrēts'];

$writable = ['php/data' => storage_dir(), 'php/data/backups' => backup_dir()];
foreach (['products', 'news', 'articles'] as $section) {
    $writable["uploads/{$section}"] = UPLOAD_ROOT . '/' . $section;
}
foreach ($writable as $label => $dir) {
    $exists = is_dir($dir);
    $checks[] = ["Rakstāms: {$label}", $label === 'php/data/backups' && !$exists ? null : ($exists && is_writable($dir)), $exists ? '' : 'nav mapes'];
}
foreach (['categories', 'products', 'news', 'articles'] as $collection) {
    try {
        $rows = load_collection($collection);
        $checks[] = ["Dati: {$collection}.json", $rows !== [] || $collection !== 'categories', count($rows) . ' ieraksti' . ($collection === 'categories' ? '' : ', publicēti: ' . count(array_filter($rows, 'is_published')))];
    } catch (StorageException) {
        $checks[] = ["Dati: {$collection}.json", false, 'fails bojāts'];
    }
}
foreach (['_shell.html', 'index.html'] as $template) {
    $checks[] = ["Veidne {$template}", is_file(__DIR__ . '/../templates/' . $template), ''];
}
$checks[] = ['.htaccess vietnes saknē', is_file($root . '/.htaccess'), 'Apache pāradresācijai'];

$failed = count(array_filter($checks, fn($c) => $c[1] === false));

if ($installMode) {
    admin_security_headers();
    echo '<!DOCTYPE html><html lang="lv"><head><meta charset="UTF-8"><meta name="robots" content="noindex, nofollow"><title>Servera pārbaude</title>'
        . '<style>body{font-family:sans-serif;max-width:900px;margin:1rem auto;padding:0 1rem}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:.4rem;text-align:left}</style></head><body>'
        . '<h1>Servera pārbaude</h1><p>Administrators vēl nav izveidots — šī lapa ir redzama visiem, līdz tiek augšupielādēts php/data/admin_users.json.</p>';
} else {
    admin_header('Servera pārbaude');
}
?>
<p><?= $failed ? "<strong class=\"error\">Problēmas: {$failed}</strong>" : '<strong class="notice">Viss kārtībā.</strong>' ?></p>
<table>
<tr><th>Pārbaude</th><th>Rezultāts</th><th>Piezīme</th></tr>
<?php foreach ($checks as [$label, $ok, $detail]): ?>
<tr>
  <td><?= htmlspecialchars($label) ?></td>
  <td><?= $ok === true ? '✔ kārtībā' : ($ok === null ? '— nav (nav obligāti)' : '<strong class="error">✘ kļūda</strong>') ?></td>
  <td><?= htmlspecialchars($detail) ?></td>
</tr>
<?php endforeach; ?>
</table>
<p>Pāradresācijas pārbaude: <a href="../../produkts-1.html" target="_blank" rel="noopener">produkts-1.html</a> jāatver produkta lapa,
<a href="../../nav-tadas-lapas.html" target="_blank" rel="noopener">nav-tadas-lapas.html</a> — lapa «Lapa nav atrasta».</p>
<?php $installMode ? print('</body></html>') : admin_footer(); ?>
