<?php

require_once __DIR__ . '/includes/text-items.php';

text_items_page([
    'collection' => 'articles',
    'page' => 'articles.php',
    'prefix' => 'raksts',
    'title' => 'Raksti',
    'add' => 'Pievienot rakstu',
    'edit' => 'Rediģēt rakstu',
    'not_found' => 'Raksts nav atrasts.',
    'dated' => false,
]);
