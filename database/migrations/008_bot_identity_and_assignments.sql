-- Migration 008: Telegram bot identity, canonical student→supporter assignments.
-- Adds supporter contact columns (only approved change to an existing table),
-- a Telegram link table, and the single canonical assignment table.
-- Legacy bot/report tables (bot_sessions, bot_ui_state, reports_status,
-- report_replies, report_attachments) are intentionally left untouched.

-- ---------------------------------------------------------------------------
-- 1) supporters: nullable phone + active flag (approved exception)
-- ---------------------------------------------------------------------------
ALTER TABLE supporters ADD COLUMN IF NOT EXISTS phone VARCHAR(15) NULL AFTER field;
ALTER TABLE supporters ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER chat_id;
ALTER TABLE supporters ADD INDEX IF NOT EXISTS idx_supporters_is_active (is_active);

-- Backfill supporters.phone from the linked users.username only when the
-- username normalizes to a valid Iranian mobile number. Digits are converted
-- from Persian/Arabic-Indic forms and common separators removed; prefixes
-- (+98 / 0098 / 98 / 9) are folded to the canonical 09XXXXXXXXX form.
-- The innermost expression performs 20 digit replacements + 6 separator
-- removals (26 REPLACE calls).
UPDATE supporters s
JOIN (
    SELECT linked_id AS supporter_id,
           CASE
               WHEN raw REGEXP '^0098[0-9]+$' THEN CONCAT('0', SUBSTRING(raw, 5))
               WHEN raw REGEXP '^\\+98[0-9]+$' THEN CONCAT('0', SUBSTRING(raw, 4))
               WHEN raw REGEXP '^98[0-9]+$' AND CHAR_LENGTH(raw) = 12 THEN CONCAT('0', SUBSTRING(raw, 3))
               WHEN raw REGEXP '^9[0-9]+$' AND CHAR_LENGTH(raw) = 10 THEN CONCAT('0', raw)
               ELSE raw
           END AS phone
    FROM (
        SELECT u.linked_id,
               REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
               REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
               REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
               u.username,
               '۰','0'),'۱','1'),'۲','2'),'۳','3'),'۴','4'),'۵','5'),'۶','6'),'۷','7'),'۸','8'),'۹','9'),
               '٠','0'),'١','1'),'٢','2'),'٣','3'),'٤','4'),'٥','5'),'٦','6'),'٧','7'),'٨','8'),'٩','9'),
               ' ',''),'-',''),'.',''),'(',''),')',''),'_','')
               AS raw
        FROM users u
        WHERE u.role = 'supporter' AND u.linked_id IS NOT NULL
    ) digits
) c ON c.supporter_id = s.id AND c.phone REGEXP '^09[0-9]{9}$'
SET s.phone = c.phone
WHERE s.phone IS NULL;

-- ---------------------------------------------------------------------------
-- 2) telegram_links: one link per (telegram user, role) and per (role, account)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS telegram_links (
    id INT(11) NOT NULL AUTO_INCREMENT,
    telegram_user_id BIGINT(20) NOT NULL,
    chat_id BIGINT(20) NOT NULL,
    role ENUM('student','supporter') NOT NULL,
    account_id INT(11) NOT NULL,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_telegram_role (telegram_user_id, role),
    UNIQUE KEY uk_role_account (role, account_id),
    KEY idx_telegram_chat (chat_id),
    KEY idx_telegram_blocked (role, is_blocked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- ---------------------------------------------------------------------------
-- 3) student_supporter_assignments: canonical assignment with full history.
-- active_student_id is a stored generated column that is NULL for inactive
-- rows, so the unique index enforces "at most one active assignment per
-- student" at the DB level while allowing unlimited historical rows.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS student_supporter_assignments (
    id INT(11) NOT NULL AUTO_INCREMENT,
    student_id INT(11) NOT NULL,
    supporter_id INT(11) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    assigned_by INT(11) DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deactivated_at DATETIME DEFAULT NULL,
    active_student_id INT(11) AS (CASE WHEN is_active = 1 THEN student_id ELSE NULL END) PERSISTENT,
    PRIMARY KEY (id),
    UNIQUE KEY uk_active_student (active_student_id),
    KEY idx_ssa_supporter_active (supporter_id, is_active),
    KEY idx_ssa_student_history (student_id, is_active),
    CONSTRAINT fk_ssa_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
    -- NOTE: no FK to supporters(id): the deployed supporters table has no
    -- PRIMARY KEY / UNIQUE index on id (and id is not AUTO_INCREMENT), so
    -- MariaDB cannot form the constraint. Enforcing supporter referential
    -- integrity is left to the service layer. See the module report.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
