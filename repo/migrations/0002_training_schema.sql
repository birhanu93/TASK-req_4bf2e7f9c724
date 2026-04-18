-- Training sessions, bookings, leave/recurrence.

CREATE TABLE IF NOT EXISTS training_sessions (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    supervisor_id VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    capacity INT NOT NULL,
    buffer_minutes INT NOT NULL DEFAULT 10,
    status VARCHAR(16) NOT NULL DEFAULT 'open',
    recurrence_rule VARCHAR(64) NULL,
    recurrence_parent_id VARCHAR(64) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX ix_sessions_supervisor (supervisor_id),
    INDEX ix_sessions_status (status),
    INDEX ix_sessions_starts (starts_at),
    CONSTRAINT fk_sessions_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bookings (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    trainee_id VARCHAR(64) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'reserved',
    cancellation_reason VARCHAR(255) NULL,
    override_actor_id VARCHAR(64) NULL,
    idempotency_key VARCHAR(128) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX ix_bookings_session (session_id),
    INDEX ix_bookings_trainee (trainee_id),
    UNIQUE KEY ux_bookings_idempotency (idempotency_key),
    CONSTRAINT fk_bookings_session FOREIGN KEY (session_id) REFERENCES training_sessions(id),
    CONSTRAINT fk_bookings_trainee FOREIGN KEY (trainee_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enforce one active booking per trainee per session. Active statuses are
-- 'reserved' and 'confirmed'. MySQL 8 supports functional indexes so we
-- use a generated column to express the constraint portably.
ALTER TABLE bookings
    ADD COLUMN active_tag VARCHAR(8)
        GENERATED ALWAYS AS (
            CASE WHEN status IN ('reserved','confirmed') THEN 'A' ELSE NULL END
        ) STORED,
    ADD UNIQUE KEY ux_bookings_active (session_id, trainee_id, active_tag);

CREATE TABLE IF NOT EXISTS supervisor_leaves (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    supervisor_id VARCHAR(64) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    recurrence_rule VARCHAR(64) NULL,
    reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    INDEX ix_leaves_supervisor (supervisor_id),
    INDEX ix_leaves_range (starts_at, ends_at),
    CONSTRAINT fk_leaves_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
