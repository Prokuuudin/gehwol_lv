<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

require_login();
admin_header('Vadības panelis');
?>
<ul>
  <li><a href="categories.php">Kategorijas</a></li>
  <li><a href="products.php">Produkti</a></li>
  <li><a href="news.php">Jaunumi</a></li>
  <li><a href="articles.php">Raksti</a></li>
  <li><a href="health.php">Servera pārbaude</a></li>
</ul>
<?php
admin_footer();
