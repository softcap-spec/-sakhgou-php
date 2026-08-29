<?php
// listing.php — v4 Avito-style
$lid = (int)($id ?? 0);
$pdo = db();

$stmt = $pdo->prepare("SELECT l.*, u.name AS host_name, u.avatar_url AS host_avatar, u.phone AS host_phone, u.seller_type AS host_seller_type, u.org_name AS host_org_name,
  (SELECT COUNT(*) FROM reviews WHERE listing_id = l.id AND moderated = 1) AS reviews_count,
  (SELECT AVG(rating) FROM reviews WHERE listing_id = l.id AND moderated = 1) AS avg_rating
  FROM listings l JOIN users u ON l.user_id = u.id WHERE l.id = ? AND l.status = ?");
$stmt->execute([$lid, 'active']);
$item = $stmt->fetch();

if (!$item) {
  $cu = auth_user();
  $stmt = $pdo->prepare('SELECT l.*, u.name AS host_name, u.avatar_url AS host_avatar, u.phone AS host_phone, u.seller_type AS host_seller_type, u.org_name AS host_org_name FROM listings l JOIN users u ON l.user_id = u.id WHERE l.id = ?');
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
// Дедупликация: одна запись просмотра на сессию в день (защита от накрутки обновлением)
if (($_SESSION['lst_view'][$lid] ?? '') !== date('Y-m-d')) {
  stats_incr($lid, 'view');
  $_SESSION['lst_view'][$lid] = date('Y-m-d');
}
$item['view_count'] = ($item['view_count'] ?? 0) + 1;

$stmt = $pdo->prepare('SELECT l.*, c.slug AS cat_slug FROM listings l JOIN categories c ON l.category_id = c.id LEFT JOIN promotions promo ON l.id = promo.listing_id AND promo.status = \'active\' AND promo.expires_at > NOW() WHERE l.listing_type = ? AND l.id != ? AND l.status = ? ORDER BY CASE WHEN promo.id IS NOT NULL THEN 0 ELSE 1 END, RAND() LIMIT 4');
$stmt->execute([$item['listing_type'] ?? 'tour', $lid, 'active']);
$similar = $stmt->fetchAll();

// Ещё предложения продавца (другие активные объявления этого же пользователя)
$stmt = $pdo->prepare("SELECT l.*, c.slug AS cat_slug,
  (SELECT filename FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1) AS s_image
  FROM listings l JOIN categories c ON l.category_id = c.id
  LEFT JOIN promotions promo ON l.id = promo.listing_id AND promo.status = 'active' AND promo.expires_at > NOW()
  WHERE l.user_id = ? AND l.id != ? AND l.status = 'active'
  ORDER BY CASE WHEN promo.id IS NOT NULL THEN 0 ELSE 1 END, l.created_at DESC LIMIT 4");
$stmt->execute([$item['user_id'], $lid]);
$sellerMore = $stmt->fetchAll();

// Рейтинг продавца — по отзывам на все его объявления
$stmt = $pdo->prepare("SELECT AVG(r.rating) AS seller_rating, COUNT(r.id) AS seller_reviews FROM reviews r JOIN listings l ON r.listing_id = l.id WHERE l.user_id = ? AND r.moderated = 1");
$stmt->execute([$item['user_id']]);
$sellerStats = $stmt->fetch();
$sellerIsOrg = in_array($item['tour_organizer_type'] ?? '', ['tour_operator', 'travel_agent'], true) || ($item['host_seller_type'] ?? 'private') === 'org';

$stmt = $pdo->prepare('SELECT r.*, u.name AS author_name, u.avatar_url AS author_avatar FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.listing_id = ? AND r.moderated = 1 ORDER BY r.created_at DESC LIMIT 10');
$stmt->execute([$lid]);
$reviews = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT filename FROM listing_images WHERE listing_id = ? ORDER BY sort_order');
$stmt->execute([$lid]);
$images = array_column($stmt->fetchAll(), 'filename');
if (empty($images) && !empty($item['cover_image'])) $images = [$item['cover_image']];
// OG-картинка объявления для соцсетей/мессенджеров
$og_image = !empty($images[0]) ? ('https://сахгоу.рф/uploads/' . rawurlencode($images[0])) : null;

$TYPE_LABEL = ['property'=>'Жильё','tour'=>'Туры','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто'];
$lt = $item['listing_type'] ?? 'tour';
$cu = auth_user();
$isFavorite = $cu ? is_favorite($cu['id'], $lid) : false;
$isOwner = $cu && ($cu['id'] == $item['user_id'] || $cu['role'] === 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fav'])) {
  csrf_check();
  if ($cu) { $isFavorite = toggle_favorite($cu['id'], $lid); header('Location: /listing/'.$lid); exit; }
}

// Review submission
$review_sent = false; $review_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review'])) {
  csrf_check();
  if ($cu && !$isOwner) {
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $rtext = trim($_POST['review_text'] ?? '');
    if (mb_strlen($rtext) < 5) {
      $review_error = 'Текст отзыва слишком короткий (минимум 5 символов)';
    } else {
      // One review per user per listing
      $dup = $pdo->prepare('SELECT id FROM reviews WHERE listing_id = ? AND user_id = ?');
      $dup->execute([$lid, $cu['id']]);
      if ($dup->fetch()) {
        $review_error = 'Вы уже оставляли отзыв на это объявление';
      } else {
        $pdo->prepare('INSERT INTO reviews (listing_id, user_id, rating, text, moderated, created_at) VALUES (?,?,?,?,0,NOW())')->execute([$lid, $cu['id'], $rating, $rtext]);
        $review_sent = true;
      }
    }
  }
}

// Booking submission
$book_sent = false; $book_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {
  csrf_check();
  if ($cu && !$isOwner) {
    $check_in = $_POST['check_in'] ?? '';
    $check_out = $_POST['check_out'] ?? '';
    $guests = max(1, (int)($_POST['guests'] ?? 1));
    $bmsg = trim($_POST['guest_message'] ?? '');
    if (($item['price_type'] ?? 'fixed') === 'negotiable') {
      $book_error = 'Стоимость по договорённости — уточните условия у автора';
    } elseif (!$check_in || !$check_out) {
      $book_error = 'Укажите даты заезда и выезда';
    } elseif (strtotime($check_out) <= strtotime($check_in)) {
      $book_error = 'Дата выезда должна быть позже даты заезда';
    } else {
      // Истёкшие «ожидающие» (48ч без ответа) освобождают даты
      bookings_expire_pendings($lid);
      // Check date overlap with existing active bookings (prevent double-booking)
      $conflict = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE listing_id = ? AND status IN ('pending','confirmed','blocked') AND check_in_date < ? AND check_out_date > ?");
      $conflict->execute([$lid, $check_out, $check_in]);
      if ((int)$conflict->fetchColumn() > 0) {
        $book_error = 'Эти даты уже забронированы';
      } else {
      $days = max(1, (int)((strtotime($check_out) - strtotime($check_in)) / 86400));
      $total = $days * (float)$item['price'];
      $pdo->prepare('INSERT INTO bookings (listing_id, guest_id, host_id, check_in_date, check_out_date, guests_count, status, total_price, guest_message, created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())')->execute([$lid, $cu['id'], $item['user_id'], $check_in, $check_out, $guests, 'pending', $total, $bmsg]);
      // Notify host (колокольчик)
      $pdo->prepare('INSERT INTO notifications (user_id, type, text, link, is_read, created_at) VALUES (?,?,?,?,0,NOW())')->execute([$item['user_id'], 'booking', 'Новая заявка на бронирование: '.$item['title'], '/dashboard?sub=host_bookings']);
      // Дублирование брони в чат — продавец видит заявку в «Сообщениях» и может ответить гостю
      $dates = date('d.m.Y', strtotime($check_in)) . ' — ' . date('d.m.Y', strtotime($check_out));
      $bookMsg = 'Новая бронь: ' . $item['title'] . "\n"
        . 'Даты: ' . $dates . ' · Гостей: ' . $guests . "\n"
        . 'Итого: ' . number_format($total, 0, '.', ' ') . ' ₽';
      if ($bmsg !== '') $bookMsg .= "\n\nСообщение гостя: " . $bmsg;
      send_message($cu['id'], $item['user_id'], $lid, $bookMsg);
      // Уведомление в «Макс» (MVP — оператору)
      try {
        max_notify_booking($item['user_id'], $item['title'], $cu['name'] ?? 'гость', $dates, $guests, number_format($total, 0, '.', ' ') . ' ₽');
      } catch (\Throwable $e) {}
      $book_sent = true;
      }
    }
  }
}

$page_title = h($item['title']) . ' — СахGO';
$page_description = h(mb_substr($item['description'] ?? $item['title'], 0, 160));
require __DIR__ . '/../includes/header.php';
?>

<main class="py-4">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

  <?php breadcrumbs(['Главная'=>'/', ($TYPE_LABEL[$lt]??'Каталог')=>'/catalog/'.$lt, h($item['title'])=>'']); ?>

  <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-6">

    <!-- LEFT COLUMN -->
    <div class="space-y-6 min-w-0">

      <!-- Title -->
      <div>
        <h1 class="font-display text-2xl sm:text-3xl leading-tight text-foreground"><?=h($item['title'])?></h1>
        <div class="flex items-center gap-3 mt-2 text-xs text-[#6B7B8D]">
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
            <img src="/uploads/<?=h($img)?>" alt="<?=h($item['title'])?> — фото <?=$i+1?>" class="w-full h-full object-cover" loading="lazy">
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
        <div class="font-display text-2xl"><?=price_text($item)?><?php if (!price_is_negotiable($item) && (float)$item['price'] > 0): ?> <span class="text-sm font-normal text-[#6B7B8D]"><?=price_label($item['listing_type'])?></span><?php endif; ?></div>
        <?php if(!empty($item['host_phone'])): ?>
        <button onclick="revealPhone()" class="inline-flex items-center gap-1.5 rounded-lg bg-accent text-white h-9 px-4 text-sm font-medium">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          Позвонить
        </button>
        <?php endif; ?>
      </div>
      <div id="revealedPhoneMobile" class="hidden lg:hidden text-center text-lg font-display mb-4"><a href="tel:<?=h(phone_display($item['host_phone']))?>" class="text-accent hover:underline"><?=h(phone_display($item['host_phone']))?></a></div>

      <!-- Description -->
      <div class="bg-white border border-[#EBEEF2] rounded-xl p-5">
        <h2 class="font-display text-lg mb-3">Описание</h2>
        <?php if (!empty($item['description'])): ?>
        <p class="text-[#3A4A5C] leading-relaxed whitespace-pre-line text-sm"><?=h($item['description'])?></p>
        <?php else: ?>
        <p class="text-[#6B7B8D] text-sm">Описание не указано</p>
        <?php endif; ?>
      </div>

      <!-- Specs -->
      <?php
      $specs = [];
      if ($lt === 'property') {
        foreach(['rooms_count'=>'Комнат','beds_count'=>'Кроватей','bathrooms_count'=>'Санузлов','area_sqm'=>'Площадь, м²','max_guests'=>'Макс. гостей','check_in_time'=>'Заезд','check_out_time'=>'Выезд','deposit_amount'=>'Депозит','cancellation_policy'=>'Отмена','transfer'=>'Трансфер'] as $k=>$l) { if (!empty($item[$k])) { $v = $item[$k]; if ($k==='transfer') $v = ['yes'=>'Да','no'=>'Нет','possible'=>'Возможен'][$v]??$v; $specs[$l] = $v; } }
      } elseif ($lt === 'tour') {
        foreach(['tour_duration_hours'=>'Длительность, ч','tour_duration_days'=>'Длительность, дн.','difficulty_level'=>'Сложность','group_size_min'=>'Мин. группа','group_size_max'=>'Макс. группа','start_point'=>'Точка старта','transport_included'=>'Транспорт','transport_type'=>'Тип транспорта','requires_border_permit'=>'Погранпропуск','depends_on_weather'=>'Зависит от погоды','meals_included'=>'Питание','season'=>'Сезон','transfer'=>'Трансфер'] as $k=>$l) { if (!empty($item[$k]) || $item[$k]==='0') { $v = $item[$k]; if ($k==='transport_included'||$k==='requires_border_permit'||$k==='depends_on_weather'||$k==='meals_included') { if (!$v) continue; $v='Да'; } if ($k==='difficulty_level') $v = ['easy'=>'Лёгкий','medium'=>'Средний','hard'=>'Сложный','extreme'=>'Экстремальный'][$v]??$v; if ($k==='season') $v = ['all_season'=>'Круглый год','summer'=>'Лето','winter'=>'Зима','spring'=>'Весна','autumn'=>'Осень'][$v]??$v; if ($k==='group_size_min'||$k==='group_size_max') $v = (int)$v . ' чел.'; if ($k==='transfer') $v = ['yes'=>'Да','no'=>'Нет','possible'=>'Возможен'][$v]??$v; $specs[$l]=$v; } }
        if (!empty($item['tour_organizer_type'])) {
          $orgMap = ['tour_operator'=>'Туроператор (в реестре)','travel_agent'=>'Турагент','individual'=>'Частное лицо'];
          $specs['Исполнитель услуги'] = $orgMap[$item['tour_organizer_type']] ?? $item['tour_organizer_type'];
          if (!empty($item['tour_operator_name'])) $specs['Туроператор'] = $item['tour_operator_name'];
          if (!empty($item['tour_operator_regno'])) $specs['Реестровый №'] = $item['tour_operator_regno'];
        }
      } elseif ($lt === 'fishing') {
        foreach(['fishing_type'=>'Тип рыбалки','fishing_method'=>'Метод ловли','group_size_max'=>'Макс. группа','season'=>'Сезон','transfer'=>'Трансфер'] as $k=>$l) { if (!empty($item[$k]) || $item[$k]==='0') { $v = $item[$k]; if ($k==='season') $v = ['all_season'=>'Круглый год','summer'=>'Лето','winter'=>'Зима','spring'=>'Весна','autumn'=>'Осень'][$v]??$v; if ($k==='group_size_max') $v = (int)$v . ' чел.'; if ($k==='fishing_type') $v = ['rechnaya'=>'Речная','morskaya'=>'Морская','ozernaya'=>'Озёрная','podlednaya'=>'Подлёдная','splav'=>'Сплав'][$v]??$v; if ($k==='fishing_method') $v = ['spin'=>'Спиннинг','fly'=>'Нахлыст','troll'=>'Троллинг','ice'=>'Зимняя','float'=>'Поплавочная'][$v]??$v; if ($k==='transfer') $v = ['yes'=>'Да','no'=>'Нет','possible'=>'Возможен'][$v]??$v; $specs[$l]=$v; } }
        foreach(['gear_included'=>'Снаряжение','catch_guarantee'=>'Гарантия улова','license_required'=>'Лицензия','boat_included'=>'Лодка'] as $k=>$l) { if (!empty($item[$k])) $specs[$l]='Да'; }
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
            <dt class="text-sm text-[#5A6B7D]"><?=$l?></dt>
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
      <div class="bg-white border border-[#EBEEF2] rounded-xl p-5" id="reviews">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-display text-lg">Отзывы</h2>
          <?php if($item['reviews_count']>0):?>
          <span class="text-sm text-[#5A6B7D]"><span class="text-amber-500"><?=$item['reviews_count']>0?'★ ':''?></span><?=round($item['avg_rating'],1)?> · <?=$item['reviews_count']?></span>
          <?php endif; ?>
        </div>
        <?php if(empty($reviews)): ?>
        <p class="text-sm text-[#6B7B8D]">Пока нет отзывов</p>
        <?php else: foreach($reviews as $r): ?>
        <div class="border-t border-[#F0F3F7] py-3.5">
          <div class="flex items-center gap-2 mb-1.5">
            <?= avatar_html(['name'=>$r['author_name'],'avatar_url'=>$r['author_avatar']??null], 'w-7 h-7', 'text-[0.6rem]') ?>
            <span class="text-sm font-medium"><?=h($r['author_name'])?></span>
            <span class="text-amber-500 text-xs"><?=str_repeat('★',(int)$r['rating'])?></span>
            <span class="text-xs text-[#6B7B8D] ml-auto"><?=time_ago($r['created_at'])?></span>
          </div>
          <p class="text-sm text-[#3A4A5C]"><?=h($r['text'])?></p>
        </div>
        <?php endforeach; endif; ?>

        <?php if ($review_sent): ?>
        <div class="mt-4 bg-[#F0FDF4] border border-[#BBF7D0] text-[#166534] rounded-lg px-4 py-3 text-sm">Спасибо! Отзыв отправлен на модерацию и появится после проверки.</div>
        <?php elseif ($review_error): ?>
        <div class="mt-4 bg-[#FEF2F2] border border-[#FECACA] text-[#DC2626] rounded-lg px-4 py-3 text-sm"><?=h($review_error)?></div>
        <?php endif; ?>

        <?php if ($cu && !$isOwner): ?>
        <div class="border-t border-[#F0F3F7] pt-4 mt-4">
          <h3 class="text-sm font-semibold mb-3">Оставить отзыв</h3>
          <form method="post" id="reviewForm" onsubmit="if(typeof ymGoal==='function')ymGoal('review')">
            <?= csrf_field() ?>
            <input type="hidden" name="rating" id="ratingVal" value="5">
            <div class="flex items-center gap-1 mb-3" id="starRating">
              <?php for ($i=1; $i<=5; $i++): ?>
              <button type="button" onclick="setRating(<?=$i?>)" id="star<?=$i?>" class="text-2xl leading-none text-amber-400" style="background:none;border:0;cursor:pointer;padding:0">★</button>
              <?php endfor; ?>
              <span class="text-xs text-[#5A6B7D] ml-2" id="ratingLabel">Отлично</span>
            </div>
            <textarea name="review_text" rows="3" placeholder="Поделитесь впечатлениями…" style="width:100%;box-sizing:border-box;border:1px solid #DFE4EA;border-radius:8px;padding:0.625rem 0.875rem;font-size:0.875rem;outline:none;font-family:inherit;resize:vertical"></textarea>
            <button type="submit" name="review" value="1" class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-accent text-white h-10 px-5 text-sm font-medium hover:bg-accent/90 transition-colors">Отправить отзыв</button>
          </form>
        </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- RIGHT COLUMN (sticky sidebar) -->
    <div class="space-y-4">
      <div class="lg:sticky lg:top-20 space-y-4">

        <!-- Price card -->
        <div class="bg-white border border-[#EBEEF2] rounded-xl p-5">
          <div class="font-display text-3xl text-foreground"><?=price_text($item)?><?php if (!price_is_negotiable($item) && (float)$item['price'] > 0): ?> <span class="text-sm font-normal text-[#6B7B8D]"><?=price_label($item['listing_type'])?></span><?php endif; ?></div>
          <?php if (!empty($item['location'])): ?>
          <div class="text-xs text-[#5A6B7D] mt-1.5 flex items-center gap-1">
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
            <div id="revealedPhone" class="hidden text-center font-display text-xl py-1"><a href="tel:<?=h(phone_display($item['host_phone']))?>" class="text-accent hover:underline"><?=h(phone_display($item['host_phone']))?></a></div>
            <?php endif; ?>

            <?php if($cu && !$isOwner): ?>
            <button onclick="openChatModal()" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-[#DFE4EA] hover:bg-[#F7F9FB] h-11 px-4 text-sm font-medium transition-colors">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              Написать сообщение
            </button>
            <?php if (in_array($lt, ['property','tour','fishing','car_rental','rental_gear']) && !price_is_negotiable($item)): ?>
            <button onclick="toggleBookForm()" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-accent text-white hover:bg-accent/90 h-11 px-4 text-sm font-semibold transition-colors">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              Забронировать
            </button>
            <div id="bookForm" class="hidden mt-2 border border-[#EBEEF2] rounded-xl p-4 bg-[#F7F9FB]">
              <?php if ($book_sent): ?>
              <div class="bg-[#F0FDF4] border border-[#BBF7D0] text-[#166534] rounded-lg px-3 py-2.5 text-xs">Заявка отправлена! Хозяин свяжется с вами.</div>
              <?php else: ?>
              <?php if ($book_error): ?><div class="bg-[#FEF2F2] border border-[#FECACA] text-[#DC2626] rounded-lg px-3 py-2.5 text-xs mb-2"><?=h($book_error)?></div><?php endif; ?>
              <form method="post" onsubmit="if(!availValidate())return false;if(typeof ymGoal==='function')ymGoal('booking')">
                <?= csrf_field() ?>
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="text-[0.65rem] text-[#5A6B7D] font-semibold block mb-1">Заезд</label>
                    <input type="date" name="check_in" id="bkIn" required class="w-full border border-[#DFE4EA] rounded-lg px-2.5 py-2 text-sm" style="box-sizing:border-box">
                  </div>
                  <div>
                    <label class="text-[0.65rem] text-[#5A6B7D] font-semibold block mb-1">Выезд</label>
                    <input type="date" name="check_out" id="bkOut" required class="w-full border border-[#DFE4EA] rounded-lg px-2.5 py-2 text-sm" style="box-sizing:border-box">
                  </div>
                </div>
                <div class="mt-2">
                  <label class="text-[0.65rem] text-[#5A6B7D] font-semibold block mb-1">Гостей</label>
                  <input type="number" name="guests" value="2" min="1" max="20" class="w-full border border-[#DFE4EA] rounded-lg px-2.5 py-2 text-sm" style="box-sizing:border-box">
                </div>
                <div class="mt-2">
                  <label class="text-[0.65rem] text-[#5A6B7D] font-semibold block mb-1">Сообщение хозяину</label>
                  <textarea name="guest_message" rows="2" placeholder="Добрый день! Интересуют даты…" class="w-full border border-[#DFE4EA] rounded-lg px-2.5 py-2 text-sm" style="box-sizing:border-box;resize:vertical;font-family:inherit"></textarea>
                </div>
                <div id="availCal" style="margin-top:12px"></div>
                <button type="submit" name="book" value="1" class="w-full mt-3 rounded-lg bg-accent text-white h-10 text-sm font-semibold hover:bg-accent/90 transition-colors">Отправить заявку</button>
              </form>
              <?php endif; ?>
            </div>
            <?php endif; ?>
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
          <a href="/seller/<?=(int)$item['user_id']?>" class="flex items-center gap-3 hover:bg-[#F7F9FB] -mx-2 -mt-2 p-2 rounded-lg transition-colors">
            <?= avatar_html(['name' => $item['host_name'], 'avatar_url' => $item['host_avatar']], 'w-11 h-11', 'text-sm') ?>
            <div class="min-w-0">
              <div class="font-semibold text-sm truncate"><?=h($item['host_name'])?></div>
              <div class="text-xs text-[#6B7B8D] truncate"><?=$sellerIsOrg ? 'Организация' : 'Частное лицо'?> · На сайте с <?=date('m.Y',strtotime($item['created_at']))?></div>
            </div>
          </a>
          <?php if ((int)($sellerStats['seller_reviews'] ?? 0) > 0): $rc = (int)$sellerStats['seller_reviews']; $rv_plural = $rc % 10 == 1 && $rc % 100 != 11 ? 'отзыв' : ($rc % 10 >= 2 && $rc % 10 <= 4 && ($rc % 100 < 12 || $rc % 100 > 14) ? 'отзыва' : 'отзывов'); ?>
          <div class="mt-3 pt-3 border-t border-[#F0F3F7] flex items-center justify-between text-sm gap-2">
            <span class="text-[#5A6B7D]">Рейтинг</span>
            <span class="flex items-center gap-1.5">
              <span style="position:relative;display:inline-block;font-size:0.875rem;line-height:1;color:#D5DCE5;letter-spacing:0.08em">★★★★★<span style="position:absolute;top:0;left:0;overflow:hidden;white-space:nowrap;color:#F59E0B;width:<?=round((float)$sellerStats['seller_rating']/5*100)?>%">★★★★★</span></span>
              <a href="#reviews" class="text-accent hover:underline whitespace-nowrap"><?=$rc?> <?=$rv_plural?></a>
            </span>
          </div>
          <?php endif; ?>
        </div>

        <!-- Complete your trip -->
        <?php
        $cross = ['property'=>['car_rental','tour','fishing','rental_gear'],'tour'=>['car_rental','property','fishing','rental_gear'],'fishing'=>['car_rental','tour','rental_gear','property'],'rental_gear'=>['car_rental','tour','fishing','property'],'car_rental'=>['tour','fishing','rental_gear','property']];
        $ct = $cross[$lt] ?? ['tour','property','fishing','rental_gear'];
        $ct_placeholders = implode(',', array_fill(0, count($ct), '?'));
        $ct_items = $pdo->prepare("SELECT l.id, l.title, l.price, l.price_type, l.listing_type, (SELECT filename FROM listing_images WHERE listing_id=l.id ORDER BY sort_order LIMIT 1) AS img, c.name AS cat_name, c.slug AS cat_slug FROM listings l JOIN categories c ON l.category_id=c.id LEFT JOIN promotions promo ON l.id = promo.listing_id AND promo.status = 'active' AND promo.expires_at > NOW() WHERE l.status='active' AND l.id!=? AND c.slug IN ($ct_placeholders) ORDER BY CASE WHEN promo.id IS NOT NULL THEN 0 ELSE 1 END, RAND() LIMIT 4");
        $ct_items->execute(array_merge([$lid], $ct));
        $crossItems = $ct_items->fetchAll();
        ?>
        <?php if (!empty($crossItems)): ?>
        <div class="bg-white border border-[#EBEEF2] rounded-xl p-4">
          <h3 class="font-display text-sm mb-3">Для вашего путешествия</h3>
          <div class="space-y-3">
            <?php foreach ($crossItems as $ci): ?>
            <a href="/listing/<?=$ci['id']?>" class="flex gap-3 group">
              <div class="w-16 h-16 rounded-lg overflow-hidden bg-[#EEF2F6] shrink-0">
                <?php if ($ci['img']): ?>
                <img src="/uploads/<?=h($ci['img'])?>" alt="<?=h($ci['title'])?>" class="w-full h-full object-cover" loading="lazy">
                <?php else: ?>
                <div class="w-full h-full flex items-center justify-center text-[#C8D0DA]"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
                <?php endif; ?>
              </div>
              <div class="min-w-0 flex-1">
                <div class="text-xs text-[#5A6B7D]"><?=h($ci['cat_name'])?></div>
                <div class="text-sm font-medium text-[#3A4A5C] truncate group-hover:text-accent transition-colors leading-snug"><?=h($ci['title'])?></div>
                <div class="text-sm font-semibold text-foreground mt-0.5"><?=price_text($ci)?><?php if (!price_is_negotiable($ci) && (float)$ci['price'] > 0): ?> <span class="text-[0.625rem] font-normal text-[#6B7B8D]"><?=price_label($ci['listing_type'])?></span><?php endif; ?></div>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Safety note -->
        <div class="bg-[#F7F9FB] border border-[#EBEEF2] rounded-xl p-4">
          <div class="flex gap-2.5">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0A7BBA" stroke-width="2" class="shrink-0 mt-0.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
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
          <?php $simgStmt = $pdo->prepare("SELECT filename FROM listing_images WHERE listing_id = ? ORDER BY sort_order LIMIT 1"); $simgStmt->execute([$s['id']]); $simg = $simgStmt->fetchColumn(); ?>
          <?php if($simg): ?><img src="/uploads/<?=h($simg)?>" alt="<?=h($s['title'])?>" loading="lazy"><?php endif; ?>
        </div>
        <div class="listing-body">
          <div class="listing-price"><?=price_text($s)?><?php if (!price_is_negotiable($s) && (float)$s['price'] > 0): ?> <span class="text-[0.625rem] font-normal text-[#6B7B8D]"><?=price_label($s['listing_type'])?></span><?php endif; ?></div>
          <div class="listing-title"><?=h($s['title'])?></div>
          <div class="listing-meta"><span><?=h($s['location'])?></span></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- More from seller -->
<?php if (!empty($sellerMore)): ?>
<section class="py-10 bg-white border-t border-[#EBEEF2]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <h2 class="font-display text-xl mb-6 flex items-center justify-between gap-3">Ещё предложения продавца
        <a href="/seller/<?=(int)$item['user_id']?>" class="text-sm font-medium text-accent hover:underline whitespace-nowrap">Все объявления →</a>
      </h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
      <?php foreach($sellerMore as $s2): ?>
      <a href="/listing/<?=$s2['id']?>" class="listing-card">
        <div class="listing-img">
          <?php if($s2['s_image']): ?><img src="/uploads/<?=h($s2['s_image'])?>" alt="<?=h($s2['title'])?>" loading="lazy"><?php endif; ?>
        </div>
        <div class="listing-body">
          <div class="listing-price"><?=price_text($s2)?><?php if (!price_is_negotiable($s2) && (float)$s2['price'] > 0): ?> <span class="text-[0.625rem] font-normal text-[#6B7B8D]"><?=price_label($s2['listing_type'])?></span><?php endif; ?></div>
          <div class="listing-title"><?=h($s2['title'])?></div>
          <div class="listing-meta"><span><?=h($s2['location'])?></span></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Chat Modal -->
<?php if ($cu && !$isOwner): ?>
<style>
#chatModal .cm-msgs{flex:1;overflow-y:auto;padding:.75rem 1rem;display:flex;flex-direction:column;gap:.125rem;background:#fff}
#chatModal .cm-row{display:flex;max-width:80%;position:relative;gap:.5rem;align-items:flex-end}
#chatModal .cm-row.out{align-self:flex-end;align-items:flex-end;flex-direction:row-reverse;position:relative}
#chatModal .cm-row.in{align-self:flex-start;align-items:flex-start;position:relative}
#chatModal .cm-row.continues{margin-top:0}
#chatModal .cm-col{display:flex;flex-direction:column;min-width:0}
#chatModal .cm-row.out .cm-col{align-items:flex-end}
#chatModal .cm-row.in .cm-col{align-items:flex-start}
#chatModal .cm-bubble{padding:.5rem .875rem;border-radius:16px;font-size:.875rem;line-height:1.4;word-wrap:break-word;position:relative;max-width:100%;transition:box-shadow .15s}
#chatModal .cm-row.out .cm-bubble{background:#EAF6FF;color:#0A1A2A;border-bottom-right-radius:5px;box-shadow:0 1px 2px rgba(10,123,186,0.08)}
#chatModal .cm-row.in .cm-bubble{background:#F4F6F8;color:#0A1A2A;border-bottom-left-radius:5px;box-shadow:0 1px 2px rgba(10,26,42,0.04)}
#chatModal .cm-row.continues.out .cm-bubble{border-bottom-right-radius:16px}
#chatModal .cm-row.continues.in .cm-bubble{border-bottom-left-radius:16px}
#chatModal .cm-meta{display:flex;align-items:center;gap:.3125rem;margin-top:.1875rem;font-size:.6875rem;color:#B8C2CC;padding:0 .25rem;white-space:nowrap}
#chatModal .cm-meta-sep{color:#D1DAE3;font-size:.625rem}
#chatModal .cm-status{font-size:.6875rem;color:#B8C2CC;line-height:1;white-space:nowrap}
#chatModal .cm-status.read{color:#00B04C}
#chatModal .cm-avatar{width:1.75rem;height:1.75rem;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.6875rem;font-weight:700;overflow:hidden;flex-shrink:0;align-self:flex-end;letter-spacing:-.02em}
#chatModal .cm-avatar.c0{background:#E3F2FD;color:#1565C0}
#chatModal .cm-avatar.c1{background:#F3E5F5;color:#7B1FA2}
#chatModal .cm-avatar.c2{background:#E8F5E9;color:#2E7D32}
#chatModal .cm-avatar.c3{background:#FFF3E0;color:#E65100}
#chatModal .cm-avatar.c4{background:#E0F7FA;color:#00838F}
#chatModal .cm-avatar.c5{background:#FCE4EC;color:#C62828}
#chatModal .cm-avatar img{width:100%;height:100%;object-fit:cover}
#chatModal .cm-row.continues .cm-avatar{visibility:hidden}
#chatModal .cm-actions{display:none;position:absolute;top:-8px;right:-8px;z-index:2}
#chatModal .cm-col:hover .cm-actions{display:block}
#chatModal .cm-del{width:20px;height:20px;border:0;border-radius:50%;background:#DC2626;color:#fff;font-size:14px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 4px rgba(0,0,0,0.15);transition:all .15s}
#chatModal .cm-del:hover{background:#EEF2F6;color:#0A1A2A}
#chatModal .cm-act-menu{position:absolute;top:28px;right:-4px;background:#fff;border:1px solid #D1DAE3;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.1);z-index:10;display:none;min-width:140px;overflow:hidden}
#chatModal .cm-act-menu.open{display:block}
#chatModal .cm-act-item{display:block;width:100%;padding:.5rem .75rem;font-size:.8125rem;color:#DC2626;background:none;border:0;cursor:pointer;text-align:left}
#chatModal .cm-act-item:hover{background:#FEF2F2}
#chatModal .cm-date{text-align:center;font-size:.6875rem;color:#6B7B8D;margin:.5rem 0;padding:.25rem .5rem;background:#F7F9FB;border-radius:8px;align-self:center}
</style>
<div id="chatModal" class="fixed inset-0 z-[100] hidden" style="background:rgba(15,23,32,0.4)">
  <div class="absolute inset-0" onclick="closeChatModal()"></div>
  <div class="absolute bottom-0 right-0 sm:bottom-6 sm:right-6 w-full sm:w-96 h-[70vh] sm:h-[34rem] bg-white sm:rounded-2xl flex flex-col overflow-hidden shadow-2xl">
    <!-- Header -->
    <div class="flex items-center justify-between px-4 py-3 border-b border-[#EBEEF2]">
      <div class="flex items-center gap-2.5 min-w-0">
        <?= avatar_html(['name'=>$item['host_name'],'avatar_url'=>$item['host_avatar']], 'w-8 h-8', 'text-xs') ?>
        <div class="min-w-0">
          <div class="text-sm font-semibold truncate"><?=h($item['host_name'])?></div>
          <div class="text-xs text-[#6B7B8D] truncate"><?=h($item['title'])?></div>
        </div>
      </div>
      <button onclick="closeChatModal()" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#5A6B7D] hover:bg-[#F7F9FB] hover:text-foreground transition-colors">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <!-- Messages -->
    <div id="cmMessages" class="cm-msgs">
      <div style="text-align:center;color:#6B7B8D;font-size:.875rem;padding:2rem 0">Загрузка...</div>
    </div>
    <!-- Input -->
    <div style="border-top:1px solid #EBEEF2;padding:.5rem .75rem;display:flex;gap:.375rem;align-items:center;background:#fff">
      <div style="flex:1;position:relative">
        <input type="text" id="cmInput" placeholder="Сообщение..." style="width:100%;border:1px solid #DFE4EA;border-radius:22px;padding:.5rem 1rem;font-size:.875rem;outline:none;background:#F7F9FB" onkeydown="if(event.key==='Enter')cmSend()" oninput="cmTyping()">
      </div>
      <button onclick="cmSend()" style="width:2.5rem;height:2.5rem;border:0;border-radius:50%;background:#0A7BBA;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
      </button>
    </div>
  </div>
</div>
<script>
var cmLid = <?=$lid?>;
var cmCsrf = <?=json_encode(csrf_token())?>;
var cmUid = <?=json_encode($cu['id'])?>;
var cmHost = <?=json_encode((int)$item['user_id'])?>;
var cmPoll = null;
var cmTypingTimer = null;
var cmOtherName = <?=json_encode($item['host_name'] ?? '')?>;
var cmOtherAvatar = <?=json_encode($item['host_avatar'] ?? '')?>;

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
  fetch('/api/messages?lid=' + cmLid + '&uid=' + cmHost + '&_=' + Date.now())
    .then(function(r){return r.json()})
    .then(function(data){
      var box = document.getElementById('cmMessages');
      if (!data.messages || data.messages.length === 0) {
        box.innerHTML = '<div style="text-align:center;color:#6B7B8D;font-size:.875rem;padding:2rem 0">Напишите первое сообщение</div>';
        return;
      }
      var html = '', lastDate = '', lastSender = 0, lastDir = '';
      for (var i=0; i<data.messages.length; i++) {
        var m = data.messages[i];
        var d = new Date(m.created_at.replace(/-/g,'/'));
        var dateStr = d.toLocaleDateString('ru-RU',{weekday:'long',day:'numeric',month:'long'});
        if (dateStr !== lastDate) { html += '<div class="cm-date">'+dateStr+'</div>'; lastDate = dateStr; lastSender = 0; }
        var mine = (parseInt(m.sender_id) === cmUid);
        var time = d.toLocaleTimeString('ru-RU',{hour:'2-digit',minute:'2-digit'});
        var isDeleted = (m.is_deleted==1||m.is_deleted==='1'||parseInt(m.is_deleted)===1);
        var continues = (parseInt(m.sender_id) === lastSender && lastDir === (mine?'out':'in'));
        lastSender = parseInt(m.sender_id); lastDir = mine ? 'out' : 'in';
        html += '<div class="cm-row '+(mine?'out':'in')+(continues?' continues':'')+'">';
        if (isDeleted) {
          html += '<div style="padding:.5rem .875rem;border-radius:16px;font-size:.8125rem;color:#9AA5B1;font-style:italic;background:#F4F6F8;border-bottom-left-radius:5px;max-width:60%">Сообщение удалено</div>';
          html += '</div>';
          continue;
        }
        /* Avatar for incoming — colored by name hash */
        if(!mine){
          var ch=0;for(var j=0;j<cmOtherName.length;j++)ch=(ch*31+cmOtherName.charCodeAt(j))>>>0;
          html+='<div class="cm-avatar c'+(ch%6)+'">';
          if(cmOtherAvatar){html+='<img src="'+escapeHtml(cmOtherAvatar)+'" alt="">'}
          else{html+=escapeHtml(cmOtherName.substring(0,2))}
          html+='</div>';
        }
        html += '<div class="cm-col">';
        if (mine) html += '<div class="cm-actions"><button class="cm-del" onclick="cmToggleAct(event,'+m.id+')" title="Действия">&#8943;</button><div class="cm-act-menu" id="cmAct'+m.id+'"><button class="cm-act-item" onclick="cmDelete('+m.id+')">Удалить</button></div></div>';
        html += '<div class="cm-bubble">'+escapeHtml(m.text)+'</div>';
        html += '<div class="cm-meta"><span>'+time+'</span>';
        if (mine) {
          var read = (m.is_read==1||m.is_read==='1'||m.is_read===true||parseInt(m.is_read)===1);
          html += '<span class="cm-meta-sep">·</span><span class="cm-status'+(read?' read':'')+'">'+(read?'Прочитано':'Доставлено')+'</span>';
        }
        html += '</div></div></div>';
      }
      box.innerHTML = html;
      box.scrollTop = box.scrollHeight;
    })
    .catch(function(){});
}
function cmTyping() {
  if (cmTypingTimer) clearTimeout(cmTypingTimer);
  cmTypingTimer = setTimeout(function(){}, 500);
  fetch('/api/typing?_='+Date.now(), {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'lid='+cmLid+'&_csrf='+encodeURIComponent(cmCsrf)}).catch(function(){});
}
function cmSend() {
  var input = document.getElementById('cmInput');
  var text = input.value.trim();
  if (!text) return;
  input.value = '';
  input.disabled = true;
  fetch('/api/send?_='+Date.now(), {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'lid=' + cmLid + '&text=' + encodeURIComponent(text) + '&_csrf=' + encodeURIComponent(cmCsrf)
  })
  .then(function(r){return r.json()})
  .then(function(data){
    input.disabled = false;
    input.focus();
    if (data.ok) { cmLoad(); if(typeof ymGoal==='function')ymGoal('send_message'); }
    else { alert('Ошибка отправки'); }
  })
  .catch(function(){ input.disabled = false; });
}
function escapeHtml(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}

function cmToggleAct(e,mid){e.stopPropagation();var m=document.getElementById('cmAct'+mid);if(!m)return;var isOpen=m.classList.contains('open');document.querySelectorAll('.cm-act-menu.open').forEach(function(x){x.classList.remove('open')});if(!isOpen)m.classList.add('open')}

function cmDelete(mid){
  document.querySelectorAll('.cm-act-menu.open').forEach(function(x){x.classList.remove('open')});
  fetch('/api/delete',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'mid='+mid+'&_csrf='+encodeURIComponent(cmCsrf)}).then(function(r){return r.json()}).then(function(d){if(d.ok)cmLoad()});
}
</script>
<?php endif; ?>

<?php
// JSON-LD Product markup
$ld_image = !empty($images) ? 'https://сахгоу.рф/uploads/'.h($images[0]) : '';
$ld_price = price_is_negotiable($item) ? '0' : number_format((float)$item['price'], 0, '.', '');
$ld_currency = 'RUB';
$ld_avail = 'https://schema.org/InStock';
?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "<?=h($item['title'])?>",
  "description": "<?=h(mb_substr($item['description'] ?? $item['title'], 0, 300))?>",
  "image": "<?=$ld_image?>",
  "sku": "SAKHGO-<?=$item['id']?>",
  "offers": {
    "@type": "Offer",
    "price": "<?=$ld_price?>",
    "priceCurrency": "<?=$ld_currency?>",
    "availability": "<?=$ld_avail?>",
    "url": "https://сахгоу.рф/listing/<?=$item['id']?>"
  }<?php if ($item['avg_rating'] && $item['reviews_count']): ?>,
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "<?=round($item['avg_rating'],1)?>",
    "reviewCount": "<?=$item['reviews_count']?>"
  }<?php endif; ?>
}
</script>
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
  try {
    fetch('/stats-hit', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: 'lid=<?=$lid?>&event=phone'});
  } catch(e){}
}
function setRating(n){
  document.getElementById('ratingVal').value=n;
  var labels=['Ужасно','Плохо','Нормально','Хорошо','Отлично'];
  document.getElementById('ratingLabel').textContent=labels[n-1];
  for(var i=1;i<=5;i++){
    var s=document.getElementById('star'+i);
    s.style.color=i<=n?'#F59E0B':'#D1DAE3';
  }
}
function toggleBookForm(){
  var f=document.getElementById('bookForm');
  if(f){ f.classList.toggle('hidden'); }
}
</script>
<script>
(function(){
  var BUSY = [];
  var wrap = document.getElementById('availCal');
  var MONTHS = ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];
  function fmt(d){var m=d.getMonth()+1,dd=d.getDate();return d.getFullYear()+'-'+(m<10?'0':'')+m+'-'+(dd<10?'0':'')+dd}
  function busy(ds){for(var i=0;i<BUSY.length;i++){if(ds>=BUSY[i].from&&ds<BUSY[i].to)return BUSY[i].status}return null}
  function render(){
    if(!wrap)return;
    var html='<div style="font-size:0.65rem;font-weight:600;color:#5A6B7D;margin-bottom:6px">Занятые даты — зачёркнуты:</div><div style="display:flex;gap:14px;flex-wrap:wrap">';
    var base=new Date();base.setDate(1);
    for(var mi=0;mi<2;mi++){
      var first=new Date(base.getFullYear(),base.getMonth()+mi,1);
      var y=first.getFullYear(),m=first.getMonth();
      var dim=new Date(y,m+1,0).getDate();
      var dow=(new Date(y,m,1).getDay()+6)%7;
      html+='<div style="flex:1;min-width:180px"><div style="font-size:0.75rem;font-weight:700;color:#121E2B;margin-bottom:4px">'+MONTHS[m]+' '+y+'</div><div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;font-size:0.62rem;text-align:center">';
      var DOW=['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
      for(var i=0;i<7;i++)html+='<div style="color:#9AAAB8">'+DOW[i]+'</div>';
      for(var i=0;i<dow;i++)html+='<div></div>';
      for(var day=1;day<=dim;day++){
        var ds=y+'-'+String(m+1).padStart(2,'0')+'-'+String(day).padStart(2,'0');
        var st=busy(ds),past=ds<fmt(new Date());
        var css='padding:3px 0;border-radius:6px;';
        if(past)css+='color:#C8D0DA;';
        else if(st==='confirmed')css+='background:#F8D7DA;color:#842029;text-decoration:line-through;font-weight:600;';
        else if(st==='blocked')css+='background:#E2E8EE;color:#54677A;text-decoration:line-through;';
        else if(st==='pending')css+='background:#FFF3CD;color:#8a6d00;';
        html+='<div style="'+css+'">'+day+'</div>';
      }
      html+='</div></div>';
    }
    html+='</div>';
    wrap.innerHTML=html;
  }
  window.availValidate=function(){
    var fi=document.getElementById('bkIn'),fo=document.getElementById('bkOut');
    if(!fi||!fo||!fi.value||!fo.value)return true;
    if(fo.value<=fi.value){alert('Дата выезда должна быть позже даты заезда');return false}
    var d=new Date(fi.value+'T00:00:00');
    while(fmt(d)<fo.value){
      var ds=fmt(d),st=busy(ds);
      if(st){alert(st==='pending'?'Эти даты сейчас резервируются другим гостем — ждём подтверждения. Попробуйте другие даты.':'Извините, даты '+ds+' уже заняты. Выберите другие.');return false}
      d.setDate(d.getDate()+1);
    }
    return true;
  };
  fetch('/availability?lid=<?=$lid?>').then(function(r){return r.json()}).then(function(d){BUSY=d.busy||[];render()}).catch(function(){});
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
