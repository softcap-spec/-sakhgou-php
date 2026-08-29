<?php
// seller.php — публичная страница продавца (как на Авито): активные и завершённые объявления
$pdo = db();
$uid = (int)($id ?? 0);
$stmt = $pdo->prepare('SELECT id, name, avatar_url, created_at, role, seller_type, org_name FROM users WHERE id = ?');
$stmt->execute([$uid]);
$seller = $stmt->fetch();
if (!$seller) { header('Location: /'); exit; }

// Тип продавца: организация (туроператор/турагент) или частное лицо
$t = $pdo->prepare("SELECT tour_organizer_type FROM listings WHERE user_id = ? AND tour_organizer_type != '' ORDER BY id DESC LIMIT 1");
$t->execute([$uid]);
$sellerIsOrg = in_array($t->fetchColumn(), ['tour_operator', 'travel_agent'], true) || ($seller['seller_type'] ?? 'private') === 'org';

// Рейтинг продавца — по отзывам на все его объявления
$r = $pdo->prepare('SELECT AVG(r.rating) AS rt, COUNT(r.id) AS rc FROM reviews r JOIN listings l ON r.listing_id = l.id WHERE l.user_id = ? AND r.moderated = 1');
$r->execute([$uid]);
$rs = $r->fetch();

$imgSub = '(SELECT filename FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1)';
$lst = $pdo->prepare("SELECT l.*, $imgSub AS img FROM listings l LEFT JOIN promotions promo ON l.id = promo.listing_id AND promo.status = 'active' AND promo.expires_at > NOW() WHERE l.user_id = ? AND l.status = ? ORDER BY CASE WHEN promo.id IS NOT NULL THEN 0 ELSE 1 END, l.created_at DESC");
$lst->execute([$uid, 'active']);
$sellerActive = $lst->fetchAll();
$lst->execute([$uid, 'archived']);
$sellerArchived = $lst->fetchAll();

$page_title = 'Продавец: ' . $seller['name'] . ' — СахGO';
require __DIR__ . '/../includes/header.php';
?>
<section class="py-8 md:py-12">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    <!-- Шапка продавца -->
    <div class="bg-white border border-[#EBEEF2] rounded-xl p-5 md:p-6 flex flex-col sm:flex-row sm:items-center gap-4 mb-8">
      <div class="flex items-center gap-4 min-w-0">
        <?= avatar_html(['name' => $seller['name'], 'avatar_url' => $seller['avatar_url']], 'w-16 h-16', 'text-xl') ?>
        <div class="min-w-0">
          <h1 class="font-display text-xl md:text-2xl truncate"><?=h(($sellerIsOrg && !empty($seller['org_name'])) ? $seller['org_name'] : $seller['name'])?></h1>
          <div class="text-sm text-[#6B7B8D] mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium <?=$sellerIsOrg ? 'bg-[#EEF2F6] text-[#121E2B]' : 'bg-[#F0F9FF] text-[#1B6B8A]'?>">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <?=$sellerIsOrg ? 'Организация' : 'Частное лицо'?>
            </span>
            <span>на сайте с <?=date('m.Y', strtotime($seller['created_at']))?></span>
            <span><?=count($sellerActive)?> активн. / <?=count($sellerArchived)?> заверш.</span>
          </div>
        </div>
      </div>
      <?php if ((int)($rs['rc'] ?? 0) > 0): $rc = (int)$rs['rc']; $rv_plural = $rc % 10 == 1 && $rc % 100 != 11 ? 'отзыв' : ($rc % 10 >= 2 && $rc % 10 <= 4 && ($rc % 100 < 12 || $rc % 100 > 14) ? 'отзыва' : 'отзывов'); ?>
      <div class="sm:ml-auto flex items-center gap-3 bg-[#F7F9FB] border border-[#EBEEF2] rounded-xl px-4 py-3">
        <span class="text-sm text-[#5A6B7D]">Рейтинг</span>
        <span class="flex items-center gap-1.5">
          <span style="position:relative;display:inline-block;font-size:0.9375rem;line-height:1;color:#D5DCE5;letter-spacing:0.08em">★★★★★<span style="position:absolute;top:0;left:0;overflow:hidden;white-space:nowrap;color:#F59E0B;width:<?=round((float)$rs['rt']/5*100)?>%">★★★★★</span></span>
          <span class="text-sm font-medium"><?=round((float)$rs['rt'], 1)?> · <?=$rc?> <?=$rv_plural?></span>
        </span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Вкладки -->
    <div class="flex gap-1 border-b border-[#EBEEF2] mb-6">
      <button type="button" onclick="sellerTab('active')" id="tab-active" class="px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors border-accent text-accent">Активные (<?=count($sellerActive)?>)</button>
      <button type="button" onclick="sellerTab('archived')" id="tab-archived" class="px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors border-transparent text-[#7A8A9A] hover:text-foreground">Завершённые (<?=count($sellerArchived)?>)</button>
    </div>

    <!-- Активные -->
    <div id="panel-active">
      <?php if (empty($sellerActive)): ?>
        <div class="text-center py-16 text-[#7A8A9A] text-sm">У продавца пока нет активных объявлений</div>
      <?php else: ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
          <?php foreach ($sellerActive as $it): ?>
          <a href="/listing/<?=$it['id']?>" class="listing-card">
            <div class="listing-img">
              <?php if ($it['img']): ?><img src="/uploads/<?=h($it['img'])?>" alt="<?=h($it['title'])?>" loading="lazy"><?php endif; ?>
            </div>
            <div class="listing-body">
              <div class="listing-price"><?=price_text($it)?><?php if (!price_is_negotiable($it) && (float)$it['price'] > 0): ?> <span class="text-xs font-normal text-[#9AAAB8]"><?=price_label($it['listing_type'])?></span><?php endif; ?></div>
              <div class="listing-title"><?=h($it['title'])?></div>
              <div class="listing-meta"><?=h($it['location'])?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Завершённые -->
    <div id="panel-archived" style="display:none">
      <?php if (empty($sellerArchived)): ?>
        <div class="text-center py-16 text-[#7A8A9A] text-sm">Завершённых объявлений нет</div>
      <?php else: ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
          <?php foreach ($sellerArchived as $it): ?>
          <a href="/listing/<?=$it['id']?>" class="listing-card">
            <div class="listing-img">
              <?php if ($it['img']): ?><img src="/uploads/<?=h($it['img'])?>" alt="<?=h($it['title'])?>" loading="lazy"><?php endif; ?>
            </div>
            <div class="listing-body">
              <div class="listing-price"><?=price_text($it)?><?php if (!price_is_negotiable($it) && (float)$it['price'] > 0): ?> <span class="text-xs font-normal text-[#9AAAB8]"><?=price_label($it['listing_type'])?></span><?php endif; ?></div>
              <div class="listing-title"><?=h($it['title'])?></div>
              <div class="listing-meta"><?=h($it['location'])?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</section>
<script>
function sellerTab(k) {
  var a = document.getElementById('panel-active');
  var ar = document.getElementById('panel-archived');
  var ta = document.getElementById('tab-active');
  var tar = document.getElementById('tab-archived');
  if (k === 'active') {
    a.style.display = ''; ar.style.display = 'none';
    ta.className = 'px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors border-accent text-accent';
    tar.className = 'px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors border-transparent text-[#7A8A9A] hover:text-foreground';
  } else {
    a.style.display = 'none'; ar.style.display = '';
    tar.className = 'px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors border-accent text-accent';
    ta.className = 'px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors border-transparent text-[#7A8A9A] hover:text-foreground';
  }
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
