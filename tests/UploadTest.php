<?php
// tests/UploadTest.php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/includes/upload.php';

final class UploadTest extends TestCase
{
    public function test_has_allowed_extension_accepts_known_image_types(): void
    {
        $this->assertTrue(has_allowed_extension('photo.jpg'));
        $this->assertTrue(has_allowed_extension('photo.JPEG'));
        $this->assertTrue(has_allowed_extension('photo.webp'));
    }

    public function test_has_allowed_extension_rejects_others(): void
    {
        $this->assertFalse(has_allowed_extension('script.php'));
        $this->assertFalse(has_allowed_extension('archive.zip'));
    }

    public function test_is_allowed_mime_accepts_known_mime_types(): void
    {
        $this->assertTrue(is_allowed_mime('image/jpeg'));
        $this->assertFalse(is_allowed_mime('application/x-php'));
    }

    public function test_is_under_size_limit(): void
    {
        $this->assertTrue(is_under_size_limit(1024));
        $this->assertFalse(is_under_size_limit(0));
        $this->assertFalse(is_under_size_limit(10 * 1024 * 1024));
    }

    public function test_generate_upload_filename_preserves_extension_and_is_unique(): void
    {
        $a = generate_upload_filename('My Photo.PNG');
        $b = generate_upload_filename('My Photo.PNG');
        $this->assertStringEndsWith('.png', $a);
        $this->assertNotSame($a, $b);
    }
}
