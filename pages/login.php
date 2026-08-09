<?php
// login.php — v4
if (isset($_SESSION['user_id'])) { header('Location: /dashboard'); exit; }

$error = '';
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

      <h1 class="font-display text-xl text-center mb-1">Вход в аккаунт</h1>
      <p class="text-xs text-[#9AAAB8] text-center mb-6">Войдите, чтобы управлять объявлениями</p>

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
            <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus class="w-full pl-9" style="padding-left:2.25rem">
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

    </div>

    <!-- Switch -->
    <p class="text-center text-sm text-[#7A8A9A] mt-5">
      Нет аккаунта? <a href="/register" class="text-accent font-semibold hover:underline">Зарегистрироваться</a>
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
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
