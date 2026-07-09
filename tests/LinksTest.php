<?php
// tests/LinksTest.php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/includes/links.php';

final class LinksTest extends TestCase
{
    public function test_category_link_uses_static_file_when_legacy(): void
    {
        $link = category_link(['id' => 2, 'link_url' => 'gehwol-classic.html']);
        $this->assertSame('gehwol-classic.html', $link);
    }

    public function test_category_link_uses_generated_page_when_live(): void
    {
        $link = category_link(['id' => 22, 'link_url' => null]);
        $this->assertSame('category.php?id=22', $link);
    }

    public function test_product_link_format(): void
    {
        $this->assertSame('product.php?id=5', product_link(5));
    }

    public function test_news_link_format(): void
    {
        $this->assertSame('news.php?id=5', news_link(5));
    }

    public function test_article_link_format(): void
    {
        $this->assertSame('article.php?id=5', article_link(5));
    }
}
