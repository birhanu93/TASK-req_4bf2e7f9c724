-- Core user, session, and bootstrap tables.

CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    username VARCHAR(128) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    roles VARCHAR(255) NOT NULL,
    encrypted_profile_blob LONGBLOB NULL,
    encrypted_profile_key_version INT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY ux_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auth_sessions (
    token CHAR(48) NOT NULL PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    active_role VARCHAR(32) NOT NULL,
    issued_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked TINYINT(1) NOT NULL DEFAULT 0,
    INDEX ix_auth_sessions_user (user_id),
    INDEX ix_auth_sessions_expires (expires_at),
    CONSTRAINT fk_auth_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bootstrap marker. Single-row table enforcing that the bootstrap admin
-- can only be created once. The unique constraint on `marker` combined
-- with INSERT IGNORE from application code provides the replay block.
CREATE TABLE IF NOT EXISTS system_state (
    marker VARCHAR(64) NOT NULL PRIMARY KEY,
    value VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS profile_keys (
    key_version INT NOT NULL PRIMARY KEY,
    wrapped_key_blob VARBINARY(255) NOT NULL,
    created_at DATETIME NOT NULL,
    retired_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
