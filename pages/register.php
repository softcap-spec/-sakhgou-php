<?php
// register.php — v4
if (isset($_SESSION['user_id'])) { header('Location: /'); exit; }

$errors = [];
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
require __DIR__ . '/../includes/header.php';
?>
<section class="min-h-[calc(100vh-4rem)] flex items-center justify-center py-12 px-4">
  <div class="w-full max-w-sm">

    <!-- Logo -->
    <div class="text-center mb-8">
      <a href="/"><img src="/logo.png" alt="СахGO" class="h-14 w-auto mx-auto"></a>
    </div>

    <!-- Card -->
    <div class="bg-white border border-[#EBEEF2] rounded-2xl p-8 shadow-[0_4px_24px_-8px_rgba(15,23,32,0.08)]">

      <h1 class="font-display text-xl text-center mb-1">Создать аккаунт</h1>
      <p class="text-xs text-[#9AAAB8] text-center mb-6">Регистрация займёт меньше минуты</p>

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

        <div>
          <label>Повторите пароль</label>
          <div class="relative">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9AAAB8]"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" name="password2" id="pwField2" required class="w-full" style="padding-left:2.25rem;padding-right:2.5rem">
            <button type="button" onclick="togglePw2()" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9AAAB8] hover:text-[#54677A] transition-colors">
              <svg id="pwEye2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="w-full bg-accent text-white rounded-lg h-11 text-sm font-semibold hover:bg-accent/90 transition-colors">
          Зарегистрироваться
        </button>
      </form>

    </div>

    <!-- Switch -->
    <p class="text-center text-sm text-[#7A8A9A] mt-5">
      Уже есть аккаунт? <a href="/login" class="text-accent font-semibold hover:underline">Войти</a>
    </p>

  </div>
</section>

<script>
function togglePw(){
  var f=document.getElementById('pwField');
  var e=document.getElementById('pwEye');
  if(f.type==='password'){f.type='text';e.innerHTML='<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';}
  else{f.type='password';e.innerHTML='<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>';}
}
function togglePw2(){
  var f=document.getElementById('pwField2');
  var e=document.getElementById('pwEye2');
  if(f.type==='password'){f.type='text';e.innerHTML='<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';}
  else{f.type='password';e.innerHTML='<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>';}
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
