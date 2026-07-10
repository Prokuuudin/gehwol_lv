<?php
// tests/AuthTest.php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/includes/auth.php';

final class AuthTest extends TestCase
{
    public function test_verify_credentials_accepts_correct_password(): void
    {
        $row = ['id' => 1, 'username' => 'admin', 'password_hash' => password_hash('secret123', PASSWORD_DEFAULT)];
        $this->assertTrue(verify_credentials($row, 'secret123'));
    }

    public function test_verify_credentials_rejects_wrong_password(): void
    {
        $row = ['id' => 1, 'username' => 'admin', 'password_hash' => password_hash('secret123', PASSWORD_DEFAULT)];
        $this->assertFalse(verify_credentials($row, 'wrong'));
    }

    public function test_verify_credentials_rejects_missing_user(): void
    {
        $this->assertFalse(verify_credentials(null, 'anything'));
    }

    public function test_find_admin_in_returns_matching_user(): void
    {
        $users = [
            ['id' => 1, 'username' => 'admin', 'password_hash' => 'x'],
            ['id' => 2, 'username' => 'other', 'password_hash' => 'y'],
        ];
        $found = find_admin_in($users, 'other');
        $this->assertSame(2, $found['id']);
    }

    public function test_find_admin_in_returns_null_when_absent(): void
    {
        $this->assertNull(find_admin_in([['id' => 1, 'username' => 'admin', 'password_hash' => 'x']], 'nobody'));
        $this->assertNull(find_admin_in([], 'admin'));
    }
}
