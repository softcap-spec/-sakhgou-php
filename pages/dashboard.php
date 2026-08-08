<?php
// dashboard.php — Tailwind, копия dashboard_main.tsx
$user = auth_required();
$pdo = db();

$sub = $_GET['sub'] ?? 'listings';

// Мой профиль: мои объявления
$st = $pdo->prepare("SELECT l.*, c.name AS category_name,
  (SELECT filename FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1) AS image,
  promo.promo_type AS promo_type
  FROM listings l JOIN categories c ON l.category_id = c.id
  LEFT JOIN promotions promo ON l.id = promo.listing_id AND promo.status = 'active' AND promo.expires_at > NOW()
  WHERE l.user_id = ? ORDER BY l.created_at DESC");
$st->execute([$user['id']]);
$myListings = $st->fetchAll();

// Вкладки
$tabs = [
  'listings' => 'Мои объявления',
  'favorites' => 'Избранное',
  'bookings' => 'Бронирования',
  'host_bookings' => 'Ко мне',
  'profile' => 'Профиль',
];

// POST: обновление профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
  csrf_check();
  $name = trim($_POST['name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');
  if (!empty($name)) {
    $pdo->prepare('UPDATE users SET name=?, phone=?, email=? WHERE id=?')->execute([$name, $phone, $email, $user['id']]);
    $user['name'] = $name;
    $user['phone'] = $phone;
    $user['email'] = $email;
  }
  header('Location: /dashboard?sub=profile&ok=1'); exit;
}

// POST: delete listing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
  csrf_check();
  $pdo->prepare('DELETE FROM listings WHERE id=? AND user_id=?')->execute([(int)$_POST['delete'], $user['id']]);
  header('Location: /dashboard'); exit;
}

$page_title = 'Личный кабинет — СахGO';
require __DIR__ . '/../includes/header.php';
?>

<main class="py-12">
<div class="max-w-7xl mx-auto px-4">
  <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
    <div>
      <span class="text-xs uppercase tracking-[0.12em] text-accent font-medium">Личный кабинет</span>
      <h1 class="font-display text-4xl mt-1"><?=h($user['name'])?></h1>
    </div>
    <a href="/create" class="inline-flex items-center justify-center rounded-lg bg-accent text-white hover:bg-accent/80 h-9 px-4 text-sm font-medium transition-all">+ Новое объявление</a>
  </div>

  <!-- Tabs -->
  <div class="flex gap-2 mb-8 border-b pb-0 flex-wrap">
    <?php foreach ($tabs as $k => $v): ?>
    <a href="/dashboard<?=$k==='listings'?'':'?sub='.$k?>" class="inline-flex items-center h-7 px-2.5 rounded-full text-sm font-medium transition-all <?=$sub===$k?'bg-accent text-white':'text-muted-foreground hover:text-foreground hover:bg-muted'?>"><?=$v?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($sub === 'listings'): ?>
    <?php if (empty($myListings)): ?>
      <div class="text-center py-20 text-muted-foreground">
        <p class="text-lg">У вас пока нет объявлений</p>
        <p class="text-sm mt-1 mb-4">Создайте первое объявление и начните зарабатывать</p>
        <a href="/create" class="inline-flex items-center justify-center rounded-lg bg-accent text-white hover:bg-accent/80 h-9 px-6 text-sm font-medium transition-all">Создать объявление</a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($myListings as $item): ?>
        <div class="bg-white border rounded-xl overflow-hidden flex flex-col relative">
          <div class="aspect-[16/10] bg-secondary overflow-hidden">
            <?php if (!empty($item['image'])): ?>
            <img src="/uploads/<?=h($item['image'])?>" alt="" class="w-full h-full object-cover" loading="lazy">
            <?php else: ?><div class="w-full h-full flex items-center justify-center text-4xl">📷</div><?php endif; ?>
          </div>
          <?php if (!empty($item['promo_type'])): ?>
          <span class="absolute top-2 left-2 text-xs font-bold px-2 py-0.5 rounded-full text-white <?=$item['promo_type']==='top'?'bg-red-600':($item['promo_type']==='highlight'?'bg-amber-500':'bg-red-500')?>"><?=$item['promo_type']==='top'?'🔝 TOP':($item['promo_type']==='highlight'?'💡 PROMO':'⚡ Срочно')?></span>
          <?php endif; ?>
          <div class="p-4 flex-1 flex flex-col gap-1">
            <div class="flex items-center gap-2 text-xs"><span class="badge <?=$item['status']==='active'?'text-green-700 border-green-200 bg-green-50':'text-muted-foreground'?>"><?=$item['status']==='active'?'Активно':$item['status']?></span><span class="text-muted-foreground"><?=h($item['category_name'])?></span></div>
            <div class="font-display text-xl mt-1"><?=number_format((float)$item['price'],0,'.',' ')?> ₽</div>
            <div class="font-medium text-sm leading-snug"><?=h($item['title'])?></div>
            <div class="flex items-center gap-2 text-xs text-muted-foreground mt-auto pt-2"><span>👁 <?=$item['view_count']??0?></span><span>·</span><span><?=time_ago($item['created_at'])?></span></div>
            <div class="flex gap-2 mt-3">
              <a href="/listing/<?=$item['id']?>" class="flex-1 inline-flex items-center justify-center rounded-lg border border-border hover:bg-muted h-8 text-xs font-medium transition-all">Смотреть</a>
              <a href="/edit/<?=$item['id']?>" class="flex-1 inline-flex items-center justify-center rounded-lg border border-border hover:bg-muted h-8 text-xs font-medium transition-all">Ред.</a>
              <a href="/promote?id=<?=$item['id']?>" class="flex-1 inline-flex items-center justify-center rounded-lg border border-amber-200 text-amber-700 hover:bg-amber-50 h-8 text-xs font-medium transition-all">🚀</a>
              <form method="post" onsubmit="return confirm('Удалить?')"><?= csrf_field() ?><button name="delete" value="<?=$item['id']?>" class="inline-flex items-center justify-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50 h-8 px-2 text-xs font-medium transition-all">🗑</button></form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($sub === 'favorites'): ?>
    <?php $favs = get_user_favorites($user['id']); ?>
    <?php if (empty($favs)): ?>
      <div class="text-center py-20 text-muted-foreground"><p class="text-lg">Нет избранных объявлений</p><p class="text-sm mt-1">Добавляйте объявления в избранное кнопкой ♡</p></div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($favs as $item): ?>
        <a href="/listing/<?=$item['id']?>" class="bg-white border rounded-xl overflow-hidden transition-all hover:-translate-y-0.5 hover:shadow-lg flex flex-col relative">
          <div class="aspect-[16/10] bg-secondary overflow-hidden"><?php if(!empty($item['image'])):?><img src="/uploads/<?=h($item['image'])?>" class="w-full h-full object-cover" loading="lazy"><?php else:?><div class="w-full h-full flex items-center justify-center text-4xl">📷</div><?php endif;?></div>
          <div class="p-4 flex-1 flex flex-col gap-1"><div class="font-display text-xl"><?=number_format((float)$item['price'],0,'.',' ')?> ₽</div><div class="font-medium text-sm leading-snug"><?=h($item['title'])?></div><div class="flex items-center gap-2 text-xs text-muted-foreground mt-auto pt-2"><span><?=h($item['category_name'])?></span></div></div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($sub === 'bookings'): ?>
    <?php $bookings = get_user_bookings($user['id']); ?>
    <?php if (empty($bookings)): ?><div class="text-center py-20 text-muted-foreground"><p class="text-lg">Нет бронирований</p></div>
    <?php else: ?>
      <div class="space-y-3">
      <?php foreach ($bookings as $b): ?>
        <div class="bg-white border rounded-xl p-5"><div class="flex justify-between items-start"><div><a href="/listing/<?=$b['listing_id']?>" class="font-display text-lg hover:underline"><?=h($b['listing_title'])?></a><div class="text-sm text-muted-foreground mt-1"><?=h($b['location']??'')?> · хозяин: <?=h($b['host_name'])?></div></div><div class="text-right"><div class="font-display text-xl"><?=number_format((float)$b['total_price'],0,'.',' ')?> ₽</div><div class="text-xs text-muted-foreground mt-1"><?=$b['created_at']?></div></div></div></div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($sub === 'host_bookings'): ?>
    <?php $hb = get_host_bookings($user['id']); ?>
    <?php if (empty($hb)): ?><div class="text-center py-20 text-muted-foreground"><p class="text-lg">Нет бронирований у вас</p></div>
    <?php else: ?>
      <div class="space-y-3">
      <?php foreach ($hb as $b): ?>
        <div class="bg-white border rounded-xl p-5"><div class="flex justify-between items-start"><div><span class="text-sm text-muted-foreground">Гость: <?=h($b['guest_name'])?></span><br><a href="/listing/<?=$b['listing_id']?>" class="font-display text-lg hover:underline"><?=h($b['listing_title'])?></a></div><div class="text-right"><div class="font-display text-xl"><?=number_format((float)$b['total_price'],0,'.',' ')?> ₽</div><div class="text-xs text-muted-foreground mt-1"><?=$b['created_at']?></div></div></div></div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($sub === 'profile'): ?>
    <?php if (isset($_GET['ok'])): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 mb-6 text-sm">Профиль обновлён</div><?php endif; ?>
    <div class="max-w-lg">
      <form method="post" class="bg-white border rounded-xl p-6 space-y-4">
        <?= csrf_field() ?>
        <div class="form-group"><label>Имя</label><input type="text" name="name" value="<?=h($user['name'])?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?=h($user['email'])?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
        <div class="form-group"><label>Телефон</label><input type="text" name="phone" value="<?=h($user['phone']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
        <button type="submit" name="update_profile" value="1" class="inline-flex items-center justify-center rounded-lg bg-accent text-white hover:bg-accent/80 h-9 px-6 text-sm font-medium transition-all">Сохранить</button>
      </form>
    </div>
  <?php endif; ?>
</div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
