# Выкладка gehwol.lv

В панели Plesk Git указано поле **Deployment directory: `/docs`**. При этом
post-deployment shell данного сервера использует реальные filesystem paths:

- source checkout: `/var/www/vhosts/gehwol.lv/docs`;
- production document root: `/var/www/vhosts/gehwol.lv/httpdocs`.

Это различие подтверждено production-запуском: команда с `cd /docs` завершилась
ошибкой `No such file or directory`, а команда с полным filesystem path успешно
обновила production. Поэтому `/docs` из поля панели нельзя переносить в shell-
команду как chroot-relative path.

Production-серверу не нужны Node.js и npm. Они используются только локально и
в CI для подготовки committed build output. Post-deployment action запускает
только PHP 8.4.

## Что подготовлено до Plesk

Папка репозитория `docs/` уже является готовой публичной production-сборкой:
CSS, JS, изображения, шрифты, legal/static pages, `.htaccess` и `.user.ini`.
Она совпадает с публичной частью результата `npm run release`, но полного сайта
недостаточно без PHP application code из `php/`.

Существующий `scripts/build-release.js` продолжает собирать обычный `release/`
и одновременно создаёт tracked-файл `deploy-manifest.json`. Manifest — точный
allow-list прежнего release pipeline:

- публичные файлы берутся из committed `docs/`;
- application code и templates — из разрешённой части committed `php/`;
- `php/data/**`, `uploads/**`, dev PHP tools, исходники, тесты и credentials в
  manifest не попадают.

PHP-скрипт на Plesk ничего не компилирует. Он читает manifest, проверяет все
source mappings и синхронизирует уже подготовленные файлы.

## Persistent production data

Эти пути являются authoritative runtime-данными и никогда не обходятся, не
удаляются, не перезаписываются и не получают новые permissions при deploy:

- `/var/www/vhosts/gehwol.lv/httpdocs/php/data/**`;
- `/var/www/vhosts/gehwol.lv/httpdocs/php/data/backups/**`;
- `/var/www/vhosts/gehwol.lv/httpdocs/uploads/**`;
- `/var/www/vhosts/gehwol.lv/httpdocs/.well-known/**`.

Production JSON из репозитория не включается в `deploy-manifest.json` и не может
заменить данные, изменённые через production-админку.

## Подготовка обычного обновления

Выполнить локально или в доверенном CI до push:

```sh
composer test
npm run build:quick
npm run check
npm run release
```

Закоммитить изменённые production-файлы в `docs/`, PHP-код и обновлённый
`deploy-manifest.json`. CI повторно строит release, проверяет, что manifest не
изменился, выполняет PHP lint, PHPUnit и отдельные PHP deployment tests.

`npm run release:first` при обычном обновлении запрещён. Он предназначен только
для первой установки с начальными JSON.

## Автоматический PHP deployment

`scripts/deploy-plesk.php`:

1. атомарно создаёт `/var/www/vhosts/gehwol.lv/.gehwol-deploy.lock` и блокирует concurrent deploy;
2. полностью валидирует manifest и все source-файлы до изменения production;
3. отклоняет symlinks, unsafe paths, runtime paths и private/dev content;
4. сравнивает SHA-256 готовых файлов с production `/var/www/vhosts/gehwol.lv/httpdocs`;
5. заменяет новый и изменённый файл через temporary file + rename;
6. удаляет устаревшие deployable-файлы только после успешного копирования;
7. не входит в persistent paths даже для сравнения;
8. логирует content version, additions, updates, removals и результат;
9. возвращает non-zero exit code при ошибке.

Замена near-atomic на уровне каждого файла. Полный swap всего `httpdocs` не
используется, поскольку runtime JSON и uploads могут изменяться админкой во
время deploy и должны оставаться на месте.

Проверить план без изменения production destination:

```sh
cd /var/www/vhosts/gehwol.lv/docs && php scripts/deploy-plesk.php --dry-run --destination=/var/www/vhosts/gehwol.lv/httpdocs
```

## Настройка Plesk

Фактически работающая production-конфигурация:

- Repository branch: `main`;
- Deployment mode: `Automatic`;
- Deployment directory: `/docs`;
- Enable post deployment actions: `ON`.

Post deployment action:

```sh
cd /var/www/vhosts/gehwol.lv/docs && php scripts/deploy-plesk.php --destination=/var/www/vhosts/gehwol.lv/httpdocs
```

Поле панели **Deployment directory** остаётся `/docs`, но shell action в этом
окружении обязан использовать полный путь `/var/www/vhosts/gehwol.lv/docs`.
Команда подтверждена реальным production deployment, не зависит от initial
working directory и не использует SSH, Node.js/npm или credentials.

После push Plesk обновит deployment directory, PHP проверит committed allow-list
в `/var/www/vhosts/gehwol.lv/docs` и только затем синхронизирует код с
`/var/www/vhosts/gehwol.lv/httpdocs`. Ошибка manifest/source validation
оставляет production полностью неизменным. Ошибка отдельной файловой операции
не оставляет частично записанный файл; stale-файлы удаляются только после
установки additions/updates.

## Первая установка

1. Локально выполнить полный test suite и `npm run build:quick`.
2. Создать администратора: `php php/bin/set-password.php <логин>`.
3. Выполнить `npm run release:first`.
4. Сохранить прежний `httpdocs` отдельно.
5. Один раз вручную загрузить содержимое `release/` в `httpdocs`, включая
   `.htaccess`, `.user.ini`, начальные JSON и protection files.
6. Проверить права записи PHP на `php/data/` и `uploads/`, затем открыть
   `/php/admin/health.php`.
7. После инициализации использовать только обычный automatic PHP deployment.

## Rollback

Создать новый commit, возвращающий нужную версию кода и готового
`deploy-manifest.json` (обычно `git revert`), и отправить его в `main`. Plesk
выполнит тот же PHP deployment. Runtime JSON, backups, uploads и `.well-known`
при rollback не меняются.

Если процесс был аварийно завершён и оставил
`/var/www/vhosts/gehwol.lv/.gehwol-deploy.lock`, сначала в Plesk убедиться, что
deploy больше не выполняется, и только затем удалить stale lock через File
Manager. Активный lock удалять нельзя.

## Резервное копирование runtime data

Админка хранит предыдущие JSON в `php/data/backups/`. Дополнительно перед
существенными изменениями и регулярно по расписанию скачивать через Plesk File
Manager или FTP каталоги `php/data/` и `uploads/`. Git rollback не заменяет их
резервную копию.

## Если сервер работает только через nginx

В nginx-only режиме `.htaccess` не применяется. В Plesk → Apache & nginx
Settings → Additional nginx directives нужны эквивалентные ограничения:

```nginx
location ~ ^/php/(data|includes|templates|bin)/ { deny all; }
location ~ ^/uploads/.*\.(php|phtml|phar)$ { deny all; }
location ~ /\.(?!well-known/) { deny all; }
location ~ \.(lock|tmp|log|md|ini|bak|sql|docx?)$ { deny all; }
location / { try_files $uri $uri/ /php/site.php$is_args$args; }
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header X-Frame-Options "SAMEORIGIN" always;
```

После настройки `/php/data/products.json` и `/php/includes/render.php` должны
отвечать 403.
