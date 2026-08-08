<?php
// admin/index.php — панель администратора
$user = admin_required();
$pdo = db();

$sub = $_GET['sub'] ?? 'stats';

// Stats
$listings_count = $pdo->query("SELECT COUNT(*) FROM listings")->fetchColumn();
$users_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$bookings_count = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$reviews_count = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
$active_promos = $pdo->query("SELECT COUNT(*) FROM promotions WHERE status = 'active' AND expires_at > NOW()")->fetchColumn();
$views_total = $pdo->query("SELECT SUM(COALESCE(view_count,0)) FROM listings")->fetchColumn();

// Moderate reviews
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_review'])) {
  $rid = (int)$_POST['approve_review'];
  $pdo->prepare('UPDATE reviews SET moderated = 1 WHERE id = ?')->execute([$rid]);
  header('Location: /admin?sub=reviews'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
  $rid = (int)$_POST['delete_review'];
  $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$rid]);
  header('Location: /admin?sub=reviews'); exit;
}
// Toggle listing status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_listing'])) {
  $lid = (int)$_POST['toggle_listing'];
  $stmt = $pdo->prepare('SELECT status FROM listings WHERE id = ?');
  $stmt->execute([$lid]);
  $cur = $stmt->fetch();
  if ($cur) {
    $pdo->prepare('UPDATE listings SET status = ? WHERE id = ?')->execute([$cur['status']==='active'?'inactive':'active', $lid]);
  }
  header('Location: /admin?sub=listings'); exit;
}

$page_title = 'Админ-панель — СахGO';
require __DIR__ . '/../../includes/header.php';
?>
<section class="py-12">
<div class="max-w-7xl mx-auto px-4">
  <span class="text-xs uppercase tracking-[0.12em] text-accent font-medium">Администрирование</span>
  <h1 class="font-display text-4xl mt-1 mb-2">Админ-панель <span class="text-sm text-muted-foreground font-normal align-top">v<?=defined('APP_VERSION')?APP_VERSION:'1.0'?></span></h1>
  <p class="text-xs text-muted-foreground mb-6">Пользователь: <?=h($user['name']??'admin')?> · Роль: <?=h($user['role']??'admin')?></p>

  <!-- Tabs -->
  <div class="flex gap-2 mb-8 border-b">
    <a href="/admin?sub=stats" class="filter-pill <?=$sub==='stats'?'active':''?>">Статистика</a>
    <a href="/admin?sub=listings" class="filter-pill <?=$sub==='listings'?'active':''?>">Объявления</a>
    <a href="/admin?sub=users" class="filter-pill <?=$sub==='users'?'active':''?>">Пользователи</a>
    <a href="/admin?sub=reviews" class="filter-pill <?=$sub==='reviews'?'active':''?>">Отзывы</a>
  </div>

  <?php if ($sub === 'stats'): ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
      <div class="bg-white border rounded-xl p-6 text-center"><div class="font-display text-4xl text-accent mb-1"><?=$listings_count?></div><div class="text-sm text-muted-foreground">объявлений</div></div>
      <div class="bg-white border rounded-xl p-6 text-center"><div class="font-display text-4xl text-accent mb-1"><?=$users_count?></div><div class="text-sm text-muted-foreground">пользователей</div></div>
      <div class="bg-white border rounded-xl p-6 text-center"><div class="font-display text-4xl text-accent mb-1"><?=$bookings_count?></div><div class="text-sm text-muted-foreground">бронирований</div></div>
      <div class="bg-white border rounded-xl p-6 text-center"><div class="font-display text-4xl text-accent mb-1"><?=$reviews_count?></div><div class="text-sm text-muted-foreground">отзывов</div></div>
      <div class="bg-white border rounded-xl p-6 text-center"><div class="font-display text-4xl text-accent mb-1"><?=$active_promos?></div><div class="text-sm text-muted-foreground">продвижений</div></div>
      <div class="bg-white border rounded-xl p-6 text-center"><div class="font-display text-4xl text-accent mb-1"><?=number_format($views_total,0,'.',' ')?></div><div class="text-sm text-muted-foreground">просмотров</div></div>
    </div>

  <?php elseif ($sub === 'listings'): ?>
    <div class="bg-white border rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead><tr class="bg-secondary text-left"><th class="p-3">ID</th><th class="p-3">Название</th><th class="p-3">Автор</th><th class="p-3">Цена</th><th class="p-3">Просмотры</th><th class="p-3">Статус</th><th class="p-3">Действия</th></tr></thead>
        <tbody>
        <?php
        $ls = $pdo->query('SELECT l.*, u.name AS author FROM listings l JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 50')->fetchAll();
        foreach ($ls as $l): ?>
          <tr class="border-t">
            <td class="p-3 text-muted-foreground">#<?=$l['id']?></td>
            <td class="p-3"><a href="/listing/<?=$l['id']?>" class="font-medium hover:underline"><?=h(mb_substr($l['title'],0,40))?></a></td>
            <td class="p-3"><?=h($l['author'])?></td>
            <td class="p-3"><?=format_price((float)$l['price'])?></td>
            <td class="p-3"><?=$l['view_count']??0?></td>
            <td class="p-3"><span class="badge <?=$l['status']==='active'?'':'opacity-50'?>"><?=$l['status']?></span></td>
            <td class="p-3">
              <form method="post" style="display:inline"><button type="submit" name="toggle_listing" value="<?=$l['id']?>" class="auth-btn-ghost text-xs"><?=$l['status']==='active'?'Откл.':'Вкл.'?></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($sub === 'users'): ?>
    <div class="bg-white border rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead><tr class="bg-secondary text-left"><th class="p-3">ID</th><th class="p-3">Имя</th><th class="p-3">Email</th><th class="p-3">Телефон</th><th class="p-3">Роль</th><th class="p-3">Объявлений</th></tr></thead>
        <tbody>
        <?php
        $us = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM listings WHERE user_id = u.id) AS listing_count FROM users u ORDER BY u.created_at DESC LIMIT 50")->fetchAll();
        foreach ($us as $u): ?>
          <tr class="border-t">
            <td class="p-3 text-muted-foreground">#<?=$u['id']?></td>
            <td class="p-3 font-medium"><?=h($u['name'])?></td>
            <td class="p-3"><?=h($u['email'])?></td>
            <td class="p-3"><?=h($u['phone']?:'—')?></td>
            <td class="p-3"><span class="badge <?=$u['role']==='admin'?'':'opacity-50'?>"><?=$u['role']?></span></td>
            <td class="p-3"><?=$u['listing_count']?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($sub === 'reviews'): ?>
    <h2 class="font-display text-2xl mb-4">Модерация отзывов</h2>
    <?php
    $revs = $pdo->query('SELECT r.*, u.name AS author, l.title AS listing_title FROM reviews r JOIN users u ON r.user_id = u.id JOIN listings l ON r.listing_id = l.id ORDER BY r.moderated ASC, r.created_at DESC LIMIT 30')->fetchAll();
    if (empty($revs)): ?>
      <p class="text-muted-foreground">Нет отзывов</p>
    <?php else: ?>
      <div class="space-y-3">
      <?php foreach ($revs as $r): ?>
        <div class="bg-white border rounded-xl p-4 <?=$r['moderated']?'':'bg-yellow-50'?>">
          <div class="flex justify-between items-start">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="font-medium"><?=h($r['author'])?></span>
                <span class="text-sm text-yellow-500"><?=str_repeat('★',(int)$r['rating'])?></span>
                <span class="text-xs text-muted-foreground">→ <?=h($r['listing_title'])?></span>
              </div>
              <p class="text-sm text-muted-foreground"><?=h($r['text'])?></p>
              <div class="text-xs text-muted-foreground mt-1"><?=$r['created_at']?> · <?=$r['moderated']?'✅ Одобрен':'⚠️ На модерации'?></div>
            </div>
            <div class="flex gap-2">
              <?php if (!$r['moderated']): ?>
                <form method="post"><button name="approve_review" value="<?=$r['id']?>" class="cta-btn" style="font-size:0.75rem;padding:0.25rem 0.75rem">Одобрить</button></form>
              <?php endif; ?>
              <form method="post" onsubmit="return confirm('Удалить?')"><button name="delete_review" value="<?=$r['id']?>" class="btn-outline" style="font-size:0.75rem;padding:0.25rem 0.75rem;color:#dc2626;border-color:#dc2626">Удалить</button></form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
