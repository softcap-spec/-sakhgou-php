<?php
// login.php — v5 split layout
if (isset($_SESSION['user_id'])) { header('Location: /dashboard'); exit; }

$error = '';
$_pdo = db();
$_recent = $_pdo->query('SELECT l.id, l.title, l.price, l.listing_type, l.location,
  (SELECT filename FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1) AS image
  FROM listings l WHERE l.status = "active" ORDER BY l.created_at DESC LIMIT 12')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $result = auth_login($email, $password);
  if ($result['ok']) {
    header('Location: /dashboard');
    exit;
  }
  $error = $result['error'];
}

$page_title = 'Вход — СахGO';
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

  <!-- Brand panel (desktop only) -->
  <div class="hidden lg:flex flex-col relative overflow-hidden" style="background: linear-gradient(155deg, #1a3a4a 0%, #1B6B8A 45%, #2a5a6a 100%)">
    <div class="absolute inset-0 opacity-20" style="background-image:url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 80 80%22><circle cx=%2240%22 cy=%2240%22 r=%221%22 fill=%22white%22/></svg>');background-size:80px 80px"></div>
    <div class="absolute inset-0" style="background:radial-gradient(ellipse 70% 50% at 30% 80%, rgba(0,0,0,0.3), transparent)"></div>

    <!-- Logo -->
    <div class="relative p-12 pb-0">
      <a href="/"><img src="/logo.png" alt="СахGO" class="h-12 w-auto brightness-0 invert"></a>
    </div>

    <!-- Carousel -->
    <?php if (!empty($_recent)): $cardW = 196; $gap = 14; ?>
    <div class="relative flex-1 flex flex-col justify-center px-12 py-6">
      <p class="text-white/50 text-xs uppercase tracking-wider mb-3">Свежие объявления</p>
      <div class="relative overflow-hidden" id="carouselViewport">
        <div id="loginCarousel" class="flex" style="gap:<?=$gap?>px;transition:transform 0.5s cubic-bezier(0.25,0.1,0.25,1)">
          <?php foreach ($_recent as $ri => $r): ?>
          <a href="/listing/<?=$r['id']?>" class="shrink-0 bg-white/95 backdrop-blur rounded-xl overflow-hidden hover:bg-white transition-colors group" style="width:<?=$cardW?>px">
            <?php if (!empty($r['image'])): ?>
            <img src="/uploads/<?=h($r['image'])?>" alt="" class="w-full aspect-[4/3] object-cover">
            <?php else: ?>
            <div class="w-full aspect-[4/3] bg-[#D5DEE6]"></div>
            <?php endif; ?>
            <div class="p-2.5">
              <div class="font-semibold text-sm text-[#121E2B] leading-tight"><?=number_format((float)$r['price'],0,'.',' ')?> <span class="font-normal text-[10px] text-[#9AAAB8]"><?=price_label($r['listing_type'])?></span></div>
              <div class="text-[11px] text-[#54677A] mt-0.5 truncate"><?=h($r['title'])?></div>
              <div class="text-[10px] text-[#9AAAB8] mt-0.5 truncate"><?=h($r['location'])?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <!-- Dots -->
      <div class="flex justify-center gap-1.5 mt-3">
        <?php foreach ($_recent as $ri => $_): ?>
        <button onclick="slideTo(<?=$ri?>)" class="w-1 h-1 rounded-full transition-all <?=$ri===0?'bg-white w-3':'bg-white/30'?>" id="dot_<?=$ri?>"></button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Slogan -->
    <div class="relative p-12 pt-0 text-white">
      <h2 class="font-display text-3xl leading-tight mb-2">Сахалин и Курилы —<br>ближе, чем кажется</h2>
      <p class="text-white/60 text-sm">Жильё, туры, рыбалка и снаряжение — от местных</p>
    </div>
  </div>

  <!-- Form panel -->
  <div class="flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-sm">

      <!-- Mobile logo -->
      <div class="text-center mb-8 lg:hidden">
        <a href="/"><img src="/logo.png" alt="СахGO" class="h-12 w-auto mx-auto"></a>
      </div>

      <h1 class="font-display text-2xl mb-1">Вход в аккаунт</h1>
      <p class="text-sm text-[#9AAAB8] mb-7">Войдите, чтобы управлять объявлениями</p>

      <?php if ($error): ?>
      <div class="flex items-start gap-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2.5 mb-4 text-xs text-red-700">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0 mt-px"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
        <span><?= h($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="post" class="space-y-4">
        <?= csrf_field() ?>

        <div>
          <label>Email</label>
          <div class="relative">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9AAAB8]"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus class="w-full" style="padding-left:2.25rem">
          </div>
        </div>

        <div>
          <label>Пароль</label>
          <div class="relative">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9AAAB8]"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" name="password" id="pwField" required class="w-full" style="padding-left:2.25rem;padding-right:2.5rem">
            <button type="button" onclick="togglePw()" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9AAAB8] hover:text-[#54677A] transition-colors">
              <svg id="pwEye" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between text-xs">
          <label class="flex items-center gap-1.5 cursor-pointer text-[#54677A]">
            <input type="checkbox" name="remember" class="w-3.5 h-3.5 rounded border-[#DFE4EA] text-accent focus:ring-accent">
            Запомнить меня
          </label>
          <a href="/reset-password" class="text-accent font-medium hover:underline">Забыли пароль?</a>
        </div>

        <button type="submit" class="w-full bg-accent text-white rounded-lg h-11 text-sm font-semibold hover:bg-accent/90 transition-colors">
          Войти
        </button>
      </form>

      <div class="flex items-center gap-3 my-6">
        <div class="flex-1 h-px bg-[#EBEEF2]"></div>
        <span class="text-xs text-[#9AAAB8]">или</span>
        <div class="flex-1 h-px bg-[#EBEEF2]"></div>
      </div>

      <p class="text-center text-sm text-[#7A8A9A]">
        Нет аккаунта? <a href="/register" class="text-accent font-semibold hover:underline">Зарегистрироваться</a>
      </p>

    </div>
  </div>
</div>

<script>
function togglePw(){
  var f=document.getElementById('pwField');
  var e=document.getElementById('pwEye');
  if(f.type==='password'){f.type='text';e.innerHTML='<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';}
  else{f.type='password';e.innerHTML='<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>';}
}
</script>
<script>
var cardW = <?=$cardW?>;
var gap = <?=$gap?>;
var step = cardW + gap;
var total = <?=count($_recent)?>;
var maxIdx = <?=count($_recent) - 1?>;
var cIdx = 0;
var track = document.getElementById('loginCarousel');

function slideTo(i){
  cIdx = Math.max(0, Math.min(i, maxIdx));
  track.style.transform = 'translateX(-'+(cIdx*step)+'px)';
  // Update dots
  for(var j=0;j<total;j++){
    var d=document.getElementById('dot_'+j);
    if(d){
      d.className = 'w-1 h-1 rounded-full transition-all '+(j===cIdx?'bg-white w-3':'bg-white/30');
    }
  }
}

// Auto-scroll
setInterval(function(){
  cIdx = (cIdx + 1) % total;
  slideTo(cIdx);
}, 3000);

// Touch/swipe support
var startX = 0, startPos = 0;
track.addEventListener('touchstart',function(e){
  startX = e.touches[0].clientX;
  startPos = cIdx * step;
  track.style.transition = 'none';
});
track.addEventListener('touchmove',function(e){
  var dx = startX - e.touches[0].clientX;
  track.style.transform = 'translateX(-'+(startPos + dx)+'px)';
});
track.addEventListener('touchend',function(e){
  track.style.transition = 'transform 0.5s cubic-bezier(0.25,0.1,0.25,1)';
  var dx = startX - (e.changedTouches[0]||{}).clientX || 0;
  if(Math.abs(dx) > 40){
    cIdx = Math.max(0, Math.min(maxIdx, Math.round((startPos + dx) / step)));
  }
  slideTo(cIdx);
});
</script>
</body></html>
