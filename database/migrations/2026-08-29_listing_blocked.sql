-- Расширение статуса объявлений: blocked (отключено администратором)
ALTER TABLE listings MODIFY status ENUM('active','pending','rejected','draft','archived','blocked') NOT NULL DEFAULT 'active';
