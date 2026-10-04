<?php

declare(strict_types=1);

require_once __DIR__ . '/storage.php';

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
