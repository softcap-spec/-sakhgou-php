<?php
// reset_password.php — восстановление пароля: шаг 1 (запрос ссылки), шаг 2 (новый пароль по токену)
$error = '';
$success = false;
$pdo = db();

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$step2 = ($token !== '');

// ── Шаг 2: проверка токена и смена пароля ──
$tokenUser = null;
if ($step2) {
  $st = $pdo->prepare('SELECT id, email FROM users WHERE reset_token = ? AND reset_token_expires > NOW()');
  $st->execute([$token]);
  $tokenUser = $st->fetch();
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    csrf_check();
    if (!$tokenUser) {
      $error = 'Ссылка недействительна или истекла. Запросите сброс пароля заново.';
    } else {
      $new = $_POST['new_password'] ?? '';
      $new2 = $_POST['new_password2'] ?? '';
      if (mb_strlen($new) < 6) {
        $error = 'Пароль должен быть не менее 6 символов';
      } elseif ($new !== $new2) {
        $error = 'Пароли не совпадают';
      } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?')
          ->execute([$hash, $tokenUser['id']]);
        header('Location: /login?reset=ok');
        exit;
      }
    }
  }
}

// ── Шаг 1: запрос ссылки на сброс ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && !$step2) {
  csrf_check();
  $email = trim($_POST['email'] ?? '');
  if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Введите корректный email';
  } else {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
      $newToken = bin2hex(random_bytes(16));
      $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?')
        ->execute([$newToken, $user['id']]);
      $resetUrl = SITE_URL . '/reset-password?' . http_build_query(['token' => $newToken, 'step' => 2]);
      $subject = 'Сброс пароля — СахGO';
      $body = "Здравствуйте!\n\n"
        . "Вы запросили сброс пароля на СахGO.\n\n"
        . "Чтобы задать новый пароль, перейдите по ссылке (действительна 1 час):\n"
        . $resetUrl . "\n\n"
        . "Если вы не запрашивали сброс — просто проигнорируйте это письмо, пароль останется прежним.\n\n"
        . "С уважением,\nкоманда СахGO";
      send_mail_smtp($email, $subject, $body);
    }
    // Одинаковый ответ независимо от существования аккаунта (не раскрываем, кто зарегистрирован)
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
        <p style="font-size:0.8125rem;color:#7A8A9A;margin:0;line-height:1.5">Если аккаунт с таким email существует, ссылка для сброса пароля отправлена на почту. Ссылка действует 1 час.</p>
        <a href="/login" class="btn-outline" style="display:inline-flex;margin-top:1.5rem;gap:0.375rem">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
          Вернуться ко входу
        </a>
      </div>
    <?php elseif ($step2 && $tokenUser): ?>
      <div style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:2rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
        <h1 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.5rem;letter-spacing:-0.02em;margin:0 0 0.25rem">Новый пароль</h1>
        <p style="font-size:0.8125rem;color:#7A8A9A;margin:0 0 1.5rem">Задайте новый пароль для аккаунта <?= h($tokenUser['email']) ?>.</p>
        <?php if ($error): ?><div class="flash error"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= h($token) ?>">
          <div class="form-group">
            <label>Новый пароль</label>
            <input type="password" name="new_password" required minlength="6" autofocus style="width:100%;box-sizing:border-box">
          </div>
          <div class="form-group">
            <label>Повторите пароль</label>
            <input type="password" name="new_password2" required minlength="6" style="width:100%;box-sizing:border-box">
          </div>
          <button type="submit" class="cta-btn" style="width:100%;gap:0.375rem;padding:0.625rem">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Сохранить пароль
          </button>
        </form>
      </div>
    <?php elseif ($step2): ?>
      <div style="text-align:center;background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:2.5rem 2rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#C62828" stroke-width="1.5" style="margin-bottom:1.25rem">
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <h1 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.5rem;letter-spacing:-0.02em;margin:0 0 0.5rem">Ссылка недействительна</h1>
        <p style="font-size:0.8125rem;color:#7A8A9A;margin:0;line-height:1.5">Ссылка для сброса пароля истекла или уже была использована. Запросите новую.</p>
        <a href="/reset-password" class="btn-outline" style="display:inline-flex;margin-top:1.5rem;gap:0.375rem">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          Запросить сброс заново
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
