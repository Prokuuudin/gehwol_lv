<?php
// php/includes/site-config.php — public site settings (same values as seo.config.json / gulp/seo.js).

const SITE_URL = 'https://gehwol.lv';
const SITE_NAME = 'Gehwol Latvijā';
const SITE_ORGANIZATION = [
    'name' => 'Versija Intersource, SIA',
    'telephone' => '+37127055700',
    'email' => 'versia@load.lv',
    'streetAddress' => 'Dzērbenes 14, 306.C',
    'addressLocality' => 'Rīga',
    'postalCode' => 'LV-1006',
    'addressCountry' => 'LV',
];

/** Folder with the built static site (css, js, img, legal pages). In production it is the document root. */
function site_public_dir(): string
{
    return rtrim(getenv('GEHWOL_PUBLIC_DIR') ?: dirname(__DIR__, 2), '/\\');
}
