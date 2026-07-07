# Produkcija Tree Section Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Заменить swiper-слайдер в секции `#products` статичной сеткой из 3 карточек с полным деревом продукции; слайдер продуктов удалить из HTML/SCSS/JS.

**Architecture:** Одностраничный gulp-лендинг (gulp-file-include, SCSS-глоб `./blocks/*.scss`, webpack-бандл). Секция — чистый HTML/CSS grid, без JS. JS-модуль `swipers.js` упрощается до единственного news-свайпера; reveal-селектор в `scrollReveal.js` обновляется.

**Tech Stack:** gulp 4, SCSS, vanilla JS (ES modules), Swiper 11 (остаётся только для news).

**Спека:** `docs/superpowers/specs/2026-07-07-products-section-design.md`

## Global Constraints

- Тексты — латышские, копировать точно, включая `GEHWOL MED®` (®) и диакритику (`Kosmētika`, `Spiedienu uz pēdām mazinoši līdzekļi`, `Rotējošie instrumenti`, `Vienreizlietojamās smilšpapīra frēzes` и т.д.).
- Все ссылки дерева — заглушки `href="#"` (глобальный `placeholder-links.js` уже гасит переходы — новых JS-обработчиков не нужно).
- HTML/SCSS — отступы табами, JS — 2 пробела.
- Цвета/шрифты — только существующие переменные (`--accent`, `--accent-dark`, `--accent-tint`, `--page-bg`, `--font-accent`, `--text-color`).
- Тестов нет: проверка = gulp-таски + grep сборки + ручной браузер.
- Поведение news-свайпера не меняется (autoplay delay 2500, pauseOnMouseEnter, speed 700, loop false, брейкпоинты 0/760/1024 → 1/2/3 слайда, spaceBetween 20).

---

### Task 1: Секция — HTML + SCSS

**Files:**
- Modify: `src/html/blocks/products.html` (весь файл)
- Modify: `src/scss/blocks/_products.scss` (весь файл)

**Interfaces:**
- Produces: классы `products__grid`, `products__card`, `products__card-title`, `products__list`, `products__link`, `products__subtitle`, `products__sublist`. Task 2 ссылается на `.products__card` в scrollReveal; Task 3 проверяет `products__link` в сборке.
- Удаляет классы `products__slider`, `products__image`, `products__slide`, `products__slide-title`, `products__btn*`, `swiper-products` — Task 2 удаляет их JS-привязки.

- [ ] **Step 1: Переписать `src/html/blocks/products.html`**

Полное новое содержимое:

```html
<section class="products text-center" id="products">
	<div class="container products__container">
		<h2 class="products__title title-2">Produkcija</h2>

		<div class="products__grid">
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
			<div class="products__card">
				<a href="#" class="products__card-title">Spiedienu uz pēdām mazinoši līdzekļi</a>
				<ul class="products__list">
					<li><a href="#" class="products__link">Polimēra gēla izstrādājumi, pārvilkti ar tekstilu</a></li>
					<li><a href="#" class="products__link">Polimēra gēla izstrādājumi</a></li>
					<li><a href="#" class="products__link">Plāksteri</a></li>
					<li><a href="#" class="products__link">Filca izstrādājumi</a></li>
				</ul>
			</div>
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
		</div>

		<a href="#contacts" class="btn-link products__cta">Sazinieties, lai pasūtītu →</a>
	</div>
</section>
```

- [ ] **Step 2: Переписать `src/scss/blocks/_products.scss`**

Полное новое содержимое:

```scss
.products__container {
	@include mobile {
		padding: 0 var(--container-padding);
	}
}

.products__grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 20px;
	text-align: left;

	@include tablet-small {
		grid-template-columns: 1fr;
	}
}

.products__card {
	padding: 24px 24px 28px;
	background-color: var(--accent-tint);
	border-left: 4px solid var(--accent);
	transition: box-shadow 0.2s ease;

	&:hover {
		box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
	}
}

.products__card-title {
	display: inline-block;
	margin-bottom: 16px;
	font-family: var(--font-accent);
	font-size: 16px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: var(--accent-dark);

	&:hover {
		color: var(--accent);
	}
}

.products__list {
	display: grid;
	gap: 10px;
}

.products__link {
	font-size: 16px;
	line-height: 1.35;
	color: var(--text-color);

	&:hover {
		color: var(--accent);
	}
}

.products__subtitle {
	display: inline-block;
	margin-top: 6px;
	font-family: var(--font-accent);
	font-size: 14px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: var(--accent-dark);

	&:hover {
		color: var(--accent);
	}
}

.products__sublist {
	display: grid;
	gap: 10px;
	margin-top: 10px;
	padding-left: 14px;
	border-left: 2px solid var(--accent);
}

.products__cta {
	margin-top: 30px;
}
```

- [ ] **Step 3: Собрать**

Run: `npx gulp html:dev`, затем `npx gulp sass:dev`
Expected: обе таски `Finished` без ошибок.

- [ ] **Step 4: Проверить вывод**

Run (Git Bash):

```bash
grep -c "products__link" build/index.html        # ожидается 17
grep -c "products__card" build/index.html        # ожидается 3 card + 3 card-title = 6
grep -c "swiper-products" build/index.html; echo "exit=$?"   # 0 совпадений, exit=1
```

- [ ] **Step 5: Commit**

```bash
git add src/html/blocks/products.html src/scss/blocks/_products.scss
git commit -m "feat: replace products slider with category tree cards"
```

---

### Task 2: JS — убрать products-свайпер, обновить reveal

**Files:**
- Modify: `src/js/modules/swipers.js` (весь файл)
- Modify: `src/js/modules/scrollReveal.js` (один reveal-вызов)

**Interfaces:**
- Consumes: класс `.products__card` из Task 1 (уже в HTML).
- Produces: `initAllSwipers()` (default export, сигнатура без изменений — `src/js/index.js` менять не нужно).

- [ ] **Step 1: Переписать `src/js/modules/swipers.js`**

Полное новое содержимое (поведение news-свайпера идентично текущему):

```js
import Swiper from "swiper";
import { Autoplay } from "swiper/modules";
import "swiper/css";

function initNewsSwiper() {
  const container = document.querySelector(".swiper-news");
  if (!container) return;

  return new Swiper(container, {
    modules: [Autoplay],
    loop: false,
    slidesPerView: 3,
    spaceBetween: 20,

    breakpoints: {
      0: { slidesPerView: 1 },
      760: { slidesPerView: 2 },
      1024: { slidesPerView: 3 },
    },

    autoplay: {
      delay: 2500,
      disableOnInteraction: false,
      reverseDirection: false,
      pauseOnMouseEnter: true,
    },
    speed: 700,
  });
}

export default function initAllSwipers() {
  initNewsSwiper();
}
```

Примечание: импорты `Navigation` и `"swiper/css/navigation"` удаляются — навигационные кнопки были только у продуктового слайдера. `isNews`-ветвление больше не нужно.

- [ ] **Step 2: Обновить `src/js/modules/scrollReveal.js`**

Найти reveal-вызов, содержащий `.news__slide` (сейчас он однострочный с рассогласованной индентацией после прошлой правки). Заменить весь statement на аккуратно отформатированный:

```js
  ScrollReveal().reveal(`.news__slide, .products__card, .btn-link`, {
    delay: 120,
    interval: 80,
    origin: "bottom",
    distance: "20px",
  });
```

(`.products__slide` → `.products__card`; индентация — 2 пробела, как в остальном файле.)

- [ ] **Step 3: Собрать**

Run: `npx gulp js:dev`
Expected: `Finished 'js:dev'`, webpack без ошибок.

- [ ] **Step 4: Проверить вывод**

Run (Git Bash):

```bash
grep -c "swiper-news" build/js/index.bundle.js               # > 0
grep -c "swiper-products" build/js/index.bundle.js; echo "exit=$?"  # 0, exit=1
grep -c "products__card" build/js/index.bundle.js            # > 0 (reveal-селектор)
grep -c "products__slide" build/js/index.bundle.js; echo "exit=$?"  # 0, exit=1
```

- [ ] **Step 5: Commit**

```bash
git add src/js/modules/swipers.js src/js/modules/scrollReveal.js
git commit -m "feat: drop products swiper, reveal category cards"
```

---

### Task 3: Чистая сборка + финальная проверка

**Files:** нет правок — только верификация.

**Interfaces:**
- Consumes: результаты Task 1–2.

- [ ] **Step 1: Чистая сборка**

Run последовательно:

```bash
npx gulp clean:dev
npx gulp html:dev
npx gulp sass:dev
npx gulp js:dev
```

Expected: все `Finished` без ошибок.

- [ ] **Step 2: Grep сборки**

```bash
grep -ri "swiper-products\|products__slide\|products__btn" build/index.html build/css/main.css build/js/index.bundle.js; echo "exit=$?"
```

Expected: пусто, `exit=1`.

```bash
grep -c "products__link" build/index.html   # 17
```

- [ ] **Step 3: Ручная проверка в браузере**

`npx gulp` (livereload). Проверить: 3 карточки со всем деревом, подгруппа Rotējošie instrumenti внутри Tehnika; ≤990px — карточки в столбик; news-слайдер листается и автопрокручивается; CTA ведёт к #contacts; клики по заглушкам не скроллят. Остановить сервер.
