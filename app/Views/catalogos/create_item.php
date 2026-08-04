<?php declare(strict_types=1); $e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); $oldv = static fn(string $k, string $d = ''): string => is_array($old) && is_string($old[$k] ?? null) ? $old[$k] : $d; ?>
<section class="heading"><div><a href="<?= $e($basePath) ?>/listas">Volver</a><h1>Agregar item</h1><p><?= $e($catalogCode) ?></p></div></section>
<?php if (is_string($error)): ?><div class="alert error"><?= $e($error) ?></div><?php endif; ?>
<form class="card grid" method="post" action="<?= $e($basePath) ?>/listas/<?= $e($catalogCode) ?>/items">
    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
    <div class="cols">
        <label>Codigo<input name="codigo" required pattern="[A-Z0-9_]{2,120}" value="<?= $e($oldv('codigo')) ?>"></label>
        <label>Padre<select name="parent_codigo"><option value="">Sin padre</option><?php foreach ($parentOptions as $p): ?><option value="<?= $e($p['codigo']) ?>"><?= $e($p['nombre']) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Nombre<input name="nombre" required value="<?= $e($oldv('nombre')) ?>"></label>
    <label>Valor<input name="valor" required value="<?= $e($oldv('valor')) ?>"></label>
    <label>Orden<input name="orden" type="number" min="0" value="<?= $e($oldv('orden', '0')) ?>"></label>
    <label>Descripcion<textarea name="descripcion" rows="4"><?= $e($oldv('descripcion')) ?></textarea></label>
    <button class="primary">Agregar item</button>
</form>
