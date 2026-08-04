<?php declare(strict_types=1); $e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<section class="login card">
    <h1>Ingreso</h1>
    <p>Acceso privado al sistema de Protocolo Familiar.</p>
    <?php if (is_string($error)): ?><div class="alert error"><?= $e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= $e($basePath) ?>/login" class="grid">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
        <label>Usuario<input name="username" required autocomplete="username"></label>
        <label>Contraseña<input name="password" type="password" required autocomplete="current-password"></label>
        <button class="primary">Entrar</button>
    </form>
</section>
