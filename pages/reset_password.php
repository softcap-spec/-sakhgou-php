<?php
// reset-password.php — v3 clean design
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $email = trim($_POST['email'] ?? '');
  if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Введите корректный email';
  } else {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
      $token = bin2hex(random_bytes(16));
      $stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?');
      $stmt->execute([$token, $user['id']]);

      // Send reset email
      $resetUrl = SITE_URL . '/reset-password?token=' . urlencode($token) . '&step=2';
      $subject = 'Сброс пароля — СахGO';
      $resetUrl = SITE_URL . '/reset-password?' . http_build_query(['token' => $token, 'step' => 2]);
      send_mail_smtp($email, $subject, $body);
    }
    $success = true;
  }
}

$page_title = 'Сброс пароля — СахGO';
require __DIR__ . '/../includes/header.php';
?>

<section style="padding:3rem 0 4rem">
  <div style="max-width:26rem;margin:0 auto;padding:0 1rem">
    <?php if ($success): ?>
      <div style="text-align:center;background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:2.5rem 2rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="1.5" style="margin-bottom:1.25rem">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        <h1 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.5rem;letter-spacing:-0.02em;margin:0 0 0.5rem">Инструкции отправлены</h1>
        <p style="font-size:0.8125rem;color:#7A8A9A;margin:0;line-height:1.5">Если аккаунт с таким email существует, ссылка для сброса пароля отправлена на почту.</p>
        <a href="/login" class="btn-outline" style="display:inline-flex;margin-top:1.5rem;gap:0.375rem">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
          Вернуться ко входу
        </a>
      </div>
    <?php else: ?>
      <div style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:2rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
        <h1 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.5rem;letter-spacing:-0.02em;margin:0 0 0.25rem">Сброс пароля</h1>
        <p style="font-size:0.8125rem;color:#7A8A9A;margin:0 0 1.5rem">Введите email &mdash; отправим ссылку для сброса пароля.</p>
        <?php if ($error): ?><div class="flash error"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus style="width:100%;box-sizing:border-box">
          </div>
          <button type="submit" class="cta-btn" style="width:100%;gap:0.375rem;padding:0.625rem">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
            Отправить ссылку
          </button>
        </form>
        <p class="auth-footer"><a href="/login">Назад ко входу</a></p>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
