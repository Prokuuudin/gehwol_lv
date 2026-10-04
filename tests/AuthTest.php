<?php
// tests/AuthTest.php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/includes/auth.php';

final class AuthTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/auth_test_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (array_merge(glob($this->dir . '/backups/*') ?: [], glob($this->dir . '/*.*') ?: []) as $f) {
            unlink($f);
        }
        @rmdir($this->dir . '/backups');
        rmdir($this->dir);
    }

    public function test_verify_credentials_accepts_correct_password(): void
    {
        $row = ['id' => 1, 'username' => 'admin', 'password_hash' => password_hash('secret123', PASSWORD_DEFAULT)];
        $this->assertTrue(verify_credentials($row, 'secret123'));
    }

    public function test_verify_credentials_rejects_wrong_password_and_missing_user(): void
    {
        $row = ['id' => 1, 'username' => 'admin', 'password_hash' => password_hash('secret123', PASSWORD_DEFAULT)];
        $this->assertFalse(verify_credentials($row, 'wrong'));
        $this->assertFalse(verify_credentials(null, 'anything'));
    }

    public function test_session_ends_when_password_changes_or_user_is_gone(): void
    {
        $user = ['id' => 1, 'username' => 'admin', 'password_hash' => password_hash('secret123', PASSWORD_DEFAULT)];
        $session = ['admin_id' => 1, 'admin_username' => 'admin', 'pw' => password_fingerprint($user)];
        $this->assertTrue(session_matches_user($session, $user));

        $changed = ['password_hash' => password_hash('other12345', PASSWORD_DEFAULT)] + $user;
        $this->assertFalse(session_matches_user($session, $changed), 'password changed on another device');
        $this->assertFalse(session_matches_user($session, null), 'user removed');
        $this->assertFalse(session_matches_user(['pw' => ''] + $session, $user), 'session from before this check');
    }

    public function test_find_admin_in(): void
    {
        $users = [['id' => 1, 'username' => 'admin', 'password_hash' => 'x'], ['id' => 2, 'username' => 'other', 'password_hash' => 'y']];
        $this->assertSame(2, find_admin_in($users, 'other')['id']);
        $this->assertNull(find_admin_in($users, 'nobody'));
        $this->assertNull(find_admin_in([], 'admin'));
    }

    public function test_password_rules(): void
    {
        $this->assertNotNull(password_problem('short1'));
        $this->assertNotNull(password_problem('onlyletterslong'));
        $this->assertNotNull(password_problem('1234567890123'));
        $this->assertNull(password_problem('Gehwol2026pēdas'));
    }

    public function test_set_admin_password_creates_then_updates_user(): void
    {
        $this->assertNull(set_admin_password('inese', 'PirmaParole1', $this->dir));
        $this->assertNull(set_admin_password('inese', 'OtraParole22', $this->dir));
        $users = load_collection('admin_users', $this->dir);
        $this->assertCount(1, $users);
        $this->assertTrue(verify_credentials($users[0], 'OtraParole22'));
        $this->assertStringNotContainsString('OtraParole22', file_get_contents($this->dir . '/admin_users.json'));
    }

    public function test_set_admin_password_rejects_weak_password_and_bad_username(): void
    {
        $this->assertNotNull(set_admin_password('inese', 'vaja', $this->dir));
        $this->assertNotNull(set_admin_password('a b', 'LabaParole123', $this->dir));
        $this->assertSame([], load_collection('admin_users', $this->dir));
    }

    public function test_login_is_locked_after_repeated_failures(): void
    {
        for ($i = 1; $i < LOGIN_MAX_FAILURES; $i++) {
            login_record_failure('10.0.0.1', $this->dir);
            $this->assertSame(0, login_locked_for('10.0.0.1', $this->dir), "attempt {$i}");
        }
        login_record_failure('10.0.0.1', $this->dir);
        $this->assertGreaterThan(LOGIN_LOCK_SECONDS - 5, login_locked_for('10.0.0.1', $this->dir));
        $this->assertSame(0, login_locked_for('10.0.0.2', $this->dir), 'other clients are not affected');
        $this->assertStringNotContainsString('10.0.0.1', file_get_contents($this->dir . '/login_attempts.json'));
    }

    public function test_successful_login_clears_failures(): void
    {
        for ($i = 1; $i < LOGIN_MAX_FAILURES; $i++) {
            login_record_failure('10.0.0.3', $this->dir);
        }
        login_clear_failures('10.0.0.3', $this->dir);
        login_record_failure('10.0.0.3', $this->dir);
        $this->assertSame(0, login_locked_for('10.0.0.3', $this->dir));
    }
}
