<?php
// notify.php — внешние уведомления: «Макс» (max.ru, Max Bot API). Далее — Telegram.
// Секреты — только в config.php на сервере (MAX_BOT_TOKEN, MAX_WEBHOOK_SECRET), в git не попадают.
// Все функции «тихие»: сбой канала не должен ломать основной поток (бронь/чат).
// Маршрутизация: каждый пользователь получает ТОЛЬКО свои события (по привязке max_user_id).

/** Отправка текстового сообщения пользователю «Макс». */
function max_send(int $user_id, string $text): bool {
  $token = defined('MAX_BOT_TOKEN') ? MAX_BOT_TOKEN : '';
  if ($token === '' || $user_id <= 0 || $text === '') return false;
  $ch = curl_init('https://platform-api2.max.ru/messages?user_id=' . $user_id);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['text' => $text], JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => [
      'Authorization: ' . $token,
      'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => 5,
    CURLOPT_CONNECTTIMEOUT => 3,
  ]);
  $resp = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return $code >= 200 && $code < 300;
}

/** Имя бота «Макс» для показа пользователям (берётся из MAX_BOT_NAME в config.php). */
function max_bot_name(): string {
  return defined('MAX_BOT_NAME') ? MAX_BOT_NAME : 'СахGO';
}

/** Сохранить значение настройки (INSERT OR UPDATE). */
function set_setting(string $key, string $value): void {
  db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')
    ->execute([$key, $value]);
}

/** Макс-user_id пользователя сайта (0 — не привязан). */
function user_max_id(int $site_user_id): int {
  static $cache = [];
  if (array_key_exists($site_user_id, $cache)) return $cache[$site_user_id];
  $v = db()->prepare('SELECT max_user_id FROM users WHERE id = ?');
  $v->execute([$site_user_id]);
  $cache[$site_user_id] = (int)$v->fetchColumn();
  return $cache[$site_user_id];
}

/** Код привязки «Макс» для пользователя (создаётся при первом запросе). */
function max_bind_code(int $site_user_id): string {
  $v = db()->prepare('SELECT max_bind_code FROM users WHERE id = ?');
  $v->execute([$site_user_id]);
  $code = $v->fetchColumn();
  if ($code) return (string)$code;
  $code = (string)random_int(100000, 999999);
  db()->prepare('UPDATE users SET max_bind_code = ? WHERE id = ?')->execute([$code, $site_user_id]);
  return $code;
}

/** Уведомление: новая бронь → владельцу объявления. */
function max_notify_booking(int $host_id, string $listing_title, string $guest_name, string $dates, int $guests, string $total): void {
  $to = user_max_id($host_id);
  if ($to <= 0) return;
  $text = "Новая бронь на СахGO\n"
    . "Объявление: {$listing_title}\n"
    . "Гость: {$guest_name}\n"
    . "Даты: {$dates}, гостей: {$guests}\n"
    . "Итого: {$total}";
  max_send($to, $text);
}

/** Уведомление: бронь подтверждена/отклонена → гостю. */
function max_notify_decision(int $guest_id, string $listing_title, string $statusText): void {
  $to = user_max_id($guest_id);
  if ($to <= 0) return;
  max_send($to, "Бронь «{$listing_title}» {$statusText}.");
}

/** Уведомление: новое сообщение → получателю. */
function max_notify_message(int $receiver_id, string $listing_title, string $from_name, string $preview): void {
  $to = user_max_id($receiver_id);
  if ($to <= 0) return;
  $preview = trim(mb_substr($preview, 0, 120));
  $text = "Новое сообщение на СахGO\n"
    . "Объявление: {$listing_title}\n"
    . "От: {$from_name}";
  if ($preview !== '') $text .= "\n«{$preview}»";
  max_send($to, $text);
}
