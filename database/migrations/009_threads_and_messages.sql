-- Migration 009: daily report threads and messages (Module 3).
-- New system of record for the Telegram bot. The legacy reports_status /
-- report_replies / report_attachments tables are intentionally NOT used.
--
-- Model: one thread per (student, Tehran calendar day). A thread snapshots the
-- supporter assigned on that day. Messages carry read state for the other side
-- and optional Telegram file-reference attachments. Timestamps are stored in
-- UTC; `day` is the Tehran calendar date.

CREATE TABLE IF NOT EXISTS report_threads (
    id INT(11) NOT NULL AUTO_INCREMENT,
    student_id INT(11) NOT NULL,
    day DATE NOT NULL,
    supporter_id INT(11) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_thread_student_day (student_id, day),
    KEY idx_thread_supporter (supporter_id),
    KEY idx_thread_day (day),
    CONSTRAINT fk_thread_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
    -- NOTE: no FK to supporters(id): the deployed supporters table has no
    -- PRIMARY KEY / UNIQUE on id (see migration 008 note).
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS report_messages (
    id INT(11) NOT NULL AUTO_INCREMENT,
    thread_id INT(11) NOT NULL,
    student_id INT(11) NOT NULL,
    sender_role ENUM('student','supporter','broadcast') NOT NULL,
    sender_account_id INT(11) DEFAULT NULL,
    body TEXT DEFAULT NULL,
    media_group_id VARCHAR(64) DEFAULT NULL,
    is_broadcast TINYINT(1) NOT NULL DEFAULT 0,
    read_by_student TINYINT(1) NOT NULL DEFAULT 0,
    read_by_supporter TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_msg_thread (thread_id, id),
    KEY idx_msg_student_supporter_read (student_id, read_by_supporter),
    KEY idx_msg_student_student_read (student_id, read_by_student),
    KEY idx_msg_created (created_at),
    CONSTRAINT fk_msg_thread FOREIGN KEY (thread_id) REFERENCES report_threads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS report_message_attachments (
    id INT(11) NOT NULL AUTO_INCREMENT,
    message_id INT(11) NOT NULL,
    kind ENUM('photo','document','voice','video','audio') NOT NULL,
    tg_file_id VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) DEFAULT NULL,
    mime_type VARCHAR(127) DEFAULT NULL,
    file_size BIGINT(20) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_att_message (message_id),
    CONSTRAINT fk_att_message FOREIGN KEY (message_id) REFERENCES report_messages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
