<?php
// reset-password.php — форма сброса пароля
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Введите корректный email';
  } else {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
      // Generate reset token (simplified — in production, send email with link)
      $token = bin2hex(random_bytes(16));
      $stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?');
      $stmt->execute([$token, $user['id']]);
    }
    // Always show success (don't leak if email exists)
    $success = true;
  }
}

$page_title = 'Сброс пароля — СахGO';
require __DIR__ . '/../includes/header.php';
?>
<section class="py-16">
  <div class="max-w-md mx-auto px-4">
    <div class="bg-white border rounded-xl p-8">
      <?php if ($success): ?>
        <div class="text-center py-4">
          <div class="text-5xl mb-3">✅</div>
          <h1 class="font-display text-2xl mb-2">Инструкции отправлены</h1>
          <p class="text-sm text-muted-foreground">Если аккаунт с таким email существует, ссылка для сброса пароля отправлена на почту.</p>
          <a href="/login" class="btn-outline mt-4" style="display:inline-flex">Вернуться ко входу</a>
        </div>
      <?php else: ?>
        <h1 class="font-display text-2xl mb-2">Сброс пароля</h1>
        <p class="text-sm text-muted-foreground mb-6">Введите email — отправим ссылку для сброса пароля.</p>
        <?php if ($error): ?><div class="flash error"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
          </div>
          <button type="submit" class="cta-btn" style="width:100%">Отправить ссылку</button>
        </form>
        <p class="auth-footer"><a href="/login">← Назад ко входу</a></p>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
