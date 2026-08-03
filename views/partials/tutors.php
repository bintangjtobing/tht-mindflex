<?php
/**
 * @var list<\Mindflex\Model\Tutor> $tutors
 * @var int $tutorTotal
 * @var int $tutorPage
 * @var int $tutorLastPage
 * @var string $searchTerm
 * @var string $currency
 */
?>
<section class="panel">
    <h2>Tutors</h2>
    <p class="panel-hint">
        <?= e($tutorTotal) ?> tutors match your filter. Search runs on name, email, and subject.
    </p>

    <form method="GET" action="index.php" class="toolbar">
        <div class="field">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" value="<?= e($searchTerm) ?>" placeholder="Name, email, or subject">
        </div>
        <button type="submit">Search</button>
        <?php if ($searchTerm !== ''): ?>
            <a href="index.php">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($tutors === []): ?>
        <p class="empty">No tutor matches that search.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Subjects</th>
                <th class="numeric">Rate</th>
                <th class="numeric">Hours used</th>
                <th>Status</th>
                <th>Set new rate</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tutors as $tutor): ?>
                <tr>
                    <td>
                        <strong><?= e($tutor->name) ?></strong><br>
                        <small><?= e($tutor->email) ?></small><br>
                        <small>
                            <?= e(number_format($tutor->rating, 1)) ?>
                            <?= $tutor->hasReviews() ? 'from ' . e($tutor->reviewCount) . ' reviews' : '(imported score)' ?>
                        </small>
                    </td>
                    <td><?= e($tutor->subjectsAsText()) ?></td>
                    <td class="numeric"><?= e($tutor->hourlyRate->format($currency)) ?></td>
                    <td class="numeric">
                        <?= e($tutor->bookedWeeklyHours) ?> of <?= e($tutor->maxWeeklyHours) ?>
                    </td>
                    <td>
                        <span class="badge <?= $tutor->isActive() ? 'badge-active' : 'badge-inactive' ?>">
                            <?= e($tutor->status) ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" action="index.php" class="inline-form">
                            <?= \Mindflex\Support\Csrf::field() ?>
                            <input type="hidden" name="action" value="update_rate">
                            <input type="hidden" name="tutor_id" value="<?= e($tutor->id) ?>">
                            <input type="number" name="hourly_rate" step="0.01" min="5" max="1000"
                                   value="<?= e(number_format($tutor->hourlyRate->toDecimal(), 2, '.', '')) ?>"
                                   aria-label="New hourly rate for <?= e($tutor->name) ?>">
                            <button type="submit" class="small">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($tutorLastPage > 1): ?>
            <div class="pagination">
                <?php if ($tutorPage > 1): ?>
                    <a href="index.php?page=<?= e($tutorPage - 1) ?>&amp;search=<?= e(urlencode($searchTerm)) ?>">Previous</a>
                <?php endif; ?>
                <span>Page <?= e($tutorPage) ?> of <?= e($tutorLastPage) ?></span>
                <?php if ($tutorPage < $tutorLastPage): ?>
                    <a href="index.php?page=<?= e($tutorPage + 1) ?>&amp;search=<?= e(urlencode($searchTerm)) ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <h2 style="margin-top: 28px;">Add a tutor</h2>
    <p class="panel-hint">New tutors start with no rating. Ratings come from reviews on finished matches.</p>

    <form method="POST" action="index.php">
        <?= \Mindflex\Support\Csrf::field() ?>
        <input type="hidden" name="action" value="add_tutor">

        <div class="field">
            <label for="tutor_name">Full name</label>
            <input type="text" id="tutor_name" name="name" maxlength="120" required>
        </div>

        <div class="field">
            <label for="tutor_email">Email</label>
            <input type="email" id="tutor_email" name="email" required>
        </div>

        <div class="field">
            <label for="tutor_rate">Hourly rate</label>
            <input type="number" id="tutor_rate" name="hourly_rate" step="0.01" min="5" max="1000" required>
        </div>

        <div class="field">
            <label for="tutor_subjects">Subjects</label>
            <input type="text" id="tutor_subjects" name="subjects" placeholder="Math, Physics" required>
            <small class="field-hint">Separate each subject with a comma. New names join the catalog.</small>
        </div>

        <div class="field">
            <label for="tutor_max_hours">Maximum hours per week</label>
            <input type="number" id="tutor_max_hours" name="max_weekly_hours" min="1" max="60" value="40" required>
            <small class="field-hint">The system blocks matches once a tutor reaches this limit.</small>
        </div>

        <button type="submit" class="primary">Add tutor</button>
    </form>
</section>
