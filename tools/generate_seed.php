<?php

declare(strict_types=1);

/**
 * Skrip sekali pakai. Membaca mindflex.db lama lalu menulis ulang isinya
 * sebagai file seed SQL untuk skema baru.
 */

$legacyDatabasePath = dirname(__DIR__) . '/database/legacy/mindflex-legacy.db';
$outputPath = dirname(__DIR__) . '/database/seeds/0001_demo_data.sql';

$legacy = new PDO('sqlite:' . $legacyDatabasePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$quote = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";
$toCents = static fn (float $amount): int => (int) round($amount * 100);

$tutors = $legacy->query('SELECT * FROM tutors ORDER BY id')->fetchAll();
$students = $legacy->query('SELECT * FROM students ORDER BY id')->fetchAll();
$assignments = $legacy->query('SELECT * FROM assignments ORDER BY id')->fetchAll();

// Katalog mata pelajaran unik.
$subjectIdBySlug = [];
$subjectRows = [];

foreach ($tutors as $tutor) {
    foreach (explode(',', (string) $tutor['subjects']) as $rawSubject) {
        $subjectName = trim($rawSubject);

        if ($subjectName === '') {
            continue;
        }

        $slug = strtolower($subjectName);

        if (! isset($subjectIdBySlug[$slug])) {
            $subjectIdBySlug[$slug] = count($subjectIdBySlug) + 1;
            $subjectRows[] = [$subjectIdBySlug[$slug], $subjectName, $slug];
        }
    }
}

// Review dibuat untuk tutor yang punya assignment supaya rating punya dasar.
$reviewPlans = [
    1 => [5, 5, 5, 5, 4],
    2 => [5, 5, 4, 4],
    3 => [5, 5, 5, 5, 5, 5, 5, 5, 5, 4],
    4 => [5, 5, 5, 5, 5, 5, 5, 4, 4, 4],
];

$reviewComments = [
    'Penjelasan runtut dan mudah diikuti.',
    'Selalu datang tepat waktu.',
    'Nilai ujian anak saya naik dalam satu bulan.',
    'Materi latihan lengkap.',
    'Sabar menghadapi pertanyaan berulang.',
];

$lines = [];
$lines[] = '-- Data demo untuk skema baru.';
$lines[] = '-- File ini dihasilkan dari mindflex.db legacy, lalu dikonversi ke integer cent';
$lines[] = '-- dan katalog mata pelajaran ternormalisasi.';
$lines[] = '';
$lines[] = 'DELETE FROM tutor_reviews;';
$lines[] = 'DELETE FROM assignments;';
$lines[] = 'DELETE FROM tutor_subjects;';
$lines[] = 'DELETE FROM subjects;';
$lines[] = 'DELETE FROM students;';
$lines[] = 'DELETE FROM tutors;';
$lines[] = "DELETE FROM sqlite_sequence WHERE name IN ('tutors', 'students', 'assignments', 'subjects', 'tutor_reviews');";
$lines[] = '';

$lines[] = 'INSERT INTO subjects (id, name, slug) VALUES';
$subjectValues = [];
foreach ($subjectRows as [$id, $name, $slug]) {
    $subjectValues[] = sprintf('    (%d, %s, %s)', $id, $quote($name), $quote($slug));
}
$lines[] = implode(",\n", $subjectValues) . ';';
$lines[] = '';

$createdAt = '2026-05-01 08:00:00';

$lines[] = 'INSERT INTO tutors (id, name, email, hourly_rate_cents, max_weekly_hours, status, rating, review_count, created_at, updated_at) VALUES';
$tutorValues = [];
foreach ($tutors as $tutor) {
    $tutorId = (int) $tutor['id'];
    $scores = $reviewPlans[$tutorId] ?? [];
    $rating = $scores === []
        ? (float) $tutor['rating']
        : round(array_sum($scores) / count($scores), 2);

    $tutorValues[] = sprintf(
        '    (%d, %s, %s, %d, %d, %s, %s, %d, %s, %s)',
        $tutorId,
        $quote((string) $tutor['name']),
        $quote(strtolower((string) $tutor['email'])),
        $toCents((float) $tutor['hourly_rate']),
        40,
        $quote((string) $tutor['status']),
        number_format($rating, 2, '.', ''),
        count($scores),
        $quote($createdAt),
        $quote($createdAt)
    );
}
$lines[] = implode(",\n", $tutorValues) . ';';
$lines[] = '';

$lines[] = 'INSERT INTO tutor_subjects (tutor_id, subject_id) VALUES';
$pivotValues = [];
foreach ($tutors as $tutor) {
    $seen = [];
    foreach (explode(',', (string) $tutor['subjects']) as $rawSubject) {
        $subjectName = trim($rawSubject);
        $slug = strtolower($subjectName);

        if ($subjectName === '' || isset($seen[$slug])) {
            continue;
        }

        $seen[$slug] = true;
        $pivotValues[] = sprintf('    (%d, %d)', (int) $tutor['id'], $subjectIdBySlug[$slug]);
    }
}
$lines[] = implode(",\n", $pivotValues) . ';';
$lines[] = '';

$lines[] = 'INSERT INTO students (id, name, grade_level, weekly_budget_cents, created_at, updated_at) VALUES';
$studentValues = [];
foreach ($students as $student) {
    $studentValues[] = sprintf(
        '    (%d, %s, %s, %d, %s, %s)',
        (int) $student['id'],
        $quote((string) $student['name']),
        $quote((string) $student['grade_level']),
        $toCents((float) $student['budget_limit']),
        $quote($createdAt),
        $quote($createdAt)
    );
}
$lines[] = implode(",\n", $studentValues) . ';';
$lines[] = '';

// Mata pelajaran yang benar benar diajarkan pada tiap assignment.
$assignmentSubjectSlug = [1 => 'math', 2 => 'chemistry', 3 => 'math', 4 => 'english'];
$statusMap = ['1' => 'active', '2' => 'completed'];

$lines[] = '-- Assignment id 3 sengaja dipertahankan apa adanya. Biayanya $100 per minggu';
$lines[] = '-- sedangkan budget student hanya $60. Dashboard memakai baris ini untuk';
$lines[] = '-- menunjukkan panel risiko budget pada data warisan.';
$lines[] = 'INSERT INTO assignments (id, student_id, tutor_id, subject_id, weekly_hours, hourly_rate_cents, status, created_at, updated_at) VALUES';
$assignmentValues = [];
$tutorRateById = [];
foreach ($tutors as $tutor) {
    $tutorRateById[(int) $tutor['id']] = $toCents((float) $tutor['hourly_rate']);
}
foreach ($assignments as $assignment) {
    $assignmentId = (int) $assignment['id'];
    $tutorId = (int) $assignment['tutor_id'];
    $slug = $assignmentSubjectSlug[$assignmentId] ?? null;

    $assignmentValues[] = sprintf(
        '    (%d, %d, %d, %s, %d, %d, %s, %s, %s)',
        $assignmentId,
        (int) $assignment['student_id'],
        $tutorId,
        $slug === null ? 'NULL' : (string) $subjectIdBySlug[$slug],
        (int) $assignment['weekly_hours'],
        $tutorRateById[$tutorId],
        $quote($statusMap[(string) $assignment['status']] ?? 'pending'),
        $quote((string) $assignment['created_at']),
        $quote((string) $assignment['created_at'])
    );
}
$lines[] = implode(",\n", $assignmentValues) . ';';
$lines[] = '';

$lines[] = 'INSERT INTO tutor_reviews (tutor_id, assignment_id, score, comment, created_at) VALUES';
$reviewValues = [];
$commentIndex = 0;
foreach ($reviewPlans as $tutorId => $scores) {
    foreach ($scores as $score) {
        $reviewValues[] = sprintf(
            '    (%d, %d, %d, %s, %s)',
            $tutorId,
            $tutorId,
            $score,
            $quote($reviewComments[$commentIndex % count($reviewComments)]),
            $quote($createdAt)
        );
        $commentIndex++;
    }
}
$lines[] = implode(",\n", $reviewValues) . ';';
$lines[] = '';

file_put_contents($outputPath, implode("\n", $lines));

printf(
    'Seed ditulis ke %s (%d tutor, %d subject, %d student, %d assignment, %d review).%s',
    $outputPath,
    count($tutors),
    count($subjectRows),
    count($students),
    count($assignments),
    count($reviewValues),
    PHP_EOL
);
