-- Migration 013: Telegram login handoff support.
-- Adds single-use nonces (widget payload replay + ticket jti), a fixed-window
-- throttle table, and server-bound 2FA challenges. Does not alter telegram_links.

CREATE TABLE IF NOT EXISTS telegram_auth_nonces (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    kind ENUM('payload','ticket') NOT NULL,
    nonce VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_kind_nonce (kind, nonce),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS auth_throttle (
    throttle_key VARCHAR(191) NOT NULL,
    attempts INT(11) NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    locked_until DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (throttle_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS telegram_2fa_challenges (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    challenge_nonce VARCHAR(64) NOT NULL,
    ticket_jti VARCHAR(64) NOT NULL,
    user_id INT(11) NOT NULL,
    attempts INT(11) NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_challenge_nonce (challenge_nonce),
    KEY idx_ticket_jti (ticket_jti),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
