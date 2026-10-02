<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/admin/includes/deletion.php';

final class DeletionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/deletion_test_' . uniqid();
        mkdir($this->dir, 0777, true);
        putenv('GEHWOL_DATA_DIR=' . $this->dir);
        start_admin_session();
        unset($_SESSION['recent_deletion']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['recent_deletion']);
        putenv('GEHWOL_DATA_DIR');
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
    }

    public function test_deleted_row_can_be_restored_with_its_original_id(): void
    {
        save_collection('products', [['id' => 2, 'name' => 'Other']], $this->dir);
        $deleted = ['id' => 7, 'name' => 'Foot cream', 'images' => []];
        $token = remember_recent_deletion('products', 'products.php', $deleted);

        $this->assertSame($deleted, restore_recent_deletion('products', $token));
        $this->assertSame([2, 7], array_column(load_collection('products', $this->dir), 'id'));
        $this->assertNull(recent_deletion());
    }

    public function test_wrong_token_does_not_restore_or_consume_the_undo_entry(): void
    {
        save_collection('news', [], $this->dir);
        $token = remember_recent_deletion('news', 'news.php', ['id' => 4, 'title' => 'News']);

        $this->assertNull(restore_recent_deletion('news', 'wrong-token'));
        $this->assertSame([], load_collection('news', $this->dir));
        $this->assertSame($token, recent_deletion()['token']);
    }

    public function test_expired_deletion_cannot_be_restored(): void
    {
        save_collection('articles', [], $this->dir);
        $token = remember_recent_deletion('articles', 'articles.php', ['id' => 5, 'title' => 'Article']);
        $_SESSION['recent_deletion']['expires_at'] = time() - 1;

        $this->assertNull(restore_recent_deletion('articles', $token));
        $this->assertSame([], load_collection('articles', $this->dir));
        $this->assertArrayNotHasKey('recent_deletion', $_SESSION);
    }

    public function test_existing_id_is_never_overwritten(): void
    {
        save_collection('products', [['id' => 7, 'name' => 'Current']], $this->dir);
        $token = remember_recent_deletion('products', 'products.php', ['id' => 7, 'name' => 'Deleted']);

        $this->assertNull(restore_recent_deletion('products', $token));
        $this->assertSame('Current', load_collection('products', $this->dir)[0]['name']);
    }

    public function test_images_are_kept_during_undo_window_and_cleaned_after_expiry(): void
    {
        $hash = str_repeat('a', 32);
        $path = "uploads/products/{$hash}.jpg";
        $uploadRoot = $this->dir . '/uploads';
        mkdir($uploadRoot . '/products', 0777, true);
        touch($uploadRoot . "/products/{$hash}.jpg");
        touch($uploadRoot . "/products/{$hash}.webp");
        save_collection('products', [], $this->dir);

        remember_recent_deletion('products', 'products.php', ['id' => 7, 'images' => [$path]], [$path]);
        $this->assertFileExists($uploadRoot . "/products/{$hash}.jpg");
        $this->assertFileExists($uploadRoot . "/products/{$hash}.webp");

        $_SESSION['recent_deletion']['expires_at'] = time() - 1;
        $this->assertNull(recent_deletion($uploadRoot));
        $this->assertFileDoesNotExist($uploadRoot . "/products/{$hash}.jpg");
        $this->assertFileDoesNotExist($uploadRoot . "/products/{$hash}.webp");
    }
}
