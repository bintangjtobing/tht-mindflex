-- Kolom `subjects` menyimpan teks "Math,Computer Science".
-- Pencarian memakai LIKE '%Science%' sehingga tutor Computer Science ikut terjaring
-- ketika student mencari Science. Katalog di bawah membuat pencocokan jadi persis.

CREATE TABLE subjects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL
);

CREATE UNIQUE INDEX idx_subjects_slug ON subjects (slug);

CREATE TABLE tutor_subjects (
    tutor_id INTEGER NOT NULL REFERENCES tutors (id) ON DELETE CASCADE,
    subject_id INTEGER NOT NULL REFERENCES subjects (id) ON DELETE CASCADE,
    PRIMARY KEY (tutor_id, subject_id)
);

CREATE INDEX idx_tutor_subjects_subject ON tutor_subjects (subject_id);

-- Pecah teks berkoma menjadi baris memakai recursive CTE.
INSERT INTO subjects (name, slug)
WITH RECURSIVE parsed (tutor_id, subject_name, remaining) AS (
    SELECT id, '', REPLACE(subjects, ', ', ',') || ','
    FROM tutors
    WHERE subjects IS NOT NULL AND TRIM(subjects) <> ''
    UNION ALL
    SELECT
        tutor_id,
        TRIM(SUBSTR(remaining, 1, INSTR(remaining, ',') - 1)),
        SUBSTR(remaining, INSTR(remaining, ',') + 1)
    FROM parsed
    WHERE remaining <> ''
)
SELECT MIN(subject_name), LOWER(subject_name)
FROM parsed
WHERE subject_name <> ''
GROUP BY LOWER(subject_name);

INSERT OR IGNORE INTO tutor_subjects (tutor_id, subject_id)
WITH RECURSIVE parsed (tutor_id, subject_name, remaining) AS (
    SELECT id, '', REPLACE(subjects, ', ', ',') || ','
    FROM tutors
    WHERE subjects IS NOT NULL AND TRIM(subjects) <> ''
    UNION ALL
    SELECT
        tutor_id,
        TRIM(SUBSTR(remaining, 1, INSTR(remaining, ',') - 1)),
        SUBSTR(remaining, INSTR(remaining, ',') + 1)
    FROM parsed
    WHERE remaining <> ''
)
SELECT parsed.tutor_id, subjects.id
FROM parsed
JOIN subjects ON subjects.slug = LOWER(parsed.subject_name)
WHERE parsed.subject_name <> '';
