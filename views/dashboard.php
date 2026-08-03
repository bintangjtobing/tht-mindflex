<?php
/**
 * @var \Mindflex\Http\View $view
 */
?>
<?= $view->partial('partials/stats', get_defined_vars()) ?>
<?= $view->partial('partials/budget-risk', get_defined_vars()) ?>
<?= $view->partial('partials/matchmaking', get_defined_vars()) ?>
<?= $view->partial('partials/assignments', get_defined_vars()) ?>

<div class="grid-two">
    <?= $view->partial('partials/tutors', get_defined_vars()) ?>
    <?= $view->partial('partials/students', get_defined_vars()) ?>
</div>
