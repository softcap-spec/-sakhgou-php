<?php
// register.php — v2
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
<section class="min-h-[80vh] flex items-center justify-center py-16">
  <div class="w-full max-w-md mx-auto px-4">
    <div class="text-center mb-8">
      <a href="/"><img src="/logo.png" alt="СахGO" class="h-14 w-auto mx-auto mb-4"></a>
      <h1 class="font-display text-3xl">Присоединяйтесь</h1>
      <p class="text-sm text-muted-foreground mt-2">Создайте аккаунт за минуту</p>
    </div>
    <div class="bg-white border border-border/60 rounded-2xl p-8 shadow-[0_12px_40px_-10px_rgba(18,30,43,0.1)]">
      <?php foreach ($errors as $e): ?><div class="flash error"><?= h($e) ?></div><?php endforeach; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>Имя</label>
          <input type="text" name="name" value="<?= h($_POST['name'] ?? '') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Телефон</label>
          <input type="text" name="phone" value="<?= h($_POST['phone'] ?? '') ?>" placeholder="+7 (XXX) XXX-XX-XX">
        </div>
        <div class="form-group">
          <label>Пароль</label>
          <input type="password" name="password" required>
        </div>
        <div class="form-group">
          <label>Повторите пароль</label>
          <input type="password" name="password2" required>
        </div>
        <button type="submit" class="cta-btn" style="width:100%;height:3rem;font-size:1rem">Зарегистрироваться</button>
      </form>
      <p class="text-sm text-center mt-5 text-muted-foreground">Уже есть аккаунт? <a href="/login" class="text-accent font-semibold hover:underline">Войти</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
