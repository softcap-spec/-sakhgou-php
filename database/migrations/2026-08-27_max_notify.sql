-- Уведомления в «Макс» (max.ru): привязка аккаунта Макс к пользователю сайта
ALTER TABLE users
  ADD COLUMN max_user_id BIGINT NULL DEFAULT NULL AFTER phone,
  ADD COLUMN max_bind_code VARCHAR(16) NULL DEFAULT NULL AFTER max_user_id;

-- Александр (id 1) уже подтвердил свой аккаунт «Макс» (написал боту «сахгоу»)
UPDATE users SET max_user_id = 66240768 WHERE id = 1;

-- старый режим «один оператор» больше не используется
DELETE FROM settings WHERE setting_key = 'max_operator_user_id';
