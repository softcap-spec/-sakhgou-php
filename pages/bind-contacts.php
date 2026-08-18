<?php
// bind-contacts.php — обязательная привязка email и телефона
$user = auth_required();
$pdo = db();

// если всё привязано — в кабинет
if (!empty($user['email']) && !empty($user['phone'])) {
  header('Location: /dashboard');
  exit;
}

$errors = [];
$email = trim($_POST['email'] ?? $user['email'] ?? '');
$phone = trim($_POST['phone'] ?? $user['phone'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $phoneNorm = normalize_phone($phone);

  // Email
  if (empty($user['email'])) {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'Укажите корректный email';
    } else {
      $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
      $chk->execute([$email, $user['id']]);
      if ($chk->fetch()) $errors[] = 'Этот email уже занят другим аккаунтом';
    }
  }

  // Телефон
  if (empty($user['phone'])) {
    if (!valid_phone($phoneNorm)) {
      $errors[] = 'Укажите корректный номер телефона (например, +7 900 000-00-00)';
    } else {
      $chk = $pdo->prepare('SELECT id FROM users WHERE phone = ? AND id != ?');
      $chk->execute([$phoneNorm, $user['id']]);
      if ($chk->fetch()) $errors[] = 'Этот номер телефона уже привязан к другому аккаунту';
    }
  }

  if (empty($errors)) {
    if (empty($user['email'])) {
      $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $user['id']]);
    }
    if (empty($user['phone'])) {
      $pdo->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$phoneNorm, $user['id']]);
    }
    header('Location: /dashboard');
    exit;
  }
}

$page_title = 'Привязка контактов — СахGO';
require __DIR__ . '/../includes/header.php';
?>

<main style="padding:3rem 0 4rem;min-height:60vh;display:flex;align-items:center">
<div style="max-width:30rem;margin:0 auto;padding:0 1rem;width:100%">

  <div style="text-align:center;margin-bottom:1.5rem">
    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#0A7BBA" stroke-width="1.5" style="margin:0 auto 0.75rem">
      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
    </svg>
    <h1 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.5rem;letter-spacing:-0.02em;margin:0;color:#0A1A2A">Привязка контактов</h1>
    <p style="font-size:0.8125rem;color:#5A6B7D;margin:0.375rem 0 0">Для использования сервиса укажите недостающие данные</p>
  </div>

  <?php if (!empty($errors)): ?>
  <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1rem">
    <?php foreach ($errors as $e): ?>
    <div style="font-size:0.8125rem;color:#DC2626"><?= h($e) ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="post" style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:2rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
    <?= csrf_field() ?>

    <?php if (empty($user['email'])): ?>
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" value="<?= h($email) ?>" placeholder="you@example.com" required style="width:100%;box-sizing:border-box">
    </div>
    <?php endif; ?>

    <?php if (empty($user['phone'])): ?>
    <div class="form-group">
      <label>Телефон</label>
      <input type="tel" name="phone" value="<?= h($phone) ?>" placeholder="+7 900 000-00-00" required style="width:100%;box-sizing:border-box">
    </div>
    <?php endif; ?>

    <button type="submit" class="cta-btn" style="width:100%;gap:0.375rem;padding:0.625rem 1.25rem;margin-top:0.5rem">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      Сохранить
    </button>
  </form>

  <div style="text-align:center;margin-top:1rem">
    <a href="/logout" style="font-size:0.8125rem;color:#5A6B7D;text-decoration:none">Выйти из аккаунта</a>
  </div>

</div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
