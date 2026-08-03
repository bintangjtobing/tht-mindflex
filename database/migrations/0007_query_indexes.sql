-- Dashboard menghitung jam aktif per tutor dan komitmen per student pada setiap
-- pemuatan halaman. Index berikut menutup query tersebut.

CREATE INDEX idx_assignments_tutor_status ON assignments (tutor_id, status);
CREATE INDEX idx_assignments_student_status ON assignments (student_id, status);
CREATE INDEX idx_assignments_status ON assignments (status);
CREATE INDEX idx_assignments_subject ON assignments (subject_id);
CREATE INDEX idx_tutors_rate ON tutors (hourly_rate_cents);
