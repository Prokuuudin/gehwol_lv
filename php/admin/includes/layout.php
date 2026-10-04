<?php

require_once __DIR__ . '/deletion.php';

/** @return array<string, array{label: string}> */
function admin_navigation_items(): array
{
    return [
        'index.php' => ['label' => 'Sākums'],
        'categories.php' => ['label' => 'Kategorijas'],
        'products.php' => ['label' => 'Produkti'],
        'news.php' => ['label' => 'Jaunumi'],
        'articles.php' => ['label' => 'Raksti'],
        'password.php' => ['label' => 'Parole'],
        'health.php' => ['label' => 'Servera pārbaude'],
    ];
}

function admin_navigation(string $class): void
{
    $current = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    ?>
<nav class="<?= htmlspecialchars($class) ?>" aria-label="Administrācijas sadaļas">
  <?php foreach (admin_navigation_items() as $href => $item): ?>
    <?php $active = $href === $current; ?>
    <a class="admin-nav__link<?= $active ? ' is-active' : '' ?>" href="<?= htmlspecialchars($href) ?>"
      <?= $active ? 'aria-current="page"' : '' ?>>
      <span><?= htmlspecialchars($item['label']) ?></span>
    </a>
  <?php endforeach; ?>
</nav>
<?php
}

function admin_site_link(): void
{
    ?>
<a class="admin-site-link" href="../../" target="_blank" rel="noopener">
  <span>Uz vietni</span>
  <span class="admin-site-link__icon" aria-hidden="true">↗</span>
</a>
<?php
}

function admin_header(string $title): void
{
    $recentDeletion = recent_deletion();
    $showUndo = $recentDeletion !== null;
    $deletedRow = is_array($recentDeletion['row'] ?? null) ? $recentDeletion['row'] : [];
    $deletedLabel = (string)($deletedRow['name'] ?? $deletedRow['title'] ?? 'Ieraksts');
    $adminCssVersion = @filemtime(__DIR__ . '/../admin.css') ?: 1;
    ?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($title) ?> — GEHWOL Admin</title>
<link rel="stylesheet" href="admin.css?v=<?= (int)$adminCssVersion ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Pāriet uz saturu</a>
<header class="mobile-header">
  <a class="mobile-brand" href="index.php" aria-label="GEHWOL administrācijas sākums">
    <span class="brand-mark" aria-hidden="true">G</span>
    <span>GEHWOL</span>
  </a>
  <div class="mobile-header__actions">
    <?php admin_site_link(); ?>
    <details class="mobile-menu">
      <summary>Izvēlne</summary>
      <div class="mobile-menu__panel">
        <?php admin_navigation('admin-nav admin-nav--mobile'); ?>
        <form class="logout-form" method="post" action="logout.php">
          <?= csrf_field() ?>
          <button class="admin-nav__link admin-nav__button" type="submit">Iziet</button>
        </form>
      </div>
    </details>
  </div>
</header>
<div class="admin-shell">
  <aside class="sidebar">
    <a class="brand" href="index.php" aria-label="GEHWOL administrācijas sākums">
      <span class="brand-mark" aria-hidden="true">G</span>
      <span class="brand-copy"><strong>GEHWOL</strong><small>Administrācija</small></span>
    </a>
    <?php admin_navigation('admin-nav'); ?>
    <div class="sidebar__footer">
      <?php admin_site_link(); ?>
      <span class="admin-user" title="Pierakstījies lietotājs"><?= htmlspecialchars(current_admin_username()) ?></span>
      <form class="logout-form" method="post" action="logout.php">
        <?= csrf_field() ?>
        <button class="admin-nav__link admin-nav__button" type="submit">Iziet</button>
      </form>
    </div>
  </aside>
  <main class="admin-main" id="main-content">
    <div class="admin-content">
      <header class="page-heading">
        <p class="eyebrow">Administrācija</p>
        <h1><?= htmlspecialchars($title) ?></h1>
      </header>
<?php foreach (storage_problems() as $problem): ?>
      <div class="alert alert--error" role="alert">
        <strong>Saglabāšana nav pieejama.</strong>
        <span><?= htmlspecialchars($problem) ?> Sazinieties ar izstrādātāju.</span>
      </div>
<?php endforeach; ?>
<?php if (function_exists('image_processing_available') && !image_processing_available()): ?>
      <div class="alert alert--warning" role="alert">
        <strong>Attēlu apstrāde nav pieejama.</strong>
        <span>Serverī nav PHP GD; attēli tiks saglabāti bez samazināšanas.</span>
      </div>
<?php endif; ?>
<?php if (!empty($_GET['saved'])): ?>
      <div class="alert alert--success" role="status">
        <strong>Saglabāts.</strong>
        <span>Izmaiņas ir veiksmīgi saglabātas.</span>
      </div>
<?php endif; ?>
<?php if (!empty($_GET['restored'])): ?>
      <div class="alert alert--success" role="status">
        <strong>Dzēšana atcelta.</strong>
        <span>Ieraksts un tā attēli ir atjaunoti.</span>
      </div>
<?php endif; ?>
<?php if (!empty($_GET['undo_failed'])): ?>
      <div class="alert alert--error" role="alert">
        <strong>Dzēšanu vairs nevar atcelt.</strong>
        <span>Atcelšanas laiks ir beidzies vai ieraksts jau ir atjaunots.</span>
      </div>
<?php endif; ?>
<?php if ($showUndo): ?>
      <div class="alert alert--warning alert--undo" role="status">
        <strong>“<?= htmlspecialchars($deletedLabel) ?>” ir dzēsts.</strong>
        <span>Dzēšanu var atcelt 5 minūšu laikā.</span>
        <form class="alert__action" method="post" action="<?= htmlspecialchars(basename((string)$recentDeletion['page'])) ?>?action=undo">
          <?= csrf_field() ?>
          <input type="hidden" name="undo_token" value="<?= htmlspecialchars((string)$recentDeletion['token']) ?>">
          <button class="button button--small" type="submit">Atcelt dzēšanu</button>
        </form>
      </div>
<?php endif; ?>
<?php
}

function admin_footer(): void
{
    ?>
    </div>
  </main>
</div>
</body>
</html>
<?php
}

/** POST form with a confirmation for deleting a record. */
function delete_button(string $page, int $id): string
{
    $warning = 'Vai tiešām dzēst šo ierakstu? Tas uzreiz pazudīs no vietnes. Dzēšanu varēs atcelt 5 minūšu laikā.';
    $confirm = 'return confirm(' . json_encode($warning, JSON_UNESCAPED_UNICODE) . ')';
    return '<form class="inline-form" method="post" action="' . htmlspecialchars($page) . '?action=delete" onsubmit="' . htmlspecialchars($confirm, ENT_QUOTES) . '">'
        . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><button class="button button--danger button--small" type="submit">Dzēst</button></form>';
}

/** Admin pages live in php/admin/, site images are relative to the site root. */
function admin_image_url(string $src): string
{
    return '../../' . ltrim($src, '/');
}

function status_label(array $row): string
{
    return is_published($row)
        ? '<span class="badge badge--success"><span aria-hidden="true">✓</span> Publicēts</span>'
        : '<span class="badge badge--draft"><span aria-hidden="true">○</span> Melnraksts</span>';
}
