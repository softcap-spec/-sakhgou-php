<?php
// search.php — v4 with filters
$q = trim($_GET['q'] ?? '');
$page_num = max(1, (int)($_GET['page'] ?? 1));
$filters = [
  'price_min' => $_GET['price_min'] ?? '',
  'price_max' => $_GET['price_max'] ?? '',
  'location' => $_GET['location'] ?? '',
  'type' => $_GET['type'] ?? '',
];

$result = get_listings('', $q, $page_num, 'active', $filters);
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

<section style="padding:3rem 0 4rem">
  <div style="max-width:1200px;margin:0 auto;padding:0 1rem">

    <!-- Search bar -->
    <div style="max-width:40rem;margin-bottom:1.5rem">
      <form action="/search" method="get">
        <div style="display:flex;align-items:center;background:#fff;border:1px solid #DFE4EA;border-radius:10px;overflow:hidden;box-shadow:0 4px 12px rgba(15,23,32,0.06);transition:border-color 0.15s ease,box-shadow 0.15s ease" onmouseover="this.style.borderColor='#C8D0DA'" onmouseout="this.style.borderColor='#DFE4EA'">
          <div style="flex:1;display:flex;align-items:center;gap:0.625rem;padding:0 1rem">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7A8A9A" stroke-width="2"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Что ищете?" autofocus style="flex:1;border:0;padding:0.75rem 0;font-size:0.9375rem;outline:none;background:transparent;box-shadow:none">
          </div>
          <button type="submit" style="background:#121E2B;color:#F7F9FB;border:0;padding:0.75rem 1.5rem;font-size:0.8125rem;font-weight:600;cursor:pointer;white-space:nowrap;font-family:inherit;transition:background 0.15s ease" onmouseover="this.style.background='#1A2937'" onmouseout="this.style.background='#121E2B'">Найти</button>
        </div>
      </form>
    </div>

    <!-- Filters -->
    <?php $types = ['property'=>'Жильё','tour'=>'Туры','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто']; ?>
    <form action="/search" method="get" style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:1rem 1.25rem;margin-bottom:2rem;box-shadow:0 4px 12px rgba(15,23,32,0.04)">
      <input type="hidden" name="q" value="<?= h($q) ?>">
      <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:1;min-width:130px">
          <label style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.08em;color:#5A6B7D;font-weight:600;display:block;margin-bottom:0.375rem">Цена от, ₽</label>
          <input type="number" name="price_min" value="<?= h($filters['price_min']) ?>" min="0" placeholder="0" style="width:100%;box-sizing:border-box;border:1px solid #DFE4EA;border-radius:8px;padding:0.5rem 0.75rem;font-size:0.875rem;outline:none;font-family:inherit">
        </div>
        <div style="flex:1;min-width:130px">
          <label style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.08em;color:#5A6B7D;font-weight:600;display:block;margin-bottom:0.375rem">Цена до, ₽</label>
          <input type="number" name="price_max" value="<?= h($filters['price_max']) ?>" min="0" placeholder="∞" style="width:100%;box-sizing:border-box;border:1px solid #DFE4EA;border-radius:8px;padding:0.5rem 0.75rem;font-size:0.875rem;outline:none;font-family:inherit">
        </div>
        <div style="flex:1.5;min-width:160px">
          <label style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.08em;color:#5A6B7D;font-weight:600;display:block;margin-bottom:0.375rem">Локация</label>
          <input type="text" name="location" value="<?= h($filters['location']) ?>" placeholder="Южно-Сахалинск" style="width:100%;box-sizing:border-box;border:1px solid #DFE4EA;border-radius:8px;padding:0.5rem 0.75rem;font-size:0.875rem;outline:none;font-family:inherit">
        </div>
        <div style="flex:1.5;min-width:160px">
          <label style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.08em;color:#5A6B7D;font-weight:600;display:block;margin-bottom:0.375rem">Тип</label>
          <select name="type" style="width:100%;box-sizing:border-box;border:1px solid #DFE4EA;border-radius:8px;padding:0.5rem 0.75rem;font-size:0.875rem;outline:none;font-family:inherit;background:#fff">
            <option value="">Все типы</option>
            <?php foreach ($types as $tk=>$tv): ?>
            <option value="<?=$tk?>" <?=$filters['type']===$tk?'selected':''?>><?=$tv?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex;gap:0.5rem">
          <button type="submit" style="background:#121E2B;color:#F7F9FB;border:0;border-radius:8px;padding:0.5rem 1.25rem;font-size:0.8125rem;font-weight:600;cursor:pointer;font-family:inherit;white-space:nowrap">Применить</button>
          <a href="/search<?=$q?'?q='.urlencode($q):''?>" style="display:inline-flex;align-items:center;border:1px solid #DFE4EA;border-radius:8px;padding:0.5rem 1rem;font-size:0.8125rem;font-weight:500;color:#5A6B7D;text-decoration:none;white-space:nowrap">Сбросить</a>
        </div>
      </div>
    </form>

    <?php if (!empty($q)): ?>
      <span style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.1em;color:#7A8A9A;font-weight:500">Результаты поиска</span>
      <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.75rem;letter-spacing:-0.02em;margin:0.25rem 0 2rem">&laquo;<?= h($q) ?>&raquo; &mdash; <?= $total ?> результатов</h2>
    <?php else: ?>
      <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.75rem;letter-spacing:-0.02em;margin:0 0 2rem">Поиск</h2>
    <?php endif; ?>

    <?php if (empty($listings)): ?>
      <div style="text-align:center;padding:5rem 1rem">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#C8D0DA" stroke-width="1.5" style="margin-bottom:1.25rem">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.34-4.34"/>
        </svg>
        <p style="font-size:1rem;font-weight:600;color:#121E2B;margin:0 0 0.25rem">Ничего не найдено</p>
        <p style="font-size:0.8125rem;color:#7A8A9A;margin:0 0 1.5rem">Попробуйте изменить запрос</p>
        <a href="/" class="btn-outline">На главную</a>
      </div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem">
        <?php foreach ($listings as $item): ?>
        <a href="/listing/<?= $item['id'] ?>" class="listing-card">
          <?php if (!empty($item['image'])): ?>
          <div class="listing-img"><img src="/uploads/<?= h($item['image']) ?>" alt="<?= h($item['title']) ?>" loading="lazy"></div>
          <?php else: ?>
          <div class="listing-img" style="display:flex;align-items:center;justify-content:center;color:#C8D0DA">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          </div>
          <?php endif; ?>
          <div class="listing-body">
            <div class="listing-price"><?=price_text($item)?><?php if (!price_is_negotiable($item) && (float)$item['price'] > 0): ?> ₽<?php endif; ?></div>
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
      <div style="display:flex;justify-content:center;gap:0.375rem;margin-top:2.5rem">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?q=<?= urlencode($q) ?>&price_min=<?= urlencode($filters['price_min']) ?>&price_max=<?= urlencode($filters['price_max']) ?>&location=<?= urlencode($filters['location']) ?>&type=<?= urlencode($filters['type']) ?>&page=<?= $i ?>"
           style="display:inline-flex;align-items:center;justify-content:center;min-width:2.25rem;height:2.25rem;border-radius:8px;font-size:0.8125rem;font-weight:500;text-decoration:none;transition:all 0.15s ease;<?=$i===$page_num?'background:#121E2B;color:#F7F9FB':'color:#7A8A9A;border:1px solid #DFE4EA'?>"
           onmouseover="if(<?=$i!==$page_num?'true':'false'?>){this.style.background='#EEF2F6';this.style.color='#121E2B'}"
           onmouseout="if(<?=$i!==$page_num?'true':'false'?>){this.style.background='transparent';this.style.color='#7A8A9A'}"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
