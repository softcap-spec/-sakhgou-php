<?php
// login.php — with forgot password link
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
<section class="py-16">
  <div class="max-w-md mx-auto px-4">
    <div class="bg-white border rounded-xl p-8">
      <h1 class="font-display text-3xl text-center mb-8">Войти</h1>
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
        <button type="submit" class="cta-btn" style="width:100%">Войти</button>
      </form>
      <div class="flex justify-between mt-4 text-sm">
        <a href="/reset-password" class="text-muted-foreground hover:text-accent">Забыли пароль?</a>
        <a href="/register" class="text-accent font-medium">Регистрация →</a>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
