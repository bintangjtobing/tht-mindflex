<?php
/**
 * @var string $content
 * @var string $pageTitle
 * @var string $adminUsername
 * @var array{type: string, message: string}|null $flash
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <div>
            <h1>Mindflex matchmaking admin</h1>
            <p>Match students with tutors, track weekly cost, and keep every match inside budget.</p>
        </div>
        <form method="POST" action="index.php" class="inline-form">
            <?= \Mindflex\Support\Csrf::field() ?>
            <input type="hidden" name="action" value="logout">
            <span>Signed in as <strong><?= e($adminUsername ?? 'admin') ?></strong></span>
            <button type="submit" class="small">Sign out</button>
        </form>
    </div>
</header>

<main class="shell">
    <?php if (isset($flash) && $flash !== null): ?>
        <div class="alert <?= $flash['type'] === 'error' ? 'alert-error' : 'alert-success' ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?= $content ?>
</main>

<script src="assets/app.js"></script>
</body>
</html>
