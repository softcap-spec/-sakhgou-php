<?php
// catalog.php — Tailwind version
$cat_slug = $sub ?? '';
$category = null;
if ($cat_slug && isset($categories[$cat_slug])) {
  $category = $categories[$cat_slug];
  $current_category = $cat_slug;
}
$page_num = max(1, (int)($_GET['page'] ?? 1));
$result = get_listings($cat_slug, '', $page_num);
$listings = $result['items'];
$total = $result['total'];
$total_pages = $result['pages'];

$cat_seo = [
  'property' => ['title' => 'Жильё на Сахалине и Курилах — аренда, базы отдыха, гостиницы', 'h1' => 'Жильё на Сахалине и Курилах'],
  'tour' => ['title' => 'Туры по Сахалину и Курилам — экскурсии, походы, рыболовные туры', 'h1' => 'Туры по Сахалину и Курилам'],
  'fishing' => ['title' => 'Рыбалка на Сахалине — морская и речная, туры на Курилы', 'h1' => 'Рыбалка на Сахалине и Курилах'],
  'rental_gear' => ['title' => 'Снаряжение для отдыха на Сахалине — аренда и прокат', 'h1' => 'Снаряжение для отдыха'],
  'car_rental' => ['title' => 'Прокат авто на Сахалине — внедорожники, минивэны, джипы', 'h1' => 'Прокат авто на Сахалине'],
];
$seo = $cat_seo[$cat_slug] ?? null;
$page_title = $seo ? $seo['title'] . ' — СахGO' : ($category ? $category['name'] . ' — СахGO' : 'Все объявления — Туристический маркетплейс Сахалина — СахGO');
$cat_h1 = $seo ? $seo['h1'] : ($category ? $category['name'] : 'Все объявления');
require __DIR__ . '/../includes/header.php';
?>

<main class="py-12">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <?php
    $bc = ['Главная'=>'/'];
    if ($category) $bc[h($category['name'])] = '';
    else $bc['Все объявления'] = '';
    breadcrumbs($bc);
    ?>
    <div class="flex flex-wrap items-end justify-between gap-4 mb-10">
      <div>
        <span class="text-xs uppercase tracking-[0.12em] text-accent font-medium">Каталог</span>
        <h1 class="font-display text-4xl mt-1"><?= $cat_h1 ?></h1>
      </div>
      <div class="flex gap-2 flex-wrap">
        <a href="/catalog" class="inline-flex items-center h-7 px-2.5 rounded-full text-sm font-medium transition-all <?=!$cat_slug?'bg-accent text-white':'text-muted-foreground hover:text-foreground hover:bg-muted'?>">Всё</a>
        <?php foreach ($categories as $slug => $cat): ?>
        <a href="/catalog/<?=$slug?>" class="inline-flex items-center h-7 px-2.5 rounded-full text-sm font-medium transition-all <?=$cat_slug===$slug?'bg-accent text-white':'text-muted-foreground hover:text-foreground hover:bg-muted'?>"><?=h($cat['name'])?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (empty($listings)): ?>
      <div class="text-center py-20 text-muted-foreground">
        <p class="text-lg">Ничего не найдено</p>
        <p class="text-sm mt-1 mb-4">Станьте первым организатором</p>
        <a href="/create" class="inline-flex items-center justify-center rounded-lg border border-border bg-background hover:bg-muted h-8 px-2.5 text-sm font-medium transition-all">Подать объявление</a>
      </div>
    <?php else: ?>
      <?php render_banners('catalog_top'); ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
        <?php foreach ($listings as $item): ?>
        <a href="/listing/<?=$item['id']?>" class="listing-card <?=!empty($item['promo_type'])?'promo-'.h($item['promo_type']):''?>">
          <div class="listing-img">
            <?php if (!empty($item['image'])): ?>
            <img src="/uploads/<?=h($item['image'])?>" alt="<?=h($item['title'])?>" loading="lazy">
            <?php endif; ?>
          </div>
          <?php if (!empty($item['promo_type'])): ?>
          <span class="promo-badge absolute top-2.5 left-2.5 <?=$item['promo_type']==='top'?'bg-red-600':($item['promo_type']==='highlight'?'bg-amber-500':'bg-red-500')?>"><?=$item['promo_type']==='top'?'TOP':($item['promo_type']==='highlight'?'PROMO':'Срочно')?></span>
          <?php endif; ?>
          <div class="listing-body">
            <div class="listing-price"><?=price_text($item)?><?php if (!price_is_negotiable($item) && (float)$item['price'] > 0): ?> <span class="text-sm font-medium text-muted-foreground"><?=price_label($item['listing_type'])?></span><?php endif; ?></div>
            <div class="listing-title"><?=h($item['title'])?></div>
            <div class="listing-meta">
              <span><?=h($item['category_name'])?></span><span>·</span><span><?=time_ago($item['created_at'])?></span>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php if ($total_pages > 1): ?>
      <div class="flex justify-center gap-2 mt-10">
        <?php for ($i=1; $i<=$total_pages; $i++): ?>
        <a href="/catalog/<?=$cat_slug?>?page=<?=$i?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-sm font-medium transition-all <?=$i===$page_num?'bg-accent text-white':'hover:bg-muted text-muted-foreground'?>"><?=$i?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
