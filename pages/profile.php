<?php
// profile.php — compatible with auth_user()
$user = auth_required();

// Get user's listings
$pdo = db();
$stmt = $pdo->prepare('SELECT l.*, c.name AS category_name FROM listings l JOIN categories c ON l.category_id = c.id WHERE l.user_id = ? ORDER BY l.created_at DESC');
$stmt->execute([$user['id']]);
$my_listings = $stmt->fetchAll();

foreach ($my_listings as &$item) {
  $item['time_ago'] = time_ago($item['created_at']);
}
unset($item);

$page_title = 'Профиль — СахGO';
require __DIR__ . '/../includes/header.php';
?>
<section class="py-12">
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
    <span class="text-xs uppercase tracking-[0.12em] text-accent font-medium">Профиль</span>
    <h1 class="font-display text-4xl mt-1 mb-8"><?= h($user['name']) ?></h1>

    <div class="bg-white border rounded-xl p-6 mb-8">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div><span class="text-muted-foreground">Email:</span> <?= h($user['email']) ?></div>
        <div><span class="text-muted-foreground">Телефон:</span> <?= h($user['phone'] ?? '—') ?></div>
        <div><span class="text-muted-foreground">Регистрация:</span> <?= h($user['created_at'] ?? '') ?></div>
        <div><span class="text-muted-foreground">Объявлений:</span> <?= count($my_listings) ?></div>
      </div>
    </div>

    <h2 class="font-display text-2xl mb-6">Мои объявления</h2>
    <?php if (empty($my_listings)): ?>
      <div class="text-center py-12 text-muted-foreground">
        <p class="text-lg">У вас пока нет объявлений</p>
        <a href="/create" class="cta-btn mt-4" style="display:inline-flex">Подать объявление</a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-6">
        <?php foreach ($my_listings as $item): ?>
        <a href="/listing/<?= $item['id'] ?>" class="listing-card">
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
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
