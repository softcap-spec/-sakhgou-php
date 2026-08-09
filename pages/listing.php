<?php
// listing.php — v3
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

$stmt = $pdo->prepare('SELECT l.*, c.slug AS cat_slug FROM listings l JOIN categories c ON l.category_id = c.id WHERE l.listing_type = ? AND l.id != ? AND l.status = ? ORDER BY RAND() LIMIT 3');
$stmt->execute([$item['listing_type'] ?? 'tour', $lid, 'active']);
$similar = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT r.*, u.name AS author_name, u.avatar_url AS author_avatar FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.listing_id = ? AND r.moderated = 1 ORDER BY r.created_at DESC LIMIT 10');
$stmt->execute([$lid]);
$reviews = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT filename FROM listing_images WHERE listing_id = ? ORDER BY sort_order');
$stmt->execute([$lid]);
$images = array_column($stmt->fetchAll(), 'filename');
if (empty($images) && !empty($item['cover_image'])) $images = [$item['cover_image']];

$TYPE_LABEL = ['property'=>'Жильё','tour'=>'Тур','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто'];
$lt = $item['listing_type'] ?? 'tour';
$cu = auth_user();
$isFavorite = $cu ? is_favorite($cu['id'], $lid) : false;
$isOwner = $cu && ($cu['id'] == $item['user_id'] || $cu['role'] === 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fav'])) {
  csrf_check();
  if ($cu) { $isFavorite = toggle_favorite($cu['id'], $lid); header('Location: /listing/'.$lid); exit; }
}

$page_title = h($item['title']) . ' — СахGO';
require __DIR__ . '/../includes/header.php';
?>

<main>
<!-- Gallery + sidebar -->
<section class="py-6">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <nav class="flex items-center gap-2 text-xs text-[#7A8A9A] mb-5">
      <a href="/" class="hover:text-foreground transition-colors">Главная</a>
      <span>/</span>
      <a href="/catalog/<?=$lt?>" class="hover:text-foreground transition-colors"><?=$TYPE_LABEL[$lt]??'Каталог'?></a>
      <span>/</span>
      <span class="text-foreground truncate max-w-[280px]"><?=h($item['title'])?></span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-6">
      <!-- Gallery -->
      <div>
        <?php if (!empty($images)): ?>
        <div class="rounded-xl overflow-hidden border border-[#EBEEF2] bg-[#EEF2F6]">
          <img src="/uploads/<?=h($images[0])?>" alt="<?=h($item['title'])?>" class="w-full aspect-[16/9] object-cover">
        </div>
        <?php if (count($images) > 1): ?>
        <div class="grid grid-cols-4 sm:grid-cols-6 gap-2 mt-3">
          <?php foreach (array_slice($images, 1, 6) as $img): ?>
          <div class="aspect-square rounded-lg overflow-hidden border border-[#EBEEF2] cursor-pointer hover:ring-2 ring-accent transition-all">
            <img src="/uploads/<?=h($img)?>" alt="" class="w-full h-full object-cover" loading="lazy">
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="rounded-xl overflow-hidden border border-[#EBEEF2] bg-[#EEF2F6] aspect-[16/9]"></div>
        <?php endif; ?>
      </div>

      <!-- Sidebar -->
      <div class="lg:sticky lg:top-20 self-start">
        <div class="bg-white border border-[#EBEEF2] rounded-xl p-6 space-y-4">
          <div class="pb-4 border-b border-[#F0F3F7]">
            <div class="font-display text-3xl text-foreground"><?=number_format((float)$item['price'],0,'.',' ')?> <span class="text-sm font-normal text-[#9AAAB8]"><?=price_label($item['listing_type'])?></span></div>
          </div>

          <div class="space-y-2.5 text-sm">
            <div class="flex justify-between"><span class="text-[#7A8A9A]">Тип</span><span class="font-medium"><?=$TYPE_LABEL[$lt]??''?></span></div>
            <?php if (!empty($item['location'])): ?>
            <div class="flex justify-between"><span class="text-[#7A8A9A]">Локация</span><span class="font-medium"><?=h($item['location'])?></span></div>
            <?php endif; ?>
            <?php if (!empty($item['max_guests'])): ?>
            <div class="flex justify-between"><span class="text-[#7A8A9A]">Гостей</span><span class="font-medium">до <?=$item['max_guests']?></span></div>
            <?php endif; ?>
            <?php if (!empty($item['rooms_count'])): ?>
            <div class="flex justify-between"><span class="text-[#7A8A9A]">Комнат</span><span class="font-medium"><?=$item['rooms_count']?></span></div>
            <?php endif; ?>
            <?php if (!empty($item['view_count'])): ?>
            <div class="flex justify-between"><span class="text-[#7A8A9A]">Просмотров</span><span class="font-medium"><?=$item['view_count']?></span></div>
            <?php endif; ?>
          </div>

          <?php if ($cu): ?>
          <div class="flex gap-2 pt-1">
            <form method="post" class="flex-1">
              <?= csrf_field() ?>
              <button name="fav" value="1" class="w-full inline-flex items-center justify-center rounded-lg border <?=$isFavorite?'bg-red-50 border-red-200 text-red-600':'border-[#DFE4EA] hover:bg-[#F7F9FB]'?> h-10 px-4 text-sm font-medium transition-colors">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?=$isFavorite?'currentColor':'none'?>" stroke="currentColor" stroke-width="2" class="mr-1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <?=$isFavorite?'В избранном':'В избранное'?>
              </button>
            </form>
            <?php if (!$isOwner): ?>
            <a href="/inbox?listing=<?=$lid?>" class="flex-1 inline-flex items-center justify-center rounded-lg bg-accent text-white hover:bg-accent/90 h-10 px-4 text-sm font-medium transition-colors">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              Написать
            </a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if ($isOwner): ?>
          <a href="/edit/<?=$lid?>" class="w-full inline-flex items-center justify-center rounded-lg border border-[#DFE4EA] hover:bg-[#F7F9FB] h-10 px-4 text-sm font-medium transition-colors">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-1.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Редактировать
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Description + specs -->
<section class="py-10">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-10">
      <div class="space-y-8">
        <div>
          <h2 class="font-display text-xl mb-3">Описание</h2>
          <?php if (!empty($item['description'])): ?>
          <p class="text-[#54677A] leading-relaxed whitespace-pre-line text-sm"><?=h($item['description'])?></p>
          <?php else: ?>
          <p class="text-[#9AAAB8] text-sm italic">Описание не указано</p>
          <?php endif; ?>
        </div>

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
          <h2 class="font-display text-xl mb-3">Характеристики</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
            <?php foreach($specs as $l=>$v): ?>
            <div class="flex justify-between py-2 border-b border-[#F0F3F7]"><span class="text-sm text-[#7A8A9A]"><?=$l?></span><span class="text-sm font-medium"><?=h((string)$v)?></span></div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php $amen = json_decode($item['amenities']??'[]',true)?:[]; if(!empty($amen)): ?>
        <div><h2 class="font-display text-xl mb-3">Удобства</h2><div class="flex flex-wrap gap-2"><?php foreach($amen as $a):?><span class="badge"><?=h($a)?></span><?php endforeach;?></div></div>
        <?php endif; ?>

        <?php $inc = json_decode($item['includes']??'[]',true)?:[]; if(!empty($inc)): ?>
        <div><h2 class="font-display text-xl mb-3">Включено</h2><div class="flex flex-wrap gap-2"><?php foreach($inc as $a):?><span class="badge"><?=h($a)?></span><?php endforeach;?></div></div>
        <?php endif; ?>

        <!-- Reviews -->
        <div>
          <div class="flex items-center gap-3 mb-4">
            <h2 class="font-display text-xl">Отзывы</h2>
            <?php if($item['reviews_count']>0):?>
            <span class="text-sm text-[#7A8A9A]"><?=round($item['avg_rating'],1)?> · <?=$item['reviews_count']?> отзывов</span>
            <?php endif; ?>
          </div>
          <?php if(empty($reviews)): ?>
          <p class="text-sm text-[#9AAAB8]">Пока нет отзывов</p>
          <?php else: foreach($reviews as $r): ?>
          <div class="border-t border-[#F0F3F7] py-4">
            <div class="flex items-center gap-2 mb-2">
              <?= avatar_html(['name'=>$r['author_name'],'avatar_url'=>$r['author_avatar']??null], 'w-6 h-6', 'text-[0.55rem]') ?>
              <span class="text-sm font-medium"><?=h($r['author_name'])?></span>
              <span class="text-amber-500 text-xs"><?=str_repeat('★',(int)$r['rating'])?></span>
              <span class="text-xs text-[#9AAAB8] ml-auto"><?=time_ago($r['created_at'])?></span>
            </div>
            <p class="text-sm text-[#54677A]"><?=h($r['text'])?></p>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Host card -->
      <div class="lg:sticky lg:top-20 self-start">
        <div class="bg-white border border-[#EBEEF2] rounded-xl p-6">
          <h3 class="text-xs font-semibold text-[#3A4A5C] uppercase tracking-wide mb-4">Организатор</h3>
          <div class="flex items-center gap-3 mb-4">
            <?= avatar_html(['name' => $item['host_name'], 'avatar_url' => $item['host_avatar']], 'w-10 h-10', 'text-sm') ?>
            <div>
              <div class="font-semibold text-sm"><?=h($item['host_name'])?></div>
              <div class="text-xs text-[#9AAAB8]">На сайте с <?=date('m.Y',strtotime($item['created_at']))?></div>
            </div>
          </div>
          <?php if(!empty($item['host_phone'])): ?>
          <div class="mb-2">
            <button type="button" onclick="revealPhone()" id="revealPhoneBtn" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-[#DFE4EA] hover:bg-[#F7F9FB] h-10 px-4 text-sm font-medium transition-colors">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              Показать номер
            </button>
            <div id="revealedPhone" class="hidden text-center font-display text-base mt-2"><a href="tel:<?=h($item['host_phone'])?>" class="text-accent hover:underline"><?=h($item['host_phone'])?></a></div>
          </div>
          <script>function revealPhone(){document.getElementById('revealPhoneBtn').classList.add('hidden');document.getElementById('revealedPhone').classList.remove('hidden');}</script>
          <?php endif; ?>
          <?php if($cu && !$isOwner): ?>
          <a href="/inbox?listing=<?=$lid?>" class="w-full inline-flex items-center justify-center rounded-lg bg-accent text-white hover:bg-accent/90 h-10 px-4 text-sm font-medium transition-colors mt-2">Написать</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Similar -->
<?php if (!empty($similar)): ?>
<section class="py-10 bg-white border-t border-[#EBEEF2]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <h2 class="font-display text-xl mb-6">Похожие объявления</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach($similar as $s): ?>
      <a href="/listing/<?=$s['id']?>" class="listing-card">
        <div class="listing-img">
          <?php $simg = $pdo->query("SELECT filename FROM listing_images WHERE listing_id={$s['id']} ORDER BY sort_order LIMIT 1")->fetchColumn(); ?>
          <?php if($simg): ?><img src="/uploads/<?=h($simg)?>" alt="" loading="lazy"><?php endif; ?>
        </div>
        <div class="listing-body">
          <div class="listing-price"><?=number_format((float)$s['price'],0,'.',' ')?> <span class="text-xs font-normal text-[#9AAAB8]"><?=price_label($s['listing_type'])?></span></div>
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
