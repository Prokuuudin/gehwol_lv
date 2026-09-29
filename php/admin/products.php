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

$products = load_collection('products');
$allCategories = load_collection('categories');

$parentIds = array_map(fn($c) => (int)($c['parent_id'] ?? 0), $allCategories);
$leafCategories = array_values(array_filter(
    $allCategories,
    fn($c) => !in_array((int)$c['id'], $parentIds, true)
));
usort($leafCategories, fn($a, $b) => strcmp($a['name'], $b['name']));
$leafIds = array_map(fn($c) => (int)$c['id'], $leafCategories);

function find_product(array $products, int $id): ?array
{
    foreach ($products as $p) {
        if ((int)$p['id'] === $id) {
            return $p;
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true)) {
    require_csrf();
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'subtitle' => trim($_POST['subtitle'] ?? ''),
        'description' => text_to_html($_POST['description'] ?? ''),
        'active_ingredients' => trim($_POST['active_ingredients'] ?? ''),
        'seo_description' => trim($_POST['seo_description'] ?? ''),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'published' => ($_POST['published'] ?? '') === '1',
    ];
    $errors = required_field_errors($data, ['name']);
    $errors = array_merge($errors, max_length_errors($data, ['name' => 255, 'subtitle' => 255, 'active_ingredients' => 1000, 'seo_description' => 300]));
    if (!in_array($data['category_id'], $leafIds, true)) {
        $errors[] = 'Izvēlies kategoriju no saraksta.';
    }

    $id = (int)($_POST['id'] ?? 0);
    $current = $action === 'edit' ? find_product($products, $id) : null;
    if ($action === 'edit' && $current === null) {
        $errors[] = 'Produkts nav atrasts.';
    }

    // Existing images are taken from the stored record by index; the form only sends order, alt text and removal.
    $kept = [];
    $removed = [];
    foreach (array_values($current['images'] ?? []) as $i => $src) {
        if (!empty($_POST['image_remove'][$i])) {
            $removed[] = $src;
            continue;
        }
        $kept[] = [
            'src' => $src,
            'alt' => mb_substr(trim((string)($_POST['image_alt'][$i] ?? '')), 0, 255),
            'order' => (int)($_POST['image_order'][$i] ?? $i + 1),
            'index' => $i,
        ];
    }
    usort($kept, fn($a, $b) => [$a['order'], $a['index']] <=> [$b['order'], $b['index']]);

    $uploaded = [];
    $files = $_FILES['images'] ?? null;
    if ($files && is_array($files['name'])) {
        foreach ($files['name'] as $i => $name) {
            if ((int)$files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            try {
                $uploaded[] = process_uploaded_image([
                    'name' => $name, 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i],
                ], 'products');
            } catch (UploadException $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if ($errors) {
        delete_unused_uploads($uploaded); // the record is not saved, so fresh uploads are orphans
    } else {
        $images = array_merge(array_column($kept, 'src'), $uploaded);
        $alts = array_merge(array_column($kept, 'alt'), array_fill(0, count($uploaded), ''));
        $imageData = ['images' => $images];
        if (array_filter($alts) !== []) {
            $imageData['image_alts'] = $alts;
        }
        $now = date('Y-m-d H:i:s');
        if ($action === 'add') {
            $id = allocate_id('products', $products);
            $products[] = ['id' => $id] + $data + $imageData + ['created_at' => $now, 'updated_at' => $now];
        } else {
            foreach ($products as &$p) {
                if ((int)$p['id'] === $id) {
                    unset($p['image_alts']);
                    $p = array_merge($p, $data, $imageData, ['updated_at' => $now]);
                    break;
                }
            }
            unset($p);
        }
        save_collection('products', $products);
        delete_unused_uploads($removed);
        admin_log("product {$action} id={$id}");
        header('Location: products.php?action=edit&id=' . $id . '&saved=1');
        exit;
    }
}

if ($action === 'delete') {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $removed = find_product($products, $id)['images'] ?? [];
    $products = array_values(array_filter($products, fn($p) => (int)$p['id'] !== $id));
    save_collection('products', $products);
    delete_unused_uploads($removed);
    admin_log("product delete id={$id}");
    header('Location: products.php?saved=1');
    exit;
}

$categoryNames = array_column($allCategories, 'name', 'id');
$filterCategory = (int)($_GET['category'] ?? 0);
$search = trim((string)($_GET['q'] ?? ''));
$list = array_filter(sort_rows($products), fn($p) =>
    ($filterCategory === 0 || (int)$p['category_id'] === $filterCategory)
    && ($search === '' || mb_stripos($p['name'], $search) !== false));

$editing = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editing = find_product($products, (int)$_GET['id']);
}
// after a failed save keep what the editor typed
$form = $errors ? array_merge($editing ?? [], $data ?? [], ['description' => $_POST['description'] ?? '']) : $editing;

admin_header($editing ? 'Rediģēt produktu' : 'Produkti');
foreach ($errors as $e) {
    echo '<p class="error">' . htmlspecialchars($e) . '</p>';
}
?>
<?php if (!$editing): ?>
<form method="get" action="products.php">
  <label>Meklēt: <input type="search" name="q" value="<?= htmlspecialchars($search) ?>"></label>
  <label>Kategorija:
    <select name="category">
      <option value="0">— visas —</option>
      <?php foreach ($leafCategories as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= $filterCategory === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button type="submit">Rādīt</button>
  <a href="#form">+ Pievienot produktu</a>
</form>
<table class="list">
<tr><th>ID</th><th>Attēls</th><th>Nosaukums</th><th>Kategorija</th><th>Statuss</th><th>Mainīts</th><th></th></tr>
<?php foreach ($list as $p): ?>
<tr>
  <td><?= (int)$p['id'] ?></td>
  <td><?php if (!empty($p['images'])): ?><img class="thumb" src="<?= htmlspecialchars(admin_image_url($p['images'][0])) ?>" alt="" loading="lazy"><?php endif; ?></td>
  <td><a href="products.php?action=edit&id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></a></td>
  <td><?= htmlspecialchars($categoryNames[$p['category_id']] ?? '?') ?></td>
  <td><?= status_label($p) ?></td>
  <td><?= htmlspecialchars(substr((string)($p['updated_at'] ?? ''), 0, 10)) ?></td>
  <td>
    <?php if (is_published($p)): ?><a href="../../produkts-<?= (int)$p['id'] ?>.html" target="_blank" rel="noopener">Skatīt</a><?php endif; ?>
    <?= delete_button('products.php', (int)$p['id']) ?>
  </td>
</tr>
<?php endforeach; ?>
</table>
<p><?= count($list) ?> no <?= count($products) ?></p>
<h2 id="form">Pievienot produktu</h2>
<?php else: ?>
<p><a href="products.php">← Visi produkti</a> · <a href="preview.php?type=product&amp;id=<?= (int)$editing['id'] ?>" target="_blank" rel="noopener">Priekšskatīt</a>
<?php if (is_published($editing)): ?> · <a href="../../produkts-<?= (int)$editing['id'] ?>.html" target="_blank" rel="noopener">Skatīt vietnē</a><?php endif; ?></p>
<?php endif; ?>

<form method="post" action="products.php?action=<?= $editing ? 'edit&amp;id=' . (int)$editing['id'] : 'add' ?>" enctype="multipart/form-data" id="product-form">
  <?= csrf_field() ?>
  <?php if ($editing): ?>
    <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
  <?php endif; ?>
  <p><label>Nosaukums:<br><input type="text" name="name" size="70" value="<?= htmlspecialchars($form['name'] ?? '') ?>" required></label></p>
  <p><label>Kategorija:<br>
    <select name="category_id" required>
      <option value="">— izvēlies —</option>
      <?php foreach ($leafCategories as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= (int)($form['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label></p>
  <p><label>Īss apraksts:<br><input type="text" name="subtitle" size="70" value="<?= htmlspecialchars($form['subtitle'] ?? '') ?>"></label></p>
  <p><label>Apraksts:<br><textarea name="description" rows="10" cols="80"><?= htmlspecialchars(html_to_editable_text($form['description'] ?? '')) ?></textarea></label><br>
  <small>Var rakstīt vienkāršu tekstu: tukša rinda — jauna rindkopa.</small></p>
  <p><label>Aktīvās vielas:<br><input type="text" name="active_ingredients" size="70" value="<?= htmlspecialchars($form['active_ingredients'] ?? '') ?>"></label></p>

  <?php if (!empty($editing['images'])): ?>
  <table class="images">
    <caption>Attēli (pirmais — galvenais)</caption>
    <tr><th>Attēls</th><th>Kārtība</th><th>Alt teksts (ja tukšs — nosaukums)</th><th>Dzēst</th></tr>
    <?php foreach (array_values($editing['images']) as $i => $src): ?>
    <tr>
      <td><img class="thumb" src="<?= htmlspecialchars(admin_image_url($src)) ?>" alt=""></td>
      <td><input type="number" name="image_order[<?= $i ?>]" value="<?= $i + 1 ?>" style="width:4em" aria-label="Attēla <?= $i + 1 ?> kārtība"></td>
      <td><input type="text" name="image_alt[<?= $i ?>]" value="<?= htmlspecialchars($editing['image_alts'][$i] ?? '') ?>" aria-label="Attēla <?= $i + 1 ?> alt teksts"></td>
      <td><input type="checkbox" name="image_remove[<?= $i ?>]" value="1" aria-label="Dzēst attēlu <?= $i + 1 ?>"></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <p><label>Pievienot attēlus (JPG, PNG, WebP, līdz <?= UPLOAD_MAX_BYTES / 1024 / 1024 ?> MB):<br><input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple></label></p>

  <p><label>Kārtība sarakstā:<br><input type="number" name="sort_order" value="<?= (int)($form['sort_order'] ?? 0) ?>"></label></p>
  <p><label>SEO apraksts (ja tukšs — nosaukums un īss apraksts):<br><input type="text" name="seo_description" size="70" maxlength="300" value="<?= htmlspecialchars($form['seo_description'] ?? '') ?>"></label></p>
  <p><label><input type="checkbox" name="published" value="1" <?= ($form === null || is_published($form)) ? 'checked' : '' ?>> Publicēts (redzams vietnē)</label></p>
  <button type="submit"><?= $editing ? 'Saglabāt' : 'Pievienot' ?></button>
</form>
<script>
// warn before leaving with unsaved changes
(function () {
  var form = document.getElementById('product-form'), dirty = false;
  form.addEventListener('input', function () { dirty = true; });
  form.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
<?php admin_footer(); ?>
