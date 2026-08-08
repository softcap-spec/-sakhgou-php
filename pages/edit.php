<?php
// edit.php — редактирование объявления
$user = auth_required();
$listing_id = (int)($id ?? 0);
$pdo = db();

// Get listing
$stmt = $pdo->prepare('SELECT l.*, c.slug AS category_slug FROM listings l JOIN categories c ON l.category_id = c.id WHERE l.id = ? AND l.user_id = ?');
$stmt->execute([$listing_id, $user['id']]);
$item = $stmt->fetch();

if (!$item) {
  http_response_code(404);
  $page_title = 'Не найдено — СахGO';
  require __DIR__ . '/../includes/header.php';
  echo '<section class="py-20"><div class="max-w-7xl mx-auto px-4 text-center text-muted-foreground"><p class="text-lg">Объявление не найдено или нет доступа</p><a href="/dashboard" class="btn-outline mt-4" style="display:inline-flex">В кабинет</a></div></section>';
  require __DIR__ . '/../includes/footer.php';
  exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');
  $price = (float)($_POST['price'] ?? 0);
  $description = trim($_POST['description'] ?? '');
  $location = trim($_POST['location'] ?? '');

  if (empty($title)) $errors[] = 'Введите название';
  if ($price <= 0) $errors[] = 'Укажите цену';
  if (empty($description)) $errors[] = 'Добавьте описание';

  if (empty($errors)) {
    $stmt = $pdo->prepare('UPDATE listings SET title = ?, description = ?, price = ?, location = ? WHERE id = ?');
    $stmt->execute([$title, $description, $price, $location, $listing_id]);
    header('Location: /listing/' . $listing_id);
    exit;
  }
}

$page_title = 'Редактировать — СахGO';
require __DIR__ . '/../includes/header.php';
?>
<section class="py-12">
  <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
      <span class="text-xs uppercase tracking-[0.12em] text-accent font-medium">Редактирование</span>
      <h1 class="font-display text-4xl mt-1">Редактировать объявление</h1>
    </div>

    <?php foreach ($errors as $e): ?><div class="flash error"><?= h($e) ?></div><?php endforeach; ?>

    <div class="bg-white border rounded-xl p-6">
      <form method="post">
        <div class="form-group">
          <label>Название</label>
          <input type="text" name="title" value="<?= h($_POST['title'] ?? $item['title']) ?>" required>
        </div>
        <div class="form-group">
          <label>Категория</label>
          <input type="text" value="<?= h($categories[$item['category_slug']]['name'] ?? $item['category_slug']) ?>" disabled style="opacity:0.6">
        </div>
        <div class="form-group">
          <label>Цена (₽)</label>
          <input type="number" name="price" value="<?= h($_POST['price'] ?? $item['price']) ?>" min="1" required>
        </div>
        <div class="form-group">
          <label>Описание</label>
          <textarea name="description" rows="5" required><?= h($_POST['description'] ?? $item['description']) ?></textarea>
        </div>
        <div class="form-group">
          <label>Местоположение</label>
          <input type="text" name="location" value="<?= h($_POST['location'] ?? $item['location']) ?>">
        </div>
        <div class="flex gap-2">
          <a href="/dashboard" class="btn-outline" style="flex:1;text-align:center">← Отмена</a>
          <button type="submit" class="cta-btn" style="flex:1">Сохранить</button>
        </div>
      </form>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
