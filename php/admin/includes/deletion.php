<?php
// A short-lived, per-admin undo buffer for destructive content actions.
// The deleted row stays in the server-side session; its uploads are removed only when the
// buffer expires or is replaced by a newer deletion. This keeps undo complete, including images.

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/upload.php';

const ADMIN_DELETE_UNDO_SECONDS = 300;

/** Remove the buffered deletion and any uploads that are still unused. */
function discard_recent_deletion(string $uploadRoot = UPLOAD_ROOT): void
{
    start_admin_session();
    $entry = $_SESSION['recent_deletion'] ?? null;
    unset($_SESSION['recent_deletion']);

    if (is_array($entry) && is_array($entry['uploads'] ?? null)) {
        delete_unused_uploads($entry['uploads'], $uploadRoot);
    }
}

/** Return the current undo entry, expiring and cleaning it when its five-minute window is over. */
function recent_deletion(string $uploadRoot = UPLOAD_ROOT): ?array
{
    start_admin_session();
    $entry = $_SESSION['recent_deletion'] ?? null;
    if (!is_array($entry) || (int)($entry['expires_at'] ?? 0) < time()) {
        if ($entry !== null) {
            discard_recent_deletion($uploadRoot);
        }
        return null;
    }
    return $entry;
}

/** Remember one deleted row. A newer deletion replaces the previous undo opportunity. */
function remember_recent_deletion(string $collection, string $page, array $row, array $uploads = []): string
{
    storage_path($collection); // validate before storing a collection name in the session
    discard_recent_deletion();

    $token = bin2hex(random_bytes(16));
    $_SESSION['recent_deletion'] = [
        'token' => $token,
        'collection' => $collection,
        'page' => basename($page),
        'row' => $row,
        'uploads' => array_values(array_unique(array_filter($uploads, 'is_string'))),
        'expires_at' => time() + ADMIN_DELETE_UNDO_SECONDS,
    ];
    return $token;
}

/** Restore a buffered row if the token and collection match and its id is still free. */
function restore_recent_deletion(string $collection, string $token): ?array
{
    $entry = recent_deletion();
    if ($entry === null
        || ($entry['collection'] ?? '') !== $collection
        || !is_string($entry['token'] ?? null)
        || !hash_equals($entry['token'], $token)
        || !is_array($entry['row'] ?? null)) {
        return null;
    }

    $row = $entry['row'];
    $id = (int)($row['id'] ?? 0);
    if ($id < 1) {
        return null;
    }
    $saved = update_collection($collection, function (array $rows) use ($id, $row) {
        foreach ($rows as $current) {
            if ((int)($current['id'] ?? 0) === $id) {
                return null;
            }
        }
        $rows[] = $row;
        return $rows;
    });
    if ($saved === null) {
        return null;
    }
    unset($_SESSION['recent_deletion']);
    return $row;
}
