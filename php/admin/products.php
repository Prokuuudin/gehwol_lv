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
        'name' => typography(trim($_POST['name'] ?? '')),
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'subtitle' => typography(trim($_POST['subtitle'] ?? '')),
        'description' => typography_html(text_to_html($_POST['description'] ?? '')),
        'active_ingredients' => typography(trim($_POST['active_ingredients'] ?? '')),
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

if ($action === 'undo') {
    require_csrf();
    $restored = restore_recent_deletion('products', (string)($_POST['undo_token'] ?? ''));
    if ($restored !== null) {
        admin_log('product delete undo id=' . (int)$restored['id']);
    }
    header('Location: products.php?' . ($restored !== null ? 'restored=1' : 'undo_failed=1'));
    exit;
}

if ($action === 'delete') {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $deleted = find_product($products, $id);
    if ($deleted === null) {
        header('Location: products.php?undo_failed=1');
        exit;
    }
    $products = array_values(array_filter($products, fn($p) => (int)$p['id'] !== $id));
    save_collection('products', $products);
    remember_recent_deletion('products', 'products.php', $deleted, $deleted['images'] ?? []);
    admin_log("product delete id={$id}");
    header('Location: products.php?deleted=1');
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
    echo '<p class="error" role="alert">' . htmlspecialchars($e) . '</p>';
}
?>
<?php if (!$editing): ?>
<div class="page-actions">
  <div>
    <h2>Produktu katalogs</h2>
    <p class="page-intro">Atrodiet, rediģējiet un publicējiet katalogā esošos produktus.</p>
  </div>
  <button class="button button--primary" type="button" data-create-form-toggle aria-controls="product-create-panel" aria-expanded="<?= $errors ? 'true' : 'false' ?>">
    <span class="create-form-toggle__icon" aria-hidden="true"></span>
    <span class="create-form-toggle__label-open">Pievienot produktu</span>
    <span class="create-form-toggle__label-close">Sakļaut formu</span>
  </button>
</div>
<form class="filters" method="get" action="products.php" role="search">
  <label>Meklēt
    <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Produkta nosaukums">
  </label>
  <label>Kategorija:
    <select name="category">
      <option value="0">— visas —</option>
      <?php foreach ($leafCategories as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= $filterCategory === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="button" type="submit">Rādīt</button>
</form>
<div class="table-panel">
<div class="table-scroll" tabindex="0" role="region" aria-label="Produktu tabula">
<table class="list">
<tr><th>ID</th><th>Attēls</th><th>Nosaukums</th><th>Kategorija</th><th>Statuss</th><th>Mainīts</th><th></th></tr>
<?php foreach ($list as $p): ?>
<tr>
  <td class="table-id"><?= (int)$p['id'] ?></td>
  <td><?php if (!empty($p['images'])): ?><img class="thumb" src="<?= htmlspecialchars(admin_image_url($p['images'][0])) ?>" alt="" loading="lazy"><?php endif; ?></td>
  <td><a class="item-title" href="products.php?action=edit&id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></a></td>
  <td><?= htmlspecialchars($categoryNames[$p['category_id']] ?? '?') ?></td>
  <td><?= status_label($p) ?></td>
  <td class="table-meta"><?= htmlspecialchars(substr((string)($p['updated_at'] ?? ''), 0, 10)) ?></td>
  <td>
    <div class="table-actions">
      <a class="button button--small" href="products.php?action=edit&amp;id=<?= (int)$p['id'] ?>">Rediģēt</a>
      <?php if (is_published($p)): ?><a class="button button--text button--small" href="../../produkts-<?= (int)$p['id'] ?>.html" target="_blank" rel="noopener">Skatīt</a><?php endif; ?>
      <?= delete_button('products.php', (int)$p['id']) ?>
    </div>
  </td>
</tr>
<?php endforeach; ?>
<?php if ($list === []): ?>
<tr><td class="empty-state" colspan="7">Pēc izvēlētajiem kritērijiem produkti nav atrasti.</td></tr>
<?php endif; ?>
</table>
</div>
<p class="table-summary">Parādīti <?= count($list) ?> no <?= count($products) ?> produktiem</p>
</div>
<div class="create-form-panel<?= $errors ? ' is-expanded' : '' ?>" id="product-create-panel"<?= $errors ? '' : ' hidden' ?>>
<div class="section-heading section-heading--form">
  <div><h2>Pievienot produktu</h2><p class="section-intro">Aizpildiet pamatinformāciju un, ja nepieciešams, pievienojiet attēlus.</p></div>
</div>
<?php else: ?>
<div class="editor-actions">
  <a class="button" href="products.php"><span aria-hidden="true">←</span> Visi produkti</a>
  <a class="button button--text" href="preview.php?type=product&amp;id=<?= (int)$editing['id'] ?>" target="_blank" rel="noopener">Priekšskatīt</a>
  <?php if (is_published($editing)): ?><a class="button button--text" href="../../produkts-<?= (int)$editing['id'] ?>.html" target="_blank" rel="noopener">Skatīt vietnē <span aria-hidden="true">↗</span></a><?php endif; ?>
</div>
<?php endif; ?>

<form class="form-card admin-form" method="post" action="products.php?action=<?= $editing ? 'edit&amp;id=' . (int)$editing['id'] : 'add' ?>" enctype="multipart/form-data" id="product-form">
  <?= csrf_field() ?>
  <?php if ($editing): ?>
    <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
  <?php endif; ?>
  <div class="form-field"><label for="product-name">Nosaukums</label><input id="product-name" type="text" name="name" value="<?= htmlspecialchars($form['name'] ?? '') ?>" required></div>
  <div class="form-field"><label for="product-category">Kategorija</label>
    <select id="product-category" name="category_id" required>
      <option value="">— izvēlies —</option>
      <?php foreach ($leafCategories as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= (int)($form['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-field"><label for="product-subtitle">Īss apraksts</label><input id="product-subtitle" type="text" name="subtitle" value="<?= htmlspecialchars($form['subtitle'] ?? '') ?>"></div>
  <div class="form-field"><label for="product-description">Apraksts</label><textarea id="product-description" name="description" rows="10"><?= htmlspecialchars(html_to_editable_text($form['description'] ?? '')) ?></textarea>
  <p class="field-hint">Var rakstīt vienkāršu tekstu: tukša rinda veido jaunu rindkopu.</p></div>
  <div class="form-field"><label for="product-ingredients">Aktīvās vielas</label><input id="product-ingredients" type="text" name="active_ingredients" value="<?= htmlspecialchars($form['active_ingredients'] ?? '') ?>"></div>

  <?php if (!empty($editing['images'])): ?>
  <div class="form-field"><span class="field-label">Esošie attēli</span>
  <div class="image-manager table-scroll" tabindex="0" role="region" aria-label="Produkta attēli">
  <table class="images">
    <caption>Attēli (pirmais — galvenais)</caption>
    <tr><th>Attēls</th><th>Kārtība</th><th>Alt teksts (ja tukšs — nosaukums)</th><th>Dzēst</th></tr>
    <?php foreach (array_values($editing['images']) as $i => $src): ?>
    <tr>
      <td><img class="thumb" src="<?= htmlspecialchars(admin_image_url($src)) ?>" alt=""></td>
      <td><input type="number" name="image_order[<?= $i ?>]" value="<?= $i + 1 ?>" aria-label="Attēla <?= $i + 1 ?> kārtība"></td>
      <td><input type="text" name="image_alt[<?= $i ?>]" value="<?= htmlspecialchars($editing['image_alts'][$i] ?? '') ?>" aria-label="Attēla <?= $i + 1 ?> alt teksts"></td>
      <td><input type="checkbox" name="image_remove[<?= $i ?>]" value="1" aria-label="Dzēst attēlu <?= $i + 1 ?>"></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  </div>
  <?php endif; ?>
  <div class="form-field"><label for="product-images">Pievienot attēlus</label><input id="product-images" type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
  <p class="field-hint">JPG, PNG vai WebP, līdz <?= UPLOAD_MAX_BYTES / 1024 / 1024 ?> MB katram failam.</p></div>

  <div class="form-field"><label for="product-order">Kārtība sarakstā</label><input id="product-order" type="number" name="sort_order" value="<?= (int)($form['sort_order'] ?? 0) ?>"></div>
  <div class="form-field"><label for="product-seo">SEO apraksts</label><input id="product-seo" type="text" name="seo_description" maxlength="300" value="<?= htmlspecialchars($form['seo_description'] ?? '') ?>">
  <p class="field-hint">Ja lauks ir tukšs, apraksts veidojas no nosaukuma un īsā apraksta.</p></div>
  <label class="checkbox-field"><input type="checkbox" name="published" value="1" <?= ($form === null || is_published($form)) ? 'checked' : '' ?>> <span>Publicēts <span class="field-hint">(redzams vietnē)</span></span></label>
  <div class="form-actions"><button class="button button--primary" type="submit"><?= $editing ? 'Saglabāt izmaiņas' : 'Pievienot produktu' ?></button></div>
</form>
<?php if (!$editing): ?></div><?php endif; ?>
<script>
// warn before leaving with unsaved changes
(function () {
  var form = document.getElementById('product-form'), dirty = false;
  var toggle = document.querySelector('[data-create-form-toggle]');
  var panel = document.getElementById('product-create-panel');
  if (toggle && panel) {
    var panelAnimationTimer;
    var panelAnimationDuration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 440;
    toggle.closest('.page-actions').insertAdjacentElement('afterend', panel);
    toggle.addEventListener('click', function () {
      var opening = toggle.getAttribute('aria-expanded') !== 'true';
      var currentHeight = panel.hidden ? 0 : panel.getBoundingClientRect().height;
      window.clearTimeout(panelAnimationTimer);
      panel.hidden = false;
      panel.style.height = currentHeight + 'px';
      panel.offsetHeight;
      toggle.setAttribute('aria-expanded', String(opening));
      if (opening) {
        panel.classList.add('is-expanded');
        panel.style.height = panel.scrollHeight + 'px';
        panel.querySelector('input:not([type="hidden"]), select, textarea').focus();
      } else {
        panel.classList.remove('is-expanded');
        panel.style.height = '0px';
        if (panel.contains(document.activeElement)) toggle.focus();
      }
      panelAnimationTimer = window.setTimeout(function () {
        panel.style.height = '';
        if (!opening) panel.hidden = true;
      }, panelAnimationDuration);
    });
  }
  form.addEventListener('input', function () { dirty = true; });
  form.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
<?php admin_footer(); ?>
