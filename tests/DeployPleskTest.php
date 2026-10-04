<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use function Gehwol\PleskDeploy\acquireLock;
use function Gehwol\PleskDeploy\deploy;
use function Gehwol\PleskDeploy\migrateTextContentIdsV1;
use function Gehwol\PleskDeploy\parseArguments;

require_once __DIR__ . '/../scripts/deploy-plesk.php';

final class DeployPleskTest extends TestCase
{
    private string $root;
    private string $source;
    private string $production;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gehwol-php-deploy-' . bin2hex(random_bytes(8));
        $this->source = $this->root . DIRECTORY_SEPARATOR . 'docs-checkout';
        $this->production = $this->root . DIRECTORY_SEPARATOR . 'httpdocs';
        mkdir($this->source, 0777, true);
        mkdir($this->production, 0777, true);
        $this->writeSourceFixture();
        $this->writeProductionFixture();
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function test_deploy_adds_updates_and_removes_code_but_preserves_all_runtime_paths(): void
    {
        $data = $this->read('httpdocs/php/data/products.json');
        $backup = $this->read('httpdocs/php/data/backups/products-1.json');
        $upload = $this->read('httpdocs/uploads/products/live.jpg');
        $wellKnown = $this->read('httpdocs/.well-known/acme-challenge/token');

        $plan = $this->runDeploy();

        self::assertSame('new js', $this->read('httpdocs/js/new.js'));
        self::assertSame('new css', $this->read('httpdocs/css/changed.css'));
        self::assertFileDoesNotExist($this->path('httpdocs/js/removed.js'));
        self::assertSame($data, $this->read('httpdocs/php/data/products.json'));
        self::assertSame($backup, $this->read('httpdocs/php/data/backups/products-1.json'));
        self::assertSame($upload, $this->read('httpdocs/uploads/products/live.jpg'));
        self::assertSame($wellKnown, $this->read('httpdocs/.well-known/acme-challenge/token'));
        self::assertFileDoesNotExist($this->path('httpdocs/php/data/source.json'));
        self::assertFileDoesNotExist($this->path('httpdocs/php/bin/dev-router.php'));
        self::assertSame('new htaccess', $this->read('httpdocs/.htaccess'));
        self::assertSame('new user ini', $this->read('httpdocs/.user.ini'));
        self::assertContains('js/new.js', $plan['added']);
        self::assertContains('css/changed.css', $plan['updated']);
        self::assertContains('js/removed.js', $plan['removed']);
    }

    public function test_concurrent_deploy_is_blocked(): void
    {
        $lockPath = $this->path('.gehwol-deploy.lock');
        $release = acquireLock($lockPath);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Another deployment is running');
            $this->runDeploy(['lockPath' => $lockPath]);
        } finally {
            $release();
        }
    }

    public function test_invalid_manifest_fails_before_production_changes(): void
    {
        $before = $this->snapshot($this->production);
        $manifest = json_decode($this->read('docs-checkout/deploy-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $manifest['files'][] = ['path' => 'php/data/products.json', 'source' => 'php/data/source.json'];
        $this->write('docs-checkout/deploy-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        try {
            $this->runDeploy();
            self::fail('Unsafe runtime manifest entry was accepted');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('persistent path', $error->getMessage());
        }
        self::assertSame($before, $this->snapshot($this->production));
    }

    public function test_dry_run_does_not_change_production(): void
    {
        $before = $this->snapshot($this->production);
        $plan = $this->runDeploy(['dryRun' => true]);
        self::assertContains('js/new.js', $plan['added']);
        self::assertSame($before, $this->snapshot($this->production));
    }

    public function test_deploy_needs_only_php_and_works_with_an_empty_process_path(): void
    {
        $script = file_get_contents(__DIR__ . '/../scripts/deploy-plesk.php');
        self::assertIsString($script);
        self::assertDoesNotMatchRegularExpression('/\b(?:node|npm)\b/i', $script);
        self::assertDoesNotMatchRegularExpression('/\b(?:exec|shell_exec|system|proc_open|popen)\s*\(/i', $script);

        $oldPath = getenv('PATH');
        putenv('PATH=');
        try {
            $this->runDeploy();
        } finally {
            putenv($oldPath === false ? 'PATH' : 'PATH=' . $oldPath);
        }
        self::assertSame('new js', $this->read('httpdocs/js/new.js'));
    }

    public function test_cli_accepts_confirmed_absolute_plesk_paths(): void
    {
        $options = parseArguments([
            '--source=/var/www/vhosts/gehwol.lv/docs',
            '--destination=/var/www/vhosts/gehwol.lv/httpdocs',
        ]);

        self::assertSame('/var/www/vhosts/gehwol.lv/docs', $options['source']);
        self::assertSame('/var/www/vhosts/gehwol.lv/httpdocs', $options['destination']);
    }

    private function runDeploy(array $overrides = []): array
    {
        return deploy($overrides + [
            'source' => $this->source,
            'destination' => $this->production,
            'logger' => static function (string $_message): void {},
        ]);
    }

    private function writeSourceFixture(): void
    {
        $files = [
            ['path' => '.htaccess', 'source' => 'docs/.htaccess', 'content' => 'new htaccess'],
            ['path' => '.user.ini', 'source' => 'docs/.user.ini', 'content' => 'new user ini'],
            ['path' => 'css/changed.css', 'source' => 'docs/css/changed.css', 'content' => 'new css'],
            ['path' => 'js/new.js', 'source' => 'docs/js/new.js', 'content' => 'new js'],
            ['path' => 'php/site.php', 'source' => 'php/site.php', 'content' => '<?php // new'],
            ['path' => 'php/templates/index.html', 'source' => 'php/templates/index.html', 'content' => '<main>ready</main>'],
            ['path' => 'php/admin/index.php', 'source' => 'php/admin/index.php', 'content' => '<?php // admin'],
        ];
        foreach ($files as $file) {
            $this->write('docs-checkout/' . $file['source'], $file['content']);
        }
        $manifest = [
            'version' => 1,
            'files' => array_map(
                static fn (array $file): array => ['path' => $file['path'], 'source' => $file['source']],
                $files
            ),
        ];
        $this->write('docs-checkout/deploy-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        // These exist in the checkout, but are deliberately absent from the allow-list.
        $this->write('docs-checkout/php/data/source.json', '{"source":true}');
        $this->write('docs-checkout/php/bin/dev-router.php', '<?php // development only');
        $this->write('docs-checkout/node_modules/private.js', 'private');
    }

    private function writeProductionFixture(): void
    {
        foreach ([
            'httpdocs/.htaccess' => 'old htaccess',
            'httpdocs/.user.ini' => 'old user ini',
            'httpdocs/css/changed.css' => 'old css',
            'httpdocs/js/removed.js' => 'stale',
            'httpdocs/php/site.php' => '<?php // old',
            'httpdocs/php/templates/index.html' => '<main>old</main>',
            'httpdocs/php/data/products.json' => '{"production":true}',
            'httpdocs/php/data/backups/products-1.json' => '{"backup":true}',
            'httpdocs/uploads/products/live.jpg' => "\x00\x01\x02\xff",
            'httpdocs/.well-known/acme-challenge/token' => 'acme-token',
        ] as $relative => $content) {
            $this->write($relative, $content);
        }
    }

    private function path(string $relative): string
    {
        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function write(string $relative, string $content): void
    {
        $path = $this->path($relative);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
    }

    private function read(string $relative): string
    {
        return (string) file_get_contents($this->path($relative));
    }

    private function snapshot(string $directory): array
    {
        $result = [];
        $visit = function (string $path, string $relative = '') use (&$visit, &$result): void {
            foreach (scandir($path) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $full = $path . DIRECTORY_SEPARATOR . $name;
                $child = $relative === '' ? $name : $relative . '/' . $name;
                if (is_dir($full) && !is_link($full)) {
                    $visit($full, $child);
                } else {
                    $result[$child] = base64_encode((string) file_get_contents($full));
                }
            }
        };
        $visit($directory);
        ksort($result);
        return $result;
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
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
