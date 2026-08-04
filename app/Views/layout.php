<?php
declare(strict_types=1);
$e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$assetVersion = static function (string $asset) {
    $path = dirname(__DIR__, 2) . '/public' . $asset;
    return is_file($path) ? (string) filemtime($path) : '1';
};
$cssAssets = [
    '/assets/css/base.css',
    '/assets/css/layout.css',
    '/assets/css/components.css',
    '/assets/css/features/familia.css',
    '/assets/css/features/empresas.css',
    '/assets/css/features/accionistas.css',
    '/assets/css/features/patrimonio.css',
    '/assets/css/features/documentos.css',
    '/assets/css/features/decisiones.css',
    '/assets/css/features/riesgos.css',
    '/assets/css/features/academia-seguros.css',
];
$jsAssets = [
    '/assets/js/protocol-sections.js',
    '/assets/js/features/familia.js',
    '/assets/js/features/empresas.js',
    '/assets/js/features/accionistas.js',
    '/assets/js/features/patrimonio.js',
    '/assets/js/features/documentos.js',
    '/assets/js/features/decisiones.js',
    '/assets/js/features/riesgos.js',
    '/assets/js/features/academia-seguros.js',
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Protocolo Familiar</title>
    <?php foreach ($cssAssets as $asset): ?>
        <link rel="stylesheet" href="<?= $e($basePath . $asset) ?>?v=<?= $e($assetVersion($asset)) ?>">
    <?php endforeach; ?>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/es.global.min.js" defer></script>
    <?php foreach ($jsAssets as $asset): ?>
        <script src="<?= $e($basePath . $asset) ?>?v=<?= $e($assetVersion($asset)) ?>" defer></script>
    <?php endforeach; ?>
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= $e($basePath) ?>/protocolo-familiar"><span>PF</span><strong>Protocolo Familiar</strong></a>
    <?php if (is_string($actor) && $actor !== ''): ?>
        <nav>
            <a href="<?= $e($basePath) ?>/protocolo-familiar">Protocolos</a>
            <a href="<?= $e($basePath) ?>/listas">Listas</a>
            <form method="post" action="<?= $e($basePath) ?>/logout"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><button>Salir</button></form>
        </nav>
    <?php endif; ?>
</header>
<main class="shell">
    <?php if (is_array($flash)): ?><div class="alert <?= $e($flash['type']) ?>"><?= $e($flash['message']) ?></div><?php endif; ?>
    <?= $content ?>
</main>
</body>
</html>
