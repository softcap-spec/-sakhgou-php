<?php
// listing.php — v4 Avito-style
$lid = (int)($id ?? 0);
$pdo = db();

$stmt = $pdo->prepare("SELECT l.*, u.name AS host_name, u.avatar_url AS host_avatar, u.phone AS host_phone,
  (SELECT COUNT(*) FROM reviews WHERE listing_id = l.id AND moderated = 1) AS reviews_count,
  (SELECT AVG(rating) FROM reviews WHERE listing_id = l.id AND moderated = 1) AS avg_rating
  FROM listings l JOIN users u ON l.user_id = u.id WHERE l.id = ? AND l.status = ?");
$stmt->execute([$lid, 'active']);
$item = $stmt->fetch();

if (!$item) {
  $cu = auth_user();
  $stmt = $pdo->prepare('SELECT l.*, u.name AS host_name, u.avatar_url AS host_avatar, u.phone AS host_phone FROM listings l JOIN users u ON l.user_id = u.id WHERE l.id = ?');
  $stmt->execute([$lid]);
  $pending = $stmt->fetch();
  if ($pending && (($cu && $cu['id'] == $pending['user_id']) || ($cu && $cu['role'] === 'admin'))) {
    $item = $pending;
    $item['reviews_count'] = 0;
    $item['avg_rating'] = null;
  }
  if (!$item) { http_response_code(404); require __DIR__.'/404.php'; exit; }
}

$pdo->prepare('UPDATE listings SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?')->execute([$lid]);
$item['view_count'] = ($item['view_count'] ?? 0) + 1;

$stmt = $pdo->prepare('SELECT l.*, c.slug AS cat_slug FROM listings l JOIN categories c ON l.category_id = c.id WHERE l.listing_type = ? AND l.id != ? AND l.status = ? ORDER BY RAND() LIMIT 4');
$stmt->execute([$item['listing_type'] ?? 'tour', $lid, 'active']);
$similar = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT r.*, u.name AS author_name, u.avatar_url AS author_avatar FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.listing_id = ? AND r.moderated = 1 ORDER BY r.created_at DESC LIMIT 10');
$stmt->execute([$lid]);
$reviews = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT filename FROM listing_images WHERE listing_id = ? ORDER BY sort_order');
$stmt->execute([$lid]);
$images = array_column($stmt->fetchAll(), 'filename');
if (empty($images) && !empty($item['cover_image'])) $images = [$item['cover_image']];

$TYPE_LABEL = ['property'=>'Жильё','tour'=>'Туры','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто'];
$lt = $item['listing_type'] ?? 'tour';
$cu = auth_user();
$isFavorite = $cu ? is_favorite($cu['id'], $lid) : false;
$isOwner = $cu && ($cu['id'] == $item['user_id'] || $cu['role'] === 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fav'])) {
  csrf_check();
  if ($cu) { $isFavorite = toggle_favorite($cu['id'], $lid); header('Location: /listing/'.$lid); exit; }
}

$page_title = h($item['title']) . ' — СахGO';
$page_description = h(mb_substr($item['description'] ?? $item['title'], 0, 160));
require __DIR__ . '/../includes/header.php';
?>

<main class="py-4">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

  <!-- Breadcrumbs -->
  <nav class="flex items-center gap-1.5 text-xs text-[#9AAAB8] mb-4 flex-wrap">
    <a href="/" class="hover:text-accent transition-colors">Главная</a>
    <span>/</span>
    <a href="/catalog/<?=$lt?>" class="hover:text-accent transition-colors"><?=$TYPE_LABEL[$lt]??'Каталог'?></a>
    <span>/</span>
    <span class="text-[#54677A] truncate max-w-[300px]"><?=h($item['title'])?></span>
  </nav>

  <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-6">

    <!-- LEFT COLUMN -->
    <div class="space-y-6 min-w-0">

      <!-- Title -->
      <div>
        <h1 class="font-display text-2xl sm:text-3xl leading-tight text-foreground"><?=h($item['title'])?></h1>
        <div class="flex items-center gap-3 mt-2 text-xs text-[#9AAAB8]">
          <?php if (!empty($item['location'])): ?>
          <span class="flex items-center gap-1">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?=h($item['location'])?>
          </span>
          <?php endif; ?>
          <span class="flex items-center gap-1">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/></svg>
            <?=$item['view_count'] ?? 0?>
          </span>
          <span>· <?=date('d.m.Y', strtotime($item['created_at']))?></span>
          <span>· № <?=h($item['id'])?></span>
        </div>
      </div>

      <!-- Gallery -->
      <div>
        <?php if (!empty($images)): ?>
        <div class="rounded-xl overflow-hidden border border-[#EBEEF2] bg-[#EEF2F6] relative">
          <img src="/uploads/<?=h($images[0])?>" alt="<?=h($item['title'])?>" class="w-full aspect-[4/3] sm:aspect-[16/10] object-cover" id="mainImg">
          <?php if ($cu && !$isOwner): ?>
          <form method="post" class="absolute top-3 right-3">
            <?= csrf_field() ?>
            <button name="fav" value="1" class="w-9 h-9 rounded-full bg-white/90 backdrop-blur flex items-center justify-center shadow-sm hover:bg-white transition-colors">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="<?=$isFavorite?'#ef4444':'none'?>" stroke="<?=$isFavorite?'#ef4444':'#54677A'?>" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </button>
          </form>
          <?php endif; ?>
        </div>
        <?php if (count($images) > 1): ?>
        <div class="flex gap-2 mt-2 overflow-x-auto pb-1">
          <?php foreach ($images as $i => $img): ?>
          <button onclick="setMainImg(this)" data-src="/uploads/<?=h($img)?>" class="shrink-0 w-20 h-20 rounded-lg overflow-hidden border-2 <?=$i===0?'border-accent':'border-[#EBEEF2]'?> hover:border-accent/50 transition-colors">
            <img src="/uploads/<?=h($img)?>" alt="" class="w-full h-full object-cover" loading="lazy">
          </button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="rounded-xl border border-[#EBEEF2] bg-[#EEF2F6] aspect-[16/10] flex items-center justify-center">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#C8D0DA" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
        </div>
        <?php endif; ?>
      </div>

      <!-- Price (mobile) -->
      <div class="lg:hidden flex items-center justify-between bg-white border border-[#EBEEF2] rounded-xl p-4">
        <div class="font-display text-2xl"><?=number_format((float)$item['price'],0,'.',' ')?> <span class="text-sm font-normal text-[#9AAAB8]"><?=price_label($item['listing_type'])?></span></div>
        <?php if(!empty($item['host_phone'])): ?>
        <button onclick="revealPhone()" class="inline-flex items-center gap-1.5 rounded-lg bg-accent text-white h-9 px-4 text-sm font-medium">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          Позвонить
        </button>
        <?php endif; ?>
      </div>
      <div id="revealedPhoneMobile" class="hidden lg:hidden text-center text-lg font-display mb-4"><a href="tel:<?=h($item['host_phone'])?>" class="text-accent hover:underline"><?=h($item['host_phone'])?></a></div>

      <!-- Description -->
      <div class="bg-white border border-[#EBEEF2] rounded-xl p-5">
        <h2 class="font-display text-lg mb-3">Описание</h2>
        <?php if (!empty($item['description'])): ?>
        <p class="text-[#3A4A5C] leading-relaxed whitespace-pre-line text-sm"><?=h($item['description'])?></p>
        <?php else: ?>
        <p class="text-[#9AAAB8] text-sm">Описание не указано</p>
        <?php endif; ?>
      </div>

      <!-- Specs -->
      <?php
      $specs = [];
      if ($lt === 'property') {
        foreach(['rooms_count'=>'Комнат','beds_count'=>'Кроватей','bathrooms_count'=>'Санузлов','area_sqm'=>'Площадь, м²','max_guests'=>'Макс. гостей','check_in_time'=>'Заезд','check_out_time'=>'Выезд','deposit_amount'=>'Депозит','cancellation_policy'=>'Отмена'] as $k=>$l) { if (!empty($item[$k])) $specs[$l] = $item[$k]; }
      } elseif ($lt === 'tour') {
        foreach(['tour_duration_hours'=>'Длительность, ч','tour_duration_days'=>'Длительность, дн.','difficulty_level'=>'Сложность','group_size_min'=>'Мин. группа','group_size_max'=>'Макс. группа','start_point'=>'Точка старта','transport_included'=>'Транспорт','transport_type'=>'Тип транспорта','requires_border_permit'=>'Погранпропуск','depends_on_weather'=>'Зависит от погоды','meals_included'=>'Питание','season'=>'Сезон'] as $k=>$l) { if (!empty($item[$k]) || $item[$k]==='0') { $v = $item[$k]; if ($k==='transport_included'||$k==='requires_border_permit'||$k==='depends_on_weather'||$k==='meals_included') $v=$v?'Да':'Нет'; if ($k==='difficulty_level') $v = ['easy'=>'Лёгкий','medium'=>'Средний','hard'=>'Сложный','extreme'=>'Экстремальный'][$v]??$v; $specs[$l]=$v; } }
      } elseif ($lt === 'fishing') {
        foreach(['fishing_type'=>'Тип рыбалки','fishing_method'=>'Метод ловли','gear_included'=>'Снаряжение','catch_guarantee'=>'Гарантия улова','license_required'=>'Лицензия','boat_included'=>'Лодка','group_size_max'=>'Макс. группа','season'=>'Сезон'] as $k=>$l) { if (!empty($item[$k]) || $item[$k]==='0') { $v = $item[$k]; if ($k==='gear_included'||$k==='catch_guarantee'||$k==='license_required'||$k==='boat_included') $v=$v?'Да':'Нет'; $specs[$l]=$v; } }
      } elseif ($lt === 'rental_gear') {
        foreach(['gear_condition'=>'Состояние','deposit_amount'=>'Депозит'] as $k=>$l) { if (!empty($item[$k])) $specs[$l]=$item[$k]; }
      } elseif ($lt === 'car_rental') {
        foreach(['car_brand'=>'Марка','car_year'=>'Год','car_type'=>'Тип','transmission'=>'Коробка','deposit_amount'=>'Депозит'] as $k=>$l) { if (!empty($item[$k])) $specs[$l]=$item[$k]; }
      }
      ?>
      <?php if (!empty($specs)): ?>
      <div class="bg-white border border-[#EBEEF2] rounded-xl p-5">
        <h2 class="font-display text-lg mb-3">Характеристики</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
          <?php foreach($specs as $l=>$v): ?>
          <div class="flex justify-between py-2 border-b border-[#F0F3F7]">
            <dt class="text-sm text-[#7A8A9A]"><?=$l?></dt>
            <dd class="text-sm font-medium text-foreground text-right"><?=h((string)$v)?></dd>
          </div>
          <?php endforeach; ?>
        </dl>
      </div>
      <?php endif; ?>

      <!-- Amenities / Includes -->
      <?php $amen = json_decode($item['amenities']??'[]',true)?:[]; $inc = json_decode($item['includes']??'[]',true)?:[]; ?>
      <?php if(!empty($amen) || !empty($inc)): ?>
      <div class="bg-white border border-[#EBEEF2] rounded-xl p-5 space-y-4">
        <?php if(!empty($amen)): ?>
        <div>
          <h3 class="text-sm font-semibold mb-2">Удобства</h3>
          <div class="flex flex-wrap gap-1.5"><?php foreach($amen as $a):?><span class="badge"><?=h($a)?></span><?php endforeach;?></div>
        </div>
        <?php endif; ?>
        <?php if(!empty($inc)): ?>
        <div>
          <h3 class="text-sm font-semibold mb-2">Включено в стоимость</h3>
          <div class="flex flex-wrap gap-1.5"><?php foreach($inc as $a):?><span class="badge"><?=h($a)?></span><?php endforeach;?></div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Reviews -->
      <div class="bg-white border border-[#EBEEF2] rounded-xl p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-display text-lg">Отзывы</h2>
          <?php if($item['reviews_count']>0):?>
          <span class="text-sm text-[#7A8A9A]"><span class="text-amber-500"><?=$item['reviews_count']>0?'★ ':''?></span><?=round($item['avg_rating'],1)?> · <?=$item['reviews_count']?></span>
          <?php endif; ?>
        </div>
        <?php if(empty($reviews)): ?>
        <p class="text-sm text-[#9AAAB8]">Пока нет отзывов</p>
        <?php else: foreach($reviews as $r): ?>
        <div class="border-t border-[#F0F3F7] py-3.5">
          <div class="flex items-center gap-2 mb-1.5">
            <?= avatar_html(['name'=>$r['author_name'],'avatar_url'=>$r['author_avatar']??null], 'w-7 h-7', 'text-[0.6rem]') ?>
            <span class="text-sm font-medium"><?=h($r['author_name'])?></span>
            <span class="text-amber-500 text-xs"><?=str_repeat('★',(int)$r['rating'])?></span>
            <span class="text-xs text-[#9AAAB8] ml-auto"><?=time_ago($r['created_at'])?></span>
          </div>
          <p class="text-sm text-[#3A4A5C]"><?=h($r['text'])?></p>
        </div>
        <?php endforeach; endif; ?>
      </div>

    </div>

    <!-- RIGHT COLUMN (sticky sidebar) -->
    <div class="space-y-4">
      <div class="lg:sticky lg:top-20 space-y-4">

        <!-- Price card -->
        <div class="bg-white border border-[#EBEEF2] rounded-xl p-5">
          <div class="font-display text-3xl text-foreground"><?=number_format((float)$item['price'],0,'.',' ')?> <span class="text-sm font-normal text-[#9AAAB8]"><?=price_label($item['listing_type'])?></span></div>
          <?php if (!empty($item['location'])): ?>
          <div class="text-xs text-[#7A8A9A] mt-1.5 flex items-center gap-1">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?=h($item['location'])?>
          </div>
          <?php endif; ?>

          <!-- Actions -->
          <div class="space-y-2 mt-4">
            <?php if(!empty($item['host_phone'])): ?>
            <button onclick="revealPhone()" id="revealPhoneBtn" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-accent text-white hover:bg-accent/90 h-11 px-4 text-sm font-semibold transition-colors">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              Показать телефон
            </button>
            <div id="revealedPhone" class="hidden text-center font-display text-xl py-1"><a href="tel:<?=h($item['host_phone'])?>" class="text-accent hover:underline"><?=h($item['host_phone'])?></a></div>
            <?php endif; ?>

            <?php if($cu && !$isOwner): ?>
            <button onclick="openChatModal()" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-[#DFE4EA] hover:bg-[#F7F9FB] h-11 px-4 text-sm font-medium transition-colors">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              Написать сообщение
            </button>
            <?php endif; ?>

            <?php if ($cu): ?>
            <form method="post">
              <?= csrf_field() ?>
              <button name="fav" value="1" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border <?=$isFavorite?'bg-red-50 border-red-200 text-red-600':'border-[#DFE4EA] hover:bg-[#F7F9FB]'?> h-11 px-4 text-sm font-medium transition-colors">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?=$isFavorite?'currentColor':'none'?>" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <?=$isFavorite?'В избранном':'Добавить в избранное'?>
              </button>
            </form>
            <?php endif; ?>

            <?php if ($isOwner): ?>
            <a href="/edit/<?=$lid?>" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-[#DFE4EA] hover:bg-[#F7F9FB] h-11 px-4 text-sm font-medium transition-colors">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Редактировать
            </a>
            <a href="/promote?id=<?=$lid?>" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-[#DFE4EA] hover:bg-[#F7F9FB] h-11 px-4 text-sm font-medium transition-colors">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
              Продвинуть
            </a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Author card -->
        <div class="bg-white border border-[#EBEEF2] rounded-xl p-5">
          <a href="#" class="flex items-center gap-3 hover:bg-[#F7F9FB] -mx-2 -mt-2 p-2 rounded-lg transition-colors">
            <?= avatar_html(['name' => $item['host_name'], 'avatar_url' => $item['host_avatar']], 'w-11 h-11', 'text-sm') ?>
            <div class="min-w-0">
              <div class="font-semibold text-sm truncate"><?=h($item['host_name'])?></div>
              <div class="text-xs text-[#9AAAB8]">На сайте с <?=date('m.Y',strtotime($item['created_at']))?></div>
            </div>
          </a>
          <?php if($item['reviews_count']>0): ?>
          <div class="mt-3 pt-3 border-t border-[#F0F3F7] flex items-center justify-between text-sm">
            <span class="text-[#7A8A9A]">Рейтинг</span>
            <span><span class="text-amber-500">★</span> <?=round($item['avg_rating'],1)?> (<?=$item['reviews_count']?>)</span>
          </div>
          <?php endif; ?>
        </div>

        <!-- Safety note -->
        <div class="bg-[#F7F9FB] border border-[#EBEEF2] rounded-xl p-4">
          <div class="flex gap-2.5">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1B6B8A" stroke-width="2" class="shrink-0 mt-0.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <div class="text-xs text-[#54677A] leading-relaxed">
              <strong class="text-foreground">Безопасная сделка</strong><br>
              Проверяйте объект перед оплатой. Не переводите предоплату незнакомым людям.
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>

<!-- Similar -->
<?php if (!empty($similar)): ?>
<section class="py-10 mt-6 bg-white border-t border-[#EBEEF2]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <h2 class="font-display text-xl mb-6">Похожие объявления</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
      <?php foreach($similar as $s): ?>
      <a href="/listing/<?=$s['id']?>" class="listing-card">
        <div class="listing-img">
          <?php $simg = $pdo->query("SELECT filename FROM listing_images WHERE listing_id={$s['id']} ORDER BY sort_order LIMIT 1")->fetchColumn(); ?>
          <?php if($simg): ?><img src="/uploads/<?=h($simg)?>" alt="" loading="lazy"><?php endif; ?>
        </div>
        <div class="listing-body">
          <div class="listing-price"><?=number_format((float)$s['price'],0,'.',' ')?> <span class="text-[0.625rem] font-normal text-[#9AAAB8]"><?=price_label($s['listing_type'])?></span></div>
          <div class="listing-title"><?=h($s['title'])?></div>
          <div class="listing-meta"><span><?=h($s['location'])?></span></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Chat Modal -->
<?php if ($cu && !$isOwner): ?>
<div id="chatModal" class="fixed inset-0 z-[100] hidden" style="background:rgba(15,23,32,0.4)">
  <div class="absolute inset-0" onclick="closeChatModal()"></div>
  <div class="absolute bottom-0 right-0 sm:bottom-6 sm:right-6 w-full sm:w-96 h-[70vh] sm:h-[32rem] bg-white sm:rounded-xl flex flex-col overflow-hidden shadow-2xl">
    <!-- Header -->
    <div class="flex items-center justify-between px-4 py-3 border-b border-[#EBEEF2]">
      <div class="flex items-center gap-2.5 min-w-0">
        <?= avatar_html(['name'=>$item['host_name'],'avatar_url'=>$item['host_avatar']], 'w-8 h-8', 'text-xs') ?>
        <div class="min-w-0">
          <div class="text-sm font-semibold truncate"><?=h($item['host_name'])?></div>
          <div class="text-xs text-[#9AAAB8] truncate"><?=h($item['title'])?></div>
        </div>
      </div>
      <button onclick="closeChatModal()" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#7A8A9A] hover:bg-[#F7F9FB] hover:text-foreground transition-colors">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <!-- Messages -->
    <div id="cmMessages" class="flex-1 overflow-y-auto p-4 flex flex-col gap-2">
      <div class="text-center text-xs text-[#9AAAB8] py-4">Загрузка...</div>
    </div>
    <!-- Input -->
    <div class="border-t border-[#EBEEF2] p-3 flex gap-2">
      <input type="text" id="cmInput" placeholder="Сообщение..." class="flex-1 border border-[#DFE4EA] rounded-lg px-3 py-2 text-sm outline-none focus:border-accent" onkeydown="if(event.key==='Enter')cmSend()">
      <button onclick="cmSend()" class="w-9 h-9 rounded-lg bg-accent text-white flex items-center justify-center hover:bg-accent/90 transition-colors">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
      </button>
    </div>
  </div>
</div>
<script>
var cmLid = <?=$lid?>;
var cmUid = <?=json_encode($cu['id'])?>;
var cmHost = <?=json_encode((int)$item['user_id'])?>;
var cmPoll = null;

function openChatModal() {
  document.getElementById('chatModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
  cmLoad();
  cmPoll = setInterval(cmLoad, 4000);
  setTimeout(function(){ document.getElementById('cmInput').focus(); }, 100);
}
function closeChatModal() {
  document.getElementById('chatModal').classList.add('hidden');
  document.body.style.overflow = '';
  if (cmPoll) { clearInterval(cmPoll); cmPoll = null; }
}
function cmLoad() {
  fetch('/api/messages?lid=' + cmLid + '&uid=' + cmHost)
    .then(function(r){return r.json()})
    .then(function(data){
      var box = document.getElementById('cmMessages');
      if (!data.messages || data.messages.length === 0) {
        box.innerHTML = '<div class="text-center text-xs text-[#9AAAB8] py-4">Напишите первое сообщение</div>';
        return;
      }
      var html = '';
      for (var i=0; i<data.messages.length; i++) {
        var m = data.messages[i];
        var mine = m.sender_id == cmUid;
        var cls = mine ? 'bg-accent text-white self-end' : 'bg-[#EEF2F6] text-foreground self-start';
        var time = new Date(m.created_at.replace(/-/g,'/')).toLocaleTimeString('ru-RU',{hour:'2-digit',minute:'2-digit'});
        html += '<div class="'+cls+' rounded-lg px-3 py-1.5 text-sm max-w-[80%]" style="word-wrap:break-word">'+escapeHtml(m.text)+'<div class="text-[0.625rem] '+(mine?'text-white/60':'text-[#9AAAB8]')+' mt-0.5">'+time+'</div></div>';
      }
      box.innerHTML = html;
      box.scrollTop = box.scrollHeight;
    })
    .catch(function(){});
}
function cmSend() {
  var input = document.getElementById('cmInput');
  var text = input.value.trim();
  if (!text) return;
  input.value = '';
  input.disabled = true;
  fetch('/api/send', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'lid=' + cmLid + '&text=' + encodeURIComponent(text)
  })
  .then(function(r){return r.json()})
  .then(function(data){
    input.disabled = false;
    input.focus();
    if (data.ok) cmLoad();
    else { alert('Ошибка отправки'); }
  })
  .catch(function(){ input.disabled = false; });
}
function escapeHtml(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>
<?php endif; ?>

</main>

<script>
function setMainImg(btn) {
  var src = btn.dataset.src;
  document.getElementById('mainImg').src = src;
  document.querySelectorAll('[data-src]').forEach(function(b){ b.classList.remove('border-accent'); b.classList.add('border-[#EBEEF2]'); });
  btn.classList.remove('border-[#EBEEF2]');
  btn.classList.add('border-accent');
}
function revealPhone(){
  document.getElementById('revealPhoneBtn').classList.add('hidden');
  document.getElementById('revealedPhone').classList.remove('hidden');
  var m = document.getElementById('revealedPhoneMobile');
  if(m){ m.classList.remove('hidden'); }
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
