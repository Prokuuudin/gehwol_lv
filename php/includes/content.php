<?php
// php/includes/content.php
// Content rules shared by the admin, the public pages and the migration:
// rich text is stored as HTML limited to a small whitelist; plain text typed by an editor
// becomes paragraphs (blank line = new paragraph, line break = <br>).

// tag => allowed attributes
const CONTENT_ALLOWED_TAGS = [
    'p' => ['class'], 'br' => [], 'strong' => [], 'em' => [], 'b' => [], 'i' => [],
    'ul' => [], 'ol' => [], 'li' => [],
    'h2' => ['class'], 'h3' => ['class'],
    'div' => ['class'], 'span' => ['class'],
    'a' => ['href', 'class'],
    'img' => ['src', 'alt'],
];
// removed together with their content; any other unknown tag is unwrapped (its text is kept)
const CONTENT_DROPPED_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'template', 'noscript'];

function is_safe_href(string $href): bool
{
    return (bool)preg_match('~^(https?://[^\s"<>]+|mailto:[^\s"<>]+|tel:[+\d ()-]+|#[\w-]*|[\w.-]+\.html(#[\w-]*)?|index\.html#[\w-]+)$~i', $href);
}

function is_safe_img_src(string $src): bool
{
    return (bool)preg_match('~^(\./)?(img|uploads)/[\w .@/-]+\.(png|jpe?g|webp|gif|svg)$~i', $src)
        && !str_contains($src, '..');
}

function sanitize_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="content-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $doc->getElementById('content-root');
    if ($root === null) {
        return '';
    }
    sanitize_children($root);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

function sanitize_children(DOMNode $node): void
{
    // iterate over a copy: the list changes while nodes are removed/unwrapped
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMText) {
            continue;
        }
        if (!$child instanceof DOMElement) {
            $node->removeChild($child); // comments, processing instructions
            continue;
        }
        $tag = strtolower($child->tagName);
        if (in_array($tag, CONTENT_DROPPED_TAGS, true)) {
            $node->removeChild($child);
            continue;
        }
        sanitize_children($child);
        if (!array_key_exists($tag, CONTENT_ALLOWED_TAGS)) {
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }
        foreach (iterator_to_array($child->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = $attr->value;
            $ok = in_array($name, CONTENT_ALLOWED_TAGS[$tag], true) && match ($name) {
                'class' => (bool)preg_match('/^[\w -]{1,100}$/', $value),
                'href' => is_safe_href($value),
                'src' => is_safe_img_src($value),
                default => true,
            };
            if (!$ok) {
                $child->removeAttribute($attr->name);
            }
        }
        if ($tag === 'img' && !$child->hasAttribute('src')) {
            $node->removeChild($child);
        }
    }
}

/** Editor input -> stored HTML. Text with tags is sanitized; plain text becomes paragraphs. */
function text_to_html(string $text): string
{
    $text = trim(str_replace("\r\n", "\n", $text));
    if ($text === '') {
        return '';
    }
    $hasTags = (bool)preg_match('~</?[a-z][^>]*>~i', $text);
    if (preg_match('~<(p|div|h2|h3|ul|ol)\b~i', $text)) {
        return sanitize_html($text); // already structured HTML
    }
    // plain text, possibly with inline tags such as <strong>
    $paragraphs = array_map(
        fn($p) => '<p>' . preg_replace('/[ \t]*\n[ \t]*/', '<br>', $hasTags ? trim($p) : htmlspecialchars(trim($p), ENT_COMPAT)) . '</p>',
        preg_split('/\n\s*\n/', $text)
    );
    return $hasTags ? sanitize_html(implode("\n", $paragraphs)) : implode("\n", $paragraphs);
}

/** 'YYYY-MM-DD' or 'DD.MM.YYYY' -> 'YYYY-MM-DD', null when empty/invalid. */
function normalize_date(?string $value): ?string
{
    $value = trim((string)$value);
    foreach (['Y-m-d', 'd.m.Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date && $date->format($format) === $value) {
            return $date->format('Y-m-d');
        }
    }
    return null;
}

function format_date_lv(?string $isoDate): string
{
    $date = $isoDate ? DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate) : false;
    return $date ? $date->format('d.m.Y') : '';
}

function is_published(array $row): bool
{
    return ($row['published'] ?? false) === true;
}

/** Old products.json row (plain-text description, image/images) -> current product shape. */
function product_from_legacy(array $row): array
{
    $lines = array_values(array_filter(array_map('trim', explode("\n", (string)($row['description'] ?? ''))), 'strlen'));
    $subtitle = rtrim(array_shift($lines) ?? '', '.');
    $active = '';
    foreach ($lines as $i => $line) {
        if (preg_match('/^Aktīvās vielas:\s*/iu', $line)) {
            $active = preg_replace('/^Aktīvās vielas:\s*/iu', '', $line);
            array_splice($lines, $i, 1);
            break;
        }
    }
    $images = !empty($row['images']) ? $row['images'] : (!empty($row['image']) ? [$row['image']] : []);

    $created = $row['created_at'] ?? date('Y-m-d H:i:s');
    return [
        'id' => (int)$row['id'],
        'category_id' => (int)$row['category_id'],
        'name' => (string)$row['name'],
        'subtitle' => $subtitle,
        'description' => implode("\n", array_map(fn($l) => '<p>' . htmlspecialchars($l, ENT_COMPAT) . '</p>', $lines)),
        'active_ingredients' => $active,
        'images' => array_map(fn($i) => 'img/' . ltrim($i, '/'), array_values($images)),
        'sort_order' => (int)($row['sort_order'] ?? 0),
        'published' => true,
        'created_at' => $created,
        'updated_at' => $row['updated_at'] ?? $created,
    ];
}

function is_legacy_product(array $row): bool
{
    return !array_key_exists('published', $row);
}
