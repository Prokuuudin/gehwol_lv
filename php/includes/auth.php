<?php
// php/includes/auth.php

require_once __DIR__ . '/storage.php';

function verify_credentials(?array $userRow, string $password): bool
{
    if ($userRow === null) {
        return false;
    }
    return password_verify($password, $userRow['password_hash']);
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
