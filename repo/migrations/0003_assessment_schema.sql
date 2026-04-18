CREATE TABLE IF NOT EXISTS assessment_templates (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    mode VARCHAR(16) NOT NULL,
    target_reps INT NOT NULL DEFAULT 0,
    target_seconds INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ranks (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    min_reps INT NOT NULL DEFAULT 0,
    min_seconds INT NOT NULL DEFAULT 0,
    display_order INT NOT NULL DEFAULT 0,
    INDEX ix_ranks_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessments (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    template_id VARCHAR(64) NOT NULL,
    trainee_id VARCHAR(64) NOT NULL,
    supervisor_id VARCHAR(64) NOT NULL,
    reps INT NOT NULL DEFAULT 0,
    seconds INT NOT NULL DEFAULT 0,
    recorded_at DATETIME NOT NULL,
    rank_achieved VARCHAR(64) NULL,
    INDEX ix_assessments_trainee (trainee_id, recorded_at),
    INDEX ix_assessments_supervisor (supervisor_id),
    CONSTRAINT fk_assess_template FOREIGN KEY (template_id) REFERENCES assessment_templates(id),
    CONSTRAINT fk_assess_trainee FOREIGN KEY (trainee_id) REFERENCES users(id),
    CONSTRAINT fk_assess_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id),
    CONSTRAINT fk_assess_rank FOREIGN KEY (rank_achieved) REFERENCES ranks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
