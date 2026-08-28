<?php
// create.php v3 — clean design wizard
$cu = auth_required();
$pdo = db();

$LISTING_LABELS = ['property'=>'Жильё','tour'=>'Тур','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто'];

$LISTING_ICONS = [
  'property'    => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
  'tour'        => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>',
  'fishing'     => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 4L3 17l4 4L22 6l-4-4z"/><line x1="4" y1="20" x2="6" y2="22"/></svg>',
  'rental_gear' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
  'car_rental'  => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 17h14M5 17l-.6-1.5A2 2 0 0 1 4 13V9a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v4a2 2 0 0 1-.4 2.5L19 17"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="16.5" cy="17.5" r="2.5"/></svg>',
];

$CATEGORY_OPTIONS = [
  'property'=>[['kvartiry','Квартира'],['doma-u-morya','Дом у моря'],['bazy-otdyha','База отдыха'],['gostevye-doma','Гостевой дом']],
  'tour'=>[['dzhip-tury','Джип-тур'],['morskie-vyhody','Морской выход'],['kurily','Тур на Курилы'],['firraid','Фрирайд / Ски-тур']],
  'fishing'=>[['rybalka-rechnaya','Речная'],['rybalka-morskaya','Морская'],['rybalka-podlednaya','Подлёдная'],['rybalka-splav','Сплав']],
  'rental_gear'=>[['avto-moto','Авто/Мото'],['vodny-sport','Водный спорт'],['turisticheskoe','Туристическое'],['zimnee','Зимнее']],
  'car_rental'=>[['vnedorozhniki','Внедорожники'],['legkovye','Легковые'],['mikroavtobusy','Микроавтобусы'],['mototexnika','Мототехника']],
];

$AMENITY_OPTIONS = ['Wi-Fi','Парковка','Вид на горы','Вид на море','Сушилка для снаряжения','Камин','Баня/Сауна','Кондиционер','Стиральная машина','Кухня','Балкон/Терраса','Мангал','Трансфер','Можно с животными'];
$FISH_SPECIES = ['Горбуша','Кета','Сима','Кунджа','Таймень','Камбала','Палтус','Треска','Корюшка'];
$INCLUDES = ['Трансфер','Обед','Снаряжение','Страховка','Услуги гида','Фотосъёмка','Проживание'];
$LOCATIONS = ['Южно-Сахалинск','Корсаков','Холмск','Невельск','Курильск','Северо-Курильск','Южно-Курильск','Оха','Ноглики','Анива'];

$step = (int)($_POST['step'] ?? ($_GET['step'] ?? 1));
$errors = [];
$success = false;

// Save uploaded images when arriving at step 5 from step 4
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 5 && !empty($_FILES['images']['name'][0])) {
  $_SESSION['tmp_images'] = [];
  $tmpDir = UPLOAD_DIR . '/.tmp';
  if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
  foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_INI_SIZE || $_FILES['images']['error'][$i] === UPLOAD_ERR_FORM_SIZE) {
      $errors[] = 'Файл «' . h($_FILES['images']['name'][$i]) . '» превышает максимальный размер (2 МБ)';
      continue;
    }
    if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
    if ($_FILES['images']['size'][$i] > MAX_UPLOAD_SIZE) {
      $errors[] = 'Файл «' . h($_FILES['images']['name'][$i]) . '» слишком большой (макс. 2 МБ)';
      continue;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = @$finfo->file($tmp);
    if (!$mime || !in_array($mime, $allowed)) continue;
    $ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext = $ext_map[$mime];
    $fn = 'tmp_' . $cu['id'] . '_' . time() . '_' . $i . '.' . $ext;
    move_uploaded_file($tmp, $tmpDir . '/' . $fn);
    $_SESSION['tmp_images'][] = ['file' => $fn, 'orig' => $_FILES['images']['name'][$i]];
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finish'])) {
  csrf_check();
  $lt = $_POST['listing_type'] ?? '';
  $cat = $_POST['category'] ?? '';
  $title = trim($_POST['title'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  $priceType = in_array($_POST['price_type'] ?? 'fixed', ['fixed','from','negotiable'], true) ? $_POST['price_type'] : 'fixed';
  $price = (float)($_POST['price'] ?? 0);
  if ($priceType === 'negotiable') $price = 0;
  $loc = $_POST['location'] ?? '';
  $guests = (int)($_POST['max_guests'] ?? 0);
  $tourOrgType = $_POST['tour_organizer_type'] ?? '';
  $tourOpName = trim($_POST['tour_operator_name'] ?? '');
  $tourOpRegno = trim($_POST['tour_operator_regno'] ?? '');

  if (empty($title)) $errors[] = ['field'=>'title', 'text'=>'Введите название'];
  if ($priceType !== 'negotiable' && $price <= 0) $errors[] = ['field'=>'price', 'text'=>'Укажите цену'];
  if (empty($desc)) $errors[] = ['field'=>'description', 'text'=>'Добавьте описание'];
  if ($loc === '__custom__') $loc = trim($_POST['location_custom'] ?? '');
  if (empty($loc)) $errors[] = ['field'=>'location', 'text'=>'Укажите локацию'];
  $_POST['location'] = $loc;
  if (empty($lt) || !isset($LISTING_LABELS[$lt])) $errors[] = ['field'=>'listing_type', 'text'=>'Выберите тип объявления'];
  if ($lt === 'tour' && empty($tourOrgType)) $errors[] = ['field'=>'tour_organizer_type', 'text'=>'Укажите статус организатора (туроператор / турагент / частное лицо)'];

  if (empty($errors)) {
    try {
    $catStmt = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
    $catStmt->execute([$lt]);
    $catRow = $catStmt->fetch();
    $real_cid = $catRow ? $catRow['id'] : 1;

    $baseSlug = transliterate($title);
    $slug = $baseSlug;
    $n = 1;
    while ($pdo->query("SELECT 1 FROM listings WHERE slug = ".$pdo->quote($slug))->fetch()) {
      $slug = $baseSlug . '-' . ($n++);
    }

    $stmt = $pdo->prepare("INSERT INTO listings (user_id,category_id,listing_type,subcategory,tour_organizer_type,tour_operator_name,tour_operator_regno,title,slug,description,price,price_type,currency,max_guests,location,
      rooms_count,beds_count,bathrooms_count,area_sqm,amenities,check_in_time,check_out_time,deposit_amount,
      tour_duration_hours,tour_duration_days,difficulty_level,group_size_min,group_size_max,start_point,
      requires_border_permit,depends_on_weather,transport_included,transport_type,
      gear_condition,fishing_type,fishing_method,gear_included,catch_guarantee,license_required,boat_included,
      meals_included,season,cancellation_policy,status)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
      $cu['id'], $real_cid, $lt, $cat, $tourOrgType, $tourOpName, $tourOpRegno, $title, $slug, $desc, $price, $priceType, 'RUB',
      $guests, $loc,
      (int)($_POST['rooms_count']??0), (int)($_POST['beds_count']??0), (int)($_POST['bathrooms_count']??1), (int)($_POST['area_sqm']??0),
      json_encode($_POST['amenities']??[], JSON_UNESCAPED_UNICODE),
      $_POST['check_in']??'', $_POST['check_out']??'', (int)($_POST['deposit']??0),
      (int)($_POST['tour_hours']??0), (int)($_POST['tour_days']??0), $_POST['difficulty']??'',
      (int)($_POST['group_min']??0), (int)($_POST['group_max']??0), $_POST['start_point']??'',
      (int)($_POST['border_permit']??0), (int)($_POST['weather_dep']??0), (int)($_POST['transport_inc']??0), $_POST['transport_type']??'',
      $_POST['gear_condition']??'', $_POST['fishing_type']??'', $_POST['fishing_method']??'',
      (int)($_POST['gear_inc']??0), (int)($_POST['catch_g']??0), (int)($_POST['license']??0), (int)($_POST['boat']??0),
      (int)($_POST['meals']??0), $_POST['season']??'', $_POST['cancellation']??'', 'pending'
    ]);
    $lid = $pdo->lastInsertId();
    $success = $lid;
    // Notify admin
    $pdo->prepare("INSERT INTO notifications (user_id, type, text, link) VALUES (0, 'new_listing', ?, ?)")
      ->execute(['Новое объявление: ' . $title, '/admin?tab=moderation']);
    } catch (PDOException $e) {
      $errors[] = 'Ошибка БД: ' . $e->getMessage();
    }

    $tmpDir = UPLOAD_DIR . '/.tmp';
    if ($success && !empty($_SESSION['tmp_images'])) {
      $pdo->beginTransaction();
      foreach ($_SESSION['tmp_images'] as $i => $img) {
        // S3 fix: use MIME-based extension, not original filename
        $ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $srcPath = $tmpDir . '/' . $img['file'];
        $mime = @$finfo->file($srcPath);
        $ext = $ext_map[$mime] ?? 'jpg';
        $fn = $lid . '_' . ($i+1) . '_' . time() . '.' . $ext;
        $src = $tmpDir . '/' . $img['file'];
        $dst = UPLOAD_DIR . '/' . $fn;
        if (file_exists($src)) {
          rename($src, $dst);
          $pdo->prepare('INSERT INTO listing_images (listing_id, filename, sort_order) VALUES (?,?,?)')->execute([$lid, $fn, $i]);
        }
      }
      $pdo->commit();
      unset($_SESSION['tmp_images']);
    }
  }
}

$page_title = 'Подать объявление — СахGO';
require __DIR__ . '/../includes/header.php';
?>

<style>
  .form-group{margin-bottom:1.125rem}
  .form-group label{display:block;font-size:0.8125rem;font-weight:600;color:#54677A;margin:0 0 0.375rem}
  .form-group input,.form-group select,.form-group textarea{padding:0.6875rem 0.875rem;border:1px solid #DFE4EA;border-radius:10px;font-size:0.9375rem;font-family:inherit;color:#121E2B;background:#fff;transition:border-color 0.15s, box-shadow 0.15s;outline:none}
  .form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#1B6B8A;box-shadow:0 0 0 3px rgba(27,107,138,0.12)}
  .form-group textarea{resize:vertical;line-height:1.55}
  form[enctype]{border-radius:16px !important;padding:2rem !important;box-shadow:0 4px 14px rgba(15,23,32,0.05) !important}
</style>

<main style="padding:2.5rem 0 5rem">
<div style="max-width:58rem;margin:0 auto;padding:0 1rem">

<?php if ($success): ?>
  <div style="text-align:center;padding:4rem 0">
    <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="1.5" style="margin-bottom:1.5rem">
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
    </svg>
    <h1 style="font-family:Manrope,sans-serif;font-weight:700;font-size:2rem;letter-spacing:-0.02em;margin:0 0 0.5rem">Объявление отправлено!</h1>
    <p style="color:#7A8A9A;margin:0 0 2rem;font-size:0.875rem">Объявление на модерации. После проверки оно появится в каталоге.</p>
    <div style="display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap">
      <a href="/listing/<?=$success?>" class="cta-btn" style="gap:0.375rem">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        Смотреть объявление
      </a>
      <a href="/dashboard" class="btn-outline">В кабинет</a>
    </div>
  </div>
<?php else: ?>

  <span style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.1em;color:#7A8A9A;font-weight:500">Новое объявление</span>
  <h1 style="font-family:Manrope,sans-serif;font-weight:700;font-size:2rem;letter-spacing:-0.02em;margin:0.25rem 0 2rem">Расскажите о вашем предложении</h1>

  <!-- Steps indicator -->
  <div style="display:flex;align-items:center;gap:0;margin-bottom:2.5rem">
    <?php
    $stepLabels = [1=>'Тип',2=>'Детали',3=>'Характеристики',4=>'Фото',5=>'Готово'];
    $stepIcons = [
      1 => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>',
      2 => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
      3 => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 22 8.5 22 15.5 12 22 2 15.5 2 8.5 12 2"/><line x1="12" y1="22" x2="12" y2="15.5"/><polyline points="22 8.5 12 15.5 2 8.5"/></svg>',
      4 => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
      5 => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
    ];
    $totalSteps = count($stepLabels);
    $i = 0;
    foreach ($stepLabels as $n => $lbl):
      $i++;
      $done = $step > $n;
      $active = $step === $n;
    ?>
    <div style="display:flex;align-items:center;gap:0.5rem;flex:1;min-width:0">
      <div style="width:2.25rem;height:2.25rem;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;flex-shrink:0;transition:all 0.2s ease;<?=$done||$active?'background:#1B6B8A;color:#F7F9FB':'background:#EEF2F6;color:#7A8A9A'?>">
        <?php if ($done): ?>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        <?php else: ?>
          <?=$stepIcons[$n]?>
        <?php endif; ?>
      </div>
      <span style="font-size:0.75rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:<?=$done||$active?'#1B6B8A':'#7A8A9A'?>"><?=$lbl?></span>
    </div>
    <?php if ($i < $totalSteps): ?>
    <div style="height:1px;background:<?=$done?'#1B6B8A':'#DFE4EA'?>;flex:0.5;margin:0 0.25rem"></div>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($errors)): ?>
    <div style="background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;border-radius:8px;padding:0.875rem 1rem;margin-bottom:1.5rem;font-size:0.8125rem">
      <?php foreach($errors as $e): $txt = is_array($e) ? $e['text'] : $e; ?>
      <div style="padding:0.125rem 0"><?=h($txt)?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:2rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
    <?= csrf_field() ?>
    <input type="hidden" name="step" value="<?=$step?>">

    <?php if ($step === 1): ?>
      <!-- Step 1: Choose type -->
      <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.25rem;margin:0 0 1.5rem">Тип объявления</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:0.75rem">
        <?php foreach($LISTING_LABELS as $k=>$v): $sel = ($_POST['listing_type']??'')===$k; ?>
        <label style="position:relative;border-radius:12px;border:2px solid <?=$sel?'#1B6B8A':'#EEF2F6'?>;padding:1.5rem 0.75rem;cursor:pointer;text-align:center;transition:all 0.15s ease;<?=$sel?'background:rgba(27,107,138,0.04)':''?>" onmouseover="if(!this.querySelector('input:checked')){this.style.borderColor='#C8D0DA'}" onmouseout="if(!this.querySelector('input:checked')){this.style.borderColor='#EEF2F6'}">
          <input type="radio" name="listing_type" value="<?=$k?>" style="position:absolute;opacity:0" <?=$sel?'checked':''?> onchange="this.form.step.value=1;this.form.submit()">
          <div style="color:<?=$sel?'#1B6B8A':'#7A8A9A'?>;margin-bottom:0.5rem"><?=$LISTING_ICONS[$k]?></div>
          <div style="font-size:0.8125rem;font-weight:500"><?=$v?></div>
        </label>
        <?php endforeach; ?>
      </div>

      <!-- Subcategory -->
      <?php $slt = $_POST['listing_type'] ?? ''; if ($slt && isset($CATEGORY_OPTIONS[$slt])): ?>
      <div style="margin-top:1.5rem">
        <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.25rem;margin:0 0 1rem">Категория</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.625rem">
          <?php foreach($CATEGORY_OPTIONS[$slt] as $c): $sel = ($_POST['category']??'')===$c[0]; ?>
          <label style="position:relative;border-radius:8px;border:1px solid <?=$sel?'#1B6B8A':'#DFE4EA'?>;padding:0.75rem 1rem;cursor:pointer;transition:all 0.15s ease;<?=$sel?'background:rgba(27,107,138,0.04)':''?>" onmouseover="if(!this.querySelector('input:checked')){this.style.borderColor='#C8D0DA'}" onmouseout="if(!this.querySelector('input:checked')){this.style.borderColor='#DFE4EA'}">
            <input type="radio" name="category" value="<?=$c[0]?>" style="position:absolute;opacity:0" <?=$sel?'checked':''?> onchange="this.form.step.value=1;this.form.submit()">>
            <div style="font-size:0.8125rem;font-weight:500;display:flex;align-items:center;gap:0.5rem">
              <?php if($sel): ?><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1B6B8A" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg><?php endif; ?>
              <?=$c[1]?>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    <?php elseif ($step === 2): ?>
      <!-- Step 2: Details -->
      <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.25rem;margin:0 0 1.5rem">Основная информация</h2>
      <input type="hidden" name="listing_type" value="<?=h($_POST['listing_type']??'')?>">
      <input type="hidden" name="category" value="<?=h($_POST['category']??'')?>">
      <div class="form-group"><label>Название объявления <span style="color:#DC2626">*</span></label><input type="text" name="title" value="<?=h($_POST['title']??'')?>" style="width:100%;box-sizing:border-box" placeholder="Напр. «Уютная квартира с видом на море»" required></div>
      <div class="form-group"><label>Описание <span style="color:#DC2626">*</span></label><textarea name="description" rows="5" style="width:100%;box-sizing:border-box" placeholder="Опишите ваше предложение подробно..." required><?=h($_POST['description']??'')?></textarea></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <div class="form-group"><label>Цена <span style="color:#DC2626">*</span></label>
          <select name="price_type" id="price_type" onchange="priceTypeChange()" style="width:100%;box-sizing:border-box;margin-bottom:0.5rem">
            <option value="fixed" <?=($_POST['price_type']??'fixed')==='fixed'?'selected':''?>>Точная цена</option>
            <option value="from" <?=($_POST['price_type']??'')==='from'?'selected':''?>>От (цена от …)</option>
            <option value="negotiable" <?=($_POST['price_type']??'')==='negotiable'?'selected':''?>>По договорённости</option>
          </select>
          <input type="number" name="price" id="price_input" value="<?=h($_POST['price']??'')?>" style="width:100%;box-sizing:border-box" min="1" step="1" required>
        </div>
        <div class="form-group"><label>Локация <span style="color:#DC2626">*</span></label>
          <?php $__loc = $_POST['location'] ?? ''; $__isCustom = ($__loc !== '' && !in_array($__loc, $LOCATIONS, true)); ?>
          <select name="location" id="location_select" onchange="locChange()" required style="width:100%;box-sizing:border-box"><option value="">Выберите...</option>
            <?php foreach($LOCATIONS as $l): ?><option value="<?=$l?>" <?=$__loc===$l?'selected':''?>><?=$l?></option><?php endforeach; ?>
            <option value="__custom__" <?=($__loc==='__custom__'||$__isCustom)?'selected':''?>>Своя локация…</option>
          </select>
          <div id="location_custom_wrap" style="margin-top:0.5rem;<?=($__isCustom||$__loc==='__custom__')?'':'display:none'?>">
            <input type="text" name="location_custom" id="location_custom" value="<?=h($__isCustom?$__loc:'')?>" placeholder="Введите свою локацию" style="width:100%;box-sizing:border-box" <?=($__isCustom||$__loc==='__custom__')?'required':''?>>
          </div>
        </div>
        <script>
        function locChange(){
          var sel=document.getElementById('location_select');
          var wrap=document.getElementById('location_custom_wrap');
          var inp=document.getElementById('location_custom');
          if(sel.value==='__custom__'){wrap.style.display='';inp.setAttribute('required','required');}
          else{wrap.style.display='none';inp.removeAttribute('required');}
        }
        locChange();
        </script>
      </div>
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
      </script>
      <?php $lt = $_POST['listing_type'] ?? ''; if ($lt === 'property' || $lt === 'tour' || $lt === 'fishing'): ?>
      <div class="form-group"><label>Максимум гостей</label><input type="number" name="max_guests" value="<?=h($_POST['max_guests']??'2')?>" style="width:100%;box-sizing:border-box" min="1"></div>
      <?php endif; ?>

    <?php elseif ($step === 3): ?>
      <!-- Step 3: Type-specific fields -->
      <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.25rem;margin:0 0 1.5rem">Характеристики</h2>
      <?php foreach(['listing_type','category','title','description','price','price_type','location','max_guests'] as $f): ?>
      <input type="hidden" name="<?=$f?>" value="<?=h($_POST[$f]??'')?>">
      <?php endforeach; ?>
      <?php $lt = $_POST['listing_type'] ?? ''; ?>

      <?php if ($lt === 'property'): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem">
          <div class="form-group"><label>Комнат</label><input type="number" name="rooms_count" value="<?=h($_POST['rooms_count']??'')?>" style="width:100%;box-sizing:border-box" min="0"></div>
          <div class="form-group"><label>Кроватей</label><input type="number" name="beds_count" value="<?=h($_POST['beds_count']??'')?>" style="width:100%;box-sizing:border-box" min="0"></div>
          <div class="form-group"><label>Санузлов</label><input type="number" name="bathrooms_count" value="<?=h($_POST['bathrooms_count']??'1')?>" style="width:100%;box-sizing:border-box" min="0"></div>
          <div class="form-group"><label>Площадь, м²</label><input type="number" name="area_sqm" value="<?=h($_POST['area_sqm']??'')?>" style="width:100%;box-sizing:border-box" min="0"></div>
          <div class="form-group"><label>Заезд (время)</label><input type="text" name="check_in" value="<?=h($_POST['check_in']??'14:00')?>" style="width:100%;box-sizing:border-box"></div>
          <div class="form-group"><label>Выезд (время)</label><input type="text" name="check_out" value="<?=h($_POST['check_out']??'12:00')?>" style="width:100%;box-sizing:border-box"></div>
        </div>
        <div class="form-group"><label>Депозит (₽)</label><input type="number" name="deposit" value="<?=h($_POST['deposit']??'')?>" style="width:100%;box-sizing:border-box" min="0"></div>
      <?php elseif ($lt === 'tour'): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem">
          <div class="form-group"><label>Длительность, часов</label><input type="number" name="tour_hours" value="<?=h($_POST['tour_hours']??'')?>" style="width:100%;box-sizing:border-box" min="0"></div>
          <div class="form-group"><label>Длительность, дней</label><input type="number" name="tour_days" value="<?=h($_POST['tour_days']??'')?>" style="width:100%;box-sizing:border-box" min="0"></div>
          <div class="form-group"><label>Мин. группа</label><input type="number" name="group_min" value="<?=h($_POST['group_min']??'')?>" style="width:100%;box-sizing:border-box" min="1"></div>
          <div class="form-group"><label>Макс. группа</label><input type="number" name="group_max" value="<?=h($_POST['group_max']??'')?>" style="width:100%;box-sizing:border-box" min="1"></div>
          <div class="form-group"><label>Сложность</label><select name="difficulty" style="width:100%;box-sizing:border-box"><option value="easy">Лёгкий</option><option value="medium" <?=($_POST['difficulty']??'')==='medium'?'selected':''?>>Средний</option><option value="hard" <?=($_POST['difficulty']??'')==='hard'?'selected':''?>>Сложный</option><option value="extreme" <?=($_POST['difficulty']??'')==='extreme'?'selected':''?>>Экстремальный</option></select></div>
          <div class="form-group"><label>Точка старта</label><input type="text" name="start_point" value="<?=h($_POST['start_point']??'')?>" style="width:100%;box-sizing:border-box"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-top:1rem">
          <?php foreach(['transport_inc'=>'Транспорт включён','border_permit'=>'Нужен погранпропуск','weather_dep'=>'Зависит от погоды','meals'=>'Питание включено'] as $fk=>$fl): ?>
          <label style="display:flex;align-items:center;gap:0.5rem;padding:0.75rem 1rem;border-radius:8px;border:1px solid #DFE4EA;cursor:pointer;transition:all 0.15s ease" onmouseover="this.style.borderColor='#C8D0DA';this.style.background='#F7F9FB'" onmouseout="this.style.borderColor='#DFE4EA';this.style.background='transparent'">
            <input type="checkbox" name="<?=$fk?>" value="1" <?=($_POST[$fk]??'')?'checked':''?> style="width:1rem;height:1rem;accent-color:#1B6B8A">
            <span style="font-size:0.8125rem"><?=$fl?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <div class="form-group" style="margin-top:1rem"><label>Тип транспорта</label><input type="text" name="transport_type" value="<?=h($_POST['transport_type']??'')?>" style="width:100%;box-sizing:border-box" placeholder="Джип, катер..."></div>
        <div style="margin-top:1.5rem">
          <h3 style="font-family:Manrope,sans-serif;font-weight:600;font-size:0.9375rem;margin:0 0 0.5rem">Исполнитель услуги (ФЗ-132)</h3>
          <p style="font-size:0.75rem;color:#7A8A9A;margin:0 0 0.75rem">Укажите статус организатора. Для туроператоров требуется вхождение в единый федеральный реестр туроператоров; для турагентов — указание туроператора, от имени которого действует агент.</p>
          <div class="form-group"><label>Статус организатора <span style="color:#DC2626">*</span></label>
            <select name="tour_organizer_type" style="width:100%;box-sizing:border-box">
              <option value="">Выберите...</option>
              <option value="tour_operator" <?=($_POST['tour_organizer_type']??'')==='tour_operator'?'selected':''?>>Туроператор (в реестре)</option>
              <option value="travel_agent" <?=($_POST['tour_organizer_type']??'')==='travel_agent'?'selected':''?>>Турагент</option>
              <option value="individual" <?=($_POST['tour_organizer_type']??'')==='individual'?'selected':''?>>Частное лицо</option>
            </select>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:0.75rem">
            <div class="form-group"><label>Наименование туроператора</label><input type="text" name="tour_operator_name" value="<?=h($_POST['tour_operator_name']??'')?>" style="width:100%;box-sizing:border-box" placeholder="ООО «...»"></div>
            <div class="form-group"><label>Реестровый номер</label><input type="text" name="tour_operator_regno" value="<?=h($_POST['tour_operator_regno']??'')?>" style="width:100%;box-sizing:border-box" placeholder="РТО 000000"></div>
          </div>
        </div>
      <?php elseif ($lt === 'fishing'): ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group"><label>Тип рыбалки</label>
            <select name="fishing_type" style="width:100%;box-sizing:border-box"><option value="rechnaya">Речная</option><option value="morskaya">Морская</option><option value="ozernaya">Озёрная</option><option value="podlednaya">Подлёдная</option><option value="splav">Сплав</option></select>
          </div>
          <div class="form-group"><label>Метод ловли</label><input type="text" name="fishing_method" value="<?=h($_POST['fishing_method']??'')?>" style="width:100%;box-sizing:border-box" placeholder="Спиннинг, нахлыст..."></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-top:1rem">
          <?php foreach(['gear_inc'=>'Снаряжение включено','catch_g'=>'Гарантия улова','license'=>'Нужна лицензия','boat'=>'Лодка включена'] as $fk=>$fl): ?>
          <label style="display:flex;align-items:center;gap:0.5rem;padding:0.75rem 1rem;border-radius:8px;border:1px solid #DFE4EA;cursor:pointer;transition:all 0.15s ease" onmouseover="this.style.borderColor='#C8D0DA';this.style.background='#F7F9FB'" onmouseout="this.style.borderColor='#DFE4EA';this.style.background='transparent'">
            <input type="checkbox" name="<?=$fk?>" value="1" <?=($_POST[$fk]??'')?'checked':''?> style="width:1rem;height:1rem;accent-color:#1B6B8A">
            <span style="font-size:0.8125rem"><?=$fl?></span>
          </label>
          <?php endforeach; ?>
        </div>
      <?php elseif ($lt === 'rental_gear'): ?>
        <div class="form-group"><label>Состояние снаряжения</label>
          <select name="gear_condition" style="width:100%;box-sizing:border-box"><option value="new">Новое</option><option value="excellent">Отличное</option><option value="good">Хорошее</option><option value="used">Б/у</option></select>
        </div>
        <div class="form-group"><label>Депозит (₽)</label><input type="number" name="deposit" value="<?=h($_POST['deposit']??'')?>" style="width:100%;box-sizing:border-box" min="0"></div>
      <?php endif; ?>

      <!-- Common: Amenities -->
      <?php if ($lt === 'property' || $lt === 'tour'): ?>
      <div style="margin-top:1.5rem">
        <h3 style="font-family:Manrope,sans-serif;font-weight:600;font-size:0.9375rem;margin:0 0 0.75rem">Удобства</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:0.375rem">
        <?php foreach($AMENITY_OPTIONS as $a): $ckd = in_array($a, $_POST['amenities']??[]); ?>
        <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;cursor:pointer;padding:0.375rem 0.5rem;border-radius:6px;transition:background 0.15s ease" onmouseover="this.style.background='#F7F9FB'" onmouseout="this.style.background='transparent'">
          <input type="checkbox" name="amenities[]" value="<?=h($a)?>" <?=$ckd?'checked':''?> style="width:1rem;height:1rem;accent-color:#1B6B8A"><?=$a?>
        </label>
        <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Common: Season -->
      <div class="form-group" style="margin-top:1rem"><label>Сезон</label>
        <select name="season" style="width:100%;box-sizing:border-box"><option value="all_season">Круглый год</option><option value="summer">Лето</option><option value="winter">Зима</option></select>
      </div>

    <?php elseif ($step === 4): ?>
      <!-- Step 4: Photos -->
      <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.25rem;margin:0 0 1.5rem">Фотографии</h2>
      <?php foreach(['listing_type','category','title','description','price','price_type','location','max_guests','rooms_count','beds_count','bathrooms_count','area_sqm','check_in','check_out','deposit','tour_hours','tour_days','difficulty','group_min','group_max','start_point','transport_inc','border_permit','weather_dep','meals','transport_type','gear_condition','fishing_type','fishing_method','gear_inc','catch_g','license','boat','tour_organizer_type','tour_operator_name','tour_operator_regno','season'] as $f): ?>
      <input type="hidden" name="<?=$f?>" value="<?=h($_POST[$f]??'')?>">
      <?php endforeach; ?>
      <?php if (isset($_POST['amenities'])): foreach($_POST['amenities'] as $a): ?><input type="hidden" name="amenities[]" value="<?=h($a)?>"><?php endforeach; endif; ?>

      <div style="border:2px dashed #DFE4EA;border-radius:12px;padding:2.5rem;text-align:center;cursor:pointer;transition:all 0.15s ease" onclick="document.getElementById('imgInput').click()" onmouseover="this.style.borderColor='#C8D0DA';this.style.background='rgba(238,242,246,0.3)'" onmouseout="this.style.borderColor='#DFE4EA';this.style.background='transparent'">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#7A8A9A" stroke-width="1.5" style="margin-bottom:0.75rem">
          <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
        </svg>
        <p style="font-size:0.875rem;color:#7A8A9A;margin:0">Нажмите, чтобы выбрать фото</p>
        <p style="font-size:0.75rem;color:#7A8A9A;margin:0.25rem 0 0">До 10 файлов, JPG/PNG/WebP, до 2 МБ каждый</p>
        <p id="imgError" style="font-size:0.75rem;color:#DC2626;margin:0.25rem 0 0;display:none"></p>
        <input type="file" id="imgInput" name="images[]" multiple accept="image/jpeg,image/png,image/webp" hidden onchange="previewImages(event)">
      </div>
      <div id="imgPreview" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:0.75rem;margin-top:1rem"></div>
      <p id="imgCount" style="font-size:0.75rem;color:#7A8A9A;margin:0.5rem 0 0;display:none"></p>

<script>
function previewImages(e) {
  var MAX = 2 * 1024 * 1024; // 2MB
  var files = Array.from(e.target.files).slice(0,10);
  var grid = document.getElementById('imgPreview');
  var count = document.getElementById('imgCount');
  var err = document.getElementById('imgError');
  err.style.display = 'none';
  // Check sizes
  var oversized = files.filter(function(f){return f.size > MAX});
  if (oversized.length) {
    err.textContent = oversized.length + ' файл(ов) превышают 2 МБ: ' + oversized.map(function(f){return f.name}).join(', ');
    err.style.display = 'block';
    // Remove oversized from DataTransfer too
    var dt = new DataTransfer();
    Array.from(files).forEach(function(f){ if(f.size <= MAX) dt.items.add(f); });
    e.target.files = dt.files;
    files = Array.from(e.target.files).slice(0,10);
  }
  grid.innerHTML = '';
  if (files.length === 0) { count.style.display = 'none'; if(!oversized.length) err.style.display='none'; return; }
  count.style.display = 'block';
  count.textContent = files.length + ' фото выбрано';
  files.forEach(function(f,i) {
    var r = new FileReader();
    r.onload = function(ev) {
      var card = document.createElement('div');
      card.style.cssText = 'position:relative;aspect-ratio:1;border-radius:8px;overflow:hidden;border:1px solid #EEF2F6;background:rgba(238,242,246,0.4)';
      card.innerHTML = '<img src="'+ev.target.result+'" style="width:100%;height:100%;object-fit:cover">' +
        '<button type="button" style="position:absolute;top:4px;right:4px;width:1.5rem;height:1.5rem;background:rgba(0,0,0,0.5);border:0;color:#fff;border-radius:50%;font-size:0.75rem;display:flex;align-items:center;justify-content:center;cursor:pointer" onclick="removeImage('+i+');event.stopPropagation();">&times;</button>';
      card.dataset.idx = i;
      grid.appendChild(card);
    };
    r.readAsDataURL(f);
  });
}
function removeImage(idx) {
  var input = document.getElementById('imgInput');
  var dt = new DataTransfer();
  Array.from(input.files).forEach(function(f,i){ if(i!==idx) dt.items.add(f); });
  input.files = dt.files;
  previewImages({target:input});
}
</script>

    <?php elseif ($step === 5): ?>
      <!-- Step 5: Confirm -->
      <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.25rem;margin:0 0 1.5rem">Подтверждение</h2>
      <?php foreach(array_keys($_POST) as $f): if ($f==='step'||$f==='finish'||is_array($_POST[$f]??null)) continue; ?>
      <input type="hidden" name="<?=$f?>" value="<?=h($_POST[$f]??'')?>">
      <?php endforeach; ?>
      <input type="hidden" name="step" value="5">
      <?php if (isset($_POST['amenities'])): foreach($_POST['amenities'] as $a): ?><input type="hidden" name="amenities[]" value="<?=h($a)?>"><?php endforeach; endif; ?>
      <div style="background:#F7F9FB;border-radius:12px;padding:1.5rem;font-size:0.875rem">
        <?php
        $fields = [
          'Тип' => $LISTING_LABELS[$_POST['listing_type']??''] ?? '',
          'Категория' => $_POST['category'] ?? '',
          'Название' => $_POST['title'] ?? '',
          'Цена' => ($_POST['price_type']??'fixed') === 'negotiable' ? 'По договорённости' : ((($_POST['price_type']??'fixed') === 'from' ? 'от ' : '').number_format((float)($_POST['price']??0),0,'.',' ').' '.price_label($_POST['listing_type']??'')),
          'Локация' => $_POST['location'] ?? '',
          'Фото' => empty($_SESSION['tmp_images'])?'Нет':count($_SESSION['tmp_images']).' фото',
        ];
        foreach ($fields as $k => $v):
          if (!$v) continue;
        ?>
        <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid #EEF2F6">
          <span style="color:#7A8A9A"><?=$k?>:</span>
          <span style="font-weight:500;color:#1B6B8A"><?=h($v)?></span>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Navigation -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:2rem;padding-top:1.25rem;border-top:1px solid #EEF2F6">
      <?php if ($step > 1): ?>
      <button type="submit" name="step" value="<?=$step-1?>" style="display:inline-flex;align-items:center;gap:0.375rem;border-radius:8px;border:1px solid #DFE4EA;background:#fff;padding:0.625rem 1.25rem;font-size:0.8125rem;font-weight:500;cursor:pointer;color:#3A4A5C;font-family:inherit;transition:all 0.15s ease" onmouseover="this.style.background='#F7F9FB';this.style.borderColor='#C8D0DA'" onmouseout="this.style.background='#fff';this.style.borderColor='#DFE4EA'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Назад
      </button>
      <?php else: ?><div></div><?php endif; ?>
      <?php if ($step < 5): ?>
      <button type="submit" name="step" value="<?=$step+1?>" style="display:inline-flex;align-items:center;gap:0.375rem;border-radius:8px;border:0;background:#1B6B8A;color:#F7F9FB;padding:0.625rem 1.25rem;font-size:0.8125rem;font-weight:600;cursor:pointer;font-family:inherit;transition:background 0.15s ease" onmouseover="this.style.background='#1A2937'" onmouseout="this.style.background='#1B6B8A'">
        Далее
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </button>
      <?php else: ?>
      <button type="submit" name="finish" value="1" style="display:inline-flex;align-items:center;gap:0.375rem;border-radius:8px;border:0;background:#1B6B8A;color:#F7F9FB;padding:0.625rem 1.5rem;font-size:0.8125rem;font-weight:600;cursor:pointer;font-family:inherit;transition:background 0.15s ease" onmouseover="this.style.background='#1A2937'" onmouseout="this.style.background='#1B6B8A'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Опубликовать
      </button>
      <?php endif; ?>
    </div>
  </form>

<?php endif; ?>
</div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
