# News & Article Detail Pages — Design

Date: 2026-07-09

## Problem

`news.html` shows 3 "Jaunumi" (news) + 3 "Noderīgi raksti" (articles) slides, none of them links (plain `div.news__slide`, no `<a>`). User wants each item to have its own page, and wants 5+5 slides instead of 3+3 so the slider's scroll behavior is visibly demonstrated.

## Scope

- Expand `news.html` to 5 news slides + 5 article slides (2 new mock items per tab).
- Wrap each slide's content in a link to its detail page.
- 10 new flat pages: `src/html/jaunums-1.html`..`jaunums-5.html`, `src/html/raksts-1.html`..`raksts-5.html`.

## Page content

Reuses `.category` chrome (breadcrumbs + h1 — already styled, no new CSS) for visual continuity with category/product-detail pages. Breadcrumb: `Sākums / Jaunumi un informācija / [title]`, linking `Jaunumi un informācija` to `index.html#news`.

New `.content-detail` block for the body (shared by both news and article pages): `content-detail__meta` (publish date, news pages only), `content-detail__media` (photo placeholder, same visual language as `.product-card__media`), `content-detail__text` (2-3 sentence mock paragraph).

Two thin partials since only news pages carry a date:
- `blocks/news-detail-page.html` (params: `title`, `date`, `text`)
- `blocks/article-detail-page.html` (params: `title`, `text`)

No CTA button — these are informational pages, not product/order pages (unlike product-detail).

## news.html slide markup

Each `.news__slide` div's contents wrap in `<a href="jaunums-N.html" class="news__slide-link">` (or `raksts-N.html`), keeping `.swiper-slide`/`.news__slide` on the outer div untouched — swiper JS (`src/js/modules/swipers.js`) and scrollReveal target the outer div's classes, not the inner content, so this is safe.

## Out of scope

- Real news/article content or images
- Publish-date sorting/pagination
- Comments or social sharing
