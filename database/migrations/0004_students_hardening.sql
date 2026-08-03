-- Kolom `budget_limit` tidak menyebut periodenya. Dashboard menampilkannya sebagai
-- anggaran per minggu, jadi nama kolom dibuat eksplisit.

CREATE TABLE students_rebuilt (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    grade_level TEXT NOT NULL,
    weekly_budget_cents INTEGER NOT NULL CHECK (weekly_budget_cents >= 0),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

INSERT INTO students_rebuilt (id, name, grade_level, weekly_budget_cents, created_at, updated_at)
SELECT
    id,
    TRIM(COALESCE(NULLIF(TRIM(name), ''), 'Student ' || id)),
    TRIM(COALESCE(NULLIF(TRIM(grade_level), ''), 'Belum diisi')),
    MAX(0, CAST(ROUND(COALESCE(budget_limit, 0) * 100) AS INTEGER)),
    STRFTIME('%Y-%m-%d %H:%M:%S', 'now'),
    STRFTIME('%Y-%m-%d %H:%M:%S', 'now')
FROM students;

DROP TABLE students;

ALTER TABLE students_rebuilt RENAME TO students;

CREATE INDEX idx_students_name ON students (name);
