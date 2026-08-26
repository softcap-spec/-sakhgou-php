<?php
// edit.php — v3 clean design with photo manager
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
  echo '<section style="padding:5rem 0"><div style="max-width:1200px;margin:0 auto;padding:0 1rem;text-align:center"><p style="font-size:1rem;font-weight:600">Объявление не найдено или нет доступа</p><a href="/dashboard" class="cta-btn" style="display:inline-flex;margin-top:1rem">В кабинет</a></div></section>';
  require __DIR__ . '/../includes/footer.php';
  exit;
}

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
  $priceType = in_array($_POST['price_type'] ?? 'fixed', ['fixed','from','negotiable'], true) ? $_POST['price_type'] : 'fixed';
  $price = (float)($_POST['price'] ?? 0);
  if ($priceType === 'negotiable') $price = 0;
  $location = trim($_POST['location'] ?? '');
  $type = $_POST['listing_type'] ?? 'property';
  $cat_id = (int)($_POST['category_id'] ?? 0);

  if (empty($title)) $errors[] = 'Введите название';
  if ($priceType !== 'negotiable' && $price <= 0) $errors[] = 'Укажите цену';
  if (empty($description)) $errors[] = 'Добавьте описание';

  if (empty($errors)) {
    $fields = ['title', 'description', 'price', 'price_type', 'location', 'listing_type', 'category_id'];
    $values = [$title, $description, $price, $priceType, $location, $type, $cat_id];

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

    $setClause = implode(', ', array_map(fn($f) => "$f = ?", $fields));
    $values[] = $listing_id;
    $stmt = $pdo->prepare("UPDATE listings SET $setClause WHERE id = ?");
    $stmt->execute($values);

    $stmt = $pdo->prepare('SELECT * FROM listings WHERE id = ?');
    $stmt->execute([$listing_id]);
    $item = $stmt->fetch();
    $success = true;
  }
}

$page_title = 'Редактировать: ' . h($item['title']) . ' — СахГО';
require __DIR__ . '/../includes/header.php';
?>

<section style="padding:2.5rem 0 4rem">
  <div style="max-width:46rem;margin:0 auto;padding:0 1rem">

    <!-- Breadcrumb -->
    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.5rem;font-size:0.8125rem">
      <a href="/dashboard" style="color:#7A8A9A;text-decoration:none;transition:color 0.15s" onmouseover="this.style.color='#1B6B8A'" onmouseout="this.style.color='#7A8A9A'">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:0.125rem"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Кабинет
      </a>
      <span style="color:#DFE4EA">/</span>
      <h1 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.25rem;margin:0;letter-spacing:-0.02em">Редактирование</h1>
    </div>

    <?php if ($success): ?>
      <div style="background:#F0FDF4;border:1px solid #BBF7D0;color:#166534;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1.5rem;font-size:0.8125rem">
        Объявление обновлено!
        <a href="/listing/<?= $item['id'] ?>" style="font-weight:600;color:#166534;margin-left:0.5rem;text-decoration:underline">Посмотреть</a>
      </div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
    <div style="background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;border-radius:8px;padding:0.625rem 1rem;margin-bottom:1rem;font-size:0.8125rem"><?= h($e) ?></div>
    <?php endforeach; ?>

    <form method="post" style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:2rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
      <?= csrf_field() ?>

      <!-- Basic Info -->
      <div style="margin-bottom:2rem">
        <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1rem;margin:0 0 1rem;padding-bottom:0.75rem;border-bottom:1px solid #EEF2F6;display:flex;align-items:center;gap:0.5rem">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7A8A9A" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          Основное
        </h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div style="grid-column:1/-1">
            <label>Название</label>
            <input type="text" name="title" value="<?= h($item['title']) ?>" required style="width:100%;box-sizing:border-box">
          </div>
          <div>
            <label>Цена</label>
            <select name="price_type" id="price_type" onchange="priceTypeChange()" style="width:100%;box-sizing:border-box;margin-bottom:0.5rem">
              <option value="fixed" <?=($item['price_type']??'fixed')==='fixed'?'selected':''?>>Точная цена</option>
              <option value="from" <?=($item['price_type']??'')==='from'?'selected':''?>>От (цена от …)</option>
              <option value="negotiable" <?=($item['price_type']??'')==='negotiable'?'selected':''?>>По договорённости</option>
            </select>
            <input type="number" name="price" id="price_input" value="<?= (int)$item['price'] ?>" min="0" step="1" required style="width:100%;box-sizing:border-box">
          </div>
          <div>
            <label>Тип</label>
            <select name="listing_type" id="listing_type" style="width:100%;box-sizing:border-box">
              <?php foreach (['property'=>'Жильё','tour'=>'Туры','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто'] as $k=>$v): ?>
                <option value="<?=$k?>" <?=$item['listing_type']===$k?'selected':''?>><?=$v?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Категория</label>
            <select name="category_id" style="width:100%;box-sizing:border-box">
              <?php
              $cats = $pdo->query("SELECT id, name, slug FROM categories ORDER BY id")->fetchAll();
              foreach ($cats as $c): ?>
                <option value="<?=$c['id']?>" <?=$item['category_id']==$c['id']?'selected':''?>><?=h($c['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="grid-column:1/-1">
            <label>Локация</label>
            <input type="text" name="location" value="<?= h($item['location'] ?? '') ?>" style="width:100%;box-sizing:border-box">
          </div>
          <div style="grid-column:1/-1">
            <label>Описание</label>
            <textarea name="description" rows="6" required style="width:100%;box-sizing:border-box"><?= h($item['description'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Property Fields -->
      <div id="prop-fields" style="margin-bottom:2rem;<?=$item['listing_type']!=='property'?'display:none':''?>">
        <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1rem;margin:0 0 1rem;padding-bottom:0.75rem;border-bottom:1px solid #EEF2F6;display:flex;align-items:center;gap:0.5rem">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7A8A9A" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          Параметры жилья
        </h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem">
          <div><label>Гостей</label><input type="number" name="max_guests" value="<?=(int)($item['max_guests']??1)?>" min="1" style="width:100%;box-sizing:border-box"></div>
          <div><label>Комнат</label><input type="number" name="rooms_count" value="<?=(int)($item['rooms_count']??1)?>" min="0" style="width:100%;box-sizing:border-box"></div>
          <div><label>Кроватей</label><input type="number" name="beds_count" value="<?=(int)($item['beds_count']??1)?>" min="0" style="width:100%;box-sizing:border-box"></div>
          <div><label>Заезд</label><input type="text" name="check_in_time" value="<?=h($item['check_in_time']??'14:00')?>" style="width:100%;box-sizing:border-box"></div>
          <div><label>Выезд</label><input type="text" name="check_out_time" value="<?=h($item['check_out_time']??'12:00')?>" style="width:100%;box-sizing:border-box"></div>
        </div>
        <div style="margin-top:1rem">
          <label>Удобства</label>
          <?php $am = json_decode($item['amenities']??'[]',true)?:[]; ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:0.375rem">
            <?php foreach (['Wi-Fi','Кухня','Парковка','Стиральная машина','Кондиционер','Телевизор','Балкон','Отопление','Фен','Утюг','Посуда','Полотенца'] as $a): ?>
              <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;cursor:pointer;padding:0.375rem 0.5rem;border-radius:6px;transition:background 0.15s" onmouseover="this.style.background='#F7F9FB'" onmouseout="this.style.background='transparent'">
                <input type="checkbox" name="amenities[]" value="<?=$a?>" <?=in_array($a,$am)?'checked':''?> style="width:1rem;height:1rem;accent-color:#121E2B"> <?=$a?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div style="margin-top:1rem">
          <label>Правила</label>
          <textarea name="rules" rows="3" style="width:100%;box-sizing:border-box"><?=h($item['rules']??'')?></textarea>
        </div>
      </div>

      <!-- Tour Fields -->
      <div id="tour-fields" style="margin-bottom:2rem;<?=$item['listing_type']!=='tour'?'display:none':''?>">
        <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1rem;margin:0 0 1rem;padding-bottom:0.75rem;border-bottom:1px solid #EEF2F6;display:flex;align-items:center;gap:0.5rem">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7A8A9A" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
          Параметры тура
        </h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem">
          <div><label>Длит. (часов)</label><input type="number" name="tour_duration_hours" value="<?=(int)($item['tour_duration_hours']??0)?>" min="0" style="width:100%;box-sizing:border-box"></div>
          <div><label>Длит. (дней)</label><input type="number" name="tour_duration_days" value="<?=(int)($item['tour_duration_days']??0)?>" min="0" style="width:100%;box-sizing:border-box"></div>
          <div><label>Группа (чел.)</label><input type="number" name="max_guests" value="<?=(int)($item['max_guests']??1)?>" min="1" style="width:100%;box-sizing:border-box"></div>
          <div>
            <label>Сложность</label>
            <select name="difficulty" style="width:100%;box-sizing:border-box">
              <?php foreach (['easy'=>'Лёгкий','medium'=>'Средний','hard'=>'Сложный','extreme'=>'Экстремальный'] as $k=>$v): ?>
                <option value="<?=$k?>" <?=($item['difficulty_level']??'')===$k?'selected':''?>><?=$v?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="margin-top:1rem"><label>Что включено</label><input type="text" name="includes" value="<?=h($item['includes']??'')?>" style="width:100%;box-sizing:border-box"></div>
        <div style="margin-top:1rem"><label>Что взять с собой</label><input type="text" name="what_to_bring" value="<?=h($item['what_to_bring']??'')?>" style="width:100%;box-sizing:border-box"></div>
        <div style="margin-top:1rem"><label>Место встречи</label><input type="text" name="meeting_point" value="<?=h($item['meeting_point']??'')?>" style="width:100%;box-sizing:border-box"></div>
      </div>

      <!-- Fishing Fields -->
      <div id="fish-fields" style="margin-bottom:2rem;<?=$item['listing_type']!=='fishing'?'display:none':''?>">
        <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1rem;margin:0 0 1rem;padding-bottom:0.75rem;border-bottom:1px solid #EEF2F6;display:flex;align-items:center;gap:0.5rem">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7A8A9A" stroke-width="2"><path d="M18 4L3 17l4 4L22 6l-4-4z"/><line x1="4" y1="20" x2="6" y2="22"/></svg>
          Параметры рыбалки
        </h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem">
          <div><label>Длит. (часов)</label><input type="number" name="tour_duration_hours" value="<?=(int)($item['tour_duration_hours']??0)?>" min="0" style="width:100%;box-sizing:border-box"></div>
          <div><label>Группа (чел.)</label><input type="number" name="max_guests" value="<?=(int)($item['max_guests']??1)?>" min="1" style="width:100%;box-sizing:border-box"></div>
          <div>
            <label>Вид ловли</label>
            <select name="fishing_method" style="width:100%;box-sizing:border-box">
              <?php foreach (['spin'=>'Спиннинг','fly'=>'Нахлыст','troll'=>'Троллинг','ice'=>'Зимняя','float'=>'Поплавочная'] as $k=>$v): ?>
                <option value="<?=$k?>" <?=($item['fishing_method']??'')===$k?'selected':''?>><?=$v?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label>Рыба</label><input type="text" name="fish_types" value="<?=h($item['fish_species']??'')?>" placeholder="Кунджа, горбуша, таймень" style="width:100%;box-sizing:border-box"></div>
          <div><label>Тип лодки</label><input type="text" name="boat_type" value="<?=h($item['boat_type']??'')?>" style="width:100%;box-sizing:border-box"></div>
          <div style="display:flex;align-items:center;padding-top:1.5rem">
            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;cursor:pointer">
              <input type="checkbox" name="license_required" value="1" <?=$item['license_required']?'checked':''?> style="width:1rem;height:1rem;accent-color:#121E2B"> Нужна лицензия
            </label>
          </div>
        </div>
        <div style="margin-top:1rem"><label>Что включено</label><input type="text" name="includes" value="<?=h($item['includes']??'')?>" style="width:100%;box-sizing:border-box"></div>
        <div style="margin-top:1rem"><label>Что взять</label><input type="text" name="what_to_bring" value="<?=h($item['what_to_bring']??'')?>" style="width:100%;box-sizing:border-box"></div>
        <div style="margin-top:1rem"><label>Место встречи</label><input type="text" name="meeting_point" value="<?=h($item['meeting_point']??'')?>" style="width:100%;box-sizing:border-box"></div>
      </div>

      <!-- Gear Fields -->
      <div id="gear-fields" style="margin-bottom:2rem;<?=$item['listing_type']!=='rental_gear'?'display:none':''?>">
        <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1rem;margin:0 0 1rem;padding-bottom:0.75rem;border-bottom:1px solid #EEF2F6;display:flex;align-items:center;gap:0.5rem">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7A8A9A" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          Параметры снаряжения
        </h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem">
          <div><label>Тип снаряжения</label><input type="text" name="gear_type" value="<?=h($item['gear_type']??'')?>" style="width:100%;box-sizing:border-box"></div>
          <div><label>Состояние</label><input type="text" name="condition" value="<?=h($item['gear_condition']??'')?>" style="width:100%;box-sizing:border-box"></div>
          <div><label>Размеры</label><input type="text" name="sizes" value="<?=h($item['sizes']??'')?>" style="width:100%;box-sizing:border-box"></div>
          <div><label>Доступно (шт.)</label><input type="number" name="max_guests" value="<?=(int)($item['max_guests']??1)?>" min="1" style="width:100%;box-sizing:border-box"></div>
          <div><label>Залог (₽)</label><input type="number" name="deposit" value="<?=(int)($item['deposit_amount']??0)?>" min="0" style="width:100%;box-sizing:border-box"></div>
        </div>
        <div style="margin-top:1rem"><label>Что включено</label><input type="text" name="includes" value="<?=h($item['includes']??'')?>" style="width:100%;box-sizing:border-box"></div>
      </div>

      <!-- Car Fields -->
      <div id="car-fields" style="margin-bottom:2rem;<?=$item['listing_type']!=='car_rental'?'display:none':''?>">
        <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1rem;margin:0 0 1rem;padding-bottom:0.75rem;border-bottom:1px solid #EEF2F6;display:flex;align-items:center;gap:0.5rem">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7A8A9A" stroke-width="2"><path d="M5 17h14M5 17l-.6-1.5A2 2 0 0 1 4 13V9a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v4a2 2 0 0 1-.4 2.5L19 17"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="16.5" cy="17.5" r="2.5"/></svg>
          Параметры авто
        </h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem">
          <div><label>Марка/модель</label><input type="text" name="car_type" value="<?=h($item['car_type']??'')?>" style="width:100%;box-sizing:border-box"></div>
          <div>
            <label>Коробка</label>
            <select name="transmission" style="width:100%;box-sizing:border-box">
              <?php foreach (['auto'=>'Автомат','manual'=>'Механика'] as $k=>$v): ?>
                <option value="<?=$k?>" <?=($item['transmission']??'')===$k?'selected':''?>><?=$v?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label>Мест</label><input type="number" name="seats" value="<?=(int)($item['seats']??5)?>" min="1" style="width:100%;box-sizing:border-box"></div>
          <div><label>Топливо</label><input type="text" name="fuel" value="<?=h($item['fuel']??'')?>" style="width:100%;box-sizing:border-box"></div>
          <div><label>Пробег (км/день)</label><input type="number" name="mileage" value="<?=(int)($item['mileage']??0)?>" min="0" style="width:100%;box-sizing:border-box"></div>
          <div><label>Залог (₽)</label><input type="number" name="deposit" value="<?=(int)($item['deposit_amount']??0)?>" min="0" style="width:100%;box-sizing:border-box"></div>
        </div>
        <div style="margin-top:1rem"><label>Требования</label><input type="text" name="requirements" value="<?=h($item['requirements']??'')?>" style="width:100%;box-sizing:border-box"></div>
        <div style="margin-top:1rem"><label>Что включено</label><input type="text" name="includes" value="<?=h($item['includes']??'')?>" style="width:100%;box-sizing:border-box"></div>
      </div>

      <!-- Photos -->
      <div style="margin-bottom:2rem">
        <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1rem;margin:0 0 1rem;padding-bottom:0.75rem;border-bottom:1px solid #EEF2F6;display:flex;align-items:center;gap:0.5rem">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7A8A9A" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          Фотографии
        </h2>
        <?php
        $imgs = $pdo->prepare("SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id");
        $imgs->execute([$listing_id]);
        $images = $imgs->fetchAll();
        ?>
        <div id="photoGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:0.625rem">
          <?php foreach ($images as $img): ?>
            <div data-img-id="<?=$img['id']?>" style="position:relative;border-radius:8px;overflow:hidden;border:1px solid #EEF2F6;background:rgba(238,242,246,0.3)">
              <img src="<?=UPLOAD_URL . $img['filename']?>" style="width:100%;height:88px;object-fit:cover;display:block" alt="">
              <?php if ($img['sort_order'] === 0): ?>
                <span style="position:absolute;top:4px;left:4px;background:#121E2B;color:#F7F9FB;font-size:0.625rem;padding:0.15rem 0.5rem;border-radius:4px;font-weight:600;letter-spacing:0.03em">Обложка</span>
              <?php else: ?>
                <button type="button" onclick="setCover(<?=$img['id']?>, this)" style="position:absolute;top:4px;left:4px;background:rgba(0,0,0,0.55);color:#fff;border:0;font-size:0.625rem;padding:0.15rem 0.5rem;border-radius:4px;font-weight:500;cursor:pointer;opacity:0;transition:opacity 0.15s" onmouseover="this.style.opacity='1';this.style.background='#1B6B8A'" onmouseout="this.style.opacity='0';this.style.background='rgba(0,0,0,0.55)'">Обложка</button>
              <?php endif; ?>
              <button type="button" onclick="deleteImage(<?=$img['id']?>, this)" style="position:absolute;top:4px;right:4px;width:1.25rem;height:1.25rem;background:#DC2626;color:#fff;border:0;font-size:0.625rem;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;opacity:0;transition:opacity 0.15s" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0'">&times;</button>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.875rem">
          <label style="display:inline-flex;align-items:center;gap:0.375rem;border-radius:8px;border:1px solid #DFE4EA;padding:0.5rem 1rem;font-size:0.8125rem;font-weight:500;cursor:pointer;transition:all 0.15s;background:#fff;color:#3A4A5C" onmouseover="this.style.background='#F7F9FB';this.style.borderColor='#C8D0DA'" onmouseout="this.style.background='#fff';this.style.borderColor='#DFE4EA'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Добавить фото
            <input type="file" id="photoInput" accept="image/*" hidden onchange="uploadImage()">
          </label>
          <span id="uploadStatus" style="font-size:0.75rem;color:#7A8A9A;display:none"></span>
        </div>
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:0.75rem;padding-top:1.25rem;border-top:1px solid #EEF2F6">
        <button type="submit" class="cta-btn" style="gap:0.375rem">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          Сохранить
        </button>
        <a href="/dashboard" class="btn-outline">Отмена</a>
      </div>
    </form>
  </div>
</section>

<script>
function priceTypeChange(){
  var sel = document.getElementById('price_type');
  var inp = document.getElementById('price_input');
  if (sel && inp) {
    if (sel.value === 'negotiable') { inp.disabled = true; inp.required = false; inp.value = ''; }
    else { inp.disabled = false; inp.required = true; }
  }
}
document.addEventListener('DOMContentLoaded', priceTypeChange);
document.getElementById('listing_type').addEventListener('change', function() {
  ['prop','tour','fish','gear','car'].forEach(function(p){
    var el = document.getElementById(p + '-fields');
    if (el) el.style.display = 'none';
  });
  var id = this.value === 'property' ? 'prop' : this.value === 'tour' ? 'tour' : this.value === 'fishing' ? 'fish' : this.value === 'rental_gear' ? 'gear' : 'car';
  var block = document.getElementById(id + '-fields');
  if (block) block.style.display = '';
});

var csrfToken = document.querySelector('input[name="_csrf"]')?.value || '';

function uploadImage() {
  var file = document.getElementById('photoInput').files[0];
  if (!file) return;
  var fd = new FormData();
  fd.append('image', file);
  fd.append('_csrf', csrfToken);
  var status = document.getElementById('uploadStatus');
  status.style.display = 'block';
  status.style.color = '#7A8A9A';
  status.textContent = 'Загрузка...';
  fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
    if (data.ok) {
      var grid = document.getElementById('photoGrid');
      var card = document.createElement('div');
      card.setAttribute('data-img-id', data.id);
      card.style.cssText = 'position:relative;border-radius:8px;overflow:hidden;border:1px solid #EEF2F6;background:rgba(238,242,246,0.3)';
      card.innerHTML = '<img src="'+data.url+'" style="width:100%;height:88px;object-fit:cover;display:block" alt="">' +
        '<button type="button" onclick="setCover('+data.id+', this)" style="position:absolute;top:4px;left:4px;background:rgba(0,0,0,0.55);color:#fff;border:0;font-size:0.625rem;padding:0.15rem 0.5rem;border-radius:4px;font-weight:500;cursor:pointer;opacity:0;transition:opacity 0.15s" onmouseover="this.style.opacity=\'1\';this.style.background=\'#1B6B8A\'" onmouseout="this.style.opacity=\'0\';this.style.background=\'rgba(0,0,0,0.55)\'">Обложка</button>' +
        '<button type="button" onclick="deleteImage('+data.id+', this)" style="position:absolute;top:4px;right:4px;width:1.25rem;height:1.25rem;background:#DC2626;color:#fff;border:0;font-size:0.625rem;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;opacity:0;transition:opacity 0.15s" onmouseover="this.style.opacity=\'1\'" onmouseout="this.style.opacity=\'0\'">&times;</button>';
      grid.appendChild(card);
      status.textContent = 'Готово';
      status.style.color = '#16A34A';
    } else {
      status.textContent = (data.error || 'Ошибка');
      status.style.color = '#DC2626';
    }
    document.getElementById('photoInput').value = '';
    setTimeout(function(){ status.style.display = 'none'; }, 2500);
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
    if (data.ok) { location.reload(); }
    else { alert('Ошибка'); }
  });
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
