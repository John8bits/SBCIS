-- Apply once to the selected SBCIS database. Existing accounts are unchanged.
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    token_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reset_admin (admin_id, used_at),
    INDEX idx_reset_expiry (expires_at),
    CONSTRAINT fk_reset_admin FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_requests (
    request_key CHAR(64) PRIMARY KEY,
    last_requested_at DATETIME NOT NULL
) ENGINE=InnoDB;
