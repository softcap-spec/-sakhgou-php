<?php
/**
 * сахгоу.рф — Front Controller / Router
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');

$url = $_GET['url'] ?? '';
$url = rtrim($url, '/');
$parts = $url ? explode('/', $url) : [];

$page = $parts[0] ?? 'home';
$sub = $parts[1] ?? null;
$id = $parts[2] ?? null;
// Fix: listing/edit/promote use /listing/N format (id in parts[1])
if (in_array($page, ['listing', 'edit', 'promote']) && empty($id)) { $id = $sub; $sub = null; }

// Maintenance mode check
if ($page !== 'admin' && $page !== 'login' && $page !== 'register' && $page !== 'api') {
  $mcheck = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'maintenance'")->fetchColumn();
  $user = auth_user();
  if ($mcheck === '1' && (!$user || $user['role'] !== 'admin')) {
    http_response_code(503);
    ?><!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Техработы — СахГО</title><script src="https://cdn.tailwindcss.com"></script><script>tailwind.config={theme:{extend:{fontFamily:{sans:['Manrope','Arial','sans-serif'],display:['Manrope','Arial','sans-serif']},colors:{background:'#F2F6F9',foreground:'#121E2B',accent:'#1B6B8A',muted:'#8BA0B5','muted-foreground':'#54677A'}}}}</script><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet"></head><body class="bg-background min-h-screen flex items-center justify-center"><div class="text-center px-4"><div class="text-6xl mb-6">🔧</div><h1 class="font-display text-3xl mb-2">На сайте ведутся<br>технические работы</h1><p class="text-muted-foreground text-lg">Мы скоро вернёмся</p><p class="text-muted-foreground text-sm mt-8">© СахГО · Сахалинская область</p></div></body></html><?php
    exit;
  }
}

switch ($page) {
  case '':
  case 'home':
    require __DIR__ . '/pages/home.php';
    break;
  case 'catalog':
    require __DIR__ . '/pages/catalog.php';
    break;
  case 'listing':
    require __DIR__ . '/pages/listing.php';
    break;
  case 'search':
    require __DIR__ . '/pages/search.php';
    break;
  case 'create':
    require __DIR__ . '/pages/create.php';
    break;
  case 'login':
    require __DIR__ . '/pages/login.php';
    break;
  case 'register':
    require __DIR__ . '/pages/register.php';
    break;
  case 'logout':
    auth_logout();
    header('Location: /');
    exit;
  case 'profile':
  case 'dashboard':
    require __DIR__ . '/pages/dashboard.php';
    break;
  case 'reset-password':
    require __DIR__ . '/pages/reset_password.php';
    break;
  case 'edit':
    require __DIR__ . '/pages/edit.php';
    break;
  case 'promote':
    require __DIR__ . '/pages/promote.php';
    break;
  case 'admin':
    require __DIR__ . '/pages/admin/index.php';
    break;
  case 'help':
    require __DIR__ . '/pages/help.php';
    break;
  case 'privacy':
    require __DIR__ . '/pages/privacy.php';
    break;
  case 'terms':
    require __DIR__ . '/pages/terms.php';
    break;
  default:
    http_response_code(404);
    $page_title = '404 — Страница не найдена — СахGO';
    require __DIR__ . '/includes/header.php';
    echo '<section class="py-20"><div class="max-w-7xl mx-auto px-4 text-center text-muted-foreground"><p class="text-lg">Страница не найдена</p><p class="text-sm mt-1 mb-4">Запрошенная страница не существует.</p><a href="/" class="btn-outline">На главную</a></div></section>';
    require __DIR__ . '/../includes/footer.php';
}
