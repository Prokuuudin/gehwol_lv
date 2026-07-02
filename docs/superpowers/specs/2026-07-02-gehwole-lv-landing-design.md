# Gehwole LV — одностраничный лендинг (дизайн)

## Контекст

Текущая gulp-сборка (`src/html`) — многоязычный (RU/LV/LT/EE/EN) лендинг бренда
**Dufta** (удалитель запахов животных), 10+ секций, переключатель языков,
все ссылки товаров/блога ведут на внешний магазин `thomas-shop.eu`.

Новая задача: превратить `src/html/index.html` в **одностраничный
латышскоязычный лендинг** нового бренда **Gehwole** (уход за ногами, в духе
www.gehwol.de), с навигацией из 5 пунктов и ровно 5 соответствующими блоками
контента. Многоязычность, внешний магазин, блог, доставка/оплата, состав,
benefits, use-cases убираются — они не входят в новую навигацию.

Реальных материалов по бренду (кроме названия) нет — весь копирайт LV и список
товаров — плейсхолдер, помечен как таковой в спеке для лёгкой замены позже.
Фото тоже плейсхолдер: используем CSS-заглушки (цвет/иконка), а не подписываем
чужие фото (кошки/отели/матрасы) как товары для ног.

## Навигация и секции

| # | Nav (LV) | id секции | Назначение |
|---|----------|-----------|------------|
| — | (без пункта) | `hero` | Заголовок бренда + слоган |
| 1 | Par mums | `about` | О бренде: что такое Gehwole, как это работает (3 шага) |
| 2 | Jaunumi | `news` | Новости/статьи — 3 карточки, слайдер |
| 3 | Produkcija | `products` | Товары — 6 позиций, слайдер |
| 4 | Risinājumi | `solutions` | Проблема → решение, 4 пары |
| 5 | Kontakti | `footer`/`contacts` | Реквизиты компании, контакты |

"Применение" переведено как **Risinājumi** (решения), т.к. по факту это блок
"проблема с ногами → как Gehwole её решает", а не список мест применения.

## Контент (LV, плейсхолдер)

**Hero**
- h1: "Profesionāla pēdu kopšana ikdienai"
- slogan: "Efektīvi. Dabīgi. Pārbaudīti."

**Par mums**
- title: "Kas ir Gehwole"
- text1: "Gehwole ir pēdu kopšanas līdzekļu klāsts, kas radīts, lai risinātu ikdienas pēdu problēmas — no sausas ādas līdz nogurušām kājām."
- text2: "Piemērots ikdienas lietošanai mājās un profesionālai pēdu aprūpei."
- "Kā strādā Gehwole", 3 шага:
  1. Mitrina un mīkstina — "Aktīvās sastāvdaļas iesūcas ādā un atjauno tās dabisko mitruma līdzsvaru."
  2. Atjauno un stiprina — "Regulāra lietošana stiprina ādas aizsargfunkciju un uzlabo tās stāvokli."
  3. Nodrošina ilgtermiņa komfortu — "Pēdas paliek veselas, mīkstas un bez diskomforta ikdienā."

**Jaunumi** (3 карточки, без ссылок наружу — `#` или заглушка)
1. "Kā izvairīties no sausas ādas uz papēžiem ziemā"
2. "5 ieradumi veselīgām pēdām ikdienā"
3. "Kāpēc regulāra pēdu kopšana ir svarīga pēc 40 gadu vecuma"

**Produkcija** (6 позиций, без внешних ссылок)
1. Gehwole Intensīvais pēdu krēms 75 ml
2. Gehwole Mitrinošais pēdu krēms 125 ml
3. Gehwole Atsvaidzinošā pēdu vanna 400 g
4. Gehwole Nagu kopšanas eļļa 15 ml
5. Gehwole Pemza cietās ādas noņemšanai
6. Gehwole Pēdu kopšanas komplekts (krēms + vanna)

**Risinājumi** (проблема → решение, 4 пары)
1. "Sausa, plaisājoša āda uz papēžiem" → "Intensīvais krēms ar dziļi mitrinošu formulu mīkstina raga ādu."
2. "Nogurušas, pietūkušas kājas dienas beigās" → "Atsvaidzinošā vanna uzlabo asinsriti un mazina smaguma sajūtu."
3. "Cietā āda un nospiedumi" → "Regulāra pemzas lietošana kopā ar krēmu noņem cieto ādu bez traumēšanas."
4. "Trausli nagi un ādas kairinājums ap tiem" → "Kopšanas eļļa stiprina nagus un mīkstina apkārtējo ādu."

**Kontakti** (реквизиты — плейсхолдер, реальная компания/адрес уточняется позже)
- Baltic Pro Company, SIA · Reģ. nr.: 40203544254 · Jūrkalnes iela 12, LV-1046 Rīga
- WhatsApp: +37129109868, email: info@gehwole.eu (плейсхолдер-домен)

## Визуальный стиль (источник: www.gehwol.de, реальные значения из их CSS)

- Акцент: `#45797c` (teal) — ссылки, активные состояния, заливка кнопок.
- Текст: `#000` заголовки, `#333`/`#6d6d6e` вторичный текст, фон `#fff`.
- Шрифт: `Open Sans` (Google Fonts), fallback `Arial, Helvetica, sans-serif`.
- Заголовки: h1 32px / h2 28px / h3 20px, bold, line-height 120%, чёрные.
- Радиусы: `border-radius: 0` везде (кнопки, карточки, инпуты) — острые углы.
- Кнопки: сплошная teal-заливка, белый жирный текст, без градиентов/теней.
- Nav: горизонтальный список, uppercase, teal подчёркивание на hover/active.
- Изображения: нет реальных фото по теме — используются CSS-плейсхолдеры
  (teal/светло-серый фон + SVG-иконка по смыслу блока), а не переиспользование
  фото Dufta.

## Технические изменения

**HTML** (`src/html/`)
- `index.html` — убрать переключатель языков, `agreement-bar`, оставить
  порядок: header → hero → about → news → products → solutions → footer.
- `blocks/header.html`, `blocks/mobile-nav.html` — новая навигация из 5
  пунктов, логотип/название Gehwole, без языкового свитчера.
- `blocks/about.html` — переписать под новый контент (структура about__how
  остаётся, texts новые).
- `blocks/news.html` (новый, заменяет `blog.html`) — 3 карточки, тот же
  swiper-паттерн.
- `blocks/products.html` — переписать список товаров, оставить swiper.
- `blocks/solutions.html` (новый, заменяет `applications.html` +
  `howToUse.html`) — 4 пары проблема/решение.
- `blocks/footer.html` — обновить бренд/лого, оставить структуру реквизитов.
- Удалить: `agreement-bar.html`, `benefits.html`, `blog.html`, `consist.html`,
  `delivery-payment.html`, `uses-cases.html`, `applications.html`,
  `howToUse.html`.
- Удалить: `html/data/translations.js` (i18n больше не нужен, весь текст —
  прямой LV в HTML).

**JS** (`src/js/`)
- `index.js` — убрать импорт/вызов `setLanguage`, `agreement-cookies`.
  Оставить: `swipers`, `header-scroll`, `scrollReveal`, `mobile-nav`.
- Удалить: `modules/setLanguage.js`, `modules/agreement-cookies.js`.
- `modules/swipers.js` — переименовать/подправить селекторы под
  `.products-slider`/`.news-slider` (было `.blog-slider`).

**SCSS** (`src/scss/`)
- `base/_vars.scss` — заменить палитру на gehwol-токены (`--accent: #45797c`,
  `--font-main: 'Open Sans', Arial, Helvetica, sans-serif`, убрать
  `--btn-link-color`/`--gradient`/`--accent2` дуфта-специфичные, добавить
  `--radius: 0`).
- Добавить подключение шрифта Open Sans (Google Fonts `@import` либо
  self-host — self-host предпочтительнее для perf, но для лендинга с плейсхолдер-контентом
  используем Google Fonts `@import` ради простоты).
- Переписать под БЭМ с нуля: `_header.scss`, `_hero.scss`, `_about.scss`,
  `_news.scss` (новый), `_products.scss`, `_solutions.scss` (новый),
  `_footer.scss`, `_mobile-nav.scss`, `_btn.scss`, `_titles.scss`.
- Удалить: `_switcher.scss`, `_agreement-bar.scss`, `_benefits.scss`,
  `_blog.scss`, `_consist.scss`, `_delivery-payment.scss`, `_uses-cases.scss`.

## БЭМ — правила именования

Строго `block__elem--mod` / `block--mod`, без вложенных дефисов в имени блока
(текущие `products-section`, `blog-slide`, `card-benefit`, `how-to-use-box` —
нарушения, которые не переносим в новые блоки). Новые блоки: `hero`, `about`,
`news`, `products`, `solutions`, `site-header`, `site-footer`, `site-nav`,
`mobile-nav`. Элементы через `__`, модификаторы через `--`
(например `nav__link`, `nav__link--active`).

## Из зоны охвата (явно не делаем)

- Реальные фото/логотип — плейсхолдеры вместо них.
- Реальные ссылки на магазин/блог — убраны (некуда вести, внешнего магазина
  для Gehwole нет).
- Формы (контактная форма, подписка) — не запрошены, не добавляем.
- SEO-мета (description/keywords) — заполняем плейсхолдер-текстом на LV,
  без реального SEO-исследования.
