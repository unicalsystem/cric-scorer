<?php
/* robots.txt - served at /robots.txt */
require_once __DIR__ . '/inc/seo.php';
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Allow: /\n";
foreach (['/api.php', '/install.php', '/fix.php', '/google-callback.php', '/uploads/', '/m/', '/v/', '/match.php', '/verify.php', '/inc/', '/sql/', '/data/', '/tools/'] as $p) echo "Disallow: $p\n";
echo "\nSitemap: " . site_url('sitemap.xml') . "\n";
