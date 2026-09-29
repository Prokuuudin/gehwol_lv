<?php
// php/bin/set-password.php — create an admin user or reset a password (CLI only).
//   php php/bin/set-password.php <username>
// The password is read from the console. Without SSH on the hosting: run locally, then upload
// php/data/admin_users.json by FTP.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';

$username = $argv[1] ?? null;
if ($username === null) {
    fwrite(STDERR, "Usage: php {$argv[0]} <username>\n");
    exit(1);
}

echo 'Parole (vismaz ' . PASSWORD_MIN_LENGTH . " rakstzīmes, burti un cipari): ";
$password = rtrim((string)fgets(STDIN), "\r\n");
echo 'Atkārtot: ';
if ($password !== rtrim((string)fgets(STDIN), "\r\n")) {
    fwrite(STDERR, "Paroles nesakrīt.\n");
    exit(1);
}
if ($error = set_admin_password($username, $password)) {
    fwrite(STDERR, $error . "\n");
    exit(1);
}
echo "Saglabāts: {$username}\n";
