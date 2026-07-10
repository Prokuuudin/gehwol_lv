<?php

require_once __DIR__ . '/../includes/auth.php';

session_start();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $row = find_admin_by_username($username);
    if (verify_credentials($row, $password)) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $row['id'];
        header('Location: index.php');
        exit;
    }
    $error = 'Nepareizs lietotājvārds vai parole.';
}
?>
<!DOCTYPE html>
<html lang="lv">
<head><meta charset="UTF-8"><title>Ielogoties — Admin</title></head>
<body>
<h1>Ielogoties</h1>
<?php if ($error): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
  <label>Lietotājvārds: <input type="text" name="username" required></label><br>
  <label>Parole: <input type="password" name="password" required></label><br>
  <button type="submit">Ielogoties</button>
</form>
</body>
</html>
