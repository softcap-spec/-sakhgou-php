<?php
// home.php — главная сахгоу.рф v2
$cu = auth_user();
$recent = get_recent_listings(8);
$cat_counts = [];
foreach (['property','tour','fishing','rental_gear','car_rental'] as $slug) {
  $r = get_listings($slug, '', 1);
  $cat_counts[$slug] = $r['total'];
}
$page_title = 'СахGO — жильё, туры, рыбалка и снаряжение. Сахалин и Курилы — ближе, чем кажется';
require __DIR__ . '/../includes/header.php';
?>

<main>
<!-- ═══ Hero ═══ -->
<section class="relative py-28 sm:py-36 overflow-hidden">
  <!-- Background layers -->
  <div class="absolute inset-0 bg-gradient-to-b from-[#E5EEF5] via-[#EDF2F7] to-background"></div>
  <div class="absolute inset-0" style="background:radial-gradient(ellipse 90% 70% at 50% 5%, rgba(59,130,200,0.09), transparent 65%), radial-gradient(ellipse 40% 30% at 85% 90%, rgba(27,107,138,0.04), transparent)"></div>
  <!-- Subtle pattern -->
  <div class="absolute inset-0 opacity-[0.03]" style="background-image:url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 60 60%22><circle cx=%2230%22 cy=%2230%22 r=%221.5%22 fill=%22%231B6B8A%22/></svg>');background-size:60px 60px"></div>

  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-10 mb-14">
      <div class="max-w-2xl">
        <span class="inline-flex items-center gap-2.5 text-xs uppercase tracking-[0.18em] text-accent font-semibold mb-5">
          <span class="w-8 h-px bg-accent/40"></span>Маркетплейс приключений
        </span>
        <h1 class="font-display text-5xl sm:text-6xl lg:text-[4.25rem] leading-[1.02] tracking-tight text-foreground mb-6">
          Сахалин и Курилы — <em class="text-accent not-italic relative">ближе<svg class="absolute -bottom-1.5 left-0 w-full" viewBox="0 0 100 8" preserveAspectRatio="none"><path d="M0,4 Q25,1 50,4 Q75,7 100,4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="text-accent/40"></path></svg></em>,<br>чем кажется
        </h1>
        <p class="text-lg text-muted-foreground max-w-lg leading-relaxed">Жильё, джип-туры, морские выходы, рыбалка и снаряжение — напрямую от местных, без посредников.</p>
      </div>
      <div class="hidden lg:block shrink-0 relative">
        <div class="absolute inset-0 bg-gradient-to-t from-accent/10 to-transparent rounded-full blur-3xl"></div>
        <img src="/hero-bear.png" alt="" class="relative h-56 w-auto opacity-90 drop-shadow-xl">
      </div>
    </div>

    <!-- Search -->
    <div class="max-w-3xl">
      <form action="/search" method="get" class="relative group">
        <div class="absolute -inset-1.5 bg-gradient-to-r from-accent/25 via-accent/10 to-accent/25 rounded-[1.25rem] blur-md opacity-0 group-focus-within:opacity-100 transition duration-700"></div>
        <div class="relative flex items-center bg-white rounded-[1.25rem] shadow-[0_8px_40px_-12px_rgba(0,0,0,0.1)] hover:shadow-[0_16px_48px_-12px_rgba(0,0,0,0.15)] transition-shadow duration-300">
          <div class="flex-1 flex items-center gap-3.5 px-6">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground/35 shrink-0"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
            <input type="text" name="q" placeholder="Квартира у моря, джип-тур, сноуборд, рыбалка..." class="w-full h-[3.75rem] bg-transparent border-0 text-lg placeholder:text-muted-foreground/35 focus:outline-none font-medium" value="<?=h($_GET['q']??'')?>">
          </div>
          <div class="p-2 pr-2.5">
            <button type="submit" class="inline-flex shrink-0 items-center justify-center border border-transparent bg-accent text-white hover:bg-accent/85 h-[3rem] px-8 rounded-xl gap-2 text-base font-semibold shadow-[0_4px_14px_-4px_rgba(27,107,138,0.4)] hover:shadow-[0_6px_20px_-4px_rgba(27,107,138,0.5)] transition-all active:scale-[0.98]">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/><path d="M20 2v4M22 4h-4"/><circle cx="4" cy="20" r="2"/></svg>
              Найти
            </button>
          </div>
        </div>
      </form>
      <div class="flex flex-wrap items-center gap-2 mt-5">
        <span class="text-xs text-muted-foreground mr-1">Часто ищут:</span>
        <?php foreach (['Маяк Анива','Джип-тур','Сноуборд','Квартира посуточно','Рыбалка'] as $tag): ?>
        <a href="/search?q=<?=urlencode($tag)?>" class="px-3 py-1.5 text-xs rounded-full border border-border/60 hover:border-accent/30 hover:text-accent hover:bg-accent/[0.04] transition-all"><?=$tag?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ═══ Quick Picks ═══ -->
<section class="py-14 section-wave">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <span class="text-xs uppercase tracking-[0.14em] text-accent font-semibold">Быстрые подборки</span>
    <h2 class="font-display text-4xl sm:text-5xl mt-1.5 mb-10">Куда поедем?</h2>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <?php
      $picks = [
        ['Жильё','property','qp-zhilyo','🏠'],
        ['Морские выходы','tour','qp-morskie','⛵'],
        ['Джип-туры','tour','qp-dzhip','🚙'],
        ['Рыбалка','fishing','qp-rybalka','🎣'],
      ];
      foreach ($picks as $p):
      ?>
      <a href="/catalog/<?=$p[1]?>" class="qp-card <?=$p[3]?>">
        <div class="qp-overlay"></div>
        <div class="qp-content">
          <div class="text-3xl mb-2"><?=$p[3]?></div>
          <span class="qp-count"><?=$cat_counts[$p[1]]?> вариантов</span>
          <h3 class="font-display text-xl mt-1 leading-tight"><?=$p[0]?></h3>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ Popular Listings ═══ -->
<section class="py-14 md:py-18 bg-white border-t border-border/40">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-12">
      <div>
        <span class="text-xs uppercase tracking-[0.14em] text-accent font-semibold">Популярное сейчас</span>
        <h2 class="font-display text-4xl sm:text-5xl mt-1.5">Выбор путешественников</h2>
      </div>
      <div class="flex gap-1.5 flex-wrap">
        <?php
        $cats_filter = ['all'=>'Всё','property'=>'Жильё','tour'=>'Туры','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто'];
        $active_cat = $_GET['cat'] ?? 'all';
        foreach ($cats_filter as $k=>$v):
        ?>
        <a href="/?cat=<?=$k?>" class="filter-pill <?=$active_cat===$k?'active':''?>"><?=$v?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (empty($recent)): ?>
      <div class="text-center py-24 text-muted-foreground">
        <div class="text-6xl mb-4">🏕️</div>
        <p class="text-xl font-medium mb-1">Ничего не найдено</p>
        <p class="text-sm mb-6">Станьте первым организатором на Сахалине</p>
        <a href="/create" class="inline-flex items-center justify-center rounded-lg border border-border bg-background hover:bg-muted hover:text-foreground h-9 gap-1.5 px-3 text-sm font-medium transition-all">Подать объявление</a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <?php foreach ($recent as $item): ?>
        <a href="/listing/<?=$item['id']?>" class="listing-card group">
          <?php if (!empty($item['image'])): ?>
          <div class="listing-img">
            <img src="/uploads/<?=h($item['image'])?>" alt="<?=h($item['title'])?>" loading="lazy">
          </div>
          <?php else: ?>
          <div class="listing-img">
            <div class="w-full h-full bg-gradient-to-br from-secondary to-secondary/50 flex items-center justify-center text-5xl">
              <?php
              $defaultIcons = ['property'=>'🏠','tour'=>'🏔️','fishing'=>'🎣','rental_gear'=>'🔧','car_rental'=>'🚗'];
              echo $defaultIcons[$item['listing_type']] ?? '📷';
              ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if (!empty($item['promo_type'])): ?>
          <span class="promo-badge absolute top-3 left-3 <?=$item['promo_type']==='top'?'bg-red-600':($item['promo_type']==='highlight'?'bg-amber-500':'bg-red-500')?>"><?=$item['promo_type']==='top'?'🔝 TOP':($item['promo_type']==='highlight'?'💡 PROMO':'⚡ Срочно')?></span>
          <?php endif; ?>
          <div class="listing-body">
            <div class="listing-price"><?=number_format((float)$item['price'],0,'.',' ')?> <span class="text-sm font-medium text-muted-foreground"><?=price_label($item['listing_type'])?></span></div>
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
    <?php endif; ?>
  </div>
</section>

<!-- ═══ CTA ═══ -->
<section class="py-28 bg-accent text-white text-center cta-section">
  <div class="relative max-w-2xl mx-auto px-4">
    <div class="text-5xl mb-6">🏔️</div>
    <h2 class="font-display text-4xl sm:text-5xl leading-[1.08] mb-6">Разместите своё объявление на SakhGo</h2>
    <p class="text-white/80 text-lg max-w-xl mx-auto mb-12 leading-relaxed">Сдавайте жильё, предлагайте туры и рыбалку или сдавайте снаряжение — наша площадка помогает найти гостей со всей России.</p>
    <a href="/create" class="inline-flex items-center justify-center rounded-xl bg-white text-accent hover:bg-white/90 h-9 gap-2 text-base px-10 py-7 font-semibold transition-all shadow-[0_8px_30px_-8px_rgba(0,0,0,0.3)] hover:shadow-[0_12px_36px_-8px_rgba(0,0,0,0.35)] hover:-translate-y-0.5 active:translate-y-0">
      Разместить объявление
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </a>
  </div>
</section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
