<?php declare(strict_types=1); $e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); $v = static fn(string $k, string $d = ''): string => is_string($catalogo[$k] ?? null) ? (string) $catalogo[$k] : $d; ?>
<section class="heading"><div><a href="<?= $e($basePath) ?>/listas">Volver</a><h1>Editar lista</h1><p><?= $e($v('codigo')) ?></p></div></section>
<?php if (is_string($error)): ?><div class="alert error"><?= $e($error) ?></div><?php endif; ?>
<form class="card grid" method="post" action="<?= $e($basePath) ?>/listas/<?= $e($v('codigo')) ?>/editar">
    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
    <div class="cols">
        <label>Codigo<input value="<?= $e($v('codigo')) ?>" disabled></label>
        <label>Tipo<select name="tipo"><option value="SIMPLE" <?= $v('tipo') === 'SIMPLE' ? 'selected' : '' ?>>Simple</option><option value="JERARQUICO" <?= $v('tipo') === 'JERARQUICO' ? 'selected' : '' ?>>Jerarquico</option></select></label>
    </div>
    <label>Nombre<input name="nombre" required value="<?= $e($v('nombre')) ?>"></label>
    <label>Orden<input name="orden" type="number" min="0" value="<?= $e((string) ($catalogo['orden'] ?? '0')) ?>"></label>
    <label>Descripcion<textarea name="descripcion" rows="4"><?= $e($v('descripcion')) ?></textarea></label>
    <div class="inline">
        <button class="primary">Guardar cambios</button>
    </div>
</form>
<form class="card" method="post" action="<?= $e($basePath) ?>/listas/<?= $e($v('codigo')) ?>/eliminar" data-confirm="Desactivar esta lista y ocultarla de los formularios?">
    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
    <button type="submit">Desactivar lista</button>
</form>
