-- Migration 014: admins can log into the Telegram bot, and one supporter can
-- cover multiple (field, grade) scopes. Additive and idempotent.
--
-- The legacy supporters.grade / supporters.field columns are kept for backward
-- compatibility but supporter_scopes is the source of truth for matching.

ALTER TABLE telegram_links MODIFY role ENUM('student','supporter','admin') NOT NULL;

CREATE TABLE IF NOT EXISTS supporter_scopes (
    id INT(11) NOT NULL AUTO_INCREMENT,
    supporter_id INT(11) NOT NULL,
    field VARCHAR(50) NOT NULL,
    grade INT(11) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_supporter_scope (supporter_id, field, grade),
    KEY idx_scope_field_grade (field, grade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

INSERT IGNORE INTO supporter_scopes (supporter_id, field, grade)
SELECT s.id, s.field, s.grade
FROM supporters s
WHERE s.grade IS NOT NULL AND s.field IS NOT NULL AND s.field <> '';
