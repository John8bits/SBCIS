USE sbcdb;

CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_key CHAR(64) PRIMARY KEY,
    failure_count INT UNSIGNED NOT NULL DEFAULT 0,
    first_failed_at DATETIME NOT NULL,
    last_failed_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    INDEX idx_login_attempts_cleanup (last_failed_at),
    INDEX idx_login_attempts_blocked (blocked_until)
) ENGINE=InnoDB;
