CREATE TABLE IF NOT EXISTS moderation_items (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    author_id VARCHAR(64) NOT NULL,
    kind VARCHAR(16) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    checksum CHAR(64) NOT NULL,
    submitted_at DATETIME NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    reviewer_id VARCHAR(64) NULL,
    reason VARCHAR(512) NULL,
    quality_score INT NULL,
    UNIQUE KEY ux_moderation_checksum (checksum),
    INDEX ix_moderation_status (status),
    INDEX ix_moderation_author (author_id),
    CONSTRAINT fk_moderation_author FOREIGN KEY (author_id) REFERENCES users(id),
    CONSTRAINT fk_moderation_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS moderation_attachments (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    item_id VARCHAR(64) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(128) NOT NULL,
    size_bytes INT NOT NULL,
    checksum CHAR(64) NOT NULL,
    storage_path VARCHAR(512) NOT NULL,
    uploaded_at DATETIME NOT NULL,
    INDEX ix_attachments_item (item_id),
    CONSTRAINT fk_attachment_item FOREIGN KEY (item_id) REFERENCES moderation_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS guardian_links (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    guardian_id VARCHAR(64) NOT NULL,
    child_id VARCHAR(64) NOT NULL,
    linked_at DATETIME NOT NULL,
    UNIQUE KEY ux_guardian_link (guardian_id, child_id),
    INDEX ix_guardian_child (child_id),
    CONSTRAINT fk_link_guardian FOREIGN KEY (guardian_id) REFERENCES users(id),
    CONSTRAINT fk_link_child FOREIGN KEY (child_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS devices (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    device_name VARCHAR(128) NOT NULL,
    fingerprint VARCHAR(128) NOT NULL,
    approved_at DATETIME NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'approved',
    session_token VARCHAR(64) NULL,
    UNIQUE KEY ux_devices_fingerprint (user_id, fingerprint),
    INDEX ix_devices_user (user_id),
    CONSTRAINT fk_device_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS certificates (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    trainee_id VARCHAR(64) NOT NULL,
    rank_id VARCHAR(64) NOT NULL,
    verification_code CHAR(12) NOT NULL,
    pdf_path VARCHAR(512) NOT NULL,
    issued_at DATETIME NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    UNIQUE KEY ux_cert_verification (verification_code),
    INDEX ix_cert_trainee (trainee_id),
    CONSTRAINT fk_cert_trainee FOREIGN KEY (trainee_id) REFERENCES users(id),
    CONSTRAINT fk_cert_rank FOREIGN KEY (rank_id) REFERENCES ranks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
