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

  // Maintenance toggle
  if ($_POST['action'] === 'toggle_maintenance') {
    $current = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'maintenance'")->fetchColumn();
    $newVal = $current === '1' ? '0' : '1';
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance'")->execute([$newVal]);
    header('Location: /admin?tab=maintenance&ok=' . ($newVal === '1' ? 'on' : 'off'));
    exit;
  }
}

// ── Data loaders ──

// Stats
$total_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_listings = (int)$pdo->query("SELECT COUNT(*) FROM listings")->fetchColumn();
$active_listings = (int)$pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'active'")->fetchColumn();
$pending_listings = (int)$pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'pending'")->fetchColumn();
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
      <span class="text-xs bg-accent/10 text-accent px-2 py-0.5 rounded-full font-medium">v<?= defined('APP_VERSION') ? APP_VERSION : '1.0' ?></span>
    </div>

    <?php if (isset($_GET['ok'])): ?>
      <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 mb-4 text-sm">Выполнено</div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="border-b mb-6">
      <nav class="flex gap-1 overflow-x-auto -mb-px">
        <?php
        $tabs = [
          'dashboard' => '📊 Дашборд',
          'moderation' => '⚠️ Модерация',
          'reviews' => '⭐ Отзывы',
          'users' => '👥 Пользователи',
          'payments' => '💰 Платежи',
          'maintenance' => '🔧 Техработы',
          'categories' => '📂 Категории',
          'banners' => '🪧 Баннеры',
          'content' => '📝 Контент',
        ];
        foreach ($tabs as $k => $v):
          $active = $tab === $k;
        ?>
          <a href="?tab=<?= $k ?>" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $active ? 'border-accent text-accent' : 'border-transparent text-muted-foreground hover:text-foreground' ?>"><?= $v ?><?= $k === 'moderation' && $pending_listings ? ' <span class="bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full">'.$pending_listings.'</span>' : '' ?></a>
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
                <span class="text-xs px-2 py-0.5 rounded-full <?= $u['role']==='admin'?'bg-purple-100 text-purple-700':($u['role']==='host'?'bg-blue-100 text-blue-700':'bg-muted text-muted-foreground') ?>"><?= h($u['role']) ?></span>
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
  // Handle price save
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_prices') {
    foreach (['top','highlight','urgent'] as $t) {
      $val = max(1, (int)($_POST['price_' . $t] ?? 100));
      $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$val, 'promo_' . $t]);
    }
    header('Location: /admin?tab=payments&ok=prices'); exit;
  }
  $promoPrices = get_promo_prices();
  $promos = $pdo->query("SELECT p.*, u.name AS host_name, l.title AS listing_title FROM promotions p JOIN users u ON p.host_id = u.id JOIN listings l ON p.listing_id = l.id ORDER BY p.created_at DESC LIMIT 200");
?>
    <h2 class="font-display text-xl mb-4">Платные услуги / Продвижение</h2>

    <!-- Price Editor -->
    <div class="bg-white border rounded-xl p-6 mb-6">
      <div class="flex items-center justify-between mb-3">
        <h3 class="font-display text-lg">Настройка цен (₽/день)</h3>
        <?php if (isset($_GET['ok']) && $_GET['ok']==='prices'): ?>
          <span class="text-xs text-green-600">✓ Сохранено</span>
        <?php endif; ?>
      </div>
      <form method="post" class="flex flex-wrap gap-4 items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_prices">
        <?php
        $priceDefs = [
          ['top', '🔝 Top', $promoPrices['top']],
          ['highlight', '💡 Highlight', $promoPrices['highlight']],
          ['urgent', '⚡ Срочно', $promoPrices['urgent']],
        ];
        foreach ($priceDefs as $pd): ?>
          <label class="flex flex-col gap-1">
            <span class="text-xs text-muted-foreground"><?=$pd[1]?></span>
            <input type="number" name="price_<?=$pd[0]?>" value="<?=$pd[2]?>" min="1" class="w-24 border rounded-lg px-3 py-2 text-sm">
          </label>
        <?php endforeach; ?>
        <button type="submit" class="btn-accent text-sm py-2 px-4">Сохранить</button>
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
                <span class="text-xs px-2 py-0.5 rounded-full <?= $pm['promo_type']==='top' ? 'bg-amber-100 text-amber-700' : ($pm['promo_type']==='highlight' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700') ?>"><?=h($pm['promo_type'])?></span>
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
                  <div class="w-6 h-6 bg-accent/10 rounded flex items-center justify-center text-[10px] text-accent font-semibold"><?= mb_substr($c['name'], 0, 1) ?></div>
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
?>
    <h2 class="font-display text-xl mb-4">Управление баннерами</h2>
    <div class="bg-white border rounded-xl p-8 text-center text-muted-foreground">
      <p class="text-4xl mb-2">🪧</p>
      <p>Управление баннерами будет доступно в следующем обновлении</p>
    </div>

<?php
// ── CONTENT ──
elseif ($tab === 'content'):
?>
    <h2 class="font-display text-xl mb-4">Редактирование контента</h2>
    <div class="bg-white border rounded-xl p-8 text-center text-muted-foreground">
      <p class="text-4xl mb-2">📝</p>
      <p>Редактор контента будет доступен в следующем обновлении</p>
    </div>
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
