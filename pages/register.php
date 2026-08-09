<?php
// register.php — v5 split layout
if (isset($_SESSION['user_id'])) { header('Location: /'); exit; }

$errors = [];
$_pdo = db();
$_recent = $_pdo->query('SELECT l.id, l.title, l.price, l.listing_type, l.location,
  (SELECT filename FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1) AS image
  FROM listings l WHERE l.status = "active" ORDER BY l.created_at DESC LIMIT 5')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $password2 = $_POST['password2'] ?? '';
  $phone = trim($_POST['phone'] ?? '');

  if (empty($name) || mb_strlen($name) < 2) $errors[] = 'Имя должно быть не короче 2 символов';
  if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Укажите корректный email';
  if (mb_strlen($password) < 6) $errors[] = 'Пароль должен быть не короче 6 символов';
  if ($password !== $password2) $errors[] = 'Пароли не совпадают';

  if (empty($errors)) {
    $result = auth_register($email, $password, $name, $phone);
    if ($result['ok']) {
      header('Location: /');
      exit;
    }
    $errors[] = $result['error'];
  }
}

$page_title = 'Регистрация — СахGO';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Manrope','Arial','sans-serif'],display:['Manrope','Arial','sans-serif']},colors:{background:'#F7F9FB',foreground:'#121E2B',accent:'#1B6B8A',border:'#DFE4EA','muted-foreground':'#7A8A9A'}}}}</script>
<link rel="stylesheet" href="/includes/style.css?v=10">
</head>
<body class="min-h-screen">

<div class="min-h-[calc(100vh-4rem)] grid lg:grid-cols-2">

  <!-- Brand panel -->
  <div class="hidden lg:flex flex-col relative overflow-hidden" style="background: linear-gradient(155deg, #1a3a4a 0%, #1B6B8A 45%, #2a5a6a 100%)">
    <div class="absolute inset-0 opacity-20" style="background-image:url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 80 80%22><circle cx=%2240%22 cy=%2240%22 r=%221%22 fill=%22white%22/></svg>');background-size:80px 80px"></div>
    <div class="absolute inset-0" style="background:radial-gradient(ellipse 70% 50% at 30% 80%, rgba(0,0,0,0.3), transparent)"></div>

    <div class="relative p-12 pb-0">
      <a href="/"><img src="/logo.png" alt="СахGO" class="h-12 w-auto brightness-0 invert"></a>
    </div>

    <!-- Carousel -->
    <?php if (!empty($_recent)): ?>
    <div class="relative flex-1 flex items-center px-12 py-6">
      <div class="relative w-full overflow-hidden rounded-2xl">
        <div id="regCarousel" class="flex transition-transform duration-500 ease-out" style="transform:translateX(0)">
          <?php foreach ($_recent as $ri => $r): ?>
          <div class="shrink-0 w-full">
            <a href="/listing/<?=$r['id']?>" class="block bg-white rounded-2xl overflow-hidden shadow-2xl">
              <?php if (!empty($r['image'])): ?>
              <img src="/uploads/<?=h($r['image'])?>" alt="" class="w-full aspect-[4/3] object-cover">
              <?php else: ?>
              <div class="w-full aspect-[4/3] bg-[#EEF2F6]"></div>
              <?php endif; ?>
              <div class="p-4">
                <div class="font-display text-lg text-foreground"><?=number_format((float)$r['price'],0,'.',' ')?> <span class="text-xs font-normal text-[#9AAAB8]"><?=price_label($r['listing_type'])?></span></div>
                <div class="text-sm text-[#3A4A5C] mt-1 truncate"><?=h($r['title'])?></div>
                <div class="text-xs text-[#9AAAB8] mt-1"><?=h($r['location'])?></div>
              </div>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="flex justify-center gap-1.5 mt-3">
          <?php foreach ($_recent as $ri => $_): ?>
          <button onclick="slideTo(<?=$ri?>)" class="w-1.5 h-1.5 rounded-full transition-colors <?=$ri===0?'bg-white':'bg-white/40'?>" id="dot_<?=$ri?>"></button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="relative p-12 pt-0 text-white">
      <h2 class="font-display text-3xl leading-tight mb-2">Размещайте<br>свои объявления</h2>
      <div class="space-y-2 mt-4 max-w-sm">
        <div class="flex items-center gap-2.5">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          <span class="text-sm text-white/70">Бесплатно и без комиссии</span>
        </div>
        <div class="flex items-center gap-2.5">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          <span class="text-sm text-white/70">Прямой контакт с клиентами</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Form panel -->
  <div class="flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-sm">

      <div class="text-center mb-8 lg:hidden">
        <a href="/"><img src="/logo.png" alt="СахGO" class="h-12 w-auto mx-auto"></a>
      </div>

      <h1 class="font-display text-2xl mb-1">Создать аккаунт</h1>
      <p class="text-sm text-[#9AAAB8] mb-7">Регистрация займёт меньше минуты</p>

      <?php if (!empty($errors)): ?>
      <div class="bg-red-50 border border-red-200 rounded-lg px-3 py-2.5 mb-4 space-y-1">
        <?php foreach ($errors as $e): ?>
        <div class="flex items-start gap-2 text-xs text-red-700">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0 mt-px"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
          <span><?= h($e) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="post" class="space-y-4">
        <?= csrf_field() ?>

        <div>
          <label>Имя</label>
          <div class="relative">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9AAAB8]"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input type="text" name="name" value="<?= h($_POST['name'] ?? '') ?>" required autofocus class="w-full" style="padding-left:2.25rem">
          </div>
        </div>

        <div>
          <label>Email</label>
          <div class="relative">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9AAAB8]"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required class="w-full" style="padding-left:2.25rem">
          </div>
        </div>

        <div>
          <label>Телефон</label>
          <div class="relative">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9AAAB8]"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <input type="text" name="phone" value="<?= h($_POST['phone'] ?? '') ?>" placeholder="+7 (XXX) XXX-XX-XX" class="w-full" style="padding-left:2.25rem">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label>Пароль</label>
            <div class="relative">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9AAAB8]"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <input type="password" name="password" id="pwField" required class="w-full" style="padding-left:2.25rem;padding-right:2rem">
            </div>
          </div>
          <div>
            <label>Повтор</label>
            <div class="relative">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9AAAB8]"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <input type="password" name="password2" required class="w-full" style="padding-left:2.25rem;padding-right:2rem">
            </div>
          </div>
        </div>

        <button type="submit" class="w-full bg-accent text-white rounded-lg h-11 text-sm font-semibold hover:bg-accent/90 transition-colors">
          Зарегистрироваться
        </button>
      </form>

      <div class="flex items-center gap-3 my-6">
        <div class="flex-1 h-px bg-[#EBEEF2]"></div>
        <span class="text-xs text-[#9AAAB8]">или</span>
        <div class="flex-1 h-px bg-[#EBEEF2]"></div>
      </div>

      <p class="text-center text-sm text-[#7A8A9A]">
        Уже есть аккаунт? <a href="/login" class="text-accent font-semibold hover:underline">Войти</a>
      </p>

    </div>
  </div>
</div>

<script>
function togglePw(){
  var f=document.getElementById('pwField');
  if(f.type==='password')f.type='text';else f.type='password';
}
</script>
<script>
var cIdx = 0;
var cMax = <?=count($_recent)?>;
function slideTo(i){
  cIdx = i;
  document.getElementById('regCarousel').style.transform = 'translateX(-'+(i*100)+'%)';
  for(var j=0;j<cMax;j++){var d=document.getElementById('dot_'+j);if(d){d.className='w-1.5 h-1.5 rounded-full transition-colors '+(j===i?'bg-white':'bg-white/40');}}
}
setInterval(function(){ cIdx=(cIdx+1)%cMax; slideTo(cIdx); }, 4000);
</script>
</body></html>
