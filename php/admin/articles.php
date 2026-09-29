<?php

require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$action = $_GET['action'] ?? 'list';
$errors = [];

$items = load_collection('articles');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true)) {
    require_csrf();
    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'text' => text_to_html($_POST['text'] ?? ''),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'published' => ($_POST['published'] ?? '') === '1',
    ];
    $errors = required_field_errors($data, ['title']);
    $errors = array_merge($errors, max_length_errors($data, ['title' => 255]));

    $id = (int)($_POST['id'] ?? 0);
    $current = null;
    foreach ($items as $i) {
        if ((int)$i['id'] === $id) {
            $current = $i;
        }
    }
    if ($action === 'edit' && $current === null) {
        $errors[] = 'Raksts nav atrasts.';
    }
    $image = $current['image'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        $saved = save_uploaded_image($_FILES['image'], __DIR__ . '/../../uploads/articles');
        if ($saved === null) {
            $errors[] = 'Neizdevās augšupielādēt attēlu (pārbaudi formātu un izmēru, maks. 5 MB).';
        } else {
            $image = 'uploads/articles/' . $saved;
        }
    }

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        if ($action === 'add') {
            $items[] = ['id' => next_id($items)] + $data + [
                'image' => $image,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        } else {
            foreach ($items as &$i) {
                if ((int)$i['id'] === $id) {
                    $i = array_merge($i, $data, ['image' => $image, 'updated_at' => $now]);
                    break;
                }
            }
            unset($i);
        }
        save_collection('articles', $items);
        header('Location: articles.php');
        exit;
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    require_csrf();
    $id = (int)$_GET['id'];
    $items = array_values(array_filter($items, fn($i) => (int)$i['id'] !== $id));
    save_collection('articles', $items);
    header('Location: articles.php');
    exit;
}

$items = sort_rows($items);

$editing = null;
if ($action === 'edit' && isset($_GET['id'])) {
    foreach ($items as $i) {
        if ($i['id'] == $_GET['id']) {
            $editing = $i;
            break;
        }
    }
}

admin_header('Raksti');
foreach ($errors as $e) {
    echo '<p class="error">' . htmlspecialchars($e) . '</p>';
}
?>
<table>
<tr><th>ID</th><th>Nosaukums</th><th>Kārtība</th><th>Statuss</th><th></th></tr>
<?php foreach ($items as $i): ?>
<tr>
  <td><?= (int)$i['id'] ?></td>
  <td><?= htmlspecialchars($i['title']) ?></td>
  <td><?= (int)($i['sort_order'] ?? 0) ?></td>
  <td><?= is_published($i) ? 'Publicēts' : 'Melnraksts' ?></td>
  <td>
    <a href="articles.php?action=edit&id=<?= (int)$i['id'] ?>">Rediģēt</a>
    <a href="articles.php?action=delete&id=<?= (int)$i['id'] ?>&csrf=<?= urlencode(csrf_token()) ?>" onclick="return confirm('Dzēst?')">Dzēst</a>
  </td>
</tr>
<?php endforeach; ?>
</table>

<h2><?= $editing ? 'Rediģēt rakstu' : 'Pievienot rakstu' ?></h2>
<form method="post" action="articles.php?action=<?= $editing ? 'edit' : 'add' ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php if ($editing): ?>
    <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
  <?php endif; ?>
  <label>Nosaukums: <input type="text" name="title" size="60" value="<?= htmlspecialchars($editing['title'] ?? '') ?>" required></label><br>
  <label>Teksts:<br><textarea name="text" rows="14" cols="80"><?= htmlspecialchars($editing['text'] ?? '') ?></textarea></label><br>
  <small>Var rakstīt vienkāršu tekstu: tukša rinda — jauna rindkopa.</small><br>
  <label>Attēls: <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></label><br>
  <label>Kārtība (mazāks skaitlis — augstāk): <input type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>"></label><br>
  <label><input type="checkbox" name="published" value="1" <?= ($editing === null || is_published($editing)) ? 'checked' : '' ?>> Publicēts</label><br>
  <button type="submit"><?= $editing ? 'Saglabāt' : 'Pievienot' ?></button>
</form>
<?php admin_footer(); ?>
