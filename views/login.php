<?php
/**
 * @var string $submittedUsername
 */
?>
<div class="panel">
    <h2>Sign in</h2>
    <p class="panel-hint">This dashboard holds tutor rates and student budgets. Sign in to continue.</p>

    <form method="POST" action="index.php">
        <?= \Mindflex\Support\Csrf::field() ?>
        <input type="hidden" name="action" value="login">

        <div class="field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= e($submittedUsername ?? '') ?>" autocomplete="username" required>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>

        <button type="submit" class="primary">Sign in</button>
    </form>

    <div class="demo-credentials">
        <p>Local demo account: <strong>admin</strong> with password <strong>mindflex-admin</strong>.</p>
        <p>Change it with <strong>php bin/console hash:password "your-password"</strong> and update ADMIN_PASSWORD_HASH.</p>
    </div>
</div>
