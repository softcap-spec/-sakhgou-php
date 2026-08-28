-- Robokassa: платежи за продвижение и рекламу
ALTER TABLE promotions
  ADD COLUMN inv_id BIGINT NULL DEFAULT NULL AFTER payment_amount,
  ADD COLUMN paid_at DATETIME NULL DEFAULT NULL AFTER inv_id;

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inv_id BIGINT NOT NULL,
  purpose VARCHAR(20) NOT NULL,
  target_id INT NULL,
  user_id INT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at DATETIME NULL,
  UNIQUE KEY uq_inv (inv_id),
  KEY idx_purpose (purpose, target_id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
