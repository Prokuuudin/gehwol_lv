# Simple Admin Panel (products, news, articles) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a PHP admin panel (JSON file storage) that lets the site owner add products, news and articles, layered onto the existing static gulp site without touching its fragile HTML build pipeline.

**Architecture:** New `php/` directory (JSON collections in `php/data/`, session auth, single admin user) lives outside the gulp pipeline and is copied verbatim into `build/` by a new gulp task. Existing static pages stay untouched; a small JS module fetches new content as JSON from `php/api.php` and injects it into the DOM (product cards, category links, news/article slides) after page load. New products/news/articles always get their own PHP detail page (`product.php?id=`, `news.php?id=`, `article.php?id=`); only `categories` carries a legacy/live split (`link_url` set = points at one of the 21 seeded rows representing the real existing category tree; `link_url` null = admin-created, gets `category.php?id=`).

**Tech Stack:** PHP 8 (sessions, no framework, no DB — JSON files under `php/data/`), PHPUnit 10 (pure-logic unit tests only — CRUD/auth/rendering pages are verified manually since they need an HTTP server), vanilla JS (bundled by the existing webpack config), gulp (one new passthrough task).

> **Status: implemented 2026-07-10.** Tasks 1–6 landed before the storage revision (then refactored to JSON); Tasks 7–18 implemented against the revised JSON design. All PHPUnit suites pass (26 tests); homepage/category/news injection and click-through verified headless (Playwright) against a clean `build/`. Additions beyond the original plan: CSRF tokens on all admin mutations, `session_regenerate_id()` on login, `seeded` flag on seed categories, `php/` link prefix in API output, `../` prefix for legacy category links in `php/product.php`.

## Revision 2026-07-10 — JSON storage instead of MySQL

Storage switched from MySQL/PDO to plain JSON files (no DB server available or wanted on the hosting). Everywhere a task below shows PDO/SQL code, the JSON storage API applies instead; the pages, routes, JSON API shapes, gulp wiring and JS injection are unchanged. Concretely:

- **Dropped:** `php/includes/db.php`, `php/migrations/001_init.sql`, `php/config.example.php`, local `php/config.php`, all MySQL setup steps.
- **Added:** `php/includes/storage.php` — `load_collection(string $name): array` (missing file → `[]`), `save_collection(string $name, array $rows): void` (`file_put_contents(..., LOCK_EX)`, pretty JSON, unescaped unicode), `next_id(array $rows): int` (`max(id)+1`), `sort_rows(array $rows): array` (by `sort_order`, then `id`). Collections live at `php/data/{name}.json`.
- **Seed:** `php/data/categories.json` committed with the same 21 rows/ids as Task 2's seed table (ids 1–21 stay authoritative), each carrying `"seeded": true` — `api.php?type=categories` returns only rows without that flag (Task 12's `link_url IS NULL` filter alone would leak the 4 seeded group rows, ids 1/9/14/18, and the JS would duplicate "Rotējošie instrumenti" on the homepage). `products/news/articles/admin_users` JSON files are gitignored, created at runtime.
- **Admin user (replaces Task 2 Steps 2–4):** `php -r "file_put_contents('php/data/admin_users.json', json_encode([['id'=>1,'username'=>'admin','password_hash'=>password_hash('changeme123', PASSWORD_DEFAULT)]]));"`
- **Task 6 auth:** pure `find_admin_in(array $users, string $username): ?array` (unit-tested) + `find_admin_by_username()` delegating to it over `load_collection('admin_users')`.
- **Tasks 8–13:** same pages/flows; row lookups/filters/mutations are array operations + `save_collection` instead of prepared statements. Deletes filter the array; referential checks (category has children/products) scan the arrays.
- **Security:** `php/data/.htaccess` denies HTTP access to raw JSON (admin hashes never served); no SQL at all.
- **Deploy:** hosting needs only PHP; make `php/data/` + `uploads/*` writable by the web server. Redeploys must never overwrite live `php/data/` or `uploads/` (owner content lives there).

## Global Constraints

- No pricing/checkout anywhere — products are description + photo only (per spec).
- Existing static files (`produkts-1..6.html`, `raksts-1..5.html`, `jaunums-1..5.html`, the 17 category line pages, `index.html`) are never edited for content and never renamed — only `products.html` and the 17 line pages' `@@include` calls get one added parameter/attribute each (structural, not content).
- Single admin user, bcrypt (`password_hash`/`password_verify`), PHP session auth.
- All data writes via `save_collection()` (LOCK_EX) — no ad-hoc file writes anywhere; ids cast to `(int)`, all HTML output escaped.
- Uploaded images: extensions `jpg`, `jpeg`, `png`, `webp` only, max 5 MB, stored under `uploads/{products,news,articles}/` with a generated filename, `uploads/.htaccess` denies PHP execution.
- `gulp docs` (→ `docs/`, GitHub Pages) is **out of scope** for this plan and must not be touched — only `gulp`/`gulp default` (→ `build/`) gets the new copy task.
- Category IDs are fixed by the seed data (see Task 2) — every task that references a category id (the 17 line pages' `categoryId` param, `products.html`'s `data-category-id` attributes) must use exactly these ids, not placeholders.

---

### Task 1: Project scaffolding — PHP/test tooling, directories, config template

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml`
- Create: `php/config.example.php`
- Create: `uploads/.htaccess`
- Create: `uploads/products/.gitkeep`, `uploads/news/.gitkeep`, `uploads/articles/.gitkeep`
- Modify: `.gitignore`

**Interfaces:**
- Produces: directory layout `php/`, `php/includes/`, `php/admin/`, `php/admin/includes/`, `php/migrations/`, `uploads/{products,news,articles}/`, `tests/` — every later task creates files inside these.
- Produces: `php/config.example.php` returning `['host'=>..., 'dbname'=>..., 'user'=>..., 'pass'=>...]` — Task 2's `db.php` reads the real (gitignored) `php/config.php` built from this template.

- [ ] **Step 1: Create directories**

```bash
mkdir -p php/includes php/admin/includes php/migrations tests uploads/products uploads/news uploads/articles
```

- [ ] **Step 2: Create `composer.json`**

```json
{
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    }
}
```

- [ ] **Step 3: Create `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
  <testsuites>
    <testsuite name="Unit">
      <directory>tests</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

- [ ] **Step 4: Install dependencies and verify PHPUnit runs**

```bash
composer install
vendor/bin/phpunit --version
```

Expected: prints `PHPUnit 10.x.x` (no test files exist yet, that's fine — this just confirms the tool works).

- [ ] **Step 5: Create `php/config.example.php`**

```php
<?php

return [
    'host' => '127.0.0.1',
    'dbname' => 'gehwol_lv',
    'user' => 'root',
    'pass' => '',
];
```

- [ ] **Step 6: Create `uploads/.htaccess`**

```apache
<FilesMatch "\.(php|phtml|php\d)$">
    Require all denied
</FilesMatch>
```

- [ ] **Step 7: Create placeholder files so git tracks the empty upload directories**

```bash
touch uploads/products/.gitkeep uploads/news/.gitkeep uploads/articles/.gitkeep
```

- [ ] **Step 8: Update `.gitignore`**

Append these lines to the existing `.gitignore` (it currently ends with `config.php` on the last line with no trailing newline — add a newline first):

```
/vendor/
uploads/products/*
uploads/news/*
uploads/articles/*
!uploads/products/.gitkeep
!uploads/news/.gitkeep
!uploads/articles/.gitkeep
!uploads/.htaccess
```

Note: the existing bare `config.php` line already ignores `php/config.php` (gitignore patterns with no leading slash match at any depth) — no change needed for that.

- [ ] **Step 9: Commit**

```bash
git add composer.json phpunit.xml php/config.example.php uploads/.htaccess uploads/products/.gitkeep uploads/news/.gitkeep uploads/articles/.gitkeep .gitignore composer.lock
git commit -m "chore: scaffold PHP admin panel tooling (composer, phpunit, uploads dir)"
```

---

### Task 2: Database schema, seed data, PDO connector

**Files:**
- Create: `php/migrations/001_init.sql`
- Create: `php/includes/db.php`

**Interfaces:**
- Consumes: `php/config.php` (gitignored, created locally by the developer from `config.example.php` in this task's manual step).
- Produces: `get_pdo(): PDO` — every other PHP file that talks to the DB calls this.
- Produces: 21 seeded `categories` rows with fixed ids 1–21 (table below) — Tasks 12, 15, 16 depend on these exact numbers.

Fixed category ids from the seed (do not renumber):

| id | name | parent_id | link_url |
|----|------|-----------|----------|
| 1 | Kosmētika | NULL | NULL |
| 2 | GEHWOL Classic | 1 | gehwol-classic.html |
| 3 | GEHWOL MED® | 1 | gehwol-med.html |
| 4 | GEHWOL FUSSKRAFT | 1 | gehwol-fusskraft.html |
| 5 | GEHWOL FUSSKRAFT Soft Feet | 1 | gehwol-fusskraft-soft-feet.html |
| 6 | GEHWOL PROFESSIONAL | 1 | gehwol-professional.html |
| 7 | GERLASAN | 1 | gerlasan.html |
| 8 | GERLAVIT | 1 | gerlavit.html |
| 9 | Spiedienu uz pēdām mazinoši līdzekļi | NULL | NULL |
| 10 | Polimēra gēla izstrādājumi, pārvilkti ar tekstilu | 9 | polimera-gela-izstradajumi-parvilkti-ar-tekstilu.html |
| 11 | Polimēra gēla izstrādājumi | 9 | polimera-gela-izstradajumi.html |
| 12 | Plāksteri | 9 | plaksteri.html |
| 13 | Filca izstrādājumi | 9 | filca-izstradajumi.html |
| 14 | Tehnika | NULL | NULL |
| 15 | Pēdu kopšanas aparāti | 14 | pedu-kopsanas-aparati.html |
| 16 | Pacienta krēsli | 14 | pacienta-kresli.html |
| 17 | Darbinieka krēsli | 14 | darbinieka-kresli.html |
| 18 | Rotējošie instrumenti | 14 | NULL |
| 19 | Keramiskās frēzes | 18 | keramiskas-frezes.html |
| 20 | Pulētāji | 18 | puletaji.html |
| 21 | Vienreizlietojamās smilšpapīra frēzes | 18 | vienreizlietojamas-smilspapira-frezes.html |

- [ ] **Step 1: Create `php/migrations/001_init.sql`**

```sql
-- Run once against a fresh MySQL/MariaDB database, e.g.:
--   mysql -u root -p gehwol_lv < php/migrations/001_init.sql
--
-- After running, create the first admin user:
--   1) generate a hash locally:
--      php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT), PHP_EOL;"
--   2) insert it:
--      INSERT INTO admin_users (username, password_hash) VALUES ('admin', '<paste hash here>');

CREATE TABLE categories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  parent_id INT NULL,
  name VARCHAR(255) NOT NULL,
  link_url VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (parent_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
  id INT PRIMARY KEY AUTO_INCREMENT,
  category_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  image VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE news (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  date DATE NULL,
  text TEXT NULL,
  image VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE articles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  text TEXT NULL,
  image VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (id, parent_id, name, link_url, sort_order) VALUES
(1,  NULL, 'Kosmētika', NULL, 1),
(2,  1,    'GEHWOL Classic', 'gehwol-classic.html', 1),
(3,  1,    'GEHWOL MED®', 'gehwol-med.html', 2),
(4,  1,    'GEHWOL FUSSKRAFT', 'gehwol-fusskraft.html', 3),
(5,  1,    'GEHWOL FUSSKRAFT Soft Feet', 'gehwol-fusskraft-soft-feet.html', 4),
(6,  1,    'GEHWOL PROFESSIONAL', 'gehwol-professional.html', 5),
(7,  1,    'GERLASAN', 'gerlasan.html', 6),
(8,  1,    'GERLAVIT', 'gerlavit.html', 7),
(9,  NULL, 'Spiedienu uz pēdām mazinoši līdzekļi', NULL, 2),
(10, 9,    'Polimēra gēla izstrādājumi, pārvilkti ar tekstilu', 'polimera-gela-izstradajumi-parvilkti-ar-tekstilu.html', 1),
(11, 9,    'Polimēra gēla izstrādājumi', 'polimera-gela-izstradajumi.html', 2),
(12, 9,    'Plāksteri', 'plaksteri.html', 3),
(13, 9,    'Filca izstrādājumi', 'filca-izstradajumi.html', 4),
(14, NULL, 'Tehnika', NULL, 3),
(15, 14,   'Pēdu kopšanas aparāti', 'pedu-kopsanas-aparati.html', 1),
(16, 14,   'Pacienta krēsli', 'pacienta-kresli.html', 2),
(17, 14,   'Darbinieka krēsli', 'darbinieka-kresli.html', 3),
(18, 14,   'Rotējošie instrumenti', NULL, 4),
(19, 18,   'Keramiskās frēzes', 'keramiskas-frezes.html', 1),
(20, 18,   'Pulētāji', 'puletaji.html', 2),
(21, 18,   'Vienreizlietojamās smilšpapīra frēzes', 'vienreizlietojamas-smilspapira-frezes.html', 3);

ALTER TABLE categories AUTO_INCREMENT = 22;
```

- [ ] **Step 2: Create a local database and run the migration**

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS gehwol_lv CHARACTER SET utf8mb4"
mysql -u root gehwol_lv < php/migrations/001_init.sql
```

Adjust the `-u root` / add `-p` to match your local MySQL/MariaDB setup (e.g. XAMPP's default root user has no password).

- [ ] **Step 3: Create the first admin user**

```bash
php -r "echo password_hash('changeme123', PASSWORD_DEFAULT), PHP_EOL;"
```

Copy the printed hash, then:

```bash
mysql -u root gehwol_lv -e "INSERT INTO admin_users (username, password_hash) VALUES ('admin', '<paste hash here>')"
```

- [ ] **Step 4: Create local `php/config.php` (gitignored, not committed)**

```php
<?php

return [
    'host' => '127.0.0.1',
    'dbname' => 'gehwol_lv',
    'user' => 'root',
    'pass' => '',
];
```

- [ ] **Step 5: Create `php/includes/db.php`**

```php
<?php

function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = require __DIR__ . '/../config.php';
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}
```

- [ ] **Step 6: Verify the connection and seed data**

```bash
php -r "require 'php/includes/db.php'; echo get_pdo()->query('SELECT COUNT(*) FROM categories')->fetchColumn(), PHP_EOL;"
```

Expected: `21`

- [ ] **Step 7: Commit**

```bash
git add php/migrations/001_init.sql php/includes/db.php
git commit -m "feat: add DB schema, category seed data, PDO connector"
```

(`php/config.php` stays uncommitted — it's gitignored.)

---

### Task 3: `includes/validation.php` — pure validation helpers (TDD)

**Files:**
- Create: `php/includes/validation.php`
- Test: `tests/ValidationTest.php`

**Interfaces:**
- Produces: `required_field_errors(array $data, array $requiredFields): array` — returns a list of human-readable error strings (empty array = valid).
- Produces: `max_length_errors(array $data, array $fieldLimits): array` — `$fieldLimits` is `['fieldName' => intLimit]`.
- Consumed by: admin CRUD pages in Tasks 8–11.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/ValidationTest.php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/includes/validation.php';

final class ValidationTest extends TestCase
{
    public function test_required_field_errors_flags_missing_and_blank_fields(): void
    {
        $errors = required_field_errors(['name' => '', 'other' => 'x'], ['name', 'missing']);
        $this->assertCount(2, $errors);
    }

    public function test_required_field_errors_passes_when_all_present(): void
    {
        $errors = required_field_errors(['name' => 'GEHWOL'], ['name']);
        $this->assertSame([], $errors);
    }

    public function test_max_length_errors_flags_too_long_value(): void
    {
        $errors = max_length_errors(['name' => str_repeat('a', 300)], ['name' => 255]);
        $this->assertCount(1, $errors);
    }

    public function test_max_length_errors_passes_at_exact_limit(): void
    {
        $errors = max_length_errors(['name' => str_repeat('a', 255)], ['name' => 255]);
        $this->assertSame([], $errors);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit tests/ValidationTest.php
```

Expected: Error — `require_once` fails or "Call to undefined function required_field_errors()" (the file doesn't exist yet).

- [ ] **Step 3: Write the implementation**

```php
<?php
// php/includes/validation.php

function required_field_errors(array $data, array $requiredFields): array
{
    $errors = [];
    foreach ($requiredFields as $field) {
        $value = trim((string)($data[$field] ?? ''));
        if ($value === '') {
            $errors[] = "Lauks '{$field}' ir obligāts.";
        }
    }
    return $errors;
}

function max_length_errors(array $data, array $fieldLimits): array
{
    $errors = [];
    foreach ($fieldLimits as $field => $limit) {
        $value = (string)($data[$field] ?? '');
        if (mb_strlen($value) > $limit) {
            $errors[] = "Lauks '{$field}' nedrīkst pārsniegt {$limit} rakstzīmes.";
        }
    }
    return $errors;
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit tests/ValidationTest.php
```

Expected: `OK (4 tests, 4 assertions)`

- [ ] **Step 5: Commit**

```bash
git add php/includes/validation.php tests/ValidationTest.php
git commit -m "feat: add pure validation helpers for admin forms"
```

---

### Task 4: `includes/upload.php` — image upload validation (TDD)

**Files:**
- Create: `php/includes/upload.php`
- Test: `tests/UploadTest.php`

**Interfaces:**
- Produces: `has_allowed_extension(string $filename): bool`, `is_allowed_mime(string $mime): bool`, `is_under_size_limit(int $bytes): bool`, `generate_upload_filename(string $originalName): string` — all pure, unit-tested.
- Produces: `save_uploaded_image(array $file, string $destDir): ?string` — impure (moves a real file), used by Tasks 9–11, not unit-tested (needs a real uploaded temp file; covered by this plan's manual verification steps instead).
- Consumes: nothing.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/UploadTest.php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/includes/upload.php';

final class UploadTest extends TestCase
{
    public function test_has_allowed_extension_accepts_known_image_types(): void
    {
        $this->assertTrue(has_allowed_extension('photo.jpg'));
        $this->assertTrue(has_allowed_extension('photo.JPEG'));
        $this->assertTrue(has_allowed_extension('photo.webp'));
    }

    public function test_has_allowed_extension_rejects_others(): void
    {
        $this->assertFalse(has_allowed_extension('script.php'));
        $this->assertFalse(has_allowed_extension('archive.zip'));
    }

    public function test_is_allowed_mime_accepts_known_mime_types(): void
    {
        $this->assertTrue(is_allowed_mime('image/jpeg'));
        $this->assertFalse(is_allowed_mime('application/x-php'));
    }

    public function test_is_under_size_limit(): void
    {
        $this->assertTrue(is_under_size_limit(1024));
        $this->assertFalse(is_under_size_limit(0));
        $this->assertFalse(is_under_size_limit(10 * 1024 * 1024));
    }

    public function test_generate_upload_filename_preserves_extension_and_is_unique(): void
    {
        $a = generate_upload_filename('My Photo.PNG');
        $b = generate_upload_filename('My Photo.PNG');
        $this->assertStringEndsWith('.png', $a);
        $this->assertNotSame($a, $b);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit tests/UploadTest.php
```

Expected: Error — undefined function `has_allowed_extension()`.

- [ ] **Step 3: Write the implementation**

```php
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
    $mime = mime_content_type($file['tmp_name']);
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
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit tests/UploadTest.php
```

Expected: `OK (5 tests, 8 assertions)`

- [ ] **Step 5: Commit**

```bash
git add php/includes/upload.php tests/UploadTest.php
git commit -m "feat: add image upload validation helpers"
```

---

### Task 5: `includes/links.php` — listing link resolution (TDD)

**Files:**
- Create: `php/includes/links.php`
- Test: `tests/LinksTest.php`

**Interfaces:**
- Produces: `category_link(array $category): string` — `$category` needs `id` and `link_url` keys; returns `link_url` verbatim if set (legacy row), else `"category.php?id={id}"`.
- Produces: `product_link(int $id): string`, `news_link(int $id): string`, `article_link(int $id): string` — always `"{name}.php?id={id}"`.
- Consumed by: `api.php` (Task 12), all 4 public detail pages (Task 13), admin categories page (Task 8).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/LinksTest.php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/includes/links.php';

final class LinksTest extends TestCase
{
    public function test_category_link_uses_static_file_when_legacy(): void
    {
        $link = category_link(['id' => 2, 'link_url' => 'gehwol-classic.html']);
        $this->assertSame('gehwol-classic.html', $link);
    }

    public function test_category_link_uses_generated_page_when_live(): void
    {
        $link = category_link(['id' => 22, 'link_url' => null]);
        $this->assertSame('category.php?id=22', $link);
    }

    public function test_product_link_format(): void
    {
        $this->assertSame('product.php?id=5', product_link(5));
    }

    public function test_news_link_format(): void
    {
        $this->assertSame('news.php?id=5', news_link(5));
    }

    public function test_article_link_format(): void
    {
        $this->assertSame('article.php?id=5', article_link(5));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit tests/LinksTest.php
```

Expected: Error — undefined function `category_link()`.

- [ ] **Step 3: Write the implementation**

```php
<?php
// php/includes/links.php

function category_link(array $category): string
{
    if (!empty($category['link_url'])) {
        return $category['link_url'];
    }
    return 'category.php?id=' . (int)$category['id'];
}

function product_link(int $id): string
{
    return 'product.php?id=' . $id;
}

function news_link(int $id): string
{
    return 'news.php?id=' . $id;
}

function article_link(int $id): string
{
    return 'article.php?id=' . $id;
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit tests/LinksTest.php
```

Expected: `OK (5 tests, 5 assertions)`

- [ ] **Step 5: Commit**

```bash
git add php/includes/links.php tests/LinksTest.php
git commit -m "feat: add listing link resolution helpers"
```

---

### Task 6: `includes/auth.php` — session guard and credential check (TDD + manual)

**Files:**
- Create: `php/includes/auth.php`
- Test: `tests/AuthTest.php`

**Interfaces:**
- Consumes: `get_pdo()` from `php/includes/db.php` (Task 2).
- Produces: `verify_credentials(?array $userRow, string $password): bool` — pure, unit-tested.
- Produces: `find_admin_by_username(string $username): ?array` — hits the DB, manually verified.
- Produces: `require_login(): void` — starts/checks `$_SESSION['admin_id']`, redirects to `login.php` if absent. Used at the top of every `php/admin/*.php` file except `login.php`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/AuthTest.php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/includes/auth.php';

final class AuthTest extends TestCase
{
    public function test_verify_credentials_accepts_correct_password(): void
    {
        $row = ['id' => 1, 'username' => 'admin', 'password_hash' => password_hash('secret123', PASSWORD_DEFAULT)];
        $this->assertTrue(verify_credentials($row, 'secret123'));
    }

    public function test_verify_credentials_rejects_wrong_password(): void
    {
        $row = ['id' => 1, 'username' => 'admin', 'password_hash' => password_hash('secret123', PASSWORD_DEFAULT)];
        $this->assertFalse(verify_credentials($row, 'wrong'));
    }

    public function test_verify_credentials_rejects_missing_user(): void
    {
        $this->assertFalse(verify_credentials(null, 'anything'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit tests/AuthTest.php
```

Expected: Error — undefined function `verify_credentials()`.

- [ ] **Step 3: Write the implementation**

```php
<?php
// php/includes/auth.php

require_once __DIR__ . '/db.php';

function verify_credentials(?array $userRow, string $password): bool
{
    if ($userRow === null) {
        return false;
    }
    return password_verify($password, $userRow['password_hash']);
}

function find_admin_by_username(string $username): ?array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function require_login(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit tests/AuthTest.php
```

Expected: `OK (3 tests, 3 assertions)`

- [ ] **Step 5: Manually verify `find_admin_by_username` against the real DB**

```bash
php -r "require 'php/includes/auth.php'; var_dump(find_admin_by_username('admin') !== null); var_dump(find_admin_by_username('nobody'));"
```

Expected: `bool(true)` then `NULL`.

- [ ] **Step 6: Commit**

```bash
git add php/includes/auth.php tests/AuthTest.php
git commit -m "feat: add session auth guard and credential verification"
```

---

### Task 7: Admin shell — layout, login, logout, dashboard

**Files:**
- Create: `php/admin/includes/layout.php`
- Create: `php/admin/login.php`
- Create: `php/admin/logout.php`
- Create: `php/admin/index.php`

**Interfaces:**
- Consumes: `require_login()`, `find_admin_by_username()`, `verify_credentials()` (Task 6); `get_pdo()` (Task 2).
- Produces: `admin_header(string $title): void`, `admin_footer(): void` — every CRUD page in Tasks 8–11 wraps its body in these.

- [ ] **Step 1: Create `php/admin/includes/layout.php`**

```php
<?php

function admin_header(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title) ?> — Admin</title>
<style>
body{font-family:sans-serif;max-width:900px;margin:2rem auto;padding:0 1rem;}
nav a{margin-right:1rem;}
table{border-collapse:collapse;width:100%;margin-top:1rem;}
th,td{border:1px solid #ccc;padding:0.4rem;text-align:left;}
.error{color:#b00020;}
</style>
</head>
<body>
<nav>
  <a href="index.php">Sākums</a>
  <a href="categories.php">Kategorijas</a>
  <a href="products.php">Produkti</a>
  <a href="news.php">Jaunumi</a>
  <a href="articles.php">Raksti</a>
  <a href="logout.php">Iziet</a>
</nav>
<h1><?= htmlspecialchars($title) ?></h1>
<?php
}

function admin_footer(): void
{
    ?>
</body>
</html>
<?php
}
```

- [ ] **Step 2: Create `php/admin/login.php`**

```php
<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

session_start();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $row = find_admin_by_username($username);
    if (verify_credentials($row, $password)) {
        $_SESSION['admin_id'] = $row['id'];
        header('Location: index.php');
        exit;
    }
    $error = 'Nepareizs lietotājvārds vai parole.';
}
?>
<!DOCTYPE html>
<html lang="lv">
<head><meta charset="UTF-8"><title>Ielogoties — Admin</title></head>
<body>
<h1>Ielogoties</h1>
<?php if ($error): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
  <label>Lietotājvārds: <input type="text" name="username" required></label><br>
  <label>Parole: <input type="password" name="password" required></label><br>
  <button type="submit">Ielogoties</button>
</form>
</body>
</html>
```

- [ ] **Step 3: Create `php/admin/logout.php`**

```php
<?php

session_start();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
```

- [ ] **Step 4: Create `php/admin/index.php`**

```php
<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

require_login();
admin_header('Vadības panelis');
?>
<ul>
  <li><a href="categories.php">Kategorijas</a></li>
  <li><a href="products.php">Produkti</a></li>
  <li><a href="news.php">Jaunumi</a></li>
  <li><a href="articles.php">Raksti</a></li>
</ul>
<?php
admin_footer();
```

- [ ] **Step 5: Manually verify the auth flow**

```bash
php -S localhost:8000
```

In a browser (or `curl -i`):
1. Visit `http://localhost:8000/php/admin/index.php` → expect redirect to `login.php` (no session yet).
2. Log in with `admin` / `changeme123` (from Task 2) → expect redirect to `index.php`, showing the 4 nav links.
3. Visit `http://localhost:8000/php/admin/logout.php` → expect redirect to `login.php`; visiting `index.php` again redirects back to `login.php`.

Stop the server (Ctrl+C) when done.

- [ ] **Step 6: Commit**

```bash
git add php/admin/includes/layout.php php/admin/login.php php/admin/logout.php php/admin/index.php
git commit -m "feat: add admin login/logout and dashboard shell"
```

---

### Task 8: Categories CRUD

**Files:**
- Create: `php/admin/categories.php`

**Interfaces:**
- Consumes: `require_login()` (Task 6), `get_pdo()` (Task 2), `required_field_errors()`/`max_length_errors()` (Task 3), `admin_header()`/`admin_footer()` (Task 7).
- Note: any category can be selected as a parent for another (no leaf/non-leaf restriction enforced in v1 — a deliberate simplification; the admin is trusted to pick sensibly, same as they'd have to reason about the existing 3-group/17-line/1-subgroup tree manually today).

- [ ] **Step 1: Create `php/admin/categories.php`**

```php
<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$pdo = get_pdo();
$action = $_GET['action'] ?? 'list';
$errors = [];

function category_has_children(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = ?');
    $stmt->execute([$id]);
    return (int)$stmt->fetchColumn() > 0;
}

function category_has_products(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
    $stmt->execute([$id]);
    return (int)$stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true)) {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'parent_id' => $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null,
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
    ];
    $errors = required_field_errors($data, ['name']);
    $errors = array_merge($errors, max_length_errors($data, ['name' => 255]));

    if (!$errors) {
        if ($action === 'add') {
            $stmt = $pdo->prepare('INSERT INTO categories (name, parent_id, sort_order) VALUES (?, ?, ?)');
            $stmt->execute([$data['name'], $data['parent_id'], $data['sort_order']]);
        } else {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare('UPDATE categories SET name = ?, parent_id = ?, sort_order = ? WHERE id = ?');
            $stmt->execute([$data['name'], $data['parent_id'], $data['sort_order'], $id]);
        }
        header('Location: categories.php');
        exit;
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if (category_has_children($pdo, $id) || category_has_products($pdo, $id)) {
        $errors[] = 'Nevar dzēst kategoriju, kurai ir apakškategorijas vai produkti.';
        $action = 'list';
    } else {
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: categories.php');
        exit;
    }
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY parent_id IS NULL DESC, parent_id, sort_order')->fetchAll();

$editing = null;
if ($action === 'edit' && isset($_GET['id'])) {
    foreach ($categories as $c) {
        if ($c['id'] == $_GET['id']) {
            $editing = $c;
            break;
        }
    }
}

admin_header('Kategorijas');
foreach ($errors as $e) {
    echo '<p class="error">' . htmlspecialchars($e) . '</p>';
}
?>
<table>
<tr><th>ID</th><th>Nosaukums</th><th>Vecāks</th><th>Saite</th><th>Kārtība</th><th></th></tr>
<?php foreach ($categories as $c):
    $parentName = '—';
    foreach ($categories as $p) {
        if ($p['id'] == $c['parent_id']) {
            $parentName = $p['name'];
            break;
        }
    }
?>
<tr>
  <td><?= (int)$c['id'] ?></td>
  <td><?= htmlspecialchars($c['name']) ?></td>
  <td><?= htmlspecialchars($parentName) ?></td>
  <td><?= htmlspecialchars($c['link_url'] ?? '(admin)') ?></td>
  <td><?= (int)$c['sort_order'] ?></td>
  <td>
    <a href="categories.php?action=edit&id=<?= (int)$c['id'] ?>">Rediģēt</a>
    <a href="categories.php?action=delete&id=<?= (int)$c['id'] ?>" onclick="return confirm('Dzēst?')">Dzēst</a>
  </td>
</tr>
<?php endforeach; ?>
</table>

<h2><?= $editing ? 'Rediģēt kategoriju' : 'Pievienot kategoriju' ?></h2>
<form method="post" action="categories.php?action=<?= $editing ? 'edit' : 'add' ?>">
  <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
  <label>Nosaukums: <input type="text" name="name" value="<?= htmlspecialchars($editing['name'] ?? '') ?>" required></label><br>
  <label>Vecāks:
    <select name="parent_id">
      <option value="">— nav (galvenā kategorija) —</option>
      <?php foreach ($categories as $c):
          if ($editing && $c['id'] == $editing['id']) {
              continue;
          }
      ?>
      <option value="<?= (int)$c['id'] ?>" <?= ($editing && $editing['parent_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label><br>
  <label>Kārtība: <input type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>"></label><br>
  <button type="submit"><?= $editing ? 'Saglabāt' : 'Pievienot' ?></button>
</form>
<?php admin_footer(); ?>
```

- [ ] **Step 2: Manually verify**

```bash
php -S localhost:8000
```

1. Log in, visit `http://localhost:8000/php/admin/categories.php` → expect a table with 21 rows (ids 1–21 matching Task 2's seed table).
2. Add a category named "Test Line" with parent "Kosmētika" → expect redirect back to the list, new row visible with id 22, parent "Kosmētika".
3. Try deleting category id 1 ("Kosmētika") → expect the error "Nevar dzēst kategoriju, kurai ir apakškategorijas vai produkti." (it has children).
4. Delete the "Test Line" row you just added → expect it to disappear (no children/products, deletion succeeds).

- [ ] **Step 3: Commit**

```bash
git add php/admin/categories.php
git commit -m "feat: add categories CRUD to admin panel"
```

---

### Task 9: Products CRUD

**Files:**
- Create: `php/admin/products.php`

**Interfaces:**
- Consumes: `require_login()`, `get_pdo()`, `required_field_errors()`/`max_length_errors()`, `save_uploaded_image()` (Task 4), `admin_header()`/`admin_footer()`.
- "Leaf category" for the dropdown = any category id that is never used as another category's `parent_id` (computed in PHP, not a stored flag).

- [ ] **Step 1: Create `php/admin/products.php`**

```php
<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$pdo = get_pdo();
$action = $_GET['action'] ?? 'list';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true)) {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'description' => trim($_POST['description'] ?? ''),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
    ];
    $errors = required_field_errors($data, ['name']);
    $errors = array_merge($errors, max_length_errors($data, ['name' => 255]));
    if ($data['category_id'] <= 0) {
        $errors[] = "Lauks 'category_id' ir obligāts.";
    }

    $imageName = $_POST['existing_image'] !== '' ? $_POST['existing_image'] : null;
    if (!empty($_FILES['image']['name'])) {
        $saved = save_uploaded_image($_FILES['image'], __DIR__ . '/../../uploads/products');
        if ($saved === null) {
            $errors[] = 'Neizdevās augšupielādēt attēlu (pārbaudi formātu un izmēru, maks. 5 MB).';
        } else {
            $imageName = $saved;
        }
    }

    if (!$errors) {
        if ($action === 'add') {
            $stmt = $pdo->prepare('INSERT INTO products (category_id, name, description, image, sort_order) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$data['category_id'], $data['name'], $data['description'], $imageName, $data['sort_order']]);
        } else {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare('UPDATE products SET category_id = ?, name = ?, description = ?, image = ?, sort_order = ? WHERE id = ?');
            $stmt->execute([$data['category_id'], $data['name'], $data['description'], $imageName, $data['sort_order'], $id]);
        }
        header('Location: products.php');
        exit;
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: products.php');
    exit;
}

$allCategories = $pdo->query('SELECT id, parent_id, name FROM categories ORDER BY name')->fetchAll();
$parentIds = array_column($allCategories, 'parent_id');
$leafCategories = array_values(array_filter(
    $allCategories,
    fn($c) => !in_array($c['id'], $parentIds, true)
));

$products = $pdo->query('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id ORDER BY p.sort_order, p.id')->fetchAll();

$editing = null;
if ($action === 'edit' && isset($_GET['id'])) {
    foreach ($products as $p) {
        if ($p['id'] == $_GET['id']) {
            $editing = $p;
            break;
        }
    }
}

admin_header('Produkti');
foreach ($errors as $e) {
    echo '<p class="error">' . htmlspecialchars($e) . '</p>';
}
?>
<table>
<tr><th>ID</th><th>Nosaukums</th><th>Kategorija</th><th>Attēls</th><th></th></tr>
<?php foreach ($products as $p): ?>
<tr>
  <td><?= (int)$p['id'] ?></td>
  <td><?= htmlspecialchars($p['name']) ?></td>
  <td><?= htmlspecialchars($p['category_name']) ?></td>
  <td><?= $p['image'] ? htmlspecialchars($p['image']) : '—' ?></td>
  <td>
    <a href="products.php?action=edit&id=<?= (int)$p['id'] ?>">Rediģēt</a>
    <a href="products.php?action=delete&id=<?= (int)$p['id'] ?>" onclick="return confirm('Dzēst?')">Dzēst</a>
  </td>
</tr>
<?php endforeach; ?>
</table>

<h2><?= $editing ? 'Rediģēt produktu' : 'Pievienot produktu' ?></h2>
<form method="post" action="products.php?action=<?= $editing ? 'edit' : 'add' ?>" enctype="multipart/form-data">
  <?php if ($editing): ?>
    <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
    <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editing['image'] ?? '') ?>">
  <?php endif; ?>
  <label>Nosaukums: <input type="text" name="name" value="<?= htmlspecialchars($editing['name'] ?? '') ?>" required></label><br>
  <label>Kategorija:
    <select name="category_id" required>
      <option value="">— izvēlies —</option>
      <?php foreach ($leafCategories as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= ($editing && $editing['category_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label><br>
  <label>Apraksts: <textarea name="description" rows="4" cols="50"><?= htmlspecialchars($editing['description'] ?? '') ?></textarea></label><br>
  <label>Attēls: <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></label><br>
  <label>Kārtība: <input type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>"></label><br>
  <button type="submit"><?= $editing ? 'Saglabāt' : 'Pievienot' ?></button>
</form>
<?php admin_footer(); ?>
```

- [ ] **Step 2: Manually verify**

```bash
php -S localhost:8000
```

1. Log in, visit `http://localhost:8000/php/admin/products.php` → expect an empty product list and a category dropdown containing only leaf categories (the 17 seeded lines — not "Kosmētika", "Tehnika", "Spiedienu...", or "Rotējošie instrumenti", since those are parents).
2. Add a product named "Test Product" under "GEHWOL Classic" with a small `.jpg` as the image → expect redirect back to the list, row visible with the category name and the generated image filename; confirm the file landed in `uploads/products/`.
3. Edit it, change the name, save → expect the updated name in the list.
4. Delete it → expect it to disappear and the uploaded file can be left orphaned (per spec, deletion doesn't need to clean up the file).

- [ ] **Step 3: Commit**

```bash
git add php/admin/products.php
git commit -m "feat: add products CRUD to admin panel"
```

---

### Task 10: News CRUD

**Files:**
- Create: `php/admin/news.php`

**Interfaces:**
- Consumes: same helpers as Task 9, minus category logic; adds a `date` field.

- [ ] **Step 1: Create `php/admin/news.php`**

```php
<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$pdo = get_pdo();
$action = $_GET['action'] ?? 'list';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true)) {
    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'date' => trim($_POST['date'] ?? ''),
        'text' => trim($_POST['text'] ?? ''),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
    ];
    $errors = required_field_errors($data, ['title']);
    $errors = array_merge($errors, max_length_errors($data, ['title' => 255]));

    $imageName = $_POST['existing_image'] !== '' ? $_POST['existing_image'] : null;
    if (!empty($_FILES['image']['name'])) {
        $saved = save_uploaded_image($_FILES['image'], __DIR__ . '/../../uploads/news');
        if ($saved === null) {
            $errors[] = 'Neizdevās augšupielādēt attēlu (pārbaudi formātu un izmēru, maks. 5 MB).';
        } else {
            $imageName = $saved;
        }
    }

    if (!$errors) {
        $dateValue = $data['date'] !== '' ? $data['date'] : null;
        if ($action === 'add') {
            $stmt = $pdo->prepare('INSERT INTO news (title, date, text, image, sort_order) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$data['title'], $dateValue, $data['text'], $imageName, $data['sort_order']]);
        } else {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare('UPDATE news SET title = ?, date = ?, text = ?, image = ?, sort_order = ? WHERE id = ?');
            $stmt->execute([$data['title'], $dateValue, $data['text'], $imageName, $data['sort_order'], $id]);
        }
        header('Location: news.php');
        exit;
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('DELETE FROM news WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: news.php');
    exit;
}

$items = $pdo->query('SELECT * FROM news ORDER BY sort_order, id')->fetchAll();

$editing = null;
if ($action === 'edit' && isset($_GET['id'])) {
    foreach ($items as $i) {
        if ($i['id'] == $_GET['id']) {
            $editing = $i;
            break;
        }
    }
}

admin_header('Jaunumi');
foreach ($errors as $e) {
    echo '<p class="error">' . htmlspecialchars($e) . '</p>';
}
?>
<table>
<tr><th>ID</th><th>Nosaukums</th><th>Datums</th><th>Attēls</th><th></th></tr>
<?php foreach ($items as $i): ?>
<tr>
  <td><?= (int)$i['id'] ?></td>
  <td><?= htmlspecialchars($i['title']) ?></td>
  <td><?= htmlspecialchars($i['date'] ?? '—') ?></td>
  <td><?= $i['image'] ? htmlspecialchars($i['image']) : '—' ?></td>
  <td>
    <a href="news.php?action=edit&id=<?= (int)$i['id'] ?>">Rediģēt</a>
    <a href="news.php?action=delete&id=<?= (int)$i['id'] ?>" onclick="return confirm('Dzēst?')">Dzēst</a>
  </td>
</tr>
<?php endforeach; ?>
</table>

<h2><?= $editing ? 'Rediģēt jaunumu' : 'Pievienot jaunumu' ?></h2>
<form method="post" action="news.php?action=<?= $editing ? 'edit' : 'add' ?>" enctype="multipart/form-data">
  <?php if ($editing): ?>
    <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
    <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editing['image'] ?? '') ?>">
  <?php endif; ?>
  <label>Nosaukums: <input type="text" name="title" value="<?= htmlspecialchars($editing['title'] ?? '') ?>" required></label><br>
  <label>Datums: <input type="date" name="date" value="<?= htmlspecialchars($editing['date'] ?? '') ?>"></label><br>
  <label>Teksts: <textarea name="text" rows="4" cols="50"><?= htmlspecialchars($editing['text'] ?? '') ?></textarea></label><br>
  <label>Attēls: <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></label><br>
  <label>Kārtība: <input type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>"></label><br>
  <button type="submit"><?= $editing ? 'Saglabāt' : 'Pievienot' ?></button>
</form>
<?php admin_footer(); ?>
```

- [ ] **Step 2: Manually verify**

```bash
php -S localhost:8000
```

Log in, visit `http://localhost:8000/php/admin/news.php`, add an item with title/date/text/image, confirm it lists correctly, edit it, delete it — same flow as Task 9's verification.

- [ ] **Step 3: Commit**

```bash
git add php/admin/news.php
git commit -m "feat: add news CRUD to admin panel"
```

---

### Task 11: Articles CRUD

**Files:**
- Create: `php/admin/articles.php`

**Interfaces:**
- Same as Task 10 minus the `date` field.

- [ ] **Step 1: Create `php/admin/articles.php`**

```php
<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$pdo = get_pdo();
$action = $_GET['action'] ?? 'list';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true)) {
    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'text' => trim($_POST['text'] ?? ''),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
    ];
    $errors = required_field_errors($data, ['title']);
    $errors = array_merge($errors, max_length_errors($data, ['title' => 255]));

    $imageName = $_POST['existing_image'] !== '' ? $_POST['existing_image'] : null;
    if (!empty($_FILES['image']['name'])) {
        $saved = save_uploaded_image($_FILES['image'], __DIR__ . '/../../uploads/articles');
        if ($saved === null) {
            $errors[] = 'Neizdevās augšupielādēt attēlu (pārbaudi formātu un izmēru, maks. 5 MB).';
        } else {
            $imageName = $saved;
        }
    }

    if (!$errors) {
        if ($action === 'add') {
            $stmt = $pdo->prepare('INSERT INTO articles (title, text, image, sort_order) VALUES (?, ?, ?, ?)');
            $stmt->execute([$data['title'], $data['text'], $imageName, $data['sort_order']]);
        } else {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare('UPDATE articles SET title = ?, text = ?, image = ?, sort_order = ? WHERE id = ?');
            $stmt->execute([$data['title'], $data['text'], $imageName, $data['sort_order'], $id]);
        }
        header('Location: articles.php');
        exit;
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('DELETE FROM articles WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: articles.php');
    exit;
}

$items = $pdo->query('SELECT * FROM articles ORDER BY sort_order, id')->fetchAll();

$editing = null;
if ($action === 'edit' && isset($_GET['id'])) {
    foreach ($items as $i) {
        if ($i['id'] == $_GET['id']) {
            $editing = $i;
            break;
        }
    }
}

admin_header('Raksti');
foreach ($errors as $e) {
    echo '<p class="error">' . htmlspecialchars($e) . '</p>';
}
?>
<table>
<tr><th>ID</th><th>Nosaukums</th><th>Attēls</th><th></th></tr>
<?php foreach ($items as $i): ?>
<tr>
  <td><?= (int)$i['id'] ?></td>
  <td><?= htmlspecialchars($i['title']) ?></td>
  <td><?= $i['image'] ? htmlspecialchars($i['image']) : '—' ?></td>
  <td>
    <a href="articles.php?action=edit&id=<?= (int)$i['id'] ?>">Rediģēt</a>
    <a href="articles.php?action=delete&id=<?= (int)$i['id'] ?>" onclick="return confirm('Dzēst?')">Dzēst</a>
  </td>
</tr>
<?php endforeach; ?>
</table>

<h2><?= $editing ? 'Rediģēt rakstu' : 'Pievienot rakstu' ?></h2>
<form method="post" action="articles.php?action=<?= $editing ? 'edit' : 'add' ?>" enctype="multipart/form-data">
  <?php if ($editing): ?>
    <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
    <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editing['image'] ?? '') ?>">
  <?php endif; ?>
  <label>Nosaukums: <input type="text" name="title" value="<?= htmlspecialchars($editing['title'] ?? '') ?>" required></label><br>
  <label>Teksts: <textarea name="text" rows="4" cols="50"><?= htmlspecialchars($editing['text'] ?? '') ?></textarea></label><br>
  <label>Attēls: <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></label><br>
  <label>Kārtība: <input type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>"></label><br>
  <button type="submit"><?= $editing ? 'Saglabāt' : 'Pievienot' ?></button>
</form>
<?php admin_footer(); ?>
```

- [ ] **Step 2: Manually verify**

Same flow as Task 10, against `http://localhost:8000/php/admin/articles.php`.

- [ ] **Step 3: Commit**

```bash
git add php/admin/articles.php
git commit -m "feat: add articles CRUD to admin panel"
```

---

### Task 12: `api.php` — public read-only JSON endpoint

**Files:**
- Create: `php/api.php`

**Interfaces:**
- Consumes: `get_pdo()` (Task 2), `product_link()`/`news_link()`/`article_link()`/`category_link()` (Task 5).
- Produces JSON shapes consumed by the JS module in Task 17:
  - `?type=products[&category_id=N]` → `[{id, category_id, name, image, link}]`
  - `?type=news` → `[{id, title, image, link}]`
  - `?type=articles` → `[{id, title, image, link}]`
  - `?type=categories` → `[{id, parent_id, name, link}]` (only rows with `link_url IS NULL` — the seeded legacy rows are already in the static HTML tree, no need to duplicate them).

- [ ] **Step 1: Create `php/api.php`**

```php
<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/links.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = get_pdo();
$type = $_GET['type'] ?? '';

switch ($type) {
    case 'products':
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        if ($categoryId) {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE category_id = ? ORDER BY sort_order, id');
            $stmt->execute([$categoryId]);
        } else {
            $stmt = $pdo->query('SELECT * FROM products ORDER BY sort_order, id');
        }
        $out = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'category_id' => (int)$r['category_id'],
            'name' => $r['name'],
            'image' => $r['image'],
            'link' => product_link((int)$r['id']),
        ], $stmt->fetchAll());
        echo json_encode($out);
        break;

    case 'news':
        $rows = $pdo->query('SELECT * FROM news ORDER BY sort_order, id')->fetchAll();
        $out = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'title' => $r['title'],
            'image' => $r['image'],
            'link' => news_link((int)$r['id']),
        ], $rows);
        echo json_encode($out);
        break;

    case 'articles':
        $rows = $pdo->query('SELECT * FROM articles ORDER BY sort_order, id')->fetchAll();
        $out = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'title' => $r['title'],
            'image' => $r['image'],
            'link' => article_link((int)$r['id']),
        ], $rows);
        echo json_encode($out);
        break;

    case 'categories':
        $rows = $pdo->query('SELECT * FROM categories WHERE link_url IS NULL ORDER BY sort_order, id')->fetchAll();
        $out = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'parent_id' => $r['parent_id'] !== null ? (int)$r['parent_id'] : null,
            'name' => $r['name'],
            'link' => category_link($r),
        ], $rows);
        echo json_encode($out);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'unknown type']);
}
```

- [ ] **Step 2: Manually verify**

```bash
php -S localhost:8000
curl "http://localhost:8000/php/api.php?type=products"
curl "http://localhost:8000/php/api.php?type=products&category_id=2"
curl "http://localhost:8000/php/api.php?type=news"
curl "http://localhost:8000/php/api.php?type=articles"
curl "http://localhost:8000/php/api.php?type=categories"
curl "http://localhost:8000/php/api.php?type=bogus"
```

Expected: `[]` for the first 5 (no data added by these commands yet, unless you left the Task 9/10/11 test rows in — either way, valid JSON arrays, no PHP errors/warnings in the body); `{"error":"unknown type"}` with HTTP 400 for the last.

- [ ] **Step 3: Commit**

```bash
git add php/api.php
git commit -m "feat: add public read-only JSON API for products/news/articles/categories"
```

---

### Task 13: Public detail pages — product.php, category.php, news.php, article.php

**Files:**
- Create: `php/product.php`
- Create: `php/category.php`
- Create: `php/news.php`
- Create: `php/article.php`

**Interfaces:**
- Consumes: `get_pdo()`, `category_link()`.
- All four: read `?id=`, 404 with a plain message if missing, otherwise render using the exact same CSS classes as `blocks/product-detail-page.html` / `blocks/category-page.html` / `blocks/news-detail-page.html` / `blocks/article-detail-page.html` (so they look identical to the static pages once `css/main.css` loads). Paths are relative to `build/php/`, one level below `build/`, hence `../css/main.css`, `../index.html`, `../uploads/...`.

- [ ] **Step 1: Create `php/product.php`**

```php
<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/links.php';

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT p.*, c.name AS category_name, c.link_url AS category_link_url, c.id AS category_id FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    echo '<h1>Produkts nav atrasts</h1>';
    exit;
}

$categoryHref = htmlspecialchars(category_link(['id' => $product['category_id'], 'link_url' => $product['category_link_url']]));
?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= htmlspecialchars($product['name']) ?> — Gehwole</title>
<link rel="stylesheet" href="../css/main.css" />
<link rel="icon" type="image/x-icon" href="../img/favicons/favicon.ico">
</head>
<body>
<main>
<section class="category">
  <div class="container category__container">
    <nav class="category__breadcrumbs" aria-label="Breadcrumbs">
      <a href="../index.html" class="category__crumb">Sākums</a>
      <span class="category__crumb-sep">/</span>
      <a href="../index.html#products" class="category__crumb">Produkti</a>
      <span class="category__crumb-sep">/</span>
      <a href="<?= $categoryHref ?>" class="category__crumb"><?= htmlspecialchars($product['category_name']) ?></a>
      <span class="category__crumb-sep">/</span>
      <span class="category__crumb category__crumb--current"><?= htmlspecialchars($product['name']) ?></span>
    </nav>
    <h1 class="category__title"><?= htmlspecialchars($product['name']) ?></h1>
    <div class="product-detail">
      <div class="product-detail__media">
        <?php if ($product['image']): ?>
          <img src="../uploads/products/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
        <?php else: ?>
          <span class="product-detail__placeholder">GEHWOL</span>
        <?php endif; ?>
      </div>
      <p class="product-detail__description"><?= nl2br(htmlspecialchars($product['description'] ?? '')) ?></p>
      <a href="../index.html#contacts" class="product-detail__cta btn-link">Sazinieties, lai pasūtītu →</a>
    </div>
  </div>
</section>
</main>
</body>
</html>
```

- [ ] **Step 2: Create `php/category.php`**

```php
<?php

require_once __DIR__ . '/includes/db.php';

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    http_response_code(404);
    echo '<h1>Kategorija nav atrasta</h1>';
    exit;
}

$parentName = 'Produkti';
if ($category['parent_id']) {
    $pstmt = $pdo->prepare('SELECT name FROM categories WHERE id = ?');
    $pstmt->execute([$category['parent_id']]);
    $parentName = $pstmt->fetchColumn() ?: $parentName;
}

$stmt = $pdo->prepare('SELECT * FROM products WHERE category_id = ? ORDER BY sort_order, id');
$stmt->execute([$id]);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= htmlspecialchars($category['name']) ?> — Gehwole</title>
<link rel="stylesheet" href="../css/main.css" />
<link rel="icon" type="image/x-icon" href="../img/favicons/favicon.ico">
</head>
<body>
<main>
<section class="category">
  <div class="container category__container">
    <nav class="category__breadcrumbs" aria-label="Breadcrumbs">
      <a href="../index.html" class="category__crumb">Sākums</a>
      <span class="category__crumb-sep">/</span>
      <a href="../index.html#products" class="category__crumb">Produkti</a>
      <span class="category__crumb-sep">/</span>
      <span class="category__crumb"><?= htmlspecialchars($parentName) ?></span>
      <span class="category__crumb-sep">/</span>
      <span class="category__crumb category__crumb--current"><?= htmlspecialchars($category['name']) ?></span>
    </nav>
    <h1 class="category__title"><?= htmlspecialchars($category['name']) ?></h1>
    <div class="category__grid">
      <?php foreach ($products as $p): ?>
      <a href="product.php?id=<?= (int)$p['id'] ?>" class="product-card">
        <div class="product-card__media">
          <?php if ($p['image']): ?>
            <img src="../uploads/products/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
          <?php else: ?>
            <span class="product-card__placeholder">GEHWOL</span>
          <?php endif; ?>
        </div>
        <h3 class="product-card__title"><?= htmlspecialchars($p['name']) ?></h3>
        <span class="product-card__cta btn-link">Uzzināt vairāk →</span>
      </a>
      <?php endforeach; ?>
      <?php if (!$products): ?>
        <p>Šajā kategorijā vēl nav produktu.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
</main>
</body>
</html>
```

- [ ] **Step 3: Create `php/news.php`**

```php
<?php

require_once __DIR__ . '/includes/db.php';

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM news WHERE id = ?');
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    echo '<h1>Jaunums nav atrasts</h1>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= htmlspecialchars($item['title']) ?> — Gehwole</title>
<link rel="stylesheet" href="../css/main.css" />
<link rel="icon" type="image/x-icon" href="../img/favicons/favicon.ico">
</head>
<body>
<main>
<section class="category">
  <div class="container category__container">
    <nav class="category__breadcrumbs" aria-label="Breadcrumbs">
      <a href="../index.html" class="category__crumb">Sākums</a>
      <span class="category__crumb-sep">/</span>
      <a href="../index.html#news" class="category__crumb">Jaunumi un informācija</a>
      <span class="category__crumb-sep">/</span>
      <span class="category__crumb category__crumb--current"><?= htmlspecialchars($item['title']) ?></span>
    </nav>
    <h1 class="category__title"><?= htmlspecialchars($item['title']) ?></h1>
    <div class="content-detail">
      <?php if ($item['date']): ?><p class="content-detail__meta"><?= htmlspecialchars($item['date']) ?></p><?php endif; ?>
      <?php if ($item['image']): ?>
        <div class="content-detail__media"><img src="../uploads/news/<?= htmlspecialchars($item['image']) ?>" alt=""></div>
      <?php else: ?>
        <div class="content-detail__media" aria-hidden="true"></div>
      <?php endif; ?>
      <p class="content-detail__text"><?= nl2br(htmlspecialchars($item['text'] ?? '')) ?></p>
    </div>
  </div>
</section>
</main>
</body>
</html>
```

- [ ] **Step 4: Create `php/article.php`**

```php
<?php

require_once __DIR__ . '/includes/db.php';

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    echo '<h1>Raksts nav atrasts</h1>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= htmlspecialchars($item['title']) ?> — Gehwole</title>
<link rel="stylesheet" href="../css/main.css" />
<link rel="icon" type="image/x-icon" href="../img/favicons/favicon.ico">
</head>
<body>
<main>
<section class="category">
  <div class="container category__container">
    <nav class="category__breadcrumbs" aria-label="Breadcrumbs">
      <a href="../index.html" class="category__crumb">Sākums</a>
      <span class="category__crumb-sep">/</span>
      <a href="../index.html#news" class="category__crumb">Jaunumi un informācija</a>
      <span class="category__crumb-sep">/</span>
      <span class="category__crumb category__crumb--current"><?= htmlspecialchars($item['title']) ?></span>
    </nav>
    <h1 class="category__title"><?= htmlspecialchars($item['title']) ?></h1>
    <div class="content-detail">
      <?php if ($item['image']): ?>
        <div class="content-detail__media"><img src="../uploads/articles/<?= htmlspecialchars($item['image']) ?>" alt=""></div>
      <?php else: ?>
        <div class="content-detail__media" aria-hidden="true"></div>
      <?php endif; ?>
      <p class="content-detail__text"><?= nl2br(htmlspecialchars($item['text'] ?? '')) ?></p>
    </div>
  </div>
</section>
</main>
</body>
</html>
```

- [ ] **Step 5: Manually verify (content correctness — styling comes together in Task 14)**

```bash
php -S localhost:8000
```

Using the "Test Product" you can re-add via `admin/products.php` (id will likely be 22 if this is the first product row):
1. `curl http://localhost:8000/php/product.php?id=22` → expect the product's name/description in the HTML, breadcrumb linking to `gehwol-classic.html`.
2. `curl http://localhost:8000/php/product.php?id=99999` → expect HTTP 404 and "Produkts nav atrasts".
3. `curl http://localhost:8000/php/category.php?id=2` → expect breadcrumb "Kosmētika / GEHWOL Classic" and the test product card linking to `product.php?id=22`.
4. Same pattern for `news.php?id=` and `article.php?id=` against a test row added via their admin pages.

- [ ] **Step 6: Commit**

```bash
git add php/product.php php/category.php php/news.php php/article.php
git commit -m "feat: add public detail pages for products, categories, news, articles"
```

---

### Task 14: Gulp copy task for `php/` and `uploads/` into `build/`

**Files:**
- Modify: `gulp/dev.js`
- Modify: `gulpfile.js`

**Interfaces:**
- Produces: `build/php/**` and `build/uploads/**`, verbatim copies of the repo's `php/` and `uploads/` directories, wired into the existing `gulp` (default) task only. `gulp docs` (→ `docs/`, GitHub Pages) is untouched.

- [ ] **Step 1: Add two tasks to `gulp/dev.js`**

Add after the existing `files:dev` task (around line 206, right before `js:dev`):

```js
gulp.task("phpAdmin:dev", function() {
  return gulp
    .src("./php/**/*")
    .pipe(changed("./build/php/"))
    .pipe(gulp.dest("./build/php/"));
});

gulp.task("uploads:dev", function() {
  return gulp
    .src("./uploads/**/*", { allowEmpty: true })
    .pipe(changed("./build/uploads/"))
    .pipe(gulp.dest("./build/uploads/"));
});
```

- [ ] **Step 2: Wire the new tasks into the `default` series in `gulpfile.js`**

```js
gulp.task(
  "default",
  gulp.series(
    "clean:dev",
    "fontsDev",
    gulp.parallel(
      "html:dev",
      "sass:dev",
      "images:dev",
      "svgIcons:dev",
      gulp.series("svgStack:dev", "svgSymbol:dev"),
      "files:dev",
      "js:dev",
      "phpAdmin:dev",
      "uploads:dev",
    ),
    gulp.parallel("server:dev", "watch:dev"),
  ),
);
```

(Only the `gulp.parallel(...)` list inside the `"default"` task changes — two entries added at the end. The `"docs"` task in `gulpfile.js` is not touched.)

- [ ] **Step 3: Verify the build**

```bash
npx gulp html:dev
npx gulp sass:dev
npx gulp js:dev
npx gulp phpAdmin:dev
npx gulp uploads:dev
```

(Running the specific tasks directly avoids starting the dev server/watcher, which would hang the terminal.)

```bash
ls build/php
ls build/uploads
```

Expected: `build/php` contains `api.php`, `product.php`, `category.php`, `news.php`, `article.php`, `includes/`, `admin/`, `migrations/`; `build/uploads` contains `products/`, `news/`, `articles/`, `.htaccess`. Note `build/php/config.php` will be **absent** unless you manually copy your local one in — that's expected, it's gitignored and must be created directly on the production server.

- [ ] **Step 4: Commit**

```bash
git add gulp/dev.js gulpfile.js
git commit -m "feat: copy php/ and uploads/ into build/ during gulp default"
```

---

### Task 15: Wire `categoryId` into `category-page.html` and the 17 line pages

**Files:**
- Modify: `src/html/blocks/category-page.html`
- Modify: `src/html/gehwol-classic.html`, `gehwol-med.html`, `gehwol-fusskraft.html`, `gehwol-fusskraft-soft-feet.html`, `gehwol-professional.html`, `gerlasan.html`, `gerlavit.html`, `polimera-gela-izstradajumi-parvilkti-ar-tekstilu.html`, `polimera-gela-izstradajumi.html`, `plaksteri.html`, `filca-izstradajumi.html`, `pedu-kopsanas-aparati.html`, `pacienta-kresli.html`, `darbinieka-kresli.html`, `keramiskas-frezes.html`, `puletaji.html`, `vienreizlietojamas-smilspapira-frezes.html` (17 files)

**Interfaces:**
- Produces: `<div class="category__grid" data-category-id="N">` on each of the 17 built pages, where `N` matches the fixed category ids from Task 2's seed table. The JS module in Task 17 reads `data-category-id` to know which `?type=products&category_id=N` to fetch and where to append the results.

- [ ] **Step 1: Add the `data-category-id` attribute to `src/html/blocks/category-page.html`**

Change line 15 from:
```html
		<div class="category__grid">
```
to:
```html
		<div class="category__grid" data-category-id="@@categoryId">
```

- [ ] **Step 2: Add the `categoryId` param to each of the 17 `@@include('blocks/category-page.html', ...)` calls**

Using the mapping from Task 2's seed table, edit each file's include call. For example, `src/html/gehwol-classic.html` currently has:
```html
		@@include('blocks/category-page.html', {
			"crumbCategory": "Kosmētika",
			"title": "GEHWOL Classic"
		})
```
Change to:
```html
		@@include('blocks/category-page.html', {
			"crumbCategory": "Kosmētika",
			"title": "GEHWOL Classic",
			"categoryId": 2
		})
```

Apply the same edit (add `"categoryId": N,` before the closing values, matching the file's title) to all 17 files, using this exact mapping:

| File | categoryId |
|------|------------|
| gehwol-classic.html | 2 |
| gehwol-med.html | 3 |
| gehwol-fusskraft.html | 4 |
| gehwol-fusskraft-soft-feet.html | 5 |
| gehwol-professional.html | 6 |
| gerlasan.html | 7 |
| gerlavit.html | 8 |
| polimera-gela-izstradajumi-parvilkti-ar-tekstilu.html | 10 |
| polimera-gela-izstradajumi.html | 11 |
| plaksteri.html | 12 |
| filca-izstradajumi.html | 13 |
| pedu-kopsanas-aparati.html | 15 |
| pacienta-kresli.html | 16 |
| darbinieka-kresli.html | 17 |
| keramiskas-frezes.html | 19 |
| puletaji.html | 20 |
| vienreizlietojamas-smilspapira-frezes.html | 21 |

- [ ] **Step 3: Build and verify**

```bash
npx gulp html:dev
grep -o 'data-category-id="[0-9]*"' build/gehwol-classic.html
grep -o 'data-category-id="[0-9]*"' build/puletaji.html
grep -c 'data-category-id' build/*.html
```

Expected: `data-category-id="2"` for `gehwol-classic.html`, `data-category-id="20"` for `puletaji.html`; exactly 17 built files contain the attribute (the 17 line pages) — `index.html` and the others (produkts-N, raksts-N, jaunums-N, etc.) show `0` or don't appear in the grep output.

- [ ] **Step 4: Commit**

```bash
git add src/html/blocks/category-page.html src/html/gehwol-classic.html src/html/gehwol-med.html src/html/gehwol-fusskraft.html src/html/gehwol-fusskraft-soft-feet.html src/html/gehwol-professional.html src/html/gerlasan.html src/html/gerlavit.html src/html/polimera-gela-izstradajumi-parvilkti-ar-tekstilu.html src/html/polimera-gela-izstradajumi.html src/html/plaksteri.html src/html/filca-izstradajumi.html src/html/pedu-kopsanas-aparati.html src/html/pacienta-kresli.html src/html/darbinieka-kresli.html src/html/keramiskas-frezes.html src/html/puletaji.html src/html/vienreizlietojamas-smilspapira-frezes.html
git commit -m "feat: tag the 17 category line pages with their DB category id"
```

---

### Task 16: Wire `data-category-id` into the homepage `products.html` tree

**Files:**
- Modify: `src/html/blocks/products.html`

**Interfaces:**
- Produces: `data-category-id` on the 3 top-level `<ul class="products__list">` elements (Kosmētika=1, Spiedienu...=9, Tehnika=14) and the `<ul class="products__sublist">` for Rotējošie instrumenti (=18). The JS module in Task 17 appends new leaf-category `<li>` links into whichever of these matches a new category's `parent_id`.

- [ ] **Step 1: Add attributes to the 3 group lists and the subgroup sublist**

In `src/html/blocks/products.html`, change:
```html
				<div class="products__card">
					<a href="#" class="products__card-title">Kosmētika</a>
					<ul class="products__list">
```
to:
```html
				<div class="products__card">
					<a href="#" class="products__card-title">Kosmētika</a>
					<ul class="products__list" data-category-id="1">
```

Change:
```html
				<div class="products__card">
					<a href="#" class="products__card-title">Spiedienu uz pēdām mazinoši līdzekļi</a>
					<ul class="products__list">
```
to:
```html
				<div class="products__card">
					<a href="#" class="products__card-title">Spiedienu uz pēdām mazinoši līdzekļi</a>
					<ul class="products__list" data-category-id="9">
```

Change:
```html
				<div class="products__card">
					<a href="#" class="products__card-title">Tehnika</a>
					<ul class="products__list">
```
to:
```html
				<div class="products__card">
					<a href="#" class="products__card-title">Tehnika</a>
					<ul class="products__list" data-category-id="14">
```

Change:
```html
							<a href="#" class="products__subtitle">Rotējošie instrumenti</a>
							<ul class="products__sublist">
```
to:
```html
							<a href="#" class="products__subtitle">Rotējošie instrumenti</a>
							<ul class="products__sublist" data-category-id="18">
```

- [ ] **Step 2: Build and verify**

```bash
npx gulp html:dev
grep -o 'data-category-id="[0-9]*"' build/index.html
```

Expected: four matches — `data-category-id="1"`, `"9"`, `"14"`, `"18"`.

- [ ] **Step 3: Commit**

```bash
git add src/html/blocks/products.html
git commit -m "feat: tag homepage category lists with their DB category id"
```

---

### Task 17: JS content-injection module

**Files:**
- Create: `src/js/modules/dynamicContent.js`
- Modify: `src/js/index.js`

**Interfaces:**
- Produces: `export default async function loadDynamicContent(): Promise<void>` — fetches `php/api.php` (types `products`, `categories`, `news`, `articles`) and injects DOM nodes; a no-op (skips all fetches) if none of its target selectors exist on the current page.
- Consumes: nothing from other JS modules; `index.js` is changed to call `loadDynamicContent()` and only start `initAllSwipers()` after it resolves, so any injected news/article slides exist before Swiper reads the DOM.

- [ ] **Step 1: Create `src/js/modules/dynamicContent.js`**

```js
async function fetchJSON(url) {
  try {
    const res = await fetch(url);
    if (!res.ok) return [];
    return await res.json();
  } catch (e) {
    return [];
  }
}

function injectProductCards(items) {
  document.querySelectorAll(".category__grid[data-category-id]").forEach((grid) => {
    const categoryId = Number(grid.dataset.categoryId);
    items
      .filter((item) => item.category_id === categoryId)
      .forEach((item) => {
        const a = document.createElement("a");
        a.href = item.link;
        a.className = "product-card";
        a.innerHTML = `
          <div class="product-card__media">
            ${item.image ? `<img src="uploads/products/${item.image}" alt="${item.name}">` : `<span class="product-card__placeholder">GEHWOL</span>`}
          </div>
          <h3 class="product-card__title">${item.name}</h3>
          <span class="product-card__cta btn-link">Uzzināt vairāk →</span>
        `;
        grid.appendChild(a);
      });
  });
}

function injectCategoryLinks(categories) {
  categories.forEach((cat) => {
    if (!cat.parent_id) return;
    const parentList = document.querySelector(`[data-category-id="${cat.parent_id}"]`);
    if (!parentList) return;
    const li = document.createElement("li");
    const a = document.createElement("a");
    a.href = cat.link;
    a.className = "products__link";
    a.textContent = cat.name;
    li.appendChild(a);
    parentList.appendChild(li);
  });
}

function injectSlides(items, wrapperSelector, folder) {
  const wrapper = document.querySelector(wrapperSelector);
  if (!wrapper) return;
  items.forEach((item) => {
    const slide = document.createElement("div");
    slide.className = "news__slide swiper-slide";
    slide.innerHTML = `
      <a href="${item.link}" class="news__slide-link">
        <div class="news__image" aria-hidden="true">${item.image ? `<img src="uploads/${folder}/${item.image}" alt="">` : ""}</div>
        <h3 class="news__slide-title">${item.title}</h3>
      </a>
    `;
    wrapper.appendChild(slide);
  });
}

function pageNeedsDynamicContent() {
  return document.querySelector(".category__grid[data-category-id], #products, #news") !== null;
}

export default async function loadDynamicContent() {
  if (!pageNeedsDynamicContent()) return;

  const [products, categories, news, articles] = await Promise.all([
    fetchJSON("php/api.php?type=products"),
    fetchJSON("php/api.php?type=categories"),
    fetchJSON("php/api.php?type=news"),
    fetchJSON("php/api.php?type=articles"),
  ]);

  injectProductCards(products);
  injectCategoryLinks(categories);
  injectSlides(news, ".swiper-news .news__wrapper", "news");
  injectSlides(articles, ".swiper-articles .news__wrapper", "articles");
}
```

- [ ] **Step 2: Rewrite `src/js/index.js` to load dynamic content before initializing swipers**

Replace the entire file with:

```js
import loadDynamicContent from "./modules/dynamicContent.js";
import initAllSwipers from "./modules/swipers.js";

import headerScroll from "./modules/header-scroll.js";
headerScroll();

import scrollReveal from "./modules/scrollReveal.js";
scrollReveal();

import mobileNav from "./modules/mobile-nav.js";
mobileNav();

import placeholderLinks from "./modules/placeholder-links.js";
placeholderLinks();

import newsTabs from "./modules/newsTabs.js";
newsTabs();

loadDynamicContent().finally(() => {
  initAllSwipers();
});
```

- [ ] **Step 3: Build and manually verify in a browser**

```bash
npx gulp js:dev
npx gulp html:dev
npx gulp sass:dev
npx gulp phpAdmin:dev
npx gulp uploads:dev
php -S localhost:8000 -t build
```

1. In the admin (`http://localhost:8000/php/admin/categories.php`), add a category "Test Line" under "Kosmētika" (id 1). In `products.php`, add a product "Test Product" under it. In `news.php` and `articles.php`, add one test item each.
2. Visit `http://localhost:8000/index.html`, open DevTools console — expect no JS errors.
3. Confirm the Kosmētika card in the `#products` section now shows an extra "Test Line" link at the bottom of its list, styled like the existing links.
4. Click "Test Line" → expect it to open `php/category.php?id=<newId>`, styled (main.css loaded via `../css/main.css` — wait, from `build/`, this page is `build/php/category.php`, one level down, so it correctly resolves to `build/css/main.css`), showing "Test Product" as a card.
5. Click "Test Product" → expect the product detail page with its description.
6. Back on the homepage, confirm the news slider (`#news`) shows the existing 5 slides plus your test news item, and the swiper still autoplays/loops without console errors.
7. Switch to the "Noderīgi raksti" tab, confirm your test article slide is present too.

- [ ] **Step 4: Commit**

```bash
git add src/js/modules/dynamicContent.js src/js/index.js
git commit -m "feat: inject new products/categories/news/articles via JS on page load"
```

---

### Task 18: End-to-end verification and deploy notes

**Files:**
- None (verification only) — optionally create `php/README.md` if the developer wants deploy notes captured in-repo (not required by this plan).

- [ ] **Step 1: Full rebuild from a clean state**

```bash
npx gulp clean:dev
npx gulp html:dev
npx gulp sass:dev
npx gulp images:dev
npx gulp svgIcons:dev
npx gulp svgStack:dev
npx gulp svgSymbol:dev
npx gulp files:dev
npx gulp js:dev
npx gulp phpAdmin:dev
npx gulp uploads:dev
```

Expected: all tasks complete without errors; `build/` contains the full static site plus `build/php/` and `build/uploads/`.

- [ ] **Step 2: Run the full PHPUnit suite**

```bash
vendor/bin/phpunit
```

Expected: `OK` — all tests from Tasks 3, 4, 5, 6 pass (17 tests total: 4 + 5 + 5 + 3).

- [ ] **Step 3: Full manual walkthrough against `build/`**

```bash
php -S localhost:8000 -t build
```

1. Log in to `http://localhost:8000/php/admin/index.php`.
2. Add one category, one product, one news item, one article (if not already left over from earlier tasks).
3. Load `http://localhost:8000/index.html` and confirm all four show up in the right places (product under its category card + on the category's own page, news/article as extra slider slides).
4. Confirm none of the pre-existing static pages (`produkts-1.html`, `raksts-1.html`, `jaunums-1.html`, any of the 17 line pages, `index.html` itself) show any visual regressions — the only difference should be the extra injected items.
5. Log out, confirm `admin/*.php` redirects to login again.

- [ ] **Step 4: Note deploy caution (no code change — read and acknowledge)**

When this goes to the real PHP hosting later: the live `uploads/` folder will accumulate real admin-uploaded images over time. Any future redeploy that overwrites the server's `uploads/` folder wholesale (e.g. a full FTP mirror from a freshly rebuilt `build/`) would destroy those uploads — redeploys must only sync `php/`, `css/`, `js/`, `img/`, and the `.html` files, never overwrite `uploads/` on the server. `gulp docs` / `docs/` (GitHub Pages) remains completely unaffected by all of this — it has no PHP, no `php/` or `uploads/` folder, and continues to serve the static site exactly as before.

- [ ] **Step 5: Final commit (if Step 4's note is captured anywhere, e.g. this plan file checkbox state)**

```bash
git add docs/superpowers/plans/2026-07-09-admin-panel.md
git commit -m "chore: mark admin panel implementation plan complete"
```
