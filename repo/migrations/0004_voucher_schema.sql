CREATE TABLE IF NOT EXISTS vouchers (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    discount_cents INT NOT NULL,
    min_spend_cents INT NOT NULL,
    claim_limit INT NOT NULL,
    claimed INT NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY ux_vouchers_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS voucher_claims (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    voucher_id VARCHAR(64) NOT NULL,
    user_id VARCHAR(64) NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'locked',
    created_at DATETIME NOT NULL,
    redeemed_at DATETIME NULL,
    UNIQUE KEY ux_claims_idempotency (idempotency_key),
    INDEX ix_claims_voucher (voucher_id),
    INDEX ix_claims_user (user_id, status),
    CONSTRAINT fk_claim_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers(id),
    CONSTRAINT fk_claim_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One non-void claim per (voucher, user). Uses a generated column so the
-- uniqueness applies only to non-void claims.
ALTER TABLE voucher_claims
    ADD COLUMN active_tag VARCHAR(8)
        GENERATED ALWAYS AS (
            CASE WHEN status <> 'void' THEN 'A' ELSE NULL END
        ) STORED,
    ADD UNIQUE KEY ux_claims_active (voucher_id, user_id, active_tag);
