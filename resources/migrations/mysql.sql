CREATE TABLE statecraft_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    machine VARCHAR(191) NOT NULL,
    subject_id VARCHAR(191) NOT NULL,
    transition_name VARCHAR(191) NOT NULL,
    from_places JSON NOT NULL,
    to_places JSON NOT NULL,
    actor VARCHAR(191) NULL,
    metadata JSON NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    INDEX statecraft_audit_subject (machine, subject_id, id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
