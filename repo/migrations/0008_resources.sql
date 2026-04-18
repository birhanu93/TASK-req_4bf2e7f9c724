CREATE TABLE IF NOT EXISTS resources (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    kind VARCHAR(32) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    UNIQUE KEY ux_resources_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resource_reservations (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    resource_id VARCHAR(64) NOT NULL,
    session_id VARCHAR(64) NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    reserved_by_user_id VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX ix_rr_resource_window (resource_id, starts_at, ends_at),
    INDEX ix_rr_session (session_id),
    CONSTRAINT fk_rr_resource FOREIGN KEY (resource_id) REFERENCES resources(id),
    CONSTRAINT fk_rr_session FOREIGN KEY (session_id) REFERENCES training_sessions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
