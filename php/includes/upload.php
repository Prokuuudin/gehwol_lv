<?php
// php/includes/upload.php

const UPLOAD_ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
const UPLOAD_ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;

function has_allowed_extension(string $filename): bool
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, UPLOAD_ALLOWED_EXTENSIONS, true);
}

function is_allowed_mime(string $mime): bool
{
    return in_array($mime, UPLOAD_ALLOWED_MIME_TYPES, true);
}

function is_under_size_limit(int $bytes): bool
{
    return $bytes > 0 && $bytes <= UPLOAD_MAX_BYTES;
}

function generate_upload_filename(string $originalName): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    return uniqid('img_', true) . '.' . $ext;
}

function save_uploaded_image(array $file, string $destDir): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (!has_allowed_extension($file['name']) || !is_under_size_limit((int)$file['size'])) {
        return null;
    }
    // fileinfo may be unavailable on shared hosting — fall back to image header sniffing
    $mime = function_exists('mime_content_type')
        ? mime_content_type($file['tmp_name'])
        : (@getimagesize($file['tmp_name'])['mime'] ?? false);
    if ($mime === false || !is_allowed_mime($mime)) {
        return null;
    }
    $filename = generate_upload_filename($file['name']);
    $destPath = rtrim($destDir, '/') . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return null;
    }
    return $filename;
}
