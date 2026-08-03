-- Form lama menerima `rating` langsung dari input. Siapa pun bisa mendaftar dengan
-- rating 5.0 lalu naik ke urutan atas hasil pencocokan.
-- Rating sekarang dihitung dari review yang terikat ke assignment.

CREATE TABLE tutor_reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tutor_id INTEGER NOT NULL REFERENCES tutors (id) ON DELETE CASCADE,
    assignment_id INTEGER REFERENCES assignments (id) ON DELETE SET NULL,
    score INTEGER NOT NULL CHECK (score BETWEEN 1 AND 5),
    comment TEXT,
    created_at TEXT NOT NULL
);

CREATE INDEX idx_tutor_reviews_tutor ON tutor_reviews (tutor_id);
