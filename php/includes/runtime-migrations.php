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

/**
 * One-time move of a product card grid written as HTML at the end of an article text into the
 * article's products / products_title fields, which the admin edits as a product list.
 * Articles whose text does not end with such a grid are left untouched.
 */
function migrate_article_products_v1(): bool
{
    $dataDir = storage_dir();
    $marker = $dataDir . '/.article-products-v1.done';
    if (is_file($marker)) {
        return false;
    }

    $migrationLock = @fopen($dataDir . '/.article-products-v1.lock', 'c');
    if ($migrationLock === false || !flock($migrationLock, LOCK_EX)) {
        throw new StorageException('Cannot lock article products migration');
    }
    try {
        if (is_file($marker)) {
            return false;
        }

        $articles = load_collection('articles', $dataDir);
        $changed = false;
        foreach ($articles as &$article) {
            if (!empty($article['products'])
                || !preg_match('~(?:<h2>((?:(?!</?h2).)*)</h2>\s*)?<div class="category__grid">(.*)</div>\s*$~su', (string)($article['text'] ?? ''), $m, PREG_OFFSET_CAPTURE)
                || !preg_match_all('~href="produkts-(\d+)\.html"~', $m[2][0], $ids)) {
                continue;
            }
            $article['text'] = rtrim(substr($article['text'], 0, $m[0][1]));
            $article['products'] = array_map('intval', $ids[1]);
            $article['products_title'] = trim(html_entity_decode(strip_tags($m[1][0] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $changed = true;
        }
        unset($article);
        if ($changed) {
            save_collection('articles', $articles, $dataDir);
        }

        if (@file_put_contents($marker, gmdate(DATE_ATOM) . PHP_EOL, LOCK_EX) === false) {
            throw new StorageException('Cannot write article products migration marker');
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
