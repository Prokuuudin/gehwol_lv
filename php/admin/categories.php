<?php
// Categories are fixed (21 rows, the site menu is part of the page template).
// Only renaming is allowed here; adding or removing a category is a developer task.

require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$action = $_GET['action'] ?? 'list';
$errors = [];

$categories = load_collection('categories');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    require_csrf();
    $data = ['name' => trim($_POST['name'] ?? '')];
    $errors = required_field_errors($data, ['name']);
    $errors = array_merge($errors, max_length_errors($data, ['name' => 255]));

    $id = (int)($_POST['id'] ?? 0);
    if (!in_array($id, array_map(fn($c) => (int)$c['id'], $categories), true)) {
        $errors[] = 'Kategorija nav atrasta.';
    }

    if (!$errors) {
        foreach ($categories as &$c) {
            if ((int)$c['id'] === $id) {
                $c['name'] = $data['name'];
                break;
            }
        }
        unset($c);
        save_collection('categories', $categories);
        header('Location: categories.php');
        exit;
    }
}

usort($categories, fn($a, $b) =>
    [(int)($a['parent_id'] ?? 0) !== 0, (int)($a['parent_id'] ?? 0), (int)$a['sort_order']]
    <=> [(int)($b['parent_id'] ?? 0) !== 0, (int)($b['parent_id'] ?? 0), (int)$b['sort_order']]);

$editing = null;
if ($action === 'edit' && isset($_GET['id'])) {
    foreach ($categories as $c) {
        if ($c['id'] == $_GET['id']) {
            $editing = $c;
            break;
        }
    }
}

$names = array_column($categories, 'name', 'id');

admin_header('Kategorijas');
foreach ($errors as $e) {
    echo '<p class="error">' . htmlspecialchars($e) . '</p>';
}
?>
<p>Kategoriju saraksts ir fiksēts. Jaunas kategorijas pievieno izstrādātājs.</p>
<table>
<tr><th>ID</th><th>Nosaukums</th><th>Vecāks</th><th>Lapa</th><th></th></tr>
<?php foreach ($categories as $c): ?>
<tr>
  <td><?= (int)$c['id'] ?></td>
  <td><?= htmlspecialchars($c['name']) ?></td>
  <td><?= htmlspecialchars($names[$c['parent_id']] ?? '—') ?></td>
  <td><?= htmlspecialchars($c['link_url'] ?? '—') ?></td>
  <td><a href="categories.php?action=edit&id=<?= (int)$c['id'] ?>">Pārdēvēt</a></td>
</tr>
<?php endforeach; ?>
</table>

<?php if ($editing): ?>
<h2>Pārdēvēt kategoriju</h2>
<form method="post" action="categories.php?action=edit">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
  <label>Nosaukums: <input type="text" name="name" size="60" value="<?= htmlspecialchars($editing['name']) ?>" required></label><br>
  <button type="submit">Saglabāt</button>
</form>
<?php endif; ?>
<?php admin_footer(); ?>
