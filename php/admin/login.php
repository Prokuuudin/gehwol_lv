<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';

start_admin_session();
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$error = isset($_GET['expired']) ? 'Sesija beigusies. Lūdzu, ielogojieties vēlreiz.' : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post_string('username');
    $password = post_string('password', false);
    $token = post_string('csrf', false);
    $lockedFor = login_locked_for($ip);
    if (!hash_equals(csrf_token(), $token)) {
        // a form from another site, or one left open past the session lifetime
        $error = 'Lapa bija atvērta pārāk ilgi. Lūdzu, ievadiet datus vēlreiz.';
    } elseif ($lockedFor > 0) {
        $error = 'Pārāk daudz neveiksmīgu mēģinājumu. Mēģiniet vēlreiz pēc ' . (int)ceil($lockedFor / 60) . ' min.';
    } else {
        $row = find_admin_by_username($username);
        if (verify_credentials($row, $password)) {
            login_clear_failures($ip);
            log_in($row);
            admin_log('login ok');
            header('Location: index.php');
            exit;
        }
        login_record_failure($ip);
        error_log(sprintf('[gehwol-admin] login failed ip=%s', $ip));
        sleep(1);
        $error = 'Nepareizs lietotājvārds vai parole.';
    }
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Ielogoties — GEHWOL Admin</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body class="login-page">
<main class="login-wrap">
  <div class="login-brand"><span class="brand-mark" aria-hidden="true">G</span><span>GEHWOL</span></div>
  <section class="login-card" aria-labelledby="login-title">
    <h1 id="login-title">Ielogoties</h1>
    <p class="login-card__intro">Pieslēdzieties vietnes administrācijas panelim.</p>
    <?php if ($error): ?><p class="error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form class="admin-form" method="post">
      <?= csrf_field() ?>
      <div class="form-field"><label for="username">Lietotājvārds</label><input id="username" type="text" name="username" autocomplete="username" required autofocus></div>
      <div class="form-field"><label for="password">Parole</label><input id="password" type="password" name="password" autocomplete="current-password" required></div>
      <button class="button button--primary" type="submit">Ielogoties</button>
    </form>
  </section>
</main>
</body>
</html>
