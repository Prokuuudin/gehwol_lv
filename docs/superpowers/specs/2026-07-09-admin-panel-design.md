# Simple Admin Panel (products, news, articles) — Design

Date: 2026-07-09

## Problem

Site is a fully static gulp build (`gulp-file-include`, no backend, no DB). Every product,
news item and article is a hand-authored HTML file. Owner needs a simple admin panel to add
new products/news/articles without a developer touching the repo, without turning the whole
site into a dynamic app (no pricing/e-commerce — content-only), and without disrupting the
existing static build/deploy process.

## Discovered current structure (important — deeper than it first looked)

- Homepage `#products` section (`blocks/products.html`) is a static tree, hardcoded:
  3 top groups (**Kosmētika**, **Spiedienu uz pēdām mazinoši līdzekļi**, **Tehnika**) →
  17 leaf "product line" pages (`gehwol-classic.html`, `gerlasan.html`, `plaksteri.html`, …),
  with **Tehnika** having one extra nesting level (**Rotējošie instrumenti** subgroup → 3 leaf
  lines). Group headers and the subgroup header are not links (`href="#"`); only the 17 leaves
  are real links.
- Each of the 17 leaf pages uses `blocks/category-page.html`, showing 6 **identical mock**
  product cards ("Produkts 1".."Produkts 6") — same 6 cards on every single line, no real
  per-product data anywhere yet.
- All 6 mock cards link to **shared** generic detail pages `produkts-1.html`..`produkts-6.html`
  (`blocks/product-detail-page.html`) — same placeholder text/media on all of them.
- News (`blocks/news.html`) and articles use the same swiper-tabs section: 5 news items
  (`jaunums-1..5.html`) and 5 articles (`raksts-1..5.html`), each with its own generated
  title/date/text via `blocks/news-detail-page.html` / `blocks/article-detail-page.html` — also
  placeholder/generated copy, not real content. Real texts and photos for products, news and
  articles alike will be sourced and uploaded later, on the same hosting as the site.

Consequence for design: **nothing existing has real per-item content worth preserving** —
products, news and articles are all symmetric. New products/news/articles are always
brand-new DB rows, always get their own `*.php?id=` detail page, and are appended after the
existing static mock cards/slides. The one exception is **categories**: the 17 line pages are
real, permanent site structure (URLs, breadcrumbs, nav) even though the products shown on them
are mock — those 21 category rows are seeded so the existing tree/links are preserved and
extendable.

## Decisions carried from brainstorming

- Stack: PHP (PDO) + MySQL, session auth, single admin user (bcrypt).
- Products have no pricing/checkout — description + photo only.
- Existing static pages (produkts-1..6, raksts-1..5, jaunums-1..5, the 17 line pages) stay
  exactly as they are — never edited, never migrated content-wise.
- Photo upload supported from day one.
- Categories are manageable through the admin (rename, reorder, add new leaf categories).
- Architecture: JS-injection, not a PHP-rendered homepage — see below.

## Architecture

New `php/` directory, sibling to `src/`, **not** processed by any gulp task (no
`gulp-file-include`, no `gulp-webp-retina-html`, no `gulp-htmlclean`/typograf) — copied
verbatim into `build/` by one new passthrough gulp task (`gulp.src('php/**/*').pipe(gulp.dest('build/php'))`,
wired into the existing `docs`/`dev` tasks). This keeps the fragile existing HTML build
pipeline completely untouched.

```
php/
  config.php          # DB credentials (gitignored), session bootstrap
  api.php              # public, read-only, JSON: ?type=products|news|articles[&category_id=]
  product.php          # ?id=  — detail page for a DB product
  category.php         # ?id=  — detail page for an admin-created leaf category
  news.php             # ?id=  — detail page for a DB news item
  article.php          # ?id=  — detail page for a DB article
  admin/
    login.php / logout.php
    index.php                     # dashboard
    categories.php                # list/add/edit/delete
    products.php                  # list/add/edit/delete
    news.php                      # list/add/edit/delete
    articles.php                  # list/add/edit/delete
    includes/auth.php              # session guard, required at top of every admin/*.php but login.php
  includes/db.php       # PDO connection, prepared-statement helpers
uploads/
  products/ news/ articles/       # uploaded images, .htaccess denies PHP execution
```

`index.html` and the 17 line pages, and the homepage `products.html`/`news.html` partials,
are **not renamed and not run through PHP**. They stay static `.html`, built by gulp exactly
as today. A small JS module (bundled into the existing webpack `index.bundle.js`) fetches
`api.php` on page load and injects extra markup into the DOM for whichever new content
applies to that page. This is why the site can honestly stay "not dynamic" while still showing
new content: the HTML delivered by the server is static; a few extra DOM nodes are added
client-side after load.

## Data model (MySQL)

```sql
categories (
  id INT PK AUTO_INCREMENT,
  parent_id INT NULL REFERENCES categories(id),
  name VARCHAR(255),
  link_url VARCHAR(255) NULL,   -- set only for the 21 seeded legacy rows (see below)
  sort_order INT DEFAULT 0
)

products (
  id INT PK AUTO_INCREMENT,
  category_id INT REFERENCES categories(id),  -- must be a LEAF category
  name VARCHAR(255),
  description TEXT NULL,
  image VARCHAR(255) NULL,
  sort_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
-- no link_url: every product row is a brand-new item, always gets product.php?id=

news (
  id INT PK AUTO_INCREMENT,
  title VARCHAR(255),
  date DATE NULL,
  text TEXT NULL,
  image VARCHAR(255) NULL,
  sort_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
-- no link_url: same as products, every row is brand-new, always gets news.php?id=

articles (
  id INT PK AUTO_INCREMENT,
  title VARCHAR(255),
  text TEXT NULL,
  image VARCHAR(255) NULL,
  sort_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
-- no link_url: same as products, every row is brand-new, always gets article.php?id=

admin_users ( id INT PK, username VARCHAR(255), password_hash VARCHAR(255) )
```

**Seed data** (one-time SQL migration, run manually via phpMyAdmin/CLI on deploy):
- `categories` only: 3 top groups (no `link_url`) → their 17 leaves (`link_url` = the real
  filename, e.g. `gehwol-classic.html`) → under Tehnika, the `Rotējošie instrumenti` subgroup
  (no `link_url`) → its 3 leaves (`link_url` set). 21 rows total.
- `products`/`news`/`articles`: no seed rows at all — the existing mock cards/slides are not
  modeled in the DB, they simply stay in the static HTML as today.

For categories, a row with `link_url` set is a "legacy" row: the tree links straight to that
static file. A row without it (admin-created) is a "live" row: it links to `category.php?id=`.
Products/news/articles don't need this distinction — every row in those three tables is,
by definition, a new addition, always linking to its own `*.php?id=` detail page.

## Wiring the 17 line pages + homepage tree to real data

Each of the 17 `gulp-file-include` calls to `category-page.html` gets one extra param, its
category id, e.g.:
```
@@include('blocks/category-page.html', { "crumbCategory": "Kosmētika", "title": "GEHWOL Classic", "categoryId": 4 })
```
rendered onto the grid as `<div class="category__grid" data-category-id="4">`. On page load,
the JS module calls `api.php?type=products&category_id=4`, and appends real product cards
(photo, name, link to `product.php?id=`) after the 6 existing mock cards.

Homepage `products.html`: each existing `<ul class="products__list">`/`.products__sublist`
gets a matching `data-category-id` attribute (on the 3 groups' lists and the Tehnika subgroup's
list), and `.products__grid` itself gets nothing special. On load, JS calls
`api.php?type=categories`, and:
- a new leaf category under an existing group → appends `<li><a>` into that group's list;
- a new leaf category under `Rotējošie instrumenti` → appends into that sublist;
- a brand-new top-level group → appends a whole new `.products__card` (rare case, supported
  for completeness, no dedicated grep-checked acceptance criteria since none exist today).

New leaf categories (created via admin) get no static file — `category.php?id=` renders the
same breadcrumb/grid markup purely from DB (its own products via `category_id`).

News/articles: the two existing sliders already end with 5 static mock slides each. JS appends
one extra `<div class="news__slide swiper-slide">` per row from `api.php?type=news` /
`?type=articles` (all rows — no legacy filter needed, every row is new), then calls
`swiper.update()` (swiper init in `index.js` must run *after* the fetch resolves, so slides
exist before Swiper reads the DOM — `initAllSwipers()` moves behind an `await` on the
content-injection module).

## Admin panel

- `login.php`: username+password → `password_verify` → `$_SESSION`. Everything else in
  `admin/` requires the session (checked via `includes/auth.php`, redirect to login if absent).
- **Categories**: list (tree view), add/edit (name, parent dropdown — only non-leaf or root as
  parent choices, sort order), delete (blocked if it still has products, or has children).
- **Products**: list filtered by category, add/edit (name, category dropdown — leaf categories
  only, description textarea, image upload, sort order), delete.
- **News** / **Articles**: list, add/edit (title, [date for news], text, image, sort order),
  delete. No legacy rows here (unlike categories) — every row is a plain new item.
- Uploads: mime+extension whitelist (jpg/jpeg/png/webp), size cap (e.g. 5 MB), stored as
  `uniqid() + extension` under `uploads/{products,news,articles}/`.

## Security

- PDO prepared statements everywhere, no string-concatenated SQL.
- `password_hash()`/`password_verify()`, PHP session, no home-rolled crypto.
- `uploads/.htaccess`: `php_flag engine off` (or `Options -ExecCGI`) so an uploaded file can
  never execute as PHP.
- `config.php` (DB credentials) is gitignored; a `config.example.php` is committed instead.

## Deploy

- `gulp docs` (unchanged) builds the static site into `build/` as today.
- New gulp task copies `php/**/*` into `build/php/` and `uploads/**/*` into `build/uploads/`
  verbatim (no transformation) — one `gulp.src().pipe(gulp.dest())`, added to the existing
  `docs`/`dev` task chains.
- One-time on the hosting: create the MySQL DB, run the migration+seed SQL file, copy
  `config.example.php` → `config.php` and fill in real credentials, create the first admin
  user by generating a hash locally (`php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"`)
  and inserting it into `admin_users` — documented as a step in the migration file's header
  comment, no setup script needed (one-time action, no reason to leave that code in prod).

## Out of scope (v1)

- E-commerce: pricing, cart, checkout, stock.
- Multiple admin accounts/roles.
- Editing the *text content* of the 17 static line pages / their mock product cards, or of the
  10 static mock news/article files, through the admin — those stay untouched static HTML;
  only the legacy category rows' listing metadata (name, order, parent) is DB-backed.
- WYSIWYG rich text editor (plain `<textarea>` for description/text is enough for v1).
- Automatic webp/retina generation for admin-uploaded images (they're served as plain
  `<img>`, no build-time optimization — acceptable for occasional manual uploads).
- A dedicated landing page for a brand-new top-level category beyond its homepage card (it
  behaves exactly like the existing 3 groups: a header + list of links, not a clickable page).

## Testing / verification

- Manual: log in, add a category under Kosmētika, add a product to it, confirm it appears on
  that line's page (`gehwol-classic.html`) after gulp build+reload, and that a brand-new news
  item appears as an extra slide with a working `news.php?id=` page.
- `gulp docs` still completes without errors and without touching `php/`.
- Deleting an uploaded image's DB row doesn't 500 (file removal best-effort, not required to
  succeed for the DB delete to succeed).
- Direct requests to `admin/*.php` without a session redirect to `login.php`.
