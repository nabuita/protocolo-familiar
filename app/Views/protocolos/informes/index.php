<?php
declare(strict_types=1);
$e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$money = static fn(mixed $value): string => '$' . number_format((float) $value, 0, ',', '.');
$decisionSummary = is_array($decisionSummary ?? null) ? $decisionSummary : [];
$patrimonioSummary = is_array($patrimonioSummary ?? null) ? $patrimonioSummary : [];
$documentoSummary = is_array($documentoSummary ?? null) ? $documentoSummary : [];
$riesgoSummary = is_array(($riesgoDashboard ?? [])['resumen'] ?? null) ? $riesgoDashboard['resumen'] : [];
$reports = [
    [
        'codigo' => 'INF-001',
        'titulo' => 'Informe integral de decisiones',
        'modulo' => '06_Decisiones',
        'alcance' => 'Consolida las 20 categorias, decisiones aprobadas, pendientes, fundamento juridico, soportes, alertas, historial y firmas.',
        'salida' => 'Word / PDF / vista imprimible',
        'estado' => 'Estructura creada',
        'prioridad' => 'Alta',
    ],
    [
        'codigo' => 'INF-002',
        'titulo' => 'Informe patrimonial global',
        'modulo' => '04_Patrimonio',
        'alcance' => 'Resume activos, valores de adquisicion, catastrales, comerciales, participacion, ingresos, gastos, INO y rentabilidad.',
        'salida' => 'PDF / Excel',
        'estado' => 'Planeado',
        'prioridad' => 'Alta',
    ],
    [
        'codigo' => 'INF-003',
        'titulo' => 'Informe patrimonial por categoria',
        'modulo' => '04_Patrimonio',
        'alcance' => 'Separa inmuebles, vehiculos, sociedades, inversiones, PI y otros activos para comparar evolucion, rentabilidad y soportes.',
        'salida' => 'PDF / Excel',
        'estado' => 'Planeado',
        'prioridad' => 'Alta',
    ],
    [
        'codigo' => 'INF-004',
        'titulo' => 'Informe anual por inmueble',
        'modulo' => '04_Patrimonio',
        'alcance' => 'Muestra por inmueble la serie anual: valor catastral, valor comercial, canon, ingresos, costos, gastos, INO y rentabilidad.',
        'salida' => 'PDF / Excel',
        'estado' => 'Planeado',
        'prioridad' => 'Alta',
    ],
    [
        'codigo' => 'INF-005',
        'titulo' => 'Informe de seguros global',
        'modulo' => '04_Patrimonio / 05_Documentos',
        'alcance' => 'Controla seguros vigentes, vencimientos, coberturas, beneficiarios, primas, exclusiones y activos/personas aseguradas.',
        'salida' => 'PDF / calendario',
        'estado' => 'Planeado',
        'prioridad' => 'Alta',
    ],
    [
        'codigo' => 'INF-006',
        'titulo' => 'Informe de seguros por categoria',
        'modulo' => '04_Patrimonio / 05_Documentos',
        'alcance' => 'Agrupa seguros por inmuebles, vehiculos, responsabilidad civil, vida, key person, cyber, cumplimiento y otros.',
        'salida' => 'PDF / Excel',
        'estado' => 'Planeado',
        'prioridad' => 'Media',
    ],
    [
        'codigo' => 'INF-007',
        'titulo' => 'Informe documental y soportes pendientes',
        'modulo' => '05_Documentos',
        'alcance' => 'Lista documentos recibidos, faltantes, vencidos, provisionales y soportes requeridos por cada modulo.',
        'salida' => 'PDF / Excel',
        'estado' => 'Planeado',
        'prioridad' => 'Alta',
    ],
    [
        'codigo' => 'INF-008',
        'titulo' => 'Informe de riesgos y controles',
        'modulo' => '07_Riesgos',
        'alcance' => 'Consolida candidatos, riesgos definitivos, controles, acciones, responsables, estado y evidencia.',
        'salida' => 'PDF / tablero',
        'estado' => 'Planeado',
        'prioridad' => 'Alta',
    ],
    [
        'codigo' => 'INF-009',
        'titulo' => 'Informe de propiedad intelectual y activos tecnologicos',
        'modulo' => '04_Patrimonio / 06_Decisiones',
        'alcance' => 'Inventario, titularidad, registros DNDA/SIC, licencias, repositorios, dominios, valoracion DNP y explotacion comercial.',
        'salida' => 'PDF / Excel',
        'estado' => 'Planeado',
        'prioridad' => 'Media',
    ],
    [
        'codigo' => 'INF-010',
        'titulo' => 'Informe de gobierno familiar y acuerdos pendientes',
        'modulo' => '01_Familia / 06_Decisiones',
        'alcance' => 'Resume organos familiares, responsables, roles, acuerdos aprobados, actas pendientes y decisiones por revisar.',
        'salida' => 'PDF',
        'estado' => 'Planeado',
        'prioridad' => 'Media',
    ],
    [
        'codigo' => 'INF-011',
        'titulo' => 'Informe de vencimientos y calendario',
        'modulo' => '05_Documentos / 04_Patrimonio',
        'alcance' => 'Calendario consolidado de polizas, contratos, renovaciones, revisiones, documentos y decisiones con fecha objetivo.',
        'salida' => 'Calendario / PDF',
        'estado' => 'Planeado',
        'prioridad' => 'Media',
    ],
    [
        'codigo' => 'INF-012',
        'titulo' => 'Informe ejecutivo para reunion familiar',
        'modulo' => 'Todos',
        'alcance' => 'Version corta para presentar avances, decisiones criticas, riesgos, pendientes y proximos pasos.',
        'salida' => 'PDF / presentacion',
        'estado' => 'Planeado',
        'prioridad' => 'Media',
    ],
];
?>

<section class="heading">
    <div>
        <small>08. INFORMES DEL PROTOCOLO</small>
        <h1>Informes</h1>
        <p>Centro de reportes del protocolo familiar. Aqui se listan los informes que podran generarse desde los modulos ya diligenciados.</p>
    </div>
</section>

<section class="decision-summary" aria-label="Resumen para informes">
    <article><strong><?= $e((int) ($decisionSummary['total'] ?? 0)) ?></strong><span>Decisiones</span></article>
    <article class="is-ok"><strong><?= $e((int) ($decisionSummary['aprobadas'] ?? 0)) ?></strong><span>Aprobadas</span></article>
    <article><strong><?= $e((int) ($patrimonioSummary['total'] ?? 0)) ?></strong><span>Activos</span></article>
    <article class="is-info"><strong><?= $e($money($patrimonioSummary['valor_comercial'] ?? 0)) ?></strong><span>Valor comercial</span></article>
    <article><strong><?= $e((int) ($documentoSummary['total'] ?? 0)) ?></strong><span>Documentos</span></article>
    <article class="is-warning"><strong><?= $e((int) ($documentoSummary['faltantes'] ?? 0)) ?></strong><span>Faltantes</span></article>
    <article class="is-danger"><strong><?= $e((int) ($riesgoSummary['riesgos_abiertos'] ?? 0)) ?></strong><span>Riesgos abiertos</span></article>
</section>

<section class="card">
    <div class="decision-toolbar">
        <div>
            <h2>Catalogo de informes previstos</h2>
            <p class="muted">Primero definimos la estructura. Luego cada informe se conecta a datos reales y se exporta en PDF, Word, Excel o vista imprimible.</p>
        </div>
    </div>

    <div class="report-grid">
        <?php foreach ($reports as $report): ?>
            <article class="report-card">
                <div>
                    <code><?= $e($report['codigo']) ?></code>
                    <strong><?= $e($report['titulo']) ?></strong>
                    <span><?= $e($report['modulo']) ?></span>
                </div>
                <p><?= $e($report['alcance']) ?></p>
                <footer>
                    <span><?= $e($report['salida']) ?></span>
                    <span><?= $e($report['estado']) ?></span>
                    <span>Prioridad <?= $e($report['prioridad']) ?></span>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="card">
    <h2>Orden sugerido de construccion</h2>
    <div class="decision-academy-body">
        <article><strong>1. Decisiones</strong><p>Convertir la estructura ya creada en informe real con datos del modulo 06.</p></article>
        <article><strong>2. Patrimonio</strong><p>Generar informe global, por categoria y por inmueble con series anuales.</p></article>
        <article><strong>3. Seguros</strong><p>Separar seguros globales y por categoria, conectados al calendario de vencimientos.</p></article>
        <article><strong>4. Documentos</strong><p>Emitir matriz de soportes faltantes, vencidos, recibidos y provisionales.</p></article>
        <article><strong>5. Riesgos</strong><p>Consolidar alertas, riesgos aprobados, controles, acciones y evidencia.</p></article>
        <article><strong>6. Ejecutivo</strong><p>Preparar una version corta para reuniones familiares y seguimiento periodico.</p></article>
    </div>
</section>
