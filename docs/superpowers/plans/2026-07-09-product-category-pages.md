# Product Category Pages Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give each of the 17 leaf product links in `products.html` / the header mega-menu a real destination page showing 6 mock product cards, replacing their current `href="#"`.

**Architecture:** One shared gulp-file-include partial (`blocks/category-page.html`) renders breadcrumb + h1 + a hardcoded 6-card mock grid, parameterized per page via `@@include(file, {json})`. 17 thin page files at `src/html/<slug>.html` (flat, same level as `index.html`) each include header, the partial (with their own title/crumb), and footer. Two new SCSS partials (`_category.scss`, `_product-card.scss`) style it, picked up automatically by the existing `@import './blocks/*.scss'` glob.

**Tech Stack:** gulp-file-include (JSON-param includes, prefix `@@`, basepath `@file`), gulp-sass-glob, no JS framework — static HTML/SCSS only. No automated test suite exists in this repo; verification is `npx gulp html:dev` / `npx gulp sass:dev` build + inspecting `build/` output, which is this project's actual working verification method (confirmed working during spec research).

## Global Constraints

- File placement is **flat**: `src/html/<slug>.html`, NOT `src/html/products/<slug>.html`. Confirmed empirically: `html:dev`'s asset-path regex collapses `../css/...` / `../img/...` to `./css/...` / `./img/...` regardless of actual file depth, so a subfolder page would ship with broken CSS/image links. Flat placement needs no gulpfile changes.
- Include syntax: `@@include('blocks/file.html', {"key": "value"})`, variables referenced inside the included file as `@@key`. `basepath: '@file'` (see `gulp/dev.js:30-33`) means the include path is relative to the including file, not the project root — since all files here live at `src/html/` root, `blocks/header.html` (no `../`) is correct from every page.
- BEM naming: `.category` block (`category__breadcrumbs`, `category__crumb`, `category__crumb-sep`, `category__crumb--current`, `category__title`, `category__grid`) and `.product-card` block (`product-card__media`, `product-card__placeholder`, `product-card__title`, `product-card__cta`).
- Reuse existing CSS custom properties only: `var(--accent)`, `var(--accent-dark)`, `var(--accent-tint)`, `var(--text-color)`, `var(--font-accent)` (all defined and already used in `_products.scss`, `_about.scss`, `_footer.scss`).
- Header is `position: fixed` (`_header.scss:2`) — every new page's first section needs the same top offset already used by `.hero__inner` (`margin-top: 100px` desktop / `86px` mobile via the `mobile` mixin) so content doesn't render under it.
- Do NOT reuse the `.title-1` class for the new page h1 — it hardcodes `color: #fff` (`_titles.scss:5`), meant for the dark hero gradient background; on a plain-background category page it would be invisible. Give `.category__title` its own color.
- Mock card CTA reuses the existing `.btn-link` class (`_btn.scss`) combined with a `product-card__cta` layout class, same pattern as `.products__cta` already does with `btn-link`.
- Card copy is intentionally generic/placeholder: title "Produkts N" (N = 1..6), no price/description, per approved spec.
- The grouping link "Rotējošie instrumenti" (`products__subtitle` / `mega-menu__subtitle`) is NOT a leaf — its `href="#"` stays untouched. Only its 3 children get pages.

---

### Task 1: Shared partial + SCSS foundation

**Files:**
- Create: `src/html/blocks/category-page.html`
- Create: `src/scss/blocks/_category.scss`
- Create: `src/scss/blocks/_product-card.scss`

**Interfaces:**
- Consumes: nothing new (uses existing `--accent`, `--accent-dark`, `--accent-tint`, `--text-color`, `--font-accent` custom properties, existing `mobile`/`tablet-small` SCSS mixins already used in `_hero.scss` and `_products.scss`, existing `.btn-link` class from `_btn.scss`, existing `.container` class).
- Produces: `blocks/category-page.html` partial accepting two JSON params — `crumbCategory` (string) and `title` (string) — consumed by every page file in Tasks 2-5. CSS classes `.category*` and `.product-card*` consumed by the same partial only (no other file references them yet).

- [ ] **Step 1: Create `src/html/blocks/category-page.html`**

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

		<div class="category__grid">
			<div class="product-card">
				<div class="product-card__media">
					<span class="product-card__placeholder">GEHWOL</span>
				</div>
				<h3 class="product-card__title">Produkts 1</h3>
				<a href="index.html#contacts" class="product-card__cta btn-link">Sazinieties, lai pasūtītu →</a>
			</div>
			<div class="product-card">
				<div class="product-card__media">
					<span class="product-card__placeholder">GEHWOL</span>
				</div>
				<h3 class="product-card__title">Produkts 2</h3>
				<a href="index.html#contacts" class="product-card__cta btn-link">Sazinieties, lai pasūtītu →</a>
			</div>
			<div class="product-card">
				<div class="product-card__media">
					<span class="product-card__placeholder">GEHWOL</span>
				</div>
				<h3 class="product-card__title">Produkts 3</h3>
				<a href="index.html#contacts" class="product-card__cta btn-link">Sazinieties, lai pasūtītu →</a>
			</div>
			<div class="product-card">
				<div class="product-card__media">
					<span class="product-card__placeholder">GEHWOL</span>
				</div>
				<h3 class="product-card__title">Produkts 4</h3>
				<a href="index.html#contacts" class="product-card__cta btn-link">Sazinieties, lai pasūtītu →</a>
			</div>
			<div class="product-card">
				<div class="product-card__media">
					<span class="product-card__placeholder">GEHWOL</span>
				</div>
				<h3 class="product-card__title">Produkts 5</h3>
				<a href="index.html#contacts" class="product-card__cta btn-link">Sazinieties, lai pasūtītu →</a>
			</div>
			<div class="product-card">
				<div class="product-card__media">
					<span class="product-card__placeholder">GEHWOL</span>
				</div>
				<h3 class="product-card__title">Produkts 6</h3>
				<a href="index.html#contacts" class="product-card__cta btn-link">Sazinieties, lai pasūtītu →</a>
			</div>
		</div>
	</div>
</section>
```

- [ ] **Step 2: Create `src/scss/blocks/_category.scss`**

```scss
.category {
	margin: 0;
}

.category__container {
	padding-top: 100px;
	padding-bottom: 60px;

	@include mobile {
		padding-top: 86px;
		padding-bottom: 40px;
	}
}

.category__breadcrumbs {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin-bottom: 20px;
	font-size: 14px;
	color: var(--text-color);
}

.category__crumb {
	color: var(--text-color);

	&:hover {
		color: var(--accent);
	}
}

.category__crumb--current {
	color: var(--accent-dark);
	font-weight: 600;
}

.category__crumb-sep {
	color: var(--text-color);
	opacity: 0.5;
}

.category__title {
	margin: 0 0 30px;
	font-size: clamp(2rem, 1.8rem + 1vw, 2.75rem);
	font-weight: 700;
	color: var(--accent-dark);
}

.category__grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 24px;

	@include tablet-small {
		grid-template-columns: 1fr;
	}
}
```

- [ ] **Step 3: Create `src/scss/blocks/_product-card.scss`**

```scss
.product-card {
	display: flex;
	flex-direction: column;
	padding: 20px;
	background-color: var(--accent-tint);
	border-left: 4px solid var(--accent);
	transition: box-shadow 0.2s ease;

	&:hover {
		box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
	}
}

.product-card__media {
	display: flex;
	align-items: center;
	justify-content: center;
	aspect-ratio: 1 / 1;
	margin-bottom: 16px;
	background: var(--accent);
	border-radius: 8px;
}

.product-card__placeholder {
	font-family: var(--font-accent);
	font-size: 14px;
	font-weight: 700;
	letter-spacing: 0.08em;
	color: #fff;
	text-transform: uppercase;
}

.product-card__title {
	margin: 0 0 16px;
	font-size: 16px;
	font-weight: 700;
	color: var(--accent-dark);
}

.product-card__cta {
	margin-top: auto;
}
```

- [ ] **Step 4: Verify SCSS compiles**

Run: `npx gulp sass:dev`
Expected: task finishes with no error output (no `Error:` lines), matching the output pattern of a clean run (`Finished 'sass:dev' after ...`).

- [ ] **Step 5: Commit**

```bash
git add src/html/blocks/category-page.html src/scss/blocks/_category.scss src/scss/blocks/_product-card.scss
git commit -m "feat: add category page partial and product-card component styles"
```

---

### Task 2: First real page — `gehwol-classic.html` (proves the template end to end)

**Files:**
- Create: `src/html/gehwol-classic.html`

**Interfaces:**
- Consumes: `blocks/category-page.html` partial from Task 1, `blocks/header.html`, `blocks/footer.html` (existing).
- Produces: the exact page template every remaining leaf page in Tasks 3-5 copies verbatim, substituting only `<title>`, the `content` meta, the `crumbCategory` param, and the `title` param.

- [ ] **Step 1: Create `src/html/gehwol-classic.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="GEHWOL Classic — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>GEHWOL Classic — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Kosmētika",
			"title": "GEHWOL Classic"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 2: Build and verify output structure**

Run: `npx gulp html:dev`
Expected: finishes with no error output.

Run: `grep -c "product-card__title" build/gehwol-classic.html`
Expected: `6`

Run: `grep "category__title\|category__crumb--current\|link rel=.stylesheet" build/gehwol-classic.html`
Expected: shows `<h1 class="category__title">GEHWOL Classic</h1>`, breadcrumb current crumb `GEHWOL Classic`, and `<link rel="stylesheet" href="./css/main.css" />` (confirms flat placement kept asset path intact — this is the exact failure mode Task-1's constraint note warns about).

- [ ] **Step 3: Commit**

```bash
git add src/html/gehwol-classic.html
git commit -m "feat: add GEHWOL Classic category page"
```

---

### Task 3: Remaining Kosmētika pages (6 pages)

**Files:**
- Create: `src/html/gehwol-med.html`
- Create: `src/html/gehwol-fusskraft.html`
- Create: `src/html/gehwol-fusskraft-soft-feet.html`
- Create: `src/html/gehwol-professional.html`
- Create: `src/html/gerlasan.html`
- Create: `src/html/gerlavit.html`

**Interfaces:**
- Consumes: the exact template proven in Task 2, `blocks/category-page.html`.
- Produces: 6 more pages consumed by Task 6's link rewiring.

Each file is the Task 2 template with `<title>`, the description meta, and the two include params swapped per row below. `crumbCategory` is `"Kosmētika"` for all six.

| File | `<title>` | meta description | `title` param |
|---|---|---|---|
| `gehwol-med.html` | `GEHWOL MED® — Gehwole` | `GEHWOL MED® — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā.` | `GEHWOL MED®` |
| `gehwol-fusskraft.html` | `GEHWOL FUSSKRAFT — Gehwole` | `GEHWOL FUSSKRAFT — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā.` | `GEHWOL FUSSKRAFT` |
| `gehwol-fusskraft-soft-feet.html` | `GEHWOL FUSSKRAFT Soft Feet — Gehwole` | `GEHWOL FUSSKRAFT Soft Feet — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā.` | `GEHWOL FUSSKRAFT Soft Feet` |
| `gehwol-professional.html` | `GEHWOL PROFESSIONAL — Gehwole` | `GEHWOL PROFESSIONAL — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā.` | `GEHWOL PROFESSIONAL` |
| `gerlasan.html` | `GERLASAN — Gehwole` | `GERLASAN — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā.` | `GERLASAN` |
| `gerlavit.html` | `GERLAVIT — Gehwole` | `GERLAVIT — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā.` | `GERLAVIT` |

- [ ] **Step 1: Create `src/html/gehwol-med.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="GEHWOL MED® — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>GEHWOL MED® — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Kosmētika",
			"title": "GEHWOL MED®"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 2: Create `src/html/gehwol-fusskraft.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="GEHWOL FUSSKRAFT — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>GEHWOL FUSSKRAFT — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Kosmētika",
			"title": "GEHWOL FUSSKRAFT"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 3: Create `src/html/gehwol-fusskraft-soft-feet.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="GEHWOL FUSSKRAFT Soft Feet — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>GEHWOL FUSSKRAFT Soft Feet — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Kosmētika",
			"title": "GEHWOL FUSSKRAFT Soft Feet"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 4: Create `src/html/gehwol-professional.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="GEHWOL PROFESSIONAL — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>GEHWOL PROFESSIONAL — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Kosmētika",
			"title": "GEHWOL PROFESSIONAL"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 5: Create `src/html/gerlasan.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="GERLASAN — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>GERLASAN — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Kosmētika",
			"title": "GERLASAN"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 6: Create `src/html/gerlavit.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="GERLAVIT — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>GERLAVIT — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Kosmētika",
			"title": "GERLAVIT"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 7: Build and verify all 6 pages exist with correct titles**

Run: `npx gulp html:dev`
Expected: finishes with no error output.

Run: `grep -l "GEHWOL MED" build/gehwol-med.html build/gehwol-fusskraft.html build/gehwol-fusskraft-soft-feet.html build/gehwol-professional.html build/gerlasan.html build/gerlavit.html 2>&1`
Expected: only `build/gehwol-med.html` listed (confirms each file has its own distinct title, not a copy-paste leftover from another).

Run: `for f in gehwol-med gehwol-fusskraft gehwol-fusskraft-soft-feet gehwol-professional gerlasan gerlavit; do echo -n "$f: "; grep -c "product-card__title" build/$f.html; done`
Expected: every line ends `: 6`

- [ ] **Step 8: Commit**

```bash
git add src/html/gehwol-med.html src/html/gehwol-fusskraft.html src/html/gehwol-fusskraft-soft-feet.html src/html/gehwol-professional.html src/html/gerlasan.html src/html/gerlavit.html
git commit -m "feat: add remaining Kosmētika category pages"
```

---

### Task 4: Spiedienu uz pēdām mazinoši līdzekļi pages (4 pages)

**Files:**
- Create: `src/html/polimera-gela-izstradajumi-parvilkti-ar-tekstilu.html`
- Create: `src/html/polimera-gela-izstradajumi.html`
- Create: `src/html/plaksteri.html`
- Create: `src/html/filca-izstradajumi.html`

**Interfaces:**
- Consumes: same template as Task 2/3, `blocks/category-page.html`.
- Produces: 4 more pages consumed by Task 6's link rewiring.

`crumbCategory` is `"Spiedienu uz pēdām mazinoši līdzekļi"` for all four.

- [ ] **Step 1: Create `src/html/polimera-gela-izstradajumi-parvilkti-ar-tekstilu.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Polimēra gēla izstrādājumi, pārvilkti ar tekstilu — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>Polimēra gēla izstrādājumi, pārvilkti ar tekstilu — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Spiedienu uz pēdām mazinoši līdzekļi",
			"title": "Polimēra gēla izstrādājumi, pārvilkti ar tekstilu"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 2: Create `src/html/polimera-gela-izstradajumi.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Polimēra gēla izstrādājumi — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>Polimēra gēla izstrādājumi — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Spiedienu uz pēdām mazinoši līdzekļi",
			"title": "Polimēra gēla izstrādājumi"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 3: Create `src/html/plaksteri.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Plāksteri — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>Plāksteri — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Spiedienu uz pēdām mazinoši līdzekļi",
			"title": "Plāksteri"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 4: Create `src/html/filca-izstradajumi.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Filca izstrādājumi — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>Filca izstrādājumi — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Spiedienu uz pēdām mazinoši līdzekļi",
			"title": "Filca izstrādājumi"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 5: Build and verify**

Run: `npx gulp html:dev`
Expected: finishes with no error output.

Run: `for f in polimera-gela-izstradajumi-parvilkti-ar-tekstilu polimera-gela-izstradajumi plaksteri filca-izstradajumi; do echo -n "$f: "; grep -c "product-card__title" build/$f.html; done`
Expected: every line ends `: 6`

- [ ] **Step 6: Commit**

```bash
git add src/html/polimera-gela-izstradajumi-parvilkti-ar-tekstilu.html src/html/polimera-gela-izstradajumi.html src/html/plaksteri.html src/html/filca-izstradajumi.html
git commit -m "feat: add Spiedienu uz pēdām mazinoši līdzekļi category pages"
```

---

### Task 5: Tehnika pages (6 pages)

**Files:**
- Create: `src/html/pedu-kopsanas-aparati.html`
- Create: `src/html/pacienta-kresli.html`
- Create: `src/html/darbinieka-kresli.html`
- Create: `src/html/keramiskas-frezes.html`
- Create: `src/html/puletaji.html`
- Create: `src/html/vienreizlietojamas-smilspapira-frezes.html`

**Interfaces:**
- Consumes: same template as Task 2/3/4, `blocks/category-page.html`.
- Produces: last 6 pages consumed by Task 6's link rewiring. The 3 "Rotējošie instrumenti" children (`keramiskas-frezes`, `puletaji`, `vienreizlietojamas-smilspapira-frezes`) use `crumbCategory: "Tehnika"` — same as their 3 direct siblings — since the design (spec) scoped breadcrumbs to 2 levels (category → item), not 3; "Rotējošie instrumenti" itself has no page to link to.

`crumbCategory` is `"Tehnika"` for all six.

- [ ] **Step 1: Create `src/html/pedu-kopsanas-aparati.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Pēdu kopšanas aparāti — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>Pēdu kopšanas aparāti — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Tehnika",
			"title": "Pēdu kopšanas aparāti"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 2: Create `src/html/pacienta-kresli.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Pacienta krēsli — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>Pacienta krēsli — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Tehnika",
			"title": "Pacienta krēsli"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 3: Create `src/html/darbinieka-kresli.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Darbinieka krēsli — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>Darbinieka krēsli — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Tehnika",
			"title": "Darbinieka krēsli"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 4: Create `src/html/keramiskas-frezes.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Keramiskās frēzes — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>Keramiskās frēzes — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Tehnika",
			"title": "Keramiskās frēzes"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 5: Create `src/html/puletaji.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Pulētāji — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>Pulētāji — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Tehnika",
			"title": "Pulētāji"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 6: Create `src/html/vienreizlietojamas-smilspapira-frezes.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Vienreizlietojamās smilšpapīra frēzes — Gehwole produkcijas kategorija. Oficiālā izplatītāja piedāvājums Latvijā." />
	<title>Vienreizlietojamās smilšpapīra frēzes — Gehwole</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/category-page.html', {
			"crumbCategory": "Tehnika",
			"title": "Vienreizlietojamās smilšpapīra frēzes"
		})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 7: Build and verify**

Run: `npx gulp html:dev`
Expected: finishes with no error output.

Run: `for f in pedu-kopsanas-aparati pacienta-kresli darbinieka-kresli keramiskas-frezes puletaji vienreizlietojamas-smilspapira-frezes; do echo -n "$f: "; grep -c "product-card__title" build/$f.html; done`
Expected: every line ends `: 6`

- [ ] **Step 8: Commit**

```bash
git add src/html/pedu-kopsanas-aparati.html src/html/pacienta-kresli.html src/html/darbinieka-kresli.html src/html/keramiskas-frezes.html src/html/puletaji.html src/html/vienreizlietojamas-smilspapira-frezes.html
git commit -m "feat: add Tehnika category pages"
```

---

### Task 6: Wire up `products.html` links

**Files:**
- Modify: `src/html/blocks/products.html`

**Interfaces:**
- Consumes: the 17 filenames created in Tasks 2-5.
- Produces: a `products.html` with no remaining `href="#"` on leaf links (the "Rotējošie instrumenti" subtitle keeps `href="#"` — not a leaf).

- [ ] **Step 1: Replace the 7 Kosmētika leaf hrefs**

In `src/html/blocks/products.html`, replace:

```html
				<div class="products__card">
					<a href="#" class="products__card-title">Kosmētika</a>
					<ul class="products__list">
						<li><a href="#" class="products__link">GEHWOL Classic</a></li>
						<li><a href="#" class="products__link">GEHWOL MED®</a></li>
						<li><a href="#" class="products__link">GEHWOL FUSSKRAFT</a></li>
						<li><a href="#" class="products__link">GEHWOL FUSSKRAFT Soft Feet</a></li>
						<li><a href="#" class="products__link">GEHWOL PROFESSIONAL</a></li>
						<li><a href="#" class="products__link">GERLASAN</a></li>
						<li><a href="#" class="products__link">GERLAVIT</a></li>
					</ul>
				</div>
```

with:

```html
				<div class="products__card">
					<a href="#" class="products__card-title">Kosmētika</a>
					<ul class="products__list">
						<li><a href="gehwol-classic.html" class="products__link">GEHWOL Classic</a></li>
						<li><a href="gehwol-med.html" class="products__link">GEHWOL MED®</a></li>
						<li><a href="gehwol-fusskraft.html" class="products__link">GEHWOL FUSSKRAFT</a></li>
						<li><a href="gehwol-fusskraft-soft-feet.html" class="products__link">GEHWOL FUSSKRAFT Soft Feet</a></li>
						<li><a href="gehwol-professional.html" class="products__link">GEHWOL PROFESSIONAL</a></li>
						<li><a href="gerlasan.html" class="products__link">GERLASAN</a></li>
						<li><a href="gerlavit.html" class="products__link">GERLAVIT</a></li>
					</ul>
				</div>
```

- [ ] **Step 2: Replace the 4 Spiedienu uz pēdām leaf hrefs**

Replace:

```html
				<div class="products__card">
					<a href="#" class="products__card-title">Spiedienu uz pēdām mazinoši līdzekļi</a>
					<ul class="products__list">
						<li><a href="#" class="products__link">Polimēra gēla izstrādājumi, pārvilkti ar tekstilu</a></li>
						<li><a href="#" class="products__link">Polimēra gēla izstrādājumi</a></li>
						<li><a href="#" class="products__link">Plāksteri</a></li>
						<li><a href="#" class="products__link">Filca izstrādājumi</a></li>
					</ul>
				</div>
```

with:

```html
				<div class="products__card">
					<a href="#" class="products__card-title">Spiedienu uz pēdām mazinoši līdzekļi</a>
					<ul class="products__list">
						<li><a href="polimera-gela-izstradajumi-parvilkti-ar-tekstilu.html" class="products__link">Polimēra gēla izstrādājumi, pārvilkti ar tekstilu</a></li>
						<li><a href="polimera-gela-izstradajumi.html" class="products__link">Polimēra gēla izstrādājumi</a></li>
						<li><a href="plaksteri.html" class="products__link">Plāksteri</a></li>
						<li><a href="filca-izstradajumi.html" class="products__link">Filca izstrādājumi</a></li>
					</ul>
				</div>
```

- [ ] **Step 3: Replace the 6 Tehnika leaf hrefs (group heading link stays `#`)**

Replace:

```html
				<div class="products__card">
					<a href="#" class="products__card-title">Tehnika</a>
					<ul class="products__list">
						<li><a href="#" class="products__link">Pēdu kopšanas aparāti</a></li>
						<li><a href="#" class="products__link">Pacienta krēsli</a></li>
						<li><a href="#" class="products__link">Darbinieka krēsli</a></li>
						<li>
							<a href="#" class="products__subtitle">Rotējošie instrumenti</a>
							<ul class="products__sublist">
								<li><a href="#" class="products__link">Keramiskās frēzes</a></li>
								<li><a href="#" class="products__link">Pulētāji</a></li>
								<li><a href="#" class="products__link">Vienreizlietojamās smilšpapīra frēzes</a></li>
							</ul>
						</li>
					</ul>
				</div>
```

with:

```html
				<div class="products__card">
					<a href="#" class="products__card-title">Tehnika</a>
					<ul class="products__list">
						<li><a href="pedu-kopsanas-aparati.html" class="products__link">Pēdu kopšanas aparāti</a></li>
						<li><a href="pacienta-kresli.html" class="products__link">Pacienta krēsli</a></li>
						<li><a href="darbinieka-kresli.html" class="products__link">Darbinieka krēsli</a></li>
						<li>
							<a href="#" class="products__subtitle">Rotējošie instrumenti</a>
							<ul class="products__sublist">
								<li><a href="keramiskas-frezes.html" class="products__link">Keramiskās frēzes</a></li>
								<li><a href="puletaji.html" class="products__link">Pulētāji</a></li>
								<li><a href="vienreizlietojamas-smilspapira-frezes.html" class="products__link">Vienreizlietojamās smilšpapīra frēzes</a></li>
							</ul>
						</li>
					</ul>
				</div>
```

- [ ] **Step 4: Verify no leaf links still point to `#`**

Run: `grep -c 'products__link" href="#"\|href="#" class="products__link"' src/html/blocks/products.html`
Expected: `0`

Run: `npx gulp html:dev && grep -o 'class="products__link"[^>]*\|href="[^"]*" class="products__link"' build/index.html | head -20`
Expected: no error output from the build; the 17 `products__link` anchors in `build/index.html` show real `.html` filenames, not `#`.

- [ ] **Step 5: Commit**

```bash
git add src/html/blocks/products.html
git commit -m "feat: link products section leaf items to their category pages"
```

---

### Task 7: Wire up `header.html` mega-menu links

**Files:**
- Modify: `src/html/blocks/header.html`

**Interfaces:**
- Consumes: the same 17 filenames as Task 6.
- Produces: a `header.html` mega-menu with no remaining `href="#"` on leaf links (the "Rotējošie instrumenti" subtitle keeps `href="#"`).

- [ ] **Step 1: Replace the 7 Kosmētika mega-menu hrefs**

In `src/html/blocks/header.html`, replace:

```html
							<div class="mega-menu__col">
								<a href="#" class="mega-menu__title">Kosmētika</a>
								<ul class="mega-menu__list">
									<li><a href="#" class="mega-menu__link">GEHWOL Classic</a></li>
									<li><a href="#" class="mega-menu__link">GEHWOL MED®</a></li>
									<li><a href="#" class="mega-menu__link">GEHWOL FUSSKRAFT</a></li>
									<li><a href="#" class="mega-menu__link">GEHWOL FUSSKRAFT Soft Feet</a></li>
									<li><a href="#" class="mega-menu__link">GEHWOL PROFESSIONAL</a></li>
									<li><a href="#" class="mega-menu__link">GERLASAN</a></li>
									<li><a href="#" class="mega-menu__link">GERLAVIT</a></li>
								</ul>
							</div>
```

with:

```html
							<div class="mega-menu__col">
								<a href="#" class="mega-menu__title">Kosmētika</a>
								<ul class="mega-menu__list">
									<li><a href="gehwol-classic.html" class="mega-menu__link">GEHWOL Classic</a></li>
									<li><a href="gehwol-med.html" class="mega-menu__link">GEHWOL MED®</a></li>
									<li><a href="gehwol-fusskraft.html" class="mega-menu__link">GEHWOL FUSSKRAFT</a></li>
									<li><a href="gehwol-fusskraft-soft-feet.html" class="mega-menu__link">GEHWOL FUSSKRAFT Soft Feet</a></li>
									<li><a href="gehwol-professional.html" class="mega-menu__link">GEHWOL PROFESSIONAL</a></li>
									<li><a href="gerlasan.html" class="mega-menu__link">GERLASAN</a></li>
									<li><a href="gerlavit.html" class="mega-menu__link">GERLAVIT</a></li>
								</ul>
							</div>
```

- [ ] **Step 2: Replace the 4 Spiedienu uz pēdām mega-menu hrefs**

Replace:

```html
							<div class="mega-menu__col">
								<a href="#" class="mega-menu__title">Spiedienu uz pēdām mazinoši līdzekļi</a>
								<ul class="mega-menu__list">
									<li><a href="#" class="mega-menu__link">Polimēra gēla izstrādājumi, pārvilkti ar tekstilu</a></li>
									<li><a href="#" class="mega-menu__link">Polimēra gēla izstrādājumi</a></li>
									<li><a href="#" class="mega-menu__link">Plāksteri</a></li>
									<li><a href="#" class="mega-menu__link">Filca izstrādājumi</a></li>
								</ul>
							</div>
```

with:

```html
							<div class="mega-menu__col">
								<a href="#" class="mega-menu__title">Spiedienu uz pēdām mazinoši līdzekļi</a>
								<ul class="mega-menu__list">
									<li><a href="polimera-gela-izstradajumi-parvilkti-ar-tekstilu.html" class="mega-menu__link">Polimēra gēla izstrādājumi, pārvilkti ar tekstilu</a></li>
									<li><a href="polimera-gela-izstradajumi.html" class="mega-menu__link">Polimēra gēla izstrādājumi</a></li>
									<li><a href="plaksteri.html" class="mega-menu__link">Plāksteri</a></li>
									<li><a href="filca-izstradajumi.html" class="mega-menu__link">Filca izstrādājumi</a></li>
								</ul>
							</div>
```

- [ ] **Step 3: Replace the 6 Tehnika mega-menu hrefs (group heading link stays `#`)**

Replace:

```html
							<div class="mega-menu__col">
								<a href="#" class="mega-menu__title">Tehnika</a>
								<ul class="mega-menu__list">
									<li><a href="#" class="mega-menu__link">Pēdu kopšanas aparāti</a></li>
									<li><a href="#" class="mega-menu__link">Pacienta krēsli</a></li>
									<li><a href="#" class="mega-menu__link">Darbinieka krēsli</a></li>
									<li>
										<a href="#" class="mega-menu__subtitle">Rotējošie instrumenti</a>
										<ul class="mega-menu__sublist">
											<li><a href="#" class="mega-menu__link">Keramiskās frēzes</a></li>
											<li><a href="#" class="mega-menu__link">Pulētāji</a></li>
											<li><a href="#" class="mega-menu__link">Vienreizlietojamās smilšpapīra frēzes</a></li>
										</ul>
									</li>
								</ul>
							</div>
```

with:

```html
							<div class="mega-menu__col">
								<a href="#" class="mega-menu__title">Tehnika</a>
								<ul class="mega-menu__list">
									<li><a href="pedu-kopsanas-aparati.html" class="mega-menu__link">Pēdu kopšanas aparāti</a></li>
									<li><a href="pacienta-kresli.html" class="mega-menu__link">Pacienta krēsli</a></li>
									<li><a href="darbinieka-kresli.html" class="mega-menu__link">Darbinieka krēsli</a></li>
									<li>
										<a href="#" class="mega-menu__subtitle">Rotējošie instrumenti</a>
										<ul class="mega-menu__sublist">
											<li><a href="keramiskas-frezes.html" class="mega-menu__link">Keramiskās frēzes</a></li>
											<li><a href="puletaji.html" class="mega-menu__link">Pulētāji</a></li>
											<li><a href="vienreizlietojamas-smilspapira-frezes.html" class="mega-menu__link">Vienreizlietojamās smilšpapīra frēzes</a></li>
										</ul>
									</li>
								</ul>
							</div>
```

- [ ] **Step 4: Verify no mega-menu leaf links still point to `#`**

Run: `grep -c 'mega-menu__link" href="#"\|href="#" class="mega-menu__link"' src/html/blocks/header.html`
Expected: `0`

- [ ] **Step 5: Commit**

```bash
git add src/html/blocks/header.html
git commit -m "feat: link header mega-menu leaf items to their category pages"
```

---

### Task 8: Wire up `mobile-nav.html` links

**Files:**
- Modify: `src/html/blocks/mobile-nav.html`

**Interfaces:**
- Consumes: the same 17 filenames as Tasks 6-7.
- Produces: a `mobile-nav.html` with no remaining `href="#"` on leaf links. This is a third, previously-undiscovered duplicate of the same 17-link list (class `mobile-nav__sublink`) — `header.html` includes it via `@@include('mobile-nav.html')` for the responsive/mobile menu. The "Rotējošie instrumenti" entry here is a `<button>`, not a link, so there's nothing to leave alone for it beyond not touching the button.

- [ ] **Step 1: Replace the 7 Kosmētika mobile-nav hrefs**

In `src/html/blocks/mobile-nav.html`, replace:

```html
					<li class="mobile-nav__item">
						<button type="button" class="mobile-nav__toggle">Kosmētika</button>
						<ul class="mobile-nav__sub">
							<li><a href="#" class="mobile-nav__sublink">GEHWOL Classic</a></li>
							<li><a href="#" class="mobile-nav__sublink">GEHWOL MED®</a></li>
							<li><a href="#" class="mobile-nav__sublink">GEHWOL FUSSKRAFT</a></li>
							<li><a href="#" class="mobile-nav__sublink">GEHWOL FUSSKRAFT Soft Feet</a></li>
							<li><a href="#" class="mobile-nav__sublink">GEHWOL PROFESSIONAL</a></li>
							<li><a href="#" class="mobile-nav__sublink">GERLASAN</a></li>
							<li><a href="#" class="mobile-nav__sublink">GERLAVIT</a></li>
						</ul>
					</li>
```

with:

```html
					<li class="mobile-nav__item">
						<button type="button" class="mobile-nav__toggle">Kosmētika</button>
						<ul class="mobile-nav__sub">
							<li><a href="gehwol-classic.html" class="mobile-nav__sublink">GEHWOL Classic</a></li>
							<li><a href="gehwol-med.html" class="mobile-nav__sublink">GEHWOL MED®</a></li>
							<li><a href="gehwol-fusskraft.html" class="mobile-nav__sublink">GEHWOL FUSSKRAFT</a></li>
							<li><a href="gehwol-fusskraft-soft-feet.html" class="mobile-nav__sublink">GEHWOL FUSSKRAFT Soft Feet</a></li>
							<li><a href="gehwol-professional.html" class="mobile-nav__sublink">GEHWOL PROFESSIONAL</a></li>
							<li><a href="gerlasan.html" class="mobile-nav__sublink">GERLASAN</a></li>
							<li><a href="gerlavit.html" class="mobile-nav__sublink">GERLAVIT</a></li>
						</ul>
					</li>
```

- [ ] **Step 2: Replace the 4 Spiedienu uz pēdām mobile-nav hrefs**

Replace:

```html
					<li class="mobile-nav__item">
						<button type="button" class="mobile-nav__toggle">Spiedienu uz pēdām mazinoši līdzekļi</button>
						<ul class="mobile-nav__sub">
							<li><a href="#" class="mobile-nav__sublink">Polimēra gēla izstrādājumi, pārvilkti ar tekstilu</a></li>
							<li><a href="#" class="mobile-nav__sublink">Polimēra gēla izstrādājumi</a></li>
							<li><a href="#" class="mobile-nav__sublink">Plāksteri</a></li>
							<li><a href="#" class="mobile-nav__sublink">Filca izstrādājumi</a></li>
						</ul>
					</li>
```

with:

```html
					<li class="mobile-nav__item">
						<button type="button" class="mobile-nav__toggle">Spiedienu uz pēdām mazinoši līdzekļi</button>
						<ul class="mobile-nav__sub">
							<li><a href="polimera-gela-izstradajumi-parvilkti-ar-tekstilu.html" class="mobile-nav__sublink">Polimēra gēla izstrādājumi, pārvilkti ar tekstilu</a></li>
							<li><a href="polimera-gela-izstradajumi.html" class="mobile-nav__sublink">Polimēra gēla izstrādājumi</a></li>
							<li><a href="plaksteri.html" class="mobile-nav__sublink">Plāksteri</a></li>
							<li><a href="filca-izstradajumi.html" class="mobile-nav__sublink">Filca izstrādājumi</a></li>
						</ul>
					</li>
```

- [ ] **Step 3: Replace the 6 Tehnika mobile-nav hrefs (toggle button untouched)**

Replace:

```html
					<li class="mobile-nav__item">
						<button type="button" class="mobile-nav__toggle">Tehnika</button>
						<ul class="mobile-nav__sub">
							<li><a href="#" class="mobile-nav__sublink">Pēdu kopšanas aparāti</a></li>
							<li><a href="#" class="mobile-nav__sublink">Pacienta krēsli</a></li>
							<li><a href="#" class="mobile-nav__sublink">Darbinieka krēsli</a></li>
							<li class="mobile-nav__item">
								<button type="button" class="mobile-nav__toggle">Rotējošie instrumenti</button>
								<ul class="mobile-nav__sub">
									<li><a href="#" class="mobile-nav__sublink">Keramiskās frēzes</a></li>
									<li><a href="#" class="mobile-nav__sublink">Pulētāji</a></li>
									<li><a href="#" class="mobile-nav__sublink">Vienreizlietojamās smilšpapīra frēzes</a></li>
								</ul>
							</li>
						</ul>
					</li>
```

with:

```html
					<li class="mobile-nav__item">
						<button type="button" class="mobile-nav__toggle">Tehnika</button>
						<ul class="mobile-nav__sub">
							<li><a href="pedu-kopsanas-aparati.html" class="mobile-nav__sublink">Pēdu kopšanas aparāti</a></li>
							<li><a href="pacienta-kresli.html" class="mobile-nav__sublink">Pacienta krēsli</a></li>
							<li><a href="darbinieka-kresli.html" class="mobile-nav__sublink">Darbinieka krēsli</a></li>
							<li class="mobile-nav__item">
								<button type="button" class="mobile-nav__toggle">Rotējošie instrumenti</button>
								<ul class="mobile-nav__sub">
									<li><a href="keramiskas-frezes.html" class="mobile-nav__sublink">Keramiskās frēzes</a></li>
									<li><a href="puletaji.html" class="mobile-nav__sublink">Pulētāji</a></li>
									<li><a href="vienreizlietojamas-smilspapira-frezes.html" class="mobile-nav__sublink">Vienreizlietojamās smilšpapīra frēzes</a></li>
								</ul>
							</li>
						</ul>
					</li>
```

- [ ] **Step 4: Verify no mobile-nav leaf links still point to `#`**

Run: `grep -c 'mobile-nav__sublink" href="#"\|href="#" class="mobile-nav__sublink"' src/html/blocks/mobile-nav.html`
Expected: `0`

- [ ] **Step 5: Commit**

```bash
git add src/html/blocks/mobile-nav.html
git commit -m "feat: link mobile nav leaf items to their category pages"
```

---

### Task 9: Full build verification

**Files:** none created/modified — verification only.

**Interfaces:**
- Consumes: everything from Tasks 1-8.
- Produces: confidence that the full site builds clean and every leaf link resolves to a real file with correct content.

- [ ] **Step 1: Full clean build**

Run: `npx gulp html:dev && npx gulp sass:dev`
Expected: both finish with no error output.

- [ ] **Step 2: Confirm all 17 pages exist in build output**

Run:
```bash
for f in gehwol-classic gehwol-med gehwol-fusskraft gehwol-fusskraft-soft-feet gehwol-professional gerlasan gerlavit polimera-gela-izstradajumi-parvilkti-ar-tekstilu polimera-gela-izstradajumi plaksteri filca-izstradajumi pedu-kopsanas-aparati pacienta-kresli darbinieka-kresli keramiskas-frezes puletaji vienreizlietojamas-smilspapira-frezes; do
  test -f "build/$f.html" && echo "OK $f" || echo "MISSING $f"
done
```
Expected: 17 lines, all `OK`.

- [ ] **Step 3: Confirm every leaf link in the built index page resolves to one of those files**

Run: `grep -oE 'class="(products__link|mega-menu__link|mobile-nav__sublink)"' build/index.html | wc -l`
Expected: `51` (17 links × 3 places — products section, header mega-menu, mobile nav)

Run: `grep -oE 'href="[a-z0-9-]+\.html"' build/index.html | sort -u | wc -l`
Expected: `17` (each of the 17 filenames referenced, deduplicated — confirms the products section, mega-menu, and mobile nav all point to the same set of real files, not typos)

- [ ] **Step 4: Spot-check one Tehnika sub-item page for correct breadcrumb and asset paths**

Run: `grep 'category__crumb\|stylesheet' build/keramiskas-frezes.html`
Expected: shows crumb chain ending in `Keramiskās frēzes`, and `<link rel="stylesheet" href="./css/main.css" />` (not `../css/main.css` or a 404-prone path).

- [ ] **Step 5: Confirm existing pages still work (no regression)**

Run: `grep -c "hero__title\|footer__copyright" build/index.html`
Expected: `2` (hero and footer sections still render — confirms this work didn't break unrelated parts of `index.html`).
