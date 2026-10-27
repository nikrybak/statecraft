CREATE TABLE statecraft_audit (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    machine VARCHAR(191) NOT NULL,
    subject_id VARCHAR(191) NOT NULL,
    transition_name VARCHAR(191) NOT NULL,
    from_places JSONB NOT NULL,
    to_places JSONB NOT NULL,
    actor VARCHAR(191) NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    occurred_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL
);

CREATE INDEX statecraft_audit_subject ON statecraft_audit (machine, subject_id, id);
