-- Audit log partitioned monthly by occurred_at.
-- Initial partitions cover 2026-01 through 2027-01; new partitions must be
-- added each month by the maintenance job (bin/console maintain:partitions).

CREATE TABLE IF NOT EXISTS audit_log (
    id VARCHAR(64) NOT NULL,
    actor_id VARCHAR(64) NOT NULL,
    action VARCHAR(64) NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id VARCHAR(64) NOT NULL,
    occurred_at DATETIME NOT NULL,
    before_json JSON NULL,
    after_json JSON NULL,
    PRIMARY KEY (id, occurred_at),
    INDEX ix_audit_entity (entity_type, entity_id),
    INDEX ix_audit_actor (actor_id, occurred_at),
    INDEX ix_audit_action (action, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
PARTITION BY RANGE COLUMNS(occurred_at) (
    PARTITION p2026_01 VALUES LESS THAN ('2026-02-01'),
    PARTITION p2026_02 VALUES LESS THAN ('2026-03-01'),
    PARTITION p2026_03 VALUES LESS THAN ('2026-04-01'),
    PARTITION p2026_04 VALUES LESS THAN ('2026-05-01'),
    PARTITION p2026_05 VALUES LESS THAN ('2026-06-01'),
    PARTITION p2026_06 VALUES LESS THAN ('2026-07-01'),
    PARTITION p2026_07 VALUES LESS THAN ('2026-08-01'),
    PARTITION p2026_08 VALUES LESS THAN ('2026-09-01'),
    PARTITION p2026_09 VALUES LESS THAN ('2026-10-01'),
    PARTITION p2026_10 VALUES LESS THAN ('2026-11-01'),
    PARTITION p2026_11 VALUES LESS THAN ('2026-12-01'),
    PARTITION p2026_12 VALUES LESS THAN ('2027-01-01'),
    PARTITION p2027_01 VALUES LESS THAN ('2027-02-01'),
    PARTITION p_future VALUES LESS THAN (MAXVALUE)
);
