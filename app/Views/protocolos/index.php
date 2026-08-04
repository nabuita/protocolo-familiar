<?php declare(strict_types=1); $e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<section class="heading"><div><small>Gestión documental</small><h1>Protocolos familiares</h1></div><a class="button primary" href="<?= $e($basePath) ?>/protocolo-familiar/nuevo">Crear protocolo</a></section>
<section class="card">
<?php if ($protocolos === []): ?>
    <div class="center"><h2>Aún no hay protocolos</h2><p>Crea el primero para iniciar el versionado.</p></div>
<?php else: ?>
    <table><thead><tr><th>Código</th><th>Empresa</th><th>Título</th><th>Estado</th><th>Versión</th></tr></thead><tbody>
    <?php foreach ($protocolos as $p): ?><tr><td><a href="<?= $e($basePath) ?>/protocolo-familiar/<?= $e($p['id']) ?>"><?= $e($p['codigo']) ?></a></td><td><?= $e($p['empresa_nombre']) ?></td><td><?= $e($p['titulo']) ?></td><td><?= $e($p['estado']) ?></td><td>v<?= $e($p['version']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
<?php endif; ?>
</section>
