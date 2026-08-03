<?php
/**
 * @var list<\Mindflex\Model\Student> $students
 * @var string $currency
 */
?>
<section class="panel">
    <h2>Students</h2>
    <p class="panel-hint">Committed cost counts every pending and active match at its agreed rate.</p>

    <?php if ($students === []): ?>
        <p class="empty">No student is registered yet.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Grade</th>
                <th class="numeric">Weekly budget</th>
                <th class="numeric">Committed</th>
                <th class="numeric">Left</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td><strong><?= e($student->name) ?></strong></td>
                    <td><?= e($student->gradeLevel) ?></td>
                    <td class="numeric"><?= e($student->weeklyBudget->format($currency)) ?></td>
                    <td class="numeric"><?= e($student->committedWeeklyCost->format($currency)) ?></td>
                    <td class="numeric">
                        <?php if ($student->isOverBudget()): ?>
                            <span class="badge badge-inactive">
                                <?= e($student->remainingWeeklyBudget()->format($currency)) ?>
                            </span>
                        <?php else: ?>
                            <?= e($student->remainingWeeklyBudget()->format($currency)) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <h2 style="margin-top: 28px;">Register a student</h2>
    <p class="panel-hint">The weekly budget caps every match the system creates for this student.</p>

    <form method="POST" action="index.php">
        <?= \Mindflex\Support\Csrf::field() ?>
        <input type="hidden" name="action" value="add_student">

        <div class="field">
            <label for="student_name">Full name</label>
            <input type="text" id="student_name" name="name" maxlength="120" required>
        </div>

        <div class="field">
            <label for="student_grade">Grade level</label>
            <input type="text" id="student_grade" name="grade_level" placeholder="10th Grade" maxlength="40" required>
        </div>

        <div class="field">
            <label for="student_budget">Weekly budget</label>
            <input type="number" id="student_budget" name="weekly_budget" step="0.01" min="0" max="10000" required>
        </div>

        <button type="submit" class="primary">Register student</button>
    </form>
</section>
