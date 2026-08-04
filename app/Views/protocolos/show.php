<?php declare(strict_types=1); $e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<section class="heading">
    <div>
        <a href="<?= $e($basePath) ?>/protocolo-familiar">Volver</a>
        <h1><?= $e($protocolo['titulo']) ?></h1>
        <p><?= $e($protocolo['empresa_nombre']) ?> · <?= $e($protocolo['estado']) ?> · v<?= $e($protocolo['version']) ?></p>
    </div>
</section>
<section class="card">
    <div class="split">
        <h2>Secciones</h2>
        <span class="muted"><?= count($protocolo['secciones']) ?> pestañas</span>
    </div>
    <form method="post" action="<?= $e($basePath) ?>/protocolo-familiar/<?= $e($protocolo['id']) ?>/secciones" data-protocol-form>
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
        <div class="tabs" data-tabs>
            <div class="tab-list" role="tablist">
                <?php foreach ($protocolo['secciones'] as $i => $s): ?>
                    <button type="button" role="tab" data-tab-target="section-<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>"><?= $e((string) $s['orden']) ?></button>
                <?php endforeach; ?>
            </div>
            <div data-sections data-next-index="<?= count($protocolo['secciones']) ?>">
                <?php foreach ($protocolo['secciones'] as $i => $s): ?>
                    <article class="section tab-panel <?= $i === 0 ? 'active' : '' ?>" id="section-<?= $i ?>" data-section>
                        <div class="split"><strong>Seccion <?= $i + 1 ?></strong><button type="button" data-remove-section>Quitar</button></div>
                        <div class="cols">
                            <label>Clave<input name="secciones[<?= $i ?>][clave]" value="<?= $e($s['clave']) ?>" required></label>
                            <label>Titulo<input name="secciones[<?= $i ?>][titulo]" value="<?= $e($s['titulo']) ?>" required></label>
                            <label>Orden<input type="number" name="secciones[<?= $i ?>][orden]" value="<?= $e($s['orden']) ?>"></label>
                        </div>
                        <label>Contenido<textarea name="secciones[<?= $i ?>][contenido]" rows="8" required><?= $e($s['contenido']) ?></textarea></label>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="inline">
            <button type="button" data-add-section>Agregar seccion</button>
            <button class="primary">Guardar secciones</button>
        </div>
    </form>
</section>
<section class="card grid">
    <h2>Estado y firmas</h2>
    <form method="post" action="<?= $e($basePath) ?>/protocolo-familiar/<?= $e($protocolo['id']) ?>/estado" class="inline">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
        <select name="estado">
            <?php foreach (['borrador' => 'Borrador', 'en_revision' => 'En revision', 'aprobado' => 'Aprobado', 'archivado' => 'Archivado'] as $value => $label): ?>
                <option value="<?= $e($value) ?>" <?= $protocolo['estado'] === $value ? 'selected' : '' ?>><?= $e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button>Cambiar estado</button>
    </form>
    <form method="post" action="<?= $e($basePath) ?>/protocolo-familiar/<?= $e($protocolo['id']) ?>/firmas" class="cols">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
        <label>Firmante<input name="firmante_nombre" required></label>
        <label>Cargo<input name="firmante_cargo" required></label>
        <button>Firmar</button>
    </form>
</section>
