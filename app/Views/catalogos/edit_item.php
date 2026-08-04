<?php declare(strict_types=1); $e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); $v = static fn(string $k, string $d = ''): string => is_string($item[$k] ?? null) ? (string) $item[$k] : $d; ?>
<section class="heading"><div><a href="<?= $e($basePath) ?>/listas">Volver</a><h1>Editar item</h1><p><?= $e($catalogCode) ?> / <?= $e($v('codigo')) ?></p></div></section>
<?php if (is_string($error)): ?><div class="alert error"><?= $e($error) ?></div><?php endif; ?>
<form class="card grid" method="post" action="<?= $e($basePath) ?>/listas/<?= $e($catalogCode) ?>/items/<?= $e($v('codigo')) ?>/editar">
    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
    <div class="cols">
        <label>Codigo<input value="<?= $e($v('codigo')) ?>" disabled></label>
        <label>Padre<select name="parent_codigo"><option value="">Sin padre</option><?php foreach ($parentOptions as $p): ?><option value="<?= $e($p['codigo']) ?>" <?= $v('parent_codigo') === $p['codigo'] ? 'selected' : '' ?>><?= $e($p['nombre']) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Nombre<input name="nombre" required value="<?= $e($v('nombre')) ?>"></label>
    <label>Valor<input name="valor" required value="<?= $e($v('valor')) ?>"></label>
    <label>Orden<input name="orden" type="number" min="0" value="<?= $e((string) ($item['orden'] ?? '0')) ?>"></label>
    <label>Descripcion<textarea name="descripcion" rows="4"><?= $e($v('descripcion')) ?></textarea></label>
    <button class="primary">Guardar cambios</button>
</form>
