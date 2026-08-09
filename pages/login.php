<?php
// login.php — v3
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

$page_title = 'Войти — СахGO';
require __DIR__ . '/../includes/header.php';
?>
<section class="min-h-[75vh] flex items-center justify-center py-16">
  <div class="w-full max-w-sm mx-auto px-4">
    <div class="text-center mb-8">
      <a href="/"><img src="/logo.png" alt="СахGO" class="h-12 w-auto mx-auto mb-6"></a>
      <h1 class="font-display text-2xl">Вход</h1>
    </div>
    <div class="bg-white border border-[#EBEEF2] rounded-xl p-7">
      <?php if ($error): ?><div class="flash error"><?= h($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label>Пароль</label>
          <input type="password" name="password" required>
        </div>
        <button type="submit" class="cta-btn w-full" style="height:2.75rem">Войти</button>
      </form>
      <div class="flex justify-between mt-4 text-xs">
        <a href="/reset-password" class="text-[#7A8A9A] hover:text-accent transition-colors">Забыли пароль?</a>
        <a href="/register" class="text-accent font-medium hover:underline">Регистрация</a>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
