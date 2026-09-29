<?php
// Categories are fixed: the menu, the category pages and their texts are part of the built
// templates, so a name changed here would not reach them. This page only lists the categories;
// adding, removing or renaming one is a developer task (see readme.md).

require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$categories = load_collection('categories');
usort($categories, fn($a, $b) =>
    [(int)($a['parent_id'] ?? 0) !== 0, (int)($a['parent_id'] ?? 0), (int)$a['sort_order']]
    <=> [(int)($b['parent_id'] ?? 0) !== 0, (int)($b['parent_id'] ?? 0), (int)$b['sort_order']]);

$names = array_column($categories, 'name', 'id');
$productCounts = [];
foreach (load_collection('products') as $p) {
    $key = (int)$p['category_id'];
    $productCounts[$key] = ($productCounts[$key] ?? 0) + (is_published($p) ? 1 : 0);
}

admin_header('Kategorijas');
?>
<p>Kategoriju saraksts un nosaukumi ir daļa no vietnes izvēlnes, tāpēc tos maina izstrādātājs.</p>
<table class="list">
<tr><th>ID</th><th>Nosaukums</th><th>Vecāks</th><th>Publicēti produkti</th><th></th></tr>
<?php foreach ($categories as $c): ?>
<tr>
  <td><?= (int)$c['id'] ?></td>
  <td><?= htmlspecialchars($c['name']) ?></td>
  <td><?= htmlspecialchars($names[$c['parent_id']] ?? '—') ?></td>
  <td><?= $c['link_url'] ? (int)($productCounts[(int)$c['id']] ?? 0) : '—' ?></td>
  <td>
    <?php if ($c['link_url']): ?>
    <a href="products.php?category=<?= (int)$c['id'] ?>">Produkti</a> ·
    <a href="../../<?= htmlspecialchars($c['link_url']) ?>" target="_blank" rel="noopener">Skatīt</a>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</table>
<?php admin_footer(); ?>
