<?php
// tests/RenderTest.php — public pages from a temporary data folder and the built templates.
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/includes/render.php';

final class RenderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (!is_file(TEMPLATE_DIR . '/_shell.html')) {
            $this->markTestSkipped('php/templates not built (run npx gulp build:docs)');
        }
        $this->dir = sys_get_temp_dir() . '/render_test_' . uniqid();
        mkdir($this->dir);
        putenv('GEHWOL_DATA_DIR=' . $this->dir);
        putenv('GEHWOL_PUBLIC_DIR=' . realpath(__DIR__ . '/../docs'));

        copy(__DIR__ . '/../php/data/categories.json', $this->dir . '/categories.json');
        $product = fn(int $id, bool $published, array $extra = []) => $extra + [
            'id' => $id, 'category_id' => 2, 'name' => "Produkts {$id}", 'subtitle' => 'Apakšvirsraksts',
            'description' => '<p>Apraksts</p>', 'active_ingredients' => '', 'images' => [], 'sort_order' => $id,
            'published' => $published, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-02-03 10:00:00',
        ];
        save_collection('products', [
            $product(1, true),
            $product(2, false),
            $product(3, true, ['name' => 'Ļauns <script>alert(1)</script>', 'description' => '<p onclick="x()">A</p><script>alert(2)</script>']),
        ]);
        $text = fn(int $id, string $title, bool $published, ?string $date = null) => [
            'id' => $id, 'title' => $title, 'date' => $date, 'text' => '<p>Teksts</p>', 'image' => null,
            'sort_order' => $id, 'published' => $published, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ];
        save_collection('news', [
            $text(1, 'Vecs jaunums', true, '2026-01-10'),
            $text(2, 'Jauns jaunums', true, '2026-05-01'),
            $text(3, 'Melnraksta jaunums', false, '2026-06-01'),
        ]);
        save_collection('articles', [$text(1, 'Raksts viens', true), $text(2, 'Raksta melnraksts', false)]);
    }

    protected function tearDown(): void
    {
        putenv('GEHWOL_DATA_DIR');
        if (isset($this->dir)) {
            array_map('unlink', glob($this->dir . '/*.*') ?: []);
            array_map('unlink', glob($this->dir . '/backups/*') ?: []);
            @rmdir($this->dir . '/backups');
            rmdir($this->dir);
        }
    }

    public function test_published_product_page(): void
    {
        [$status, , $html] = site_response('/produkts-1.html');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('<h1 class="category__title">Produkts 1</h1>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://gehwol.lv/produkts-1.html">', $html);
        $this->assertStringContainsString('href="gehwol-classic.html" class="category__crumb"', $html);
        $this->assertStringNotContainsString('<x-slot', $html);
    }

    public function test_draft_and_unknown_items_are_404(): void
    {
        foreach (['/produkts-2.html', '/produkts-999.html', '/jaunums-3.html', '/raksts-2.html', '/nav-tadas.html', '/produkts-01.html'] as $path) {
            [$status, , $html] = site_response($path);
            $this->assertSame(404, $status, $path);
            $this->assertStringContainsString('<meta name="robots" content="noindex">', $html, $path);
        }
    }

    public function test_admin_preview_renders_drafts(): void
    {
        $this->assertNull(render_product(2));
        $this->assertStringContainsString('<h1 class="category__title">Produkts 2</h1>', render_product(2, true));
        $this->assertStringContainsString('Melnraksta jaunums', render_text_page('news', 3, true));
        $this->assertNull(render_product(999, true));
    }

    public function test_user_content_is_escaped_and_sanitized(): void
    {
        [, , $html] = site_response('/produkts-3.html');
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringContainsString('Ļauns &lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_category_lists_only_published_products(): void
    {
        [$status, , $html] = site_response('/gehwol-classic.html');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('href="produkts-1.html"', $html);
        $this->assertStringNotContainsString('href="produkts-2.html"', $html);
    }

    public function test_empty_category_shows_message(): void
    {
        [, , $html] = site_response('/gehwol-med.html');
        $this->assertStringContainsString('Šajā kategorijā vēl nav produktu.', $html);
    }

    public function test_home_shows_published_news_newest_first(): void
    {
        [$status, , $html] = site_response('/');
        $this->assertSame(200, $status);
        $this->assertLessThan(strpos($html, 'Vecs jaunums'), strpos($html, 'Jauns jaunums'));
        $this->assertStringNotContainsString('Melnraksta jaunums', $html);
        $this->assertStringContainsString('href="raksts-1.html"', $html);
        $this->assertStringNotContainsString('Raksta melnraksts', $html);
    }

    public function test_news_page_shows_date(): void
    {
        [, , $html] = site_response('/jaunums-2.html');
        $this->assertStringContainsString('<p class="content-detail__meta">01.05.2026</p>', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
    }

    public function test_news_and_articles_have_article_structured_data(): void
    {
        [, , $html] = site_response('/jaunums-2.html');
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);
        $types = array_column(json_decode($m[1], true)['@graph'], null, '@type');
        $this->assertSame('Jauns jaunums', $types['Article']['headline']);
        $this->assertSame('2026-05-01', $types['Article']['datePublished']);
        $this->assertSame('2026-05-01', $types['Article']['dateModified'], 'never before the publication date');

        [, , $product] = site_response('/produkts-1.html');
        $this->assertStringNotContainsString('"Article"', $product);
    }

    public function test_sitemap_lists_published_content_only(): void
    {
        [$status, $type, $xml] = site_response('/sitemap.xml');
        $this->assertSame(200, $status);
        $this->assertStringStartsWith('application/xml', $type);
        $this->assertStringContainsString('<loc>https://gehwol.lv/produkts-1.html</loc><lastmod>2026-02-03</lastmod>', $xml);
        $this->assertStringContainsString('<loc>https://gehwol.lv/rekviziti.html</loc>', $xml);
        $this->assertStringContainsString('<loc>https://gehwol.lv/gehwol-classic.html</loc>', $xml);
        $this->assertStringNotContainsString('produkts-2.html', $xml);
        $this->assertStringNotContainsString('jaunums-3.html', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_missing_slot_is_an_error(): void
    {
        $this->expectException(RuntimeException::class);
        fill_slot('<p></p>', 'main', 'x');
    }
}
