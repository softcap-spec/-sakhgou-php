-- 2026-08-27: типы цены — «От …» и «По договорённости»
-- fixed — точная цена, from — «от N ₽», negotiable — «По договорённости»
ALTER TABLE `listings`
  ADD COLUMN `price_type` enum('fixed','from','negotiable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed' AFTER `price`;
