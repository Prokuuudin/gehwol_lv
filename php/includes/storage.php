<?php
// php/includes/storage.php
//
// JSON collections in php/data/<name>.json.
// - A missing file is an empty collection; an unreadable or corrupt file is an error
//   (never treated as empty, so a later save cannot wipe real data).
// - Saves are atomic: temp file -> verify -> rename. The previous version is copied to
//   php/data/backups/<name>-<timestamp>.json first; only the newest STORAGE_KEEP_BACKUPS are kept.

const STORAGE_DEFAULT_DIR = __DIR__ . '/../data';
const STORAGE_KEEP_BACKUPS = 20;

class StorageException extends RuntimeException
{
}

function storage_dir(?string $dir = null): string
{
    return rtrim($dir ?? (getenv('GEHWOL_DATA_DIR') ?: STORAGE_DEFAULT_DIR), '/\\');
}

function storage_path(string $collection, ?string $dir = null): string
{
    if (!preg_match('/^[a-z0-9_]+$/', $collection)) {
        throw new StorageException("Invalid collection name: {$collection}");
    }
    return storage_dir($dir) . '/' . $collection . '.json';
}

function backup_dir(?string $dir = null): string
{
    return storage_dir($dir) . '/backups';
}

function decode_collection(string $json, string $source): array
{
    $rows = json_decode($json, true);
    if (!is_array($rows) || !array_is_list($rows)) {
        throw new StorageException("Corrupt JSON in {$source}");
    }
    return $rows;
}

function load_collection(string $collection, ?string $dir = null): array
{
    $path = storage_path($collection, $dir);
    if (!file_exists($path)) {
        return [];
    }
    $json = @file_get_contents($path);
    if ($json === false) {
        throw new StorageException("Cannot read {$path}");
    }
    return decode_collection($json, $path);
}

function save_collection(string $collection, array $rows, ?string $dir = null): void
{
    $path = storage_path($collection, $dir);
    $json = json_encode(
        array_values($rows),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($json === false) {
        throw new StorageException("Cannot encode {$collection}: " . json_last_error_msg());
    }
    $json .= "\n";

    $lock = @fopen($path . '.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new StorageException("Cannot lock {$path}");
    }
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    try {
        if (@file_put_contents($tmp, $json) !== strlen($json)) {
            throw new StorageException("Cannot write {$tmp}");
        }
        decode_collection((string)file_get_contents($tmp), $tmp);

        if (file_exists($path)) {
            backup_current($collection, $path, $dir);
        }
        if (!@rename($tmp, $path)) {
            throw new StorageException("Cannot replace {$path}");
        }
    } finally {
        if (file_exists($tmp)) {
            @unlink($tmp);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function backup_current(string $collection, string $path, ?string $dir): void
{
    $backups = backup_dir($dir);
    if (!is_dir($backups) && !@mkdir($backups, 0775, true)) {
        throw new StorageException("Cannot create {$backups}");
    }
    $target = sprintf('%s/%s-%s.json', $backups, $collection, (new DateTimeImmutable())->format('Ymd-His-u'));
    if (file_exists($target)) {
        $target = substr($target, 0, -5) . '-' . bin2hex(random_bytes(2)) . '.json';
    }
    if (!@copy($path, $target)) {
        throw new StorageException("Cannot back up {$path}");
    }
    $old = array_slice(list_backups($collection, $dir), STORAGE_KEEP_BACKUPS);
    foreach ($old as $file) {
        @unlink($backups . '/' . $file);
    }
}

/** Backup file names for a collection, newest first. */
function list_backups(string $collection, ?string $dir = null): array
{
    $files = array_map('basename', glob(backup_dir($dir) . '/' . $collection . '-*.json') ?: []);
    $files = array_values(array_filter($files, fn($f) => preg_match('/^' . preg_quote($collection, '/') . '-\d{8}-\d{6}-\d{6}/', $f)));
    rsort($files, SORT_STRING);
    return $files;
}

/** Replace a collection with one of its backups (the current version is backed up first). */
function restore_backup(string $collection, string $backupFile, ?string $dir = null): void
{
    if (!in_array($backupFile, list_backups($collection, $dir), true)) {
        throw new StorageException("Unknown backup {$backupFile}");
    }
    $path = backup_dir($dir) . '/' . $backupFile;
    save_collection($collection, decode_collection((string)file_get_contents($path), $path), $dir);
}

/** Human-readable problems with data directory permissions (empty when fine). */
function storage_problems(?string $dir = null): array
{
    $problems = [];
    if (!is_dir(storage_dir($dir)) || !is_writable(storage_dir($dir))) {
        $problems[] = 'Datu mapē nevar rakstīt.';
    }
    if (is_dir(backup_dir($dir)) && !is_writable(backup_dir($dir))) {
        $problems[] = 'Rezerves kopiju mapē nevar rakstīt.';
    }
    return $problems;
}

function next_id(array $rows): int
{
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int)($row['id'] ?? 0));
    }
    return $max + 1;
}

/**
 * Highest ids used by the old static site (jaunums-1..5, raksts-1..7). New records start above them,
 * so an old URL known to search engines never shows unrelated content and the raksts-6/7 redirects
 * in render.php never hide a new article.
 */
const ID_FLOORS = ['news' => 5, 'articles' => 7];

/**
 * New id for a record that is about to be added. Remembers the last issued id per collection in
 * php/data/id_counters.json, so the id (and the public URL) of a deleted record is never reused.
 */
function allocate_id(string $collection, array $rows, ?string $dir = null): int
{
    storage_path($collection, $dir); // validates the name
    $handle = @fopen(storage_dir($dir) . '/id_counters.json', 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        throw new StorageException('Cannot open id_counters.json');
    }
    try {
        $counters = json_decode((string)stream_get_contents($handle), true);
        $counters = is_array($counters) ? $counters : [];
        $id = max(next_id($rows), (int)($counters[$collection] ?? 0) + 1, (ID_FLOORS[$collection] ?? 0) + 1);
        $counters[$collection] = $id;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($counters, JSON_PRETTY_PRINT) . "\n");
        fflush($handle);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
    return $id;
}

function sort_rows(array $rows): array
{
    usort($rows, fn($a, $b) =>
        [(int)($a['sort_order'] ?? 0), (int)($a['id'] ?? 0)]
        <=> [(int)($b['sort_order'] ?? 0), (int)($b['id'] ?? 0)]);
    return $rows;
}
