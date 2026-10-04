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
        $this->assertMatchesRegularExpression('~href="\./css/main\.css\?v=\d+"~', $html);
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

    public function test_article_shows_selected_published_products_in_order(): void
    {
        $articles = load_collection('articles');
        $articles[0]['products'] = [3, 2, 1, 99];
        $articles[0]['products_title'] = 'Ieteicamie produkti';
        save_collection('articles', $articles);

        [, , $html] = site_response('/raksts-1.html');
        $this->assertStringContainsString('<h2>Ieteicamie produkti</h2><div class="category__grid">', $html);
        $this->assertStringContainsString('<h3 class="product-card__title">Produkts 1</h3>', $html);
        $this->assertLessThan(strpos($html, 'href="produkts-1.html"'), strpos($html, 'href="produkts-3.html"'));
        $this->assertStringNotContainsString('produkts-2.html', $html, 'drafts are skipped');
        $this->assertStringNotContainsString('produkts-99.html', $html, 'missing products are skipped');
    }

    public function test_article_without_products_has_no_product_grid(): void
    {
        [, , $html] = site_response('/raksts-1.html');
        $this->assertStringNotContainsString('category__grid', $html);
    }

    public function test_old_article_urls_redirect_to_normalized_ids(): void
    {
        $articles = load_collection('articles');
        foreach ($articles as &$article) {
            if ((int) $article['id'] === 2) {
                $article['published'] = true;
            }
        }
        unset($article);
        foreach ([6, 7] as $legacyId) {
            $articles[] = [
                'id' => $legacyId,
                'title' => 'Vecā adrese migrācijas laikā',
                'text' => '<p>Saturs</p>',
                'image' => null,
                'sort_order' => $legacyId,
                'published' => true,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ];
        }
        save_collection('articles', $articles, $this->dir);

        [$firstStatus, , , $firstHeaders] = site_response('/raksts-6.html');
        [$secondStatus, , , $secondHeaders] = site_response('/raksts-7.html');

        $this->assertSame(301, $firstStatus);
        $this->assertSame('/raksts-1.html', $firstHeaders['Location']);
        $this->assertSame(301, $secondStatus);
        $this->assertSame('/raksts-2.html', $secondHeaders['Location']);
    }

    public function test_sitemap_lists_published_content_only(): void
    {
        [$status, $type, $xml] = site_response('/sitemap.xml');
        $this->assertSame(200, $status);
        $this->assertStringStartsWith('application/xml', $type);
        $this->assertStringContainsString('<loc>https://gehwol.lv/produkts-1.html</loc><lastmod>2026-02-03</lastmod>', $xml);
        $this->assertStringContainsString('<loc>https://gehwol.lv/rekviziti.html</loc>', $xml);
        $this->assertSame(1, substr_count($xml, '<loc>https://gehwol.lv/gehwol-classic.html</loc>'));
        $this->assertStringNotContainsString('_shell.html', $xml);
        $this->assertStringNotContainsString('produkts-2.html', $xml);
        $this->assertStringNotContainsString('jaunums-3.html', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_pages_show_built_in_partners_until_the_admin_saves_a_list(): void
    {
        foreach (['/', '/produkts-1.html', '/gehwol-classic.html', '/rekviziti.html', '/nav-tadas.html'] as $path) {
            [, , $html] = site_response($path);
            $this->assertStringContainsString('href="https://www.benu.lv/zimoli/gehwol"', $html, $path);
            $this->assertStringContainsString('<small>GEHWOL ražotājs</small>', $html, $path);
            $this->assertStringNotContainsString('<x-slot', $html, $path);
        }
    }

    public function test_legal_pages_are_rendered_from_templates(): void
    {
        [$status, , $html] = site_response('/rekviziti.html');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('<link rel="canonical" href="https://gehwol.lv/rekviziti.html">', $html);
        [$status] = site_response('/_shell.html');
        $this->assertSame(404, $status);
    }

    public function test_saved_partners_replace_the_built_in_list(): void
    {
        save_collection('partners', [
            ['group' => 'retailer', 'name' => 'Aptieka <b>', 'subtitle' => '', 'url' => 'https://aptieka.example.lv/?a=1&b=2'],
        ]);
        [, , $html] = site_response('/');
        $this->assertStringContainsString('href="https://aptieka.example.lv/?a=1&amp;b=2"', $html);
        $this->assertStringContainsString('<span>Aptieka &lt;b&gt;</span>', $html);
        $this->assertStringNotContainsString('benu.lv', $html);
        $this->assertStringNotContainsString('GEHWOL ražotājs</h2>', $html, 'empty group is hidden');

        save_collection('partners', []);
        [, , $html] = site_response('/');
        $this->assertStringNotContainsString('class="partners"', $html);
    }

    public function test_partner_form_rows(): void
    {
        [$rows, $errors] = partners_from_form([
            ['sort_order' => '2', 'group' => 'retailer', 'name' => ' B ', 'subtitle' => '', 'url' => 'https://b.lv/'],
            ['sort_order' => '1', 'group' => 'manufacturer', 'name' => 'A', 'subtitle' => 'Ražotājs', 'url' => 'https://a.de/'],
            ['sort_order' => '3', 'group' => 'retailer', 'name' => 'C', 'subtitle' => '', 'url' => 'https://c.lv/', 'remove' => '1'],
            ['sort_order' => '4', 'group' => 'hacker', 'name' => '', 'subtitle' => '', 'url' => ''],
        ]);
        $this->assertSame([], $errors);
        $this->assertSame(['A', 'B'], array_column($rows, 'name'));
        $this->assertSame(['manufacturer', 'retailer'], array_column($rows, 'group'));

        [, $errors] = partners_from_form([
            ['group' => 'retailer', 'name' => '', 'url' => 'https://x.lv/'],
            ['group' => 'retailer', 'name' => 'X', 'url' => 'javascript:alert(1)'],
            ['group' => 'retailer', 'name' => 'Y', 'url' => 'www.y.lv'],
        ]);
        $this->assertCount(3, $errors);
    }

    public function test_missing_slot_is_an_error(): void
    {
        $this->expectException(RuntimeException::class);
        fill_slot('<p></p>', 'main', 'x');
    }
}
