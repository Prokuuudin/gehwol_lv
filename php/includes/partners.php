<?php
// php/includes/partners.php
// Manufacturer and retailer links shown above the footer on every public page.
// Stored in php/data/partners.json as an ordered list of {group, name, subtitle, url};
// until the admin saves the list for the first time, the built-in list below is used.

require_once __DIR__ . '/storage.php';

const PARTNER_GROUPS = ['manufacturer' => 'Ražotājs', 'retailer' => 'Tirgotājs'];

function default_partners(): array
{
    $row = fn(string $group, string $name, string $url, string $subtitle = '') =>
        ['group' => $group, 'name' => $name, 'subtitle' => $subtitle, 'url' => $url];
    return [
        $row('manufacturer', 'Eduard Gerlach GmbH', 'https://www.gehwol.de/', 'GEHWOL ražotājs'),
        $row('retailer', 'BENU Aptieka', 'https://www.benu.lv/zimoli/gehwol'),
        $row('retailer', 'Apotheka', 'https://www.apotheka.lv/'),
        $row('retailer', 'Euroaptieka', 'https://www.euroaptieka.lv/l/gehwol'),
        $row('retailer', 'Hairshop', 'https://www.hairshop.lv/lv/gehwol'),
    ];
}

function load_partners(): array
{
    return file_exists(storage_path('partners')) ? load_collection('partners') : default_partners();
}

function is_partner_url(string $url): bool
{
    return (bool)preg_match('~^https?://[^\s/]+\.[^\s]+$~i', $url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Rows posted by the admin form (partners[i][group|name|subtitle|url|sort_order|remove]):
 * removed and fully empty rows are dropped, the rest are sorted by sort_order.
 * @return array{0: array, 1: list<string>} rows, errors
 */
function partners_from_form(mixed $posted): array
{
    $rows = [];
    $errors = [];
    foreach (is_array($posted) ? $posted : [] as $i => $p) {
        $p = is_array($p) ? $p : [];
        $row = [
            'group' => array_key_exists($p['group'] ?? '', PARTNER_GROUPS) ? $p['group'] : 'retailer',
            'name' => trim((string)($p['name'] ?? '')),
            'subtitle' => trim((string)($p['subtitle'] ?? '')),
            'url' => trim((string)($p['url'] ?? '')),
        ];
        if (!empty($p['remove']) || ($row['name'] === '' && $row['url'] === '' && $row['subtitle'] === '')) {
            continue;
        }
        if (!mb_check_encoding(implode('', $row), 'UTF-8')) {
            $errors[] = 'Rindā ' . ((int)$i + 1) . ': nederīgas rakstzīmes.';
            continue;
        }
        $label = $row['name'] !== '' ? "„{$row['name']}”" : 'Rindā ' . ((int)$i + 1);
        if ($row['name'] === '') {
            $errors[] = "{$label}: norādi nosaukumu.";
        }
        if (!is_partner_url($row['url'])) {
            $errors[] = "{$label}: saitei jābūt pilnai adresei, piemēram, https://www.example.lv/.";
        }
        if (mb_strlen($row['name']) > 100 || mb_strlen($row['subtitle']) > 100 || strlen($row['url']) > 500) {
            $errors[] = "{$label}: teksts ir pārāk garš.";
        }
        $rows[] = [(int)($p['sort_order'] ?? 0), count($rows), $row];
    }
    usort($rows, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
    return [array_column($rows, 2), $errors];
}
