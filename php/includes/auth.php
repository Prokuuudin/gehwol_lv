<?php
// php/includes/auth.php
// Admin login, session, CSRF and login throttling.

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/errors.php';

const SESSION_IDLE_SECONDS = 30 * 60;
const LOGIN_MAX_FAILURES = 5;
const LOGIN_WINDOW_SECONDS = 15 * 60;
const LOGIN_LOCK_SECONDS = 15 * 60;
const PASSWORD_MIN_LENGTH = 10;

function verify_credentials(?array $userRow, string $password): bool
{
    if ($userRow === null) {
        // check against a real hash anyway (result ignored): the response time must not reveal
        // which usernames exist
        $any = load_collection('admin_users')[0]['password_hash'] ?? null;
        if (is_string($any)) {
            password_verify($password, $any);
        }
        return false;
    }
    return password_verify($password, $userRow['password_hash']);
}

/** Short fingerprint of the stored password hash: a password change ends the user's other sessions. */
function password_fingerprint(array $user): string
{
    return substr(hash('sha256', (string)($user['password_hash'] ?? '')), 0, 16);
}

/** The session still belongs to an existing admin whose password has not changed since login. */
function session_matches_user(array $session, ?array $user): bool
{
    return $user !== null
        && (int)$user['id'] === (int)($session['admin_id'] ?? 0)
        && hash_equals(password_fingerprint($user), (string)($session['pw'] ?? ''));
}

function find_admin_in(array $users, string $username): ?array
{
    foreach ($users as $user) {
        if (($user['username'] ?? null) === $username) {
            return $user;
        }
    }
    return null;
}

function find_admin_by_username(string $username): ?array
{
    return find_admin_in(load_collection('admin_users'), $username);
}

function password_problem(string $password): ?string
{
    if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Parolei jābūt vismaz ' . PASSWORD_MIN_LENGTH . ' rakstzīmes garai.';
    }
    if (!preg_match('/\p{L}/u', $password) || !preg_match('/\d/', $password)) {
        return 'Parolē jābūt gan burtiem, gan cipariem.';
    }
    return null;
}

/** Creates the user or replaces the password; returns an error message or null. */
function set_admin_password(string $username, string $password, ?string $dir = null): ?string
{
    if (!preg_match('/^[\w.@-]{3,50}$/u', $username)) {
        return 'Lietotājvārds: 3–50 burti, cipari vai . _ - @';
    }
    if ($problem = password_problem($password)) {
        return $problem;
    }
    $users = load_collection('admin_users', $dir);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $found = false;
    foreach ($users as &$user) {
        if ($user['username'] === $username) {
            $user['password_hash'] = $hash;
            $found = true;
        }
    }
    unset($user);
    if (!$found) {
        $users[] = ['id' => next_id($users), 'username' => $username, 'password_hash' => $hash];
    }
    save_collection('admin_users', $users, $dir);
    return null;
}

// --- session ---------------------------------------------------------------------

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function admin_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
}

function start_admin_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    admin_security_headers();
    session_name('gehwol_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function log_in(array $user): void
{
    start_admin_session();
    session_regenerate_id(true);
    $_SESSION = [
        'admin_id' => $user['id'],
        'admin_username' => $user['username'],
        'pw' => password_fingerprint($user),
        'last_activity' => time(),
    ];
}

function log_out(): void
{
    start_admin_session();
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600] + array_intersect_key($params, array_flip(['path', 'domain', 'secure', 'httponly', 'samesite'])));
    session_destroy();
}

function require_login(): void
{
    start_admin_session();
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
    if (time() - (int)($_SESSION['last_activity'] ?? 0) > SESSION_IDLE_SECONDS
        || !session_matches_user($_SESSION, find_admin_by_username(current_admin_username()))) {
        log_out();
        header('Location: login.php?expired=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function current_admin_username(): string
{
    return (string)($_SESSION['admin_username'] ?? '');
}

// --- CSRF --------------------------------------------------------------------------

function csrf_token(): string
{
    start_admin_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

/** State changes are accepted only as POST with a valid token. */
function require_csrf(): void
{
    $given = $_POST['csrf'] ?? '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_string($given) || !hash_equals(csrf_token(), $given)) {
        http_response_code(403);
        exit('Nederīgs pieprasījums. Atjaunojiet lapu un mēģiniet vēlreiz.');
    }
}

// --- login throttling --------------------------------------------------------------
// Failed logins are counted per client IP in php/data/login_attempts.json (no backups: it is not content).

function login_attempts_path(?string $dir = null): string
{
    return storage_dir($dir) . '/login_attempts.json';
}

function login_attempts_update(callable $change, ?string $dir = null): array
{
    $path = login_attempts_path($dir);
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        return [];
    }
    flock($handle, LOCK_EX);
    $all = json_decode((string)stream_get_contents($handle), true);
    $all = is_array($all) ? $all : [];
    $now = time();
    foreach ($all as $key => $entry) {
        if (($entry['locked_until'] ?? 0) < $now && ($entry['first'] ?? 0) < $now - LOGIN_WINDOW_SECONDS) {
            unset($all[$key]);
        }
    }
    $all = $change($all, $now);
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($all));
    flock($handle, LOCK_UN);
    fclose($handle);
    return $all;
}

function login_key(string $ip): string
{
    return hash('sha256', 'gehwol-login|' . $ip);
}

/** Seconds until the client may try again, 0 when not locked. */
function login_locked_for(string $ip, ?string $dir = null): int
{
    $entry = login_attempts_update(fn($all) => $all, $dir)[login_key($ip)] ?? null;
    return max(0, (int)($entry['locked_until'] ?? 0) - time());
}

function login_record_failure(string $ip, ?string $dir = null): void
{
    $key = login_key($ip);
    login_attempts_update(function (array $all, int $now) use ($key) {
        $entry = $all[$key] ?? ['count' => 0, 'first' => $now, 'locked_until' => 0];
        $entry['count']++;
        if ($entry['count'] >= LOGIN_MAX_FAILURES) {
            $entry = ['count' => 0, 'first' => $now, 'locked_until' => $now + LOGIN_LOCK_SECONDS];
        }
        $all[$key] = $entry;
        return $all;
    }, $dir);
}

function login_clear_failures(string $ip, ?string $dir = null): void
{
    $key = login_key($ip);
    login_attempts_update(function (array $all) use ($key) {
        unset($all[$key]);
        return $all;
    }, $dir);
}

/** Security-relevant admin events go to the PHP error log (never passwords, tokens or session data). */
function admin_log(string $event): void
{
    error_log(sprintf('[gehwol-admin] %s user=%s ip=%s', $event, current_admin_username() ?: '-', $_SERVER['REMOTE_ADDR'] ?? '-'));
}
