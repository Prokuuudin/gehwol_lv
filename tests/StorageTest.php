<?php
// tests/StorageTest.php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/includes/storage.php';

final class StorageTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/storage_test_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->dir);
    }

    private function removeTree(string $path): void
    {
        if (is_dir($path)) {
            foreach (scandir($path) as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->removeTree($path . '/' . $entry);
                }
            }
            rmdir($path);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }

    public function test_load_collection_returns_empty_array_for_missing_file(): void
    {
        $this->assertSame([], load_collection('products', $this->dir));
    }

    public function test_save_then_load_round_trips_rows_with_unicode(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Kosmētika', 'link_url' => null, 'sort_order' => 1],
            ['id' => 2, 'name' => 'Plāksteri', 'link_url' => 'plaksteri.html', 'sort_order' => 2],
        ];
        save_collection('categories', $rows, $this->dir);
        $this->assertSame($rows, load_collection('categories', $this->dir));
    }

    public function test_save_collection_writes_readable_unescaped_json(): void
    {
        save_collection('categories', [['id' => 1, 'name' => 'Kosmētika']], $this->dir);
        $raw = file_get_contents($this->dir . '/categories.json');
        $this->assertStringContainsString('Kosmētika', $raw);
    }

    public function test_corrupt_json_throws_instead_of_returning_empty(): void
    {
        file_put_contents($this->dir . '/broken.json', '{not json');
        $this->expectException(StorageException::class);
        load_collection('broken', $this->dir);
    }

    public function test_json_object_instead_of_list_is_corrupt(): void
    {
        file_put_contents($this->dir . '/broken.json', '{"id": 1}');
        $this->expectException(StorageException::class);
        load_collection('broken', $this->dir);
    }

    public function test_invalid_collection_name_is_rejected(): void
    {
        $this->expectException(StorageException::class);
        load_collection('../secret', $this->dir);
    }

    public function test_save_leaves_no_temp_or_partial_files(): void
    {
        save_collection('products', [['id' => 1]], $this->dir);
        save_collection('products', [['id' => 2]], $this->dir);
        $this->assertSame([], glob($this->dir . '/*.tmp'));
        $this->assertSame([['id' => 2]], load_collection('products', $this->dir));
    }

    public function test_save_backs_up_previous_version(): void
    {
        save_collection('products', [['id' => 1]], $this->dir);
        $this->assertSame([], list_backups('products', $this->dir), 'first save has nothing to back up');

        save_collection('products', [['id' => 2]], $this->dir);
        $backups = list_backups('products', $this->dir);
        $this->assertCount(1, $backups);
        $this->assertSame([['id' => 1]], json_decode(file_get_contents($this->dir . '/backups/' . $backups[0]), true));
    }

    public function test_backups_are_limited_to_newest(): void
    {
        for ($i = 1; $i <= STORAGE_KEEP_BACKUPS + 5; $i++) {
            save_collection('news', [['id' => $i]], $this->dir);
        }
        $backups = list_backups('news', $this->dir);
        $this->assertCount(STORAGE_KEEP_BACKUPS, $backups);
        $newest = json_decode(file_get_contents($this->dir . '/backups/' . $backups[0]), true);
        $this->assertSame([['id' => STORAGE_KEEP_BACKUPS + 4]], $newest);
    }

    public function test_unencodable_rows_throw_and_keep_existing_data(): void
    {
        save_collection('products', [['id' => 1, 'name' => 'OK']], $this->dir);
        try {
            save_collection('products', [['id' => 2, 'name' => "\xB1\x31"]], $this->dir);
            $this->fail('expected StorageException');
        } catch (StorageException) {
        }
        $this->assertSame([['id' => 1, 'name' => 'OK']], load_collection('products', $this->dir));
    }

    public function test_failed_backup_aborts_save_and_keeps_existing_data(): void
    {
        save_collection('products', [['id' => 1]], $this->dir);
        file_put_contents($this->dir . '/backups', 'not a directory');
        try {
            save_collection('products', [['id' => 2]], $this->dir);
            $this->fail('expected StorageException');
        } catch (StorageException) {
        }
        $this->assertSame([['id' => 1]], load_collection('products', $this->dir));
        $this->assertSame([], glob($this->dir . '/*.tmp'));
    }

    public function test_save_into_missing_directory_throws(): void
    {
        $this->expectException(StorageException::class);
        save_collection('products', [['id' => 1]], $this->dir . '/missing');
    }

    public function test_restore_backup_brings_back_previous_version(): void
    {
        save_collection('articles', [['id' => 1, 'title' => 'Vecais']], $this->dir);
        save_collection('articles', [['id' => 1, 'title' => 'Jaunais']], $this->dir);
        $backup = list_backups('articles', $this->dir)[0];

        restore_backup('articles', $backup, $this->dir);

        $this->assertSame([['id' => 1, 'title' => 'Vecais']], load_collection('articles', $this->dir));
        $this->assertCount(2, list_backups('articles', $this->dir), 'restore backs up the replaced version');
    }

    public function test_restore_rejects_unknown_backup_name(): void
    {
        $this->expectException(StorageException::class);
        restore_backup('articles', '../products.json', $this->dir);
    }

    public function test_concurrent_saves_never_corrupt_the_file(): void
    {
        $child = $this->dir . '/child.php';
        file_put_contents($child, sprintf(
            '<?php require %s; for ($i = 0; $i < 30; $i++) { save_collection("products", array_fill(0, 200, ["id" => $i, "w" => $argv[1]]), %s); }',
            var_export(realpath(__DIR__ . '/../php/includes/storage.php'), true),
            var_export($this->dir, true)
        ));
        $procs = [];
        foreach (['a', 'b', 'c'] as $worker) {
            $procs[] = proc_open([PHP_BINARY, $child, $worker], [], $pipes);
        }
        foreach ($procs as $proc) {
            $this->assertSame(0, proc_close($proc));
        }
        $rows = load_collection('products', $this->dir);
        $this->assertCount(200, $rows);
        $this->assertCount(1, array_unique(array_column($rows, 'w')), 'file holds one complete write');
        $this->assertSame([], glob($this->dir . '/*.tmp'));
    }

    public function test_storage_problems_reports_missing_directory(): void
    {
        $this->assertSame([], storage_problems($this->dir));
        $this->assertNotEmpty(storage_problems($this->dir . '/missing'));
    }

    public function test_allocate_id_never_reuses_ids_of_deleted_records(): void
    {
        $this->assertSame(8, allocate_id('products', [['id' => 3], ['id' => 7]], $this->dir), 'starts after existing rows');
        // record 8 is deleted again: the next record must not get id 8
        $this->assertSame(9, allocate_id('products', [['id' => 3], ['id' => 7]], $this->dir));
        $this->assertSame(1, allocate_id('categories', [], $this->dir), 'counters are per collection');
        $this->assertSame(21, allocate_id('products', [['id' => 20]], $this->dir), 'existing rows win over a lower counter');
    }

    public function test_new_news_and_articles_never_get_ids_of_old_static_pages(): void
    {
        // the text id migration left these counters on the live server
        file_put_contents($this->dir . '/id_counters.json', json_encode(['articles' => 2, 'news' => 0]));
        $this->assertSame(8, allocate_id('articles', [['id' => 1], ['id' => 2]], $this->dir));
        $this->assertSame(6, allocate_id('news', [], $this->dir));
        $this->assertSame(7, allocate_id('news', [], $this->dir));
    }

    public function test_allocate_id_rejects_invalid_collection(): void
    {
        $this->expectException(StorageException::class);
        allocate_id('../x', [], $this->dir);
    }

    public function test_next_id_starts_at_one(): void
    {
        $this->assertSame(1, next_id([]));
    }

    public function test_next_id_is_max_plus_one(): void
    {
        $rows = [['id' => 3], ['id' => 7], ['id' => 2]];
        $this->assertSame(8, next_id($rows));
    }

    public function test_sort_rows_orders_by_sort_order_then_id(): void
    {
        $rows = [
            ['id' => 5, 'sort_order' => 2],
            ['id' => 9, 'sort_order' => 1],
            ['id' => 3, 'sort_order' => 2],
        ];
        $sorted = sort_rows($rows);
        $this->assertSame([9, 3, 5], array_column($sorted, 'id'));
    }
}
