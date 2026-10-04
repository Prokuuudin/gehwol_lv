<?php
// tests/UploadTest.php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/includes/upload.php';

final class UploadTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/upload_test_' . uniqid();
        mkdir($this->dir . '/data', 0777, true);
        mkdir($this->dir . '/uploads', 0777, true);
        putenv('GEHWOL_DATA_DIR=' . $this->dir . '/data');
    }

    protected function tearDown(): void
    {
        putenv('GEHWOL_DATA_DIR');
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
    }

    private function requireGd(): void
    {
        if (!image_processing_available()) {
            $this->markTestSkipped('GD not loaded (run with -d extension=gd)');
        }
    }

    private function makeImage(string $type, int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 30));
        $path = $this->dir . '/src_' . uniqid() . '.' . $type;
        $type === 'png' ? imagepng($img, $path) : imagejpeg($img, $path, 90);
        imagedestroy($img);
        return $path;
    }

    /** JPEG with an EXIF block: Orientation=6 (rotate 90° clockwise) and a camera model string. */
    private function makeJpegWithExif(int $w, int $h): string
    {
        $jpeg = file_get_contents($this->makeImage('jpg', $w, $h));
        $model = "SecretCam\0";
        $ifdOffset = 8;
        $entries = 2;
        $dataOffset = $ifdOffset + 2 + $entries * 12 + 4;
        $tiff = 'II' . pack('v', 42) . pack('V', $ifdOffset) . pack('v', $entries)
            . pack('vvV', 0x0112, 3, 1) . pack('vv', 6, 0)                       // Orientation = 6
            . pack('vvVV', 0x0110, 2, strlen($model), $dataOffset)                 // Model
            . pack('V', 0) . $model;
        $app1 = "Exif\0\0" . $tiff;
        $segment = "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1;
        $path = $this->dir . '/exif.jpg';
        file_put_contents($path, substr($jpeg, 0, 2) . $segment . substr($jpeg, 2));
        return $path;
    }

    public function test_extension_and_mime_rules(): void
    {
        $this->assertTrue(has_allowed_extension('photo.JPEG'));
        $this->assertTrue(has_allowed_extension('photo.webp'));
        $this->assertFalse(has_allowed_extension('script.php'));
        $this->assertFalse(has_allowed_extension('photo.jpg.php'));
        $this->assertTrue(is_allowed_mime('image/jpeg'));
        $this->assertFalse(is_allowed_mime('application/x-php'));
        $this->assertTrue(is_under_size_limit(1024));
        $this->assertFalse(is_under_size_limit(0));
        $this->assertFalse(is_under_size_limit(UPLOAD_MAX_BYTES + 1));
    }

    public function test_large_jpeg_is_reduced_and_gets_webp_copy(): void
    {
        $this->requireGd();
        $path = store_image($this->makeImage('jpg', 3000, 2000), 'Foto.JPG', 'products', $this->dir . '/uploads');
        $this->assertMatchesRegularExpression('~^uploads/products/[a-f0-9]{32}\.jpg$~', $path);
        $file = $this->dir . '/' . $path;
        $this->assertSame([1600, 1067], array_slice(getimagesize($file), 0, 2));
        $this->assertFileExists(substr($file, 0, -4) . '.webp');
    }

    public function test_small_image_is_not_enlarged_and_png_stays_png(): void
    {
        $this->requireGd();
        $path = store_image($this->makeImage('png', 300, 200), 'maza.png', 'news', $this->dir . '/uploads');
        $this->assertStringEndsWith('.png', $path);
        $this->assertSame([300, 200], array_slice(getimagesize($this->dir . '/' . $path), 0, 2));
    }

    public function test_exif_is_removed_and_orientation_applied(): void
    {
        $this->requireGd();
        $source = $this->makeJpegWithExif(400, 200);
        $this->assertStringContainsString('SecretCam', file_get_contents($source));

        $path = store_image($source, 'kamera.jpg', 'products', $this->dir . '/uploads');
        $out = $this->dir . '/' . $path;
        $this->assertStringNotContainsString('SecretCam', file_get_contents($out));
        $this->assertStringNotContainsString('Exif', file_get_contents($out));
        if (function_exists('exif_read_data')) {
            $this->assertSame([200, 400], array_slice(getimagesize($out), 0, 2), 'rotated to portrait');
        }
    }

    public function test_file_names_are_random(): void
    {
        $this->requireGd();
        $src = $this->makeImage('png', 10, 10);
        $this->assertNotSame(
            store_image($src, 'a.png', 'products', $this->dir . '/uploads'),
            store_image($src, 'a.png', 'products', $this->dir . '/uploads')
        );
    }

    public function test_text_file_with_image_extension_is_rejected(): void
    {
        $path = $this->dir . '/fake.jpg';
        file_put_contents($path, '<?php echo "x"; ?>');
        $this->expectException(UploadException::class);
        store_image($path, 'fake.jpg', 'products', $this->dir . '/uploads');
    }

    public function test_real_image_with_wrong_extension_is_rejected(): void
    {
        $this->requireGd();
        $this->expectException(UploadException::class);
        store_image($this->makeImage('png', 10, 10), 'image.php', 'products', $this->dir . '/uploads');
    }

    public function test_corrupted_image_is_rejected(): void
    {
        $this->requireGd();
        $good = file_get_contents($this->makeImage('jpg', 400, 400));
        $path = $this->dir . '/broken.jpg';
        file_put_contents($path, substr($good, 0, 400)); // header intact, image data cut off
        try {
            store_image($path, 'broken.jpg', 'products', $this->dir . '/uploads');
            $this->fail('expected UploadException');
        } catch (UploadException $e) {
            $this->assertStringContainsString('broken.jpg', $e->getMessage());
        }
        $this->assertSame([], glob($this->dir . '/uploads/products/*') ?: []);
    }

    public function test_image_with_huge_dimensions_is_rejected_before_decoding(): void
    {
        // valid PNG header claiming 20000 x 20000 pixels
        $ihdr = pack('NNCCCCC', 20000, 20000, 8, 2, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));
        $path = $this->dir . '/huge.png';
        file_put_contents($path, $png . str_repeat("\0", 100));
        $this->expectException(UploadException::class);
        $this->expectExceptionMessageMatches('/megapikseļi/');
        store_image($path, 'huge.png', 'products', $this->dir . '/uploads');
    }

    public function test_empty_file_is_rejected(): void
    {
        $path = $this->dir . '/empty.jpg';
        touch($path);
        $this->expectException(UploadException::class);
        store_image($path, 'empty.jpg', 'products', $this->dir . '/uploads');
    }

    public function test_upload_error_codes_become_messages(): void
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('Attēls ir pārāk liels.');
        process_uploaded_image(['name' => 'a.jpg', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE], 'products');
    }

    public function test_unused_uploads_are_deleted_and_used_or_site_images_kept(): void
    {
        $root = $this->dir . '/uploads';
        mkdir($root . '/products');
        $used = 'uploads/products/' . str_repeat('a', 32) . '.jpg';
        $unused = 'uploads/products/' . str_repeat('b', 32) . '.png';
        foreach ([$used, $unused] as $p) {
            touch($this->dir . '/' . $p);
            touch($this->dir . '/' . preg_replace('/\.\w+$/', '.webp', $p));
        }
        save_collection('products', [['id' => 1, 'images' => [$used, 'img/products/x.png']]]);

        delete_unused_uploads([$used, $unused, 'img/products/x.png', '../php/data/products.json'], $root);

        $this->assertFileExists($this->dir . '/' . $used);
        $this->assertFileDoesNotExist($this->dir . '/' . $unused);
        $this->assertFileDoesNotExist($this->dir . '/uploads/products/' . str_repeat('b', 32) . '.webp');
    }

    public function test_sweep_removes_only_old_uploads_no_data_or_backup_mentions(): void
    {
        $uploads = $this->dir . '/uploads/products';
        mkdir($uploads, 0777, true);
        $old = time() - 2 * 86400;
        $names = ['used' => str_repeat('a', 32), 'backup' => str_repeat('b', 32), 'orphan' => str_repeat('c', 32), 'fresh' => str_repeat('d', 32)];
        foreach ($names as $kind => $name) {
            foreach (['png', 'webp'] as $ext) {
                file_put_contents("{$uploads}/{$name}.{$ext}", 'x');
                touch("{$uploads}/{$name}.{$ext}", $kind === 'fresh' ? time() : $old);
            }
        }
        file_put_contents("{$uploads}/notes.txt", 'x');
        touch("{$uploads}/notes.txt", $old);
        save_collection('products', [['id' => 1, 'images' => ["uploads/products/{$names['backup']}.png"]]]);
        save_collection('products', [['id' => 1, 'images' => ["uploads/products/{$names['used']}.png"]]]); // first version is now a backup

        $this->assertSame(2, sweep_orphan_uploads($this->dir . '/uploads'));
        $this->assertEqualsCanonicalizing([
            "{$names['used']}.png", "{$names['used']}.webp",
            "{$names['backup']}.png", "{$names['backup']}.webp",
            "{$names['fresh']}.png", "{$names['fresh']}.webp",
            'notes.txt',
        ], array_map('basename', glob($uploads . '/*') ?: []));
    }
}
