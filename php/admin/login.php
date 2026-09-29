<?php

require_once __DIR__ . '/../includes/auth.php';

start_admin_session();
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$error = isset($_GET['expired']) ? 'Sesija beigusies. Lūdzu, ielogojieties vēlreiz.' : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $lockedFor = login_locked_for($ip);
    if ($lockedFor > 0) {
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
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Ielogoties — Admin</title></head>
<body style="font-family:sans-serif;max-width:400px;margin:3rem auto;padding:0 1rem;">
<h1>Ielogoties</h1>
<?php if ($error): ?><p style="color:#b00020;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
  <p><label>Lietotājvārds:<br><input type="text" name="username" autocomplete="username" required></label></p>
  <p><label>Parole:<br><input type="password" name="password" autocomplete="current-password" required></label></p>
  <button type="submit">Ielogoties</button>
</form>
</body>
</html>
