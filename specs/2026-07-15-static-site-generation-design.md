# Static site generation from JSON (public site off PHP)

## Context

Public-facing pages (category grids, product detail) currently render via a
client-side fetch to `php/api.php` / `php/product.php` / `php/category.php`
(see `src/js/modules/dynamicContent.js`), which reads `php/data/*.json`. Two
problems:

1. Local preview needs a PHP-capable server (`php -S`); the normal gulp dev
   server (`gulp-server-livereload`) is a static file server and can't run
   `.php`, so dynamic content silently never loads there.
2. `blocks/category-page.html` and `src/html/produkts-1..6.html` contain
   hardcoded placeholder cards ("Produkts 1".."Produkts 6") that the fetch
   result gets appended after, rather than replacing.

Goal: move product/category rendering to build-time static generation from
`php/data/*.json`, so the public site is plain HTML with zero PHP dependency
at request time. Admin panel (`php/admin/*`, `php/includes/*`) is untouched
and out of scope — it keeps writing to `php/data/*.json` as today; wiring
admin saves to auto-trigger regeneration is future work.

News/articles home section (`blocks/news.html`) is authored static content
(not placeholder-labeled, not JSON-backed) and is untouched.

## Architecture

New gulp task `generate:static`, defined in `gulp/generate.js`, required
from `gulpfile.js`. Manual invocation only (`npx gulp generate:static`) —
not part of the default `gulp` series, so routine scss/js edits don't
trigger regeneration or diff `src/html/produkts-*.html` unnecessarily.

Plain Node (`fs`, template strings) — no DOM library. Reuses the existing
`gulp-file-include` `@@param` substitution mechanism already used by
`blocks/category-page.html` (`@@title`, `@@categoryId`, `@@crumbCategory`)
and `blocks/product-detail-page.html` (`@@n`), so generated source files
flow through the normal `html:dev` / `html:docs` pipeline (typograf, webp,
prettier) unchanged.

### Data flow

```
php/data/categories.json ─┐
                           ├─> gulp/generate.js ─> src/html/<category>.html (17 files)
php/data/products.json  ──┘                    └─> src/html/produkts-<id>.html (N files)
                                                       │
                                                       ▼
                                        gulp (html:dev / html:docs, existing)
                                                       │
                                                       ▼
                                              build/ or docs/ (existing)
```

### Category pages

For each category row in `categories.json` with non-null `link_url` (17
rows: ids 2–8, 10–13, 15–17, 19–21):

- Filter `products.json` by `category_id`, sort by `sort_order`.
- Build one `<a class="product-card">` block per product (name, link
  `produkts-<id>.html`, image `uploads/products/<image>` if set else the
  `product-card__placeholder` GEHWOL span) — same markup `category.php`
  emits today.
- If zero products: render `<p>Šajā kategorijā vēl nav produktu.</p>`
  (matches `category.php`'s existing empty-state copy).
- Look up the category's parent name (`parent_id` → `categories.json`) for
  the breadcrumb.
- Overwrite `src/html/<link_url>` with header + `@@include('blocks/category-page.html', {...})` (title, categoryId, crumbCategory, new `productCards` param carrying the raw HTML block above) + footer — same file shape as today's `src/html/gehwol-classic.html`.

### Product detail pages

For each row in `products.json`:

- Look up its category (`name` for breadcrumb text, `link_url` for
  breadcrumb href).
- Build description markup: split `description` on `\n`, HTML-escape each
  line, join with `<br>` (equivalent to `product.php`'s `nl2br(htmlspecialchars(...))`).
  HTML-escape `name` too.
- Media: `<img>` if `image` set, else the `product-detail__placeholder`
  GEHWOL span.
- Write/overwrite `src/html/produkts-<id>.html`: header + `@@include('blocks/product-detail-page.html', {...})` (name, description, media, categoryName, categoryHref) + footer.

### Stale file cleanup

Before writing, glob `src/html/produkts-*.html`, compute the current set of
`produkts-<id>.html` filenames from `products.json`, delete any generated
file not in that set (handles a product removed from JSON between runs).
Only files matching the `produkts-<digits>.html` pattern are eligible for
deletion — this task never touches category pages beyond overwriting the 17
known `link_url` targets.

## Template changes

- `blocks/category-page.html`: replace the six hardcoded placeholder
  `<a class="product-card">` blocks with a single `@@productCards` param.
- `blocks/product-detail-page.html`: replace `Produkts @@n` and the generic
  filler description with `@@name`, `@@description`, `@@media`,
  `@@categoryName`, `@@categoryHref` params (breadcrumb + title + media +
  description use these instead of literals).
- Delete `src/html/produkts-1.html` .. `produkts-6.html` (fake placeholders,
  superseded by real generated files — current product IDs happen to also
  span 1–16, so `produkts-1..6.html` get regenerated with real content as
  part of the same run rather than existing as a separate leftover set).

## Removing the PHP bridge

Per decision: delete the runtime bridge between admin JSON and the public
site, since generation now happens at build time:

- Delete `php/product.php`, `php/category.php`, `php/api.php`,
  `php/news.php`, `php/article.php`.
- Delete `src/js/modules/dynamicContent.js`.
- `src/js/index.js`: remove the `loadDynamicContent` import and call;
  `initAllSwipers()` runs directly instead of inside `.finally()`.
- Not touched: `php/admin/*`, `php/includes/*` (storage/auth/validation/upload) — admin still reads/writes `php/data/*.json` the same way.

## Error handling

- Malformed/missing `php/data/products.json` or `categories.json`:
  `load` step throws with a clear message (fail the gulp task loudly —
  no silent empty-catch, unlike the old client-side `fetchJSON`).
- A product referencing a `category_id` not present in `categories.json`:
  skip it from category-grid injection (nowhere valid to put it) but still
  generate its `produkts-<id>.html` with an empty breadcrumb category
  segment — logged as a warning to the gulp console, not a hard failure.

## Testing

- Run `gulp generate:static`, inspect `src/html/gehwol-classic.html` and a
  couple of `src/html/produkts-<id>.html` for correct data.
- Run `gulp` (or `js:dev`/`html:dev`) to rebuild `build/`.
- Serve `build/` with any static server (no PHP needed) and click through:
  home → GEHWOL Classic → a product detail page → back.
- Spot check a category with zero products (e.g. `gehwol-med.html`) shows
  the empty-state message, not stale placeholder cards.
- Confirm `php/admin/products.php` still lists/edits/deletes rows in
  `php/data/products.json` unaffected (admin untouched).

## Out of scope (explicitly deferred)

- Auto-regenerating static pages when admin saves a product/category
  (would wire `products.php`/`categories.php` admin actions to invoke the
  generator — deferred, admin integration ignored per this task).
- News/articles JSON-driven generation (home section stays hand-authored
  static content; `news.json`/`articles.json` are unused today).
- Non-seeded (admin-created) categories appearing in the home nav tree
  (`injectCategoryLinks` is removed with the rest of the bridge; today
  there are zero non-seeded categories, so no visible regression).
