<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/admin/includes/layout.php';

final class AdminLayoutTest extends TestCase
{
    public function test_site_link_is_separate_from_admin_navigation(): void
    {
        self::assertArrayNotHasKey('../../', admin_navigation_items());

        ob_start();
        admin_site_link();
        $html = (string)ob_get_clean();

        self::assertStringContainsString('class="admin-site-link"', $html);
        self::assertStringContainsString('href="../../"', $html);
        self::assertStringContainsString('target="_blank" rel="noopener"', $html);
        self::assertStringContainsString('Uz vietni', $html);
    }
}
