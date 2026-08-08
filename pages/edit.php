<?php
// edit.php — полное редактирование объявления
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$user = auth_required();
$listing_id = (int)($id ?? 0);
$pdo = db();
define('UPLOAD_URL', '/uploads/');

$stmt = $pdo->prepare('SELECT * FROM listings WHERE id = ? AND user_id = ?');
$stmt->execute([$listing_id, $user['id']]);
$item = $stmt->fetch();

if (!$item && $user['role'] !== 'admin') {
  http_response_code(404);
  $page_title = 'Не найдено — СахГО';
  require __DIR__ . '/../includes/header.php';
  echo '<section class="py-20"><div class="max-w-7xl mx-auto px-4 text-center"><p class="text-lg">Объявление не найдено или нет доступа</p><a href="/dashboard" class="inline-flex items-center justify-center rounded-lg bg-accent text-white h-10 px-4 text-sm font-medium mt-4">В кабинет</a></div></section>';
  require __DIR__ . '/../includes/footer.php';
  exit;
}

// Allow admin to view any
if (!$item && $user['role'] === 'admin') {
  $stmt = $pdo->prepare('SELECT * FROM listings WHERE id = ?');
  $stmt->execute([$listing_id]);
  $item = $stmt->fetch();
}
if (!$item) { http_response_code(404); echo 'Not found'; exit; }

// ── AJAX Image Management ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['image_action']) || !empty($_FILES['image']['name']))) {
  csrf_check();
  header('Content-Type: application/json; charset=utf-8');

  // Delete image
  if (($_POST['image_action'] ?? '') === 'delete') {
    $img_id = (int)($_POST['image_id'] ?? 0);
    $img = $pdo->prepare("SELECT filename FROM listing_images WHERE id = ? AND listing_id = ?");
    $img->execute([$img_id, $listing_id]);
    $imgRow = $img->fetch();
    if ($imgRow) {
      @unlink(UPLOAD_DIR . '/' . $imgRow['filename']);
      $pdo->prepare("DELETE FROM listing_images WHERE id = ?")->execute([$img_id]);
      echo json_encode(['ok' => true]);
    } else { echo json_encode(['ok' => false, 'error' => 'Не найдено']); }
    exit;
  }

  // Set cover image
  if (($_POST['image_action'] ?? '') === 'cover') {
    $img_id = (int)($_POST['image_id'] ?? 0);
    $check = $pdo->prepare("SELECT id FROM listing_images WHERE id = ? AND listing_id = ?");
    $check->execute([$img_id, $listing_id]);
    if ($check->fetch()) {
      $pdo->prepare("UPDATE listing_images SET sort_order = sort_order + 1 WHERE listing_id = ?")->execute([$listing_id]);
      $pdo->prepare("UPDATE listing_images SET sort_order = 0 WHERE id = ?")->execute([$img_id]);
      echo json_encode(['ok' => true]);
    } else { echo json_encode(['ok' => false]); }
    exit;
  }

  // Upload new image
  if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed)) {
      echo json_encode(['ok' => false, 'error' => 'Только JPG, PNG, WebP']);
      exit;
    }
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
    $fn = 'listing_' . $listing_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . '/' . $fn)) {
      $maxSort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), -1) FROM listing_images WHERE listing_id = $listing_id")->fetchColumn();
      $pdo->prepare("INSERT INTO listing_images (listing_id, filename, sort_order) VALUES (?,?,?)")->execute([$listing_id, $fn, $maxSort + 1]);
      echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'url' => UPLOAD_URL . $fn]);
    } else {
      echo json_encode(['ok' => false, 'error' => 'Ошибка сохранения']);
    }
    exit;
  }

  echo json_encode(['ok' => false, 'error' => 'Нет файла']);
  exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $title = trim($_POST['title'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $price = (float)($_POST['price'] ?? 0);
  $location = trim($_POST['location'] ?? '');
  $type = $_POST['listing_type'] ?? 'property';
  $cat_id = (int)($_POST['category_id'] ?? 0);

  if (empty($title)) $errors[] = 'Введите название';
  if ($price <= 0) $errors[] = 'Укажите цену';
  if (empty($description)) $errors[] = 'Добавьте описание';

  if (empty($errors)) {
    // Build update dynamically from POST
    $fields = ['title', 'description', 'price', 'location', 'listing_type', 'category_id'];
    $values = [$title, $description, $price, $location, $type, $cat_id];

    // Type-specific fields
    $typeFields = [
      'property' => ['max_guests', 'rooms_count', 'beds_count', 'amenities', 'check_in_time', 'check_out_time', 'rules'],
      'tour' => ['tour_duration_hours', 'tour_duration_days', 'max_guests', 'difficulty', 'includes', 'what_to_bring', 'meeting_point'],
      'fishing' => ['tour_duration_hours', 'max_guests', 'fish_types', 'fishing_method', 'boat_type', 'includes', 'what_to_bring', 'meeting_point', 'license_required'],
      'rental_gear' => ['gear_type', 'sizes', 'condition', 'includes', 'deposit', 'max_guests'],
      'car_rental' => ['car_type', 'transmission', 'seats', 'fuel', 'mileage', 'deposit', 'requirements', 'includes'],
    ];

    $map = [
      'max_guests' => 'max_guests', 'rooms_count' => 'rooms_count', 'beds_count' => 'beds_count',
      'amenities' => 'amenities', 'check_in_time' => 'check_in_time', 'check_out_time' => 'check_out_time',
      'rules' => 'rules', 'tour_duration_hours' => 'tour_duration_hours',
      'tour_duration_days' => 'tour_duration_days', 'difficulty' => 'difficulty_level',
      'includes' => 'includes', 'what_to_bring' => 'what_to_bring', 'meeting_point' => 'meeting_point',
      'fish_types' => 'fish_species', 'fishing_method' => 'fishing_method', 'boat_type' => 'boat_type',
      'license_required' => 'license_required', 'gear_type' => 'gear_type', 'sizes' => 'sizes',
      'condition' => 'gear_condition', 'deposit' => 'deposit_amount', 'car_type' => 'car_type',
      'transmission' => 'transmission', 'seats' => 'seats', 'fuel' => 'fuel',
      'mileage' => 'mileage', 'requirements' => 'requirements',
    ];

    if (isset($typeFields[$type])) {
      foreach ($typeFields[$type] as $f) {
        $dbCol = $map[$f] ?? $f;
        $val = $_POST[$f] ?? null;
        if (is_array($val)) $val = json_encode($val, JSON_UNESCAPED_UNICODE);
        elseif ($f === 'license_required') $val = isset($_POST[$f]) ? 1 : 0;
        $fields[] = $dbCol;
        $values[] = $val;
      }
    }

    // Build SQL
    $setClause = implode(', ', array_map(fn($f) => "$f = ?", $fields));
    $values[] = $listing_id;
    $stmt = $pdo->prepare("UPDATE listings SET $setClause WHERE id = ?");
    $stmt->execute($values);

    // Refresh
    $stmt = $pdo->prepare('SELECT * FROM listings WHERE id = ?');
    $stmt->execute([$listing_id]);
    $item = $stmt->fetch();
    $success = true;
  }
}

$page_title = 'Редактировать: ' . h($item['title']) . ' — СахГО';
require __DIR__ . '/../includes/header.php';
?>

<section class="py-8">
  <div class="max-w-3xl mx-auto px-4">
    <div class="flex items-center gap-3 mb-6">
      <a href="/dashboard" class="text-muted-foreground hover:text-accent">&larr; В кабинет</a>
      <span class="text-muted-foreground">/</span>
      <h1 class="font-display text-2xl">Редактирование</h1>
    </div>

    <?php if ($success): ?>
      <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-4 mb-6">Объявление обновлено! <a href="/listing/<?= $item['id'] ?>" class="underline font-medium">Посмотреть</a></div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 mb-4 text-sm"><?= h($e) ?></div><?php endforeach; ?>

    <form method="post" class="bg-white border rounded-xl p-6 md:p-8 space-y-6">
      <?= csrf_field() ?>

      <!-- Basic Info -->
      <div class="space-y-4">
        <h2 class="font-display text-lg pb-2 border-b">Основное</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Название</label>
            <input type="text" name="title" value="<?= h($item['title']) ?>" required class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Цена (₽)</label>
            <input type="number" name="price" value="<?= (int)$item['price'] ?>" min="0" step="1" required class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Тип</label>
            <select name="listing_type" id="listing_type" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none bg-white">
              <?php foreach (['property'=>'Жильё','tour'=>'Туры','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто'] as $k=>$v): ?>
                <option value="<?=$k?>" <?=$item['listing_type']===$k?'selected':''?>><?=$v?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Категория</label>
            <select name="category_id" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none bg-white">
              <?php
              $cats = $pdo->query("SELECT id, name, slug FROM categories ORDER BY id")->fetchAll();
              foreach ($cats as $c): ?>
                <option value="<?=$c['id']?>" <?=$item['category_id']==$c['id']?'selected':''?>><?=h($c['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Локация</label>
            <input type="text" name="location" value="<?= h($item['location'] ?? '') ?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none">
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Описание</label>
            <textarea name="description" rows="6" required class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"><?= h($item['description'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Property Fields -->
      <div id="prop-fields" class="space-y-4 <?=$item['listing_type']!=='property'?'hidden':''?>">
        <h2 class="font-display text-lg pb-2 border-b">Параметры жилья</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <div><label class="block text-sm font-medium mb-1">Гостей</label><input type="number" name="max_guests" value="<?=(int)($item['max_guests']??1)?>" min="1" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Комнат</label><input type="number" name="rooms_count" value="<?=(int)($item['rooms_count']??1)?>" min="0" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Кроватей</label><input type="number" name="beds_count" value="<?=(int)($item['beds_count']??1)?>" min="0" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Заезд</label><input type="text" name="check_in_time" value="<?=h($item['check_in_time']??'14:00')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Выезд</label><input type="text" name="check_out_time" value="<?=h($item['check_out_time']??'12:00')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Удобства</label>
          <?php $am = json_decode($item['amenities']??'[]',true)?:[]; ?>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <?php foreach (['Wi-Fi','Кухня','Парковка','Стиральная машина','Кондиционер','Телевизор','Балкон','Отопление','Фен','Утюг','Посуда','Полотенца'] as $a): ?>
              <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" name="amenities[]" value="<?=$a?>" <?=in_array($a,$am)?'checked':''?> class="rounded"> <?=$a?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Правила</label>
          <textarea name="rules" rows="3" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"><?=h($item['rules']??'')?></textarea>
        </div>
      </div>

      <!-- Tour Fields -->
      <div id="tour-fields" class="space-y-4 <?=$item['listing_type']!=='tour'?'hidden':''?>">
        <h2 class="font-display text-lg pb-2 border-b">Параметры тура</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <div><label class="block text-sm font-medium mb-1">Длит. (часов)</label><input type="number" name="tour_duration_hours" value="<?=(int)($item['tour_duration_hours']??0)?>" min="0" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Длит. (дней)</label><input type="number" name="tour_duration_days" value="<?=(int)($item['tour_duration_days']??0)?>" min="0" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Группа (чел.)</label><input type="number" name="max_guests" value="<?=(int)($item['max_guests']??1)?>" min="1" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div>
            <label class="block text-sm font-medium mb-1">Сложность</label>
            <select name="difficulty" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none bg-white">
              <?php foreach (['easy'=>'Лёгкий','medium'=>'Средний','hard'=>'Сложный','extreme'=>'Экстремальный'] as $k=>$v): ?>
                <option value="<?=$k?>" <?=($item['difficulty_level']??'')===$k?'selected':''?>><?=$v?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div><label class="block text-sm font-medium mb-1">Что включено</label><input type="text" name="includes" value="<?=h($item['includes']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
        <div><label class="block text-sm font-medium mb-1">Что взять с собой</label><input type="text" name="what_to_bring" value="<?=h($item['what_to_bring']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
        <div><label class="block text-sm font-medium mb-1">Место встречи</label><input type="text" name="meeting_point" value="<?=h($item['meeting_point']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
      </div>

      <!-- Fishing Fields -->
      <div id="fish-fields" class="space-y-4 <?=$item['listing_type']!=='fishing'?'hidden':''?>">
        <h2 class="font-display text-lg pb-2 border-b">Параметры рыбалки</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <div><label class="block text-sm font-medium mb-1">Длит. (часов)</label><input type="number" name="tour_duration_hours" value="<?=(int)($item['tour_duration_hours']??0)?>" min="0" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Группа (чел.)</label><input type="number" name="max_guests" value="<?=(int)($item['max_guests']??1)?>" min="1" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div>
            <label class="block text-sm font-medium mb-1">Вид ловли</label>
            <select name="fishing_method" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none bg-white">
              <?php foreach (['spin'=>'Спиннинг','fly'=>'Нахлыст','troll'=>'Троллинг','ice'=>'Зимняя','float'=>'Поплавочная'] as $k=>$v): ?>
                <option value="<?=$k?>" <?=($item['fishing_method']??'')===$k?'selected':''?>><?=$v?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label class="block text-sm font-medium mb-1">Рыба</label><input type="text" name="fish_types" value="<?=h($item['fish_species']??'')?>" placeholder="Кунджа, горбуша, таймень" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Тип лодки</label><input type="text" name="boat_type" value="<?=h($item['boat_type']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div class="flex items-center pt-6"><label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" name="license_required" value="1" <?=$item['license_required']?'checked':''?> class="rounded"> Нужна лицензия</label></div>
        </div>
        <div><label class="block text-sm font-medium mb-1">Что включено</label><input type="text" name="includes" value="<?=h($item['includes']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
        <div><label class="block text-sm font-medium mb-1">Что взять</label><input type="text" name="what_to_bring" value="<?=h($item['what_to_bring']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
        <div><label class="block text-sm font-medium mb-1">Место встречи</label><input type="text" name="meeting_point" value="<?=h($item['meeting_point']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
      </div>

      <!-- Gear Fields -->
      <div id="gear-fields" class="space-y-4 <?=$item['listing_type']!=='rental_gear'?'hidden':''?>">
        <h2 class="font-display text-lg pb-2 border-b">Параметры снаряжения</h2>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="block text-sm font-medium mb-1">Тип снаряжения</label><input type="text" name="gear_type" value="<?=h($item['gear_type']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Состояние</label><input type="text" name="condition" value="<?=h($item['gear_condition']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Размеры</label><input type="text" name="sizes" value="<?=h($item['sizes']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Доступно (шт.)</label><input type="number" name="max_guests" value="<?=(int)($item['max_guests']??1)?>" min="1" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Залог (₽)</label><input type="number" name="deposit" value="<?=(int)($item['deposit_amount']??0)?>" min="0" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
        </div>
        <div><label class="block text-sm font-medium mb-1">Что включено</label><input type="text" name="includes" value="<?=h($item['includes']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
      </div>

      <!-- Car Fields -->
      <div id="car-fields" class="space-y-4 <?=$item['listing_type']!=='car_rental'?'hidden':''?>">
        <h2 class="font-display text-lg pb-2 border-b">Параметры авто</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <div><label class="block text-sm font-medium mb-1">Марка/модель</label><input type="text" name="car_type" value="<?=h($item['car_type']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div>
            <label class="block text-sm font-medium mb-1">Коробка</label>
            <select name="transmission" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none bg-white">
              <?php foreach (['auto'=>'Автомат','manual'=>'Механика'] as $k=>$v): ?>
                <option value="<?=$k?>" <?=($item['transmission']??'')===$k?'selected':''?>><?=$v?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label class="block text-sm font-medium mb-1">Мест</label><input type="number" name="seats" value="<?=(int)($item['seats']??5)?>" min="1" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Топливо</label><input type="text" name="fuel" value="<?=h($item['fuel']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Пробег (км/день)</label><input type="number" name="mileage" value="<?=(int)($item['mileage']??0)?>" min="0" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
          <div><label class="block text-sm font-medium mb-1">Залог (₽)</label><input type="number" name="deposit" value="<?=(int)($item['deposit_amount']??0)?>" min="0" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
        </div>
        <div><label class="block text-sm font-medium mb-1">Требования</label><input type="text" name="requirements" value="<?=h($item['requirements']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
        <div><label class="block text-sm font-medium mb-1">Что включено</label><input type="text" name="includes" value="<?=h($item['includes']??'')?>" class="w-full rounded-lg border border-border py-2 px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none"></div>
      </div>

      <!-- Photos -->
      <div class="space-y-4">
        <h2 class="font-display text-lg pb-2 border-b">Фотографии</h2>
        <?php
        $imgs = $pdo->prepare("SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id");
        $imgs->execute([$listing_id]);
        $images = $imgs->fetchAll();
        ?>
        <div id="photoGrid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3">
          <?php foreach ($images as $img): ?>
            <div class="relative group rounded-lg overflow-hidden border border-border bg-muted/20" data-img-id="<?=$img['id']?>">
              <img src="<?=UPLOAD_URL . $img['filename']?>" class="w-full h-24 object-cover" alt="">
              <?php if ($img['sort_order'] === 0): ?>
                <span class="absolute top-1 left-1 bg-accent text-white text-[10px] px-1.5 py-0.5 rounded font-medium">Обложка</span>
              <?php else: ?>
                <button type="button" onclick="setCover(<?=$img['id']?>, this)" class="absolute top-1 left-1 bg-black/50 hover:bg-accent text-white text-[10px] px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity">Обложка</button>
              <?php endif; ?>
              <button type="button" onclick="deleteImage(<?=$img['id']?>, this)" class="absolute top-1 right-1 w-5 h-5 bg-red-500 hover:bg-red-600 text-white text-xs rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">&times;</button>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="flex gap-3">
          <label class="inline-flex items-center justify-center rounded-lg border border-border hover:bg-muted h-10 px-4 text-sm font-medium cursor-pointer transition-all">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Добавить фото
            <input type="file" id="photoInput" accept="image/*" class="hidden" onchange="uploadImage()">
          </label>
          <span id="uploadStatus" class="text-sm text-muted-foreground self-center hidden"></span>
        </div>
      </div>

      <div class="flex gap-3 pt-4">
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-accent text-white h-10 px-6 text-sm font-medium hover:opacity-90 transition-all">Сохранить</button>
        <a href="/dashboard" class="inline-flex items-center justify-center rounded-lg border border-border hover:bg-muted h-10 px-4 text-sm font-medium transition-all">Отмена</a>
      </div>
    </form>
  </div>
</section>

<script>
document.getElementById('listing_type').addEventListener('change', function() {
  document.getElementById('prop-fields').classList.add('hidden');
  document.getElementById('tour-fields').classList.add('hidden');
  document.getElementById('fish-fields').classList.add('hidden');
  document.getElementById('gear-fields').classList.add('hidden');
  document.getElementById('car-fields').classList.add('hidden');
  var block = this.value === 'property' ? 'prop-fields' : this.value === 'tour' ? 'tour-fields' : this.value === 'fishing' ? 'fish-fields' : this.value === 'rental_gear' ? 'gear-fields' : 'car-fields';
  document.getElementById(block).classList.remove('hidden');
});

// Image management
var csrfToken = document.querySelector('input[name="_csrf"]')?.value || '';

function uploadImage() {
  var file = document.getElementById('photoInput').files[0];
  if (!file) return;
  var fd = new FormData();
  fd.append('image', file);
  fd.append('_csrf', csrfToken);
  var status = document.getElementById('uploadStatus');
  status.classList.remove('hidden');
  status.textContent = 'Загрузка...';
  fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
    if (data.ok) {
      var grid = document.getElementById('photoGrid');
      var card = document.createElement('div');
      card.className = 'relative group rounded-lg overflow-hidden border border-border bg-muted/20';
      card.setAttribute('data-img-id', data.id);
      card.innerHTML = '<img src="'+data.url+'" class="w-full h-24 object-cover" alt="">' +
        '<button type="button" onclick="setCover('+data.id+', this)" class="absolute top-1 left-1 bg-black/50 hover:bg-accent text-white text-[10px] px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity">Обложка</button>' +
        '<button type="button" onclick="deleteImage('+data.id+', this)" class="absolute top-1 right-1 w-5 h-5 bg-red-500 hover:bg-red-600 text-white text-xs rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">&times;</button>';
      grid.appendChild(card);
      status.textContent = 'Готово';
      status.classList.add('text-green-600');
    } else {
      status.textContent = (data.error || 'Ошибка');
      status.classList.add('text-red-600');
    }
    document.getElementById('photoInput').value = '';
    setTimeout(function(){ status.classList.add('hidden'); status.className = 'text-sm text-muted-foreground self-center hidden'; }, 2000);
  });
}

function deleteImage(id, btn) {
  if (!confirm('Удалить фото?')) return;
  var fd = new FormData();
  fd.append('image_action', 'delete');
  fd.append('image_id', id);
  fd.append('_csrf', csrfToken);
  fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
    if (data.ok) {
      var card = btn.closest('[data-img-id]');
      if (card) card.remove();
    } else { alert('Ошибка удаления'); }
  });
}

function setCover(id, btn) {
  var fd = new FormData();
  fd.append('image_action', 'cover');
  fd.append('image_id', id);
  fd.append('_csrf', csrfToken);
  fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
    if (data.ok) {
      // Remove all cover badges
      document.querySelectorAll('#photoGrid [data-img-id]').forEach(function(c) {
        var badge = c.querySelector('.bg-accent, [onclick^="setCover"]');
        if (badge && badge.tagName === 'SPAN') badge.remove();
        var covBtn = c.querySelector('button[onclick^="setCover"]');
        if (covBtn) covBtn.remove();
      });
      // Add cover badge to this one
      var card = btn.closest('[data-img-id]');
      if (card) {
        var badge = document.createElement('span');
        badge.className = 'absolute top-1 left-1 bg-accent text-white text-[10px] px-1.5 py-0.5 rounded font-medium';
        badge.textContent = 'Обложка';
        card.querySelector('.relative.group, .relative')?.prepend(badge);
      }
      // Refresh to show correct badges
      location.reload();
    } else { alert('Ошибка'); }
  });
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
