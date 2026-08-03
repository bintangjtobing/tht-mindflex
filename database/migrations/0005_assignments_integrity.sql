-- Tiga perbaikan utama di tabel ini.
-- Pertama, `hourly_rate_cents` menyimpan tarif saat match dibuat. Tanpa itu, kenaikan
-- tarif tutor mengubah tagihan minggu lalu secara surut.
-- Kedua, status pindah dari '1' dan '2' menjadi nama yang terbaca.
-- Ketiga, foreign key menutup celah assignment yang menunjuk student atau tutor fiktif.

CREATE TABLE assignments_rebuilt (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL REFERENCES students (id) ON DELETE RESTRICT,
    tutor_id INTEGER NOT NULL REFERENCES tutors (id) ON DELETE RESTRICT,
    subject_id INTEGER REFERENCES subjects (id) ON DELETE SET NULL,
    weekly_hours INTEGER NOT NULL CHECK (weekly_hours BETWEEN 1 AND 40),
    hourly_rate_cents INTEGER NOT NULL CHECK (hourly_rate_cents > 0),
    status TEXT NOT NULL CHECK (status IN ('pending', 'active', 'completed', 'cancelled')),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

-- Baris yang menunjuk tutor atau student tidak ada akan tersaring oleh JOIN.
-- Baris seperti itu tidak punya tarif untuk disalin, jadi tidak bisa masuk skema baru.
INSERT INTO assignments_rebuilt (
    id, student_id, tutor_id, subject_id, weekly_hours,
    hourly_rate_cents, status, created_at, updated_at
)
SELECT
    assignments.id,
    assignments.student_id,
    assignments.tutor_id,
    NULL,
    MAX(1, MIN(40, COALESCE(assignments.weekly_hours, 1))),
    tutors.hourly_rate_cents,
    CASE TRIM(COALESCE(assignments.status, ''))
        WHEN '1' THEN 'active'
        WHEN '2' THEN 'completed'
        WHEN 'active' THEN 'active'
        WHEN 'completed' THEN 'completed'
        WHEN 'cancelled' THEN 'cancelled'
        ELSE 'pending'
    END,
    COALESCE(NULLIF(TRIM(assignments.created_at), ''), STRFTIME('%Y-%m-%d %H:%M:%S', 'now')),
    STRFTIME('%Y-%m-%d %H:%M:%S', 'now')
FROM assignments
JOIN tutors ON tutors.id = assignments.tutor_id
JOIN students ON students.id = assignments.student_id;

DROP TABLE assignments;

ALTER TABLE assignments_rebuilt RENAME TO assignments;

-- Satu pasangan student, tutor, dan mata pelajaran hanya boleh punya satu match berjalan.
CREATE UNIQUE INDEX idx_assignments_open_pair
ON assignments (student_id, tutor_id, COALESCE(subject_id, 0))
WHERE status IN ('pending', 'active');
