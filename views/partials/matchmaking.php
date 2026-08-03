<?php
/**
 * @var list<\Mindflex\Model\Student> $students
 * @var list<array{id: int, name: string, slug: string, tutor_count: int}> $subjects
 * @var \Mindflex\Model\MatchResult|null $matchResult
 * @var int $matchStudentId
 * @var string $matchSubjectSlug
 * @var int $matchWeeklyHours
 * @var string $currency
 */
?>
<section class="panel">
    <h2>Matchmaking board</h2>
    <p class="panel-hint">
        Pick a student and a subject. The board ranks every eligible tutor and hides the ones who
        pass the remaining budget or have no free hours left.
    </p>

    <form method="GET" action="index.php" class="toolbar">
        <div class="field">
            <label for="match_student_id">Student</label>
            <select id="match_student_id" name="match_student_id" required>
                <option value="">Select a student</option>
                <?php foreach ($students as $student): ?>
                    <option value="<?= e($student->id) ?>" <?= $matchStudentId === $student->id ? 'selected' : '' ?>>
                        <?= e($student->name) ?> (<?= e($student->remainingWeeklyBudget()->format($currency)) ?> left)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="match_subject">Subject</label>
            <select id="match_subject" name="match_subject" required>
                <option value="">Select a subject</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= e($subject['slug']) ?>" <?= $matchSubjectSlug === $subject['slug'] ? 'selected' : '' ?>>
                        <?= e($subject['name']) ?> (<?= e($subject['tutor_count']) ?> tutors)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="match_hours">Hours per week</label>
            <input type="number" id="match_hours" name="match_hours" min="1" max="40" value="<?= e($matchWeeklyHours) ?>">
        </div>

        <button type="submit" class="primary">Find tutors</button>
    </form>

    <?php if ($matchResult === null): ?>
        <p class="empty">Run a search to see ranked tutors.</p>
    <?php elseif (! $matchResult->hasMatch()): ?>
        <div class="alert alert-warning"><?= e($matchResult->explainNoMatch()) ?></div>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Tutor</th>
                <th>Rating</th>
                <th class="numeric">Rate per hour</th>
                <th class="numeric">Weekly cost</th>
                <th class="numeric">Free hours</th>
                <th>Match score</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($matchResult->candidates as $candidate): ?>
                <tr>
                    <td>
                        <strong><?= e($candidate->tutor->name) ?></strong><br>
                        <small><?= e($candidate->tutor->subjectsAsText()) ?></small>
                    </td>
                    <td>
                        <?= e(number_format($candidate->tutor->rating, 1)) ?>
                        <br><small><?= $candidate->tutor->hasReviews()
                            ? e($candidate->tutor->reviewCount) . ' reviews'
                            : 'imported score' ?></small>
                    </td>
                    <td class="numeric"><?= e($candidate->tutor->hourlyRate->format($currency)) ?></td>
                    <td class="numeric"><?= e($candidate->weeklyCost->format($currency)) ?></td>
                    <td class="numeric"><?= e($candidate->tutor->remainingWeeklyHours()) ?></td>
                    <td>
                        <strong><?= e(number_format($candidate->score, 3)) ?></strong>
                        <span class="score-bar"><span style="width: <?= e((int) round($candidate->score * 100)) ?>%"></span></span>
                        <small>
                            rating <?= e($candidate->scoreBreakdown['rating']) ?>,
                            budget <?= e($candidate->scoreBreakdown['budget_fit']) ?>,
                            capacity <?= e($candidate->scoreBreakdown['capacity']) ?>
                        </small>
                    </td>
                    <td>
                        <form method="POST" action="index.php">
                            <?= \Mindflex\Support\Csrf::field() ?>
                            <input type="hidden" name="action" value="create_assignment">
                            <input type="hidden" name="student_id" value="<?= e($matchStudentId) ?>">
                            <input type="hidden" name="tutor_id" value="<?= e($candidate->tutor->id) ?>">
                            <input type="hidden" name="subject_id" value="<?= e($matchResult->subjectId) ?>">
                            <input type="hidden" name="weekly_hours" value="<?= e($matchResult->weeklyHours) ?>">
                            <button type="submit" class="primary small">Create match</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($matchResult->filteredOutTotal() > 0): ?>
            <p class="panel-hint">
                <?= e($matchResult->tutorsTeachingSubject) ?> tutors teach <?= e($matchResult->subjectName) ?>.
                The board hid <?= e($matchResult->filteredOut['over_budget'] ?? 0) ?> over budget,
                <?= e($matchResult->filteredOut['no_capacity'] ?? 0) ?> without free hours, and
                <?= e($matchResult->filteredOut['already_matched'] ?? 0) ?> already matched with this student.
            </p>
        <?php endif; ?>
    <?php endif; ?>
</section>
