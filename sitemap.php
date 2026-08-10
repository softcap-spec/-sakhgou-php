<?php
// sitemap.php — динамический sitemap.xml
header('Content-Type: application/xml; charset=utf-8');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$base = 'https://сахгоу.рф';
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Статические страницы
$static = [
  ['url' => '/', 'freq' => 'daily', 'priority' => '1.0'],
  ['url' => '/catalog', 'freq' => 'daily', 'priority' => '0.9'],
  ['url' => '/login', 'freq' => 'monthly', 'priority' => '0.3'],
  ['url' => '/register', 'freq' => 'monthly', 'priority' => '0.3'],
  ['url' => '/help', 'freq' => 'monthly', 'priority' => '0.4'],
  ['url' => '/privacy', 'freq' => 'yearly', 'priority' => '0.1'],
  ['url' => '/terms', 'freq' => 'yearly', 'priority' => '0.1'],
  ['url' => '/search', 'freq' => 'weekly', 'priority' => '0.5'],
];
foreach ($static as $p) {
  echo "  <url>\n    <loc>{$base}{$p['url']}</loc>\n    <changefreq>{$p['freq']}</changefreq>\n    <priority>{$p['priority']}</priority>\n  </url>\n";
}

// Категории
$cats = db()->query("SELECT slug FROM categories ORDER BY id")->fetchAll();
foreach ($cats as $cat) {
  echo "  <url>\n    <loc>{$base}/catalog/{$cat['slug']}</loc>\n    <changefreq>daily</changefreq>\n    <priority>0.8</priority>\n  </url>\n";
}

// Активные объявления
$listings = db()->query("SELECT id, updated_at FROM listings WHERE status='active' ORDER BY id DESC LIMIT 500")->fetchAll();
foreach ($listings as $l) {
  $lastmod = date('c', strtotime($l['updated_at']));
  echo "  <url>\n    <loc>{$base}/listing/{$l['id']}</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.7</priority>\n  </url>\n";
}

echo '</urlset>';
