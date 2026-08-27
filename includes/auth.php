<?php
/**
 * Аутентификация: регистрация, вход, выход
 */
require_once __DIR__ . '/db.php';

/**
 * Нормализация телефона: оставляем цифры, приводим к +7XXXXXXXXXX
 */
function normalize_phone(string $phone): string {
  $d = preg_replace('/\D/', '', $phone);
  if (strlen($d) === 11 && $d[0] === '8') $d = '7' . substr($d, 1);
  if (strlen($d) === 10) $d = '7' . $d;
  return $d;
}

/** Телефон для отображения: в БД хранится без «+», а пользователю показываем с «+». */
function phone_display(string $phone): string {
  $d = preg_replace('/\D/', '', $phone);
  if ($d === '') return $phone;
  if (strlen($d) === 11 && $d[0] === '8') $d = '7' . substr($d, 1);
  if (strlen($d) === 10) $d = '7' . $d;
  return '+' . $d;
}

/**
 * Проверка: корректный российский номер (+7/8 + 10 цифр)
 */
function valid_phone(string $phone): bool {
  return (bool)preg_match('/^7\d{10}$/', normalize_phone($phone));
}

function auth_register(string $email, string $password, string $name, string $phone): array {
  $pdo = db();
  $phone = normalize_phone($phone);
  if (!valid_phone($phone)) return ['ok' => false, 'error' => 'Укажите корректный номер телефона (например, +7 900 000-00-00)'];
  // Проверка существующего email
  $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
  $stmt->execute([$email]);
  if ($stmt->fetch()) return ['ok' => false, 'error' => 'Email уже занят'];
  // Проверка уникальности телефона
  $stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ?');
  $stmt->execute([$phone]);
  if ($stmt->fetch()) return ['ok' => false, 'error' => 'Этот номер телефона уже привязан к другому аккаунту'];
  
  $hash = password_hash($password, PASSWORD_BCRYPT);
  $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name, phone) VALUES (?, ?, ?, ?)');
  $stmt->execute([$email, $hash, $name, $phone]);
  
  $userId = (int) $pdo->lastInsertId();
  session_regenerate_id(true);
  $_SESSION['user_id'] = $userId;
  $_SESSION['user_role'] = 'user';
  return ['ok' => true, 'user_id' => $userId];
}

function auth_login(string $email, string $password): array {
  // Rate limiting: max 5 attempts per 15 minutes per IP
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  $key = 'login_attempts_' . md5($ip);
  $now = time();
  $attempts = $_SESSION[$key] ?? ['count' => 0, 'first' => $now];
  if ($now - $attempts['first'] > 900) { $attempts = ['count' => 0, 'first' => $now]; }
  if ($attempts['count'] >= 5) {
    return ['ok' => false, 'error' => 'Слишком много попыток. Попробуйте через 15 минут.'];
  }

  $pdo = db();
  $stmt = $pdo->prepare('SELECT id, password_hash, role, name FROM users WHERE email = ?');
  $stmt->execute([$email]);
  $user = $stmt->fetch();
  
  if (!$user || !password_verify($password, $user['password_hash'])) {
    $attempts['count']++;
    $_SESSION[$key] = $attempts;
    return ['ok' => false, 'error' => 'Неверный email или пароль'];
  }

  // Clear attempts on success
  unset($_SESSION[$key]);
  
  session_regenerate_id(true);
  $_SESSION['user_id'] = (int) $user['id'];
  $_SESSION['user_role'] = $user['role'];
  $_SESSION['user_name'] = $user['name'];
  return ['ok' => true, 'user' => $user];
}

function auth_logout(): void {
  session_destroy();
}

function auth_user(): ?array {
  if (empty($_SESSION['user_id'])) return null;
  $pdo = db();
  $stmt = $pdo->prepare('SELECT id, email, name, phone, role, avatar_url, created_at FROM users WHERE id = ?');
  $stmt->execute([$_SESSION['user_id']]);
  $u = $stmt->fetch() ?: null;
  if ($u) {
    // Update last_seen (throttled: every 60s)
    $ls = db()->prepare('SELECT last_seen FROM users WHERE id=?');
    $ls->execute([$u['id']]);
    $lsr = $ls->fetch();
    if (!$lsr['last_seen'] || (time() - strtotime($lsr['last_seen'])) > 60) {
      db()->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?')->execute([$u['id']]);
    }
    // S7 fix: cache unread notifications count in session (TTL 60s) to cut N+1 queries
    $ck = 'unread_cache_' . $u['id'];
    $now = time();
    if (isset($_SESSION[$ck]) && ($now - $_SESSION[$ck]['t']) < 60) {
      $u['unread_notifications'] = $_SESSION[$ck]['n'];
    } else {
      $cn = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
      $cn->execute([$u['id']]);
      $u['unread_notifications'] = (int)$cn->fetchColumn();
      $_SESSION[$ck] = ['t' => $now, 'n' => $u['unread_notifications']];
    }
  }
  return $u;
}

function auth_required(): array {
  $user = auth_user();
  if (!$user) { header('Location: /login'); exit; }
  // Обязательная привязка email и телефона: если чего-то нет — на страницу привязки
  $url = $_GET['url'] ?? '';
  $parts = $url ? explode('/', rtrim($url, '/')) : [];
  $page = $parts[0] ?? 'home';
  if ((empty($user['email']) || empty($user['phone'])) && !in_array($page, ['bind-contacts', 'logout', 'api'], true)) {
    header('Location: /bind-contacts'); exit;
  }
  return $user;
}

function admin_required(): array {
  $user = auth_required();
  if ($user['role'] !== 'admin') { header('Location: /'); exit; }
  return $user;
}

function auth_change_password(int $userId, string $current, string $new): array {
  if (mb_strlen($new) < 6) return ['ok' => false, 'error' => 'Новый пароль должен быть не менее 6 символов'];
  $pdo = db();
  $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
  $stmt->execute([$userId]);
  $user = $stmt->fetch();
  if (!$user || !password_verify($current, $user['password_hash'])) {
    return ['ok' => false, 'error' => 'Текущий пароль неверен'];
  }
  $hash = password_hash($new, PASSWORD_BCRYPT);
  $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
  return ['ok' => true];
}
