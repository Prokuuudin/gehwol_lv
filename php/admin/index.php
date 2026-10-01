<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

require_login();
$sections = [
    ['label' => 'Kategorijas', 'count' => count(load_collection('categories')), 'href' => 'categories.php'],
    ['label' => 'Produkti', 'count' => count(load_collection('products')), 'href' => 'products.php'],
    ['label' => 'Jaunumi', 'count' => count(load_collection('news')), 'href' => 'news.php'],
    ['label' => 'Raksti', 'count' => count(load_collection('articles')), 'href' => 'articles.php'],
];

admin_header('Vadības panelis');
?>
<div class="page-actions">
  <div>
    <h2>Saturs</h2>
    <p class="page-intro">Pārvaldiet vietnes kategorijas, produktus un publikācijas.</p>
  </div>
</div>
<div class="dashboard-grid">
  <?php foreach ($sections as $section): ?>
  <a class="dashboard-card" href="<?= htmlspecialchars($section['href']) ?>">
    <span class="dashboard-card__label"><?= htmlspecialchars($section['label']) ?></span>
    <strong class="dashboard-card__count"><?= (int)$section['count'] ?></strong>
    <span class="dashboard-card__link">Atvērt sadaļu <span aria-hidden="true">→</span></span>
  </a>
  <?php endforeach; ?>
</div>
<section class="panel dashboard-guide" aria-labelledby="admin-guide-title">
  <div class="dashboard-guide__heading">
    <span class="dashboard-guide__icon" aria-hidden="true">?</span>
    <div>
      <h2 id="admin-guide-title">Īsa pamācība</h2>
      <p>Četri vienkārši soļi vietnes satura atjaunošanai.</p>
    </div>
  </div>
  <ol class="dashboard-guide__steps">
    <li><strong>Izvēlieties sadaļu.</strong><span>Produktus, jaunumus un rakstus pārvaldiet attiecīgajā sadaļā.</span></li>
    <li><strong>Atveriet vai pievienojiet ierakstu.</strong><span>Aizpildiet laukus un, ja vajag, pievienojiet attēlus.</span></li>
    <li><strong>Pārbaudiet pirms publicēšanas.</strong><span>Izmantojiet “Priekšskatīt”; atzīme “Publicēts” padara ierakstu redzamu vietnē.</span></li>
    <li><strong>Saglabājiet un apskatiet rezultātu.</strong><span>Pēc saglabāšanas atveriet “Skatīt vietni”.</span></li>
  </ol>
  <p class="dashboard-guide__tip"><strong>Noderīgi:</strong> kategoriju struktūru maina izstrādātājs. Ja rodas tehniska problēma, atveriet “Servera pārbaude”.</p>
</section>
<section class="panel dashboard-note">
  <div>
    <h2>Servera statuss</h2>
    <p>Pārbaudiet PHP vidi, datu failus un rakstīšanas tiesības.</p>
  </div>
  <a class="button" href="health.php">Veikt pārbaudi</a>
</section>
<?php
admin_footer();
