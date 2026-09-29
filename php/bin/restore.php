<?php
// php/bin/restore.php — list or restore JSON backups (CLI only).
//   php php/bin/restore.php products              list backups, newest first
//   php php/bin/restore.php products <file>       restore that backup (current version is backed up first)

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/storage.php';

[$script, $collection, $file] = $argv + [null, null, null];
if ($collection === null) {
    fwrite(STDERR, "Usage: php {$script} <collection> [backup-file]\n");
    exit(1);
}

try {
    if ($file === null) {
        foreach (list_backups($collection) as $name) {
            echo $name, "\n";
        }
        exit(0);
    }
    restore_backup($collection, $file);
    echo "Restored {$collection} from {$file}\n";
} catch (StorageException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
