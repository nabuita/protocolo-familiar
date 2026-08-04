<?php
declare(strict_types=1);
$e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$catalog = is_array(($insuranceAcademy ?? [])['catalog'] ?? null) ? $insuranceAcademy['catalog'] : [];
$coverages = is_array(($insuranceAcademy ?? [])['coverages'] ?? null) ? $insuranceAcademy['coverages'] : [];
$macros = array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string) ($row['Macro-ramo'] ?? ''), $catalog))));
$byRamo = [];
foreach ($coverages as $coverage) {
    $ramo = (string) ($coverage['Ramo'] ?? '');
    if ($ramo === '') {
        continue;
    }
    $byRamo[$ramo][] = $coverage;
}
?>
<section class="insurance-academy-page" data-insurance-academy-page>
    <div class="insurance-academy-hero">
        <div>
            <p class="section-kicker">09. Biblioteca tecnica de seguros</p>
            <h1>Academia de seguros</h1>
            <p>Consulta macro-ramos, ramos, coberturas principales, complementarias, fuentes y advertencias antes de configurar la proteccion de cada activo.</p>
        </div>
        <div class="insurance-academy-metrics" aria-label="Resumen de academia de seguros">
            <article><strong><?= $e(count($macros)) ?></strong><span>Macro-ramos</span></article>
            <article><strong><?= $e(count($catalog)) ?></strong><span>Ramos</span></article>
            <article><strong><?= $e(count($coverages)) ?></strong><span>Coberturas</span></article>
        </div>
    </div>

    <div class="insurance-academy-toolbar">
        <label>Buscar
            <input type="search" data-insurance-academy-search placeholder="Ramo, cobertura, fuente, observacion...">
        </label>
        <label>Macro-ramo
            <select data-insurance-academy-macro>
                <option value="">Todos</option>
                <?php foreach ($macros as $macro): ?>
                    <option value="<?= $e($macro) ?>"><?= $e($macro) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <div class="insurance-academy-layout">
        <aside class="insurance-academy-list" aria-label="Ramos de seguros">
            <?php foreach ($catalog as $index => $row): ?>
                <?php
                $ramo = (string) ($row['Ramo oficial'] ?? '');
                $macro = (string) ($row['Macro-ramo'] ?? '');
                $search = strtolower(trim(implode(' ', array_map(static fn(mixed $v): string => (string) $v, $row))));
                ?>
                <button type="button" class="<?= $index === 0 ? 'is-active' : '' ?>" data-insurance-academy-ramo="<?= $e($ramo) ?>" data-insurance-academy-macro-value="<?= $e($macro) ?>" data-insurance-academy-search-text="<?= $e($search) ?>">
                    <em><?= $e($row['Código'] ?? '') ?></em>
                    <span><?= $e($ramo) ?></span>
                    <small><?= $e($macro) ?></small>
                </button>
            <?php endforeach; ?>
        </aside>

        <div class="insurance-academy-detail">
            <?php foreach ($catalog as $index => $row): ?>
                <?php
                $ramo = (string) ($row['Ramo oficial'] ?? '');
                $ramoCoverages = $byRamo[$ramo] ?? [];
                ?>
                <article class="insurance-academy-card <?= $index === 0 ? 'is-active' : '' ?>" data-insurance-academy-card="<?= $e($ramo) ?>">
                    <div class="insurance-academy-card-head">
                        <div>
                            <span><?= $e($row['Código'] ?? '') ?> · <?= $e($row['Macro-ramo'] ?? '') ?></span>
                            <h2><?= $e($ramo) ?></h2>
                            <p><?= $e($row['Objeto / cobertura básica'] ?? '') ?></p>
                        </div>
                        <strong><?= $e($row['Carácter'] ?? '') ?></strong>
                    </div>

                    <div class="insurance-academy-grid">
                        <section>
                            <h3>Coberturas principales</h3>
                            <p><?= $e($row['Coberturas principales'] ?? '') ?></p>
                        </section>
                        <section>
                            <h3>Complementarias habituales</h3>
                            <p><?= $e($row['Coberturas complementarias habituales'] ?? '') ?></p>
                        </section>
                        <section>
                            <h3>Naturaleza</h3>
                            <p><?= $e($row['Naturaleza'] ?? '') ?></p>
                        </section>
                        <section>
                            <h3>Observacion</h3>
                            <p><?= $e($row['Observación'] ?? '') ?></p>
                        </section>
                    </div>

                    <div class="insurance-academy-coverage-table">
                        <div class="insurance-academy-table-head">
                            <span>Tipo</span>
                            <span>Cobertura</span>
                            <span>Descripcion practica</span>
                            <span>Fuente</span>
                        </div>
                        <?php foreach ($ramoCoverages as $coverage): ?>
                            <div>
                                <span><?= $e($coverage['Tipo'] ?? '') ?></span>
                                <strong><?= $e($coverage['Cobertura'] ?? '') ?></strong>
                                <span><?= $e($coverage['Descripción práctica'] ?? '') ?></span>
                                <?php $source = (string) ($coverage['Fuente'] ?? ''); ?>
                                <a href="<?= $e($source) ?>" target="_blank" rel="noopener">Fuente</a>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php $officialSource = (string) ($row['Fuente oficial'] ?? ''); ?>
                    <?php if ($officialSource !== ''): ?>
                        <p class="insurance-academy-source">Fuente oficial: <a href="<?= $e($officialSource) ?>" target="_blank" rel="noopener"><?= $e($officialSource) ?></a></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
