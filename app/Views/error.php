<?php declare(strict_types=1); $e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<section class="card center"><h1>No fue posible continuar</h1><p><?= $e($message ?? 'Error') ?></p></section>
