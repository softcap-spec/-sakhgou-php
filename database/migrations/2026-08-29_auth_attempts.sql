-- 2026-08-29: постоянный rate-limit входа (переживает смену cookie, в отличие от сессии)
CREATE TABLE IF NOT EXISTS auth_attempts (
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(190) NOT NULL DEFAULT '',
  attempts INT NOT NULL DEFAULT 0,
  first_at DATETIME NOT NULL,
  PRIMARY KEY (ip, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
