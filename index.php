<?php
/**
 * сахгоу.рф — Front Controller / Router
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/version.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notify.php';
require_once __DIR__ . '/includes/robokassa.php';

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
// Fix: listing/edit/promote/seller use /listing/N format (id in parts[1])
if (in_array($page, ['listing', 'edit', 'promote', 'seller']) && empty($id)) { $id = $sub; $sub = null; }

// Maintenance mode check
if ($page !== 'admin' && $page !== 'login' && $page !== 'register' && $page !== 'api' && $page !== 'max-webhook' && $page !== 'robokassa') {
  $mcheck = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'maintenance'")->fetchColumn();
  $user = auth_user();
  if ($mcheck === '1' && (!$user || $user['role'] !== 'admin')) {
    require __DIR__ . '/includes/maintenance.php';
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
  case 'seller':
    require __DIR__ . '/pages/seller.php';
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
  case 'bind-contacts':
    require __DIR__ . '/pages/bind-contacts.php';
    break;
  case 'admin':
    require __DIR__ . '/pages/admin/index.php';
    break;
  case 'help':
    require __DIR__ . '/pages/help.php';
    break;
  case 'availability':
    // Занятые даты объявления (публично, для мини-календаря на странице объявления)
    header('Content-Type: application/json; charset=utf-8');
    $avlLid = (int)($_GET['lid'] ?? 0);
    $avlBusy = [];
    if ($avlLid > 0) {
      bookings_expire_pendings($avlLid);
      $avlSt = db()->prepare("SELECT check_in_date, check_out_date, status FROM bookings WHERE listing_id=? AND status IN ('pending','confirmed','blocked') AND check_out_date > CURDATE() ORDER BY check_in_date");
      $avlSt->execute([$avlLid]);
      foreach ($avlSt->fetchAll() as $avlRow) {
        $avlBusy[] = ['from'=>$avlRow['check_in_date'], 'to'=>$avlRow['check_out_date'], 'status'=>$avlRow['status']];
      }
    }
    echo json_encode(['busy'=>$avlBusy]);
    exit;

  case 'robokassa':
    // Уведомления и возвраты Robokassa. result — серверный callback (Пароль №2).
    $rkAction = $sub ?? '';
    if ($rkAction === 'result') {
      header('Content-Type: text/plain; charset=utf-8');
      $res = rk_verify_result($_POST['OutSum'] ?? '', $_POST['InvId'] ?? '', $_POST['SignatureValue'] ?? '');
      if (!$res) { echo 'bad sign'; exit; }
      rk_activate_payment($res[0], $res[1]);
      echo 'OK' . $res[0];
      exit;
    }
    if ($rkAction === 'success') {
      $res = rk_verify_success(
        $_POST['OutSum'] ?? ($_GET['OutSum'] ?? ''),
        $_POST['InvId'] ?? ($_GET['InvId'] ?? ''),
        $_POST['SignatureValue'] ?? ($_GET['SignatureValue'] ?? '')
      );
      if ($res) rk_activate_payment($res[0], $res[1]);
      header('Location: /dashboard?pay=ok');
      exit;
    }
    header('Location: /dashboard?pay=fail');
    exit;

  case 'max-webhook':
    // Вебхук «Макс» (Max Bot API): приём событий (message_created и др.)
    // Без сессии; подлинность — по заголовку X-Max-Bot-Api-Secret.
    $maxSecret = defined('MAX_WEBHOOK_SECRET') ? MAX_WEBHOOK_SECRET : '';
    $gotSecret = $_SERVER['HTTP_X_MAX_BOT_API_SECRET'] ?? '';
    if ($maxSecret === '' || !hash_equals($maxSecret, $gotSecret)) {
      http_response_code(403);
      echo 'forbidden';
      exit;
    }
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true) ?: [];
    try {
      // дебаг-лог последнего события (в БД, не в открытый файл)
      set_setting('max_webhook_debug', mb_substr($raw, 0, 2000));
      // Привязка пользователя по персональному коду: сообщение вида «сахгоу <код>»
      // Реальная структура Update: message.sender.user_id, message.body.text
      $uid = (int)($data['user_id'] ?? $data['message']['sender']['user_id'] ?? $data['sender']['user_id'] ?? 0);
      $mtext = $data['message']['body']['text'] ?? $data['message']['text'] ?? '';
      if (is_array($mtext)) $mtext = '';
      $mtext = (string)$mtext;
      if ($uid > 0 && preg_match('/сахгоу\s+(\d{4,8})/iu', $mtext, $mm)) {
        $code = $mm[1];
        $su = db()->prepare('SELECT id FROM users WHERE max_bind_code = ?');
        $su->execute([$code]);
        $siteUser = (int)$su->fetchColumn();
        if ($siteUser > 0) {
          // убираем прежнюю привязку этого Макс-аккаунта, затем привязываем к текущему юзеру
          db()->prepare('UPDATE users SET max_user_id = NULL WHERE max_user_id = ? AND id != ?')->execute([$uid, $siteUser]);
          db()->prepare('UPDATE users SET max_user_id = ? WHERE id = ?')->execute([$uid, $siteUser]);
        }
      }
    } catch (\Throwable $e) { /* тихо */ }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;

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
      $stmt = $pdo->prepare('SELECT * FROM messages WHERE listing_id=? AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) ORDER BY created_at ASC');
      $stmt->execute([$lid,$cu['id'],$other,$other,$cu['id']]);
      $msgs = $stmt->fetchAll();
      $hasConversation = count($msgs) > 0;
      // Mark as read (only if a conversation exists)
      if ($hasConversation) {
        $pdo->prepare('UPDATE messages SET is_read=1 WHERE listing_id=? AND receiver_id=? AND sender_id=? AND is_read=0')->execute([$lid,$cu['id'],$other]);
      }
      // Other user info — only expose profile if a conversation actually exists
      $otherUser = null;
      $isTyping = false;
      if ($hasConversation) {
        $ou = $pdo->prepare('SELECT name, avatar_url, last_seen FROM users WHERE id=?');
        $ou->execute([$other]);
        $otherUser = $ou->fetch();
        // Typing status
        $typing = $pdo->prepare('SELECT typing_lid, typing_at FROM users WHERE id=?');
        $typing->execute([$other]);
        $t = $typing->fetch();
        $isTyping = ($t && $t['typing_lid'] == $lid && time() - strtotime($t['typing_at']) < 8);
      }
      // Listing info
      $ll = $pdo->prepare('SELECT title, price, listing_type FROM listings WHERE id=?');
      $ll->execute([$lid]);
      $listingInfo = $ll->fetch();
      echo json_encode([
        'messages' => $msgs,
        'other' => $otherUser ? ['name'=>$otherUser['name'],'avatar'=>$otherUser['avatar_url'],'last_seen'=>$otherUser['last_seen']] : null,
        'listing' => $listingInfo ? ['title'=>$listingInfo['title'],'price'=>(float)$listingInfo['price'],'type'=>$listingInfo['listing_type']] : null,
        'typing' => $isTyping
      ]);
      exit;
    }
    if ($action === 'typing' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_check();
      if (!rate_limit('typing', 5)) { echo json_encode(['error'=>'rate_limit']); exit; }
      $lid = (int)($_POST['lid'] ?? 0);
      if ($lid > 0) {
        $pdo->prepare('UPDATE users SET typing_lid=?, typing_at=NOW() WHERE id=?')->execute([$lid, $cu['id']]);
      }
      echo json_encode(['ok'=>true]);
      exit;
    }
    if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_check();
      if (!rate_limit('send', 2)) { echo json_encode(['error'=>'rate_limit']); exit; }
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
            // Owner may only reply to someone who already messaged them on this listing
            $chk = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE listing_id=? AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))');
            $chk->execute([$lid,$cu['id'],$other,$other,$cu['id']]);
            if ((int)$chk->fetchColumn() === 0) { echo json_encode(['error'=>'invalid']); exit; }
            $receiver = $other;
          } else {
            $receiver = $listing['user_id'];
          }
          $pdo->prepare('INSERT INTO messages (listing_id,sender_id,receiver_id,text,is_read,created_at) VALUES (?,?,?,?,0,NOW())')->execute([$lid,$cu['id'],$receiver,$text]);
          $pdo->prepare('INSERT INTO notifications (user_id,type,text,link,is_read,created_at) VALUES (?,?,?,?,0,NOW())')->execute([$receiver,'message','Новое сообщение по объявлению «'.$listing['title'].'»','/listing/'.$lid]);
          // Email notification (throttled: one per conversation per hour)
          notify_new_message($cu['id'], $receiver, $lid, $listing['title']);
          // Уведомление в «Макс» (MVP — оператору)
          try { max_notify_message($receiver, $listing['title'], $cu['name'] ?? 'пользователь', $text); } catch (\Throwable $e) {}
          echo json_encode(['ok'=>true]);
          exit;
        }
      }
      echo json_encode(['error'=>'invalid']);
      exit;
    }
    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_check();
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
  case 'contacts':
    require __DIR__ . '/pages/contacts.php';
    break;
  default:
    http_response_code(404);
    $page_title = '404 — Страница не найдена — СахGO';
    require __DIR__ . '/includes/header.php';
    echo '<section class="py-20"><div class="max-w-7xl mx-auto px-4 text-center text-muted-foreground"><p class="text-lg">Страница не найдена</p><p class="text-sm mt-1 mb-4">Запрошенная страница не существует.</p><a href="/" class="btn-outline">На главную</a></div></section>';
    require __DIR__ . '/../includes/footer.php';
}
