-- 2026-08-27: уведомления для админки используют user_id = 0 (системные).
-- FK notifications_ibfk_1 (user_id -> users.id) запрещал вставку с user_id = 0:
-- «Cannot add or update a child row ... foreign key constraint fails»,
-- из-за чего уведомление о новом объявлении падало с ошибкой (публикация 500).
-- Админ-панель читает системные уведомления именно по user_id = 0,
-- поэтому ограничение удалено (очистка уведомлений выполняется явным DELETE).
ALTER TABLE `notifications` DROP FOREIGN KEY `notifications_ibfk_1`;
