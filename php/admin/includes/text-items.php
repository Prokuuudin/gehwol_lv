<?php
// Shared admin page for news and articles (title, text, one image, published flag).
// News have a date and are listed newest first; articles have a manual order.

require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/../../includes/content.php';
require_once __DIR__ . '/layout.php';

/**
 * $cfg: collection, page (news.php), prefix (jaunums), title (Jaunumi), add (Pievienot jaunumu),
 *       edit (Rediģēt jaunumu), not_found, dated (bool)
 */
function text_items_page(array $cfg): void
{
    require_login();

    $collection = $cfg['collection'];
    $page = $cfg['page'];
    $dated = $cfg['dated'];
    $action = $_GET['action'] ?? 'list';
    $errors = [];
    $items = load_collection($collection);

    $find = function (int $id) use (&$items): ?array {
        foreach ($items as $i) {
            if ((int)$i['id'] === $id) {
                return $i;
            }
        }
        return null;
    };

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true)) {
        require_csrf();
        $data = [
            'title' => typography(trim($_POST['title'] ?? '')),
            'text' => typography_html(text_to_html($_POST['text'] ?? '')),
            'seo_description' => trim($_POST['seo_description'] ?? ''),
            'published' => ($_POST['published'] ?? '') === '1',
        ];
        if ($dated) {
            $data['date'] = normalize_date($_POST['date'] ?? '');
        } else {
            $data['sort_order'] = (int)($_POST['sort_order'] ?? 0);
        }
        $errors = required_field_errors($data, ['title']);
        $errors = array_merge($errors, max_length_errors($data, ['title' => 255, 'seo_description' => 300]));
        if ($dated && $data['date'] === null) {
            $errors[] = 'Norādi datumu.';
        }

        $id = (int)($_POST['id'] ?? 0);
        $current = $action === 'edit' ? $find($id) : null;
        if ($action === 'edit' && $current === null) {
            $errors[] = $cfg['not_found'];
        }
        $oldImage = $current['image'] ?? null;
        $image = !empty($_POST['image_remove']) ? null : $oldImage;
        $uploaded = null;
        if (($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $uploaded = process_uploaded_image($_FILES['image'], $collection);
                $image = $uploaded;
            } catch (UploadException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors) {
            delete_unused_uploads(array_filter([$uploaded]));
        } else {
            $now = date('Y-m-d H:i:s');
            if ($action === 'add') {
                $id = allocate_id($collection, $items);
                $items[] = ['id' => $id] + $data + ['image' => $image, 'created_at' => $now, 'updated_at' => $now]
                    + ($dated ? ['sort_order' => 0] : []);
            } else {
                foreach ($items as &$i) {
                    if ((int)$i['id'] === $id) {
                        $i = array_merge($i, $data, ['image' => $image, 'updated_at' => $now]);
                        break;
                    }
                }
                unset($i);
            }
            save_collection($collection, $items);
            if ($oldImage !== null && $oldImage !== $image) {
                delete_unused_uploads([$oldImage]);
            }
            admin_log("{$collection} {$action} id={$id}");
            header("Location: {$page}?action=edit&id={$id}&saved=1");
            exit;
        }
    }

    if ($action === 'undo') {
        require_csrf();
        $restored = restore_recent_deletion($collection, (string)($_POST['undo_token'] ?? ''));
        if ($restored !== null) {
            admin_log("{$collection} delete undo id=" . (int)$restored['id']);
        }
        header("Location: {$page}?" . ($restored !== null ? 'restored=1' : 'undo_failed=1'));
        exit;
    }

    if ($action === 'delete') {
        require_csrf();
        $id = (int)($_POST['id'] ?? 0);
        $deleted = $find($id);
        if ($deleted === null) {
            header("Location: {$page}?undo_failed=1");
            exit;
        }
        $items = array_values(array_filter($items, fn($i) => (int)$i['id'] !== $id));
        save_collection($collection, $items);
        remember_recent_deletion($collection, $page, $deleted, array_filter([$deleted['image'] ?? null]));
        admin_log("{$collection} delete id={$id}");
        header("Location: {$page}?deleted=1");
        exit;
    }

    if ($dated) {
        usort($items, fn($a, $b) => [$b['date'] ?? '', (int)$b['id']] <=> [$a['date'] ?? '', (int)$a['id']]);
    } else {
        $items = sort_rows($items);
    }
    $editing = $action === 'edit' && isset($_GET['id']) ? $find((int)$_GET['id']) : null;
    $form = $errors ? array_merge($editing ?? [], $data ?? [], ['text' => $_POST['text'] ?? '']) : $editing;
    $viewUrl = fn(array $row) => '../../' . $cfg['prefix'] . '-' . (int)$row['id'] . '.html';

    admin_header($editing ? $cfg['edit'] : $cfg['title']);
    foreach ($errors as $e) {
        echo '<p class="error" role="alert">' . htmlspecialchars($e) . '</p>';
    }
    if (!$editing): ?>
<div class="page-actions">
  <div>
    <h2><?= $dated ? 'Jaunumu publikācijas' : 'Rakstu publikācijas' ?></h2>
    <p class="page-intro">Pārvaldiet saturu, publicēšanas statusu un attēlus.</p>
  </div>
  <button class="button button--primary" type="button" data-create-form-toggle aria-controls="item-create-panel" aria-expanded="<?= $errors ? 'true' : 'false' ?>"><span aria-hidden="true">+</span> <?= htmlspecialchars($cfg['add']) ?></button>
</div>
<div class="table-panel">
<div class="table-scroll" tabindex="0" role="region" aria-label="<?= htmlspecialchars($cfg['title']) ?> tabula">
<table class="list">
<tr><th>ID</th><th>Nosaukums</th><th><?= $dated ? 'Datums' : 'Kārtība' ?></th><th>Statuss</th><th>Mainīts</th><th></th></tr>
<?php foreach ($items as $i): ?>
<tr>
  <td class="table-id"><?= (int)$i['id'] ?></td>
  <td><a class="item-title" href="<?= $page ?>?action=edit&amp;id=<?= (int)$i['id'] ?>"><?= htmlspecialchars($i['title']) ?></a></td>
  <td><?= $dated ? htmlspecialchars(format_date_lv($i['date'] ?? null) ?: '—') : (int)($i['sort_order'] ?? 0) ?></td>
  <td><?= status_label($i) ?></td>
  <td class="table-meta"><?= htmlspecialchars(substr((string)($i['updated_at'] ?? ''), 0, 10)) ?></td>
  <td>
    <div class="table-actions">
      <a class="button button--small" href="<?= $page ?>?action=edit&amp;id=<?= (int)$i['id'] ?>">Rediģēt</a>
      <?php if (is_published($i)): ?><a class="button button--text button--small" href="<?= htmlspecialchars($viewUrl($i)) ?>" target="_blank" rel="noopener">Skatīt</a><?php endif; ?>
      <?= delete_button($page, (int)$i['id']) ?>
    </div>
  </td>
</tr>
<?php endforeach; ?>
<?php if ($items === []): ?>
<tr><td class="empty-state" colspan="6">Šajā sadaļā vēl nav neviena ieraksta.</td></tr>
<?php endif; ?>
</table>
</div>
<p class="table-summary"><?= count($items) ?> ieraksti</p>
</div>
<div class="create-form-panel" id="item-create-panel"<?= $errors ? '' : ' hidden' ?>>
<div class="section-heading section-heading--form">
  <div><h2><?= htmlspecialchars($cfg['add']) ?></h2><p class="section-intro">Aizpildiet publikācijas saturu un statusu.</p></div>
</div>
<?php else: ?>
<div class="editor-actions">
  <a class="button" href="<?= $page ?>"><span aria-hidden="true">←</span> Atpakaļ uz sarakstu</a>
  <a class="button button--text" href="preview.php?type=<?= $cfg['preview'] ?>&amp;id=<?= (int)$editing['id'] ?>" target="_blank" rel="noopener">Priekšskatīt</a>
  <?php if (is_published($editing)): ?><a class="button button--text" href="<?= htmlspecialchars($viewUrl($editing)) ?>" target="_blank" rel="noopener">Skatīt vietnē <span aria-hidden="true">↗</span></a><?php endif; ?>
</div>
<?php endif; ?>

<form class="form-card admin-form" method="post" action="<?= $page ?>?action=<?= $editing ? 'edit&amp;id=' . (int)$editing['id'] : 'add' ?>" enctype="multipart/form-data" id="item-form">
  <?= csrf_field() ?>
  <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
  <div class="form-field"><label for="item-title">Nosaukums</label><input id="item-title" type="text" name="title" value="<?= htmlspecialchars($form['title'] ?? '') ?>" required></div>
  <?php if ($dated): ?>
  <div class="form-field"><label for="item-date">Datums</label><input id="item-date" type="date" name="date" value="<?= htmlspecialchars($form['date'] ?? date('Y-m-d')) ?>" required></div>
  <?php endif; ?>
  <div class="form-field"><label for="item-text">Teksts</label><textarea id="item-text" name="text" rows="14"><?= htmlspecialchars(html_to_editable_text($form['text'] ?? '')) ?></textarea>
  <p class="field-hint">Var rakstīt vienkāršu tekstu: tukša rinda veido jaunu rindkopu.</p></div>
  <?php if (!empty($editing['image'])): ?>
  <div class="form-field"><span class="field-label">Pašreizējais attēls</span>
    <div class="current-image"><img class="thumb" src="<?= htmlspecialchars(admin_image_url($editing['image'])) ?>" alt="">
      <label class="checkbox-field"><input type="checkbox" name="image_remove" value="1"> <span>Dzēst attēlu pēc saglabāšanas</span></label>
    </div>
  </div>
  <?php endif; ?>
  <div class="form-field"><label for="item-image"><?= !empty($editing['image']) ? 'Aizstāt attēlu' : 'Attēls' ?></label><input id="item-image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
  <p class="field-hint">JPG, PNG vai WebP, līdz <?= UPLOAD_MAX_BYTES / 1024 / 1024 ?> MB.</p></div>
  <?php if (!$dated): ?>
  <div class="form-field"><label for="item-order">Kārtība</label><input id="item-order" type="number" name="sort_order" value="<?= (int)($form['sort_order'] ?? 0) ?>">
  <p class="field-hint">Mazāks skaitlis novieto ierakstu augstāk sarakstā.</p></div>
  <?php endif; ?>
  <div class="form-field"><label for="item-seo">SEO apraksts</label><input id="item-seo" type="text" name="seo_description" maxlength="300" value="<?= htmlspecialchars($form['seo_description'] ?? '') ?>">
  <p class="field-hint">Ja lauks ir tukšs, apraksts veidojas no nosaukuma.</p></div>
  <label class="checkbox-field"><input type="checkbox" name="published" value="1" <?= ($form === null || is_published($form)) ? 'checked' : '' ?>> <span>Publicēts <span class="field-hint">(redzams vietnē)</span></span></label>
  <div class="form-actions"><button class="button button--primary" type="submit"><?= $editing ? 'Saglabāt izmaiņas' : htmlspecialchars($cfg['add']) ?></button></div>
</form>
<?php if (!$editing): ?></div><?php endif; ?>
<script>
(function () {
  var form = document.getElementById('item-form'), dirty = false;
  var toggle = document.querySelector('[data-create-form-toggle]');
  var panel = document.getElementById('item-create-panel');
  if (toggle && panel) {
    toggle.closest('.page-actions').insertAdjacentElement('afterend', panel);
    toggle.addEventListener('click', function () {
      var opening = panel.hidden;
      panel.hidden = !opening;
      toggle.setAttribute('aria-expanded', String(opening));
      if (opening) {
        panel.querySelector('input:not([type="hidden"]), select, textarea').focus();
      }
    });
  }
  form.addEventListener('input', function () { dirty = true; });
  form.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
<?php
    admin_footer();
}
