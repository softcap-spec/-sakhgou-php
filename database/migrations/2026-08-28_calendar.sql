-- Календарь продавца: ручные записи и источник брони
ALTER TABLE bookings
  ADD COLUMN source VARCHAR(10) NOT NULL DEFAULT 'site' AFTER status,
  ADD COLUMN guest_name VARCHAR(120) NULL DEFAULT NULL AFTER guests_count,
  ADD COLUMN guest_phone VARCHAR(32) NULL DEFAULT NULL AFTER guest_name,
  MODIFY guest_id INT(11) NULL DEFAULT NULL;
