<?php
// home.php — копия главной sakhgo.ru
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
<section class="py-32 relative overflow-hidden">
  <div class="absolute inset-0 bg-gradient-to-b from-[#E2ECF4] via-[#EAF1F6] to-background"></div>
  <div class="absolute inset-0" style="background:radial-gradient(ellipse 80% 60% at 50% 0%, rgba(59,130,200,0.07), transparent 70%)"></div>
  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-10 mb-12">
      <div class="max-w-2xl">
        <span class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.15em] text-accent font-semibold mb-4">
          <span class="w-6 h-px bg-accent/40"></span>Маркетплейс приключений
        </span>
        <h1 class="font-display text-5xl sm:text-6xl lg:text-[4.5rem] leading-[1.03] tracking-tight text-foreground mb-6">
          Сахалин и Курилы — <em class="text-accent not-italic relative">ближе<svg class="absolute -bottom-2 left-0 w-full" viewBox="0 0 100 8" preserveAspectRatio="none"><path d="M0,4 Q25,0 50,4 Q75,8 100,4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="text-accent/50"></path></svg></em>, чем кажется.
        </h1>
        <p class="text-lg text-muted-foreground max-w-lg leading-relaxed">Жильё, джип-туры, морские выходы, рыбалка и снаряжение — напрямую от местных, без посредников.</p>
      </div>
      <div class="hidden lg:block shrink-0">
        <img src="/hero-bear.png" alt="" class="h-52 w-auto opacity-90">
      </div>
    </div>

    <!-- Search -->
    <div class="max-w-3xl">
      <form action="/search" method="get" class="relative group">
        <div class="absolute -inset-1 bg-gradient-to-r from-accent/20 via-accent/10 to-accent/20 rounded-2xl blur opacity-0 group-focus-within:opacity-100 transition duration-500"></div>
        <div class="relative flex items-center bg-white rounded-2xl shadow-[0_8px_40px_-12px_rgba(0,0,0,0.12)] hover:shadow-[0_12px_48px_-12px_rgba(0,0,0,0.15)] transition-shadow duration-300">
          <div class="flex-1 flex items-center gap-3 px-5">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground/40 shrink-0"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
            <input type="text" name="q" placeholder="Квартира у моря, джип-тур, сноуборд, рыбалка..." class="w-full h-16 bg-transparent border-0 text-lg placeholder:text-muted-foreground/40 focus:outline-none" value="<?=h($_GET['q']??'')?>">
          </div>
          <div class="p-2 pr-2">
            <button type="submit" class="inline-flex shrink-0 items-center justify-center border border-transparent bg-accent text-white hover:bg-accent/80 h-12 px-7 rounded-xl gap-2 text-base font-semibold shadow-[0_4px_14px_-4px_rgba(0,0,0,0.2)] hover:shadow-[0_6px_20px_-4px_rgba(0,0,0,0.25)] transition-all active:scale-[0.98]">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/><path d="M20 2v4M22 4h-4"/><circle cx="4" cy="20" r="2"/></svg>
              Найти
            </button>
          </div>
        </div>
      </form>
      <div class="flex flex-wrap gap-2 mt-4">
        <span class="text-xs text-muted-foreground mr-1 self-center">Часто ищут:</span>
        <?php foreach (['Маяк Анива','Джип-тур','Сноуборд','Квартира посуточно','Рыбалка'] as $tag): ?>
        <a href="/search?q=<?=urlencode($tag)?>" class="px-3 py-1.5 text-xs rounded-full border border-border/60 hover:border-accent/30 hover:text-accent hover:bg-accent/5 transition-colors"><?=$tag?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ═══ Quick Picks ═══ -->
<section class="py-12">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <span class="text-xs uppercase tracking-[0.12em] text-accent font-medium">Быстрые подборки</span>
    <h2 class="font-display text-4xl mt-1 mb-8">Куда поедем?</h2>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
      <?php
      $picks = [
        ['Жильё','property','from-[#D4DFE7] via-[#8FB8CC] to-[#4A8BA8]'],
        ['Морские выходы','tour','from-[#C5D5E0] via-[#7BA4BC] to-[#3B7599]'],
        ['Джип-туры','tour','from-[#DDD6C8] via-[#B5A68E] to-[#8C7B62]'],
        ['Рыбалка','fishing','from-[#B8D8D8] via-[#70A8A0] to-[#387870]'],
      ];
      foreach ($picks as $p):
      ?>
      <a href="/catalog/<?=$p[1]?>" class="relative rounded-xl overflow-hidden min-h-[200px] flex items-end text-left border transition-all hover:-translate-y-0.5 border-border">
        <div class="absolute inset-0 bg-gradient-to-br <?=$p[2]?>"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/55 to-black/5 pointer-events-none"></div>
        <div class="relative z-10 p-5 text-white">
          <span class="text-xs uppercase tracking-widest opacity-75"><?=$cat_counts[$p[1]]?> вариантов</span>
          <h3 class="font-display text-xl mt-0.5 leading-tight"><?=$p[0]?></h3>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ Popular Listings ═══ -->
<section class="py-12 md:py-16 bg-white border-t">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-10">
      <div>
        <span class="text-xs uppercase tracking-[0.12em] text-accent font-medium">Популярное сейчас</span>
        <h2 class="font-display text-4xl mt-1">Выбор путешественников</h2>
      </div>
      <div class="flex gap-2 flex-wrap">
        <?php
        $cats_filter = ['all'=>'Всё','property'=>'Жильё','tour'=>'Туры','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто'];
        $active_cat = $_GET['cat'] ?? 'all';
        foreach ($cats_filter as $k=>$v):
        ?>
        <a href="/?cat=<?=$k?>" class="inline-flex items-center justify-center h-7 gap-1 px-2.5 rounded-full text-sm font-medium transition-all <?=$active_cat===$k?'bg-accent text-white hover:bg-accent/90':'text-muted-foreground hover:text-foreground hover:bg-muted'?>"><?=$v?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (empty($recent)): ?>
      <div class="text-center py-20 text-muted-foreground">
        <p class="text-lg">Ничего не найдено</p>
        <p class="text-sm mt-1 mb-4">Станьте первым организатором</p>
        <a href="/create" class="inline-flex items-center justify-center rounded-lg border border-border bg-background hover:bg-muted hover:text-foreground h-8 gap-1.5 px-2.5 text-sm font-medium transition-all">Подать объявление</a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
        <?php foreach ($recent as $item): ?>
        <a href="/listing/<?=$item['id']?>" class="bg-white border border-border rounded-xl overflow-hidden transition-all hover:-translate-y-0.5 hover:shadow-lg flex flex-col relative">
          <?php if (!empty($item['image'])): ?>
          <div class="aspect-[16/10] bg-secondary overflow-hidden">
            <img src="/uploads/<?=h($item['image'])?>" alt="<?=h($item['title'])?>" class="w-full h-full object-cover" loading="lazy">
          </div>
          <?php else: ?>
          <div class="aspect-[16/10] bg-secondary flex items-center justify-center text-4xl">📷</div>
          <?php endif; ?>
          <?php if (!empty($item['promo_type'])): ?>
          <span class="absolute top-2 left-2 text-xs font-bold px-2 py-0.5 rounded-full text-white <?=$item['promo_type']==='top'?'bg-red-600':($item['promo_type']==='highlight'?'bg-amber-500':'bg-red-500')?>"><?=$item['promo_type']==='top'?'🔝 TOP':($item['promo_type']==='highlight'?'💡 PROMO':'⚡ Срочно')?></span>
          <?php endif; ?>
          <div class="p-4 flex-1 flex flex-col gap-1">
            <div class="font-display text-xl"><?=number_format((float)$item['price'],0,'.',' ')?> <?=price_label($item['listing_type'])?></div>
            <div class="font-medium text-sm leading-snug line-clamp-2"><?=h($item['title'])?></div>
            <div class="flex items-center gap-2 text-xs text-muted-foreground mt-auto pt-2">
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
<section class="py-24 bg-accent text-white text-center">
  <div class="max-w-2xl mx-auto px-4">
    <h2 class="font-display text-4xl sm:text-5xl leading-[1.05] mb-6">Разместите своё объявление на SakhGo</h2>
    <p class="text-white/85 text-lg max-w-xl mx-auto mb-10 leading-relaxed">Сдавайте жильё, предлагайте туры и рыбалку или сдавайте снаряжение — наша площадка помогает найти гостей со всей России.</p>
    <a href="/create" class="inline-flex items-center justify-center rounded-lg bg-white text-accent hover:bg-white/90 h-9 gap-1.5 text-base px-8 py-6 font-medium transition-all">Разместить объявление</a>
  </div>
</section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
