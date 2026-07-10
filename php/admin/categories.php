<?php

require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$action = $_GET['action'] ?? 'list';
$errors = [];

$categories = load_collection('categories');

function category_has_children(array $categories, int $id): bool
{
    foreach ($categories as $c) {
        if ((int)($c['parent_id'] ?? 0) === $id) {
            return true;
        }
    }
    return false;
}

function category_has_products(int $id): bool
{
    foreach (load_collection('products') as $p) {
        if ((int)$p['category_id'] === $id) {
            return true;
        }
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true)) {
    require_csrf();
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'parent_id' => $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null,
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
    ];
    $errors = required_field_errors($data, ['name']);
    $errors = array_merge($errors, max_length_errors($data, ['name' => 255]));

    if (!$errors) {
        if ($action === 'add') {
            $categories[] = [
                'id' => next_id($categories),
                'parent_id' => $data['parent_id'],
                'name' => $data['name'],
                'link_url' => null,
                'sort_order' => $data['sort_order'],
            ];
        } else {
            $id = (int)$_POST['id'];
            foreach ($categories as &$c) {
                if ((int)$c['id'] === $id) {
                    $c['name'] = $data['name'];
                    $c['parent_id'] = $data['parent_id'];
                    $c['sort_order'] = $data['sort_order'];
                    break;
                }
            }
            unset($c);
        }
        save_collection('categories', $categories);
        header('Location: categories.php');
        exit;
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    require_csrf();
    $id = (int)$_GET['id'];
    if (category_has_children($categories, $id) || category_has_products($id)) {
        $errors[] = 'Nevar dzēst kategoriju, kurai ir apakškategorijas vai produkti.';
        $action = 'list';
    } else {
        $categories = array_values(array_filter($categories, fn($c) => (int)$c['id'] !== $id));
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

admin_header('Kategorijas');
foreach ($errors as $e) {
    echo '<p class="error">' . htmlspecialchars($e) . '</p>';
}
?>
<table>
<tr><th>ID</th><th>Nosaukums</th><th>Vecāks</th><th>Saite</th><th>Kārtība</th><th></th></tr>
<?php foreach ($categories as $c):
    $parentName = '—';
    foreach ($categories as $p) {
        if ($p['id'] == $c['parent_id']) {
            $parentName = $p['name'];
            break;
        }
    }
?>
<tr>
  <td><?= (int)$c['id'] ?></td>
  <td><?= htmlspecialchars($c['name']) ?></td>
  <td><?= htmlspecialchars($parentName) ?></td>
  <td><?= htmlspecialchars($c['link_url'] ?? '(admin)') ?></td>
  <td><?= (int)$c['sort_order'] ?></td>
  <td>
    <a href="categories.php?action=edit&id=<?= (int)$c['id'] ?>">Rediģēt</a>
    <a href="categories.php?action=delete&id=<?= (int)$c['id'] ?>&csrf=<?= urlencode(csrf_token()) ?>" onclick="return confirm('Dzēst?')">Dzēst</a>
  </td>
</tr>
<?php endforeach; ?>
</table>

<h2><?= $editing ? 'Rediģēt kategoriju' : 'Pievienot kategoriju' ?></h2>
<form method="post" action="categories.php?action=<?= $editing ? 'edit' : 'add' ?>">
  <?= csrf_field() ?>
  <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
  <label>Nosaukums: <input type="text" name="name" value="<?= htmlspecialchars($editing['name'] ?? '') ?>" required></label><br>
  <label>Vecāks:
    <select name="parent_id">
      <option value="">— nav (galvenā kategorija) —</option>
      <?php foreach ($categories as $c):
          if ($editing && $c['id'] == $editing['id']) {
              continue;
          }
      ?>
      <option value="<?= (int)$c['id'] ?>" <?= ($editing && $editing['parent_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label><br>
  <label>Kārtība: <input type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>"></label><br>
  <button type="submit"><?= $editing ? 'Saglabāt' : 'Pievienot' ?></button>
</form>
<?php admin_footer(); ?>
