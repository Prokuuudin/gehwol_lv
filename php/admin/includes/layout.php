<?php

function admin_header(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($title) ?> — Admin</title>
<style>
body{font-family:sans-serif;max-width:900px;margin:2rem auto;padding:0 1rem;}
nav a{margin-right:1rem;}
table{border-collapse:collapse;width:100%;margin-top:1rem;}
th,td{border:1px solid #ccc;padding:0.4rem;text-align:left;}
.error{color:#b00020;}
</style>
</head>
<body>
<nav>
  <a href="index.php">Sākums</a>
  <a href="categories.php">Kategorijas</a>
  <a href="products.php">Produkti</a>
  <a href="news.php">Jaunumi</a>
  <a href="articles.php">Raksti</a>
  <a href="logout.php">Iziet</a>
</nav>
<h1><?= htmlspecialchars($title) ?></h1>
<?php
}

function admin_footer(): void
{
    ?>
</body>
</html>
<?php
}
