# Static Site Generation From JSON Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Render category and product pages from `php/data/{categories,products}.json` at build time via a new manual gulp task, then delete the PHP/JS runtime bridge (`php/api.php`, `php/product.php`, `php/category.php`, `php/news.php`, `php/article.php`, `php/includes/links.php`, `src/js/modules/dynamicContent.js`) so the public site is plain HTML with zero PHP dependency at request time.

**Architecture:** New `gulp generate:static` task (`gulp/generate.js`) reads the JSON data, patches the existing 17 category page source files in place (preserving their hand-authored `<title>`/meta), and fully (re)writes one `src/html/produkts-<id>.html` per product. Two shared include blocks (`blocks/category-page.html`, `blocks/product-detail-page.html`) gain new `@@param` placeholders that the generator fills in — reusing the `gulp-file-include` mechanism already used by every other page on the site, so generated source files flow through the normal `html:dev`/`html:docs` build unchanged.

**Tech Stack:** Node/gulp (existing toolchain), `gulp-file-include` (already a dependency, parses `@@include(...)` params via `JSON5.parse`), no new npm packages.

## Global Constraints

- `generate:static` is a standalone gulp task, **not** added to the default `gulp` series (`gulpfile.js`'s `"default"` task) — routine scss/js edits must not regenerate or diff `src/html/produkts-*.html`.
- Admin panel (`php/admin/*`) and `php/includes/{storage,auth,validation,upload}.php` are not modified.
- Home page news/articles section (`src/html/blocks/news.html`) is not modified — it is hand-authored static content, not JSON-backed.
- Never run the bare `gulp` (default) task as a verification step in this plan — it starts a live server and file watcher and never exits. Use targeted tasks (`npx gulp html:dev`, `npx gulp js:dev`, `npx gulp generate:static`) instead.
- Product/category text (names, descriptions) must be HTML-escaped when the generator injects it into markup.
- Baseline PHPUnit suite is 26 tests, all passing (verified via `php vendor/bin/phpunit` before this plan starts).

---

### Task 1: Parameterize the shared category/product templates

**Files:**
- Modify: `src/html/blocks/category-page.html`
- Modify: `src/html/blocks/product-detail-page.html`
- Test (temporary, not committed): `src/html/_scratch-check.html`

**Interfaces:**
- Produces: `blocks/category-page.html` now requires params `crumbCategory` (string), `title` (string), `categoryId` (number), `productCards` (string, raw HTML — a `<a class="product-card">...</a>` list or a `<p>...</p>` empty-state message). These four keys are the contract Task 2's generator must supply.
- Produces: `blocks/product-detail-page.html` now requires params `name` (string, HTML-escaped), `description` (string, raw HTML — `<br>`-joined lines), `media` (string, raw HTML — an `<img>` tag or the placeholder `<span>`), `categoryName` (string, HTML-escaped), `categoryHref` (string, URL). These five keys are the contract Task 2's generator must supply.

- [ ] **Step 1: Replace the hardcoded product cards in `category-page.html`**

Current file (`src/html/blocks/category-page.html`) has six hardcoded `<a class="product-card">Produkts N</a>` blocks inside `<div class="category__grid" data-category-id="@@categoryId">`. Replace the entire block with:

```html
<section class="category">
	<div class="container category__container">
		<nav class="category__breadcrumbs" aria-label="Breadcrumbs">
			<a href="index.html" class="category__crumb">Sākums</a>
			<span class="category__crumb-sep">/</span>
			<a href="index.html#products" class="category__crumb">Produkti</a>
			<span class="category__crumb-sep">/</span>
			<span class="category__crumb">@@crumbCategory</span>
			<span class="category__crumb-sep">/</span>
			<span class="category__crumb category__crumb--current">@@title</span>
		</nav>

		<h1 class="category__title">@@title</h1>

		<div class="category__grid" data-category-id="@@categoryId">
			@@productCards
		</div>
	</div>
</section>
```

- [ ] **Step 2: Replace the hardcoded product detail markup in `product-detail-page.html`**

Replace the full contents of `src/html/blocks/product-detail-page.html` with:

```html
<section class="category">
	<div class="container category__container">
		<nav class="category__breadcrumbs" aria-label="Breadcrumbs">
			<a href="index.html" class="category__crumb">Sākums</a>
			<span class="category__crumb-sep">/</span>
			<a href="index.html#products" class="category__crumb">Produkti</a>
			<span class="category__crumb-sep">/</span>
			<a href="@@categoryHref" class="category__crumb">@@categoryName</a>
			<span class="category__crumb-sep">/</span>
			<span class="category__crumb category__crumb--current">@@name</span>
		</nav>

		<h1 class="category__title">@@name</h1>

		<div class="product-detail">
			<div class="product-detail__media">
				@@media
			</div>
			<p class="product-detail__description">@@description</p>
			<a href="index.html#contacts" class="product-detail__cta btn-link">Sazinieties, lai sadarbotos →</a>
		</div>
	</div>
</section>
```

Note: this adds a category breadcrumb link that the old placeholder template never had — matches what `php/product.php` renders today.

- [ ] **Step 3: Create a scratch file to verify both templates substitute correctly**

Create `src/html/_scratch-check.html`:

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<title>scratch</title>
</head>
<body>
@@include('blocks/category-page.html', {
	"crumbCategory": "TestParent",
	"title": "TestCategory",
	"categoryId": 99,
	"productCards": "<a href=\"produkts-1.html\" class=\"product-card\"><h3>Test Product</h3></a>"
})
@@include('blocks/product-detail-page.html', {
	"name": "Test Product",
	"description": "Line one<br>Line two",
	"media": "<span class=\"product-detail__placeholder\">GEHWOL</span>",
	"categoryName": "TestCategory",
	"categoryHref": "test-category.html"
})
</body>
</html>
```

- [ ] **Step 4: Build and inspect the scratch output**

Run: `npx gulp html:dev`

This rebuilds every `src/html/**/*.html` file into `build/` (gulp-changed always sees the `@@include` source as "different" from the expanded destination, so it never skips a file). **Expected:** the 17 existing category pages and 6 existing `produkts-N.html` pages in `build/` will now show literal unresolved `@@productCards`/`@@name`/etc. tokens — this is expected and temporary; their `src/html` source files don't pass the new params yet. Task 2 fixes this. Only check the scratch file for this task:

Run: `grep -c "@@" build/_scratch-check.html`
Expected: `0` (no leftover unresolved tokens)

Run: `grep -o "TestCategory\|Test Product\|Line one<br>Line two\|test-category.html" build/_scratch-check.html`
Expected: all four strings present.

- [ ] **Step 5: Delete the scratch files**

```bash
rm src/html/_scratch-check.html build/_scratch-check.html
```

- [ ] **Step 6: Commit**

```bash
git add src/html/blocks/category-page.html src/html/blocks/product-detail-page.html
git commit -m "refactor: parameterize category/product templates for static generation"
```

---

### Task 2: Build the `generate:static` gulp task

**Files:**
- Create: `gulp/generate.js`
- Modify: `gulpfile.js` (add `require("./gulp/generate.js")`)

**Interfaces:**
- Consumes: `php/data/categories.json` rows shaped `{id, parent_id, name, link_url, sort_order, seeded}`; `php/data/products.json` rows shaped `{id, category_id, name, description, image, sort_order, created_at}` (both already on disk — see `php/admin/products.php` for the writer). Consumes template params defined in Task 1 (`crumbCategory`/`title`/`categoryId`/`productCards` and `name`/`description`/`media`/`categoryName`/`categoryHref`).
- Produces: gulp task named `generate:static`. Patches `src/html/<category.link_url>` for every category with a non-null `link_url`. Writes/overwrites `src/html/produkts-<product.id>.html` for every product. Deletes any `src/html/produkts-*.html` whose numeric id has no matching product.

- [ ] **Step 1: Write `gulp/generate.js`**

```js
const gulp = require("gulp");
const fs = require("fs");
const path = require("path");

const ROOT = path.join(__dirname, "..");
const DATA_DIR = path.join(ROOT, "php", "data");
const HTML_DIR = path.join(ROOT, "src", "html");

function loadJSON(name) {
  const file = path.join(DATA_DIR, `${name}.json`);
  if (!fs.existsSync(file)) {
    throw new Error(`generate:static: missing ${file}`);
  }
  const rows = JSON.parse(fs.readFileSync(file, "utf8"));
  if (!Array.isArray(rows)) {
    throw new Error(`generate:static: ${file} must contain a JSON array`);
  }
  return rows;
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function bySortOrder(a, b) {
  const orderDiff = (a.sort_order || 0) - (b.sort_order || 0);
  return orderDiff !== 0 ? orderDiff : (a.id || 0) - (b.id || 0);
}

function productCardHtml(product) {
  const name = escapeHtml(product.name);
  const media = product.image
    ? `<img src="uploads/products/${escapeHtml(product.image)}" alt="${name}">`
    : `<span class="product-card__placeholder">GEHWOL</span>`;
  return `<a href="produkts-${product.id}.html" class="product-card"><div class="product-card__media">${media}</div><h3 class="product-card__title">${name}</h3><span class="product-card__cta btn-link">Uzzināt vairāk →</span></a>`;
}

function categoryGridHtml(products) {
  if (products.length === 0) {
    return `<p>Šajā kategorijā vēl nav produktu.</p>`;
  }
  return products.map(productCardHtml).join("");
}

const CATEGORY_INCLUDE_RE = /@@include\('blocks\/category-page\.html',\s*(\{[\s\S]*?\})\s*\)/;

function patchCategoryPage(category, products) {
  const filePath = path.join(HTML_DIR, category.link_url);
  if (!fs.existsSync(filePath)) {
    console.warn(`generate:static: skip missing category page ${category.link_url}`);
    return;
  }
  const source = fs.readFileSync(filePath, "utf8");
  const match = source.match(CATEGORY_INCLUDE_RE);
  if (!match) {
    console.warn(`generate:static: no category-page include found in ${category.link_url}`);
    return;
  }
  const params = JSON.parse(match[1]);
  params.productCards = categoryGridHtml(products);
  const replacement = `@@include('blocks/category-page.html', ${JSON.stringify(params)})`;
  fs.writeFileSync(filePath, source.replace(CATEGORY_INCLUDE_RE, () => replacement), "utf8");
}

function productMetaDescription(product) {
  const firstLine = String(product.description || "").split("\n")[0];
  return escapeHtml(`${product.name} — ${firstLine}`.slice(0, 160));
}

function productDescriptionHtml(description) {
  return String(description || "")
    .split("\n")
    .map(escapeHtml)
    .join("<br>");
}

function productMediaHtml(product) {
  const name = escapeHtml(product.name);
  return product.image
    ? `<img src="uploads/products/${escapeHtml(product.image)}" alt="${name}">`
    : `<span class="product-detail__placeholder">GEHWOL</span>`;
}

function productDetailPageSource(product, category) {
  const name = escapeHtml(product.name);
  const params = {
    name,
    description: productDescriptionHtml(product.description),
    media: productMediaHtml(product),
    categoryName: category ? escapeHtml(category.name) : "",
    categoryHref: category ? category.link_url : "index.html#products",
  };
  return `<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="${productMetaDescription(product)}" />
	<title>${name} — Gehwol</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/product-detail-page.html', ${JSON.stringify(params)})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
`;
}

function writeProductPage(product, categoriesById) {
  const category = categoriesById.get(product.category_id);
  if (!category) {
    console.warn(`generate:static: product ${product.id} (${product.name}) has unknown category_id ${product.category_id}`);
  }
  const filePath = path.join(HTML_DIR, `produkts-${product.id}.html`);
  fs.writeFileSync(filePath, productDetailPageSource(product, category), "utf8");
}

function cleanStaleProductPages(validIds) {
  const files = fs.readdirSync(HTML_DIR).filter((f) => /^produkts-\d+\.html$/.test(f));
  files.forEach((file) => {
    const id = Number(file.match(/^produkts-(\d+)\.html$/)[1]);
    if (!validIds.has(id)) {
      fs.unlinkSync(path.join(HTML_DIR, file));
      console.log(`generate:static: removed stale ${file}`);
    }
  });
}

function generateStatic(done) {
  const categories = loadJSON("categories");
  const products = loadJSON("products");
  const categoriesById = new Map(categories.map((c) => [c.id, c]));

  const productsByCategory = new Map();
  products.forEach((p) => {
    const list = productsByCategory.get(p.category_id) || [];
    list.push(p);
    productsByCategory.set(p.category_id, list);
  });

  categories
    .filter((c) => c.link_url)
    .forEach((category) => {
      const items = (productsByCategory.get(category.id) || []).slice().sort(bySortOrder);
      patchCategoryPage(category, items);
    });

  products.forEach((product) => writeProductPage(product, categoriesById));
  cleanStaleProductPages(new Set(products.map((p) => p.id)));

  done();
}

gulp.task("generate:static", generateStatic);
```

- [ ] **Step 2: Register the task in `gulpfile.js`**

In `gulpfile.js`, add the require alongside the existing ones:

```js
const gulp = require("gulp");

// Tasks
require("./gulp/dev.js");
require("./gulp/docs.js");
require("./gulp/fontsDev.js");
require("./gulp/fontsDocs.js");
require("./gulp/generate.js");
```

(Only the new `require("./gulp/generate.js");` line is added — do not touch the `"default"`/`"docs"` task series definitions below it.)

- [ ] **Step 3: Run the generator**

Run: `npx gulp generate:static`
Expected: exits with `Finished 'generate:static'`, no warnings printed (all 16 products have a valid `category_id`, all 17 category files exist).

- [ ] **Step 4: Verify a category page was patched correctly**

Run: `grep -o "GEHWOL Balsam Normale Haut 75, 125ml" src/html/gehwol-classic.html`
Expected: one match (the first Gehwol Classic product's name is now baked into the category page's source).

Run: `grep -c "produkts-16.html" src/html/gehwol-classic.html`
Expected: `1` (the 16th product's card links to its detail page).

- [ ] **Step 5: Verify an empty category got the empty-state message**

Run: `grep -o "Šajā kategorijā vēl nav produktu." src/html/gehwol-med.html`
Expected: one match (GEHWOL MED® has zero products in `products.json` today).

- [ ] **Step 6: Verify product detail pages were generated**

Run: `grep -o "GEHWOL Badesalz 250 (25gx10), 1000g" src/html/produkts-16.html`
Expected: one match.

Run: `ls src/html/produkts-*.html | wc -l`
Expected: `16`.

- [ ] **Step 7: Commit**

```bash
git add gulp/generate.js gulpfile.js src/html/gehwol-classic.html src/html/gehwol-med.html src/html/gehwol-fusskraft.html src/html/gehwol-fusskraft-soft-feet.html src/html/gehwol-professional.html src/html/gerlasan.html src/html/gerlavit.html src/html/polimera-gela-izstradajumi-parvilkti-ar-tekstilu.html src/html/polimera-gela-izstradajumi.html src/html/plaksteri.html src/html/filca-izstradajumi.html src/html/pedu-kopsanas-aparati.html src/html/pacienta-kresli.html src/html/darbinieka-kresli.html src/html/keramiskas-frezes.html src/html/puletaji.html src/html/vienreizlietojamas-smilspapira-frezes.html src/html/produkts-*.html
git commit -m "feat: generate category/product pages from php/data JSON"
```

---

### Task 3: Rebuild and verify the static output end-to-end

**Files:**
- None modified — this task only rebuilds `build/` from the already-generated `src/html/` and verifies it, serving with a non-PHP static server to confirm no runtime PHP dependency.

- [ ] **Step 1: Rebuild HTML output**

Run: `npx gulp html:dev`
Expected: `Finished 'html:dev'`, no plumber/notify error output.

- [ ] **Step 2: Serve `build/` with a plain static server (no PHP)**

Run (background): `python -m http.server 8020 --directory build`

- [ ] **Step 3: Verify the category grid renders real products**

Run: `curl -s http://127.0.0.1:8020/gehwol-classic.html | grep -o "GEHWOL Balsam Normale Haut 75, 125ml"`
Expected: one match.

Run: `curl -s http://127.0.0.1:8020/gehwol-classic.html | grep -o "Produkts 1"`
Expected: no output (old fake placeholder text gone).

- [ ] **Step 4: Verify a product detail page renders real content**

Run: `curl -s http://127.0.0.1:8020/produkts-1.html | grep -o "Hohobas eļļa, mentols, lavandas eļļa, rozmarīna eļļa, alveja."`
Expected: one match (the product's active-ingredients line from its description).

Run: `curl -s http://127.0.0.1:8020/produkts-1.html | grep -o 'href="gehwol-classic.html"'`
Expected: one match (breadcrumb links back to its category).

- [ ] **Step 5: Verify the empty-category state**

Run: `curl -s http://127.0.0.1:8020/gehwol-med.html | grep -o "Šajā kategorijā vēl nav produktu."`
Expected: one match.

- [ ] **Step 6: Stop the test server**

Find and stop the `python` process started in Step 2 (e.g. `tasklist` + `taskkill //PID <pid> //F` on Windows, or kill the backgrounded job).

No commit — this task is verification-only, no files changed.

---

### Task 4: Remove the PHP/JS runtime bridge

**Files:**
- Delete: `php/product.php`, `php/category.php`, `php/api.php`, `php/news.php`, `php/article.php`, `php/includes/links.php`, `tests/LinksTest.php`
- Delete: `src/js/modules/dynamicContent.js`
- Modify: `src/js/index.js`

Note on `php/includes/links.php` + `tests/LinksTest.php`: the spec calls out deleting the five public PHP entry points explicitly. `links.php` isn't one of them, but grep confirms its only callers are `php/product.php` and `php/api.php` — both deleted in this task, so it becomes dead code with no other caller (`php/admin/*` never requires it). Deleting it and its matching test alongside the files that used it keeps the codebase free of orphaned code — no separate step needed for it.

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: nothing consumed by later tasks — this is the final cleanup task.

- [ ] **Step 1: Delete the public PHP bridge files**

```bash
rm php/product.php php/category.php php/api.php php/news.php php/article.php php/includes/links.php tests/LinksTest.php
```

- [ ] **Step 2: Remove the dead client-side fetch module**

```bash
rm src/js/modules/dynamicContent.js
```

- [ ] **Step 3: Update `src/js/index.js`**

Replace the full contents of `src/js/index.js` with:

```js
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

initAllSwipers();
```

- [ ] **Step 4: Rebuild the JS bundle**

Run: `npx gulp js:dev`
Expected: `webpack ... compiled successfully`, `Finished 'js:dev'`.

Run: `grep -c "api.php" build/js/index.bundle.js`
Expected: `0`.

- [ ] **Step 5: Run the PHP test suite**

Run: `php vendor/bin/phpunit`
Expected: `OK (21 tests, ...)` — 21, not the original 26, since `LinksTest.php`'s 5 tests were deleted along with `links.php`. No failures.

- [ ] **Step 6: Confirm admin panel is untouched**

Run: `git status --porcelain -- php/admin/ php/includes/storage.php php/includes/auth.php php/includes/validation.php php/includes/upload.php`
Expected: empty output (no changes under admin or the remaining includes).

- [ ] **Step 7: Commit**

```bash
git add -A php/product.php php/category.php php/api.php php/news.php php/article.php php/includes/links.php tests/LinksTest.php src/js/modules/dynamicContent.js src/js/index.js
git commit -m "refactor: remove PHP/JS runtime bridge, public site is static HTML"
```

(`git add -A` on the deleted paths stages the removals; the modified `src/js/index.js` is staged normally.)

---

### Task 5: Full rebuild and final click-through verification

**Files:**
- None modified — full-site rebuild and manual smoke test.

- [ ] **Step 1: Full rebuild (targeted tasks, not the default watch/server task)**

```bash
npx gulp clean:dev
npx gulp fontsDev
npx gulp html:dev
npx gulp sass:dev
npx gulp images:dev
npx gulp svgStack:dev
npx gulp svgSymbol:dev
npx gulp svgIcons:dev
npx gulp files:dev
npx gulp js:dev
npx gulp phpAdmin:dev
npx gulp uploads:dev
```

Expected: each task prints `Finished '<task>'`, no errors.

- [ ] **Step 2: Serve and smoke test without PHP**

Run (background): `python -m http.server 8020 --directory build`

Open in a browser (or `curl`) and confirm:
- `http://127.0.0.1:8020/index.html` — home page loads, no console errors, "Produkcija" section nav links work.
- `http://127.0.0.1:8020/gehwol-classic.html` — grid shows 16 real GEHWOL Classic products, no "Produkts N" placeholders.
- Click a product card → lands on its `produkts-<id>.html`, shows real name/description/breadcrumb back to GEHWOL Classic.
- `http://127.0.0.1:8020/gehwol-med.html` (or any other category) — shows the "Šajā kategorijā vēl nav produktu." empty state.
- `http://127.0.0.1:8020/php/admin/products.php` — 404 or connection behavior expected for a static server (admin needs PHP; this is fine, admin is out of scope for the public static site and is still reachable via `php -S` separately).

- [ ] **Step 3: Stop the test server**

Stop the `python` process started in Step 2.

No commit — this task is a full-site verification pass only; all functional changes were committed in Tasks 1, 2, and 4.

---

### Task 6: Rebuild and commit the production `docs/` deployment

**Context (added after Task 4's review):** `docs/` is a second, separately tracked build output — the GitHub Pages production deployment (confirmed by prior commit history like `a090bce "fix: prevent broken Google Fonts @import in production CSS build"`), independent from the gitignored `build/` dev output. Tasks 1-5 only rebuilt `build/`. `docs/` was never touched by this plan and still contains the pre-refactor content: old fake placeholder products and a JS bundle built before the PHP bridge was removed. Note: the `docs` gulp series (`gulpfile.js`'s `"docs"` task) has no `phpAdmin:docs` or `uploads:docs` task at all — `docs/` has never included the PHP admin panel or uploads, so this task cannot and does not touch admin there either; it is already a static-only deployment target by construction.

**Files:**
- None hand-edited — this task only regenerates `docs/` (tracked in git) from the same `src/html/`, `src/scss/`, `src/js/`, etc. that Tasks 1-4 already changed.

- [ ] **Step 1: Rebuild `docs/` via the individual `:docs` gulp tasks (not the `docs` series — that starts a livereload server and never exits)**

```bash
npx gulp clean:docs
npx gulp fontsDocs
npx gulp html:docs
npx gulp sass:docs
npx gulp images:docs
npx gulp svgStack:docs
npx gulp svgSymbol:docs
npx gulp files:docs
npx gulp js:docs
```

Expected: each prints `Finished '<task>'`, no errors. `clean:docs` first removes the existing `docs/` directory (mirrors how `clean:dev` works for `build/` — this is the established, already-used workflow for updating this production output, not a new risk introduced by this task).

- [ ] **Step 2: Verify the stale content is gone and real content is present**

Run: `grep -c "api.php" docs/js/index.bundle.js`
Expected: `0`.

Run: `grep -o "GEHWOL Balsam Normale Haut 75, 125ml" docs/gehwol-classic.html`
Expected: one match.

Run: `grep -o "Produkts 1" docs/gehwol-classic.html`
Expected: no output (old fake placeholder text gone).

- [ ] **Step 3: Confirm `git status` shows only the expected `docs/` regeneration**

Run: `git status --porcelain -- docs/ | grep -v "^ M docs/\|^ D docs/\|^?? docs/\|^ A docs/"`
Expected: no output — every change under `docs/` is a modify/delete/add inside `docs/` itself, nothing else touched.

- [ ] **Step 4: Commit**

```bash
git add docs/
git commit -m "$(cat <<'EOF'
chore: rebuild production docs/ with static product pages

docs/ is the GitHub Pages deployment, tracked separately from the
gitignored build/ dev output. Regenerates it from the current
src/html/src/scss/src/js so the live site gets the real GEHWOL
Classic catalog and drops the now-removed php/api.php dependency.
EOF
)"
```

No commit yet at this point in git history — this is the task's only commit.
