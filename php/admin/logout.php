<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/deletion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    admin_log('logout');
    discard_recent_deletion();
    log_out();
}
header('Location: login.php');
exit;
