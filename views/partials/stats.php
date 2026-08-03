<?php
/**
 * @var \Mindflex\Model\DashboardStats $stats
 * @var string $currency
 */
?>
<section class="stat-grid">
    <div class="stat-card">
        <h3>Tutors</h3>
        <div class="value"><?= e($stats->totalTutors) ?></div>
        <div class="note"><?= e($stats->activeTutors) ?> active</div>
    </div>

    <div class="stat-card">
        <h3>Students</h3>
        <div class="value"><?= e($stats->totalStudents) ?></div>
        <div class="note">Registered accounts</div>
    </div>

    <div class="stat-card">
        <h3>Active matches</h3>
        <div class="value"><?= e($stats->activeAssignments) ?></div>
        <div class="note">Running this week</div>
    </div>

    <div class="stat-card">
        <h3>Weekly revenue</h3>
        <div class="value"><?= e($stats->weeklyRevenue->format($currency)) ?></div>
        <div class="note">Uses the rate agreed per match</div>
    </div>

    <div class="stat-card<?= $stats->assignmentsOverBudget > 0 ? ' flag' : '' ?>">
        <h3>Over budget</h3>
        <div class="value"><?= e($stats->assignmentsOverBudget) ?></div>
        <div class="note">Matches above the student budget</div>
    </div>

    <div class="stat-card<?= $stats->tutorsAtFullCapacity > 0 ? ' flag' : '' ?>">
        <h3>Tutors at capacity</h3>
        <div class="value"><?= e($stats->tutorsAtFullCapacity) ?></div>
        <div class="note">No free hours left this week</div>
    </div>
</section>
