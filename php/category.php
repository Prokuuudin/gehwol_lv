<?php

require_once __DIR__ . '/includes/storage.php';

$id = (int)($_GET['id'] ?? 0);

$categories = load_collection('categories');
$category = null;
foreach ($categories as $c) {
    if ((int)$c['id'] === $id) {
        $category = $c;
        break;
    }
}

if (!$category) {
    http_response_code(404);
    echo '<h1>Kategorija nav atrasta</h1>';
    exit;
}

$parentName = 'Produkti';
if ($category['parent_id']) {
    foreach ($categories as $c) {
        if ((int)$c['id'] === (int)$category['parent_id']) {
            $parentName = $c['name'];
            break;
        }
    }
}

$products = sort_rows(array_values(array_filter(
    load_collection('products'),
    fn($p) => (int)$p['category_id'] === $id
)));
?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= htmlspecialchars($category['name']) ?> — Gehwole</title>
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
      <a href="../index.html#products" class="category__crumb">Produkti</a>
      <span class="category__crumb-sep">/</span>
      <span class="category__crumb"><?= htmlspecialchars($parentName) ?></span>
      <span class="category__crumb-sep">/</span>
      <span class="category__crumb category__crumb--current"><?= htmlspecialchars($category['name']) ?></span>
    </nav>
    <h1 class="category__title"><?= htmlspecialchars($category['name']) ?></h1>
    <div class="category__grid">
      <?php foreach ($products as $p): ?>
      <a href="product.php?id=<?= (int)$p['id'] ?>" class="product-card">
        <div class="product-card__media">
          <?php if ($p['image']): ?>
            <img src="../uploads/products/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
          <?php else: ?>
            <span class="product-card__placeholder">GEHWOL</span>
          <?php endif; ?>
        </div>
        <h3 class="product-card__title"><?= htmlspecialchars($p['name']) ?></h3>
        <span class="product-card__cta btn-link">Uzzināt vairāk →</span>
      </a>
      <?php endforeach; ?>
      <?php if (!$products): ?>
        <p>Šajā kategorijā vēl nav produktu.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
</main>
</body>
</html>
