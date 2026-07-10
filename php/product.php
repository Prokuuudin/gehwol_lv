<?php

require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/includes/links.php';

$id = (int)($_GET['id'] ?? 0);

$product = null;
foreach (load_collection('products') as $p) {
    if ((int)$p['id'] === $id) {
        $product = $p;
        break;
    }
}

if (!$product) {
    http_response_code(404);
    echo '<h1>Produkts nav atrasts</h1>';
    exit;
}

$category = null;
foreach (load_collection('categories') as $c) {
    if ((int)$c['id'] === (int)$product['category_id']) {
        $category = $c;
        break;
    }
}
$categoryName = $category['name'] ?? '';
$categoryHref = htmlspecialchars($category ? category_link($category) : '../index.html#products');
?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= htmlspecialchars($product['name']) ?> — Gehwole</title>
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
      <a href="<?= $categoryHref ?>" class="category__crumb"><?= htmlspecialchars($categoryName) ?></a>
      <span class="category__crumb-sep">/</span>
      <span class="category__crumb category__crumb--current"><?= htmlspecialchars($product['name']) ?></span>
    </nav>
    <h1 class="category__title"><?= htmlspecialchars($product['name']) ?></h1>
    <div class="product-detail">
      <div class="product-detail__media">
        <?php if ($product['image']): ?>
          <img src="../uploads/products/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
        <?php else: ?>
          <span class="product-detail__placeholder">GEHWOL</span>
        <?php endif; ?>
      </div>
      <p class="product-detail__description"><?= nl2br(htmlspecialchars($product['description'] ?? '')) ?></p>
      <a href="../index.html#contacts" class="product-detail__cta btn-link">Sazinieties, lai pasūtītu →</a>
    </div>
  </div>
</section>
</main>
</body>
</html>
