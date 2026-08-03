-- Titik awal yang sama untuk dua kondisi: database lama yang sudah berisi data,
-- dan database kosong yang dibangun dari nol.

CREATE TABLE IF NOT EXISTS tutors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    email TEXT,
    hourly_rate REAL,
    subjects TEXT,
    status TEXT,
    rating REAL
);

CREATE TABLE IF NOT EXISTS students (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    grade_level TEXT,
    budget_limit REAL
);

CREATE TABLE IF NOT EXISTS assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER,
    tutor_id INTEGER,
    weekly_hours INTEGER,
    status TEXT,
    created_at TEXT
);
