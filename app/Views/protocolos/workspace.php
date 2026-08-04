<?php declare(strict_types=1); $e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<section class="workspace" data-workspace data-base-path="<?= $e($basePath) ?>" data-csrf="<?= $e($csrfToken) ?>">
    <div class="protocol-tabs" role="tablist">
        <?php foreach ($tabs as $i => $tab): ?>
            <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-main-tab="<?= $e($tab['key']) ?>"><?= $e($tab['label']) ?></button>
        <?php endforeach; ?>
    </div>

    <section class="panel active" data-main-panel="familia">
        <?php require __DIR__ . '/familia/index.php'; ?>
    </section>

    <section class="panel" data-main-panel="empresas">
        <?php require __DIR__ . '/empresas/index.php'; ?>
    </section>

    <section class="panel" data-main-panel="accionistas">
        <?php require __DIR__ . '/accionistas/index.php'; ?>
    </section>

    <section class="panel" data-main-panel="patrimonio">
        <?php require __DIR__ . '/patrimonio/index.php'; ?>
    </section>

    <section class="panel" data-main-panel="documentos">
        <?php require __DIR__ . '/documentos/index.php'; ?>
    </section>

    <section class="panel" data-main-panel="decisiones">
        <?php require __DIR__ . '/decisiones/index.php'; ?>
    </section>

    <section class="panel" data-main-panel="riesgos">
        <?php require __DIR__ . '/riesgos/index.php'; ?>
    </section>

    <section class="panel" data-main-panel="informes">
        <?php require __DIR__ . '/informes/index.php'; ?>
    </section>

    <section class="panel" data-main-panel="academia_seguros">
        <?php require __DIR__ . '/academia_seguros/index.php'; ?>
    </section>

    <?php foreach (array_slice($tabs, 9) as $tab): ?>
        <section class="panel" data-main-panel="<?= $e($tab['key']) ?>">
            <div class="card center">
                <h2><?= $e($tab['label']) ?></h2>
                <p>Esta pestana queda reservada para la siguiente fase del desarrollo.</p>
            </div>
        </section>
    <?php endforeach; ?>
</section>
