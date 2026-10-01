<?php

declare(strict_types=1);

namespace Gehwol\PleskDeploy;

use Closure;
use RuntimeException;
use Throwable;

const PERSISTENT_ROOTS = ['php/data', 'uploads'];
const PLATFORM_ROOTS = ['.well-known'];
const DEV_ONLY_PHP = ['php/bin/dev-router.php', 'php/bin/render-all.php', 'php/bin/migrate.php'];

function slash(string $path): string
{
    return str_replace('\\', '/', $path);
}

function isInside(string $relative, array $roots): bool
{
    foreach ($roots as $root) {
        if ($relative === $root || str_starts_with($relative, $root . '/')) {
            return true;
        }
    }
    return false;
}

function safeRelative(string $relative, string $label): string
{
    $relative = slash($relative);
    if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, "\0")) {
        throw new RuntimeException("Unsafe {$label}: {$relative}");
    }
    foreach (explode('/', $relative) as $part) {
        if ($part === '' || $part === '.' || $part === '..') {
            throw new RuntimeException("Unsafe {$label}: {$relative}");
        }
    }
    return $relative;
}

function nativePath(string $root, string $relative): string
{
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function assertNoSymlinkPath(string $root, string $relative): void
{
    $current = rtrim($root, '/\\');
    foreach (explode('/', $relative) as $part) {
        $current .= DIRECTORY_SEPARATOR . $part;
        if (is_link($current)) {
            throw new RuntimeException("Deploy source must not contain symlinks: {$relative}");
        }
    }
}

function fileHash(string $path): string
{
    $hash = hash_file('sha256', $path);
    if ($hash === false) {
        throw new RuntimeException("Cannot hash file: {$path}");
    }
    return $hash;
}

function validateTextFile(string $relative, string $path): void
{
    if (!preg_match('/\.(?:html?|js|css|txt|json|ini|xml)$/i', $relative)) {
        return;
    }
    $size = filesize($path);
    if ($size === false || $size > 8 * 1024 * 1024) {
        return;
    }
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Cannot read release file: {$relative}");
    }
    if (preg_match('/(?:password|parole|passwd)\s*[:=]\s*["\']?[^\s"\'<]{6,}/i', $contents)) {
        throw new RuntimeException("Release file looks like it contains a credential: {$relative}");
    }
}

function loadDesired(string $sourceRoot): array
{
    $manifestPath = $sourceRoot . DIRECTORY_SEPARATOR . 'deploy-manifest.json';
    if (!is_file($manifestPath) || is_link($manifestPath)) {
        throw new RuntimeException("Missing safe deploy manifest: {$manifestPath}");
    }
    $decoded = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    if (($decoded['version'] ?? null) !== 1 || !is_array($decoded['files'] ?? null)) {
        throw new RuntimeException('Unsupported or corrupt deploy manifest');
    }

    $desired = [];
    $fingerprint = [];
    $latestMtime = 0;
    foreach ($decoded['files'] as $item) {
        if (!is_array($item) || !is_string($item['path'] ?? null) || !is_string($item['source'] ?? null)) {
            throw new RuntimeException('Invalid deploy manifest entry');
        }
        $relative = safeRelative($item['path'], 'release path');
        $source = safeRelative($item['source'], 'source path');
        if (isset($desired[$relative])) {
            throw new RuntimeException("Duplicate release path: {$relative}");
        }
        if (isInside($relative, PERSISTENT_ROOTS) || isInside($relative, PLATFORM_ROOTS)) {
            throw new RuntimeException("Manifest must not manage persistent path: {$relative}");
        }
        if (preg_match('~(^|/)(?:\.git|node_modules|tests|src|plans|specs|backups)(/|$)|\.(?:docx?|md|log|lock|tmp|env|sql|zip|bak)$~i', $relative)) {
            throw new RuntimeException("Development/private file in manifest: {$relative}");
        }
        $validMapping = str_starts_with($source, 'docs/')
            ? substr($source, 5) === $relative
            : (str_starts_with($source, 'php/') && $source === $relative);
        if (!$validMapping || isInside($source, PERSISTENT_ROOTS) || in_array($source, DEV_ONLY_PHP, true)) {
            throw new RuntimeException("Unsafe source mapping: {$source} -> {$relative}");
        }

        assertNoSymlinkPath($sourceRoot, $source);
        $fullSource = nativePath($sourceRoot, $source);
        if (!is_file($fullSource)) {
            throw new RuntimeException("Manifest source is missing: {$source}");
        }
        validateTextFile($relative, $fullSource);
        $hash = fileHash($fullSource);
        $mode = fileperms($fullSource);
        $mtime = filemtime($fullSource);
        $latestMtime = max($latestMtime, $mtime === false ? 0 : $mtime);
        $desired[$relative] = [
            'source' => $fullSource,
            'content' => null,
            'hash' => $hash,
            'mode' => $mode === false ? 0644 : ($mode & 0777),
        ];
        $fingerprint[] = $relative . ':' . $hash;
    }

    foreach (['.htaccess', '.user.ini', 'php/site.php', 'php/templates/index.html'] as $required) {
        if (!isset($desired[$required])) {
            throw new RuntimeException("Incomplete deploy manifest, missing {$required}");
        }
    }

    ksort($desired, SORT_STRING);
    sort($fingerprint, SORT_STRING);
    $version = substr(hash('sha256', implode("\n", $fingerprint)), 0, 12);
    $built = gmdate('Y-m-d H:i', $latestMtime ?: time());
    $releaseContent = "<?php\nreturn ['commit' => 'content-{$version}', 'built' => '{$built}'];\n";
    $desired['php/includes/release.php'] = [
        'source' => null,
        'content' => $releaseContent,
        'hash' => hash('sha256', $releaseContent),
        'mode' => 0644,
    ];
    ksort($desired, SORT_STRING);
    return [$desired, $version];
}

function scanDestination(string $root, string $current = '', array &$entries = []): array
{
    $directory = $current === '' ? $root : nativePath($root, $current);
    if (!is_dir($directory)) {
        return $entries;
    }
    $items = scandir($directory);
    if ($items === false) {
        throw new RuntimeException("Cannot read production directory: {$directory}");
    }
    foreach ($items as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $relative = $current === '' ? $name : $current . '/' . $name;
        if (isInside($relative, PERSISTENT_ROOTS) || isInside($relative, PLATFORM_ROOTS)) {
            continue;
        }
        $full = nativePath($root, $relative);
        if (is_link($full)) {
            $entries[$relative] = ['type' => 'link', 'hash' => null];
        } elseif (is_dir($full)) {
            scanDestination($root, $relative, $entries);
        } elseif (is_file($full)) {
            $entries[$relative] = ['type' => 'file', 'hash' => fileHash($full)];
        } else {
            throw new RuntimeException("Unsupported production filesystem entry: {$full}");
        }
    }
    return $entries;
}

function makePlan(string $sourceRoot, string $destination): array
{
    [$desired, $version] = loadDesired($sourceRoot);
    $current = scanDestination($destination);
    $plan = ['added' => [], 'updated' => [], 'removed' => [], 'unchanged' => []];
    foreach ($desired as $relative => $entry) {
        if (!isset($current[$relative])) {
            $plan['added'][] = $relative;
        } elseif ($current[$relative]['type'] === 'file' && $current[$relative]['hash'] === $entry['hash']) {
            $plan['unchanged'][] = $relative;
        } else {
            $plan['updated'][] = $relative;
        }
    }
    foreach ($current as $relative => $_entry) {
        if (!isset($desired[$relative])) {
            $plan['removed'][] = $relative;
        }
    }
    foreach ($plan as &$paths) {
        sort($paths, SORT_STRING);
    }
    unset($paths);
    return ['desired' => $desired, 'version' => $version] + $plan;
}

function removeTree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        if (!@unlink($path)) {
            throw new RuntimeException("Cannot remove: {$path}");
        }
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        throw new RuntimeException("Cannot read directory for removal: {$path}");
    }
    foreach ($items as $name) {
        if ($name !== '.' && $name !== '..') {
            removeTree($path . DIRECTORY_SEPARATOR . $name);
        }
    }
    if (!@rmdir($path)) {
        throw new RuntimeException("Cannot remove directory: {$path}");
    }
}

function ensureDirectory(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        return;
    }
    if (file_exists($path) || is_link($path)) {
        removeTree($path);
    }
    $parent = dirname($path);
    if ($parent !== $path) {
        ensureDirectory($parent);
    }
    if (!@mkdir($path, 0755) && !is_dir($path)) {
        throw new RuntimeException("Cannot create directory: {$path}");
    }
}

function atomicInstall(array $entry, string $target): void
{
    ensureDirectory(dirname($target));
    if (is_dir($target) && !is_link($target)) {
        removeTree($target);
    }
    $temporary = dirname($target) . DIRECTORY_SEPARATOR
        . '.gehwol-deploy-' . getmypid() . '-' . bin2hex(random_bytes(6)) . '.tmp';
    try {
        $ok = $entry['source'] !== null
            ? copy($entry['source'], $temporary)
            : file_put_contents($temporary, $entry['content']) !== false;
        if (!$ok) {
            throw new RuntimeException("Cannot stage file: {$target}");
        }
        @chmod($temporary, $entry['mode']);
        if (!@rename($temporary, $target)) {
            if (file_exists($target) || is_link($target)) {
                removeTree($target);
            }
            if (!@rename($temporary, $target)) {
                throw new RuntimeException("Cannot atomically install file: {$target}");
            }
        }
    } finally {
        if (file_exists($temporary) || is_link($temporary)) {
            @unlink($temporary);
        }
    }
}

function pruneEmptyDirectories(string $root, string $current = ''): void
{
    $directory = $current === '' ? $root : nativePath($root, $current);
    if (!is_dir($directory) || is_link($directory)) {
        return;
    }
    $items = scandir($directory);
    if ($items === false) {
        throw new RuntimeException("Cannot inspect directory: {$directory}");
    }
    foreach ($items as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $relative = $current === '' ? $name : $current . '/' . $name;
        if (!isInside($relative, PERSISTENT_ROOTS) && !isInside($relative, PLATFORM_ROOTS)) {
            pruneEmptyDirectories($root, $relative);
        }
    }
    if ($current !== '' && !isInside($current, PERSISTENT_ROOTS) && !isInside($current, PLATFORM_ROOTS)) {
        $remaining = scandir($directory);
        if ($remaining !== false && count($remaining) === 2) {
            @rmdir($directory);
        }
    }
}

function applyPlan(array $plan, string $destination): void
{
    ensureDirectory($destination);
    $blockers = [];
    foreach ($plan['removed'] as $old) {
        foreach ($plan['desired'] as $wanted => $_entry) {
            if (str_starts_with($wanted, $old . '/')) {
                $blockers[$old] = true;
                removeTree(nativePath($destination, $old));
                break;
            }
        }
    }
    foreach (array_merge($plan['added'], $plan['updated']) as $relative) {
        atomicInstall($plan['desired'][$relative], nativePath($destination, $relative));
    }
    $removed = array_values(array_filter(
        $plan['removed'],
        static fn (string $path): bool => !isset($blockers[$path])
    ));
    usort($removed, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
    foreach ($removed as $relative) {
        removeTree(nativePath($destination, $relative));
    }
    pruneEmptyDirectories($destination);
}

function acquireLock(string $lockPath): Closure
{
    ensureDirectory(dirname($lockPath));
    $handle = @fopen($lockPath, 'x');
    if ($handle === false) {
        throw new RuntimeException("Another deployment is running (lock: {$lockPath})");
    }
    fwrite($handle, json_encode(['pid' => getmypid(), 'started' => gmdate(DATE_ATOM)]) . "\n");
    fclose($handle);
    $released = false;
    return static function () use ($lockPath, &$released): void {
        if (!$released) {
            $released = true;
            @unlink($lockPath);
        }
    };
}

function normalizedAbsolute(string $path): string
{
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    if (!str_starts_with($path, DIRECTORY_SEPARATOR)
        && !preg_match('/^[A-Za-z]:\\\\/', $path)) {
        $path = getcwd() . DIRECTORY_SEPARATOR . $path;
    }
    $drive = '';
    if (preg_match('/^([A-Za-z]:)\\\\/', $path, $match)) {
        $drive = $match[1];
        $path = substr($path, 3);
    } else {
        $path = ltrim($path, DIRECTORY_SEPARATOR);
    }
    $parts = [];
    foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($parts);
        } else {
            $parts[] = $part;
        }
    }
    $prefix = $drive !== '' ? $drive . DIRECTORY_SEPARATOR : DIRECTORY_SEPARATOR;
    return $prefix . implode(DIRECTORY_SEPARATOR, $parts);
}

function containsPath(string $parent, string $child): bool
{
    $parent = rtrim(normalizedAbsolute($parent), DIRECTORY_SEPARATOR);
    $child = normalizedAbsolute($child);
    if (DIRECTORY_SEPARATOR === '\\') {
        $parent = strtolower($parent);
        $child = strtolower($child);
    }
    return $child === $parent || str_starts_with($child, $parent . DIRECTORY_SEPARATOR);
}

function logPaths(string $label, array $paths, callable $logger): void
{
    $logger("{$label}: " . count($paths));
    foreach (array_slice($paths, 0, 30) as $path) {
        $logger("  {$path}");
    }
    if (count($paths) > 30) {
        $logger('  ... and ' . (count($paths) - 30) . ' more');
    }
}

function deploy(array $options = []): array
{
    $source = normalizedAbsolute((string) ($options['source'] ?? dirname(__DIR__)));
    $destination = normalizedAbsolute((string) (
        $options['destination'] ?? '/var/www/vhosts/gehwol.lv/httpdocs'
    ));
    $dryRun = (bool) ($options['dryRun'] ?? false);
    $logger = $options['logger'] ?? static fn (string $message) => print($message . PHP_EOL);
    if (basename($destination) !== 'httpdocs' || $destination === DIRECTORY_SEPARATOR) {
        throw new RuntimeException("Refusing unsafe destination (expected httpdocs): {$destination}");
    }
    if (is_link($destination)) {
        throw new RuntimeException("Refusing symlink destination: {$destination}");
    }
    if (containsPath($source, $destination) || containsPath($destination, $source)) {
        throw new RuntimeException('Source and production destination must not contain each other');
    }

    $lockPath = (string) ($options['lockPath'] ?? dirname($destination) . DIRECTORY_SEPARATOR . '.gehwol-deploy.lock');
    $releaseLock = acquireLock($lockPath);
    $logger('Deploy started: ' . gmdate(DATE_ATOM));
    $logger("Source: {$source}");
    $logger("Destination: {$destination}");
    $logger('Persistent paths: php/data/**, uploads/**, .well-known/** (preserved)');
    try {
        $plan = makePlan($source, $destination);
        $logger("Release validation: success (content-{$plan['version']})");
        logPaths('Add', $plan['added'], $logger);
        logPaths('Update', $plan['updated'], $logger);
        logPaths('Remove', $plan['removed'], $logger);
        if ($dryRun) {
            $logger('Runtime preservation: persistent paths were not read or modified');
            $logger('Deployment result: dry run; production destination was not changed');
            return $plan;
        }
        applyPlan($plan, $destination);
        $logger('Runtime preservation: persistent paths were not read or modified');
        $logger(sprintf(
            'Deployment result: success (%d added, %d updated, %d removed)',
            count($plan['added']),
            count($plan['updated']),
            count($plan['removed'])
        ));
        $logger('Deploy completed successfully');
        return $plan;
    } catch (Throwable $error) {
        $logger('Deployment failed: ' . $error->getMessage());
        throw $error;
    } finally {
        $releaseLock();
    }
}

function parseArguments(array $arguments): array
{
    $options = [];
    for ($index = 0; $index < count($arguments); $index++) {
        $argument = $arguments[$index];
        if ($argument === '--dry-run') {
            $options['dryRun'] = true;
        } elseif (str_starts_with($argument, '--destination=')) {
            $options['destination'] = substr($argument, 14);
        } elseif ($argument === '--destination' && isset($arguments[$index + 1])) {
            $options['destination'] = $arguments[++$index];
        } elseif (str_starts_with($argument, '--source=')) {
            $options['source'] = substr($argument, 9);
        } elseif ($argument === '--source' && isset($arguments[$index + 1])) {
            $options['source'] = $arguments[++$index];
        } else {
            throw new RuntimeException("Unknown or incomplete argument: {$argument}");
        }
    }
    return $options;
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        deploy(parseArguments(array_slice($argv, 1)));
    } catch (Throwable $error) {
        fwrite(STDERR, 'ERROR: ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
