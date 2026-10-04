<?php
// php/includes/upload.php
// Image uploads: the file is checked by its content (not by the browser's name or type), decoded,
// rotated by its EXIF orientation, reduced to IMAGE_MAX_SIDE and saved again — which drops EXIF and
// any other metadata — under a random name, plus a WebP copy used by the public pages.
// Without the GD extension the checked original is stored as is (the admin shows a warning).

require_once __DIR__ . '/storage.php';

const UPLOAD_ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
const UPLOAD_ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const UPLOAD_MAX_BYTES = 10 * 1024 * 1024;
const UPLOAD_MAX_PIXELS = 40_000_000;
const IMAGE_MAX_SIDE = 1600;
const UPLOAD_ROOT = __DIR__ . '/../../uploads';
const ORPHAN_UPLOAD_MIN_AGE = 24 * 3600;

class UploadException extends RuntimeException
{
}

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

function image_processing_available(): bool
{
    return function_exists('imagecreatefromjpeg') && function_exists('imagewebp');
}

function random_image_name(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Handles one entry of $_FILES. Returns the stored path relative to the site root,
 * e.g. "uploads/products/3f9c….jpg". Throws UploadException with a message for the editor.
 */
function process_uploaded_image(array $file, string $section): string
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new UploadException(match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Attēls ir pārāk liels.',
            UPLOAD_ERR_PARTIAL => 'Attēls augšupielādēts tikai daļēji. Mēģiniet vēlreiz.',
            UPLOAD_ERR_NO_FILE => 'Attēls nav izvēlēts.',
            default => 'Attēlu neizdevās augšupielādēt (servera kļūda).',
        });
    }
    if (!is_uploaded_file((string)$file['tmp_name'])) {
        throw new UploadException('Attēlu neizdevās augšupielādēt.');
    }
    return store_image((string)$file['tmp_name'], (string)$file['name'], $section);
}

/** Validates and stores an image file; see process_uploaded_image(). */
function store_image(string $tmpPath, string $originalName, string $section, string $uploadRoot = UPLOAD_ROOT): string
{
    $label = mb_substr(basename($originalName), 0, 80);
    if (!preg_match('/^[a-z]+$/', $section)) {
        throw new UploadException('Nederīga sadaļa.');
    }
    if (!has_allowed_extension($originalName)) {
        throw new UploadException("{$label}: atļauti tikai JPG, PNG un WebP attēli.");
    }
    if (!is_under_size_limit((int)@filesize($tmpPath))) {
        throw new UploadException("{$label}: attēls ir tukšs vai lielāks par " . (UPLOAD_MAX_BYTES / 1024 / 1024) . ' MB.');
    }
    $info = @getimagesize($tmpPath);
    if ($info === false || !is_allowed_mime($info['mime'])) {
        throw new UploadException("{$label}: fails nav JPG, PNG vai WebP attēls.");
    }
    if (function_exists('finfo_open')) {
        $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmpPath);
        if ($mime !== $info['mime']) {
            throw new UploadException("{$label}: faila saturs neatbilst attēla formātam.");
        }
    }
    [$width, $height] = $info;
    if ($width < 1 || $height < 1 || $width * $height > UPLOAD_MAX_PIXELS) {
        throw new UploadException("{$label}: attēla izmēri ir par lielu (maks. " . UPLOAD_MAX_PIXELS / 1_000_000 . ' megapikseļi).');
    }

    $dir = rtrim($uploadRoot, '/\\') . '/' . $section;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        throw new UploadException('Attēlu mape nav pieejama. Sazinieties ar izstrādātāju.');
    }
    $name = random_image_name();

    if (!image_processing_available()) {
        $ext = $info['mime'] === 'image/png' ? 'png' : ($info['mime'] === 'image/webp' ? 'webp' : 'jpg');
        if (!@copy($tmpPath, "{$dir}/{$name}.{$ext}")) {
            throw new UploadException('Attēlu neizdevās saglabāt.');
        }
        return "uploads/{$section}/{$name}.{$ext}";
    }

    $image = match ($info['mime']) {
        'image/jpeg' => @imagecreatefromjpeg($tmpPath),
        'image/png' => @imagecreatefrompng($tmpPath),
        'image/webp' => @imagecreatefromwebp($tmpPath),
    };
    if (!$image) {
        throw new UploadException("{$label}: attēls ir bojāts un to nevar atvērt.");
    }
    if (!imageistruecolor($image)) {
        imagepalettetotruecolor($image);
    }
    if ($info['mime'] === 'image/jpeg') {
        $image = apply_exif_orientation($image, $tmpPath);
    }
    $image = limit_image_size($image);

    // PNG and WebP may be transparent (product photos on a clear background) — keep them lossless PNG
    $ext = $info['mime'] === 'image/jpeg' ? 'jpg' : 'png';
    $base = "{$dir}/{$name}";
    imagesavealpha($image, true);
    $saved = $ext === 'jpg' ? imagejpeg($image, "{$base}.jpg", 85) : imagepng($image, "{$base}.png", 6);
    $savedWebp = $saved && imagewebp($image, "{$base}.webp", 82);
    imagedestroy($image);
    if (!$saved || !$savedWebp) {
        @unlink("{$base}.{$ext}");
        @unlink("{$base}.webp");
        throw new UploadException('Attēlu neizdevās saglabāt.');
    }
    return "uploads/{$section}/{$name}.{$ext}";
}

function apply_exif_orientation(GdImage $image, string $path): GdImage
{
    $exif = function_exists('exif_read_data') ? @exif_read_data($path) : false;
    $angle = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
    if ($angle === 0) {
        return $image;
    }
    $rotated = imagerotate($image, $angle, 0);
    if ($rotated === false) {
        return $image;
    }
    imagedestroy($image);
    return $rotated;
}

/** Scales down so the longer side is at most IMAGE_MAX_SIDE; small images are never enlarged. */
function limit_image_size(GdImage $image): GdImage
{
    $w = imagesx($image);
    $h = imagesy($image);
    if (max($w, $h) <= IMAGE_MAX_SIDE) {
        return $image;
    }
    $scale = IMAGE_MAX_SIDE / max($w, $h);
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $resized = imagecreatetruecolor($nw, $nh);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($image);
    return $resized;
}

/** Every uploads/… path referenced by products, news and articles. */
function referenced_uploads(): array
{
    $paths = [];
    foreach (load_collection('products') as $p) {
        foreach ($p['images'] ?? [] as $src) {
            $paths[$src] = true;
        }
    }
    foreach (['news', 'articles'] as $collection) {
        foreach (load_collection($collection) as $row) {
            if (!empty($row['image'])) {
                $paths[$row['image']] = true;
            }
        }
    }
    return $paths;
}

/** Deletes uploaded files (and their WebP copies) that no record uses any more. Never touches img/. */
function delete_unused_uploads(array $paths, string $uploadRoot = UPLOAD_ROOT): void
{
    $used = referenced_uploads();
    foreach (array_unique($paths) as $path) {
        if (isset($used[$path]) || !preg_match('~^uploads/([a-z]+)/([a-f0-9]{32})\.(jpg|png|webp)$~', $path, $m)) {
            continue;
        }
        foreach (array_unique([$m[3], 'webp']) as $ext) {
            $file = rtrim($uploadRoot, '/\\') . "/{$m[1]}/{$m[2]}.{$ext}";
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}

/**
 * Deletes uploaded images that no data file and no backup mentions — left over when an admin's
 * undo buffer was never cleaned up (the session simply expired). Backups count as references,
 * so restoring a backup never loses its images. Files younger than a day are kept: they may
 * belong to a form being saved right now. Returns the number of deleted files.
 */
function sweep_orphan_uploads(string $uploadRoot = UPLOAD_ROOT, ?string $dataDir = null): int
{
    $references = '';
    foreach (array_merge(glob(storage_dir($dataDir) . '/*.json') ?: [], glob(backup_dir($dataDir) . '/*.json') ?: []) as $file) {
        $references .= (string)@file_get_contents($file);
    }
    $deleted = 0;
    foreach (glob(rtrim($uploadRoot, '/\\') . '/*/*.*') ?: [] as $file) {
        if (preg_match('~^([a-f0-9]{32})\.(jpg|png|webp)$~', basename($file), $m)
            && !str_contains($references, $m[1])
            && (int)@filemtime($file) < time() - ORPHAN_UPLOAD_MIN_AGE
            && @unlink($file)) {
            $deleted++;
        }
    }
    return $deleted;
}
