<?php
declare(strict_types=1);
$e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$sections = is_array($old['secciones'] ?? null) ? $old['secciones'] : $defaultSections;
?>
<section class="heading">
    <div>
        <a href="<?= $e($basePath) ?>/protocolo-familiar">Volver</a>
        <h1>Crear protocolo familiar</h1>
        <p>El documento inicia con las 20 pestañas recomendadas para la estructura documental.</p>
    </div>
</section>
<?php if (is_string($error)): ?><div class="alert error"><?= $e($error) ?></div><?php endif; ?>
<form class="card grid" method="post" action="<?= $e($basePath) ?>/protocolo-familiar/nuevo" data-protocol-form>
    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
    <div class="cols">
        <label>Codigo<input name="codigo" required value="<?= $e(is_string($old['codigo'] ?? null) ? $old['codigo'] : 'PF-001') ?>"></label>
        <label>Empresa<input name="empresa_nombre" required value="<?= $e(is_string($old['empresa_nombre'] ?? null) ? $old['empresa_nombre'] : '') ?>"></label>
    </div>
    <label>Titulo<input name="titulo" required value="<?= $e(is_string($old['titulo'] ?? null) ? $old['titulo'] : 'Protocolo Familiar') ?>"></label>
    <label>Descripcion<textarea name="descripcion" required rows="4"><?= $e(is_string($old['descripcion'] ?? null) ? $old['descripcion'] : 'Proposito y alcance del protocolo') ?></textarea></label>
    <div class="split">
        <h2>Secciones</h2>
        <button type="button" data-add-section>Agregar seccion</button>
    </div>
    <div class="tabs" data-tabs>
        <div class="tab-list" role="tablist">
            <?php foreach ($sections as $i => $s): ?>
                <button type="button" role="tab" data-tab-target="section-<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>"><?= $e((string) ($s['orden'] ?? ($i + 1))) ?></button>
            <?php endforeach; ?>
        </div>
        <div data-sections data-next-index="<?= count($sections) ?>">
            <?php foreach ($sections as $i => $s): ?>
                <article class="section tab-panel <?= $i === 0 ? 'active' : '' ?>" id="section-<?= $i ?>" data-section>
                    <div class="split"><strong>Seccion <?= $i + 1 ?></strong><button type="button" data-remove-section>Quitar</button></div>
                    <div class="cols">
                        <label>Clave<input name="secciones[<?= $i ?>][clave]" required value="<?= $e((string) ($s['clave'] ?? '')) ?>"></label>
                        <label>Titulo<input name="secciones[<?= $i ?>][titulo]" required value="<?= $e((string) ($s['titulo'] ?? '')) ?>"></label>
                        <label>Orden<input name="secciones[<?= $i ?>][orden]" type="number" value="<?= $e((string) ($s['orden'] ?? ($i + 1))) ?>"></label>
                    </div>
                    <label>Contenido<textarea name="secciones[<?= $i ?>][contenido]" required rows="8"><?= $e((string) ($s['contenido'] ?? 'Pendiente por documentar.')) ?></textarea></label>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    <button class="primary">Guardar protocolo</button>
</form>
