<?php

require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$action = $_GET['action'] ?? 'list';
$errors = [];

$products = load_collection('products');
$allCategories = load_collection('categories');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true)) {
    require_csrf();
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'description' => trim($_POST['description'] ?? ''),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
    ];
    $errors = required_field_errors($data, ['name']);
    $errors = array_merge($errors, max_length_errors($data, ['name' => 255]));
    if ($data['category_id'] <= 0) {
        $errors[] = "Lauks 'category_id' ir obligāts.";
    }

    $imageName = ($_POST['existing_image'] ?? '') !== '' ? $_POST['existing_image'] : null;
    if (!empty($_FILES['image']['name'])) {
        $saved = save_uploaded_image($_FILES['image'], __DIR__ . '/../../uploads/products');
        if ($saved === null) {
            $errors[] = 'Neizdevās augšupielādēt attēlu (pārbaudi formātu un izmēru, maks. 5 MB).';
        } else {
            $imageName = $saved;
        }
    }

    if (!$errors) {
        if ($action === 'add') {
            $products[] = [
                'id' => next_id($products),
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'description' => $data['description'],
                'image' => $imageName,
                'sort_order' => $data['sort_order'],
                'created_at' => date('Y-m-d H:i:s'),
            ];
        } else {
            $id = (int)$_POST['id'];
            foreach ($products as &$p) {
                if ((int)$p['id'] === $id) {
                    $p['category_id'] = $data['category_id'];
                    $p['name'] = $data['name'];
                    $p['description'] = $data['description'];
                    $p['image'] = $imageName;
                    $p['sort_order'] = $data['sort_order'];
                    break;
                }
            }
            unset($p);
        }
        save_collection('products', $products);
        header('Location: products.php');
        exit;
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    require_csrf();
    $id = (int)$_GET['id'];
    $products = array_values(array_filter($products, fn($p) => (int)$p['id'] !== $id));
    save_collection('products', $products);
    header('Location: products.php');
    exit;
}

$parentIds = array_map(fn($c) => (int)($c['parent_id'] ?? 0), $allCategories);
$leafCategories = array_values(array_filter(
    $allCategories,
    fn($c) => !in_array((int)$c['id'], $parentIds, true)
));
usort($leafCategories, fn($a, $b) => strcmp($a['name'], $b['name']));

$categoryNames = array_column($allCategories, 'name', 'id');
$products = sort_rows($products);

$editing = null;
if ($action === 'edit' && isset($_GET['id'])) {
    foreach ($products as $p) {
        if ($p['id'] == $_GET['id']) {
            $editing = $p;
            break;
        }
    }
}

admin_header('Produkti');
foreach ($errors as $e) {
    echo '<p class="error">' . htmlspecialchars($e) . '</p>';
}
?>
<table>
<tr><th>ID</th><th>Nosaukums</th><th>Kategorija</th><th>Attēls</th><th></th></tr>
<?php foreach ($products as $p): ?>
<tr>
  <td><?= (int)$p['id'] ?></td>
  <td><?= htmlspecialchars($p['name']) ?></td>
  <td><?= htmlspecialchars($categoryNames[$p['category_id']] ?? '?') ?></td>
  <td><?= $p['image'] ? htmlspecialchars($p['image']) : '—' ?></td>
  <td>
    <a href="products.php?action=edit&id=<?= (int)$p['id'] ?>">Rediģēt</a>
    <a href="products.php?action=delete&id=<?= (int)$p['id'] ?>&csrf=<?= urlencode(csrf_token()) ?>" onclick="return confirm('Dzēst?')">Dzēst</a>
  </td>
</tr>
<?php endforeach; ?>
</table>

<h2><?= $editing ? 'Rediģēt produktu' : 'Pievienot produktu' ?></h2>
<form method="post" action="products.php?action=<?= $editing ? 'edit' : 'add' ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php if ($editing): ?>
    <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
    <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editing['image'] ?? '') ?>">
  <?php endif; ?>
  <label>Nosaukums: <input type="text" name="name" value="<?= htmlspecialchars($editing['name'] ?? '') ?>" required></label><br>
  <label>Kategorija:
    <select name="category_id" required>
      <option value="">— izvēlies —</option>
      <?php foreach ($leafCategories as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= ($editing && $editing['category_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label><br>
  <label>Apraksts: <textarea name="description" rows="4" cols="50"><?= htmlspecialchars($editing['description'] ?? '') ?></textarea></label><br>
  <label>Attēls: <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></label><br>
  <label>Kārtība: <input type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>"></label><br>
  <button type="submit"><?= $editing ? 'Saglabāt' : 'Pievienot' ?></button>
</form>
<?php admin_footer(); ?>
