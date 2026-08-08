<?php
// search.php
$q = trim($_GET['q'] ?? '');
$page_num = max(1, (int)($_GET['page'] ?? 1));

$result = get_listings('', $q, $page_num);
$listings = $result['items'];
$total = $result['total'];
$total_pages = $result['pages'];

foreach ($listings as &$item) {
  $item['time_ago'] = time_ago($item['created_at']);
}
unset($item);

$page_title = (!empty($q) ? 'Поиск: ' . $q : 'Поиск') . ' — СахGO';
require __DIR__ . '/../includes/header.php';
?>
<section class="py-12">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mb-10">
      <form action="/search" method="get" class="relative group search-group">
        <div class="search-bar-glow"></div>
        <div class="search-bar-wrap">
          <div class="flex-1 flex items-center gap-3 px-5">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="search-icon"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Что ищете?" class="search-input-hero flex-1" autofocus>
          </div>
          <div class="p-2 pr-2"><button type="submit" class="search-submit">Найти</button></div>
        </div>
      </form>
    </div>

    <?php if (!empty($q)): ?>
      <span class="text-xs uppercase tracking-[0.12em] text-accent font-medium">Результаты поиска</span>
      <h2 class="font-display text-4xl mt-1 mb-8">«<?= h($q) ?>» — <?= $total ?> результатов</h2>
    <?php else: ?>
      <h2 class="font-display text-4xl mb-8">Поиск</h2>
    <?php endif; ?>

    <?php if (empty($listings)): ?>
      <div class="text-center py-20 text-muted-foreground">
        <p class="text-lg">Ничего не найдено</p>
        <p class="text-sm mt-1 mb-4">Попробуйте изменить запрос</p>
        <a href="/" class="btn-outline">На главную</a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
        <?php foreach ($listings as $item): ?>
        <a href="/listing/<?= $item['id'] ?>" class="listing-card">
          <?php if (!empty($item['image'])): ?>
          <img src="/uploads/<?= h($item['image']) ?>" alt="<?= h($item['title']) ?>" class="listing-img" loading="lazy">
          <?php else: ?>
          <div class="listing-img" style="display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:var(--muted)">📷</div>
          <?php endif; ?>
          <div class="listing-body">
            <div class="listing-price"><?= format_price((float)$item['price']) ?></div>
            <div class="listing-title"><?= h($item['title']) ?></div>
            <div class="listing-meta">
              <span><?= h($item['category_name'] ?? '') ?></span>
              <span><?= $item['time_ago'] ?></span>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?q=<?= urlencode($q) ?>&page=<?= $i ?>" class="<?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
