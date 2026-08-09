<?php
// listing.php — Tailwind, исправлено
$lid = (int)($id ?? 0);
$pdo = db();

// Получение объявления
$stmt = $pdo->prepare("SELECT l.*, u.name AS host_name, u.avatar_url AS host_avatar, u.phone AS host_phone,
  (SELECT COUNT(*) FROM reviews WHERE listing_id = l.id AND moderated = 1) AS reviews_count,
  (SELECT AVG(rating) FROM reviews WHERE listing_id = l.id AND moderated = 1) AS avg_rating
  FROM listings l JOIN users u ON l.user_id = u.id WHERE l.id = ? AND l.status = ?");
$stmt->execute([$lid, 'active']);
$item = $stmt->fetch();

if (!$item) {
  $cu = auth_user();
  // Allow owner and admin to view pending/inactive listings
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

// Счётчик просмотров
$pdo->prepare('UPDATE listings SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?')->execute([$lid]);
$item['view_count'] = ($item['view_count'] ?? 0) + 1;

// Похожие
$stmt = $pdo->prepare('SELECT l.*, c.slug AS cat_slug FROM listings l JOIN categories c ON l.category_id = c.id WHERE l.listing_type = ? AND l.id != ? AND l.status = ? ORDER BY RAND() LIMIT 3');
$stmt->execute([$item['listing_type'] ?? 'tour', $lid, 'active']);
$similar = $stmt->fetchAll();

// Отзывы
$stmt = $pdo->prepare('SELECT r.*, u.name AS author_name, u.avatar_url AS author_avatar FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.listing_id = ? AND r.moderated = 1 ORDER BY r.created_at DESC LIMIT 10');
$stmt->execute([$lid]);
$reviews = $stmt->fetchAll();

// Фото — ИСПРАВЛЕНО: image_url → filename
$stmt = $pdo->prepare('SELECT filename FROM listing_images WHERE listing_id = ? ORDER BY sort_order');
$stmt->execute([$lid]);
$images = array_column($stmt->fetchAll(), 'filename');
if (empty($images) && !empty($item['cover_image'])) $images = [$item['cover_image']];

// Константы
$TYPE_BG = ['property'=>'linear-gradient(135deg,#C5D5E4,#8FB0C8,#5A8AA8)','tour'=>'linear-gradient(135deg,#D4CBB8,#B5A080,#8B7250)','fishing'=>'linear-gradient(135deg,#70A8B0,#388890,#186068)','rental_gear'=>'linear-gradient(135deg,#C8C0B8,#A09888,#686050)','car_rental'=>'linear-gradient(135deg,#B8C8D0,#688898,#385060)',];
$TYPE_LABEL = ['property'=>'Жильё','tour'=>'Тур','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто'];
$TYPE_EMOJI = ['property'=>'🏠','tour'=>'🏔️','fishing'=>'🎣','rental_gear'=>'🔧','car_rental'=>'🚗'];

// price_label() in functions.php handles unit display

$lt = $item['listing_type'] ?? 'tour';
$cu = auth_user();
$isFavorite = $cu ? is_favorite($cu['id'], $lid) : false;
$isOwner = $cu && ($cu['id'] == $item['user_id'] || $cu['role'] === 'admin');

// POST: favorite toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fav'])) {
  csrf_check();
  if ($cu) { $isFavorite = toggle_favorite($cu['id'], $lid); header('Location: /listing/'.$lid); exit; }
}

$page_title = h($item['title']) . ' — СахGO';
require __DIR__ . '/../includes/header.php';
?>

<main>
<!-- Hero image + gallery -->
<section class="relative bg-black/5">
  <div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground mb-4">
      <a href="/" class="hover:text-foreground transition-colors">Главная</a><span>/</span>
      <a href="/catalog/<?=$lt?>" class="hover:text-foreground transition-colors"><?=$TYPE_LABEL[$lt]??'Каталог'?></a><span>/</span>
      <span class="text-foreground truncate max-w-[300px]"><?=h($item['title'])?></span>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-6">
      <!-- Галерея -->
      <div>
        <?php if (!empty($images)): ?>
        <div class="rounded-xl overflow-hidden border bg-black/5">
          <img src="/uploads/<?=h($images[0])?>" alt="<?=h($item['title'])?>" class="w-full aspect-[16/9] object-cover">
        </div>
        <?php if (count($images) > 1): ?>
        <div class="grid grid-cols-4 sm:grid-cols-6 gap-2 mt-3">
          <?php foreach (array_slice($images, 1, 6) as $i => $img): ?>
          <div class="aspect-square rounded-lg overflow-hidden border cursor-pointer hover:ring-2 ring-accent transition-all">
            <img src="/uploads/<?=h($img)?>" alt="" class="w-full h-full object-cover" loading="lazy">
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="rounded-xl overflow-hidden border bg-secondary flex items-center justify-center aspect-[16/9] text-6xl">📷</div>
        <?php endif; ?>
      </div>
      <!-- Sidebar -->
      <div class="lg:sticky lg:top-24 self-start">
        <div class="bg-white border border-border/60 rounded-2xl p-7 space-y-5 shadow-[0_8px_30px_-8px_rgba(18,30,43,0.08)]">
          <div class="pb-4 border-b border-border/30">
            <div class="font-display text-3xl text-foreground"><?=number_format((float)$item['price'],0,'.',' ')?> <span class="text-base font-medium text-muted-foreground"><?=price_label($item['listing_type'])?></span></div>
          </div>
          <div class="space-y-1 text-sm"><span class="text-muted-foreground">Тип:</span> <?=$TYPE_EMOJI[$lt]??''?> <?=$TYPE_LABEL[$lt]??''?></div>
          <?php if (!empty($item['location'])): ?>
          <div class="space-y-1 text-sm"><span class="text-muted-foreground">Локация:</span> <?=h($item['location'])?></div>
          <?php endif; ?>
          <?php if (!empty($item['max_guests'])): ?>
          <div class="space-y-1 text-sm"><span class="text-muted-foreground">Гостей:</span> до <?=$item['max_guests']?></div>
          <?php endif; ?>
          <?php if (!empty($item['rooms_count'])): ?>
          <div class="space-y-1 text-sm"><span class="text-muted-foreground">Комнат:</span> <?=$item['rooms_count']?></div>
          <?php endif; ?>
          <?php if (!empty($item['view_count'])): ?>
          <div class="space-y-1 text-sm"><span class="text-muted-foreground">Просмотров:</span> <?=$item['view_count']?></div>
          <?php endif; ?>

          <?php if ($cu): ?>
          <div class="flex gap-2 pt-2">
            <form method="post" class="flex-1">
              <?= csrf_field() ?>
              <button name="fav" value="1" class="w-full inline-flex items-center justify-center rounded-lg border <?=$isFavorite?'bg-red-50 border-red-200 text-red-600':'border-border hover:bg-muted'?> h-10 px-4 text-sm font-medium transition-all"><?=$isFavorite?'♥ В избранном':'♡ В избранное'?></button>
            </form>
            <?php if (!$isOwner): ?>
            <a href="/inbox?listing=<?=$lid?>" class="flex-1 inline-flex items-center justify-center rounded-lg bg-accent text-white hover:bg-accent/80 h-10 px-4 text-sm font-medium transition-all">✉️ Написать</a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if ($isOwner): ?>
          <a href="/edit/<?=$lid?>" class="w-full inline-flex items-center justify-center rounded-lg border border-border hover:bg-muted h-10 px-4 text-sm font-medium transition-all">✏️ Редактировать</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Описание + характеристики -->
<section class="py-12">
  <div class="max-w-7xl mx-auto px-4">
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-10">
      <div class="space-y-10">
        <div>
          <h2 class="font-display text-2xl mb-4">Описание</h2>
          <?php if (!empty($item['description'])): ?>
          <p class="text-muted-foreground leading-relaxed whitespace-pre-line"><?=h($item['description'])?></p>
          <?php else: ?>
          <p class="text-muted-foreground italic">Описание не указано</p>
          <?php endif; ?>
        </div>

        <!-- Характеристики по типу -->
        <?php
        $specs = [];
        if ($lt === 'property') {
          foreach(['rooms_count'=>'Комнат','beds_count'=>'Кроватей','bathrooms_count'=>'Санузлов','area_sqm'=>'Площадь, м²','max_guests'=>'Макс. гостей','check_in_time'=>'Заезд','check_out_time'=>'Выезд','deposit_amount'=>'Депозит','cancellation_policy'=>'Отмена'] as $k=>$l) { if (!empty($item[$k])) $specs[$l] = $item[$k]; }
        } elseif ($lt === 'tour') {
          foreach(['tour_duration_hours'=>'Длительность, ч','tour_duration_days'=>'Длительность, дн.','difficulty_level'=>'Сложность','group_size_min'=>'Мин. группа','group_size_max'=>'Макс. группа','start_point'=>'Точка старта','transport_included'=>'Транспорт вкл.','transport_type'=>'Тип транспорта','requires_border_permit'=>'Погранпропуск','depends_on_weather'=>'Зависит от погоды','meals_included'=>'Питание вкл.','season'=>'Сезон'] as $k=>$l) { if (!empty($item[$k]) || $item[$k]==='0') { $v = $item[$k]; if ($k==='transport_included'||$k==='requires_border_permit'||$k==='depends_on_weather'||$k==='meals_included') $v=$v?'Да':'Нет'; if ($k==='difficulty_level') $v = ['easy'=>'Лёгкий','medium'=>'Средний','hard'=>'Сложный','extreme'=>'Экстремальный'][$v]??$v; $specs[$l]=$v; } }
        } elseif ($lt === 'fishing') {
          foreach(['fishing_type'=>'Тип рыбалки','fishing_method'=>'Метод ловли','gear_included'=>'Снаряжение вкл.','catch_guarantee'=>'Гарантия улова','license_required'=>'Лицензия','boat_included'=>'Лодка вкл.','group_size_max'=>'Макс. группа','season'=>'Сезон'] as $k=>$l) { if (!empty($item[$k]) || $item[$k]==='0') { $v = $item[$k]; if ($k==='gear_included'||$k==='catch_guarantee'||$k==='license_required'||$k==='boat_included') $v=$v?'Да':'Нет'; $specs[$l]=$v; } }
        } elseif ($lt === 'rental_gear') {
          foreach(['gear_condition'=>'Состояние','deposit_amount'=>'Депозит'] as $k=>$l) { if (!empty($item[$k])) $specs[$l]=$item[$k]; }
        }
        ?>
        <?php if (!empty($specs)): ?>
        <div>
          <h2 class="font-display text-2xl mb-4">Характеристики</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?php foreach($specs as $l=>$v): ?>
            <div class="flex justify-between py-2 border-b border-border/50"><span class="text-muted-foreground"><?=$l?></span><span class="font-medium"><?=h((string)$v)?></span></div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Amenities -->
        <?php $amen = json_decode($item['amenities']??'[]',true)?:[]; if(!empty($amen)): ?>
        <div><h2 class="font-display text-2xl mb-4">Удобства</h2><div class="flex flex-wrap gap-2"><?php foreach($amen as $a):?><span class="badge text-xs"><?=h($a)?></span><?php endforeach;?></div></div>
        <?php endif; ?>

        <!-- Includes -->
        <?php $inc = json_decode($item['includes']??'[]',true)?:[]; if(!empty($inc)): ?>
        <div><h2 class="font-display text-2xl mb-4">Включено</h2><div class="flex flex-wrap gap-2"><?php foreach($inc as $a):?><span class="badge text-xs">✅ <?=h($a)?></span><?php endforeach;?></div></div>
        <?php endif; ?>

        <!-- Отзывы -->
        <div>
          <div class="flex items-center gap-3 mb-4">
            <h2 class="font-display text-2xl">Отзывы</h2>
            <?php if($item['reviews_count']>0):?>
            <span class="text-sm text-muted-foreground">★ <?=round($item['avg_rating'],1)?> (<?=$item['reviews_count']?>)</span>
            <?php endif; ?>
          </div>
          <?php if(empty($reviews)): ?>
          <p class="text-muted-foreground text-sm">Пока нет отзывов</p>
          <?php else: foreach($reviews as $r): ?>
          <div class="border-t py-4"><div class="flex items-center gap-2 mb-2"><?= avatar_html(['name'=>$r['author_name'],'avatar_url'=>$r['author_avatar']??null], 'w-6 h-6', 'text-[0.55rem]') ?><span class="font-medium"><?=h($r['author_name'])?></span><span class="text-amber-500 text-sm"><?=str_repeat('★',(int)$r['rating'])?></span><span class="text-xs text-muted-foreground ml-auto"><?=time_ago($r['created_at'])?></span></div><p class="text-sm text-muted-foreground"><?=h($r['text'])?></p></div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Host card -->
      <div class="lg:sticky lg:top-24 self-start">
        <div class="bg-white border border-border/60 rounded-2xl p-7 shadow-[0_4px_20px_-4px_rgba(18,30,43,0.06)]">
          <h3 class="font-display text-lg mb-5">Организатор</h3>
          <div class="flex items-center gap-3.5 mb-4">
            <?= avatar_html(['name' => $item['host_name'], 'avatar_url' => $item['host_avatar']], 'w-12 h-12', 'text-xl') ?>
            <div>
              <div class="font-semibold"><?=h($item['host_name'])?></div>
              <div class="text-xs text-muted-foreground">На сайте с <?=date('m.Y',strtotime($item['created_at']))?></div>
            </div>
          </div>
          <?php if(!empty($item['host_phone'])): ?>
            <div class="text-sm mb-2" id="phoneBlock">
              <button type="button" onclick="revealPhone()" id="revealPhoneBtn" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-border hover:bg-muted h-10 px-4 text-sm font-medium transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                Показать номер
              </button>
              <div id="revealedPhone" class="hidden text-center font-display text-lg mt-2">📞 <a href="tel:<?=h($item['host_phone'])?>" class="text-accent hover:underline"><?=h($item['host_phone'])?></a></div>
            </div>
            <script>function revealPhone(){document.getElementById('revealPhoneBtn').classList.add('hidden');document.getElementById('revealedPhone').classList.remove('hidden');}</script>
          <?php endif; ?>
          <?php if($cu && !$isOwner): ?>
          <a href="/inbox?listing=<?=$lid?>" class="w-full inline-flex items-center justify-center rounded-lg bg-accent text-white hover:bg-accent/80 h-10 px-4 text-sm font-medium transition-all mt-2">✉️ Написать</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Похожие -->
<?php if (!empty($similar)): ?>
<section class="py-12 bg-white border-t">
  <div class="max-w-7xl mx-auto px-4">
    <h2 class="font-display text-2xl mb-6">Похожие объявления</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach($similar as $s): ?>
      <a href="/listing/<?=$s['id']?>" class="listing-card">
        <div class="listing-img">
          <?php $simg = $pdo->query("SELECT filename FROM listing_images WHERE listing_id={$s['id']} ORDER BY sort_order LIMIT 1")->fetchColumn(); ?>
          <?php if($simg): ?><img src="/uploads/<?=h($simg)?>" alt="" loading="lazy"><?php endif; ?>
        </div>
        <div class="listing-body">
          <div class="listing-price"><?=number_format((float)$s['price'],0,'.',' ')?> <?=price_label($s['listing_type'])?></div>
          <div class="listing-title"><?=h($s['title'])?></div>
          <div class="listing-meta"><span><?=h($s['location'])?></span></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
