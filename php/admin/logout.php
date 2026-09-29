<?php

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    admin_log('logout');
    log_out();
}
header('Location: login.php');
exit;
