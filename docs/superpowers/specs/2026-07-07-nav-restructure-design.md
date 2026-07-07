# Gehwol LV — перестройка навигации и удаление секции Risinājumi

Дата: 2026-07-07
Статус: утверждено пользователем (устно в сессии)

## Цель

Обновить структуру меню сайта под новую товарную структуру Gehwol и удалить секцию
Risinājumi со страницы. Сайт остаётся одностраничным лендингом; страницы категорий
не создаются — ссылки пунктов дерева Produkti пока заглушки `#`.

## Верхнее меню

Десктоп и мобильное меню, порядок пунктов:

| Пункт | Ссылка |
|---|---|
| Ražotājs | `index.html#about` (десктоп) / `#about` (мобильное) |
| Produkti | `index.html#products` + мега-панель |
| Informācija un jaunumi | `index.html#news` |
| Kontakti | `index.html#contacts` |

Пункт Risinājumi удаляется из обоих меню, секция — со страницы (см. ниже).

## Дерево Produkti

- **Kosmētika**
  - GEHWOL Classic
  - GEHWOL MED®
  - GEHWOL FUSSKRAFT
  - GEHWOL FUSSKRAFT Soft Feet
  - GEHWOL PROFESSIONAL
  - GERLASAN
  - GERLAVIT
- **Spiedienu uz pēdām mazinoši līdzekļi**
  - Polimēra gēla izstrādājumi, pārvilkti ar tekstilu
  - Polimēra gēla izstrādājumi
  - Plāksteri
  - Filca izstrādājumi
- **Tehnika**
  - Pēdu kopšanas aparāti
  - Pacienta krēsli
  - Darbinieka krēsli
  - **Rotējošie instrumenti** (подгруппа)
    - Keramiskās frēzes
    - Pulētāji
    - Vienreizlietojamās smilšpapīra frēzes

Все ссылки внутри дерева — `#` (заглушки до появления страниц категорий).

## Десктоп: мега-меню

- Пункт Produkti получает стрелку-индикатор и мега-панель.
- Панель позиционируется под шапкой, ширина — по контейнеру, 3 колонки:
  Kosmētika / Spiedienu uz pēdām mazinoši līdzekļi / Tehnika.
- В колонке Tehnika после трёх пунктов идёт подзаголовок Rotējošie instrumenti
  и его 3 под-пункта с отступом.
- Открытие: CSS `:hover` и `:focus-within` (доступно с клавиатуры), плавное
  появление (opacity/visibility transition). JS для десктопа не нужен.
- Стилистика — существующая: font-accent, uppercase у заголовков колонок,
  акцентный цвет `var(--accent)` для hover.

## Мобильное меню: аккордеон

- Fullscreen-оверлей `.mobile-nav` сохраняется.
- Produkti — кнопка-аккордеон: тап раскрывает список категорий.
- Kosmētika, Spiedienu…, Tehnika — вложенные аккордеоны второго уровня.
- Внутри Tehnika — аккордеон третьего уровня Rotējošie instrumenti.
- JS: делегированный обработчик в `src/js/modules/mobile-nav.js` — тоггл класса
  `--open` на родительском `li`, раскрытие через `max-height`/`grid-template-rows`.
- При закрытии оверлея все аккордеоны сворачиваются.

## Удаление Risinājumi

- Удалить `src/html/blocks/solutions.html`; убрать `@@include('blocks/solutions.html')`
  из `src/html/index.html`.
- Удалить `src/scss/blocks/_solutions.scss` (подключается глобом `./blocks/*.scss`,
  правок в `main.scss` не нужно).
- Убрать `#solutions` из списка scroll-margin в `src/scss/base/_base.scss`.
- Убрать `.solutions__item` из селектора в `src/js/modules/scrollReveal.js`.

## Затрагиваемые файлы

- `src/html/blocks/header.html` — новое меню + мега-панель
- `src/html/blocks/mobile-nav.html` — вложенные списки-аккордеоны
- `src/html/index.html` — убрать include solutions
- `src/html/blocks/solutions.html` — удалить
- `src/scss/blocks/_header.scss` — стили мега-меню
- `src/scss/blocks/_mobile-nav.scss` — стили аккордеона
- `src/scss/blocks/_solutions.scss` — удалить
- `src/scss/base/_base.scss` — убрать `#solutions`
- `src/js/modules/mobile-nav.js` — тогглы аккордеона
- `src/js/modules/scrollReveal.js` — убрать `.solutions__item`

## Критерии готовности

- Десктоп: hover/фокус на Produkti открывает панель со всем деревом; остальные
  пункты ведут на якоря; Risinājumi нигде нет.
- Мобильное: аккордеоны раскрывают все 3 уровня; якорные пункты закрывают оверлей
  и скроллят к секции.
- Сборка gulp проходит без ошибок; в собранном HTML/CSS/JS нет упоминаний solutions.
