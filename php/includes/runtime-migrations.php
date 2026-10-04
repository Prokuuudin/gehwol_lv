<?php

declare(strict_types=1);

require_once __DIR__ . '/storage.php';

/**
 * One-time cleanup of editorial ids. Runs as the PHP/web user because production runtime data is
 * intentionally not writable by the Git post-deploy user. Existing article fields and uploads are
 * preserved, while save_collection() creates normal recoverable backups.
 */
function migrate_text_content_ids_v2(): bool
{
    $dataDir = storage_dir();
    $marker = $dataDir . '/.text-content-ids-v2.done';
    if (is_file($marker)) {
        return false;
    }

    $migrationLock = @fopen($dataDir . '/.text-content-ids-v2.lock', 'c');
    if ($migrationLock === false || !flock($migrationLock, LOCK_EX)) {
        throw new StorageException('Cannot lock text content id migration');
    }
    try {
        if (is_file($marker)) {
            return false;
        }

        $articles = load_collection('articles', $dataDir);
        $expected = [
            1 => ['old_id' => 6, 'title' => 'Sausas pēdu ādas kopšana'],
            2 => ['old_id' => 7, 'title' => 'Terapeitiskā enerģijā pārvērstais gaiss'],
        ];
        $normalized = [];
        foreach ($expected as $id => $identity) {
            $matches = array_values(array_filter(
                $articles,
                static fn(array $row): bool => (int)($row['id'] ?? 0) === $identity['old_id']
            ));
            if (count($matches) !== 1) {
                $matches = array_values(array_filter(
                    $articles,
                    static fn(array $row): bool => (string)($row['title'] ?? '') === $identity['title']
                ));
            }
            if (count($matches) !== 1) {
                throw new StorageException("Expected exactly one real article with old id {$identity['old_id']}");
            }
            $row = $matches[0];
            $row['id'] = $id;
            $row['sort_order'] = $id;
            $normalized[] = $row;
        }

        save_collection('articles', $normalized, $dataDir);
        save_collection('news', [], $dataDir);
        reset_text_content_counters_v2($dataDir);

        if (@file_put_contents($marker, gmdate(DATE_ATOM) . PHP_EOL, LOCK_EX) === false) {
            throw new StorageException('Cannot write text content id migration marker');
        }
        return true;
    } finally {
        flock($migrationLock, LOCK_UN);
        fclose($migrationLock);
    }
}

function reset_text_content_counters_v2(string $dataDir): void
{
    $path = $dataDir . '/id_counters.json';
    $handle = @fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        throw new StorageException('Cannot lock id_counters.json for migration');
    }
    try {
        rewind($handle);
        $raw = (string)stream_get_contents($handle);
        $counters = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($counters) || (array_is_list($counters) && $counters !== [])) {
            throw new StorageException('Corrupt id_counters.json during migration');
        }
        if ($raw !== '') {
            backup_current('id_counters', $path, $dataDir);
        }
        $counters['articles'] = 2;
        $counters['news'] = 0;
        $json = json_encode($counters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new StorageException('Cannot encode id counters during migration');
        }
        $json .= PHP_EOL;
        ftruncate($handle, 0);
        rewind($handle);
        if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
            throw new StorageException('Cannot reset id counters during migration');
        }
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
