<?php
/**
 * @var list<\Mindflex\Model\Assignment> $budgetRisks
 * @var string $currency
 */
?>
<?php if ($budgetRisks !== []): ?>
    <section class="panel">
        <h2>Budget risk</h2>
        <p class="panel-hint">
            These matches started before the budget rule existed. The system now blocks new matches
            that pass the student budget. Review each row and reduce the hours or cancel the match.
        </p>

        <table>
            <thead>
            <tr>
                <th>Student</th>
                <th>Tutor</th>
                <th>Subject</th>
                <th class="numeric">Weekly budget</th>
                <th class="numeric">Weekly cost</th>
                <th class="numeric">Over by</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($budgetRisks as $assignment): ?>
                <tr>
                    <td><?= e($assignment->studentName) ?></td>
                    <td><?= e($assignment->tutorName) ?></td>
                    <td><?= e($assignment->subjectLabel()) ?></td>
                    <td class="numeric"><?= e($assignment->studentWeeklyBudget->format($currency)) ?></td>
                    <td class="numeric"><?= e($assignment->weeklyCost()->format($currency)) ?></td>
                    <td class="numeric"><strong><?= e($assignment->budgetOverrun()->format($currency)) ?></strong></td>
                    <td>
                        <form method="POST" action="index.php" class="inline-form">
                            <?= \Mindflex\Support\Csrf::field() ?>
                            <input type="hidden" name="action" value="cancel_assignment">
                            <input type="hidden" name="assignment_id" value="<?= e($assignment->id) ?>">
                            <button type="submit" class="small">Cancel match</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>
