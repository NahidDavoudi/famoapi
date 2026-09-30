-- Migration 011: supporter broadcasts (Module 5).
-- A broadcast is frozen into one message per recipient (inside the student's
-- thread of the target day, labelled as broadcast) plus one outbox item per
-- recipient. Per-recipient delivery result is tracked here and kept in sync
-- when the bot reports outbox results.
--
-- Outbox items gain a generic soft reference (ref_type/ref_id) so a delivery
-- result can be reflected back onto its owning record without a hard FK.

ALTER TABLE bot_outbox ADD COLUMN IF NOT EXISTS ref_type VARCHAR(40) NULL AFTER payload_json;
ALTER TABLE bot_outbox ADD COLUMN IF NOT EXISTS ref_id INT(11) NULL AFTER ref_type;
ALTER TABLE bot_outbox ADD INDEX IF NOT EXISTS idx_outbox_ref (ref_type, ref_id);

CREATE TABLE IF NOT EXISTS report_broadcasts (
    id INT(11) NOT NULL AUTO_INCREMENT,
    supporter_id INT(11) NOT NULL,
    audience ENUM('no_report_today','all_students') NOT NULL,
    day DATE NOT NULL,
    body TEXT DEFAULT NULL,
    attachment_count INT(11) NOT NULL DEFAULT 0,
    recipient_count INT(11) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_broadcast_supporter (supporter_id, created_at)
    -- NOTE: no FK to supporters(id): the deployed supporters table has no
    -- PRIMARY KEY / UNIQUE on id (see migration 008 note).
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS report_broadcast_recipients (
    id INT(11) NOT NULL AUTO_INCREMENT,
    broadcast_id INT(11) NOT NULL,
    student_id INT(11) NOT NULL,
    message_id INT(11) DEFAULT NULL,
    outbox_id INT(11) DEFAULT NULL,
    status ENUM('pending','sent','failed','blocked') NOT NULL DEFAULT 'pending',
    telegram_message_id VARCHAR(64) DEFAULT NULL,
    error VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recipient_broadcast (broadcast_id),
    KEY idx_recipient_student (student_id),
    CONSTRAINT fk_recipient_broadcast FOREIGN KEY (broadcast_id) REFERENCES report_broadcasts (id) ON DELETE CASCADE,
    CONSTRAINT fk_recipient_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
