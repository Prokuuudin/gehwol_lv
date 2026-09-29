<?php
// tests/ContentTest.php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/includes/content.php';

final class ContentTest extends TestCase
{
    public function test_allowed_markup_is_kept(): void
    {
        $html = '<p><strong>Platums:</strong> 52 cm<br>Ā ē ī</p><h3 class="content-detail__subtitle">Virsraksts</h3>'
            . '<a href="produkts-19.html" class="product-card"><img src="./img/news-articles/x.png" alt="X"></a>';
        $this->assertSame($html, sanitize_html($html));
    }

    public function test_script_and_style_are_removed_with_content(): void
    {
        $this->assertSame('<p>ok</p>', sanitize_html('<p>ok</p><script>alert(1)</script><style>p{}</style>'));
    }

    public function test_event_handlers_and_style_attributes_are_removed(): void
    {
        $out = sanitize_html('<p onclick="alert(1)" style="color:red">x</p><img src="img/a.png" onerror="alert(1)" alt="a">');
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('onerror', $out);
        $this->assertStringNotContainsString('style', $out);
        $this->assertStringContainsString('src="img/a.png"', $out);
    }

    public function test_javascript_links_lose_href(): void
    {
        foreach (['javascript:alert(1)', ' javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html,x', '//evil.example/x'] as $href) {
            $out = sanitize_html('<a href="' . $href . '">x</a>');
            $this->assertStringNotContainsString('href', $out, $href);
        }
    }

    public function test_safe_links_are_kept(): void
    {
        foreach (['https://gehwol.lv/', 'mailto:versia@load.lv', 'tel:+37127055700', 'gehwol-classic.html', 'index.html#contacts', '#top'] as $href) {
            $this->assertStringContainsString('href="' . $href . '"', sanitize_html('<a href="' . $href . '">x</a>'), $href);
        }
    }

    public function test_images_from_outside_site_folders_are_dropped(): void
    {
        $this->assertSame('', sanitize_html('<img src="https://evil.example/a.png" alt="a">'));
        $this->assertSame('', sanitize_html('<img src="img/../../php/data/admin_users.json" alt="a">'));
    }

    public function test_unknown_tags_are_unwrapped_keeping_text(): void
    {
        $this->assertSame('<p>Teksts</p>', sanitize_html('<section><p><font>Teksts</font></p></section>'));
    }

    public function test_iframe_and_comments_are_removed(): void
    {
        $this->assertSame('<p>a</p>', sanitize_html('<p>a</p><!-- x --><iframe src="https://evil.example"></iframe>'));
    }

    public function test_plain_text_becomes_paragraphs(): void
    {
        $this->assertSame(
            "<p>Pirmā rindkopa<br>otrā rinda</p>\n<p>Otrā 5 &lt; 6 &amp; &quot;x&quot; rindkopa</p>",
            text_to_html("Pirmā rindkopa\r\notrā rinda\n\n\nOtrā 5 < 6 & \"x\" rindkopa")
        );
    }

    public function test_text_with_tags_is_sanitized_not_escaped(): void
    {
        $this->assertSame('<p><strong>Jā</strong></p>', text_to_html('<p><strong>Jā</strong></p><script>x</script>'));
    }

    public function test_text_with_inline_tags_still_gets_paragraphs(): void
    {
        $this->assertSame(
            "<p><strong>Platums:</strong> 52 cm<br>Augstums</p>\n<p>Otrā</p>",
            text_to_html("<strong>Platums:</strong> 52 cm\nAugstums\n\nOtrā<script>x</script>")
        );
    }

    public function test_simple_html_is_edited_as_plain_text_and_round_trips(): void
    {
        $html = "<p>Pirmā &amp; rindkopa<br>otrā rinda</p>\n<p>Otrā</p>";
        $this->assertSame("Pirmā & rindkopa\notrā rinda\n\nOtrā", html_to_editable_text($html));
        $this->assertSame($html, text_to_html(html_to_editable_text($html)));
    }

    public function test_rich_html_is_edited_as_html(): void
    {
        $html = '<p><strong>Platums:</strong> 52 cm</p>';
        $this->assertSame($html, html_to_editable_text($html));
        $this->assertSame('<p class="x">a</p>', html_to_editable_text('<p class="x">a</p>'));
    }

    public function test_all_stored_texts_survive_an_edit_without_changes(): void
    {
        $data = __DIR__ . '/../php/data';
        foreach (['products' => 'description', 'news' => 'text', 'articles' => 'text'] as $file => $field) {
            foreach (json_decode(file_get_contents("{$data}/{$file}.json"), true) as $row) {
                $again = text_to_html(html_to_editable_text($row[$field]));
                $norm = fn($h) => html_entity_decode(preg_replace('/>\s+</', '><', $h), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $this->assertSame(
                    $norm($row[$field]),
                    $norm($again),
                    "{$file} {$row['id']}"
                );
            }
        }
    }

    public function test_typography_matches_the_original_site_rules(): void
    {
        $nb = "\u{00A0}";
        $this->assertSame(
            "Krēsls ar{$nb}gāzes atsperēm{$nb}— 52{$nb}cm, 5{$nb}000 apgr., 28×12,5×28{$nb}cm, «Trendelenburga» slīpums",
            typography('Krēsls ar gāzes atsperēm - 52 cm, 5 000 apgr., 28 x 12,5 x 28 cm, "Trendelenburga" slīpums')
        );
    }

    public function test_typography_is_idempotent_and_leaves_markup_alone(): void
    {
        $once = typography_html('<p><a href="produkts-1.html" class="x y">Uz "lapu" - 10 x 20 mm</a></p>');
        $this->assertSame($once, typography_html($once));
        $this->assertStringContainsString('href="produkts-1.html" class="x y"', $once);
        $this->assertStringContainsString('«lapu»', $once);
        $this->assertSame("<p>Vārds «citāts»</p>", typography_html(text_to_html('Vārds "citāts"')));
    }

    public function test_typography_keeps_existing_texts_unchanged_where_they_were_typeset(): void
    {
        $data = json_decode(file_get_contents(__DIR__ . '/../php/data/products.json'), true);
        $unchanged = 0;
        foreach ($data as $p) {
            $unchanged += typography($p['subtitle']) === $p['subtitle'] ? 1 : 0;
        }
        $this->assertGreaterThan(count($data) * 0.8, $unchanged, 'most imported subtitles are already typeset');
    }

    public function test_empty_text_is_empty(): void
    {
        $this->assertSame('', text_to_html("  \n "));
        $this->assertSame('', sanitize_html(''));
    }

    public function test_dates_are_normalized(): void
    {
        $this->assertSame('2026-05-12', normalize_date('12.05.2026'));
        $this->assertSame('2026-05-12', normalize_date('2026-05-12'));
        $this->assertNull(normalize_date('31.02.2026'));
        $this->assertNull(normalize_date(''));
        $this->assertSame('12.05.2026', format_date_lv('2026-05-12'));
        $this->assertSame('', format_date_lv(null));
    }

    public function test_only_explicit_true_is_published(): void
    {
        $this->assertTrue(is_published(['published' => true]));
        $this->assertFalse(is_published(['published' => false]));
        $this->assertFalse(is_published(['published' => '1']));
        $this->assertFalse(is_published([]));
    }

    public function test_legacy_product_is_converted_without_losing_data(): void
    {
        $legacy = [
            'id' => 1, 'category_id' => 2, 'name' => 'GEHWOL Balsam',
            'description' => "Balzāms normālai ādai.\nMitrina & mīkstina.\nAktīvās vielas: Hohobas eļļa, mentols.",
            'image' => 'products/a.png', 'sort_order' => 3, 'created_at' => '2026-07-15 00:00:00',
        ];
        $product = product_from_legacy($legacy);
        $this->assertTrue(is_legacy_product($legacy));
        $this->assertFalse(is_legacy_product($product));
        $this->assertSame([
            'id' => 1, 'category_id' => 2, 'name' => 'GEHWOL Balsam',
            'subtitle' => 'Balzāms normālai ādai',
            'description' => '<p>Mitrina &amp; mīkstina.</p>',
            'active_ingredients' => 'Hohobas eļļa, mentols.',
            'images' => ['img/products/a.png'],
            'sort_order' => 3, 'published' => true,
            'created_at' => '2026-07-15 00:00:00', 'updated_at' => '2026-07-15 00:00:00',
        ], $product);
    }

    public function test_legacy_images_list_wins_over_single_image(): void
    {
        $product = product_from_legacy(['id' => 1, 'category_id' => 2, 'name' => 'X', 'description' => '', 'image' => 'a.png', 'images' => ['a.png', 'b.png']]);
        $this->assertSame(['img/a.png', 'img/b.png'], $product['images']);
    }
}
