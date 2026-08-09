<?php
// login.php — v2
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
<section class="min-h-[80vh] flex items-center justify-center py-16">
  <div class="w-full max-w-md mx-auto px-4">
    <div class="text-center mb-8">
      <a href="/"><img src="/logo.png" alt="СахGO" class="h-14 w-auto mx-auto mb-4"></a>
      <h1 class="font-display text-3xl">С возвращением</h1>
      <p class="text-sm text-muted-foreground mt-2">Войдите чтобы управлять объявлениями</p>
    </div>
    <div class="bg-white border border-border/60 rounded-2xl p-8 shadow-[0_12px_40px_-10px_rgba(18,30,43,0.1)]">
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
        <button type="submit" class="cta-btn" style="width:100%;height:3rem;font-size:1rem">Войти</button>
      </form>
      <div class="flex justify-between mt-5 text-sm">
        <a href="/reset-password" class="text-muted-foreground hover:text-accent transition-colors">Забыли пароль?</a>
        <a href="/register" class="text-accent font-semibold hover:underline">Регистрация →</a>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
