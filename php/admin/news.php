<?php

require_once __DIR__ . '/includes/text-items.php';

text_items_page([
    'collection' => 'news',
    'page' => 'news.php',
    'prefix' => 'jaunums',
    'title' => 'Jaunumi',
    'add' => 'Pievienot jaunumu',
    'edit' => 'Rediģēt jaunumu',
    'not_found' => 'Jaunums nav atrasts.',
    'dated' => true,
]);
