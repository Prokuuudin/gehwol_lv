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
<details class="panel dashboard-guide">
  <summary class="dashboard-guide__summary">
    <span class="dashboard-guide__icon" aria-hidden="true">?</span>
    <span class="dashboard-guide__heading">
      <strong id="admin-guide-title" role="heading" aria-level="2">Administrācijas pamācība</strong>
      <span>Detalizēti par satura pievienošanu, pārbaudi un publicēšanu.</span>
    </span>
    <span class="dashboard-guide__toggle" aria-hidden="true">
      <span class="dashboard-guide__toggle-open">Izvērst</span>
      <span class="dashboard-guide__toggle-close">Sakļaut</span>
      <span class="dashboard-guide__chevron">⌄</span>
    </span>
  </summary>
  <div class="dashboard-guide__body" aria-labelledby="admin-guide-title">
    <ol class="dashboard-guide__steps">
      <li><strong>Izvēlieties vajadzīgo sadaļu.</strong><span>“Produkti” paredzēti katalogam, “Jaunumi” — datētām publikācijām, bet “Raksti” — ilgākam informatīvam saturam. Kategoriju struktūru maina izstrādātājs.</span></li>
      <li><strong>Atveriet ierakstu vai izveidojiet jaunu.</strong><span>Lai labotu esošu saturu, spiediet “Rediģēt”. Jaunam saturam izmantojiet sadaļas pogu “Pievienot”. Obligātie lauki ir jāaizpilda pirms saglabāšanas.</span></li>
      <li><strong>Sagatavojiet tekstu.</strong><span>Tekstu var rakstīt bez HTML: tukša rinda izveido jaunu rindkopu. SEO aprakstu var norādīt atsevišķi; ja tas paliek tukšs, sistēma to izveido no satura.</span></li>
      <li><strong>Pievienojiet un sakārtojiet attēlus.</strong><span>Atļauti JPG, PNG un WebP faili līdz 10 MB. Produktam pirmais attēls ir galvenais; secību var mainīt ar kārtas numuriem. Alt teksts palīdz pieejamībai un meklētājiem.</span></li>
      <li><strong>Pārbaudiet melnrakstu.</strong><span>Noņemiet atzīmi “Publicēts”, ja saturam vēl nav jābūt redzamam vietnē. Poga “Priekšskatīt” parāda arī nepublicētu ierakstu tikai autorizētam administratoram.</span></li>
      <li><strong>Saglabājiet un pārbaudiet vietnē.</strong><span>Pēc saglabāšanas pārliecinieties, ka redzams veiksmīgas saglabāšanas paziņojums. Publicētu ierakstu atveriet ar “Skatīt vietnē” un pārbaudiet tekstu, attēlus un saites.</span></li>
      <li><strong>Dzēsiet uzmanīgi.</strong><span>Pirms dzēšanas sistēma lūdz apstiprinājumu. Ja dzēšana veikta kļūdaini, 5 minūšu laikā izmantojiet paziņojumā redzamo pogu “Atcelt dzēšanu”.</span></li>
      <li><strong>Ja rodas tehniska problēma.</strong><span>Atveriet “Servera pārbaude” un apskatiet atzīmētos brīdinājumus. Kļūdas tekstu un veikto darbību nododiet izstrādātājam.</span></li>
    </ol>
    <p class="dashboard-guide__tip"><strong>Drošākais darba veids:</strong> vispirms saglabājiet ierakstu kā melnrakstu, pārbaudiet to priekšskatījumā un tikai pēc tam publicējiet.</p>
  </div>
</details>
<section class="panel dashboard-note">
  <div>
    <h2>Servera statuss</h2>
    <p>Pārbaudiet PHP vidi, datu failus un rakstīšanas tiesības.</p>
  </div>
  <a class="button" href="health.php">Veikt pārbaudi</a>
</section>
<?php
admin_footer();
