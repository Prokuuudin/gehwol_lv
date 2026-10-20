<?php
// Video blocks shown on the homepage: MP4 file, optional poster, caption, order and status.

require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/../includes/videos.php';
require_once __DIR__ . '/includes/layout.php';

require_login();
initialize_videos();

$page = 'videos.php';
$action = $_GET['action'] ?? 'list';
$errors = [];
$items = load_collection('videos');

$find = function (int $id) use (&$items): ?array {
    foreach ($items as $item) {
        if ((int)($item['id'] ?? 0) === $id) {
            return $item;
        }
    }
    return null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true)) {
    require_csrf();
    $data = [
        'title' => typography(post_string('title')),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'published' => ($_POST['published'] ?? '') === '1',
    ];
    $errors = array_merge(
        required_field_errors($data, ['title']),
        max_length_errors($data, ['title' => 500])
    );

    $id = (int)($_POST['id'] ?? 0);
    $current = $action === 'edit' ? $find($id) : null;
    if ($action === 'edit' && $current === null) {
        $errors[] = 'Video nav atrasts.';
    }

    $oldVideo = $current['video'] ?? null;
    $oldPoster = $current['poster'] ?? null;
    $video = $oldVideo;
    $poster = !empty($_POST['poster_remove']) ? null : $oldPoster;
    $uploadedVideo = null;
    $uploadedPoster = null;

    if (($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $uploadedVideo = process_uploaded_video($_FILES['video']);
            $video = $uploadedVideo;
        } catch (UploadException $e) {
            $errors[] = $e->getMessage();
        }
    }
    if (($_FILES['poster']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $uploadedPoster = process_uploaded_image($_FILES['poster'], 'videos');
            $poster = $uploadedPoster;
        } catch (UploadException $e) {
            $errors[] = $e->getMessage();
        }
    }
    if ($video === null) {
        $errors[] = 'Izvēlieties MP4 video failu.';
    }

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        $saved = update_collection('videos', function (array $rows) use ($action, &$id, $data, $video, $poster, $now) {
            if ($action === 'add') {
                $id = allocate_id('videos', $rows);
                $rows[] = ['id' => $id] + $data + [
                    'video' => $video,
                    'poster' => $poster,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                return $rows;
            }
            foreach ($rows as &$row) {
                if ((int)$row['id'] === $id) {
                    $row = array_merge($row, $data, [
                        'video' => $video,
                        'poster' => $poster,
                        'updated_at' => $now,
                    ]);
                    return $rows;
                }
            }
            return null;
        });
        if ($saved === null) {
            $errors[] = 'Video nav atrasts.';
        } else {
            delete_unused_uploads(array_filter([
                $oldVideo !== $video ? $oldVideo : null,
                $oldPoster !== $poster ? $oldPoster : null,
            ]));
            admin_log("videos {$action} id={$id}");
            header("Location: {$page}?action=edit&id={$id}&saved=1");
            exit;
        }
    }
    if ($errors) {
        delete_unused_uploads(array_filter([$uploadedVideo, $uploadedPoster]));
    }
}

if ($action === 'undo') {
    require_csrf();
    $restored = restore_recent_deletion('videos', post_string('undo_token', false));
    if ($restored !== null) {
        admin_log('videos delete undo id=' . (int)$restored['id']);
    }
    header("Location: {$page}?" . ($restored !== null ? 'restored=1' : 'undo_failed=1'));
    exit;
}

if ($action === 'delete') {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $deleted = null;
    update_collection('videos', function (array $rows) use ($id, &$deleted) {
        foreach ($rows as $row) {
            if ((int)$row['id'] === $id) {
                $deleted = $row;
            }
        }
        return $deleted === null ? null : array_values(array_filter($rows, fn($row) => (int)$row['id'] !== $id));
    });
    if ($deleted === null) {
        header("Location: {$page}?undo_failed=1");
        exit;
    }
    remember_recent_deletion('videos', $page, $deleted, array_filter([$deleted['video'] ?? null, $deleted['poster'] ?? null]));
    admin_log("videos delete id={$id}");
    header("Location: {$page}?deleted=1");
    exit;
}

$items = sort_rows($items);
$editing = $action === 'edit' && isset($_GET['id']) ? $find((int)$_GET['id']) : null;
$form = $errors ? array_merge($editing ?? [], $data ?? []) : $editing;

admin_header($editing ? 'Rediģēt video' : 'Video');
foreach ($errors as $error) {
    echo '<p class="error" role="alert">' . htmlspecialchars($error) . '</p>';
}
if (!$editing): ?>
<div class="page-actions">
  <div>
    <h2>Sākumlapas video</h2>
    <p class="page-intro">Pievienojiet vairākus video blokus un norādiet to secību sākumlapā.</p>
  </div>
  <a class="button button--primary" href="videos.php?action=add">Pievienot video</a>
</div>
<?php if ($action !== 'add'): ?>
<div class="table-panel">
<div class="table-scroll" tabindex="0" role="region" aria-label="Video tabula">
<table class="list">
<tr><th>ID</th><th>Nosaukums</th><th>Kārtība</th><th>Statuss</th><th>Mainīts</th><th></th></tr>
<?php foreach ($items as $item): ?>
<tr>
  <td class="table-id"><?= (int)$item['id'] ?></td>
  <td><a class="item-title" href="videos.php?action=edit&amp;id=<?= (int)$item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a></td>
  <td><?= (int)($item['sort_order'] ?? 0) ?></td>
  <td><?= status_label($item) ?></td>
  <td class="table-meta"><?= htmlspecialchars(substr((string)($item['updated_at'] ?? ''), 0, 10)) ?></td>
  <td><div class="table-actions">
    <a class="button button--small" href="videos.php?action=edit&amp;id=<?= (int)$item['id'] ?>">Rediģēt</a>
    <?php if (is_published($item)): ?><a class="button button--text button--small" href="../../index.html#video" target="_blank" rel="noopener">Skatīt</a><?php endif; ?>
    <?= delete_button($page, (int)$item['id']) ?>
  </div></td>
</tr>
<?php endforeach; ?>
<?php if ($items === []): ?><tr><td class="empty-state" colspan="6">Nav pievienots neviens video.</td></tr><?php endif; ?>
</table>
</div>
<p class="table-summary"><?= count($items) ?> ieraksti</p>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($editing || $action === 'add'): ?>
<div class="editor-actions">
  <a class="button" href="videos.php"><span aria-hidden="true">←</span> Atpakaļ uz sarakstu</a>
  <?php if ($editing && is_published($editing)): ?><a class="button button--text" href="../../index.html#video" target="_blank" rel="noopener">Skatīt vietnē <span aria-hidden="true">↗</span></a><?php endif; ?>
</div>
<form class="form-card admin-form" method="post" action="videos.php?action=<?= $editing ? 'edit&amp;id=' . (int)$editing['id'] : 'add' ?>" enctype="multipart/form-data" id="video-form">
  <?= csrf_field() ?>
  <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
  <div class="form-field"><label for="video-title">Nosaukums un apraksts</label><textarea id="video-title" name="title" rows="3" maxlength="500" required><?= htmlspecialchars($form['title'] ?? '') ?></textarea>
  <p class="field-hint">Šis teksts būs redzams zem video un tiks izmantots arī pieejamības aprakstam.</p></div>

  <?php if (!empty($editing['video'])): ?>
  <div class="form-field"><span class="field-label">Pašreizējais video</span>
    <video class="admin-video-preview" controls preload="metadata" src="<?= htmlspecialchars(admin_image_url($editing['video'])) ?>"></video>
  </div>
  <?php endif; ?>
  <div class="form-field"><label for="video-file"><?= !empty($editing['video']) ? 'Aizstāt video' : 'Video fails' ?></label>
    <input id="video-file" type="file" name="video" accept="video/mp4,.mp4" <?= $editing ? '' : 'required' ?>>
    <p class="field-hint">MP4, līdz <?= VIDEO_UPLOAD_MAX_BYTES / 1024 / 1024 ?> MB. Liela faila augšupielāde var aizņemt vairākas minūtes.</p>
  </div>

  <?php if (!empty($editing['poster'])): ?>
  <div class="form-field"><span class="field-label">Pašreizējā titulbilde</span>
    <div class="current-image"><img class="thumb" src="<?= htmlspecialchars(admin_image_url($editing['poster'])) ?>" alt="">
      <label class="checkbox-field"><input type="checkbox" name="poster_remove" value="1"> <span>Dzēst titulbildi pēc saglabāšanas</span></label>
    </div>
  </div>
  <?php endif; ?>
  <div class="form-field"><label for="video-poster"><?= !empty($editing['poster']) ? 'Aizstāt titulbildi' : 'Titulbilde' ?></label>
    <input id="video-poster" type="file" name="poster" accept=".jpg,.jpeg,.png,.webp">
    <p class="field-hint">Nav obligāta. Ieteicamā malu attiecība 16:9; JPG, PNG vai WebP līdz <?= UPLOAD_MAX_BYTES / 1024 / 1024 ?> MB.</p>
  </div>
  <div class="form-field"><label for="video-order">Kārtība</label><input id="video-order" type="number" name="sort_order" value="<?= (int)($form['sort_order'] ?? 0) ?>">
  <p class="field-hint">Mazāks skaitlis novieto video augstāk.</p></div>
  <label class="checkbox-field"><input type="checkbox" name="published" value="1" <?= ($form === null || is_published($form)) ? 'checked' : '' ?>> <span>Publicēts <span class="field-hint">(redzams vietnē)</span></span></label>
  <div class="form-actions"><button class="button button--primary" type="submit"><?= $editing ? 'Saglabāt izmaiņas' : 'Pievienot video' ?></button></div>
</form>
<script>
(function () {
  var form = document.getElementById('video-form'), dirty = false;
  form.addEventListener('input', function () { dirty = true; });
  form.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (event) { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
})();
</script>
<?php endif; ?>
<?php admin_footer(); ?>
