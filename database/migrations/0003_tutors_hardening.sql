-- Tarif pindah ke integer cent, status dikunci daftar nilai, email jadi unik,
-- dan tutor punya batas jam mingguan supaya sistem bisa menolak beban berlebih.

CREATE TABLE tutors_rebuilt (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    hourly_rate_cents INTEGER NOT NULL CHECK (hourly_rate_cents > 0),
    max_weekly_hours INTEGER NOT NULL DEFAULT 40 CHECK (max_weekly_hours BETWEEN 1 AND 60),
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    rating REAL NOT NULL DEFAULT 0 CHECK (rating >= 0 AND rating <= 5),
    review_count INTEGER NOT NULL DEFAULT 0 CHECK (review_count >= 0),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

INSERT INTO tutors_rebuilt (
    id, name, email, hourly_rate_cents, max_weekly_hours,
    status, rating, review_count, created_at, updated_at
)
SELECT
    id,
    TRIM(COALESCE(NULLIF(TRIM(name), ''), 'Tutor ' || id)),
    LOWER(TRIM(COALESCE(NULLIF(TRIM(email), ''), 'tutor' || id || '@placeholder.invalid'))),
    MAX(1, CAST(ROUND(COALESCE(hourly_rate, 0) * 100) AS INTEGER)),
    40,
    CASE WHEN LOWER(TRIM(COALESCE(status, 'active'))) = 'inactive' THEN 'inactive' ELSE 'active' END,
    MIN(5.0, MAX(0.0, COALESCE(rating, 0))),
    0,
    STRFTIME('%Y-%m-%d %H:%M:%S', 'now'),
    STRFTIME('%Y-%m-%d %H:%M:%S', 'now')
FROM tutors;

DROP TABLE tutors;

ALTER TABLE tutors_rebuilt RENAME TO tutors;

CREATE UNIQUE INDEX idx_tutors_email ON tutors (LOWER(email));
CREATE INDEX idx_tutors_status ON tutors (status);
