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
    echo '<p class="error">' . htmlspecialchars($e) . '</p>';
}
if ($done) {
    echo '<p class="notice" role="status">Parole nomainīta.</p>';
}
?>
<form method="post">
  <?= csrf_field() ?>
  <p><label>Pašreizējā parole:<br><input type="password" name="current" autocomplete="current-password" required></label></p>
  <p><label>Jaunā parole (vismaz <?= PASSWORD_MIN_LENGTH ?> rakstzīmes, burti un cipari):<br><input type="password" name="new" autocomplete="new-password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required></label></p>
  <p><label>Atkārtot jauno paroli:<br><input type="password" name="repeat" autocomplete="new-password" required></label></p>
  <button type="submit">Mainīt paroli</button>
</form>
<?php admin_footer(); ?>
