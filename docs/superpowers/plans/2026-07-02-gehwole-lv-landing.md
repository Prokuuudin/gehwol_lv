# Gehwole LV Landing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn `src/html/index.html` from the multilingual Dufta landing into a single-page Latvian landing for the new foot-care brand Gehwole, with 5-item nav (Par mums / Jaunumi / Produkcija / Risinājumi / Kontakti) and matching blocks, styled after www.gehwol.de.

**Architecture:** Gulp static-site build (`gulp-file-include` HTML partials + SCSS partials via glob import). No JS framework, no test runner — verification is done by running the relevant gulp task(s) and grepping the generated `build/` output.

**Tech Stack:** Gulp 4, `gulp-file-include`, Sass (dart-sass via `gulp-sass`), Swiper 11, ScrollReveal, vanilla JS (Babel/webpack bundled).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-02-gehwole-lv-landing-design.md`
- Single language: Latvian only, no i18n switcher, no `data-key` attributes.
- Nav (exact labels): **Par mums · Jaunumi · Produkcija · Risinājumi · Kontakti**, ids `about news products solutions contacts`.
- Visual tokens (from gehwol.de CSS): accent `#45797c`, text `#000`/`#333`/`#6d6d6e`, bg `#fff`, font `'Open Sans', Arial, Helvetica, sans-serif`, `border-radius: 0` everywhere.
- BEM strict: `block__elem`, `block__elem--mod`, `block--mod`. No nested-hyphen block names (`products-section` → `products`, `blog-slide` → `news__slide`, `card-benefit` → n/a, deleted).
- No external links to `thomas-shop.eu` or any Dufta property. No Google Analytics snippet (was Dufta's GA property).
- Content images: CSS-background placeholder blocks (`background-color: var(--accent-tint); border-left: 4px solid var(--accent);`), never repurposed Dufta photos. Logo: plain text logotype (`.logo__text`), not the Dufta SVG mark.
- All copy is Latvian placeholder text specified verbatim in the spec — use it exactly as written, don't paraphrase.

---

### Task 1: Global tokens, typography, buttons, base cleanup

**Files:**
- Modify: `src/scss/base/_vars.scss` (full rewrite)
- Modify: `src/scss/base/_base.scss:1-4,10-26,97-125` (background color, scroll-margin ids, divider gradient)
- Modify: `src/scss/blocks/_titles.scss` (full rewrite)
- Modify: `src/scss/blocks/_btn.scss` (full rewrite)
- Modify: `src/scss/blocks/_icons.scss` (trim to used icons only)

**Interfaces:**
- Produces: CSS custom properties `--accent (#45797c)`, `--accent-dark`, `--accent-tint`, `--text-color`, `--text-secondary`, `--font-main`, `--link-color`; classes `.title-1`, `.title-2`, `.btn-link`, `.icon`, `.icon--whatsapp`, `.icon--email`. All later tasks consume these.
- Consumes: nothing (foundation task).

- [ ] **Step 1: Rewrite `src/scss/base/_vars.scss`**

```scss
:root {
	--container-width: 1200px;
	--container-padding: 15px;

	--font-main: 'Open Sans', Arial, Helvetica, sans-serif;
	--font-accent: 'Open Sans', Arial, Helvetica, sans-serif;
	--font-titles: var(--font-main);

	--page-bg: #fff;
	--text-color: #000;
	--text-secondary: #6d6d6e;
	--link-color: var(--text-color);

	--accent: #45797c;
	--accent-dark: #345c5e;
	--accent-tint: #eaf1f1;

	--section-space-y: 48px;
	--section-space-y-tablet: 40px;
	--section-space-y-mobile: 28px;

	--laptop-size: 1199px;
	--tablet-size: 959px;
	--mobile-size: 599px;
}
```

- [ ] **Step 2: Edit `src/scss/base/_base.scss`**

Replace line 4 (`background-color: rgb(39, 39, 39);`) with:

```scss
	background-color: #fff;
```

Replace lines 10-13 (`#about,\n#products,\n#blog {\n\tscroll-margin-top: 96px;`) with:

```scss
#about,
#news,
#products,
#solutions,
#contacts {
	scroll-margin-top: 96px;
```

Replace the divider gradient inside `main > section + section::before` (lines ~108-115) with:

```scss
		background: linear-gradient(
			90deg,
			rgba(69, 121, 124, 0) 0%,
			rgba(69, 121, 124, 0.5) 50%,
			rgba(69, 121, 124, 0) 100%
		);
```

- [ ] **Step 3: Run `npx gulp sass:dev`**

Run: `npx gulp sass:dev`
Expected: task completes, no "Error" in console output, `build/css/main.css` is regenerated.

- [ ] **Step 4: Verify tokens compiled**

Run: `grep -c "45797c" build/css/main.css`
Expected: a number greater than `0` (accent color present in compiled CSS).

- [ ] **Step 5: Rewrite `src/scss/blocks/_titles.scss`**

```scss
.title-1 {
	margin: 1em 0 0.5em;
	font-size: clamp(2.5rem, 2.227rem + 1.36vw, 3.25rem);
	font-weight: 700;
	color: #fff;
}

.title-2 {
	position: relative;
	margin: 0 0 40px;
	padding-bottom: 16px;
	font-size: clamp(1.75rem, 1.477rem + 1.36vw, 2.5rem);
	font-weight: 700;
	line-height: 1.2;
	font-family: var(--font-titles);
	text-align: center;
	color: var(--text-color);

	&::after {
		content: "";
		position: absolute;
		left: 50%;
		bottom: 0;
		transform: translateX(-50%);
		width: 56px;
		height: 3px;
		background-color: var(--accent);
	}

	@include tablet {
		margin-bottom: 32px;
	}

	@include mobile {
		margin-bottom: 24px;
	}
}
```

- [ ] **Step 6: Rewrite `src/scss/blocks/_btn.scss`**

```scss
.btn-link,
a.btn-link {
	display: inline-block;
	width: fit-content;
	color: #fff;
	background: var(--accent);
	padding: 14px 32px;
	border-radius: 0;
	font-size: 16px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	text-decoration: none;
	outline: none;
	border: none;
	cursor: pointer;
	transition: background-color 0.2s ease-in;

	&:hover,
	&:focus {
		background: var(--accent-dark);
		color: #fff;
		text-decoration: none;
	}

	@include mobile {
		padding: 12px 20px;
		font-size: 14px;
	}
}
```

- [ ] **Step 7: Trim `src/scss/blocks/_icons.scss`**

Replace the full file content with:

```scss
.icon {
	fill: transparent;
	stroke: transparent;
	width: 22px;
	height: 22px;
}

.icon--phone, .icon--email, .icon--youtube, .icon--instagram, .icon--fb, .icon--globe, .icon--whatsapp {
	width: 27px;
	height: 27px;
	fill: var(--text-color);
	stroke: var(--text-color);
	flex-shrink: 0;
	transition: all 0.2s ease-in;

	@include mobile {
		width: 20px;
		height: 20px;
		margin-right: 0;
	}
}
```

- [ ] **Step 8: Run `npx gulp sass:dev` again**

Run: `npx gulp sass:dev`
Expected: no "Error" in console, `build/css/main.css` regenerated without SCSS syntax errors.

- [ ] **Step 9: Commit**

```bash
git add src/scss/base/_vars.scss src/scss/base/_base.scss src/scss/blocks/_titles.scss src/scss/blocks/_btn.scss src/scss/blocks/_icons.scss
git commit -m "style: switch design tokens to gehwol.de palette (teal, Open Sans, sharp corners)"
```

---

### Task 2: Header & navigation (5-item LV nav, no language switcher)

**Files:**
- Modify: `src/html/blocks/header.html` (full rewrite)
- Modify: `src/html/blocks/mobile-nav.html` (full rewrite)
- Modify: `src/scss/blocks/_header.scss` (full rewrite)
- Modify: `src/scss/blocks/_mobile-nav.scss` (full rewrite)
- Modify: `src/scss/blocks/_logo.scss` (full rewrite)
- Modify: `src/js/modules/header-scroll.js` (full rewrite)
- Modify: `src/js/modules/mobile-nav.js` (full rewrite)
- Delete: `src/scss/blocks/_switcher.scss`

**Interfaces:**
- Consumes: `--accent`, `--text-color`, `--font-accent`, `.title-2` from Task 1.
- Produces: `#header`, `.header--elevated` (toggled by `header-scroll.js`), `.nav__link`, `.mobile-nav`, `.mobile-nav--open`, `.mobile-nav__link`, `.mobile-nav-btn`, `.nav-icon`, `.nav-icon--active`, `.logo__text`. Task 8 (footer) reuses `.logo__text`/`.footer__logo-link`.

- [ ] **Step 1: Rewrite `src/html/blocks/header.html`**

```html
<header class="header" id="header">
	<div class="container">
		<div class="header__row">
			<a href="index.html" class="logo">
				<span class="logo__text">Gehwole</span>
			</a>
			<nav class="header__nav nav">
				<ul class="nav__list">
					<li><a href="index.html#about" class="nav__link">Par mums</a></li>
					<li><a href="index.html#news" class="nav__link">Jaunumi</a></li>
					<li><a href="index.html#products" class="nav__link">Produkcija</a></li>
					<li><a href="index.html#solutions" class="nav__link">Risinājumi</a></li>
					<li><a href="index.html#contacts" class="nav__link">Kontakti</a></li>
				</ul>
			</nav>

			<button class="mobile-nav-btn" id="mobile-nav-btn">
				<span class="nav-icon"></span>
			</button>
		</div>
	</div>
</header>

@@include('mobile-nav.html')
```

- [ ] **Step 2: Rewrite `src/html/blocks/mobile-nav.html`**

```html
<div class="mobile-nav">
	<ul class="mobile-nav__list">
		<li><a href="#about" class="mobile-nav__link">Par mums</a></li>
		<li><a href="#news" class="mobile-nav__link">Jaunumi</a></li>
		<li><a href="#products" class="mobile-nav__link">Produkcija</a></li>
		<li><a href="#solutions" class="mobile-nav__link">Risinājumi</a></li>
		<li><a href="#contacts" class="mobile-nav__link">Kontakti</a></li>
	</ul>
</div>
```

- [ ] **Step 3: Rewrite `src/scss/blocks/_header.scss`**

```scss
.header {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	background-color: var(--page-bg);
	color: var(--text-color);
	padding: 18px 0;
	border-bottom: 1px solid transparent;
	transition: padding 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
	z-index: 1000;

	@include mobile {
		padding: 10px 0;
	}
}

.header.header--elevated {
	padding: 10px 0;
	border-bottom-color: var(--accent);
	box-shadow: 0 8px 22px rgba(0, 0, 0, 0.08);
}

.header__row {
	display: flex;
	justify-content: space-between;
	align-items: center;

	@include mobile {
		gap: 12px;
	}
}

.nav__list {
	display: flex;
	gap: 30px;

	@include tablet-small {
		display: none;
	}
}

.nav__link {
	position: relative;
	font-family: var(--font-accent);
	text-decoration: none;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	font-size: 14px;
	font-weight: 600;
	color: var(--text-color);
}

.nav__link::after {
	content: "";
	position: absolute;
	left: 0;
	bottom: -6px;
	width: 100%;
	height: 2px;
	background-color: var(--accent);
	transform: scaleX(0);
	transform-origin: left;
	transition: transform 0.25s ease;
}

.nav__link:hover {
	color: var(--accent);
}

.nav__link:hover::after {
	transform: scaleX(1);
}

.header__right-side {
	display: flex;
	align-items: center;
	gap: 20px;
}
```

- [ ] **Step 4: Rewrite `src/scss/blocks/_mobile-nav.scss`**

```scss
.mobile-nav {
	position: fixed;
	top: -100%;
	width: 100%;
	height: 100%;
	z-index: 99;

	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding-top: 40px;
	padding-bottom: 40px;
	background: var(--accent);
	transition: top 0.2s ease-in;
}

.mobile-nav--open {
	top: 0;
}

.mobile-nav__link {
	color: #fff;
	text-transform: uppercase;
	letter-spacing: 0.04em;

	&:hover {
		opacity: 0.8;
	}
}

.mobile-nav__list {
	display: flex;
	flex-direction: column;
	align-items: center;
	row-gap: 20px;
	font-size: 24px;
}
```

- [ ] **Step 5: Rewrite `src/scss/blocks/_logo.scss`**

```scss
.logo {
	display: flex;
	align-items: center;
	transition: opacity 0.2s ease;

	&:hover {
		opacity: 0.85;
	}
}

.logo__text {
	font-size: 24px;
	font-weight: 700;
	letter-spacing: 0.02em;
	color: var(--accent);
	text-transform: uppercase;

	@include mobile {
		font-size: 18px;
	}
}

.footer__logo .footer__logo-link {
	display: flex;
	align-items: center;
}
```

- [ ] **Step 6: Rewrite `src/js/modules/header-scroll.js`**

```js
function headerScroll() {
  const header = document.getElementById("header");
  if (!header) return;

  window.addEventListener(
    "scroll",
    function () {
      header.classList.toggle("header--elevated", window.scrollY > 24);
    },
    { passive: true },
  );
}

export default headerScroll;
```

- [ ] **Step 7: Rewrite `src/js/modules/mobile-nav.js`**

```js
function mobileNav() {
  const navBtn = document.querySelector(".mobile-nav-btn");
  const nav = document.querySelector(".mobile-nav");
  const menuIcon = document.querySelector(".nav-icon");
  const navLinks = document.querySelectorAll(".mobile-nav__link");

  const closeMenu = () => {
    nav.classList.remove("mobile-nav--open");
    menuIcon.classList.remove("nav-icon--active");
    document.body.classList.remove("no-scroll");
  };

  navBtn.onclick = function () {
    nav.classList.toggle("mobile-nav--open");
    menuIcon.classList.toggle("nav-icon--active");
    document.body.classList.toggle("no-scroll");
  };

  navLinks.forEach((link) => {
    link.addEventListener("click", closeMenu);
  });

  document.addEventListener("click", (e) => {
    const clickedInsideNav = nav.contains(e.target);
    const clickedOnBtn = navBtn.contains(e.target);

    if (!clickedInsideNav && !clickedOnBtn) {
      closeMenu();
    }
  });
}

export default mobileNav;
```

- [ ] **Step 8: Delete the language switcher stylesheet**

Run: `rm src/scss/blocks/_switcher.scss`

- [ ] **Step 9: Build and verify**

Run: `npx gulp html:dev && npx gulp sass:dev && npx gulp js:dev`
Expected: no "Error" in console output for any of the three tasks.

Run: `grep -o "Risinājumi" build/index.html`
Expected: `Risinājumi` printed (nav link merged into the page via file-include).

Run: `grep -c "switcher-wrapper" build/index.html`
Expected: `0` (language switcher fully removed).

- [ ] **Step 10: Commit**

```bash
git add src/html/blocks/header.html src/html/blocks/mobile-nav.html src/scss/blocks/_header.scss src/scss/blocks/_mobile-nav.scss src/scss/blocks/_logo.scss src/js/modules/header-scroll.js src/js/modules/mobile-nav.js
git rm src/scss/blocks/_switcher.scss
git commit -m "feat: rebuild header/nav with 5-item LV nav, drop language switcher"
```

---

### Task 3: Hero section

**Files:**
- Modify: `src/html/blocks/hero.html` (full rewrite)
- Modify: `src/scss/blocks/_hero.scss` (full rewrite)

**Interfaces:**
- Consumes: `.title-1` from Task 1.
- Produces: `.hero`, `.hero__title`, `.hero__slogan` (consumed by `scrollReveal.js` in Task 9).

- [ ] **Step 1: Rewrite `src/html/blocks/hero.html`**

```html
<section class="hero" id="hero">
	<div class="hero__inner">
		<div class="container hero__container">
			<div class="hero__content">
				<h1 class="hero__title title-1">Profesionāla pēdu kopšana ikdienai</h1>
				<p class="hero__slogan">Efektīvi. Dabīgi. Pārbaudīti.</p>
			</div>
		</div>
	</div>
</section>
```

- [ ] **Step 2: Rewrite `src/scss/blocks/_hero.scss`**

`main.scss` glob-imports `blocks/*.scss` alphabetically, so `_titles.scss` (t) loads after `_hero.scss` (h) — a bare `.hero__title { margin: ... }` would lose the margin to `.title-1`'s rule. Use the two-class descendant selector below so it wins on specificity regardless of load order.

```scss
.hero {
	margin: 0;
}

.hero__inner {
	position: relative;
	margin-top: 100px;
	background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
	min-height: 70vh;
	display: flex;
	align-items: center;
	padding: 60px 20px;
	overflow: hidden;

	@include mobile {
		margin-top: 86px;
		min-height: 50vh;
		padding: 40px 12px;
	}
}

.hero__container {
	width: 100%;
}

.hero__content {
	max-width: 640px;
}

.hero__content .hero__title {
	color: #fff;
	margin: 0 0 20px;
}

.hero__slogan {
	margin: 0;
	font-size: 20px;
	line-height: 1.4;
	color: #fff;
	font-weight: 600;
	letter-spacing: 0.02em;

	@include mobile {
		font-size: 16px;
	}
}
```

- [ ] **Step 3: Build and verify**

Run: `npx gulp html:dev && npx gulp sass:dev`
Expected: no "Error" in console output.

Run: `grep -o "Profesionāla pēdu kopšana ikdienai" build/index.html`
Expected: the hero title text is printed.

- [ ] **Step 4: Commit**

```bash
git add src/html/blocks/hero.html src/scss/blocks/_hero.scss
git commit -m "feat: rebuild hero section for Gehwole brand"
```

---

### Task 4: About section (Par mums)

**Files:**
- Modify: `src/html/blocks/about.html` (full rewrite)
- Modify: `src/scss/blocks/_about.scss` (full rewrite)

**Interfaces:**
- Consumes: `.title-2`, `.btn-link` from Task 1.
- Produces: `.about__image`, `.about__content`, `.about__how-step` (consumed by `scrollReveal.js` in Task 9).

- [ ] **Step 1: Rewrite `src/html/blocks/about.html`**

```html
<section class="about" id="about">
	<div class="container">
		<h2 class="about__title title-2">Kas ir Gehwole</h2>

		<div class="about__row">
			<div class="about__image" aria-hidden="true"></div>

			<div class="about__content">
				<p class="about__text">Gehwole ir pēdu kopšanas līdzekļu klāsts, kas radīts, lai risinātu ikdienas pēdu problēmas — no sausas ādas līdz nogurušām kājām.</p>
				<p class="about__text">Piemērots ikdienas lietošanai mājās un profesionālai pēdu aprūpei.</p>

				<div class="about__how">
					<h3 class="about__how-title">Kā strādā Gehwole</h3>
					<ol class="about__how-steps">
						<li class="about__how-step">
							<span class="about__how-step-name">Mitrina un mīkstina</span>
							<span class="about__how-step-desc">Aktīvās sastāvdaļas iesūcas ādā un atjauno tās dabisko mitruma līdzsvaru.</span>
						</li>
						<li class="about__how-step">
							<span class="about__how-step-name">Atjauno un stiprina</span>
							<span class="about__how-step-desc">Regulāra lietošana stiprina ādas aizsargfunkciju un uzlabo tās stāvokli.</span>
						</li>
						<li class="about__how-step">
							<span class="about__how-step-name">Nodrošina ilgtermiņa komfortu</span>
							<span class="about__how-step-desc">Pēdas paliek veselas, mīkstas un bez diskomforta ikdienā.</span>
						</li>
					</ol>
				</div>

				<a href="#products" class="btn-link about__cta">Skatīt produkciju →</a>
			</div>
		</div>
	</div>
</section>
```

- [ ] **Step 2: Rewrite `src/scss/blocks/_about.scss`**

```scss
.about {
	background-color: var(--page-bg);
}

.about__row {
	display: grid;
	grid-template-columns: minmax(0, 2fr) minmax(0, 3fr);
	gap: 40px;
	align-items: stretch;

	@include tablet {
		grid-template-columns: minmax(0, 45fr) minmax(0, 55fr);
		gap: 24px;
	}

	@include tablet-small {
		grid-template-columns: 1fr;
		gap: 18px;
	}
}

.about__image {
	width: 100%;
	min-height: 360px;
	background-color: var(--accent-tint);
	border-left: 4px solid var(--accent);

	@include tablet {
		min-height: 320px;
	}

	@include tablet-small {
		min-height: 220px;
	}
}

.about__content {
	text-align: left;
	min-width: 0;
}

.about__text {
	line-height: 1.55;
}

.about__how {
	margin-top: 20px;
	padding: 18px 20px 20px;
	background: var(--accent-tint);
	border-left: 4px solid var(--accent);

	@include mobile {
		padding: 10px 10px 12px;
	}
}

.about__how-title {
	font-size: 18px;
	font-weight: 700;
	margin: 0 0 10px;
	color: var(--text-color);
}

.about__how-steps {
	list-style: none;
	padding: 0;
	margin: 0;
	display: flex;
	flex-direction: column;
	gap: 10px;
	counter-reset: how-steps;
}

.about__how-step {
	counter-increment: how-steps;
	display: flex;
	flex-direction: column;
	gap: 2px;
	padding-left: 34px;
	position: relative;

	&::before {
		content: counter(how-steps);
		position: absolute;
		left: 0;
		top: 1px;
		width: 22px;
		height: 22px;
		background: var(--accent);
		color: #fff;
		font-size: 12px;
		font-weight: 700;
		display: flex;
		align-items: center;
		justify-content: center;
	}
}

.about__how-step-name {
	font-weight: 600;
	font-size: 15px;
	line-height: 1.4;
	color: var(--text-color);
}

.about__how-step-desc {
	font-size: 14px;
	color: var(--text-secondary);
	line-height: 1.45;
}

.about__cta {
	margin-top: 24px;

	@include mobile {
		display: block;
		width: fit-content;
		margin-left: auto;
		margin-right: auto;
	}
}
```

- [ ] **Step 3: Build and verify**

Run: `npx gulp html:dev && npx gulp sass:dev`
Expected: no "Error" in console output.

Run: `grep -o "Kā strādā Gehwole" build/index.html`
Expected: the about-how title text is printed.

- [ ] **Step 4: Commit**

```bash
git add src/html/blocks/about.html src/scss/blocks/_about.scss
git commit -m "feat: rebuild about section (Par mums) for Gehwole brand"
```

---

### Task 5: News section (Jaunumi) — replaces blog

**Files:**
- Create: `src/html/blocks/news.html`
- Create: `src/scss/blocks/_news.scss`
- Modify: `src/js/modules/swipers.js:10,32,48` (blog → news)
- Modify: `src/html/index.html:36` (swap include line)
- Delete: `src/html/blocks/blog.html`
- Delete: `src/scss/blocks/_blog.scss`

**Interfaces:**
- Consumes: `.title-2` from Task 1.
- Produces: `.swiper-news` selector (consumed by `swipers.js`), `.news__slide` (consumed by `scrollReveal.js` in Task 9).

- [ ] **Step 1: Create `src/html/blocks/news.html`**

```html
<section class="news" id="news">
	<div class="container news__container">
		<h2 class="news__title title-2">Jaunumi</h2>

		<div class="news__slider swiper swiper-news">
			<div class="news__wrapper swiper-wrapper">
				<div class="news__slide swiper-slide">
					<div class="news__image" aria-hidden="true"></div>
					<h3 class="news__slide-title">Kā izvairīties no sausas ādas uz papēžiem ziemā</h3>
				</div>
				<div class="news__slide swiper-slide">
					<div class="news__image" aria-hidden="true"></div>
					<h3 class="news__slide-title">5 ieradumi veselīgām pēdām ikdienā</h3>
				</div>
				<div class="news__slide swiper-slide">
					<div class="news__image" aria-hidden="true"></div>
					<h3 class="news__slide-title">Kāpēc regulāra pēdu kopšana ir svarīga pēc 40 gadu vecuma</h3>
				</div>
			</div>
		</div>
	</div>
</section>
```

- [ ] **Step 2: Create `src/scss/blocks/_news.scss`**

```scss
.news__container {
	@include mobile {
		padding: 0 var(--container-padding);
	}
}

.news__image {
	width: 100%;
	height: 200px;
	background-color: var(--accent-tint);
	border-left: 4px solid var(--accent);
}

.news__slide-title {
	margin: 15px 0 0;
	font-size: 18px;
	font-weight: 600;
	line-height: 1.4;
	color: var(--text-color);
}
```

- [ ] **Step 3: Edit `src/js/modules/swipers.js`**

Replace:

```js
  const isBlog = selector === ".swiper-blog";
  return new Swiper(container, {
    modules: isBlog ? [Navigation, Autoplay] : [Navigation],
```

with:

```js
  const isNews = selector === ".swiper-news";
  return new Swiper(container, {
    modules: isNews ? [Navigation, Autoplay] : [Navigation],
```

Replace:

```js
    ...(!isBlog
      ? {
          navigation: {
            nextEl: container.querySelector(".swiper__btn--next"),
            prevEl: container.querySelector(".swiper__btn--prev"),
          },
        }
      : {}),

    ...(isBlog
```

with:

```js
    ...(!isNews
      ? {
          navigation: {
            nextEl: container.querySelector(".swiper__btn--next"),
            prevEl: container.querySelector(".swiper__btn--prev"),
          },
        }
      : {}),

    ...(isNews
```

Replace:

```js
export default function initAllSwipers() {
  initSwiper(".swiper-products");
  initSwiper(".swiper-blog");
}
```

with:

```js
export default function initAllSwipers() {
  initSwiper(".swiper-products");
  initSwiper(".swiper-news");
}
```

- [ ] **Step 4: Swap the include line in `src/html/index.html`**

Replace:

```html
		@@include('blocks/blog.html')
```

with:

```html
		@@include('blocks/news.html')
```

- [ ] **Step 5: Delete the old blog files**

Run: `rm src/html/blocks/blog.html src/scss/blocks/_blog.scss`

- [ ] **Step 6: Build and verify**

Run: `npx gulp html:dev && npx gulp sass:dev && npx gulp js:dev`
Expected: no "Error" in console output (in particular, no "file not found" from gulp-file-include, which would confirm the blog.html deletion didn't break the include).

Run: `grep -o "Kāpēc regulāra pēdu kopšana ir svarīga pēc 40 gadu vecuma" build/index.html`
Expected: the third news headline is printed.

Run: `grep -c "swiper-news" build/js/index.bundle.js`
Expected: a number greater than `0`.

- [ ] **Step 7: Commit**

```bash
git add src/html/blocks/news.html src/scss/blocks/_news.scss src/js/modules/swipers.js src/html/index.html
git rm src/html/blocks/blog.html src/scss/blocks/_blog.scss
git commit -m "feat: replace blog section with news (Jaunumi)"
```

---

### Task 6: Products section (Produkcija)

**Files:**
- Modify: `src/html/blocks/products.html` (full rewrite)
- Modify: `src/scss/blocks/_products.scss` (full rewrite)
- Modify: `src/js/modules/swipers.js` (button selectors)

**Interfaces:**
- Consumes: `.title-2`, `.btn-link`, `.swiper-products` wiring from Task 1/5.
- Produces: `.products__slide` (consumed by `scrollReveal.js` in Task 9), `.products__btn--prev`/`.products__btn--next` (consumed by `swipers.js`).

- [ ] **Step 1: Rewrite `src/html/blocks/products.html`**

```html
<section class="products text-center" id="products">
	<div class="container products__container">
		<h2 class="products__title title-2">Produkcija</h2>

		<div class="products__slider swiper swiper-products">
			<button class="products__btn products__btn--prev" aria-label="Iepriekšējais">&lt;</button>
			<button class="products__btn products__btn--next" aria-label="Nākamais">&gt;</button>

			<div class="products__wrapper swiper-wrapper">
				<div class="products__slide swiper-slide">
					<div class="products__image" aria-hidden="true"></div>
					<div class="products__slide-title">Gehwole Intensīvais pēdu krēms 75 ml</div>
				</div>
				<div class="products__slide swiper-slide">
					<div class="products__image" aria-hidden="true"></div>
					<div class="products__slide-title">Gehwole Mitrinošais pēdu krēms 125 ml</div>
				</div>
				<div class="products__slide swiper-slide">
					<div class="products__image" aria-hidden="true"></div>
					<div class="products__slide-title">Gehwole Atsvaidzinošā pēdu vanna 400 g</div>
				</div>
				<div class="products__slide swiper-slide">
					<div class="products__image" aria-hidden="true"></div>
					<div class="products__slide-title">Gehwole Nagu kopšanas eļļa 15 ml</div>
				</div>
				<div class="products__slide swiper-slide">
					<div class="products__image" aria-hidden="true"></div>
					<div class="products__slide-title">Gehwole Pemza cietās ādas noņemšanai</div>
				</div>
				<div class="products__slide swiper-slide">
					<div class="products__image" aria-hidden="true"></div>
					<div class="products__slide-title">Gehwole Pēdu kopšanas komplekts (krēms + vanna)</div>
				</div>
			</div>
		</div>

		<a href="#contacts" class="btn-link products__cta">Sazinieties, lai pasūtītu →</a>
	</div>
</section>
```

- [ ] **Step 2: Rewrite `src/scss/blocks/_products.scss`**

```scss
.products__container {
	@include mobile {
		padding: 0 var(--container-padding);
	}
}

.products__slider {
	position: relative;
}

.products__image {
	width: 100%;
	height: 240px;
	background-color: var(--accent-tint);
	border-left: 4px solid var(--accent);
	transition: background-color 0.2s ease;
}

.products__slide:hover .products__image {
	background-color: #fff;
	box-shadow: inset 0 0 0 1px var(--accent);
}

.products__slide-title {
	margin: 15px 0 0;
	font-size: 18px;
}

.products__btn {
	position: absolute;
	top: 50%;
	transform: translateY(-50%);
	z-index: 10;
	width: 48px;
	height: 48px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 28px;
	line-height: 1;
	cursor: pointer;
	color: var(--text-color);
	background: rgba(255, 255, 255, 0.85);
	border: 1px solid var(--accent);
	user-select: none;
	transition: background-color 0.2s ease-in, color 0.2s ease-in;

	&:hover {
		background: var(--accent);
		color: #fff;
	}
}

.products__btn--prev {
	left: 20px;

	@include mobile {
		left: 0;
	}
}

.products__btn--next {
	right: 20px;

	@include mobile {
		right: 0;
	}
}

.products__cta {
	margin-top: 30px;
}
```

- [ ] **Step 3: Edit `src/js/modules/swipers.js`**

Replace:

```js
            nextEl: container.querySelector(".swiper__btn--next"),
            prevEl: container.querySelector(".swiper__btn--prev"),
```

with:

```js
            nextEl: container.querySelector(".products__btn--next"),
            prevEl: container.querySelector(".products__btn--prev"),
```

- [ ] **Step 4: Build and verify**

Run: `npx gulp html:dev && npx gulp sass:dev && npx gulp js:dev`
Expected: no "Error" in console output.

Run: `grep -o "Gehwole Pēdu kopšanas komplekts (krēms + vanna)" build/index.html`
Expected: the sixth product title is printed.

Run: `grep -c "products__btn--next" build/js/index.bundle.js`
Expected: a number greater than `0`.

- [ ] **Step 5: Commit**

```bash
git add src/html/blocks/products.html src/scss/blocks/_products.scss src/js/modules/swipers.js
git commit -m "feat: rebuild products section (Produkcija) with Gehwole line-up"
```

---

### Task 7: Solutions section (Risinājumi) — replaces applications + how-to-use

**Files:**
- Create: `src/html/blocks/solutions.html`
- Create: `src/scss/blocks/_solutions.scss`
- Modify: `src/html/index.html` (swap three include lines for one)
- Delete: `src/html/blocks/applications.html`
- Delete: `src/html/blocks/howToUse.html`
- Delete: `src/html/blocks/uses-cases.html`
- Delete: `src/scss/blocks/_applications.scss`
- Delete: `src/scss/blocks/_howToUse.scss`
- Delete: `src/scss/blocks/_uses-cases.scss`

**Interfaces:**
- Consumes: `.title-2` from Task 1.
- Produces: `.solutions__item` (consumed by `scrollReveal.js` in Task 9).

- [ ] **Step 1: Create `src/html/blocks/solutions.html`**

```html
<section class="solutions" id="solutions">
	<div class="container">
		<h2 class="solutions__title title-2">Risinājumi</h2>

		<div class="solutions__list">
			<div class="solutions__item">
				<span class="solutions__number">1</span>
				<div class="solutions__text">
					<h3 class="solutions__problem">Sausa, plaisājoša āda uz papēžiem</h3>
					<p class="solutions__answer">Intensīvais krēms ar dziļi mitrinošu formulu mīkstina raga ādu.</p>
				</div>
			</div>
			<div class="solutions__item">
				<span class="solutions__number">2</span>
				<div class="solutions__text">
					<h3 class="solutions__problem">Nogurušas, pietūkušas kājas dienas beigās</h3>
					<p class="solutions__answer">Atsvaidzinošā vanna uzlabo asinsriti un mazina smaguma sajūtu.</p>
				</div>
			</div>
			<div class="solutions__item">
				<span class="solutions__number">3</span>
				<div class="solutions__text">
					<h3 class="solutions__problem">Cietā āda un nospiedumi</h3>
					<p class="solutions__answer">Regulāra pemzas lietošana kopā ar krēmu noņem cieto ādu bez traumēšanas.</p>
				</div>
			</div>
			<div class="solutions__item">
				<span class="solutions__number">4</span>
				<div class="solutions__text">
					<h3 class="solutions__problem">Trausli nagi un ādas kairinājums ap tiem</h3>
					<p class="solutions__answer">Kopšanas eļļa stiprina nagus un mīkstina apkārtējo ādu.</p>
				</div>
			</div>
		</div>
	</div>
</section>
```

- [ ] **Step 2: Create `src/scss/blocks/_solutions.scss`**

```scss
.solutions__list {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 30px;

	@include tablet-small {
		grid-template-columns: 1fr;
		gap: 20px;
	}
}

.solutions__item {
	display: flex;
	gap: 16px;
	padding: 20px;
	background: var(--accent-tint);
	border-left: 4px solid var(--accent);
}

.solutions__number {
	flex-shrink: 0;
	width: 32px;
	height: 32px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: var(--accent);
	color: #fff;
	font-weight: 700;
}

.solutions__problem {
	margin: 0 0 6px;
	font-size: 17px;
	font-weight: 700;
	color: var(--text-color);
}

.solutions__answer {
	margin: 0;
	font-size: 15px;
	line-height: 1.5;
	color: var(--text-secondary);
}
```

- [ ] **Step 3: Edit `src/html/index.html`**

Replace:

```html
		@@include('blocks/applications.html')
		@@include('blocks/benefits.html')
		@@include('blocks/uses-cases.html')
		@@include('blocks/products.html')
		@@include('blocks/howToUse.html')
```

with:

```html
		@@include('blocks/benefits.html')
		@@include('blocks/products.html')
		@@include('blocks/solutions.html')
```

(`benefits.html` stays included for now — it's removed in Task 9 — this step only removes the three files being deleted in *this* task.)

- [ ] **Step 4: Delete the old files**

Run: `rm src/html/blocks/applications.html src/html/blocks/howToUse.html src/html/blocks/uses-cases.html src/scss/blocks/_applications.scss src/scss/blocks/_howToUse.scss src/scss/blocks/_uses-cases.scss`

- [ ] **Step 5: Build and verify**

Run: `npx gulp html:dev && npx gulp sass:dev`
Expected: no "Error" in console output (confirms no dangling `@@include` for the three deleted files).

Run: `grep -o "Trausli nagi un ādas kairinājums ap tiem" build/index.html`
Expected: the fourth solutions problem title is printed.

- [ ] **Step 6: Commit**

```bash
git add src/html/blocks/solutions.html src/scss/blocks/_solutions.scss src/html/index.html
git rm src/html/blocks/applications.html src/html/blocks/howToUse.html src/html/blocks/uses-cases.html src/scss/blocks/_applications.scss src/scss/blocks/_howToUse.scss src/scss/blocks/_uses-cases.scss
git commit -m "feat: replace applications/how-to-use/uses-cases with solutions (Risinājumi)"
```

---

### Task 8: Footer (Kontakti)

**Files:**
- Modify: `src/html/blocks/footer.html` (full rewrite)
- Modify: `src/scss/blocks/_footer.scss:1-7,184-188` (background/border, link-text selectors)
- Modify: `src/scss/base/_sticky-footer.scss` (full rewrite)

**Interfaces:**
- Consumes: `.logo__text`, `.footer__logo-link` from Task 2; `.icon--whatsapp`/`.icon--email` from Task 1.
- Produces: none consumed by later tasks (footer is the last visual block).

- [ ] **Step 1: Rewrite `src/html/blocks/footer.html`**

```html
<footer class="footer" id="contacts" aria-label="Informācija par uzņēmumu">
	<div class="footer__container">
		<div class="footer__row">

			<div class="footer__info">
				<h3 class="footer__title">Baltic Pro Company, SIA</h3>
				<span class="footer__text">Reģ. nr.: 40203544254</span>
				<span class="footer__text">Jūrkalnes iela 12, LV-1046 Rīga, Latvija</span>
			</div>

			<div class="footer__contacts">
				<h3 class="footer__title">Sazinieties ar mums</h3>
				<a class="footer__link" href="https://wa.me/37129109868" target="_blank" rel="noopener">
					<svg class="icon icon--whatsapp">
						<use href="./img/svgsprite/sprite.symbol.svg#whatsapp"></use>
					</svg>
					<p class="footer__link-text">+37129109868 (čats)</p>
				</a>

				<a class="footer__link" href="mailto:info@gehwole.eu" target="_blank" rel="noopener">
					<svg class="icon icon--email">
						<use href="./img/svgsprite/sprite.symbol.svg#email"></use>
					</svg>
					<p class="footer__link-text">info@gehwole.eu</p>
				</a>
			</div>

			<div class="footer__meta">
				<div class="footer__logo">
					<a href="index.html" class="footer__logo-link" aria-label="Gehwole">
						<span class="logo__text">Gehwole</span>
					</a>
				</div>
				<p class="footer__copyright">Copyright © 2026. Visas tiesības aizsargātas</p>
			</div>

		</div>
	</div>
</footer>
```

- [ ] **Step 2: Edit `src/scss/blocks/_footer.scss`**

Replace lines 1-7:

```scss
.footer {
  position: relative;
  overflow: hidden;
  border-top: 1px solid rgba(0, 0, 0, .08);
  box-shadow: 0 -4px 12px rgba(0, 0, 0, .04);
  background: var(--footer-bg);
}
```

with:

```scss
.footer {
  position: relative;
  overflow: hidden;
  border-top: 3px solid var(--accent);
  background: var(--accent-tint);
}
```

Replace lines 184-187:

```scss
p.footer-email,
p.footer-phone {
  margin: 0;
}
```

with:

```scss
.footer__link-text {
  margin: 0;
}
```

- [ ] **Step 3: Rewrite `src/scss/base/_sticky-footer.scss`**

```scss
html, body {
	min-height: 100vh;
	display: flex;
	flex-direction: column;
}

.footer {
	margin-top: auto;
}
```

- [ ] **Step 4: Build and verify**

Run: `npx gulp svgSymbol:dev && npx gulp html:dev && npx gulp sass:dev`
Expected: no "Error" in console output. `npx gulp svgSymbol:dev` regenerates `build/img/svgsprite/sprite.symbol.svg` so the whatsapp/email icons referenced by `<use>` resolve.

Run: `grep -o "info@gehwole.eu" build/index.html`
Expected: the placeholder contact email is printed.

Run: `grep -c "footer-email\|footer-phone" build/index.html`
Expected: `0` (old tag-qualified selectors fully replaced by `.footer__link-text`).

- [ ] **Step 5: Commit**

```bash
git add src/html/blocks/footer.html src/scss/blocks/_footer.scss src/scss/base/_sticky-footer.scss
git commit -m "feat: rebuild footer (Kontakti) for Gehwole brand"
```

---

### Task 9: Assemble index.html, remove remaining obsolete sections, final verification

**Files:**
- Modify: `src/html/index.html` (full rewrite)
- Modify: `src/js/index.js` (full rewrite)
- Modify: `src/js/modules/scrollReveal.js` (full rewrite)
- Delete: `src/html/blocks/agreement-bar.html`
- Delete: `src/html/blocks/benefits.html`
- Delete: `src/html/blocks/consist.html`
- Delete: `src/html/blocks/delivery-payment.html`
- Delete: `src/scss/blocks/_agreement-bar.scss`
- Delete: `src/scss/blocks/_benefits.scss`
- Delete: `src/scss/blocks/_consist.scss`
- Delete: `src/scss/blocks/_delivery-payment.scss`
- Delete: `src/html/data/translations.js`
- Delete: `src/js/modules/setLanguage.js`
- Delete: `src/js/modules/agreement-cookies.js`

**Interfaces:**
- Consumes: every block produced in Tasks 2-8 (`header.html`, `hero.html`, `about.html`, `news.html`, `products.html`, `solutions.html`, `footer.html`).
- Produces: the final assembled page — nothing downstream depends on this task.

- [ ] **Step 1: Rewrite `src/html/index.html`**

```html
<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Gehwole — pēdu kopšanas līdzekļi ikdienas komfortam un veselīgām kājām." />
	<title>Gehwole — profesionāla pēdu kopšana</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/hero.html')
		@@include('blocks/about.html')
		@@include('blocks/news.html')
		@@include('blocks/products.html')
		@@include('blocks/solutions.html')
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
```

- [ ] **Step 2: Rewrite `src/js/index.js`**

```js
import initAllSwipers from "./modules/swipers.js";
initAllSwipers();

import headerScroll from "./modules/header-scroll.js";
headerScroll();

import scrollReveal from "./modules/scrollReveal.js";
scrollReveal();

import mobileNav from "./modules/mobile-nav.js";
mobileNav();
```

- [ ] **Step 3: Rewrite `src/js/modules/scrollReveal.js`**

```js
import ScrollReveal from "scrollreveal";

ScrollReveal({
  distance: "24px",
  duration: 700,
  easing: "cubic-bezier(0.22, 1, 0.36, 1)",
  viewFactor: 0.15,
});

function scrollRevealFunc() {
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    return;
  }

  const headerAnimatedElements = Array.from(
    document.querySelectorAll(".header .logo, .header__nav, .mobile-nav-btn"),
  );

  headerAnimatedElements.forEach((element, index) => {
    element.animate(
      [
        { opacity: 0, transform: "translateY(-14px)" },
        { opacity: 1, transform: "translateY(0)" },
      ],
      {
        delay: 80 + index * 70,
        duration: 600,
        easing: "cubic-bezier(0.22, 1, 0.36, 1)",
        fill: "both",
      },
    );
  });

  ScrollReveal().reveal(`.hero__title, .hero__slogan`, {
    delay: 220,
    distance: "0px",
    duration: 650,
    scale: 0.98,
    opacity: 0,
  });

  ScrollReveal().reveal(`.title-2`, {
    delay: 100,
    origin: "top",
    distance: "18px",
  });

  ScrollReveal().reveal(`.about__image`, {
    delay: 120,
    origin: "left",
  });

  ScrollReveal().reveal(`.about__content`, {
    delay: 150,
    origin: "bottom",
    distance: "18px",
  });

  ScrollReveal().reveal(
    `.news__slide, .products__slide, .solutions__item, .btn-link`,
    {
      delay: 120,
      interval: 80,
      origin: "bottom",
      distance: "20px",
    },
  );

  ScrollReveal().reveal(`.about__how-step`, {
    delay: 130,
    interval: 80,
    origin: "bottom",
    distance: "16px",
  });

  ScrollReveal().reveal(`.footer__info, .footer__contacts, .footer__meta`, {
    delay: 140,
    interval: 100,
    origin: "bottom",
    distance: "18px",
  });
}

export default scrollRevealFunc;
```

- [ ] **Step 4: Delete the remaining obsolete files**

Run: `rm src/html/blocks/agreement-bar.html src/html/blocks/benefits.html src/html/blocks/consist.html src/html/blocks/delivery-payment.html src/scss/blocks/_agreement-bar.scss src/scss/blocks/_benefits.scss src/scss/blocks/_consist.scss src/scss/blocks/_delivery-payment.scss src/html/data/translations.js src/js/modules/setLanguage.js src/js/modules/agreement-cookies.js`

- [ ] **Step 5: Full build**

Run: `npx gulp html:dev && npx gulp sass:dev && npx gulp js:dev`
Expected: no "Error" in console output for any of the three tasks — this is the strongest signal that every `@@include` resolves and every SCSS/JS file is syntactically valid now that all obsolete files are gone.

- [ ] **Step 6: Verify no Dufta/legacy content remains**

Run: `grep -ic "dufta\|thomas-shop\|translations\|switcher-wrapper\|googletagmanager" build/index.html build/js/index.bundle.js`
Expected: `0` for every match (case-insensitive) — confirms no leftover Dufta branding, external shop links, i18n system, or the old GA snippet.

- [ ] **Step 7: Verify the 5-item nav and all 5 section ids are present**

Run: `grep -oE 'id="(about|news|products|solutions|contacts)"' build/index.html | sort -u`
Expected: exactly 5 lines — `id="about"`, `id="contacts"`, `id="news"`, `id="products"`, `id="solutions"` (alphabetical from `sort -u`).

- [ ] **Step 8: Manual visual check**

Run: `npx gulp` (starts the dev server with livereload and opens the browser)
Expected: page loads at the default dev server address, shows the teal Gehwole hero, 5-item nav scrolls to each section, mobile nav toggle works below 990px viewport width. Stop the server (Ctrl+C) once confirmed.

- [ ] **Step 9: Commit**

```bash
git add src/html/index.html src/js/index.js src/js/modules/scrollReveal.js
git rm src/html/blocks/agreement-bar.html src/html/blocks/benefits.html src/html/blocks/consist.html src/html/blocks/delivery-payment.html src/scss/blocks/_agreement-bar.scss src/scss/blocks/_benefits.scss src/scss/blocks/_consist.scss src/scss/blocks/_delivery-payment.scss src/html/data/translations.js src/js/modules/setLanguage.js src/js/modules/agreement-cookies.js
git commit -m "feat: assemble final Gehwole LV landing, drop remaining Dufta sections and i18n"
```
