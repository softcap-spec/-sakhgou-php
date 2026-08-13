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

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_notifs_read') {
  header('Content-Type: application/json');
  $user = auth_user();
  if (!$user) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }
  csrf_check();
  db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);
  echo json_encode(['ok'=>true]);
  exit;
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
  case 'api':
    // AJAX API for chat
    header('Content-Type: application/json; charset=utf-8');
    $cu = auth_user();
    if (!$cu) { echo json_encode(['error'=>'auth']); exit; }
    $action = $sub ?? '';
    $pdo = db();
    if ($action === 'messages' && $_SERVER['REQUEST_METHOD'] === 'GET') {
      // Get messages + other user info
      $lid = (int)($_GET['lid'] ?? 0);
      $other = (int)($_GET['uid'] ?? 0);
      $stmt = $pdo->prepare('SELECT *, 0 AS deleted FROM messages WHERE listing_id=? AND is_deleted=0 AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) ORDER BY created_at ASC');
      $stmt->execute([$lid,$cu['id'],$other,$other,$cu['id']]);
      $msgs = $stmt->fetchAll();
      // Mark as read
      $pdo->prepare('UPDATE messages SET is_read=1 WHERE listing_id=? AND receiver_id=? AND sender_id=? AND is_read=0')->execute([$lid,$cu['id'],$other]);
      // Other user info
      $ou = $pdo->prepare('SELECT name, avatar_url, last_seen FROM users WHERE id=?');
      $ou->execute([$other]);
      $otherUser = $ou->fetch();
      // Listing info
      $ll = $pdo->prepare('SELECT title, price, listing_type FROM listings WHERE id=?');
      $ll->execute([$lid]);
      $listingInfo = $ll->fetch();
      // Typing status
      $typing = $pdo->prepare('SELECT typing_lid, typing_at FROM users WHERE id=?');
      $typing->execute([$other]);
      $t = $typing->fetch();
      $isTyping = ($t && $t['typing_lid'] == $lid && time() - strtotime($t['typing_at']) < 8);
      echo json_encode([
        'messages' => $msgs,
        'other' => $otherUser ? ['name'=>$otherUser['name'],'avatar'=>$otherUser['avatar_url'],'last_seen'=>$otherUser['last_seen']] : null,
        'listing' => $listingInfo ? ['title'=>$listingInfo['title'],'price'=>(float)$listingInfo['price'],'type'=>$listingInfo['listing_type']] : null,
        'typing' => $isTyping
      ]);
      exit;
    }
    if ($action === 'typing' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $lid = (int)($_POST['lid'] ?? 0);
      if ($lid > 0) {
        $pdo->prepare('UPDATE users SET typing_lid=?, typing_at=NOW() WHERE id=?')->execute([$lid, $cu['id']]);
      }
      echo json_encode(['ok'=>true]);
      exit;
    }
    if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $lid = (int)($_POST['lid'] ?? 0);
      $text = trim($_POST['text'] ?? '');
      if ($lid > 0 && $text !== '') {
        // Find listing owner
        $stmt = $pdo->prepare('SELECT user_id, title FROM listings WHERE id=?');
        $stmt->execute([$lid]);
        $listing = $stmt->fetch();
        if ($listing) {
          // Determine receiver: if sender is owner, receiver is the other user (passed via POST)
          if ($listing['user_id'] == $cu['id']) {
            $other = (int)($_POST['uid'] ?? 0);
            if ($other <= 0 || $other == $cu['id']) { echo json_encode(['error'=>'invalid']); exit; }
            $receiver = $other;
          } else {
            $receiver = $listing['user_id'];
          }
          $pdo->prepare('INSERT INTO messages (listing_id,sender_id,receiver_id,text,is_read,created_at) VALUES (?,?,?,?,0,NOW())')->execute([$lid,$cu['id'],$receiver,$text]);
          $pdo->prepare('INSERT INTO notifications (user_id,type,text,link,is_read,created_at) VALUES (?,?,?,?,0,NOW())')->execute([$receiver,'message','Новое сообщение по объявлению «'.$listing['title'].'»','/listing/'.$lid]);
          // Email notification (throttled: one per conversation per hour)
          notify_new_message($cu['id'], $receiver, $lid, $listing['title']);
          echo json_encode(['ok'=>true]);
          exit;
        }
      }
      echo json_encode(['error'=>'invalid']);
      exit;
    }
    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $mid = (int)($_POST['mid'] ?? 0);
      if ($mid > 0) {
        $stmt = $pdo->prepare('UPDATE messages SET is_deleted=1 WHERE id=? AND sender_id=?');
        $stmt->execute([$mid, $cu['id']]);
        echo json_encode(['ok' => $stmt->rowCount() > 0]);
        exit;
      }
      echo json_encode(['ok'=>false, 'error'=>'invalid']);
      exit;
    }
    echo json_encode(['error'=>'unknown']);
    exit;
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
