<?php
declare(strict_types=1);
$e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$items = static fn(string $c): array => is_array($itemsByCatalog[$c] ?? null) ? $itemsByCatalog[$c] : [];
$select = static function (string $code, string $label) use ($e, $items): void { ?>
    <label><?= $e($label) ?><select name="<?= $e($code) ?>"><option value="" disabled selected hidden>Seleccione...</option><?php foreach ($items($code) as $it): ?><option value="<?= $e($it['valor']) ?>"><?= $e($it['nombre']) ?></option><?php endforeach; ?></select></label>
<?php }; ?>
<section class="heading">
    <div>
        <small>Modulo de soporte</small>
        <h1>Listas maestras</h1>
        <p>Administra las opciones que alimentan formularios, documentos y secciones del protocolo.</p>
    </div>
    <a class="button primary" href="<?= $e($basePath) ?>/listas/nuevo">Crear lista</a>
</section>
<section class="card grid">
    <h2>Formulario base con selects</h2>
    <div class="select-grid">
        <?php foreach (['SI_NO'=>'Si/No','ESTADO_CIVIL'=>'Estado civil','TIPO_SOCIEDAD'=>'Tipo sociedad','VINCULO_FAMILIAR'=>'Tipo vinculo','GENERACION_FAMILIAR'=>'Generacion','ORIGEN_ACCIONES'=>'Origen acciones','ESTADO_DOCUMENTO'=>'Estado documento','NIVEL_RIESGO'=>'Nivel riesgo','NIVEL_PRIORIDAD'=>'Prioridad','AMBITO_PATRIMONIAL'=>'Ambito patrimonial','TIPO_TITULAR'=>'Tipo titular','CLASIFICACION_PATRIMONIAL'=>'Clasificacion patrimonial','MONEDA'=>'Moneda','FORMA_ADQUISICION_ACTIVO'=>'Forma adquisicion','METODO_VALORACION'=>'Metodo valoracion','NIVEL_LIQUIDEZ'=>'Liquidez','ESTADO_FISICO_ACTIVO'=>'Estado fisico','ESTADO_JURIDICO_ACTIVO'=>'Estado juridico','ESTADO_SOPORTE'=>'Estado soporte','ESTADO_GESTION'=>'Estado gestion','ESTADO_DECISION'=>'Estado decision','ESTRUCTURA_CARPETAS'=>'Estructura carpetas'] as $code => $label): ?><?php $select($code, $label); ?><?php endforeach; ?>
        <label>Grupo documental<select data-dependent-parent="documento"><option value="" disabled selected hidden>Seleccione...</option><?php foreach ($items('GRUPO_DOCUMENTAL') as $it): ?><option value="<?= $e($it['codigo']) ?>"><?= $e($it['nombre']) ?></option><?php endforeach; ?></select></label>
        <label>Tipo documento<select data-dependent-select="documento"><option value="">Seleccione grupo...</option><?php foreach ($items('TIPO_DOCUMENTO') as $it): ?><option value="<?= $e($it['valor']) ?>" data-parent-code="<?= $e($it['parent_codigo'] ?? '') ?>"><?= $e($it['nombre']) ?></option><?php endforeach; ?></select></label>
        <label>Categoria activo<select data-dependent-parent="activo"><option value="" disabled selected hidden>Seleccione...</option><?php foreach ($items('CATEGORIA_ACTIVO') as $it): ?><option value="<?= $e($it['codigo']) ?>"><?= $e($it['nombre']) ?></option><?php endforeach; ?></select></label>
        <label>Subcategoria activo<select data-dependent-select="activo"><option value="">Seleccione categoria...</option><?php foreach ($items('SUBCATEGORIA_ACTIVO') as $it): ?><option value="<?= $e($it['valor']) ?>" data-parent-code="<?= $e($it['parent_codigo'] ?? '') ?>"><?= $e($it['nombre']) ?></option><?php endforeach; ?></select></label>
    </div>
</section>
<section class="card">
    <div class="split">
        <h2>Administracion</h2>
        <span class="muted"><?= count($catalogos) ?> listas activas</span>
    </div>
    <?php foreach ($catalogos as $catalogo): ?>
        <details>
            <summary>
                <span><strong><?= $e($catalogo['nombre']) ?></strong> <code><?= $e($catalogo['codigo']) ?></code></span>
                <span class="actions">
                    <a class="button" href="<?= $e($basePath) ?>/listas/<?= $e($catalogo['codigo']) ?>/items/nuevo">Agregar item</a>
                    <a class="button" href="<?= $e($basePath) ?>/listas/<?= $e($catalogo['codigo']) ?>/editar">Editar</a>
                </span>
            </summary>
            <?php if ($items((string) $catalogo['codigo']) === []): ?>
                <p>No hay items activos.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Codigo</th><th>Nombre</th><th>Valor</th><th>Padre</th><th>Orden</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($items((string) $catalogo['codigo']) as $it): ?>
                        <tr>
                            <td><code><?= $e($it['codigo']) ?></code></td>
                            <td><?= $e($it['nombre']) ?></td>
                            <td><?= $e($it['valor']) ?></td>
                            <td><?= $e($it['parent_codigo'] ?? '-') ?></td>
                            <td><?= $e($it['orden']) ?></td>
                            <td class="row-actions">
                                <a href="<?= $e($basePath) ?>/listas/<?= $e($catalogo['codigo']) ?>/items/<?= $e($it['codigo']) ?>/editar">Editar</a>
                                <form method="post" action="<?= $e($basePath) ?>/listas/<?= $e($catalogo['codigo']) ?>/items/<?= $e($it['codigo']) ?>/eliminar" data-confirm="Desactivar este item?">
                                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                    <button type="submit">Desactivar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
</section>
