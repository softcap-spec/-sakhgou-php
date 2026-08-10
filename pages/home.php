<?php
// home.php — сахгоу.рф v4
$cu = auth_user();
$recent = get_recent_listings(24);
$cat_counts = []; $cat_images = [];
$_db = db();
foreach (['property','tour','fishing','rental_gear','car_rental'] as $slug) {
  $r = get_listings($slug, '', 1);
  $cat_counts[$slug] = $r['total'];
  $s = $_db->prepare('SELECT li.filename FROM listing_images li JOIN listings l ON li.listing_id=l.id JOIN categories c ON l.category_id=c.id WHERE c.slug=? AND l.status="active" ORDER BY RAND() LIMIT 1');
  $s->execute([$slug]);
  $cat_images[$slug] = $s->fetchColumn() ?: '';
}
$page_title = 'СахGO — жильё, туры, рыбалка и снаряжение. Сахалин и Курилы';
require __DIR__ . '/../includes/header.php';
?>

<main>

<!-- ═══ Hero ═══ -->
<section class="py-24 sm:py-32">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-start gap-8 lg:gap-16">
    <div class="max-w-2xl flex-1">
      <span class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.16em] text-accent font-semibold mb-5">
        <span class="w-8 h-px bg-accent/30"></span>Маркетплейс Сахалина
      </span>
      <h1 class="font-display text-[2.75rem] sm:text-5xl lg:text-[3.5rem] leading-[1.05] tracking-tight text-foreground mb-5">
        Сахалин и Курилы —<br>
        <span class="text-accent">ближе, чем кажется</span>
      </h1>
      <p class="text-base sm:text-lg text-[#7A8A9A] max-w-lg leading-relaxed">
        Жильё, джип-туры, морские выходы, рыбалка и снаряжение — напрямую от местных организаторов.
      </p>
    </div>
    <div class="hidden lg:block shrink-0">
      <img src="/hero-bear.png" alt="" class="h-52 w-auto opacity-90">
    </div>
    </div>

    <!-- Search -->
    <div class="max-w-2xl mt-10">
      <form action="/search" method="get" class="relative">
        <div class="flex items-center bg-white border border-[#DFE4EA] rounded-xl shadow-sm focus-within:border-accent focus-within:ring-1 focus-within:ring-accent transition-colors">
          <div class="flex-1 flex items-center gap-2.5 px-4">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-[#9AAAB8] shrink-0"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
            <input type="text" name="q" placeholder="Квартира, джип-тур, сноуборд, рыбалка..." class="w-full h-14 bg-transparent border-0 text-base placeholder:text-[#9AAAB8] focus:outline-none">
          </div>
          <div class="p-1.5">
            <button type="submit" class="inline-flex items-center justify-center bg-accent text-white hover:bg-accent/90 h-11 px-6 rounded-lg text-sm font-semibold transition-colors">
              Найти
            </button>
          </div>
        </div>
      </form>
      <div class="flex flex-wrap items-center gap-2 mt-3">
        <span class="text-xs text-[#9AAAB8] mr-1">Часто ищут:</span>
        <?php foreach (['Маяк Анива','Джип-тур','Сноуборд','Квартира посуточно','Рыбалка'] as $tag): ?>
        <a href="/search?q=<?=urlencode($tag)?>" class="px-2.5 py-1 text-xs rounded-md text-[#54677A] hover:text-foreground hover:bg-[#EEF2F6] transition-colors"><?=$tag?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<?php render_banners('home_hero_bottom'); ?>

<!-- ═══ Quick Picks ═══ -->
<section class="pb-16">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <span class="text-xs uppercase tracking-[0.12em] text-accent font-medium mb-1 inline-block">Быстрые подборки</span>
    <h2 class="font-display text-3xl sm:text-4xl mb-8">Куда поедем?</h2>
    <div class="flex gap-3 md:gap-4 overflow-x-auto">
      <?php
      $picks = [
        ['Жильё','property',"#4A90A4"],
        ['Морские прогулки','tour',"#3E7A8E"],
        ['Рыбалка','fishing',"#5E948B"],
        ['Снаряжение','rental_gear',"#8B7E6A"],
        ['Прокат авто','car_rental',"#7B6FA8"],
      ];
      foreach ($picks as $p):
      ?>
      <a href="/catalog/<?=$p[1]?>" class="relative rounded-xl overflow-hidden min-h-[160px] shrink-0 flex items-end text-left transition-all hover:-translate-y-0.5 hover:shadow-lg w-[180px] sm:w-[200px] lg:flex-1" style="background:linear-gradient(150deg,<?=$p[2]?> 0%,<?=$p[2]?>88 60%,<?=$p[2]?>55 100%)">
        <?php if (!empty($cat_images[$p[1]])): ?>
        <img src="/uploads/<?=h($cat_images[$p[1]])?>" alt="" class="absolute inset-0 w-full h-full object-cover opacity-40" loading="lazy">
        <?php endif; ?>
        <div class="absolute inset-0" style="background:linear-gradient(to top, rgba(0,0,0,0.55) 0%, transparent 60%)"></div>
        <div class="relative p-4 sm:p-5 w-full">
          <span class="text-white/70 text-xs"><?=$cat_counts[$p[1]]?> вариантов</span>
          <h3 class="font-display text-lg sm:text-xl leading-tight text-white mt-0.5"><?=$p[0]?></h3>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php render_banners('home_picks_bottom'); ?>

<!-- ═══ Popular Listings ═══ -->
<section class="py-16 bg-white border-t border-[#EBEEF2]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-10">
      <div>
        <span class="text-xs uppercase tracking-[0.12em] text-accent font-medium">Популярное сейчас</span>
        <h2 class="font-display text-3xl sm:text-4xl tracking-tight mt-1">Выбор путешественников</h2>
      </div>
      <div class="flex gap-1 flex-wrap">
        <?php
        $cats_filter = ['all'=>'Всё','property'=>'Жильё','tour'=>'Туры','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто'];
        $active_cat = $_GET['cat'] ?? 'all';
        foreach ($cats_filter as $k=>$v):
        ?>
        <a href="/?cat=<?=$k?>" class="filter-pill <?=$active_cat===$k?'active':''?>"><?=$v?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php render_banners('home_listings_top'); ?>
      <?php if (empty($recent)): ?>
      <div class="text-center py-20 text-muted-foreground">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[#EEF2F6] mb-4">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9AAAB8" stroke-width="1.5"><rect x="2" y="2" width="20" height="20" rx="2"/><path d="M12 8v8M8 12h8"/></svg>
        </div>
        <p class="text-lg font-medium text-[#3A4A5C]">Ничего не найдено</p>
        <p class="text-sm text-[#7A8A9A] mt-1 mb-4">Станьте первым организатором на Сахалине</p>
        <a href="/create" class="cta-btn">Подать объявление</a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php $i = 0; foreach ($recent as $item): $i++; ?>
        <?php if ($i === 6) render_banners('home_listings_inline'); ?>
        <a href="/listing/<?=$item['id']?>" class="listing-card hover:-translate-y-0.5 hover:shadow-[0_8px_24px_-8px_rgba(0,0,0,0.12)]">
          <div class="listing-img">
            <?php if (!empty($item['image'])): ?>
            <img src="/uploads/<?=h($item['image'])?>" alt="<?=h($item['title'])?>" loading="lazy">
            <?php endif; ?>
          </div>
          <?php if (!empty($item['promo_type'])): ?>
          <span class="promo-badge absolute top-2.5 left-2.5 <?=$item['promo_type']==='top'?'bg-red-600':($item['promo_type']==='highlight'?'bg-amber-500':'bg-red-500')?>"><?=$item['promo_type']==='top'?'TOP':($item['promo_type']==='highlight'?'PROMO':'Срочно')?></span>
          <?php endif; ?>
          <div class="listing-body">
            <div class="listing-price"><?=number_format((float)$item['price'],0,'.',' ')?> <span class="text-xs font-normal text-[#9AAAB8]"><?=price_label($item['listing_type'])?></span></div>
            <div class="listing-title"><?=h($item['title'])?></div>
            <div class="listing-meta">
              <span><?=h($item['category_name'])?></span>
              <span>·</span>
              <span><?=time_ago($item['created_at'])?></span>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

      <?php render_banners('home_listings_bottom'); ?>
      <?php if (count($recent) >= 24): ?>
      <div class="text-center mt-10">
        <a href="/catalog" class="inline-flex items-center justify-center border border-[#DFE4EA] hover:border-accent hover:text-accent rounded-lg h-10 px-6 text-sm font-medium transition-colors">
          Все объявления
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="ml-1.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<!-- ═══ CTA ═══ -->
<section class="py-20 bg-foreground text-white">
  <div class="max-w-3xl mx-auto px-4 text-center">
    <h2 class="font-display text-3xl sm:text-4xl leading-[1.1] mb-4">Разместите своё объявление</h2>
    <p class="text-white/60 text-base max-w-xl mx-auto mb-8 leading-relaxed">
      Сдавайте жильё, предлагайте туры и рыбалку или сдавайте снаряжение. Найдите гостей со всей России.
    </p>
    <a href="/create" class="inline-flex items-center justify-center bg-white text-foreground hover:bg-white/90 h-11 px-8 rounded-lg text-sm font-semibold transition-colors">
      Подать объявление
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="ml-1.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </a>
  </div>
</section>

</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
