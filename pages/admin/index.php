<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

admin_required();
$pdo = db();
$tab = $_GET['tab'] ?? 'dashboard';

// ── POST handlers ──

// Moderation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  csrf_check();
  
  if ($_POST['action'] === 'approve' && isset($_POST['id'])) {
    $pdo->prepare("UPDATE listings SET status = 'active' WHERE id = ?")->execute([(int)$_POST['id']]);
    $pdo->prepare("DELETE FROM notifications WHERE user_id = (SELECT user_id FROM listings WHERE id = ?) AND type = 'moderation'")->execute([(int)$_POST['id']]);
    header('Location: /admin?tab=moderation&ok=approved');
    exit;
  }
  
  if ($_POST['action'] === 'reject' && isset($_POST['id'])) {
    $reason = trim($_POST['reason'] ?? '');
    $pdo->prepare("UPDATE listings SET status = 'rejected' WHERE id = ?")->execute([(int)$_POST['id']]);
    $l = $pdo->prepare("SELECT user_id, title FROM listings WHERE id = ?");
    $l->execute([(int)$_POST['id']]);
    $listing = $l->fetch();
    if ($listing) {
      $pdo->prepare("INSERT INTO notifications (user_id, type, text, link) VALUES (?, 'moderation', ?, '/admin')")
        ->execute([$listing['user_id'], 'Объявление «' . $listing['title'] . '» отклонено: ' . $reason]);
    }
    header('Location: /admin?tab=moderation&ok=rejected');
    exit;
  }

  // Review moderation
  if ($_POST['action'] === 'moderate_review') {
    $rid = (int)$_POST['review_id'];
    $newStatus = (int)$_POST['moderated'];
    $pdo->prepare("UPDATE reviews SET moderated = ? WHERE id = ?")->execute([$newStatus, $rid]);
    header('Location: /admin?tab=reviews&ok=1');
    exit;
  }

  // Unblock listing
  if ($_POST['action'] === 'unblock' && isset($_POST['id'])) {
    $lid = (int)$_POST['id'];
    $pdo->prepare("UPDATE listings SET status = 'active' WHERE id = ?")->execute([$lid]);
    $ul = $pdo->prepare("SELECT user_id, title FROM listings WHERE id = ?");
    $ul->execute([$lid]);
    $ub = $ul->fetch();
    if ($ub) {
      $pdo->prepare("INSERT INTO notifications (user_id, type, text, link) VALUES (?, 'moderation', ?, ?)")
        ->execute([$ub['user_id'], 'Объявление «'.$ub['title'].'» разблокировано', '/listing/'.$lid]);
    }
    header('Location: /admin?tab=moderation&ok=unblocked');
    exit;
  }

  // User edit
  if ($_POST['action'] === 'edit_user') {
    $uid = (int)$_POST['user_id'];
    $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?")
      ->execute([trim($_POST['name']), trim($_POST['email']), trim($_POST['phone'] ?? ''), $uid]);
    header('Location: /admin?tab=users&ok=1');
    exit;
  }

  // User role change
  if ($_POST['action'] === 'change_role') {
    $uid = (int)$_POST['user_id'];
    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$_POST['role'], $uid]);
    header('Location: /admin?tab=users&ok=1');
    exit;
  }

  // User ban/unban
  if ($_POST['action'] === 'ban_user') {
    $uid = (int)$_POST['user_id'];
    $pdo->prepare("UPDATE users SET banned = CASE WHEN banned = 1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$uid]);
    header('Location: /admin?tab=users&ok=1');
    exit;
  }

  // User delete
  if ($_POST['action'] === 'delete_user') {
    $uid = (int)$_POST['user_id'];
    $u = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $u->execute([$uid]);
    $userRow = $u->fetch();
    if ($userRow && trim($_POST['confirm_name']) === $userRow['name']) {
      $pdo->prepare("DELETE FROM listings WHERE user_id = ?")->execute([$uid]);
      $pdo->prepare("DELETE FROM reviews WHERE user_id = ? OR host_id = ?")->execute([$uid, $uid]);
      $pdo->prepare("DELETE FROM favorites WHERE user_id = ?")->execute([$uid]);
      $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$uid]);
      $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")->execute([$uid, $uid]);
      $pdo->prepare("DELETE FROM bookings WHERE guest_id = ? OR host_id = ?")->execute([$uid, $uid]);
      $pdo->prepare("DELETE FROM promotions WHERE user_id = ?")->execute([$uid]);
      $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
    }
    header('Location: /admin?tab=users&ok=1');
    exit;
  }

  // Category
  if ($_POST['action'] === 'add_category') {
    $name = trim($_POST['name']);
    $slug = transliterate($name);
    $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
    header('Location: /admin?tab=categories&ok=1');
    exit;
  }
  if ($_POST['action'] === 'edit_category') {
    $cid = (int)$_POST['id'];
    $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?")->execute([trim($_POST['name']), $cid]);
    header('Location: /admin?tab=categories&ok=1');
    exit;
  }
  if ($_POST['action'] === 'delete_category') {
    $cid = (int)$_POST['id'];
    $pdo->prepare("UPDATE listings SET category_id = NULL WHERE category_id = ?")->execute([$cid]);
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$cid]);
    header('Location: /admin?tab=categories&ok=1');
    exit;
  }

  // Promo approve/reject
  if ($_POST['action'] === 'approve_promo') {
    $pid = (int)$_POST['id'];
    $promo = $pdo->prepare("SELECT p.*, l.title AS listing_title FROM promotions p JOIN listings l ON p.listing_id = l.id WHERE p.id = ?");
    $promo->execute([$pid]);
    $p = $promo->fetch();
    if ($p) {
      $pdo->prepare("UPDATE promotions SET status = 'active', payment_status = 'paid' WHERE id = ?")->execute([$pid]);
      $pdo->prepare("INSERT INTO notifications (user_id, type, text, link, is_read, created_at) VALUES (?,?,?,?,0,NOW())")->execute([$p['host_id'], 'promo', "Ваше продвижение «{$p['listing_title']}» одобрено и активировано!", '/dashboard']);
    }
    header('Location: /admin?tab=payments&ok=approved'); exit;
  }
  if ($_POST['action'] === 'reject_promo') {
    $pid = (int)$_POST['id'];
    $reason = trim($_POST['reason'] ?? '');
    $promo = $pdo->prepare("SELECT p.*, l.title AS listing_title FROM promotions p JOIN listings l ON p.listing_id = l.id WHERE p.id = ?");
    $promo->execute([$pid]);
    $p = $promo->fetch();
    if ($p) {
      $pdo->prepare("UPDATE promotions SET status = 'rejected', payment_status = 'rejected' WHERE id = ?")->execute([$pid]);
      $msg = "Ваше продвижение «{$p['listing_title']}» отклонено." . ($reason ? " Причина: $reason" : '');
      $pdo->prepare("INSERT INTO notifications (user_id, type, text, link, is_read, created_at) VALUES (?,?,?,?,0,NOW())")->execute([$p['host_id'], 'promo', $msg, '/dashboard']);
    }
    header('Location: /admin?tab=payments&ok=rejected'); exit;
  }
  if ($_POST['action'] === 'cancel_promo') {
    $pid = (int)$_POST['id'];
    $promo = $pdo->prepare("SELECT p.*, l.title AS listing_title FROM promotions p JOIN listings l ON p.listing_id = l.id WHERE p.id = ?");
    $promo->execute([$pid]);
    $p = $promo->fetch();
    if ($p) {
      $pdo->prepare("UPDATE promotions SET status = 'cancelled', payment_status = 'refunded' WHERE id = ?")->execute([$pid]);
      $pdo->prepare("INSERT INTO notifications (user_id, type, text, link, is_read, created_at) VALUES (?,?,?,?,0,NOW())")->execute([$p['host_id'], 'promo', "Продвижение «{$p['listing_title']}» отменено администратором.", '/dashboard']);
    }
    header('Location: /admin?tab=payments&ok=cancelled'); exit;
  }

  // Maintenance toggle
  if ($_POST['action'] === 'toggle_maintenance') {
    $current = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'maintenance'")->fetchColumn();
    $newVal = $current === '1' ? '0' : '1';
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance'")->execute([$newVal]);
    header('Location: /admin?tab=maintenance&ok=' . ($newVal === '1' ? 'on' : 'off'));
    exit;
  }

  // Banner: add
  if ($_POST['action'] === 'add_banner') {
    $bannerContent = $_POST['content'];
    if ($_POST['type'] === 'image' && !empty($_FILES['banner_image']['name']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime = finfo_file($finfo, $_FILES['banner_image']['tmp_name']);
      finfo_close($finfo);
      $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
      if (isset($extMap[$mime])) {
        $fn = 'banner_' . time() . '_' . random_int(100, 999) . '.' . $extMap[$mime];
        if (move_uploaded_file($_FILES['banner_image']['tmp_name'], UPLOAD_DIR . '/' . $fn)) {
          $bannerContent = '/uploads/' . $fn;
        }
      }
    }
    $pdo->prepare("INSERT INTO banners (title, type, content, link, placement, sort_order, is_active, is_ad, advertiser, advertiser_ogrn, advertiser_address, erid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
      ->execute([$_POST['title'], $_POST['type'], $bannerContent, $_POST['link'] ?: null, $_POST['placement'], (int)$_POST['sort_order'], isset($_POST['is_active']) ? 1 : 0, isset($_POST['is_ad']) ? 1 : 0, $_POST['advertiser'] ?: null, $_POST['advertiser_ogrn'] ?: null, $_POST['advertiser_address'] ?: null, $_POST['erid'] ?: null]);
    header('Location: /admin?tab=banners&ok=1');
    exit;
  }
  // Banner: edit
  if ($_POST['action'] === 'edit_banner') {
    $bannerContent = $_POST['content'];
    if ($_POST['type'] === 'image' && !empty($_FILES['banner_image']['name']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime = finfo_file($finfo, $_FILES['banner_image']['tmp_name']);
      finfo_close($finfo);
      $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
      if (isset($extMap[$mime])) {
        $fn = 'banner_' . time() . '_' . random_int(100, 999) . '.' . $extMap[$mime];
        if (move_uploaded_file($_FILES['banner_image']['tmp_name'], UPLOAD_DIR . '/' . $fn)) {
          $bannerContent = '/uploads/' . $fn;
        }
      }
    }
    $pdo->prepare("UPDATE banners SET title=?, type=?, content=?, link=?, placement=?, sort_order=?, is_active=?, is_ad=?, advertiser=?, advertiser_ogrn=?, advertiser_address=?, erid=? WHERE id=?")
      ->execute([$_POST['title'], $_POST['type'], $bannerContent, $_POST['link'] ?: null, $_POST['placement'], (int)$_POST['sort_order'], isset($_POST['is_active']) ? 1 : 0, isset($_POST['is_ad']) ? 1 : 0, $_POST['advertiser'] ?: null, $_POST['advertiser_ogrn'] ?: null, $_POST['advertiser_address'] ?: null, $_POST['erid'] ?: null, (int)$_POST['id']]);
    header('Location: /admin?tab=banners&ok=1');
    exit;
  }
  // Banner: delete
  if ($_POST['action'] === 'delete_banner') {
    $pdo->prepare("DELETE FROM banners WHERE id=?")->execute([(int)$_POST['id']]);
    header('Location: /admin?tab=banners&ok=1');
    exit;
  }
  // Banner: toggle
  if ($_POST['action'] === 'toggle_banner') {
    $pdo->prepare("UPDATE banners SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?")->execute([(int)$_POST['id']]);
    header('Location: /admin?tab=banners&ok=1');
    exit;
  }
  // Promo prices: save (пустое/некорректное поле не затирает прежнюю цену)
  if ($_POST['action'] === 'save_prices') {
    foreach (['top','highlight','urgent'] as $t) {
      foreach (['7','14','30'] as $d) {
        $raw = trim((string)($_POST['price_' . $t . '_' . $d] ?? ''));
        if ($raw === '') continue;
        if (!ctype_digit($raw) || (int)$raw < 1) continue;
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([(int)$raw, 'promo_' . $t . '_' . $d]);
      }
    }
    header('Location: /admin?tab=payments&ok=prices');
    exit;
  }
}

// ── Data loaders ──

// Stats
$total_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_listings = (int)$pdo->query("SELECT COUNT(*) FROM listings")->fetchColumn();
$active_listings = (int)$pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'active'")->fetchColumn();
$pending_listings = (int)$pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'pending'")->fetchColumn();
$unread_notifs = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = 0 AND is_read = 0")->fetchColumn();
$total_bookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$promo_revenue = (int)$pdo->query("SELECT COALESCE(SUM(payment_amount), 0) FROM promotions WHERE payment_status = 'paid'")->fetchColumn();
$new_week_users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$new_week_listings = (int)$pdo->query("SELECT COUNT(*) FROM listings WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// Search query
$user_search = $_GET['user_search'] ?? '';

// Maintenance status
$maint_status = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'maintenance'")->fetchColumn();
$maint_on = $maint_status === '1';

// ── Header ──
$page_title = 'Админ-панель — СахГО';
require_once __DIR__ . '/../../includes/header.php';
?>

<section class="py-6">
  <div class="max-w-7xl mx-auto px-4">
    <div class="flex items-center gap-3 mb-2">
      <h1 class="font-display text-2xl">Админ-панель</h1>
      <span class="text-xs bg-accent text-white px-2 py-0.5 rounded-full font-medium">v<?= defined('APP_VERSION') ? APP_VERSION : '1.0' ?></span>
    </div>

    <?php if (isset($_GET['ok'])): ?>
      <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 mb-4 text-sm">Выполнено</div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="border-b mb-6">
      <nav class="flex gap-0.5 flex-nowrap -mb-px">
        <?php
        $tabs = [
          'dashboard' => 'Дашборд',
          'listings' => 'Объявления',
          'moderation' => 'Модерация',
          'reviews' => 'Отзывы',
          'users' => 'Пользователи',
          'payments' => 'Платежи',
          'maintenance' => 'Техработы',
          'categories' => 'Категории',
          'banners' => 'Баннеры',
          'notifications' => 'Уведомления',
        ];
        foreach ($tabs as $k => $v):
          $active = $tab === $k;
        ?>
          <a href="?tab=<?= $k ?>" class="px-2.5 py-2 text-xs font-medium whitespace-nowrap border-b-2 transition-colors <?= $active ? 'border-accent text-accent' : 'border-transparent text-muted-foreground hover:text-foreground' ?>"><?= $v ?><?= $k === 'moderation' && $pending_listings ? ' <span class="bg-red-500 text-white text-[10px] px-1 py-0 rounded-full">'.$pending_listings.'</span>' : '' ?><?= $k === 'notifications' && $unread_notifs ? ' <span class="bg-red-500 text-white text-[10px] px-1 py-0 rounded-full">'.$unread_notifs.'</span>' : '' ?></a>
        <?php endforeach; ?>
      </nav>
    </div>

<?php
// ── DASHBOARD ──
if ($tab === 'dashboard'):
?>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-8">
      <div class="bg-white border rounded-xl p-4"><div class="text-2xl font-display"><?= $total_users ?></div><div class="text-xs text-muted-foreground mt-1">Пользователей</div></div>
      <div class="bg-white border rounded-xl p-4"><div class="text-2xl font-display"><?= $active_listings ?></div><div class="text-xs text-muted-foreground mt-1">Активных объявлений</div></div>
      <div class="bg-white border rounded-xl p-4"><div class="text-2xl font-display text-accent"><?= $pending_listings ?></div><div class="text-xs text-muted-foreground mt-1">На модерации</div></div>
      <div class="bg-white border rounded-xl p-4"><div class="text-2xl font-display"><?= $total_bookings ?></div><div class="text-xs text-muted-foreground mt-1">Бронирований</div></div>
      <div class="bg-white border rounded-xl p-4"><div class="text-2xl font-display text-green-600"><?= number_format($promo_revenue, 0, ',', ' ') ?> ₽</div><div class="text-xs text-muted-foreground mt-1">Доход от продвижения</div></div>
      <div class="bg-white border rounded-xl p-4"><div class="text-2xl font-display">+<?= $new_week_listings ?></div><div class="text-xs text-muted-foreground mt-1">Новых за 7 дней</div></div>
    </div>

    <h3 class="font-display text-lg mb-3">Последние объявления</h3>
    <div class="bg-white border rounded-xl overflow-hidden mb-8">
      <table class="w-full text-sm">
        <thead><tr class="border-b bg-muted/30"><th class="text-left px-4 py-3 font-medium text-muted-foreground">ID</th><th class="text-left px-4 py-3 font-medium text-muted-foreground">Название</th><th class="text-left px-4 py-3 font-medium text-muted-foreground hidden sm:table-cell">Тип</th><th class="text-left px-4 py-3 font-medium text-muted-foreground hidden sm:table-cell">Статус</th><th class="text-right px-4 py-3 font-medium text-muted-foreground hidden sm:table-cell">Дата</th></tr></thead>
        <tbody>
          <?php $recent = $pdo->query("SELECT * FROM listings ORDER BY created_at DESC LIMIT 10");
          while ($r = $recent->fetch()): ?>
            <tr class="border-b hover:bg-muted/20">
              <td class="px-4 py-3">#<?= $r['id'] ?></td>
              <td class="px-4 py-3 font-medium"><a href="/listing/<?= $r['id'] ?>" class="hover:text-accent"><?= h($r['title']) ?></a></td>
              <td class="px-4 py-3 text-xs text-muted-foreground hidden sm:table-cell"><?= h($r['listing_type']) ?></td>
              <td class="px-4 py-3 hidden sm:table-cell"><span class="text-xs px-2 py-0.5 rounded-full <?= $r['status']==='active'?'bg-green-100 text-green-700':($r['status']==='pending'?'bg-yellow-100 text-yellow-700':'bg-red-100 text-red-700') ?>"><?= h($r['status']) ?></span></td>
              <td class="px-4 py-3 text-xs text-muted-foreground text-right hidden sm:table-cell"><?= date('d.m.Y', strtotime($r['created_at'])) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

<?php
// ── LISTINGS ──
elseif ($tab === 'listings'):
  $page = max(1, (int)($_GET['p'] ?? 1));
  $per = 25;
  $offset = ($page - 1) * $per;
  $filter = $_GET['filter'] ?? 'all';
  $where = $filter === 'active' ? "WHERE l.status = 'active'" : ($filter === 'pending' ? "WHERE l.status = 'pending'" : ($filter === 'blocked' ? "WHERE l.status = 'blocked'" : ''));
  $countStmt = $pdo->query("SELECT COUNT(*) FROM listings l $where");
  $total = (int)$countStmt->fetchColumn();
  $pages = ceil($total / $per);
  $stmt = $pdo->query("SELECT l.*, u.name AS host_name, c.name AS cat_name FROM listings l JOIN users u ON l.user_id = u.id JOIN categories c ON l.category_id = c.id $where ORDER BY l.created_at DESC LIMIT $per OFFSET $offset");
  $allListings = $stmt->fetchAll();
  $filters = ['all'=>'Все','active'=>'Активные','pending'=>'На модерации','blocked'=>'Заблокированные'];
?>
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem">
    <h3 class="font-display text-lg" style="margin:0">Все объявления (<?=$total?>)</h3>
    <div style="display:flex;gap:0.25rem">
      <?php foreach($filters as $fk=>$fv): ?>
        <a href="?tab=listings&filter=<?=$fk?>" style="padding:0.375rem 0.75rem;font-size:0.75rem;border-radius:999px;text-decoration:none;border:1px solid <?=$filter===$fk?'#1B6B8A':'#DFE4EA'?>;color:<?=$filter===$fk?'#fff':'#3A4A5C'?>;background:<?=$filter===$fk?'#1B6B8A':'#fff'?>"><?=$fv?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="bg-white border rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead><tr class="border-b bg-muted/30"><th class="text-left px-4 py-3 font-medium text-muted-foreground">ID</th><th class="text-left px-4 py-3 font-medium text-muted-foreground">Название</th><th class="text-left px-4 py-3 font-medium text-muted-foreground hidden sm:table-cell">Категория</th><th class="text-left px-4 py-3 font-medium text-muted-foreground hidden sm:table-cell">Автор</th><th class="text-left px-4 py-3 font-medium text-muted-foreground hidden sm:table-cell">Статус</th><th class="text-right px-4 py-3 font-medium text-muted-foreground hidden sm:table-cell">Дата</th></tr></thead>
      <tbody>
        <?php foreach($allListings as $l): ?>
          <tr class="border-b hover:bg-muted/20">
            <td class="px-4 py-3">#<?=$l['id']?></td>
            <td class="px-4 py-3 font-medium"><a href="/listing/<?=$l['id']?>" class="hover:text-accent"><?=h($l['title'])?></a></td>
            <td class="px-4 py-3 hidden sm:table-cell text-muted-foreground"><?=h($l['cat_name'])?></td>
            <td class="px-4 py-3 hidden sm:table-cell"><?=h($l['host_name'])?></td>
            <td class="px-4 py-3 hidden sm:table-cell"><span style="font-size:0.6875rem;padding:0.125rem 0.5rem;border-radius:999px;<?=$l['status']==='active'?'color:#166534;background:#DCFCE7':($l['status']==='pending'?'color:#92400E;background:#FEF3C7':'color:#991B1B;background:#FEE2E2')?>"><?=$l['status']==='active'?'Активно':$l['status']?></span></td>
            <td class="px-4 py-3 hidden sm:table-cell text-right text-muted-foreground"><?=date('d.m.Y',strtotime($l['created_at']))?></td>
          </tr>
        <?php endforeach; ?>
        <?php if(empty($allListings)): ?>
          <tr><td colspan="6" class="px-4 py-8 text-center text-muted-foreground">Нет объявлений</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($pages > 1): ?>
  <div style="display:flex;justify-content:center;gap:0.25rem;margin-top:1rem;flex-wrap:wrap">
    <?php for($i=1;$i<=$pages;$i++): ?>
      <a href="?tab=listings&filter=<?=$filter?>&p=<?=$i?>" style="padding:0.375rem 0.75rem;font-size:0.8125rem;border-radius:8px;text-decoration:none;<?=$i===$page?'background:#121E2B;color:#fff':'border:1px solid #DFE4EA;color:#3A4A5C'?>"><?=$i?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

<?php
// ── MODERATION ──
elseif ($tab === 'moderation'):
  $pending = $pdo->query("SELECT l.*, u.name AS host_name, u.email AS host_email FROM listings l JOIN users u ON l.user_id = u.id WHERE l.status = 'pending' ORDER BY l.created_at DESC LIMIT 100");
?>
    <h2 class="font-display text-xl mb-4">Объявления на модерации (<?= $pending_listings ?>)</h2>
    <?php if ($pending->rowCount() === 0): ?>
      <p class="text-muted-foreground text-sm">Нет объявлений для проверки.</p>
    <?php else: ?>
      <div class="space-y-4">
        <?php while ($p = $pending->fetch()): ?>
          <div class="bg-white border rounded-xl p-4 md:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-3">
              <div class="flex-1">
                <h3 class="font-medium"><a href="/listing/<?= $p['id'] ?>" class="hover:text-accent"><?= h($p['title']) ?></a></h3>
                <p class="text-xs text-muted-foreground mt-1"><?= h($p['listing_type']) ?> · <?= h($p['host_name']) ?> (<?= h($p['host_email']) ?>) · <?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></p>
              </div>
              <div class="flex gap-2">
                <form method="post" class="inline"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $p['id'] ?>"><?= csrf_field() ?><button class="px-4 py-1.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">Одобрить</button></form>
                <button onclick="document.getElementById('reject-<?=$p['id']?>').classList.toggle('hidden')" class="px-4 py-1.5 bg-red-500 text-white rounded-lg text-sm font-medium hover:bg-red-600 transition-colors">Отклонить</button>
              </div>
            </div>
            <p class="text-sm text-muted-foreground line-clamp-3"><?= h($p['description'] ?? '') ?></p>
            <div id="reject-<?=$p['id']?>" class="hidden mt-3 pt-3 border-t">
              <form method="post" class="flex gap-2">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <?= csrf_field() ?>
                <input type="text" name="reason" placeholder="Причина отклонения..." class="flex-1 rounded-lg border border-border py-1.5 px-3 text-sm focus:border-red-400 outline-none">
                <button class="px-4 py-1.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">Отклонить</button>
              </form>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>

    <!-- Blocked / Rejected listings -->
    <h2 class="font-display text-xl mb-4 mt-8">Заблокированные объявления</h2>
    <?php
    $blocked = $pdo->query("SELECT l.*, u.name AS host_name, u.email AS host_email FROM listings l JOIN users u ON l.user_id = u.id WHERE l.status = 'rejected' ORDER BY l.created_at DESC LIMIT 100");
    ?>
    <?php if ($blocked->rowCount() === 0): ?>
      <p class="text-muted-foreground text-sm">Нет заблокированных объявлений.</p>
    <?php else: ?>
      <div class="space-y-3">
        <?php while ($b = $blocked->fetch()): ?>
          <div class="bg-white border border-red-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex-1 min-w-0">
              <div class="font-medium text-sm truncate"><a href="/listing/<?= $b['id'] ?>" class="hover:text-accent"><?= h($b['title']) ?></a></div>
              <p class="text-xs text-muted-foreground mt-0.5"><?= h($b['listing_type']) ?> · <?= h($b['host_name']) ?> · <?= date('d.m.Y H:i', strtotime($b['created_at'])) ?></p>
            </div>
            <form method="post" class="shrink-0">
              <input type="hidden" name="action" value="unblock">
              <input type="hidden" name="id" value="<?= $b['id'] ?>">
              <?= csrf_field() ?>
              <button class="px-4 py-1.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors">Разблокировать</button>
            </form>
          </div>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>

<?php
// ── REVIEWS ──
elseif ($tab === 'reviews'):
  $reviews = $pdo->query("SELECT r.*, l.title AS listing_title, u.name AS author_name FROM reviews r JOIN listings l ON r.listing_id = l.id JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC LIMIT 200");
?>
    <h2 class="font-display text-xl mb-4">Модерация отзывов</h2>
    <?php if ($reviews->rowCount() === 0): ?>
      <p class="text-muted-foreground text-sm">Нет отзывов.</p>
    <?php else: ?>
      <div class="bg-white border rounded-xl overflow-hidden">
        <table class="w-full text-sm">
          <thead><tr class="border-b bg-muted/30"><th class="px-4 py-3 text-left">Автор</th><th class="px-4 py-3 text-left hidden sm:table-cell">Объявление</th><th class="px-4 py-3 text-left">Отзыв</th><th class="px-4 py-3 text-center">⭐</th><th class="px-4 py-3 text-right">Действия</th></tr></thead>
          <tbody>
            <?php while ($rv = $reviews->fetch()): ?>
              <tr class="border-b hover:bg-muted/20">
                <td class="px-4 py-3 text-xs"><?= h($rv['author_name']) ?></td>
                <td class="px-4 py-3 text-xs hidden sm:table-cell"><a href="/listing/<?= $rv['listing_id'] ?>" class="hover:text-accent"><?= h($rv['listing_title']) ?></a></td>
                <td class="px-4 py-3 text-xs max-w-xs truncate"><?= h($rv['text']) ?></td>
                <td class="px-4 py-3 text-center"><?= str_repeat('⭐', (int)$rv['rating']) ?></td>
                <td class="px-4 py-3 text-right">
                  <form method="post" class="inline">
                    <input type="hidden" name="action" value="moderate_review"><input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="moderated" value="<?= $rv['moderated'] ? 0 : 1 ?>">
                    <button class="text-xs px-3 py-1 rounded-lg border transition-colors <?= $rv['moderated'] ? 'bg-green-50 border-green-300 text-green-700 hover:bg-green-100' : 'bg-muted border-border hover:bg-muted/70' ?>">
                      <?= $rv['moderated'] ? '✅ Одобрен' : '👁️ Одобрить' ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

<?php
// ── USERS ──
elseif ($tab === 'users'):
  $where = '';
  $params = [];
  if ($user_search) {
    $where = "WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?";
    $params = ["%$user_search%", "%$user_search%", "%$user_search%"];
  }
  $users = $pdo->prepare("SELECT * FROM users $where ORDER BY created_at DESC LIMIT 200");
  $users->execute($params);
?>
    <h2 class="font-display text-xl mb-4">Пользователи</h2>
    <form method="get" class="flex gap-2 mb-4">
      <input type="hidden" name="tab" value="users">
      <input type="text" name="user_search" value="<?= h($user_search) ?>" placeholder="Поиск по имени, email, телефону..." class="max-w-sm rounded-lg border border-border py-2 px-3 text-sm focus:border-accent outline-none">
      <button class="px-4 py-2 bg-accent text-white rounded-lg text-sm font-medium hover:opacity-90 transition-all">Найти</button>
    </form>
    <div class="bg-white border rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead><tr class="border-b bg-muted/30"><th class="px-4 py-3 text-left">Пользователь</th><th class="px-4 py-3 text-left hidden sm:table-cell">Email</th><th class="px-4 py-3 text-center hidden sm:table-cell">Роль</th><th class="px-4 py-3 text-center">Активен</th><th class="px-4 py-3 text-right">Действия</th></tr></thead>
        <tbody>
          <?php while ($u = $users->fetch()): ?>
            <tr class="border-b hover:bg-muted/20">
              <td class="px-4 py-3 font-medium"><?= h($u['name']) ?></td>
              <td class="px-4 py-3 text-xs text-muted-foreground hidden sm:table-cell"><?= h($u['email']) ?></td>
              <td class="px-4 py-3 text-center hidden sm:table-cell">
                <span class="text-xs px-2 py-0.5 rounded-full <?= $u['role']==='admin'?'bg-purple-600 text-white':($u['role']==='host'?'bg-accent text-white':'bg-muted text-muted-foreground') ?>"><?= h($u['role']) ?></span>
              </td>
              <td class="px-4 py-3 text-center"><?= $u['banned'] ? '🚫' : '✅' ?></td>
              <td class="px-4 py-3 text-right">
                <div class="flex gap-1 justify-end flex-wrap">
                  <button onclick="openEditUser(<?=$u['id']?>,'<?= addslashes(h($u['name'])) ?>','<?= addslashes(h($u['email'])) ?>','<?= addslashes(h($u['phone']??'')) ?>')" class="text-xs px-2 py-1 border border-border rounded-lg hover:bg-muted transition-colors">Ред.</button>
                  <button onclick="openRoleUser(<?=$u['id']?>,'<?= h($u['role']) ?>')" class="text-xs px-2 py-1 border border-border rounded-lg hover:bg-muted transition-colors">Роль</button>
                  <form method="post" class="inline"><input type="hidden" name="action" value="ban_user"><input type="hidden" name="user_id" value="<?=$u['id']?>"><?= csrf_field() ?><button class="text-xs px-2 py-1 border border-border rounded-lg hover:bg-muted transition-colors"><?=$u['banned']?'Разблок':'Блок'?></button></form>
                  <button onclick="openDeleteUser(<?=$u['id']?>,'<?= addslashes(h($u['name'])) ?>')" class="text-xs px-2 py-1 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors">Удалить</button>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
      <div class="fixed inset-0 bg-black/50" onclick="closeModal('editUserModal')"></div>
      <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-6">
        <h3 class="font-display text-lg mb-4">Редактировать пользователя</h3>
        <form method="post" class="space-y-3">
          <input type="hidden" name="action" value="edit_user"><input type="hidden" name="user_id" id="editUserId"><?= csrf_field() ?>
          <div><label class="block text-xs font-medium mb-1">Имя</label><input type="text" name="name" id="editUserName" required class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent outline-none"></div>
          <div><label class="block text-xs font-medium mb-1">Email</label><input type="email" name="email" id="editUserEmail" required class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent outline-none"></div>
          <div><label class="block text-xs font-medium mb-1">Телефон</label><input type="text" name="phone" id="editUserPhone" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent outline-none"></div>
          <div class="flex gap-2 pt-2"><button type="button" onclick="closeModal('editUserModal')" class="flex-1 py-2 border border-border rounded-lg text-sm font-medium hover:bg-muted transition-colors">Отмена</button><button class="flex-1 py-2 bg-accent text-white rounded-lg text-sm font-medium hover:opacity-90 transition-all">Сохранить</button></div>
        </form>
      </div>
    </div>

    <!-- Role Modal -->
    <div id="roleUserModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
      <div class="fixed inset-0 bg-black/50" onclick="closeModal('roleUserModal')"></div>
      <div class="relative bg-white rounded-2xl shadow-xl max-w-xs w-full mx-4 p-6">
        <h3 class="font-display text-lg mb-4">Сменить роль</h3>
        <form method="post" class="space-y-3">
          <input type="hidden" name="action" value="change_role"><input type="hidden" name="user_id" id="roleUserId"><?= csrf_field() ?>
          <select name="role" id="roleSelect" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent outline-none bg-white">
            <option value="traveler">Путешественник</option><option value="host">Организатор</option><option value="vendor">Продавец</option><option value="admin">Администратор</option>
          </select>
          <div class="flex gap-2 pt-2"><button type="button" onclick="closeModal('roleUserModal')" class="flex-1 py-2 border border-border rounded-lg text-sm font-medium hover:bg-muted transition-colors">Отмена</button><button class="flex-1 py-2 bg-accent text-white rounded-lg text-sm font-medium hover:opacity-90 transition-all">Сохранить</button></div>
        </form>
      </div>
    </div>

    <!-- Delete User Modal -->
    <div id="deleteUserModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
      <div class="fixed inset-0 bg-black/50" onclick="closeModal('deleteUserModal')"></div>
      <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-6">
        <h3 class="font-display text-lg mb-2">Удаление пользователя</h3>
        <p class="text-sm text-red-600 bg-red-50 rounded-lg p-3 mb-4">Это действие необратимо. Все объявления, бронирования и данные будут удалены.</p>
        <form method="post" class="space-y-3">
          <input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" id="deleteUserId"><?= csrf_field() ?>
          <div>
            <label class="block text-xs font-medium mb-1">Для подтверждения введите имя: <strong id="deleteUserNameLabel"></strong></label>
            <input type="text" name="confirm_name" id="deleteConfirmInput" required class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-red-400 outline-none">
          </div>
          <div class="flex gap-2 pt-2"><button type="button" onclick="closeModal('deleteUserModal')" class="flex-1 py-2 border border-border rounded-lg text-sm font-medium hover:bg-muted transition-colors">Отмена</button><button class="flex-1 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-all">Удалить</button></div>
        </form>
      </div>
    </div>

<?php
// ── PAYMENTS ──
elseif ($tab === 'payments'):
  $promoPrices = get_promo_prices();
  $promos = $pdo->query("SELECT p.*, u.name AS host_name, l.title AS listing_title FROM promotions p JOIN users u ON p.host_id = u.id JOIN listings l ON p.listing_id = l.id ORDER BY p.created_at DESC LIMIT 200");
?>
    <h2 class="font-display text-xl mb-4">Платные услуги / Продвижение</h2>

    <!-- Price Editor -->
    <div class="bg-white border rounded-xl p-6 mb-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-display text-lg">Настройка цен (₽ за пакет)</h3>
        <?php if (isset($_GET['ok']) && $_GET['ok']==='prices'): ?>
          <span class="text-xs text-green-600">✓ Сохранено</span>
        <?php endif; ?>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_prices">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr class="border-b"><th class="px-3 py-2 text-left">Тариф</th><th class="px-3 py-2 text-center">7 дней</th><th class="px-3 py-2 text-center">14 дней</th><th class="px-3 py-2 text-center">30 дней</th></tr></thead>
            <tbody>
              <?php
              $editDefs = [
                ['top','🔝 Top'],
                ['highlight','💡 Highlight'],
                ['urgent','⚡ Срочно'],
              ];
              foreach ($editDefs as $ed): ?>
                <tr class="border-b">
                  <td class="px-3 py-3 font-medium"><?=$ed[1]?></td>
                  <?php foreach (['7','14','30'] as $d): ?>
                    <td class="px-3 py-2 text-center">
                      <input type="number" name="price_<?=$ed[0]?>_<?=$d?>" value="<?=$promoPrices[$ed[0]][$d]?>" min="1" class="w-24 border rounded-lg px-3 py-2 text-sm text-center">
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button type="submit" class="btn-accent text-sm py-2 px-6 mt-4">Сохранить</button>
      </form>
    </div>

    <!-- Promo Requests -->
    <div class="bg-white border rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead><tr class="border-b bg-muted/30"><th class="px-4 py-3 text-left">ID</th><th class="px-4 py-3 text-left">Пользователь</th><th class="px-4 py-3 text-left hidden sm:table-cell">Объявление</th><th class="px-4 py-3 text-center hidden sm:table-cell">Тип</th><th class="px-4 py-3 text-right hidden sm:table-cell">Сумма</th><th class="px-4 py-3 text-center">Статус</th><th class="px-4 py-3 text-right hidden sm:table-cell">Дата</th><th class="px-4 py-3 text-center">Действия</th></tr></thead>
        <tbody>
          <?php while ($pm = $promos->fetch()): ?>
            <tr class="border-b hover:bg-muted/20">
              <td class="px-4 py-3 text-xs">#<?=$pm['id']?></td>
              <td class="px-4 py-3 text-xs"><?=h($pm['host_name'])?></td>
              <td class="px-4 py-3 text-xs hidden sm:table-cell"><a href="/listing/<?=$pm['listing_id']?>" class="hover:text-accent"><?=h($pm['listing_title'])?></a></td>
              <td class="px-4 py-3 text-center hidden sm:table-cell">
                <span class="text-xs px-2 py-0.5 rounded-full <?= $pm['promo_type']==='top' ? 'bg-amber-500 text-white' : ($pm['promo_type']==='highlight' ? 'bg-accent text-white' : 'bg-red-500 text-white') ?>"><?=h($pm['promo_type'])?></span>
              </td>
              <td class="px-4 py-3 text-right text-xs hidden sm:table-cell"><?=number_format((float)$pm['payment_amount'],0,',',' ')?> ₽</td>
              <td class="px-4 py-3 text-center"><span class="text-xs px-2 py-0.5 rounded-full <?= $pm['payment_status']==='paid' ? 'bg-green-100 text-green-700' : ($pm['payment_status']==='refunded' ? 'bg-gray-100 text-gray-600' : 'bg-yellow-100 text-yellow-700') ?>"><?=h($pm['payment_status'])?></span></td>
              <td class="px-4 py-3 text-xs text-muted-foreground text-right hidden sm:table-cell"><?=date('d.m.Y', strtotime($pm['created_at']))?></td>
              <td class="px-4 py-3 text-center">
                <?php if ($pm['payment_status'] === 'pending'): ?>
                  <div class="flex items-center justify-center gap-1">
                    <form method="post" class="inline" onsubmit="return confirm('Одобрить продвижение?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="approve_promo">
                      <input type="hidden" name="id" value="<?=$pm['id']?>">
                      <button class="text-xs bg-green-600 text-white px-2 py-1 rounded hover:bg-green-700">Одобрить</button>
                    </form>
                    <button class="text-xs bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600" onclick="rejectPromo(<?=$pm['id']?>)">Отклонить</button>
                  </div>
                <?php elseif ($pm['payment_status'] === 'paid'): ?>
                  <form method="post" class="inline" onsubmit="return confirm('Отменить оплаченное продвижение?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel_promo">
                    <input type="hidden" name="id" value="<?=$pm['id']?>">
                    <button class="text-xs bg-orange-500 text-white px-2 py-1 rounded hover:bg-orange-600">Отменить</button>
                  </form>
                <?php else: ?>
                  <span class="text-xs text-muted-foreground">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
  <div class="bg-white rounded-xl p-6 w-full max-w-sm mx-4">
    <h3 class="font-display text-lg mb-3">Причина отказа</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reject_promo">
      <input type="hidden" name="id" id="rejectPromoId">
      <textarea name="reason" rows="2" class="w-full border rounded-lg p-2 text-sm" placeholder="Необязательно"></textarea>
      <div class="flex gap-2 mt-3">
        <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" class="flex-1 btn-outline text-sm py-2">Отмена</button>
        <button type="submit" class="flex-1 bg-red-500 text-white rounded-lg text-sm py-2 hover:bg-red-600">Отклонить</button>
      </div>
    </form>
  </div>
</div>
<script>function rejectPromo(id){document.getElementById('rejectPromoId').value=id;document.getElementById('rejectModal').classList.remove('hidden');}</script>

<?php
// ── MAINTENANCE ──
elseif ($tab === 'maintenance'):
?>
    <div class="bg-white border rounded-xl p-8 max-w-lg mx-auto text-center space-y-6">
      <div class="mx-auto w-16 h-16 rounded-2xl <?= $maint_on ? 'bg-red-100' : 'bg-amber-100' ?> flex items-center justify-center">
        <span class="text-3xl"><?= $maint_on ? '🚫' : '🔧' ?></span>
      </div>
      <div>
        <h2 class="font-display text-xl mb-2">Режим техработ</h2>
        <p class="text-sm text-muted-foreground">
          Включает заглушку для всех посетителей.<br>Админка и API продолжают работать.
        </p>
      </div>

      <?php if (isset($_GET['ok'])): ?>
        <div class="text-sm text-green-600 font-medium animate-pulse">Режим техработ <?= $_GET['ok']==='on' ? 'ВКЛЮЧЕН' : 'ВЫКЛЮЧЕН' ?></div>
      <?php endif; ?>

      <div class="flex flex-col items-center gap-3">
        <div class="flex items-center gap-3">
          <span class="text-sm <?= $maint_on ? 'text-red-600 font-medium' : 'text-muted-foreground' ?>"><?= $maint_on ? '🔴 ВКЛЮЧЕНЫ' : '⚪ ВЫКЛЮЧЕНЫ' ?></span>
        </div>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle_maintenance">
          <button class="px-8 py-3 rounded-xl text-sm font-medium transition-all <?= $maint_on ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-red-500 text-white hover:bg-red-600' ?>">
            <?= $maint_on ? 'Выключить техработы' : 'Включить техработы' ?>
          </button>
        </form>
      </div>

      <div class="bg-muted/50 rounded-lg p-3 font-mono text-xs text-muted-foreground">
        SSH: ssh alex@192.168.85.87<br>
        <code class="text-accent">docker compose ps</code> · <code class="text-accent">docker compose logs app</code>
      </div>
    </div>

<?php
// ── CATEGORIES ──
elseif ($tab === 'categories'):
  $cats = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM listings WHERE category_id = c.id) AS cnt FROM categories c ORDER BY c.id")->fetchAll();
?>
    <h2 class="font-display text-xl mb-4">Управление категориями</h2>

    <form method="post" class="flex gap-2 mb-4">
      <input type="hidden" name="action" value="add_category"><?= csrf_field() ?>
      <input type="text" name="name" placeholder="Название новой категории" required class="max-w-sm rounded-lg border border-border py-2 px-3 text-sm focus:border-accent outline-none">
      <button class="px-4 py-2 bg-accent text-white rounded-lg text-sm font-medium hover:opacity-90 transition-all">Добавить</button>
    </form>

    <div class="bg-white border rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead><tr class="border-b bg-muted/30"><th class="px-4 py-3 text-left">Название</th><th class="px-4 py-3 text-left hidden sm:table-cell">Slug</th><th class="px-4 py-3 text-center hidden sm:table-cell">Объявлений</th><th class="px-4 py-3 text-right">Действия</th></tr></thead>
        <tbody>
          <?php foreach ($cats as $c): ?>
            <tr class="border-b hover:bg-muted/20">
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="w-6 h-6 bg-accent rounded flex items-center justify-center text-[10px] text-white font-semibold"><?= mb_substr($c['name'], 0, 1) ?></div>
                  <span class="font-medium" id="cat-name-<?=$c['id']?>"><?= h($c['name']) ?></span>
                </div>
              </td>
              <td class="px-4 py-3 text-xs text-muted-foreground font-mono hidden sm:table-cell"><?= h($c['slug']) ?></td>
              <td class="px-4 py-3 text-center text-muted-foreground hidden sm:table-cell"><?= (int)$c['cnt'] ?></td>
              <td class="px-4 py-3 text-right">
                <div class="flex gap-1 justify-end">
                  <button onclick="editCat(<?=$c['id']?>,'<?= addslashes(h($c['name'])) ?>')" class="text-xs px-2 py-1 border border-border rounded-lg hover:bg-muted transition-colors">Ред.</button>
                  <form method="post" class="inline"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?=$c['id']?>"><?= csrf_field() ?><button onclick="return confirm('Удалить категорию «<?= addslashes(h($c['name'])) ?>»?')" class="text-xs px-2 py-1 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors">Удалить</button></form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Edit Cat Modal -->
    <div id="editCatModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
      <div class="fixed inset-0 bg-black/50" onclick="closeModal('editCatModal')"></div>
      <div class="relative bg-white rounded-2xl shadow-xl max-w-sm w-full mx-4 p-6">
        <h3 class="font-display text-lg mb-4">Редактировать категорию</h3>
        <form method="post" class="space-y-3">
          <input type="hidden" name="action" value="edit_category"><input type="hidden" name="id" id="editCatId"><?= csrf_field() ?>
          <input type="text" name="name" id="editCatName" required class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent outline-none">
          <div class="flex gap-2 pt-2"><button type="button" onclick="closeModal('editCatModal')" class="flex-1 py-2 border border-border rounded-lg text-sm font-medium hover:bg-muted transition-colors">Отмена</button><button class="flex-1 py-2 bg-accent text-white rounded-lg text-sm font-medium hover:opacity-90 transition-all">Сохранить</button></div>
        </form>
      </div>
    </div>

<?php
// ── BANNERS ──
elseif ($tab === 'banners'):
  $banners = $pdo->query("SELECT * FROM banners ORDER BY sort_order, id")->fetchAll();
  $edit_banner = null;
  if (isset($_GET['edit'])) {
    $eb = $pdo->prepare("SELECT * FROM banners WHERE id=?");
    $eb->execute([(int)$_GET['edit']]);
    $edit_banner = $eb->fetch();
  }
?>
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-display text-xl">Баннеры</h2>
      <button onclick="document.getElementById('bannerForm').classList.toggle('hidden')" class="cta-btn text-sm">
        <?= $edit_banner ? '✎ Редактировать' : '+ Добавить баннер' ?>
      </button>
    </div>

    <div id="bannerForm" class="<?= $edit_banner ? '' : 'hidden' ?> bg-white border rounded-xl p-6 mb-6">
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $edit_banner ? 'edit_banner' : 'add_banner' ?>">
        <?php if ($edit_banner): ?><input type="hidden" name="id" value="<?= $edit_banner['id'] ?>"><?php endif; ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Название</label>
            <input name="title" value="<?= $edit_banner ? h($edit_banner['title']) : '' ?>" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-accent">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Тип</label>
            <select name="type" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-accent">
              <option value="image" <?= $edit_banner && $edit_banner['type']==='image' ? 'selected' : '' ?>>Изображение</option>
              <option value="code" <?= $edit_banner && $edit_banner['type']==='code' ? 'selected' : '' ?>>HTML-код</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1"><?= ($edit_banner && $edit_banner['type']==='code') ? 'HTML-код' : 'URL изображения' ?></label>
            <textarea name="content" rows="4" class="w-full border rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:border-accent" placeholder="<?= ($edit_banner && $edit_banner['type']==='code') ? '<div>...' : '/uploads/banner.jpg' ?>"><?= $edit_banner ? h($edit_banner['content']) : '' ?></textarea>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Или загрузить изображение (JPG, PNG, WebP, GIF)</label>
            <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp,image/gif" class="w-full border rounded-lg px-3 py-2 text-sm">
            <?php if ($edit_banner && $edit_banner['type'] === 'image' && !empty($edit_banner['content']) && $edit_banner['content'] !== 'code'): ?>
            <img src="<?= h($edit_banner['content']) ?>" alt="" style="height:3.5rem;margin-top:0.5rem;border-radius:8px">
            <?php endif; ?>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Ссылка (необязательно)</label>
            <input name="link" value="<?= $edit_banner ? h($edit_banner['link'] ?? '') : '' ?>" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-accent" placeholder="/catalog/...">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Размещение</label>
            <select name="placement" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-accent">
              <?php $pls = ['home_hero_bottom'=>'Главная: под Hero','home_picks_bottom'=>'Главная: под подборками','home_listings_top'=>'Главная: над объявлениями','home_listings_inline'=>'Главная: в сетке объявлений','home_listings_bottom'=>'Главная: под объявлениями','catalog_top'=>'Каталог: сверху','catalog_sidebar'=>'Каталог: сбоку','listing_sidebar'=>'Объявление: сбоку'];
              foreach ($pls as $pv=>$pl): ?>
              <option value="<?=$pv?>" <?= ($edit_banner && $edit_banner['placement']===$pv) ? 'selected' : '' ?>><?=$pl?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="flex items-end gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Порядок</label>
              <input name="sort_order" type="number" value="<?= $edit_banner ? (int)$edit_banner['sort_order'] : 0 ?>" class="w-20 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-accent">
            </div>
            <label class="flex items-center gap-2 text-sm pb-2">
              <input type="checkbox" name="is_active" <?= (!$edit_banner || $edit_banner['is_active']) ? 'checked' : '' ?> class="rounded"> Активен
            </label>
          </div>
        </div>
        <div class="border-t pt-4 mt-4">
          <p class="text-sm font-medium mb-2">Маркировка рекламы (закон «О рекламе»)</p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" name="is_ad" <?= ($edit_banner && $edit_banner['is_ad']) ? 'checked' : '' ?> class="rounded"> Это реклама
            </label>
            <input name="advertiser" value="<?= $edit_banner ? h($edit_banner['advertiser'] ?? '') : '' ?>" placeholder="Рекламодатель (название)" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-accent">
            <input name="advertiser_ogrn" value="<?= $edit_banner ? h($edit_banner['advertiser_ogrn'] ?? '') : '' ?>" placeholder="ОГРН рекламодателя" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-accent">
            <input name="advertiser_address" value="<?= $edit_banner ? h($edit_banner['advertiser_address'] ?? '') : '' ?>" placeholder="Место нахождения" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-accent">
            <input name="erid" value="<?= $edit_banner ? h($edit_banner['erid'] ?? '') : '' ?>" placeholder="erid (токен)" class="border rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:border-accent">
          </div>
        </div>
        <div class="flex gap-2 mt-4">
          <button type="submit" class="cta-btn text-sm"><?= $edit_banner ? 'Сохранить' : 'Добавить' ?></button>
          <?php if ($edit_banner): ?>
          <a href="?tab=banners" class="px-4 py-2 text-sm border rounded-lg hover:bg-muted">Отмена</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <?php if (empty($banners)): ?>
    <div class="bg-white border rounded-xl p-8 text-center text-muted-foreground">
      <p class="text-4xl mb-2">🪧</p>
      <p>Баннеров пока нет. Добавьте первый.</p>
    </div>
    <?php else: ?>
    <div class="bg-white border rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead><tr class="border-b bg-muted/30">
          <th class="text-left px-4 py-3">ID</th><th class="text-left px-4 py-3">Название</th><th class="text-left px-4 py-3">Тип</th>
          <th class="text-left px-4 py-3 hidden sm:table-cell">Размещение</th><th class="text-center px-4 py-3">Активен</th><th class="text-center px-4 py-3">Реклама</th>
          <th class="text-right px-4 py-3">Действия</th>
        </tr></thead>
        <tbody>
          <?php foreach ($banners as $b): ?>
          <tr class="border-b hover:bg-muted/20">
            <td class="px-4 py-3">#<?=$b['id']?></td>
            <td class="px-4 py-3 font-medium"><?=h($b['title'])?></td>
            <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full <?=$b['type']==='code'?'bg-purple-600 text-white':'bg-accent text-white'?>"><?=$b['type']==='code'?'HTML':'IMG'?></span></td>
            <td class="px-4 py-3 text-xs text-muted-foreground hidden sm:table-cell"><?=h($b['placement'])?></td>
            <td class="px-4 py-3 text-center">
              <form method="post" class="inline"><input type="hidden" name="action" value="toggle_banner"><input type="hidden" name="id" value="<?=$b['id']?>"><button type="submit" class="text-lg"><?=$b['is_active']?'🟢':'🔴'?></button></form>
            </td>
            <td class="px-4 py-3 text-right space-x-2">
              <a href="?tab=banners&edit=<?=$b['id']?>" class="text-accent hover:underline text-xs">Ред.</a>
              <form method="post" class="inline" onsubmit="return confirm('Удалить баннер?')"><input type="hidden" name="action" value="delete_banner"><input type="hidden" name="id" value="<?=$b['id']?>"><button type="submit" class="text-red-500 hover:underline text-xs">Удалить</button></form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

<?php
// ── REVISIONS ──
elseif ($tab === 'content'):
  // Try to get git log
  $revisions = [];
  $git_log = @shell_exec('cd ' . escapeshellarg(dirname(dirname(__DIR__))) . ' && git log --oneline -20 2>/dev/null');
  if ($git_log) {
    foreach (explode("
", trim($git_log)) as $line) {
      if (preg_match('/^([a-f0-9]+)\s+(.+)$/', $line, $m)) {
        $revisions[] = ['hash' => $m[1], 'message' => $m[2]];
      }
    }
  }
?>
    <h2 class="font-display text-xl mb-4">Ревизии</h2>
    <?php if (!empty($revisions)): ?>
    <div class="bg-white border rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead><tr class="border-b bg-muted/30">
          <th class="text-left px-4 py-3">#</th><th class="text-left px-4 py-3">Коммит</th><th class="text-left px-4 py-3">Описание</th>
        </tr></thead>
        <tbody>
          <?php $rn = count($revisions); foreach ($revisions as $i => $r): ?>
          <tr class="border-b hover:bg-muted/20">
            <td class="px-4 py-3 text-muted-foreground"><?=$rn - $i?></td>
            <td class="px-4 py-3 font-mono text-xs"><?=substr($r['hash'],0,7)?></td>
            <td class="px-4 py-3"><?=h($r['message'])?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="text-xs text-muted-foreground mt-2">Коммитов: <?=count($revisions)?>. Данные из git-репозитория на сервере.</p>
    <?php else: ?>
    <div class="bg-white border rounded-xl p-8 text-center text-muted-foreground">
      <p class="text-4xl mb-2">📝</p>
      <p>История ревизий недоступна (git не найден на сервере)</p>
    </div>
    <?php endif; ?>

<?php elseif ($tab === 'notifications'): ?>
    <h2 class="font-display text-xl mb-4">Уведомления</h2>
    <?php
    // Mark all as read if requested
    if (isset($_GET['mark_read'])) {
      $pdo->exec("UPDATE notifications SET is_read = 1 WHERE user_id = 0");
      header('Location: /admin?tab=notifications'); exit;
    }
    $notifs = $pdo->query("SELECT * FROM notifications WHERE user_id = 0 ORDER BY created_at DESC LIMIT 50");
    ?>
    <?php if ($notifs->rowCount() === 0): ?>
      <div class="text-center py-12 bg-white border rounded-xl">
        <p class="text-4xl mb-2">🔔</p>
        <p class="text-muted-foreground">Нет уведомлений</p>
      </div>
    <?php else: ?>
      <div class="flex justify-end mb-3">
        <a href="?tab=notifications&mark_read=1" class="text-xs text-accent hover:underline">Отметить все прочитанными</a>
      </div>
      <div class="space-y-2">
        <?php while ($n = $notifs->fetch()): ?>
          <div class="bg-white border rounded-xl p-4 flex items-start gap-3 <?= $n['is_read'] ? '' : 'border-l-4 border-l-accent' ?>">
            <div class="flex-1 min-w-0">
              <div class="text-sm"><?= h($n['text']) ?></div>
              <div class="text-xs text-muted-foreground mt-1"><?= date('d.m.Y H:i', strtotime($n['created_at'])) ?></div>
            </div>
            <?php if ($n['link']): ?>
              <a href="<?= h($n['link']) ?>" class="text-xs text-accent hover:underline shrink-0">Перейти</a>
            <?php endif; ?>
          </div>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>

<?php endif; ?>
  </div>
</section>

<script>
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
function openEditUser(id, name, email, phone) { document.getElementById('editUserId').value = id; document.getElementById('editUserName').value = name; document.getElementById('editUserEmail').value = email; document.getElementById('editUserPhone').value = phone; document.getElementById('editUserModal').classList.remove('hidden'); }
function openRoleUser(id, role) { document.getElementById('roleUserId').value = id; document.getElementById('roleSelect').value = role; document.getElementById('roleUserModal').classList.remove('hidden'); }
function openDeleteUser(id, name) { document.getElementById('deleteUserId').value = id; document.getElementById('deleteUserNameLabel').innerText = name; document.getElementById('deleteUserModal').classList.remove('hidden'); }
function editCat(id, name) { document.getElementById('editCatId').value = id; document.getElementById('editCatName').value = name; document.getElementById('editCatModal').classList.remove('hidden'); }
document.querySelectorAll('.fixed.inset-0.z-50').forEach(function(m) { m.addEventListener('click', function(e) { if (e.target === this) this.classList.add('hidden'); }); });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
