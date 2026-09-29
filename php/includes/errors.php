<?php
// php/includes/errors.php
// Admin-wide error handling: details go to the server log, the user sees a short message without paths.

ini_set('display_errors', '0');
ini_set('log_errors', '1');

function admin_exception_handler(Throwable $e): void
{
    error_log(sprintf('[gehwol-admin] %s: %s at %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    $message = $e instanceof StorageException
        ? 'Datus neizdevās nolasīt vai saglabāt. Esošie dati nav mainīti. Lūdzu, sazinieties ar izstrādātāju.'
        : 'Radās kļūda. Lūdzu, mēģiniet vēlreiz vai sazinieties ar izstrādātāju.';
    echo '<!DOCTYPE html><html lang="lv"><head><meta charset="UTF-8"><meta name="robots" content="noindex, nofollow"><title>Kļūda</title></head><body>'
        . '<p style="color:#b00020;">' . htmlspecialchars($message) . '</p><p><a href="index.php">Atpakaļ</a></p></body></html>';
}

set_exception_handler('admin_exception_handler');
