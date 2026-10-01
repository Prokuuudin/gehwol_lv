# Выкладка gehwol.lv

Production работает в Plesk. Git checkout ветки `main` находится в
`/var/www/vhosts/gehwol.lv/docs`, document root — в
`/var/www/vhosts/gehwol.lv/httpdocs`.

## Что является runtime-данными

Эти каталоги в production являются authoritative и persistent:

- `httpdocs/php/data/**` — JSON, lock-файлы и резервные копии, которыми управляет админка;
- `httpdocs/php/data/backups/**` — runtime-копии JSON (входит в предыдущий путь и указан отдельно для ясности);
- `httpdocs/uploads/**` — загруженные через админку изображения.

Обычный release и автоматический deploy никогда не создают, не заменяют и не
удаляют ничего внутри этих путей и не меняют их permissions. Служебный каталог
Plesk/Let's Encrypt `.well-known/**` также не управляется deploy-скриптом.

## Production release

`npm run release` собирает `release/` из уже подготовленных `docs/` и PHP-кода.
В него входят PHP application code, templates, CSS, JS, изображения, шрифты,
статические и юридические страницы, корневые `.htaccess` и `.user.ini`. В него
не входят Git, `node_modules`, исходники, тесты, документация, credentials и
production JSON.

`npm run release:first` дополнительно включает начальные JSON. Эта команда
предназначена **только для первой ручной установки**. При обычном обновлении её
использовать нельзя: начальные данные не должны становиться источником истины
для уже работающей production-админки.

## Требования к серверу

- Node.js и npm доступны для Plesk post-deployment action; установка npm-пакетов
  для deploy не нужна, потому что release собирается из committed build output.
- PHP 8.1 или новее с `json`, `mbstring`, `dom`, `session`, `gd`/WebP,
  желательно `fileinfo` и `exif`.
- Apache обрабатывает `.htaccess`; PHP может писать в `php/data/` и `uploads/`.
- Системный пользователь подписки может писать в `docs/release`, `httpdocs` и
  создать lock в `/var/www/vhosts/gehwol.lv`.

## Первая установка

1. Выполнить локально проверки:

   ```sh
   composer test
   npm run build:quick
   npm run check
   npm run test:deploy
   npm run release:first
   ```

2. Создать администратора командой `php php/bin/set-password.php <логин>`.
3. Сохранить прежний `httpdocs` отдельно для возможного отката.
4. Один раз вручную загрузить **содержимое** `release/` в `httpdocs/`, включая
   скрытые `.htaccess` и `.user.ini`.
5. Проверить права записи PHP на `php/data/` и `uploads/`, затем открыть
   `/php/admin/health.php`.
6. После первой установки больше не применять `release:first` к этому сайту.

## Обычное обновление

Автоматический вариант — `npm run deploy:plesk`. Команда:

1. атомарно захватывает общий lock рядом с `httpdocs`, не допуская второй deploy;
2. полностью выполняет существующий `scripts/build-release.js`;
3. при ошибке сборки завершается с non-zero code, не меняя `httpdocs`;
4. проверяет release на runtime JSON, symlinks и development/private content;
5. сравнивает release с production, исключая persistent paths;
6. заменяет новые и изменённые файлы через temporary file + rename;
7. только после успешного копирования удаляет устаревшие deployable-файлы;
8. пишет SHA, результат build/deploy, списки изменений и подтверждение сохранения runtime paths.

Перед изменением production выполнить `composer test`, PHP lint, `npm run check`,
`npm run test:deploy` и `npm run release`. Резервный ручной способ без
post-deployment action: загрузить содержимое обычного `release/` через Plesk
File Manager/FTP, не трогая `php/data/` и `uploads/`; устаревшие deployable-файлы
при этом придётся удалить вручную. `release:first` для обновления запрещён.

Проверить план без изменения destination:

```sh
npm run deploy:plesk -- --dry-run
```

Для ручного локального/тестового запуска другой destination задаётся явно:

```sh
npm run deploy:plesk -- --destination /safe/test/httpdocs
```

## Автоматический Plesk deployment

В Plesk Git должны оставаться branch `main`, automatic deployment и deployment
directory `/docs`. Включить `Enable post deployment actions` и вставить ровно:

```sh
cd /var/www/vhosts/gehwol.lv/docs && npm run deploy:plesk -- --destination /var/www/vhosts/gehwol.lv/httpdocs
```

Команда содержит абсолютный переход в checkout и поэтому не зависит от current
working directory Plesk. Credentials в проекте или в команде не требуются.

После push в `main` webhook обновит `/docs`, Plesk запустит post-deployment
action, release полностью соберётся и проверится, затем deployable-файлы
синхронизируются с `/httpdocs`. Runtime JSON, backups и uploads останутся
byte-for-byte нетронутыми. При неуспешной сборке `/httpdocs` не изменится.

## Rollback

Откат кода выполняется новым commit в `main`, возвращающим нужное состояние
(предпочтительно `git revert`), и обычным автоматическим deploy. Так история
остаётся линейной, а Plesk снова получает `main`. `php/data/**` и `uploads/**`
при rollback не откатываются.

Если deploy прервался уже во время файловой синхронизации, повторно доставить
исправленный или предыдущий commit: операции идемпотентны, а каждый отдельный
файл на Linux заменяется атомарно. Для аварийного восстановления первой
установки использовать сохранённую копию `httpdocs`, не заменяя более свежие
`php/data/**` и `uploads/**` без отдельного решения о восстановлении данных.

Если процесс был аварийно завершён так, что остался
`/var/www/vhosts/gehwol.lv/.gehwol-deploy.lock`, сначала убедиться в Plesk, что
deploy больше не выполняется, и только затем удалить stale lock через File
Manager. Активный lock удалять нельзя.

## Резервное копирование данных

Админка сохраняет предыдущие версии JSON в `php/data/backups/`. Дополнительно
перед существенными изменениями и регулярно по расписанию скачивать через Plesk
File Manager или FTP каталоги `php/data/` и `uploads/`. Git и rollback кода не
заменяют резервную копию этих production-данных.

Восстановление отдельного JSON выполняется из `php/data/backups/`; полное
восстановление — возвратом отдельно сохранённых `php/data/` и `uploads/`.
Перед восстановлением сначала сохранить их текущее production-состояние.

## Если сервер работает только через nginx

В режиме nginx-only `.htaccess` не применяется. В Plesk → Apache & nginx
Settings → Additional nginx directives должны быть эквивалентные ограничения:

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
