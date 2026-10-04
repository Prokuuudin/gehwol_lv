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
    }

    protected function tearDown(): void
    {
        putenv('GEHWOL_DATA_DIR');
        $this->removeTree($this->dir);
    }

    public function test_product_grid_moves_from_article_text_to_fields_once(): void
    {
        $grid = '<h2>GEHWOL MED® kāju kopšanas produkti</h2><div class="category__grid">'
            . '<a href="produkts-23.html" class="product-card"><div class="product-card__media"> <img src="./img/a.png" alt="A"></div>'
            . '<h3 class="product-card__title">A</h3><span class="product-card__cta btn-link">Uzzināt vairāk →</span></a>'
            . '<a href="produkts-19.html" class="product-card"><div class="product-card__media"> <img src="./img/b.png" alt="B"></div>'
            . '<h3 class="product-card__title">B</h3><span class="product-card__cta btn-link">Uzzināt vairāk →</span></a></div>';
        save_collection('articles', [
            ['id' => 1, 'title' => 'Ar produktiem', 'text' => '<p>Teksts</p>' . $grid],
            ['id' => 2, 'title' => 'Bez produktiem', 'text' => '<p>Cits</p>'],
        ], $this->dir);

        self::assertTrue(migrate_article_products_v1());

        [$first, $second] = load_collection('articles', $this->dir);
        self::assertSame('<p>Teksts</p>', $first['text']);
        self::assertSame([23, 19], $first['products']);
        self::assertSame('GEHWOL MED® kāju kopšanas produkti', $first['products_title']);
        self::assertSame(['id' => 2, 'title' => 'Bez produktiem', 'text' => '<p>Cits</p>'], $second);

        save_collection('articles', [['id' => 1, 'title' => 'X', 'text' => '<p>Teksts</p>' . $grid]], $this->dir);
        self::assertFalse(migrate_article_products_v1());
        self::assertStringContainsString('category__grid', load_collection('articles', $this->dir)[0]['text']);
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
