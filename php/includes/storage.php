<?php
// php/includes/storage.php

const STORAGE_DEFAULT_DIR = __DIR__ . '/../data';

function storage_path(string $collection, ?string $dir = null): string
{
    return rtrim($dir ?? STORAGE_DEFAULT_DIR, '/\\') . '/' . $collection . '.json';
}

function load_collection(string $collection, ?string $dir = null): array
{
    $path = storage_path($collection, $dir);
    if (!is_file($path)) {
        return [];
    }
    $rows = json_decode((string)file_get_contents($path), true);
    return is_array($rows) ? $rows : [];
}

function save_collection(string $collection, array $rows, ?string $dir = null): void
{
    $json = json_encode(
        array_values($rows),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    file_put_contents(storage_path($collection, $dir), $json, LOCK_EX);
}

function next_id(array $rows): int
{
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int)($row['id'] ?? 0));
    }
    return $max + 1;
}

function sort_rows(array $rows): array
{
    usort($rows, fn($a, $b) =>
        [(int)($a['sort_order'] ?? 0), (int)($a['id'] ?? 0)]
        <=> [(int)($b['sort_order'] ?? 0), (int)($b['id'] ?? 0)]);
    return $rows;
}
