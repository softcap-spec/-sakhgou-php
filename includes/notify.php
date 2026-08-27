<?php
// notify.php — внешние уведомления: «Макс» (max.ru, Max Bot API), далее Telegram.
// Секреты — только в config.php на сервере (MAX_BOT_TOKEN, MAX_WEBHOOK_SECRET), в git не попадают.
// Все функции «тихие»: сбой канала не должен ломать основной поток (бронь/чат).

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

/** user_id оператора в «Максе» (кто написал боту первым — привязка на MVP). */
function max_operator_user_id(): int {
  static $id = null;
  if ($id !== null) return $id;
  $v = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'max_operator_user_id'")->fetchColumn();
  $id = $v ? (int)$v : 0;
  return $id;
}

/** Сохранить значение настройки (INSERT OR UPDATE). */
function set_setting(string $key, string $value): void {
  db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')
    ->execute([$key, $value]);
}

/** Уведомление: новая бронь (MVP — оператору). */
function max_notify_booking(string $listing_title, string $guest_name, string $dates, int $guests, string $total): void {
  $op = max_operator_user_id();
  if ($op <= 0) return;
  $text = "Новая бронь на СахGO\n"
    . "Объявление: {$listing_title}\n"
    . "Гость: {$guest_name}\n"
    . "Даты: {$dates}, гостей: {$guests}\n"
    . "Итого: {$total}";
  max_send($op, $text);
}

/** Уведомление: новое сообщение (MVP — оператору). */
function max_notify_message(string $listing_title, string $from_name, string $preview): void {
  $op = max_operator_user_id();
  if ($op <= 0) return;
  $preview = trim(mb_substr($preview, 0, 120));
  $text = "Новое сообщение на СахGO\n"
    . "Объявление: {$listing_title}\n"
    . "От: {$from_name}";
  if ($preview !== '') $text .= "\n«{$preview}»";
  max_send($op, $text);
}
