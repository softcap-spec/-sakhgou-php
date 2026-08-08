<?php
/**
 * Аутентификация: регистрация, вход, выход
 */
require_once __DIR__ . '/db.php';

function auth_register(string $email, string $password, string $name, string $phone = ''): array {
  $pdo = db();
  // Проверка существующего email
  $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
  $stmt->execute([$email]);
  if ($stmt->fetch()) return ['ok' => false, 'error' => 'Email уже занят'];
  
  $hash = password_hash($password, PASSWORD_BCRYPT);
  $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name, phone) VALUES (?, ?, ?, ?)');
  $stmt->execute([$email, $hash, $name, $phone]);
  
  $userId = (int) $pdo->lastInsertId();
  $_SESSION['user_id'] = $userId;
  $_SESSION['user_role'] = 'user';
  return ['ok' => true, 'user_id' => $userId];
}

function auth_login(string $email, string $password): array {
  $pdo = db();
  $stmt = $pdo->prepare('SELECT id, password_hash, role, name FROM users WHERE email = ?');
  $stmt->execute([$email]);
  $user = $stmt->fetch();
  
  if (!$user || !password_verify($password, $user['password_hash'])) {
    return ['ok' => false, 'error' => 'Неверный email или пароль'];
  }
  
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
  $stmt = $pdo->prepare('SELECT id, email, name, phone, role, created_at FROM users WHERE id = ?');
  $stmt->execute([$_SESSION['user_id']]);
  return $stmt->fetch() ?: null;
}

function auth_required(): array {
  $user = auth_user();
  if (!$user) { header('Location: /login'); exit; }
  return $user;
}

function admin_required(): array {
  $user = auth_required();
  if ($user['role'] !== 'admin') { header('Location: /'); exit; }
  return $user;
}
