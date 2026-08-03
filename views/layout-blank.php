<?php
/**
 * @var string $content
 * @var string $pageTitle
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
<div class="login-shell">
    <?php if (isset($flash) && $flash !== null): ?>
        <div class="alert <?= $flash['type'] === 'error' ? 'alert-error' : 'alert-success' ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?= $content ?>
</div>
</body>
</html>
