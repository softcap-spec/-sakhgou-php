<?php
// create.php v2 — Tailwind wizard
$cu = auth_required();
$pdo = db();

$LISTING_LABELS = ['property'=>'Жильё','tour'=>'Тур','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто'];
$LISTING_EMOJI = ['property'=>'🏠','tour'=>'🏔️','fishing'=>'🎣','rental_gear'=>'🔧','car_rental'=>'🚗'];

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
    if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
    // Verify actual MIME type from file contents (not client-supplied name)
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
  $price = (float)($_POST['price'] ?? 0);
  $loc = $_POST['location'] ?? '';
  $guests = (int)($_POST['max_guests'] ?? 0);

  if (empty($title)) $errors[] = 'Введите название';
  if ($price <= 0) $errors[] = 'Укажите цену';
  if (empty($desc)) $errors[] = 'Добавьте описание';
  if (empty($loc)) $errors[] = 'Укажите локацию';
  if (empty($lt) || !isset($LISTING_LABELS[$lt])) $errors[] = 'Выберите тип';

  if (empty($errors)) {
    try {
    $catStmt = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
    $catStmt->execute([$lt]);
    $catRow = $catStmt->fetch();
    $real_cid = $catRow ? $catRow['id'] : 1;

    // Unique slug
    $baseSlug = transliterate($title);
    $slug = $baseSlug;
    $n = 1;
    while ($pdo->query("SELECT 1 FROM listings WHERE slug = ".$pdo->quote($slug))->fetch()) {
      $slug = $baseSlug . '-' . ($n++);
    }

    $stmt = $pdo->prepare("INSERT INTO listings (user_id,category_id,listing_type,subcategory,title,slug,description,price,currency,max_guests,location,
      rooms_count,beds_count,bathrooms_count,area_sqm,amenities,check_in_time,check_out_time,deposit_amount,
      tour_duration_hours,tour_duration_days,difficulty_level,group_size_min,group_size_max,start_point,
      requires_border_permit,depends_on_weather,transport_included,transport_type,
      gear_condition,fishing_type,fishing_method,gear_included,catch_guarantee,license_required,boat_included,
      meals_included,season,cancellation_policy,status)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
      $cu['id'], $real_cid, $lt, $cat, $title, $slug, $desc, $price, 'RUB',
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
    } catch (PDOException $e) {
      $errors[] = 'Ошибка БД: ' . $e->getMessage();
    }

    // Move images from temp to permanent
    $tmpDir = UPLOAD_DIR . '/.tmp';
    if (!empty($_SESSION['tmp_images'])) {
      $pdo->beginTransaction();
      foreach ($_SESSION['tmp_images'] as $i => $img) {
        $ext = pathinfo($img['orig'], PATHINFO_EXTENSION) ?: 'jpg';
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

<main class="py-12">
<div class="max-w-3xl mx-auto px-4">

<?php if ($success): ?>
  <div class="text-center py-16">
    <div class="text-6xl mb-6">🎉</div>
    <h1 class="font-display text-4xl mb-4">Объявление отправлено!</h1>
    <p class="text-muted-foreground mb-8">Объявление на модерации. После проверки оно появится в каталоге.</p>
    <div class="flex gap-3 justify-center">
      <a href="/listing/<?=$success?>" class="inline-flex items-center justify-center rounded-lg bg-accent text-white hover:bg-accent/80 h-10 px-6 text-sm font-medium transition-all">Смотреть объявление</a>
      <a href="/dashboard" class="inline-flex items-center justify-center rounded-lg bg-accent text-white hover:bg-accent/80 h-10 px-6 text-sm font-medium transition-all">В кабинет</a>
    </div>
  </div>
<?php else: ?>

  <span class="text-xs uppercase tracking-[0.12em] text-accent font-medium">Новое объявление</span>
  <h1 class="font-display text-4xl mt-1 mb-8">Расскажите о вашем предложении</h1>

  <!-- Steps indicator -->
  <div class="flex gap-2 mb-10">
    <?php foreach ([1=>'Тип',2=>'Детали',3=>'Характеристики',4=>'Фото',5=>'Готово'] as $n=>$lbl): ?>
    <div class="flex-1 flex items-center gap-2">
      <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold <?=$step>=$n?'bg-accent text-white':'bg-muted text-muted-foreground'?>"><?=$n?></div>
      <span class="text-xs <?=$step>=$n?'text-foreground font-medium':'text-muted-foreground'?> hidden sm:inline"><?=$lbl?></span>
    </div>
    <?php if ($n<5): ?><div class="w-8 h-px bg-border self-center"></div><?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-6 text-sm space-y-1">
      <?php foreach($errors as $e): ?><div>• <?=h($e)?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="bg-white border rounded-xl p-6 md:p-8 space-y-6">
    <?= csrf_field() ?>
    <input type="hidden" name="step" value="<?=$step?>">

    <?php if ($step === 1): ?>
      <!-- Step 1: Choose type -->
      <h2 class="font-display text-2xl mb-4">Тип объявления</h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <?php foreach($LISTING_LABELS as $k=>$v): $sel = ($_POST['listing_type']??'')===$k; ?>
        <label class="relative rounded-xl border-2 p-4 cursor-pointer transition-all text-center <?=$sel?'border-accent bg-accent/5':'border-border hover:border-accent/30'?>">
          <input type="radio" name="listing_type" value="<?=$k?>" class="sr-only" <?=$sel?'checked':''?>>
          <div class="text-3xl mb-1"><?=$LISTING_EMOJI[$k]?></div>
          <div class="text-sm font-medium"><?=$v?></div>
        </label>
        <?php endforeach; ?>
      </div>

      <!-- Subcategory -->
      <?php $slt = $_POST['listing_type'] ?? ''; if ($slt && isset($CATEGORY_OPTIONS[$slt])): ?>
      <div class="mt-6"><h2 class="font-display text-2xl mb-4">Категория</h2>
        <div class="grid grid-cols-2 gap-3">
          <?php foreach($CATEGORY_OPTIONS[$slt] as $c): $sel = ($_POST['category']??'')===$c[0]; ?>
          <label class="relative rounded-xl border-2 p-4 cursor-pointer transition-all <?=$sel?'border-accent bg-accent/5':'border-border hover:border-accent/30'?>">
            <input type="radio" name="category" value="<?=$c[0]?>" class="sr-only" <?=$sel?'checked':''?>>
            <div class="text-sm font-medium"><?=$c[1]?></div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    <?php elseif ($step === 2): ?>
      <!-- Step 2: Details -->
      <h2 class="font-display text-2xl mb-6">Основная информация</h2>
      <input type="hidden" name="listing_type" value="<?=h($_POST['listing_type']??'')?>">
      <input type="hidden" name="category" value="<?=h($_POST['category']??'')?>">
      <div class="form-group"><label>Название объявления</label><input type="text" name="title" value="<?=h($_POST['title']??'')?>" class="w-full" placeholder="Напр. «Уютная квартира с видом на море»" required></div>
      <div class="form-group"><label>Описание</label><textarea name="description" rows="5" class="w-full" placeholder="Опишите ваше предложение подробно..."><?=h($_POST['description']??'')?></textarea></div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="form-group"><label>Цена (<?=price_label($_POST['listing_type']??'')?>)</label><input type="number" name="price" value="<?=h($_POST['price']??'')?>" class="w-full" min="0" step="1" required></div>
        <div class="form-group"><label>Локация</label>
          <select name="location" class="w-full"><option value="">Выберите...</option>
            <?php foreach($LOCATIONS as $l): ?><option value="<?=$l?>" <?=($_POST['location']??'')===$l?'selected':''?>><?=$l?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php $lt = $_POST['listing_type'] ?? ''; if ($lt === 'property' || $lt === 'tour' || $lt === 'fishing'): ?>
      <div class="form-group"><label>Максимум гостей</label><input type="number" name="max_guests" value="<?=h($_POST['max_guests']??'2')?>" class="w-full" min="1"></div>
      <?php endif; ?>

    <?php elseif ($step === 3): ?>
      <!-- Step 3: Type-specific fields -->
      <h2 class="font-display text-2xl mb-6">Характеристики</h2>
      <?php foreach(['listing_type','category','title','description','price','location','max_guests'] as $f): ?>
      <input type="hidden" name="<?=$f?>" value="<?=h($_POST[$f]??'')?>">
      <?php endforeach; ?>
      <?php $lt = $_POST['listing_type'] ?? ''; ?>

      <?php if ($lt === 'property'): ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <div class="form-group"><label>Комнат</label><input type="number" name="rooms_count" value="<?=h($_POST['rooms_count']??'')?>" class="w-full" min="0"></div>
          <div class="form-group"><label>Кроватей</label><input type="number" name="beds_count" value="<?=h($_POST['beds_count']??'')?>" class="w-full" min="0"></div>
          <div class="form-group"><label>Санузлов</label><input type="number" name="bathrooms_count" value="<?=h($_POST['bathrooms_count']??'1')?>" class="w-full" min="0"></div>
          <div class="form-group"><label>Площадь, м²</label><input type="number" name="area_sqm" value="<?=h($_POST['area_sqm']??'')?>" class="w-full" min="0"></div>
          <div class="form-group"><label>Заезд (время)</label><input type="text" name="check_in" value="<?=h($_POST['check_in']??'14:00')?>" class="w-full"></div>
          <div class="form-group"><label>Выезд (время)</label><input type="text" name="check_out" value="<?=h($_POST['check_out']??'12:00')?>" class="w-full"></div>
        </div>
        <div class="form-group"><label>Депозит (₽)</label><input type="number" name="deposit" value="<?=h($_POST['deposit']??'')?>" class="w-full" min="0"></div>
      <?php elseif ($lt === 'tour'): ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <div class="form-group"><label>Длительность, часов</label><input type="number" name="tour_hours" value="<?=h($_POST['tour_hours']??'')?>" class="w-full" min="0"></div>
          <div class="form-group"><label>Длительность, дней</label><input type="number" name="tour_days" value="<?=h($_POST['tour_days']??'')?>" class="w-full" min="0"></div>
          <div class="form-group"><label>Мин. группа</label><input type="number" name="group_min" value="<?=h($_POST['group_min']??'')?>" class="w-full" min="1"></div>
          <div class="form-group"><label>Макс. группа</label><input type="number" name="group_max" value="<?=h($_POST['group_max']??'')?>" class="w-full" min="1"></div>
          <div class="form-group"><label>Сложность</label><select name="difficulty" class="w-full"><option value="easy">Лёгкий</option><option value="medium" <?=($_POST['difficulty']??'')==='medium'?'selected':''?>>Средний</option><option value="hard" <?=($_POST['difficulty']??'')==='hard'?'selected':''?>>Сложный</option><option value="extreme" <?=($_POST['difficulty']??'')==='extreme'?'selected':''?>>Экстремальный</option></select></div>
          <div class="form-group"><label>Точка старта</label><input type="text" name="start_point" value="<?=h($_POST['start_point']??'')?>" class="w-full"></div>
        </div>
        <div class="grid grid-cols-2 gap-3 mt-4">
          <?php foreach(['transport_inc'=>'Транспорт включён','border_permit'=>'Нужен погранпропуск','weather_dep'=>'Зависит от погоды','meals'=>'Питание включено'] as $fk=>$fl): ?>
          <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer hover:bg-muted/50"><input type="checkbox" name="<?=$fk?>" value="1" <?=($_POST[$fk]??'')?'checked':''?> class="rounded"><span class="text-sm"><?=$fl?></span></label>
          <?php endforeach; ?>
        </div>
        <div class="form-group mt-4"><label>Тип транспорта</label><input type="text" name="transport_type" value="<?=h($_POST['transport_type']??'')?>" class="w-full" placeholder="Джип, катер..."></div>
      <?php elseif ($lt === 'fishing'): ?>
        <div class="grid grid-cols-2 gap-4">
          <div class="form-group"><label>Тип рыбалки</label>
            <select name="fishing_type" class="w-full"><option value="rechnaya">Речная</option><option value="morskaya">Морская</option><option value="ozernaya">Озёрная</option><option value="podlednaya">Подлёдная</option><option value="splav">Сплав</option></select>
          </div>
          <div class="form-group"><label>Метод ловли</label><input type="text" name="fishing_method" value="<?=h($_POST['fishing_method']??'')?>" class="w-full" placeholder="Спиннинг, нахлыст..."></div>
        </div>
        <div class="grid grid-cols-2 gap-3 mt-4">
          <?php foreach(['gear_inc'=>'Снаряжение включено','catch_g'=>'Гарантия улова','license'=>'Нужна лицензия','boat'=>'Лодка включена'] as $fk=>$fl): ?>
          <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer hover:bg-muted/50"><input type="checkbox" name="<?=$fk?>" value="1" <?=($_POST[$fk]??'')?'checked':''?> class="rounded"><span class="text-sm"><?=$fl?></span></label>
          <?php endforeach; ?>
        </div>
      <?php elseif ($lt === 'rental_gear'): ?>
        <div class="form-group"><label>Состояние снаряжения</label>
          <select name="gear_condition" class="w-full"><option value="new">Новое</option><option value="excellent">Отличное</option><option value="good">Хорошее</option><option value="used">Б/у</option></select>
        </div>
        <div class="form-group"><label>Депозит (₽)</label><input type="number" name="deposit" value="<?=h($_POST['deposit']??'')?>" class="w-full" min="0"></div>
      <?php endif; ?>

      <!-- Common: Amenities -->
      <?php if ($lt === 'property' || $lt === 'tour'): ?>
      <div class="mt-6"><h3 class="font-medium mb-3">Удобства</h3><div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
        <?php foreach($AMENITY_OPTIONS as $a): $ckd = in_array($a, $_POST['amenities']??[]); ?>
        <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-muted/50 p-1.5 rounded"><input type="checkbox" name="amenities[]" value="<?=h($a)?>" <?=$ckd?'checked':''?> class="rounded"><?=$a?></label>
        <?php endforeach; ?>
      </div></div>
      <?php endif; ?>

      <!-- Common: Season -->
      <div class="form-group mt-4"><label>Сезон</label>
        <select name="season" class="w-full"><option value="all_season">Круглый год</option><option value="summer">Лето</option><option value="winter">Зима</option></select>
      </div>

    <?php elseif ($step === 4): ?>
      <!-- Step 4: Photos -->
      <h2 class="font-display text-2xl mb-6">Фотографии</h2>
      <?php foreach(['listing_type','category','title','description','price','location','max_guests','rooms_count','beds_count','bathrooms_count','area_sqm','check_in','check_out','deposit','tour_hours','tour_days','difficulty','group_min','group_max','start_point','transport_inc','border_permit','weather_dep','meals','transport_type','gear_condition','fishing_type','fishing_method','gear_inc','catch_g','license','boat','season'] as $f): ?>
      <input type="hidden" name="<?=$f?>" value="<?=h($_POST[$f]??'')?>">
      <?php endforeach; ?>
      <?php if (isset($_POST['amenities'])): foreach($_POST['amenities'] as $a): ?><input type="hidden" name="amenities[]" value="<?=h($a)?>"><?php endforeach; endif; ?>

      <div class="border-2 border-dashed rounded-xl p-8 text-center cursor-pointer hover:bg-muted/30 transition-colors" onclick="document.getElementById('imgInput').click()">
        <div class="text-4xl mb-2">📸</div>
        <p class="text-sm text-muted-foreground">Нажмите, чтобы выбрать фото</p>
        <p class="text-xs text-muted-foreground mt-1">До 10 файлов, JPG/PNG</p>
        <input type="file" id="imgInput" name="images[]" multiple accept="image/*" class="hidden" onchange="previewImages(event)">
      </div>
      <div id="imgPreview" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3 mt-4"></div>
      <p id="imgCount" class="text-xs text-muted-foreground mt-2 hidden"></p>

<script>
function previewImages(e) {
  var files = Array.from(e.target.files).slice(0,10);
  var grid = document.getElementById('imgPreview');
  var count = document.getElementById('imgCount');
  grid.innerHTML = '';
  if (files.length === 0) { count.classList.add('hidden'); return; }
  count.classList.remove('hidden');
  count.textContent = files.length + ' фото выбрано';
  files.forEach(function(f,i) {
    var r = new FileReader();
    r.onload = function(ev) {
      var card = document.createElement('div');
      card.className = 'relative aspect-square rounded-lg overflow-hidden border border-border bg-muted/30';
      card.innerHTML = '<img src="'+ev.target.result+'" class="w-full h-full object-cover">' +
        '<button type="button" class="absolute top-1 right-1 w-6 h-6 bg-black/50 hover:bg-black/70 text-white rounded-full text-xs flex items-center justify-center" onclick="removeImage('+i+');event.stopPropagation();">×</button>';
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
      <h2 class="font-display text-2xl mb-6">Подтверждение</h2>
      <?php foreach(array_keys($_POST) as $f): if ($f==='step'||$f==='finish'||is_array($_POST[$f]??null)) continue; ?>
      <input type="hidden" name="<?=$f?>" value="<?=h($_POST[$f]??'')?>">
      <?php endforeach; ?>
      <input type="hidden" name="step" value="5">
      <?php if (isset($_POST['amenities'])): foreach($_POST['amenities'] as $a): ?><input type="hidden" name="amenities[]" value="<?=h($a)?>"><?php endforeach; endif; ?>
      <div class="bg-secondary/50 rounded-xl p-6 space-y-3 text-sm">
        <div class="flex justify-between"><span class="text-muted-foreground">Тип:</span><span class="font-medium"><?=$LISTING_EMOJI[$_POST['listing_type']??'']??''?> <?=$LISTING_LABELS[$_POST['listing_type']??'']??''?></span></div>
        <div class="flex justify-between"><span class="text-muted-foreground">Название:</span><span class="font-medium"><?=h($_POST['title']??'')?></span></div>
        <div class="flex justify-between"><span class="text-muted-foreground">Цена:</span><span class="font-display text-lg"><?=number_format((float)($_POST['price']??0),0,'.',' ')?> <?=price_label($_POST['listing_type']??'')?></span></div>
        <div class="flex justify-between"><span class="text-muted-foreground">Локация:</span><span class="font-medium"><?=h($_POST['location']??'')?></span></div>
        <div class="flex justify-between"><span class="text-muted-foreground">Фото:</span><span class="font-medium"><?=empty($_SESSION['tmp_images'])?'Нет':count($_SESSION['tmp_images']).' фото'?></span></div>
      </div>
    <?php endif; ?>

    <!-- Navigation -->
    <div class="flex justify-between pt-6 border-t">
      <?php if ($step > 1): ?>
      <button type="submit" name="step" value="<?=$step-1?>" class="inline-flex items-center justify-center rounded-lg border border-border hover:bg-muted h-10 px-6 text-sm font-medium transition-all">← Назад</button>
      <?php else: ?><div></div><?php endif; ?>
      <?php if ($step < 5): ?>
      <button type="submit" name="step" value="<?=$step+1?>" class="inline-flex items-center justify-center rounded-lg bg-accent text-white hover:bg-accent/80 h-10 px-6 text-sm font-medium transition-all">Далее →</button>
      <?php else: ?>
      <button type="submit" name="finish" value="1" class="inline-flex items-center justify-center rounded-lg bg-accent text-white hover:bg-accent/80 h-10 px-8 text-sm font-medium transition-all">Опубликовать 🚀</button>
      <?php endif; ?>
    </div>
  </form>

<?php endif; ?>
</div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
