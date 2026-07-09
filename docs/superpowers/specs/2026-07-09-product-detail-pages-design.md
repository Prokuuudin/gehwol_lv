# Product Detail Pages — Design

Date: 2026-07-09

## Problem

Each category page (`src/html/<slug>.html`, 17 of them) shows 6 identical mock product cards via the shared `blocks/category-page.html` partial. Every card's CTA currently reads "Sazinieties, lai pasūtītu →" and links straight to `index.html#contacts`. The card should instead say "Uzzināt vairāk →" and lead to a product detail page showing that product's (mock) description; the actual "contact to order" CTA moves to that detail page.

## Scope

Card content is identical mock data on all 17 category pages ("Produkts 1".."Produkts 6"), so only **6** detail pages are needed — not one per category × product. All 17 category pages link into the same 6 shared pages.

Files: `src/html/produkts-1.html` .. `produkts-6.html` (flat, same convention as the category pages — confirmed necessary earlier in this project because `html:dev` normalizes asset paths assuming files sit at `src/html/` root).

## Card change (`blocks/category-page.html`)

Each of the 6 hardcoded `.product-card` blocks: CTA text "Sazinieties, lai pasūtītu →" → "Uzzināt vairāk →", `href="index.html#contacts"` → `href="produkts-N.html"` (N = 1..6, matching that card's position). One partial edit propagates to all 17 category pages automatically — no page file touched.

## Product detail page content

- Breadcrumb + h1 reuse the existing `.category__breadcrumbs` / `.category__crumb` / `.category__title` classes and CSS (already styled, no new rules needed) — visual continuity with category pages.
- Breadcrumb: `Sākums / Produkti / Produkts N` (2-level — the page is shared across all 17 categories, so it can't claim to belong to whichever one linked in).
- New `.product-detail` block for the body: `product-detail__media` (photo placeholder, same visual treatment as `.product-card__media`/`__placeholder` but larger), `product-detail__description` (2-3 sentence generic mock paragraph, identical wording is fine across all 6 since there's no real product data yet), `product-detail__cta` (button, reuses `.btn-link`, text "Sazinieties, lai pasūtītu →", `href="index.html#contacts"` — this is now the actual order step).

## Out of scope

- Real product data/photography
- Per-category-specific product pages (102 files) — explicitly rejected in favor of 6 shared pages
- "Back to category" link (no reliable way to know which category the visitor came from on a static multi-entry-point page)
