<?php
/**
 * сахгоу.рф — Front Controller / Router
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$url = $_GET['url'] ?? '';
$url = rtrim($url, '/');
$parts = $url ? explode('/', $url) : [];

$page = $parts[0] ?? 'home';
$sub = $parts[1] ?? null;
$id = $parts[2] ?? null;
// Fix: listing/edit/promote use /listing/N format (id in parts[1])
if (in_array($page, ['listing', 'edit', 'promote']) && empty($id)) { $id = $sub; $sub = null; }

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
