-- Migration 010: unified Telegram outbox (Module 4).
-- The main host cannot reach Telegram; everything that must be delivered is
-- written here as an outbox item. The external bot host claims items in small
-- batches (locked so two workers never take the same row), sends them, then
-- reports the result back (sent / failed / blocked).
--
-- Nothing here is coupled to a queue/Redis: the outbox lives in MySQL.

CREATE TABLE IF NOT EXISTS bot_outbox (
    id INT(11) NOT NULL AUTO_INCREMENT,
    kind ENUM('student_report','supporter_reply','broadcast') NOT NULL,
    recipient_role ENUM('student','supporter') NOT NULL,
    recipient_account_id INT(11) NOT NULL,
    telegram_user_id BIGINT(20) DEFAULT NULL,
    chat_id BIGINT(20) DEFAULT NULL,
    status ENUM('pending','processing','sent','failed','skipped') NOT NULL DEFAULT 'pending',
    payload_json MEDIUMTEXT NOT NULL,
    dedup_key VARCHAR(160) DEFAULT NULL,
    attempts INT(11) NOT NULL DEFAULT 0,
    max_attempts INT(11) NOT NULL DEFAULT 3,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_by VARCHAR(64) DEFAULT NULL,
    locked_at DATETIME DEFAULT NULL,
    telegram_message_id VARCHAR(64) DEFAULT NULL,
    error VARCHAR(500) DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_outbox_dedup (dedup_key),
    KEY idx_outbox_claim (status, available_at, id),
    KEY idx_outbox_locked (status, locked_at),
    KEY idx_outbox_recipient (recipient_role, recipient_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
