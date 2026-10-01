<?php

require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $current = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    if (!verify_credentials(find_admin_by_username(current_admin_username()), $current)) {
        $errors[] = 'Pašreizējā parole nav pareiza.';
    } elseif ($new !== (string)($_POST['repeat'] ?? '')) {
        $errors[] = 'Jaunās paroles nesakrīt.';
    } elseif ($problem = set_admin_password(current_admin_username(), $new)) {
        $errors[] = $problem;
    } else {
        admin_log('password changed');
        session_regenerate_id(true);
        $done = true;
    }
}

admin_header('Mainīt paroli');
foreach ($errors as $e) {
    echo '<p class="error" role="alert">' . htmlspecialchars($e) . '</p>';
}
if ($done) {
    echo '<p class="notice" role="status">Parole nomainīta.</p>';
}
?>
<div class="page-actions">
  <div>
    <h2>Kontu drošība</h2>
    <p class="page-intro">Izvēlieties unikālu paroli ar vismaz <?= PASSWORD_MIN_LENGTH ?> rakstzīmēm, burtiem un cipariem.</p>
  </div>
</div>
<form class="form-card admin-form" method="post">
  <?= csrf_field() ?>
  <div class="form-field"><label for="current-password">Pašreizējā parole</label><input id="current-password" type="password" name="current" autocomplete="current-password" required></div>
  <div class="form-field"><label for="new-password">Jaunā parole</label><input id="new-password" type="password" name="new" autocomplete="new-password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required></div>
  <div class="form-field"><label for="repeat-password">Atkārtot jauno paroli</label><input id="repeat-password" type="password" name="repeat" autocomplete="new-password" required></div>
  <div class="form-actions"><button class="button button--primary" type="submit">Mainīt paroli</button></div>
</form>
<?php admin_footer(); ?>
