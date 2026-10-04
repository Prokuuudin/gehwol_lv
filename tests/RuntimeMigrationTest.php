<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/includes/runtime-migrations.php';

final class RuntimeMigrationTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/runtime_migration_' . bin2hex(random_bytes(8));
        mkdir($this->dir, 0777, true);
        putenv('GEHWOL_DATA_DIR=' . $this->dir);

        save_collection('articles', [
            ['id' => 3, 'title' => 'Testa raksts'],
            ['id' => 6, 'title' => 'Sausas pēdu ādas kopšana', 'text' => 'first', 'image' => 'uploads/articles/first.jpg'],
            ['id' => 7, 'title' => 'Terapeitiskā enerģijā pārvērstais gaiss', 'text' => 'second'],
        ], $this->dir);
        save_collection('news', [['id' => 4, 'title' => 'Testa jaunums']], $this->dir);
        file_put_contents($this->dir . '/id_counters.json', json_encode(['products' => 99, 'articles' => 7, 'news' => 4]));
    }

    protected function tearDown(): void
    {
        putenv('GEHWOL_DATA_DIR');
        $this->removeTree($this->dir);
    }

    public function test_migration_keeps_real_articles_and_resets_ids_once(): void
    {
        self::assertTrue(migrate_text_content_ids_v2());

        $articles = load_collection('articles', $this->dir);
        self::assertSame([1, 2], array_column($articles, 'id'));
        self::assertSame(['first', 'second'], array_column($articles, 'text'));
        self::assertSame('uploads/articles/first.jpg', $articles[0]['image']);
        self::assertSame([], load_collection('news', $this->dir));
        self::assertSame(
            ['products' => 99, 'articles' => 2, 'news' => 0],
            json_decode((string)file_get_contents($this->dir . '/id_counters.json'), true, 512, JSON_THROW_ON_ERROR)
        );
        self::assertNotEmpty(list_backups('articles', $this->dir));
        self::assertNotEmpty(list_backups('news', $this->dir));

        save_collection('news', [['id' => 1, 'title' => 'Jauns materiāls']], $this->dir);
        self::assertFalse(migrate_text_content_ids_v2());
        self::assertCount(1, load_collection('news', $this->dir));
    }

    private function removeTree(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $name) {
            if ($name !== '.' && $name !== '..') {
                $this->removeTree($path . DIRECTORY_SEPARATOR . $name);
            }
        }
        @rmdir($path);
    }
}
