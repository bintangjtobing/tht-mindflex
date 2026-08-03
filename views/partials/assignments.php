<?php
/**
 * @var list<\Mindflex\Model\Assignment> $assignments
 * @var string $currency
 */
?>
<section class="panel">
    <h2>Matches</h2>
    <p class="panel-hint">
        Each match keeps the rate agreed on the day it started. When a tutor changes their rate,
        the table shows the difference instead of rewriting past cost.
    </p>

    <?php if ($assignments === []): ?>
        <p class="empty">No match exists yet. Use the matchmaking board above to create the first one.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
            <thead>
            <tr>
                <th>Student</th>
                <th>Tutor</th>
                <th>Subject</th>
                <th class="numeric">Hours</th>
                <th class="numeric">Agreed rate</th>
                <th class="numeric">Weekly cost</th>
                <th>Status</th>
                <th>Started</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($assignments as $assignment): ?>
                <tr>
                    <td><?= e($assignment->studentName) ?></td>
                    <td><?= e($assignment->tutorName) ?></td>
                    <td><?= e($assignment->subjectLabel()) ?></td>
                    <td class="numeric"><?= e($assignment->weeklyHours) ?></td>
                    <td class="numeric">
                        <?= e($assignment->agreedHourlyRate->format($currency)) ?>
                        <?php if ($assignment->tutorRateHasChanged()): ?>
                            <small class="rate-drift">
                                Tutor now charges <?= e($assignment->currentTutorHourlyRate->format($currency)) ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td class="numeric">
                        <?= e($assignment->weeklyCost()->format($currency)) ?>
                        <?php if ($assignment->exceedsStudentBudget()): ?>
                            <small class="rate-drift">
                                Over budget by <?= e($assignment->budgetOverrun()->format($currency)) ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= e($assignment->status->cssClass()) ?>">
                            <?= e($assignment->status->label()) ?>
                        </span>
                    </td>
                    <td><?= e(\Mindflex\Support\Clock::forDisplay($assignment->createdAt)) ?></td>
                    <td>
                        <?php if ($assignment->status->canBeCompleted()): ?>
                            <form method="POST" action="index.php" class="inline-form">
                                <?= \Mindflex\Support\Csrf::field() ?>
                                <input type="hidden" name="action" value="complete_assignment">
                                <input type="hidden" name="assignment_id" value="<?= e($assignment->id) ?>">
                                <button type="submit" class="small">Complete</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($assignment->status->canBeCancelled()): ?>
                            <form method="POST" action="index.php" class="inline-form">
                                <?= \Mindflex\Support\Csrf::field() ?>
                                <input type="hidden" name="action" value="cancel_assignment">
                                <input type="hidden" name="assignment_id" value="<?= e($assignment->id) ?>">
                                <button type="submit" class="small">Cancel</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
