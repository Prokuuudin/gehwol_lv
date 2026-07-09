# Product Category Pages — Design

Date: 2026-07-09

## Problem

`products.html` (products section) and `header.html` (mega-menu) both list the same 17 leaf-level product links, all pointing to `href="#"`. Each leaf link needs a real destination page showing mock product cards, since real catalog data doesn't exist yet.

## Scope

17 leaf links get a page each. Grouping headings that have children (e.g. "Rotējošie instrumenti") are not leaves and are out of scope — they stay as `#`.

Leaves and slugs:

**Kosmētika**
1. GEHWOL Classic → `gehwol-classic`
2. GEHWOL MED® → `gehwol-med`
3. GEHWOL FUSSKRAFT → `gehwol-fusskraft`
4. GEHWOL FUSSKRAFT Soft Feet → `gehwol-fusskraft-soft-feet`
5. GEHWOL PROFESSIONAL → `gehwol-professional`
6. GERLASAN → `gerlasan`
7. GERLAVIT → `gerlavit`

**Spiedienu uz pēdām mazinoši līdzekļi**
8. Polimēra gēla izstrādājumi, pārvilkti ar tekstilu → `polimera-gela-izstradajumi-parvilkti-ar-tekstilu`
9. Polimēra gēla izstrādājumi → `polimera-gela-izstradajumi`
10. Plāksteri → `plaksteri`
11. Filca izstrādājumi → `filca-izstradajumi`

**Tehnika**
12. Pēdu kopšanas aparāti → `pedu-kopsanas-aparati`
13. Pacienta krēsli → `pacienta-kresli`
14. Darbinieka krēsli → `darbinieka-kresli`
15. Keramiskās frēzes (under Rotējošie instrumenti) → `keramiskas-frezes`
16. Pulētāji (under Rotējošie instrumenti) → `puletaji`
17. Vienreizlietojamās smilšpapīra frēzes (under Rotējošie instrumenti) → `vienreizlietojamas-smilspapira-frezes`

## File structure

```
src/html/<slug>.html   × 17   (flat, same level as index.html)
src/html/blocks/category-page.html   (shared partial)
```

**Why flat, not `products/<slug>.html`:** confirmed empirically that `html:dev`'s asset-path-normalizing regex collapses any leading `./`/`../` on `css|img|js|fonts|...` paths down to a single `./` regardless of actual file depth (e.g. `../css/main.css` → `./css/main.css` in build output). Pages one directory below `index.html` would ship with broken CSS/image/JS links. Flat placement avoids touching that shared build config.

Each page file is thin, reusing the standard include pattern already used by `index.html`:

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	... standard meta, title, favicon, css link ...
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

gulp-file-include's JSON-param include lets one partial serve all 17 pages — only the breadcrumb category label and page title vary per file. Card markup is identical mock content everywhere, so it's hardcoded once inside `category-page.html`, not duplicated per page.

`html:dev` / `html:docs` gulp tasks already glob `./src/html/**/*.html` excluding `**/blocks/**`, so the new flat files are picked up automatically with no gulpfile changes.

## category-page.html partial (BEM)

Block: `.category`

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

		<h1 class="category__title title-1">@@title</h1>

		<div class="category__grid">
			<!-- 6 identical mock product-card blocks -->
		</div>
	</div>
</section>
```

## product-card component (BEM)

Block: `.product-card` — reusable, currently mock, real catalog data can replace content later without markup changes.

```html
<div class="product-card">
	<div class="product-card__media">
		<span class="product-card__placeholder">GEHWOL</span>
	</div>
	<h3 class="product-card__title">Produkts N</h3>
	<a href="index.html#contacts" class="product-card__cta btn-link">Sazinieties, lai pasūtītu →</a>
</div>
```

`product-card__placeholder` is a styled block (background tint + label), no image asset needed since there's no real product photography yet.

6 cards per page, numbered "Produkts 1" .. "Produkts 6", grid 3 columns desktop / 1 column mobile — matching the `.products__grid` responsive pattern already in `_products.scss`.

## Linking updates

Three files duplicate the same 17-leaf-link list: `products.html`, `header.html` (mega-menu), and `header.html`'s included `mobile-nav.html` (responsive menu — found during plan write-up, not part of the original two-file assumption). In all three, for each leaf `<a href="#" class="products__link|mega-menu__link|mobile-nav__sublink">`, change `href="#"` to `href="<slug>.html"` (all three are included into pages living at html root, so plain filename is correct — matches existing `href="index.html#about"` style already used in `header.html`).

Group heading links (`products__subtitle` / `mega-menu__subtitle` "Rotējošie instrumenti", and its `<button class="mobile-nav__toggle">` equivalent in mobile-nav) stay untouched — not a leaf, out of scope.

## Styling

New partial SCSS files: `src/scss/blocks/_category.scss`, `src/scss/blocks/_product-card.scss`, both imported automatically via the existing `@import './blocks/*.scss'` glob in `main.scss`.

## Out of scope

- Real product data/images
- Search/filter on category pages
- Pagination (6 mock cards is the whole page)
- Changing the "Rotējošie instrumenti" grouping link
