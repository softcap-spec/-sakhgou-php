<?php
// register.php — compatible with auth.php
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
<section class="py-16">
  <div class="max-w-md mx-auto px-4">
    <div class="bg-white border rounded-xl p-8">
      <h1 class="font-display text-3xl text-center mb-8">Регистрация</h1>
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
        <button type="submit" class="cta-btn" style="width:100%">Зарегистрироваться</button>
      </form>
      <p class="auth-footer">Уже есть аккаунт? <a href="/login">Войти</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
