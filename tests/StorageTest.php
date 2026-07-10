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
        foreach (glob($this->dir . '/*.json') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->dir);
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

    public function test_load_collection_returns_empty_array_for_corrupt_json(): void
    {
        file_put_contents($this->dir . '/broken.json', '{not json');
        $this->assertSame([], load_collection('broken', $this->dir));
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
