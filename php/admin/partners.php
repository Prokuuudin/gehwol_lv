<?php
// Manufacturer and retailer links shown above the footer of every public page.
// The whole list is edited in one form; empty rows at the end add new links.

require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/partners.php';
require_once __DIR__ . '/includes/layout.php';

const PARTNER_EMPTY_ROWS = 3;

require_login();

$errors = [];
$partners = load_partners();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    [$posted, $errors] = partners_from_form($_POST['partners'] ?? []);
    if ($errors) {
        $partners = $posted;
    } else {
        save_collection('partners', $posted);
        admin_log('partners save count=' . count($posted));
        header('Location: partners.php?saved=1');
        exit;
    }
}

$empty = ['group' => 'retailer', 'name' => '', 'subtitle' => '', 'url' => ''];
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
    <p class="page-intro">Saites bloks virs kājenes visās vietnes lapās. Lai pievienotu saiti, aizpildiet tukšu rindu; lai noņemtu — atzīmējiet „Dzēst”.</p>
  </div>
</div>
<form class="form-card admin-form" method="post" action="partners.php">
  <?= csrf_field() ?>
  <div class="table-scroll" tabindex="0" role="region" aria-label="Partneru saites">
  <table class="images partners-table">
    <tr><th>Kārtība</th><th>Grupa</th><th>Nosaukums</th><th>Paskaidrojums</th><th>Saite</th><th>Dzēst</th></tr>
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
      <td><input type="checkbox" name="<?= $field('remove') ?>" value="1" aria-label="Dzēst rindu <?= $n ?>"></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="field-hint">Paskaidrojums nav obligāts (piemēram, „GEHWOL ražotājs”). Saites atveras jaunā cilnē. Ja kādā grupā nav saišu, tās kartīte vietnē netiek rādīta.</p>
  <div class="form-actions"><button class="button button--primary" type="submit">Saglabāt</button></div>
</form>
<?php admin_footer(); ?>
