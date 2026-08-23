<?php
// register.php — v5 split layout
if (isset($_SESSION['user_id'])) { header('Location: /'); exit; }

$errors = [];
$captcha_question = captcha_generate();
$_pdo = db();
$_recent = $_pdo->query('SELECT l.id, l.title, l.price, l.listing_type, l.location,
  (SELECT filename FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1) AS image,
  (p.id IS NOT NULL AND p.status = "active" AND p.expires_at > NOW()) AS is_promoted
  FROM listings l
  LEFT JOIN promotions p ON l.id = p.listing_id AND p.status = "active" AND p.expires_at > NOW()
  WHERE l.status = "active"
  ORDER BY is_promoted DESC, l.created_at DESC LIMIT 12')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $password2 = $_POST['password2'] ?? '';
  $phone = trim($_POST['phone'] ?? '');

  if (empty($name) || mb_strlen($name) < 2) $errors[] = 'Имя должно быть не короче 2 символов';
  if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Укажите корректный email';
  if (empty($phone)) $errors[] = 'Укажите номер телефона';
  elseif (!valid_phone($phone)) $errors[] = 'Укажите корректный номер телефона (например, +7 900 000-00-00)';
  if (mb_strlen($password) < 6) $errors[] = 'Пароль должен быть не короче 6 символов';
  if ($password !== $password2) $errors[] = 'Пароли не совпадают';
  if (!captcha_validate($_POST['captcha'] ?? '')) {
    $errors[] = 'Неверный ответ на проверочный вопрос';
    $captcha_question = captcha_generate(); // regenerate
  }
  if (empty($_POST['consent'])) {
    $errors[] = 'Для регистрации необходимо согласие на обработку персональных данных';
  }

  if (empty($errors)) {
    $result = auth_register($email, $password, $name, $phone);
    if ($result['ok']) {
      // Журнал согласия (ст. 9 ФЗ № 152-ФЗ): дата, время, редакция политики
      try {
        $_pdo->prepare('INSERT INTO consents (user_id, consent_type, policy_version, ip, user_agent, created_at) VALUES (?,?,?,?,?,NOW())')
          ->execute([$result['user_id'], 'pd_processing', '23.08.2026', $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
      } catch (\Throwable $e) { /* таблица consents недоступна — регистрацию не блокируем */ }
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
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="icon" type="image/png" sizes="256x256" href="/favicon.png">
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
    <?php if (!empty($_recent)): $cardW = 196; $gap = 14; $total = count($_recent); ?>
    <div class="relative flex-1 flex flex-col justify-center px-12 py-6">
      <p class="text-white/50 text-xs uppercase tracking-wider mb-3">Свежие объявления</p>
      <div class="relative overflow-hidden" id="carouselViewport">
        <div id="regCarousel" class="flex" style="gap:<?=$gap?>px">
          <?php for ($dup = 0; $dup < 2; $dup++): ?>
          <?php foreach ($_recent as $ri => $r): ?>
          <a href="/listing/<?=$r['id']?>" class="shrink-0 bg-white/95 backdrop-blur rounded-xl overflow-hidden hover:bg-white transition-colors relative" style="width:<?=$cardW?>px">
            <?php if ($r['is_promoted']): ?>
            <div class="absolute top-2 left-2 z-10 bg-accent text-white text-[9px] font-semibold px-1.5 py-0.5 rounded-md">Продвигается</div>
            <?php endif; ?>
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
          <?php endfor; ?>
        </div>
      </div>
      <div class="flex justify-center gap-1.5 mt-3">
        <?php foreach ($_recent as $ri => $r): ?>
        <button onclick="goTo(<?=$ri?>, false)" class="w-1 h-1 rounded-full transition-all <?=$ri===0?'bg-white w-3':'bg-white/30'?>" id="dot_<?=$ri?>"></button>
        <?php endforeach; ?>
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

      <form method="post" class="space-y-4" onsubmit="if(typeof ymGoal==='function')ymGoal('registration')">
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
            <input type="text" name="phone" value="<?= h($_POST['phone'] ?? '') ?>" placeholder="+7 (XXX) XXX-XX-XX" required class="w-full" style="padding-left:2.25rem">
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

        <div>
          <label><?=h($captcha_question)?> <span style="color:#DC2626">*</span></label>
          <div class="relative">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9AAAB8]"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <input type="text" name="captcha" required class="w-full" style="padding-left:2.25rem" placeholder="Введите число" autocomplete="off">
          </div>
        </div>

        <label class="flex items-start gap-2.5 text-xs text-[#7A8A9A] leading-relaxed cursor-pointer">
          <input type="checkbox" name="consent" value="1" class="mt-0.5 shrink-0" style="width:1rem;height:1rem;accent-color:#1B6B8A" <?= isset($_POST['consent']) ? 'checked' : '' ?>>
          <span>Я даю согласие на обработку моих персональных данных (имя, адрес электронной почты, номер телефона) оператором ООО «СахТур» в целях регистрации и авторизации на сайте, размещения и модерации объявлений, обмена сообщениями между пользователями и направления сервисных уведомлений, в соответствии с Федеральным законом от 27.07.2006 № 152-ФЗ «О персональных данных» и <a href="/privacy" class="text-accent hover:underline">Политикой конфиденциальности</a>. Согласие действует до момента его отзыва. Я ознакомлен(а) с Политикой конфиденциальности.</span>
        </label>

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
var cardW = <?=$cardW?>;
var gap = <?=$gap?>;
var step = cardW + gap;
var total = <?=$total?>;
var cIdx = 0;
var track = document.getElementById('regCarousel');
var animating = false;

function goTo(i, instant){
  if(animating && !instant) return;
  cIdx = i;
  var real = ((i % total) + total) % total;
  track.style.transition = instant ? 'none' : 'transform 0.5s cubic-bezier(0.25,0.1,0.25,1)';
  track.style.transform = 'translateX(-'+(i*step)+'px)';
  for(var j=0;j<total;j++){
    var d=document.getElementById('dot_'+j);
    if(d){
      d.className = 'w-1 h-1 rounded-full transition-all '+(j===real?'bg-white w-3':'bg-white/30');
    }
  }
  if(!instant) animating = true;
}

function onTransEnd(){
  animating = false;
  if(cIdx >= total * 2){
    cIdx = cIdx - total;
    goTo(cIdx, true);
  }
  if(cIdx < 0){
    cIdx = total + cIdx;
    goTo(cIdx, true);
  }
}
track.addEventListener('transitionend', onTransEnd);

setInterval(function(){
  cIdx++;
  goTo(cIdx, false);
  if(cIdx >= total * 2 - 1){
    setTimeout(function(){
      var ct = cIdx % total;
      track.style.transition = 'none';
      track.style.transform = 'translateX(-'+(ct*step)+'px)';
      cIdx = ct;
      animating = false;
    }, 550);
  }
}, 3000);

var startX = 0, startPos = 0;
track.addEventListener('touchstart',function(e){
  startX = e.touches[0].clientX;
  startPos = cIdx * step;
  track.style.transition = 'none';
  animating = false;
});
track.addEventListener('touchmove',function(e){
  var dx = startX - e.touches[0].clientX;
  track.style.transform = 'translateX(-'+(startPos + dx)+'px)';
});
track.addEventListener('touchend',function(e){
  var dx = startX - (e.changedTouches[0]||{}).clientX || 0;
  if(Math.abs(dx) > 40){
    cIdx = Math.round((startPos + dx) / step);
  }
  goTo(cIdx, false);
});

document.getElementById('carouselViewport').addEventListener('wheel',function(e){
  e.preventDefault();
  if(e.deltaX > 20 || e.deltaY > 20) goTo(cIdx+1, false);
  else if(e.deltaX < -20 || e.deltaY < -20) goTo(cIdx-1, false);
},{passive:false});
</script>
<?php require_once __DIR__ . '/../includes/cookie_consent.php'; ?>
<?php require_once __DIR__ . '/../includes/metrics_counter.php'; ?>
</body></html>
