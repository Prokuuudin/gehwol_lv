<?php
// Manufacturer and retailer links shown above the footer of every public page.
// The whole list is edited in one form; empty rows at the end add new links.

require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/partners.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/includes/layout.php';

const PARTNER_EMPTY_ROWS = 3;

require_login();

$errors = [];
$partners = load_partners();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $oldLogos = array_values(array_filter(array_map(fn($p) => partner_logo($p)['src'] ?? null, $partners)));
    $postedRows = is_array($_POST['partners'] ?? null) ? $_POST['partners'] : [];
    $files = $_FILES['partner_logos'] ?? null;
    foreach ($postedRows as $i => &$row) {
        if (!is_array($row) || !empty($row['remove'])) {
            continue;
        }
        $error = is_array($files['error'] ?? null) ? (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        try {
            $row['logo'] = process_uploaded_image([
                'name' => $files['name'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $error,
            ], 'partners');
            unset($row['logo_remove']); // a newly selected file replaces the current logo
        } catch (UploadException $e) {
            $errors[] = 'Rindā ' . ((int)$i + 1) . ': ' . $e->getMessage();
        }
    }
    unset($row);
    [$posted, $formErrors] = partners_from_form($postedRows);
    $errors = array_merge($errors, $formErrors);
    if ($errors) {
        $partners = $posted;
    } else {
        save_collection('partners', $posted);
        $newLogos = array_values(array_filter(array_map(fn($p) => partner_logo($p)['src'] ?? null, $posted)));
        delete_unused_uploads(array_values(array_diff($oldLogos, $newLogos)));
        admin_log('partners save count=' . count($posted));
        header('Location: partners.php?saved=1');
        exit;
    }
}

$empty = ['group' => 'retailer', 'name' => '', 'subtitle' => '', 'url' => '', 'logo' => ''];
$rows = array_merge($partners, array_fill(0, PARTNER_EMPTY_ROWS, $empty));

admin_header('Partneri');
foreach ($errors as $e) {
    echo '<p class="error" role="alert">' . htmlspecialchars($e) . '</p>';
}
if (isset($_GET['saved'])) {
    echo '<p class="notice" role="status">Saglabāts. Izmaiņas jau redzamas vietnē.</p>';
}
?>
<div class="page-actions">
  <div>
    <h2>Ražotājs un tirgotāji</h2>
    <p class="page-intro">Saites bloks virs kājenes visās vietnes lapās. Katrai saitei var pievienot, aizstāt vai noņemt logotipu.</p>
  </div>
</div>
<form class="form-card admin-form" method="post" action="partners.php" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="table-scroll" tabindex="0" role="region" aria-label="Partneru saites">
  <table class="images partners-table">
    <tr><th>Kārtība</th><th>Grupa</th><th>Nosaukums</th><th>Paskaidrojums</th><th>Saite</th><th>Logotips</th><th>Dzēst</th></tr>
    <?php foreach ($rows as $i => $p): ?>
    <?php $n = $i + 1; $field = fn(string $key) => 'partners[' . $i . '][' . $key . ']'; ?>
    <tr>
      <td><input type="number" name="<?= $field('sort_order') ?>" value="<?= $n ?>" aria-label="Rindas <?= $n ?> kārtība"></td>
      <td>
        <select name="<?= $field('group') ?>" aria-label="Rindas <?= $n ?> grupa">
          <?php foreach (PARTNER_GROUPS as $value => $label): ?>
          <option value="<?= $value ?>" <?= $p['group'] === $value ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><input type="text" name="<?= $field('name') ?>" value="<?= htmlspecialchars($p['name']) ?>" maxlength="100" aria-label="Rindas <?= $n ?> nosaukums"></td>
      <td><input type="text" name="<?= $field('subtitle') ?>" value="<?= htmlspecialchars($p['subtitle']) ?>" maxlength="100" aria-label="Rindas <?= $n ?> paskaidrojums"></td>
      <td><input type="url" name="<?= $field('url') ?>" value="<?= htmlspecialchars($p['url']) ?>" maxlength="500" placeholder="https://" aria-label="Rindas <?= $n ?> saite"></td>
      <td class="partner-logo-cell">
        <div class="partner-logo-controls">
        <?php $logo = partner_logo($p); ?>
        <input type="hidden" name="<?= $field('logo') ?>" value="<?= htmlspecialchars($logo['src'] ?? '') ?>">
        <?php if ($logo): ?>
        <img class="partner-logo-preview" src="<?= htmlspecialchars(admin_image_url($logo['src'])) ?>" alt="">
        <label class="partner-logo-remove"><input type="checkbox" name="<?= $field('logo_remove') ?>" value="1"> <span>Noņemt</span></label>
        <?php endif; ?>
        <input type="file" name="partner_logos[<?= $i ?>]" accept=".jpg,.jpeg,.png,.webp" aria-label="Rindas <?= $n ?> logotips">
        </div>
      </td>
      <td><input type="checkbox" name="<?= $field('remove') ?>" value="1" aria-label="Dzēst rindu <?= $n ?>"></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="field-hint">Logotips nav obligāts. Ieteicamā malu attiecība — aptuveni 3:1; JPG, PNG vai WebP līdz <?= UPLOAD_MAX_BYTES / 1024 / 1024 ?> MB. Jauna faila izvēle aizstāj pašreizējo logotipu.</p>
  <div class="form-actions"><button class="button button--primary" type="submit">Saglabāt</button></div>
</form>
<?php admin_footer(); ?>
