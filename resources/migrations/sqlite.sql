CREATE TABLE statecraft_audit (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    machine TEXT NOT NULL,
    subject_id TEXT NOT NULL,
    transition_name TEXT NOT NULL,
    from_places TEXT NOT NULL,
    to_places TEXT NOT NULL,
    actor TEXT NULL,
    metadata TEXT NOT NULL DEFAULT '{}',
    occurred_at TEXT NOT NULL
);

CREATE INDEX statecraft_audit_subject ON statecraft_audit (machine, subject_id, id);
