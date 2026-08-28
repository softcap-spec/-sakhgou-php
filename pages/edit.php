<?php
// edit.php — v3 clean design with photo manager
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Cache-Control: no-store, must-revalidate');
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

<style>
  .ed-wrap{max-width:1080px;margin:0 auto;padding:1.5rem 1rem 5rem}
  .ed-back{display:inline-flex;align-items:center;gap:0.375rem;color:#7A8A9A;text-decoration:none;font-size:0.8125rem;margin-bottom:0.875rem}
  .ed-back:hover{color:#1B6B8A}
  .ed-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem}
  .ed-title{font-family:Manrope,sans-serif;font-weight:800;font-size:1.4rem;letter-spacing:-0.02em;margin:0;color:#121E2B;line-height:1.3}
  .ed-title .ed-id{color:#9AAAB8;font-weight:600;font-size:0.95rem;white-space:nowrap}
  .ed-sub{color:#7A8A9A;font-size:0.8125rem;margin:0.25rem 0 0}
  .ed-head-actions{display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap}
  .ed-btn-view{display:inline-flex;align-items:center;gap:0.375rem;padding:0.5625rem 1rem;border:1px solid #DFE4EA;border-radius:10px;background:#fff;color:#121E2B;font-size:0.8125rem;font-weight:600;text-decoration:none;transition:all 0.15s}
  .ed-btn-view:hover{border-color:#C8D0DA;background:#F7F9FB}
  .ed-btn-save{display:inline-flex;align-items:center;gap:0.375rem;padding:0.5625rem 1.125rem;border:0;border-radius:10px;background:#1B6B8A;color:#fff;font-size:0.8125rem;font-weight:700;cursor:pointer;transition:all 0.15s}
  .ed-btn-save:hover{background:#155a75}
  .ed-grid{display:grid;grid-template-columns:1fr;gap:1.25rem;align-items:start}
  @media(min-width:1024px){.ed-grid{grid-template-columns:minmax(0,1fr) 320px}}
  .ed-card{background:#fff;border:1px solid #EEF2F6;border-radius:16px;padding:1.5rem;box-shadow:0 4px 14px rgba(15,23,32,0.05);margin-bottom:1.25rem}
  .ed-card:last-child{margin-bottom:0}
  .ed-sect{display:flex;align-items:center;gap:0.625rem;margin-bottom:1.25rem}
  .ed-sect .ic{width:2.25rem;height:2.25rem;border-radius:10px;background:#F0F6FA;display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .ed-sect b{font-family:Manrope,sans-serif;font-size:1rem;color:#121E2B;display:block;line-height:1.2}
  .ed-sect small{color:#7A8A9A;font-size:0.75rem;display:block}
  .ed-label{display:block;font-size:0.8125rem;font-weight:600;color:#54677A;margin:0 0 0.375rem}
  .ed-input,.ed-select,.ed-textarea{width:100%;box-sizing:border-box;padding:0.6875rem 0.875rem;border:1px solid #DFE4EA;border-radius:10px;font-size:0.9375rem;font-family:inherit;color:#121E2B;background:#fff;transition:border-color 0.15s, box-shadow 0.15s;outline:none}
  .ed-input:focus,.ed-select:focus,.ed-textarea:focus{border-color:#1B6B8A;box-shadow:0 0 0 3px rgba(27,107,138,0.12)}
  .ed-textarea{resize:vertical;line-height:1.55}
  .ed-row2{display:grid;grid-template-columns:1fr 1fr;gap:0.875rem}
  @media(max-width:560px){.ed-row2{grid-template-columns:1fr}}
  .ed-grid3{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:0.875rem}
  .ed-grid-a{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:0.375rem}
  .ed-side{position:sticky;top:1rem}
  .ed-side .ed-card{padding:1.25rem}
  .ed-side-row{margin-bottom:0.875rem}
  .ed-flash{border-radius:10px;padding:0.75rem 1rem;margin-bottom:1.25rem;font-size:0.8125rem}
  .ed-flash.ok{background:#F0FDF4;border:1px solid #BBF7D0;color:#166534}
  .ed-flash.ok a{color:#166534;font-weight:700}
  .ed-flash.err{background:#FEF2F2;border:1px solid #FECACA;color:#991B1B}
  .ed-savebar{position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid #EEF2F6;padding:0.75rem 1rem calc(0.75rem + env(safe-area-inset-bottom));display:flex;gap:0.75rem;z-index:80;box-shadow:0 -6px 20px rgba(15,23,32,0.08)}
  @media(min-width:1024px){.ed-savebar{display:none}}
  .ed-btn-primary{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:0.375rem;padding:0.75rem 1.25rem;border:0;border-radius:10px;background:#1B6B8A;color:#fff;font-size:0.875rem;font-weight:700;cursor:pointer}
  .ed-check{display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;cursor:pointer;padding:0.4375rem 0.5625rem;border-radius:8px;border:1px solid transparent}
  .ed-check:hover{background:#F7F9FB;border-color:#EEF2F6}
  .ed-check input{width:1rem;height:1rem;accent-color:#1B6B8A}
  .ed-hint{font-size:0.75rem;color:#9AAAB8;margin:0.375rem 0 0}
  .ed-block{margin-top:1rem}
  .ed-block:first-child{margin-top:0}
</style>
<section>
  <div class="ed-wrap">
    <a class="ed-back" href="/dashboard"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg> Кабинет</a>
    <div class="ed-head">
      <div>
        <h1 class="ed-title"><?= h($item['title']) ?> <span class="ed-id">№<?= (int)$item['id'] ?></span></h1>
        <p class="ed-sub">Изменения появятся на сайте после сохранения</p>
      </div>
      <div class="ed-head-actions">
        <a class="ed-btn-view" href="/listing/<?= (int)$item['id'] ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Просмотр</a>
        <button type="submit" form="editForm" class="ed-btn-save"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Сохранить</button>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="ed-flash ok">Объявление обновлено! <a href="/listing/<?= (int)$item['id'] ?>">Посмотреть</a></div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
    <div class="ed-flash err"><?= h($e) ?></div>
    <?php endforeach; ?>

    <form id="editForm" method="post">
      <?= csrf_field() ?>
      <div class="ed-grid">

        <!-- ЛЕВАЯ КОЛОНКА -->
        <div>
          <div class="ed-card">
            <div class="ed-sect">
              <div class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1B6B8A" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
              <div><b>Основное</b><small>Название, цена и описание</small></div>
            </div>
            <label class="ed-label">Название</label>
            <input type="text" name="title" value="<?= h($item['title']) ?>" required class="ed-input" style="margin-bottom:1rem">
            <label class="ed-label">Цена</label>
            <div class="ed-row2" style="margin-bottom:1rem">
              <div>
                <select name="price_type" id="price_type" onchange="priceTypeChange()" class="ed-select">
                  <option value="fixed" <?=($item['price_type']??'fixed')==='fixed'?'selected':''?>>Точная цена</option>
                  <option value="from" <?=($item['price_type']??'')==='from'?'selected':''?>>От (цена от …)</option>
                  <option value="negotiable" <?=($item['price_type']??'')==='negotiable'?'selected':''?>>По договорённости</option>
                </select>
              </div>
              <div><input type="number" name="price" id="price_input" value="<?= (int)$item['price'] ?>" min="0" step="1" required class="ed-input" placeholder="₽"></div>
            </div>
            <label class="ed-label">Описание</label>
            <textarea name="description" rows="8" required class="ed-textarea"><?= h($item['description'] ?? '') ?></textarea>
            <p class="ed-hint">Расскажите подробно: что входит, особенности места, условия.</p>
          </div>

          <div class="ed-card">
            <div class="ed-sect">
              <div class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1B6B8A" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg></div>
              <div><b>Параметры</b><small>Зависят от типа объявления</small></div>
            </div>

            <div id="prop-fields" class="ed-block" style="<?=$item['listing_type']!=='property'?'display:none':''?>">
              <p class="ed-label" style="color:#1B6B8A;margin-bottom:0.625rem">Жильё</p>
              <div class="ed-grid3">
                <div><label class="ed-label">Гостей</label><input type="number" name="max_guests" value="<?=(int)($item['max_guests']??1)?>" min="1" class="ed-input"></div>
                <div><label class="ed-label">Комнат</label><input type="number" name="rooms_count" value="<?=(int)($item['rooms_count']??1)?>" min="0" class="ed-input"></div>
                <div><label class="ed-label">Кроватей</label><input type="number" name="beds_count" value="<?=(int)($item['beds_count']??1)?>" min="0" class="ed-input"></div>
                <div><label class="ed-label">Заезд</label><input type="text" name="check_in_time" value="<?=h($item['check_in_time']??'14:00')?>" class="ed-input"></div>
                <div><label class="ed-label">Выезд</label><input type="text" name="check_out_time" value="<?=h($item['check_out_time']??'12:00')?>" class="ed-input"></div>
              </div>
              <div class="ed-block">
                <label class="ed-label">Удобства</label>
                <?php $am = json_decode($item['amenities']??'[]',true)?:[]; ?>
                <div class="ed-grid-a">
                  <?php foreach (['Wi-Fi','Кухня','Парковка','Стиральная машина','Кондиционер','Телевизор','Балкон','Отопление','Фен','Утюг','Посуда','Полотенца'] as $a): ?>
                    <label class="ed-check"><input type="checkbox" name="amenities[]" value="<?=$a?>" <?=in_array($a,$am)?'checked':''?>> <?=$a?></label>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="ed-block">
                <label class="ed-label">Правила</label>
                <textarea name="rules" rows="3" class="ed-textarea"><?=h($item['rules']??'')?></textarea>
              </div>
            </div>

            <div id="tour-fields" class="ed-block" style="<?=$item['listing_type']!=='tour'?'display:none':''?>">
              <p class="ed-label" style="color:#1B6B8A;margin-bottom:0.625rem">Тур</p>
              <div class="ed-grid3">
                <div><label class="ed-label">Длит. (часов)</label><input type="number" name="tour_duration_hours" value="<?=(int)($item['tour_duration_hours']??0)?>" min="0" class="ed-input"></div>
                <div><label class="ed-label">Длит. (дней)</label><input type="number" name="tour_duration_days" value="<?=(int)($item['tour_duration_days']??0)?>" min="0" class="ed-input"></div>
                <div><label class="ed-label">Группа (чел.)</label><input type="number" name="max_guests" value="<?=(int)($item['max_guests']??1)?>" min="1" class="ed-input"></div>
                <div>
                  <label class="ed-label">Сложность</label>
                  <select name="difficulty" class="ed-select">
                    <?php foreach (['easy'=>'Лёгкий','medium'=>'Средний','hard'=>'Сложный','extreme'=>'Экстремальный'] as $k=>$v): ?>
                      <option value="<?=$k?>" <?=($item['difficulty_level']??'')===$k?'selected':''?>><?=$v?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="ed-block"><label class="ed-label">Что включено</label><input type="text" name="includes" value="<?=h($item['includes']??'')?>" class="ed-input"></div>
              <div class="ed-block"><label class="ed-label">Что взять с собой</label><input type="text" name="what_to_bring" value="<?=h($item['what_to_bring']??'')?>" class="ed-input"></div>
              <div class="ed-block"><label class="ed-label">Место встречи</label><input type="text" name="meeting_point" value="<?=h($item['meeting_point']??'')?>" class="ed-input"></div>
            </div>

            <div id="fish-fields" class="ed-block" style="<?=$item['listing_type']!=='fishing'?'display:none':''?>">
              <p class="ed-label" style="color:#1B6B8A;margin-bottom:0.625rem">Рыбалка</p>
              <div class="ed-grid3">
                <div><label class="ed-label">Длит. (часов)</label><input type="number" name="tour_duration_hours" value="<?=(int)($item['tour_duration_hours']??0)?>" min="0" class="ed-input"></div>
                <div><label class="ed-label">Группа (чел.)</label><input type="number" name="max_guests" value="<?=(int)($item['max_guests']??1)?>" min="1" class="ed-input"></div>
                <div>
                  <label class="ed-label">Вид ловли</label>
                  <select name="fishing_method" class="ed-select">
                    <?php foreach (['spin'=>'Спиннинг','fly'=>'Нахлыст','troll'=>'Троллинг','ice'=>'Зимняя','float'=>'Поплавочная'] as $k=>$v): ?>
                      <option value="<?=$k?>" <?=($item['fishing_method']??'')===$k?'selected':''?>><?=$v?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div><label class="ed-label">Рыба</label><input type="text" name="fish_types" value="<?=h($item['fish_species']??'')?>" placeholder="Кунджа, горбуша, таймень" class="ed-input"></div>
                <div><label class="ed-label">Тип лодки</label><input type="text" name="boat_type" value="<?=h($item['boat_type']??'')?>" class="ed-input"></div>
                <div style="display:flex;align-items:center;padding-top:1.375rem">
                  <label class="ed-check"><input type="checkbox" name="license_required" value="1" <?=$item['license_required']?'checked':''?>> Нужна лицензия</label>
                </div>
              </div>
              <div class="ed-block"><label class="ed-label">Что включено</label><input type="text" name="includes" value="<?=h($item['includes']??'')?>" class="ed-input"></div>
              <div class="ed-block"><label class="ed-label">Что взять</label><input type="text" name="what_to_bring" value="<?=h($item['what_to_bring']??'')?>" class="ed-input"></div>
              <div class="ed-block"><label class="ed-label">Место встречи</label><input type="text" name="meeting_point" value="<?=h($item['meeting_point']??'')?>" class="ed-input"></div>
            </div>

            <div id="gear-fields" class="ed-block" style="<?=$item['listing_type']!=='rental_gear'?'display:none':''?>">
              <p class="ed-label" style="color:#1B6B8A;margin-bottom:0.625rem">Снаряжение</p>
              <div class="ed-grid3">
                <div><label class="ed-label">Тип</label><input type="text" name="gear_type" value="<?=h($item['gear_type']??'')?>" class="ed-input"></div>
                <div><label class="ed-label">Состояние</label><input type="text" name="condition" value="<?=h($item['gear_condition']??'')?>" class="ed-input"></div>
                <div><label class="ed-label">Размеры</label><input type="text" name="sizes" value="<?=h($item['sizes']??'')?>" class="ed-input"></div>
                <div><label class="ed-label">Доступно (шт.)</label><input type="number" name="max_guests" value="<?=(int)($item['max_guests']??1)?>" min="1" class="ed-input"></div>
                <div><label class="ed-label">Залог (₽)</label><input type="number" name="deposit" value="<?=(int)($item['deposit_amount']??0)?>" min="0" class="ed-input"></div>
              </div>
              <div class="ed-block"><label class="ed-label">Что включено</label><input type="text" name="includes" value="<?=h($item['includes']??'')?>" class="ed-input"></div>
            </div>

            <div id="car-fields" class="ed-block" style="<?=$item['listing_type']!=='car_rental'?'display:none':''?>">
              <p class="ed-label" style="color:#1B6B8A;margin-bottom:0.625rem">Авто</p>
              <div class="ed-grid3">
                <div><label class="ed-label">Марка/модель</label><input type="text" name="car_type" value="<?=h($item['car_type']??'')?>" class="ed-input"></div>
                <div>
                  <label class="ed-label">Коробка</label>
                  <select name="transmission" class="ed-select">
                    <?php foreach (['auto'=>'Автомат','manual'=>'Механика'] as $k=>$v): ?>
                      <option value="<?=$k?>" <?=($item['transmission']??'')===$k?'selected':''?>><?=$v?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div><label class="ed-label">Мест</label><input type="number" name="seats" value="<?=(int)($item['seats']??5)?>" min="1" class="ed-input"></div>
                <div><label class="ed-label">Топливо</label><input type="text" name="fuel" value="<?=h($item['fuel']??'')?>" class="ed-input"></div>
                <div><label class="ed-label">Пробег (км/день)</label><input type="number" name="mileage" value="<?=(int)($item['mileage']??0)?>" min="0" class="ed-input"></div>
                <div><label class="ed-label">Залог (₽)</label><input type="number" name="deposit" value="<?=(int)($item['deposit_amount']??0)?>" min="0" class="ed-input"></div>
              </div>
              <div class="ed-block"><label class="ed-label">Требования</label><input type="text" name="requirements" value="<?=h($item['requirements']??'')?>" class="ed-input"></div>
              <div class="ed-block"><label class="ed-label">Что включено</label><input type="text" name="includes" value="<?=h($item['includes']??'')?>" class="ed-input"></div>
            </div>
          </div>

          <div class="ed-card">
            <div class="ed-sect">
              <div class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1B6B8A" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
              <div><b>Фотографии</b><small>Первая — обложка объявления</small></div>
            </div>
            <?php
            $imgs = $pdo->prepare("SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id");
            $imgs->execute([$listing_id]);
            $images = $imgs->fetchAll();
            ?>
            <div id="photoGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:0.625rem">
              <?php foreach ($images as $img): ?>
                <div data-img-id="<?=$img['id']?>" style="position:relative;border-radius:10px;overflow:hidden;border:1px solid #EEF2F6;background:rgba(238,242,246,0.3)">
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
              <label style="display:inline-flex;align-items:center;gap:0.375rem;border-radius:10px;border:1px dashed #C8D0DA;padding:0.625rem 1.125rem;font-size:0.8125rem;font-weight:600;cursor:pointer;transition:all 0.15s;background:#F7F9FB;color:#3A4A5C" onmouseover="this.style.background='#EEF4F8';this.style.borderColor='#1B6B8A'" onmouseout="this.style.background='#F7F9FB';this.style.borderColor='#C8D0DA'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                Добавить фото
                <input type="file" id="photoInput" accept="image/*" hidden onchange="uploadImage()">
              </label>
              <span id="uploadStatus" style="font-size:0.75rem;color:#7A8A9A;display:none"></span>
            </div>
          </div>
        </div>

        <!-- САЙДБАР -->
        <div class="ed-side">
          <div class="ed-card">
            <div class="ed-sect" style="margin-bottom:1rem">
              <div class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1B6B8A" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
              <div><b>Размещение</b><small>Тип и категория</small></div>
            </div>
            <div class="ed-side-row">
              <label class="ed-label">Тип</label>
              <select name="listing_type" id="listing_type" onchange="edTypeChange()" class="ed-select">
                <?php foreach (['property'=>'Жильё','tour'=>'Туры','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто'] as $k=>$v): ?>
                  <option value="<?=$k?>" <?=$item['listing_type']===$k?'selected':''?>><?=$v?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="ed-side-row">
              <label class="ed-label">Категория</label>
              <select name="category_id" class="ed-select">
                <?php
                $cats = $pdo->query("SELECT id, name, slug FROM categories ORDER BY id")->fetchAll();
                foreach ($cats as $c): ?>
                  <option value="<?=$c['id']?>" <?=$item['category_id']==$c['id']?'selected':''?>><?=h($c['name'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="ed-side-row">
              <label class="ed-label">Локация</label>
              <input type="text" name="location" value="<?= h($item['location'] ?? '') ?>" class="ed-input" placeholder="Южно-Сахалинск">
            </div>
          </div>
          <div class="ed-card">
            <button type="submit" class="ed-btn-save" style="width:100%;justify-content:center;padding:0.75rem;font-size:0.875rem"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Сохранить изменения</button>
            <a class="ed-btn-view" href="/listing/<?= (int)$item['id'] ?>" style="width:100%;justify-content:center;margin-top:0.625rem">Просмотреть объявление</a>
            <a class="ed-btn-view" href="/dashboard" style="width:100%;justify-content:center;margin-top:0.625rem;border:0;background:none;color:#7A8A9A">Отмена</a>
          </div>
        </div>

      </div>
    </form>

    <div class="ed-savebar">
      <button type="submit" form="editForm" class="ed-btn-primary"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Сохранить изменения</button>
      <a class="ed-btn-view" href="/listing/<?= (int)$item['id'] ?>" style="align-self:center">Просмотр</a>
    </div>
  </div>
</section>

<script>
function edTypeChange(){
  var map = {property:'prop', tour:'tour', fishing:'fish', rental_gear:'gear', car_rental:'car'};
  var v = document.getElementById('listing_type').value;
  Object.keys(map).forEach(function(k){
    var el = document.getElementById(map[k] + '-fields');
    if (el) el.style.display = (map[k] === map[v]) ? '' : 'none';
  });
}
</script>
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
