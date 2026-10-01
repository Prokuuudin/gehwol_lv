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
    echo '<!DOCTYPE html><html lang="lv"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Servera pārbaude — GEHWOL Admin</title>'
        . '<link rel="stylesheet" href="admin.css"></head><body class="setup-page"><main class="setup-wrap">'
        . '<header class="page-heading"><p class="eyebrow">Administrācija</p><h1>Servera pārbaude</h1></header>'
        . '<div class="alert alert--warning" role="alert"><strong>Administrators vēl nav izveidots.</strong><span>Šī lapa ir publiski redzama, līdz tiek augšupielādēts php/data/admin_users.json.</span></div>';
} else {
    admin_header('Servera pārbaude');
}
?>
<div class="status-card <?= $failed ? 'status-card--error' : 'status-card--success' ?>" role="status">
  <span class="status-card__icon" aria-hidden="true"><?= $failed ? '!' : '✓' ?></span>
  <div>
    <strong><?= $failed ? "Atrastas problēmas: {$failed}" : 'Viss kārtībā' ?></strong>
    <span><?= $failed ? 'Pārskatiet ar kļūdu atzīmētās rindas.' : 'Visas obligātās servera pārbaudes ir veiksmīgas.' ?></span>
  </div>
</div>
<div class="table-panel">
<div class="table-scroll" tabindex="0" role="region" aria-label="Servera pārbaužu rezultāti">
<table class="health-table">
<tr><th>Pārbaude</th><th>Rezultāts</th><th>Piezīme</th></tr>
<?php foreach ($checks as [$label, $ok, $detail]): ?>
<tr class="<?= $ok === true ? 'check--success' : ($ok === null ? 'check--optional' : 'check--error') ?>">
  <td><?= htmlspecialchars($label) ?></td>
  <td><?= $ok === true
      ? '<span class="badge badge--success"><span aria-hidden="true">✓</span> Kārtībā</span>'
      : ($ok === null
          ? '<span class="badge badge--optional"><span aria-hidden="true">—</span> Nav obligāti</span>'
          : '<span class="badge badge--error"><span aria-hidden="true">✕</span> Kļūda</span>') ?></td>
  <td><?= htmlspecialchars($detail) ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
</div>
<p class="health-notes">Pāradresācijas pārbaude: <a href="../../produkts-1.html" target="_blank" rel="noopener">produkts-1.html</a> jāatver produkta lapa,
<a href="../../nav-tadas-lapas.html" target="_blank" rel="noopener">nav-tadas-lapas.html</a> — lapa “Lapa nav atrasta”.</p>
<?php $installMode ? print('</main></body></html>') : admin_footer(); ?>
