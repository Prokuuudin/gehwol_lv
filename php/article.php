<?php

require_once __DIR__ . '/includes/storage.php';

$id = (int)($_GET['id'] ?? 0);

$item = null;
foreach (load_collection('articles') as $a) {
    if ((int)$a['id'] === $id) {
        $item = $a;
        break;
    }
}

if (!$item) {
    http_response_code(404);
    echo '<h1>Raksts nav atrasts</h1>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= htmlspecialchars($item['title']) ?> — Gehwol</title>
<link rel="stylesheet" href="../css/main.css" />
<link rel="icon" type="image/x-icon" href="../img/favicons/favicon.ico">
</head>
<body>
<main>
<section class="category">
  <div class="container category__container">
    <nav class="category__breadcrumbs" aria-label="Breadcrumbs">
      <a href="../index.html" class="category__crumb">Sākums</a>
      <span class="category__crumb-sep">/</span>
      <a href="../index.html#news" class="category__crumb">Jaunumi un informācija</a>
      <span class="category__crumb-sep">/</span>
      <span class="category__crumb category__crumb--current"><?= htmlspecialchars($item['title']) ?></span>
    </nav>
    <h1 class="category__title"><?= htmlspecialchars($item['title']) ?></h1>
    <div class="content-detail">
      <?php if ($item['image']): ?>
        <div class="content-detail__media"><img src="../uploads/articles/<?= htmlspecialchars($item['image']) ?>" alt=""></div>
      <?php else: ?>
        <div class="content-detail__media" aria-hidden="true"></div>
      <?php endif; ?>
      <p class="content-detail__text"><?= nl2br(htmlspecialchars($item['text'] ?? '')) ?></p>
    </div>
  </div>
</section>
</main>
</body>
</html>
