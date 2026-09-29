<?php

function admin_header(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($title) ?> — Admin</title>
<style>
body{font-family:sans-serif;max-width:1000px;margin:1rem auto;padding:0 1rem;}
nav{display:flex;flex-wrap:wrap;gap:.5rem 1rem;align-items:center;}
nav form{margin-left:auto;}
table{border-collapse:collapse;width:100%;margin-top:1rem;}
th,td{border:1px solid #ccc;padding:0.4rem;text-align:left;vertical-align:middle;}
td form{display:inline;}
.error{color:#b00020;}
.notice{color:#0a5c36;}
.thumb{width:60px;height:60px;object-fit:contain;background:#f3f3f3;}
.images td{text-align:center;}
input[type=text],textarea,select{max-width:100%;}
:focus-visible{outline:3px solid #1a73e8;outline-offset:2px;}
.images input[type=text]{width:100%;box-sizing:border-box;}
@media (max-width:640px){.list th:nth-child(1),.list td:nth-child(1){display:none;}.images th:nth-child(4),.images td:nth-child(4){width:3em;}}
</style>
</head>
<body>
<nav>
  <a href="index.php">Sākums</a>
  <a href="categories.php">Kategorijas</a>
  <a href="products.php">Produkti</a>
  <a href="news.php">Jaunumi</a>
  <a href="articles.php">Raksti</a>
  <a href="password.php">Parole</a>
  <a href="../../" target="_blank" rel="noopener">Skatīt vietni</a>
  <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit">Iziet</button></form>
</nav>
<h1><?= htmlspecialchars($title) ?></h1>
<?php foreach (storage_problems() as $problem): ?>
<p class="error"><?= htmlspecialchars($problem) ?> Saglabāšana nedarbosies — sazinieties ar izstrādātāju.</p>
<?php endforeach; ?>
<?php if (function_exists('image_processing_available') && !image_processing_available()): ?>
<p class="error">Serverī nav attēlu apstrādes (PHP GD): attēli tiks saglabāti bez samazināšanas.</p>
<?php endif; ?>
<?php if (!empty($_GET['saved'])): ?>
<p class="notice" role="status">Saglabāts.</p>
<?php endif; ?>
<?php
}

function admin_footer(): void
{
    ?>
</body>
</html>
<?php
}

/** POST form with a confirmation for deleting a record. */
function delete_button(string $page, int $id): string
{
    return '<form method="post" action="' . htmlspecialchars($page) . '?action=delete" onsubmit="return confirm(\'Dzēst neatgriezeniski?\')">'
        . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><button type="submit">Dzēst</button></form>';
}

/** Admin pages live in php/admin/, site images are relative to the site root. */
function admin_image_url(string $src): string
{
    return '../../' . ltrim($src, '/');
}

function status_label(array $row): string
{
    return is_published($row) ? 'Publicēts' : '<strong>Melnraksts</strong>';
}
