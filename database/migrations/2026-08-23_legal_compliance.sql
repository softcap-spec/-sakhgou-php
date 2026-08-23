-- ============================================================
-- 2026-08-23 — Юридические исправления (приведение к 152-ФЗ,
-- Закону «О рекламе» № 38-ФЗ, Закону № 132-ФЗ «Об основах
-- туристской деятельности», ГК РФ).
-- Выполнить ОДИН РАЗ на рабочей БД (rostpower_sakhgou).
-- Все изменения аддитивные (без удаления данных).
-- ============================================================

-- 1. Журнал согласий на обработку персональных данных (ст. 9 152-ФЗ)
CREATE TABLE IF NOT EXISTS `consents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `consent_type` varchar(50) NOT NULL DEFAULT 'pd_processing',
  `policy_version` varchar(20) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Полные реквизиты рекламодателя в рекламных блоках (ст. 8 ФЗ № 38-ФЗ)
ALTER TABLE `banners`
  ADD COLUMN `advertiser_ogrn` varchar(20) DEFAULT NULL,
  ADD COLUMN `advertiser_address` varchar(255) DEFAULT NULL;

-- 3. Статус организатора туров (ст. 4.1, 5, 10 ФЗ № 132-ФЗ)
ALTER TABLE `listings`
  ADD COLUMN `tour_organizer_type` varchar(30) DEFAULT NULL,
  ADD COLUMN `tour_operator_name` varchar(255) DEFAULT NULL,
  ADD COLUMN `tour_operator_regno` varchar(100) DEFAULT NULL;
