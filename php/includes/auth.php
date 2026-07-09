<?php
// php/includes/auth.php

require_once __DIR__ . '/db.php';

function verify_credentials(?array $userRow, string $password): bool
{
    if ($userRow === null) {
        return false;
    }
    return password_verify($password, $userRow['password_hash']);
}

function find_admin_by_username(string $username): ?array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function require_login(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}
