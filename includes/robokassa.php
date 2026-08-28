<?php
// robokassa.php — приём платежей через Robokassa (продвижение, реклама)
// Секреты — только в config.php на сервере: RK_MERCHANT_LOGIN, RK_PASSWORD1, RK_PASSWORD2, RK_IS_TEST.
// Пока не заполнены — сайт работает по старой схеме (ручное подтверждение администратором).

const RK_URL = 'https://auth.robokassa.ru/Merchant/Index.aspx';

function rk_configured(): bool {
  return defined('RK_MERCHANT_LOGIN') && RK_MERCHANT_LOGIN !== ''
    && defined('RK_PASSWORD1') && RK_PASSWORD1 !== ''
    && defined('RK_PASSWORD2') && RK_PASSWORD2 !== '';
}

function rk_sign(array $parts): string {
  return strtoupper(md5(implode(':', $parts)));
}

/** Создать платёж (payments) и вернуть InvId. */
function rk_create_payment(float $amount, string $purpose, ?int $targetId, ?int $userId, string $description): int {
  $invId = (int)(microtime(true) * 1000);
  db()->prepare('INSERT INTO payments (inv_id, purpose, target_id, user_id, amount, description, status) VALUES (?,?,?,?,?,?,?)')
    ->execute([$invId, $purpose, $targetId, $userId, $amount, $description, 'pending']);
  return $invId;
}

/** URL оплаты Robokassa (редирект пользователя). */
function rk_pay_url(float $amount, int $invId, string $description): string {
  $login = RK_MERCHANT_LOGIN;
  $sig = rk_sign([$login, number_format($amount, 2, '.', ''), $invId, RK_PASSWORD1]);
  $q = http_build_query([
    'MerchantLogin' => $login,
    'OutSum' => number_format($amount, 2, '.', ''),
    'InvId' => $invId,
    'Description' => mb_substr($description, 0, 250),
    'SignatureValue' => $sig,
    'Encoding' => 'utf-8',
  ]);
  if (defined('RK_IS_TEST') && RK_IS_TEST) $q .= '&IsTest=1';
  return RK_URL . '?' . $q;
}

/** Проверка уведомления ResultURL (Пароль №2). Возвращает [invId, outSum] или false. */
function rk_verify_result(string $outSum, string $invId, string $signature): ?array {
  if ($outSum === '' || $invId === '' || $signature === '') return null;
  $sig = rk_sign([number_format((float)$outSum, 2, '.', ''), (int)$invId, RK_PASSWORD2]);
  if (!hash_equals($sig, strtoupper($signature))) return null;
  return [(int)$invId, (float)$outSum];
}

/** Проверка возврата пользователя SuccessURL (Пароль №1). */
function rk_verify_success(string $outSum, string $invId, string $signature): ?array {
  if ($outSum === '' || $invId === '' || $signature === '') return null;
  $sig = rk_sign([RK_MERCHANT_LOGIN, number_format((float)$outSum, 2, '.', ''), (int)$invId, RK_PASSWORD1]);
  if (!hash_equals($sig, strtoupper($signature))) return null;
  return [(int)$invId, (float)$outSum];
}

/** Активация оплаченного заказа: payments → paid + цель (промо/баннер). */
function rk_activate_payment(int $invId, float $amount): bool {
  $pdo = db();
  $st = $pdo->prepare('SELECT * FROM payments WHERE inv_id = ?');
  $st->execute([$invId]);
  $pay = $st->fetch();
  if (!$pay) return false;
  if ($pay['status'] === 'paid') return true; // идемпотентно
  if (abs((float)$pay['amount'] - $amount) > 0.01) return false; // сумма не совпала
  $pdo->prepare("UPDATE payments SET status='paid', paid_at=NOW() WHERE id=?")->execute([$pay['id']]);

  if ($pay['purpose'] === 'promo' && $pay['target_id']) {
    $pr = $pdo->prepare('SELECT * FROM promotions WHERE id = ?');
    $pr->execute([$pay['target_id']]);
    $promo = $pr->fetch();
    if ($promo) {
      $days = max(1, (int)round((strtotime($promo['expires_at']) - strtotime($promo['starts_at'])) / 86400));
      $pdo->prepare("UPDATE promotions SET status='active', payment_status='paid', inv_id=?, paid_at=NOW(), starts_at=NOW(), expires_at=DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id=?")
        ->execute([$invId, $days, $promo['id']]);
      $pdo->prepare('INSERT INTO notifications (user_id, type, text, link, is_read, created_at) VALUES (?,?,?,?,0,NOW())')
        ->execute([$pay['user_id'], 'promo', 'Оплата получена, продвижение активировано на ' . $days . ' дн.', '/dashboard']);
      $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
      foreach ($admins as $a) {
        $pdo->prepare('INSERT INTO notifications (user_id, type, text, link, is_read, created_at) VALUES (?,?,?,?,0,NOW())')
          ->execute([$a['id'], 'promo', 'Оплачено продвижение: ' . ($pay['description'] ?? ''), '/admin?tab=payments']);
      }
    }
  }
  if ($pay['purpose'] === 'banner' && $pay['target_id']) {
    $pdo->prepare("UPDATE banners SET is_active = 1 WHERE id = ?")->execute([$pay['target_id']]);
  }
  return true;
}
