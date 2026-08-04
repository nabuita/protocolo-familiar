<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Support\CatalogSeed;
use App\Support\Uuid;

require dirname(__DIR__) . '/app/bootstrap.php';

$config = Config::load(dirname(__DIR__));
$pdo = (new Database($config))->pdo();

$pdo->exec('CREATE TABLE IF NOT EXISTS pf_catalogos (
    id CHAR(36) NOT NULL PRIMARY KEY,
    codigo VARCHAR(80) NOT NULL UNIQUE,
    nombre VARCHAR(160) NOT NULL,
    descripcion TEXT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT "SIMPLE",
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_by VARCHAR(120) NULL,
    updated_by VARCHAR(120) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    INDEX idx_pf_catalogos_activo_orden (activo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS pf_catalogo_items (
    id CHAR(36) NOT NULL PRIMARY KEY,
    catalogo_id CHAR(36) NOT NULL,
    parent_id CHAR(36) NULL,
    codigo VARCHAR(120) NOT NULL,
    nombre VARCHAR(220) NOT NULL,
    valor VARCHAR(220) NOT NULL,
    descripcion TEXT NULL,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    metadata_json JSON NULL,
    created_by VARCHAR(120) NULL,
    updated_by VARCHAR(120) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    UNIQUE KEY uq_pf_catalogo_items_catalog_codigo (catalogo_id, codigo),
    INDEX idx_pf_catalogo_items_parent (parent_id),
    INDEX idx_pf_catalogo_items_activo_orden (activo, orden),
    CONSTRAINT fk_pf_catalogo_items_catalogo FOREIGN KEY (catalogo_id) REFERENCES pf_catalogos(id),
    CONSTRAINT fk_pf_catalogo_items_parent FOREIGN KEY (parent_id) REFERENCES pf_catalogo_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_familiar (
    id CHAR(36) NOT NULL PRIMARY KEY,
    codigo VARCHAR(80) NOT NULL UNIQUE,
    empresa_nombre VARCHAR(220) NOT NULL,
    titulo VARCHAR(220) NOT NULL,
    descripcion TEXT NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT "borrador",
    version INT NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    created_by VARCHAR(120) NULL,
    updated_by VARCHAR(120) NULL,
    INDEX idx_protocolo_familiar_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_familiar_seccion (
    id CHAR(36) NOT NULL PRIMARY KEY,
    protocolo_id CHAR(36) NOT NULL,
    clave VARCHAR(160) NOT NULL,
    titulo VARCHAR(220) NOT NULL,
    contenido MEDIUMTEXT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    created_by VARCHAR(120) NULL,
    updated_by VARCHAR(120) NULL,
    INDEX idx_protocolo_familiar_seccion_orden (protocolo_id, orden),
    CONSTRAINT fk_protocolo_familiar_seccion_protocolo FOREIGN KEY (protocolo_id) REFERENCES protocolo_familiar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_familiar_version (
    protocolo_id CHAR(36) NOT NULL,
    version INT NOT NULL,
    snapshot_json JSON NOT NULL,
    snapshot_hash CHAR(64) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    created_by VARCHAR(120) NULL,
    PRIMARY KEY (protocolo_id, version),
    CONSTRAINT fk_protocolo_familiar_version_protocolo FOREIGN KEY (protocolo_id) REFERENCES protocolo_familiar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_familiar_auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    protocolo_id CHAR(36) NOT NULL,
    version INT NOT NULL,
    accion VARCHAR(80) NOT NULL,
    actor_id VARCHAR(120) NULL,
    detalle_json JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    INDEX idx_protocolo_familiar_auditoria_protocolo (protocolo_id, created_at),
    CONSTRAINT fk_protocolo_familiar_auditoria_protocolo FOREIGN KEY (protocolo_id) REFERENCES protocolo_familiar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_familiar_firma (
    id CHAR(36) NOT NULL PRIMARY KEY,
    protocolo_id CHAR(36) NOT NULL,
    protocolo_version INT NOT NULL,
    firmante_nombre VARCHAR(180) NOT NULL,
    firmante_cargo VARCHAR(180) NOT NULL,
    firma_hash CHAR(64) NOT NULL,
    firmado_at DATETIME(6) NOT NULL,
    created_by VARCHAR(120) NULL,
    INDEX idx_protocolo_familiar_firma_protocolo (protocolo_id, firmado_at),
    CONSTRAINT fk_protocolo_familiar_firma_protocolo FOREIGN KEY (protocolo_id) REFERENCES protocolo_familiar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_familia_personas (
    id CHAR(36) NOT NULL PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre_completo VARCHAR(220) NOT NULL,
    tipo_vinculo VARCHAR(120) NULL,
    generacion VARCHAR(120) NULL,
    edad INT NULL,
    estado_civil VARCHAR(120) NULL,
    ano_matrimonio_convivencia INT NULL,
    tiene_capitulaciones VARCHAR(40) NULL,
    sociedad_conyugal_liquidada VARCHAR(40) NULL,
    numero_hijos INT NULL,
    trabaja_empresa_familiar VARCHAR(40) NULL,
    empresa_donde_trabaja VARCHAR(220) NULL,
    cargo VARCHAR(180) NULL,
    es_accionista VARCHAR(40) NULL,
    participa_decisiones VARCHAR(40) NULL,
    observaciones TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    created_by VARCHAR(120) NULL,
    updated_by VARCHAR(120) NULL,
    INDEX idx_protocolo_familia_personas_activo_codigo (activo, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_empresas (
    id CHAR(36) NOT NULL PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    razon_social VARCHAR(240) NOT NULL,
    nombre_comercial VARCHAR(160) NULL,
    tipo_sociedad VARCHAR(80) NULL,
    ano_creacion DATE NULL,
    actividad_principal TEXT NULL,
    empresa_operativa_patrimonial VARCHAR(80) NULL,
    representante_legal VARCHAR(220) NULL,
    tiene_junta_directiva VARCHAR(40) NULL,
    tiene_revisor_fiscal VARCHAR(40) NULL,
    tiene_inmuebles_propios VARCHAR(40) NULL,
    tiene_empleados VARCHAR(40) NULL,
    deudas_creditos_importantes TEXT NULL,
    estatutos_actualizados VARCHAR(40) NULL,
    acuerdo_accionistas VARCHAR(40) NULL,
    libro_accionistas_actualizado VARCHAR(40) NULL,
    nivel_riesgo VARCHAR(40) NULL,
    observaciones TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    created_by VARCHAR(120) NULL,
    updated_by VARCHAR(120) NULL,
    INDEX idx_protocolo_empresas_activo_codigo (activo, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_accionistas (
    id CHAR(36) NOT NULL PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    empresa VARCHAR(240) NOT NULL,
    accionista VARCHAR(220) NOT NULL,
    vinculo_familiar VARCHAR(120) NULL,
    numero_acciones_cuotas BIGINT NULL,
    porcentaje DECIMAL(7,4) NOT NULL DEFAULT 0,
    ano_ingreso INT NULL,
    forma_adquisicion VARCHAR(120) NULL,
    valor_pagado_aportado DECIMAL(18,2) NULL,
    quien_aporto_recursos VARCHAR(220) NULL,
    estado_civil_adquirir VARCHAR(120) NULL,
    convivia_en_ese_momento VARCHAR(40) NULL,
    existe_documento_adquisicion VARCHAR(40) NULL,
    registro_libro_accionistas VARCHAR(40) NULL,
    restriccion_vigente VARCHAR(80) NULL,
    valor_estimado_actual DECIMAL(18,2) NULL,
    observaciones TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    created_by VARCHAR(120) NULL,
    updated_by VARCHAR(120) NULL,
    INDEX idx_protocolo_accionistas_empresa (activo, empresa),
    INDEX idx_protocolo_accionistas_codigo (activo, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_patrimonio_activos (
    id CHAR(36) NOT NULL PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    tipo_activo VARCHAR(120) NOT NULL,
    nombre_descripcion VARCHAR(240) NOT NULL,
    identificador VARCHAR(220) NULL,
    etiqueta_identificador VARCHAR(160) NULL,
    titular VARCHAR(220) NULL,
    ambito_titular VARCHAR(120) NULL,
    empresa_relacionada VARCHAR(240) NULL,
    valor_adquisicion DECIMAL(18,2) NULL,
    fecha_adquisicion DATE NULL,
    valor_actual DECIMAL(18,2) NULL,
    fecha_corte_valor DATE NULL,
    metodo_valoracion VARCHAR(120) NULL,
    moneda VARCHAR(20) NULL,
    estado_soporte VARCHAR(120) NULL,
    nivel_riesgo VARCHAR(80) NULL,
    detalle_json JSON NULL,
    participaciones_json JSON NULL,
    fiducia_beneficiarios_json JSON NULL,
    subunidades_json JSON NULL,
    seguro_coberturas_json JSON NULL,
    seguro_equipos_json JSON NULL,
    valoraciones_anuales_json JSON NULL,
    ingresos_anuales_json JSON NULL,
    gastos_anuales_json JSON NULL,
    observaciones TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    created_by VARCHAR(120) NULL,
    updated_by VARCHAR(120) NULL,
    INDEX idx_protocolo_patrimonio_tipo_codigo (activo, tipo_activo, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_documentos (
    id CHAR(36) NOT NULL PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    sujeto_tipo VARCHAR(120) NOT NULL,
    sujeto_codigo VARCHAR(40) NULL,
    sujeto_nombre VARCHAR(260) NOT NULL,
    categoria VARCHAR(120) NOT NULL,
    documento_tipo VARCHAR(180) NOT NULL,
    requerido VARCHAR(20) NOT NULL DEFAULT "Si",
    estado VARCHAR(80) NOT NULL DEFAULT "Faltante",
    nivel_riesgo VARCHAR(80) NULL,
    fecha_documento DATE NULL,
    fecha_vencimiento DATE NULL,
    archivo_nombre VARCHAR(255) NULL,
    archivo_ruta VARCHAR(500) NULL,
    archivo_mime VARCHAR(160) NULL,
    archivo_tamano INT NULL,
    observaciones TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    created_by VARCHAR(120) NULL,
    updated_by VARCHAR(120) NULL,
    INDEX idx_protocolo_documentos_estado (activo, estado),
    INDEX idx_protocolo_documentos_sujeto (activo, sujeto_tipo, sujeto_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$catalogStmt = $pdo->prepare('INSERT INTO pf_catalogos (id, codigo, nombre, descripcion, tipo, orden, activo, created_by, updated_by, created_at, updated_at)
    VALUES (:id, :codigo, :nombre, :descripcion, :tipo, :orden, 1, :created_by, :updated_by, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
    ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion), tipo = VALUES(tipo), orden = VALUES(orden), activo = 1, updated_by = VALUES(updated_by), updated_at = UTC_TIMESTAMP(6)');

$itemStmt = $pdo->prepare('INSERT INTO pf_catalogo_items (id, catalogo_id, parent_id, codigo, nombre, valor, descripcion, orden, activo, metadata_json, created_by, updated_by, created_at, updated_at)
    VALUES (:id, :catalogo_id, :parent_id, :codigo, :nombre, :valor, :descripcion, :orden, 1, NULL, :created_by, :updated_by, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
    ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), valor = VALUES(valor), descripcion = VALUES(descripcion), orden = VALUES(orden), activo = 1, updated_by = VALUES(updated_by), updated_at = UTC_TIMESTAMP(6)');

$actor = 'install';
$catalogOrder = 0;
foreach (CatalogSeed::simple() as $code => $catalog) {
    $catalogStmt->execute(['id' => Uuid::v4(), 'codigo' => $code, 'nombre' => $catalog['nombre'], 'descripcion' => null, 'tipo' => $catalog['tipo'], 'orden' => ++$catalogOrder, 'created_by' => $actor, 'updated_by' => $actor]);
    $catalogId = catalogId($pdo, $code);
    $itemOrder = 0;
    foreach ($catalog['items'] as $itemName) {
        $itemCode = itemCode($itemName);
        $itemStmt->execute(['id' => Uuid::v4(), 'catalogo_id' => $catalogId, 'parent_id' => null, 'codigo' => $itemCode, 'nombre' => $itemName, 'valor' => $itemName, 'descripcion' => null, 'orden' => ++$itemOrder, 'created_by' => $actor, 'updated_by' => $actor]);
    }
}

$catalogStmt->execute(['id' => Uuid::v4(), 'codigo' => 'GRUPO_DOCUMENTAL', 'nombre' => 'Grupo documental', 'descripcion' => null, 'tipo' => 'SIMPLE', 'orden' => ++$catalogOrder, 'created_by' => $actor, 'updated_by' => $actor]);
$catalogStmt->execute(['id' => Uuid::v4(), 'codigo' => 'TIPO_DOCUMENTO', 'nombre' => 'Tipo documento', 'descripcion' => null, 'tipo' => 'JERARQUICO', 'orden' => ++$catalogOrder, 'created_by' => $actor, 'updated_by' => $actor]);
$groupCatalogId = catalogId($pdo, 'GRUPO_DOCUMENTAL');
$typeCatalogId = catalogId($pdo, 'TIPO_DOCUMENTO');
$groupOrder = 0;
foreach (CatalogSeed::documentGroups() as $groupCode => $documents) {
    $groupName = str_replace('_', ' ', $groupCode);
    $itemStmt->execute(['id' => Uuid::v4(), 'catalogo_id' => $groupCatalogId, 'parent_id' => null, 'codigo' => itemCode($groupCode), 'nombre' => $groupName, 'valor' => $groupName, 'descripcion' => null, 'orden' => ++$groupOrder, 'created_by' => $actor, 'updated_by' => $actor]);
    $parentId = itemId($pdo, $groupCatalogId, itemCode($groupCode));
    $docOrder = 0;
    foreach ($documents as $documentName) {
        $itemStmt->execute(['id' => Uuid::v4(), 'catalogo_id' => $typeCatalogId, 'parent_id' => $parentId, 'codigo' => itemCode($groupCode . '_' . $documentName), 'nombre' => $documentName, 'valor' => $documentName, 'descripcion' => null, 'orden' => ++$docOrder, 'created_by' => $actor, 'updated_by' => $actor]);
    }
}

$catalogStmt->execute(['id' => Uuid::v4(), 'codigo' => 'CATEGORIA_ACTIVO', 'nombre' => 'Categoria activo', 'descripcion' => null, 'tipo' => 'SIMPLE', 'orden' => ++$catalogOrder, 'created_by' => $actor, 'updated_by' => $actor]);
$catalogStmt->execute(['id' => Uuid::v4(), 'codigo' => 'SUBCATEGORIA_ACTIVO', 'nombre' => 'Subcategoria activo', 'descripcion' => null, 'tipo' => 'JERARQUICO', 'orden' => ++$catalogOrder, 'created_by' => $actor, 'updated_by' => $actor]);
$assetCatalogId = catalogId($pdo, 'CATEGORIA_ACTIVO');
$subassetCatalogId = catalogId($pdo, 'SUBCATEGORIA_ACTIVO');
$assetOrder = 0;
foreach (CatalogSeed::assetCategories() as $categoryCode => $subcategories) {
    $categoryName = str_replace('_', ' ', $categoryCode);
    $itemStmt->execute(['id' => Uuid::v4(), 'catalogo_id' => $assetCatalogId, 'parent_id' => null, 'codigo' => itemCode($categoryCode), 'nombre' => $categoryName, 'valor' => $categoryName, 'descripcion' => null, 'orden' => ++$assetOrder, 'created_by' => $actor, 'updated_by' => $actor]);
    $parentId = itemId($pdo, $assetCatalogId, itemCode($categoryCode));
    $subOrder = 0;
    foreach ($subcategories as $subcategoryName) {
        $itemStmt->execute(['id' => Uuid::v4(), 'catalogo_id' => $subassetCatalogId, 'parent_id' => $parentId, 'codigo' => itemCode($categoryCode . '_' . $subcategoryName), 'nombre' => $subcategoryName, 'valor' => $subcategoryName, 'descripcion' => null, 'orden' => ++$subOrder, 'created_by' => $actor, 'updated_by' => $actor]);
    }
}

$familyStmt = $pdo->prepare('INSERT INTO protocolo_familia_personas (
    id, codigo, nombre_completo, tipo_vinculo, generacion, edad, estado_civil, ano_matrimonio_convivencia,
    tiene_capitulaciones, sociedad_conyugal_liquidada, numero_hijos, trabaja_empresa_familiar,
    empresa_donde_trabaja, cargo, es_accionista, participa_decisiones, observaciones, activo,
    created_at, updated_at, created_by, updated_by
) VALUES (
    :id, :codigo, :nombre_completo, :tipo_vinculo, :generacion, :edad, :estado_civil, :ano_matrimonio_convivencia,
    :tiene_capitulaciones, :sociedad_conyugal_liquidada, :numero_hijos, :trabaja_empresa_familiar,
    :empresa_donde_trabaja, :cargo, :es_accionista, :participa_decisiones, :observaciones, 1,
    UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
) ON DUPLICATE KEY UPDATE
    nombre_completo = VALUES(nombre_completo),
    tipo_vinculo = VALUES(tipo_vinculo),
    generacion = VALUES(generacion),
    edad = VALUES(edad),
    estado_civil = VALUES(estado_civil),
    ano_matrimonio_convivencia = VALUES(ano_matrimonio_convivencia),
    tiene_capitulaciones = VALUES(tiene_capitulaciones),
    sociedad_conyugal_liquidada = VALUES(sociedad_conyugal_liquidada),
    numero_hijos = VALUES(numero_hijos),
    trabaja_empresa_familiar = VALUES(trabaja_empresa_familiar),
    empresa_donde_trabaja = VALUES(empresa_donde_trabaja),
    cargo = VALUES(cargo),
    es_accionista = VALUES(es_accionista),
    participa_decisiones = VALUES(participa_decisiones),
    observaciones = VALUES(observaciones),
    activo = 1,
    updated_at = UTC_TIMESTAMP(6),
    updated_by = VALUES(updated_by)');

foreach (familySeed() as $row) {
    $familyStmt->execute($row + ['id' => Uuid::v4(), 'created_by' => $actor, 'updated_by' => $actor]);
}

$companyStmt = $pdo->prepare('INSERT INTO protocolo_empresas (
    id, codigo, razon_social, nombre_comercial, tipo_sociedad, ano_creacion, actividad_principal,
    empresa_operativa_patrimonial, representante_legal, tiene_junta_directiva, tiene_revisor_fiscal,
    tiene_inmuebles_propios, tiene_empleados, deudas_creditos_importantes, estatutos_actualizados,
    acuerdo_accionistas, libro_accionistas_actualizado, nivel_riesgo, observaciones, activo,
    created_at, updated_at, created_by, updated_by
) VALUES (
    :id, :codigo, :razon_social, :nombre_comercial, :tipo_sociedad, :ano_creacion, :actividad_principal,
    :empresa_operativa_patrimonial, :representante_legal, :tiene_junta_directiva, :tiene_revisor_fiscal,
    :tiene_inmuebles_propios, :tiene_empleados, :deudas_creditos_importantes, :estatutos_actualizados,
    :acuerdo_accionistas, :libro_accionistas_actualizado, :nivel_riesgo, :observaciones, 1,
    UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
) ON DUPLICATE KEY UPDATE
    razon_social = VALUES(razon_social),
    nombre_comercial = VALUES(nombre_comercial),
    tipo_sociedad = VALUES(tipo_sociedad),
    ano_creacion = VALUES(ano_creacion),
    actividad_principal = VALUES(actividad_principal),
    empresa_operativa_patrimonial = VALUES(empresa_operativa_patrimonial),
    representante_legal = VALUES(representante_legal),
    tiene_junta_directiva = VALUES(tiene_junta_directiva),
    tiene_revisor_fiscal = VALUES(tiene_revisor_fiscal),
    tiene_inmuebles_propios = VALUES(tiene_inmuebles_propios),
    tiene_empleados = VALUES(tiene_empleados),
    deudas_creditos_importantes = VALUES(deudas_creditos_importantes),
    estatutos_actualizados = VALUES(estatutos_actualizados),
    acuerdo_accionistas = VALUES(acuerdo_accionistas),
    libro_accionistas_actualizado = VALUES(libro_accionistas_actualizado),
    nivel_riesgo = VALUES(nivel_riesgo),
    observaciones = VALUES(observaciones),
    activo = 1,
    updated_at = UTC_TIMESTAMP(6),
    updated_by = VALUES(updated_by)');

foreach (companySeed() as $row) {
    $companyStmt->execute($row + ['id' => Uuid::v4(), 'created_by' => $actor, 'updated_by' => $actor]);
}

$shareholderStmt = $pdo->prepare('INSERT INTO protocolo_accionistas (
    id, codigo, empresa, accionista, vinculo_familiar, numero_acciones_cuotas, porcentaje, ano_ingreso,
    forma_adquisicion, valor_pagado_aportado, quien_aporto_recursos, estado_civil_adquirir,
    convivia_en_ese_momento, existe_documento_adquisicion, registro_libro_accionistas, restriccion_vigente,
    valor_estimado_actual, observaciones, activo, created_at, updated_at, created_by, updated_by
) VALUES (
    :id, :codigo, :empresa, :accionista, :vinculo_familiar, :numero_acciones_cuotas, :porcentaje, :ano_ingreso,
    :forma_adquisicion, :valor_pagado_aportado, :quien_aporto_recursos, :estado_civil_adquirir,
    :convivia_en_ese_momento, :existe_documento_adquisicion, :registro_libro_accionistas, :restriccion_vigente,
    :valor_estimado_actual, :observaciones, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
) ON DUPLICATE KEY UPDATE
    empresa = VALUES(empresa),
    accionista = VALUES(accionista),
    vinculo_familiar = VALUES(vinculo_familiar),
    numero_acciones_cuotas = VALUES(numero_acciones_cuotas),
    porcentaje = VALUES(porcentaje),
    ano_ingreso = VALUES(ano_ingreso),
    forma_adquisicion = VALUES(forma_adquisicion),
    valor_pagado_aportado = VALUES(valor_pagado_aportado),
    quien_aporto_recursos = VALUES(quien_aporto_recursos),
    estado_civil_adquirir = VALUES(estado_civil_adquirir),
    convivia_en_ese_momento = VALUES(convivia_en_ese_momento),
    existe_documento_adquisicion = VALUES(existe_documento_adquisicion),
    registro_libro_accionistas = VALUES(registro_libro_accionistas),
    restriccion_vigente = VALUES(restriccion_vigente),
    valor_estimado_actual = VALUES(valor_estimado_actual),
    observaciones = VALUES(observaciones),
    activo = 1,
    updated_at = UTC_TIMESTAMP(6),
    updated_by = VALUES(updated_by)');

foreach (shareholderSeed() as $row) {
    $shareholderStmt->execute($row + ['id' => Uuid::v4(), 'created_by' => $actor, 'updated_by' => $actor]);
}

echo "Instalacion completada. Protocolo, 01_Familia, 02_Empresas, 03_Accionistas, 04_Patrimonio y 05_Documentos listos.\n";

function catalogId(PDO $pdo, string $code): string
{
    $stmt = $pdo->prepare('SELECT id FROM pf_catalogos WHERE codigo = :codigo');
    $stmt->execute(['codigo' => $code]);
    $id = $stmt->fetchColumn();
    if (!is_string($id) || $id === '') {
        throw new RuntimeException('No fue posible resolver el catalogo ' . $code);
    }
    return $id;
}

function itemId(PDO $pdo, string $catalogId, string $code): string
{
    $stmt = $pdo->prepare('SELECT id FROM pf_catalogo_items WHERE catalogo_id = :catalogo_id AND codigo = :codigo');
    $stmt->execute(['catalogo_id' => $catalogId, 'codigo' => $code]);
    $id = $stmt->fetchColumn();
    if (!is_string($id) || $id === '') {
        throw new RuntimeException('No fue posible resolver el item ' . $code);
    }
    return $id;
}

function itemCode(string $value): string
{
    return substr(CatalogSeed::code($value), 0, 120);
}

/** @return list<array<string, mixed>> */
function familySeed(): array
{
    return [
        [
            'codigo' => 'FAM-001',
            'nombre_completo' => 'Nassif Abuita Nassar',
            'tipo_vinculo' => 'Fundador(a)',
            'generacion' => 'Primera Generacion',
            'edad' => 65,
            'estado_civil' => 'Divorciado(a)',
            'ano_matrimonio_convivencia' => null,
            'tiene_capitulaciones' => 'No',
            'sociedad_conyugal_liquidada' => 'Si',
            'numero_hijos' => 2,
            'trabaja_empresa_familiar' => 'Si',
            'empresa_donde_trabaja' => 'Soluciones Comerciales y Cosntructuvas S.A.S',
            'cargo' => 'Representante Legal',
            'es_accionista' => 'Si',
            'participa_decisiones' => 'Si',
            'observaciones' => null,
        ],
        [
            'codigo' => 'FAM-002',
            'nombre_completo' => 'Gloria Maria Correa Rivera',
            'tipo_vinculo' => 'Fundador(a)',
            'generacion' => 'Primera Generacion',
            'edad' => 68,
            'estado_civil' => 'Divorciado(a)',
            'ano_matrimonio_convivencia' => null,
            'tiene_capitulaciones' => 'No',
            'sociedad_conyugal_liquidada' => 'Si',
            'numero_hijos' => 2,
            'trabaja_empresa_familiar' => 'Si',
            'empresa_donde_trabaja' => 'Soluciones Comerciales y Cosntructuvas S.A.S',
            'cargo' => 'Representante Legal Suplente',
            'es_accionista' => 'Si',
            'participa_decisiones' => 'Si',
            'observaciones' => null,
        ],
        [
            'codigo' => 'FAM-003',
            'nombre_completo' => 'Said Latif Abuita Correa',
            'tipo_vinculo' => 'Hijo(a)',
            'generacion' => 'Segunda Generacion',
            'edad' => 35,
            'estado_civil' => 'Union libre',
            'ano_matrimonio_convivencia' => null,
            'tiene_capitulaciones' => 'No',
            'sociedad_conyugal_liquidada' => null,
            'numero_hijos' => 0,
            'trabaja_empresa_familiar' => 'Si',
            'empresa_donde_trabaja' => 'Soluciones Comerciales y Cosntructuvas S.A.S',
            'cargo' => 'Gerente Comercial',
            'es_accionista' => 'Si',
            'participa_decisiones' => 'Si',
            'observaciones' => null,
        ],
        [
            'codigo' => 'FAM-004',
            'nombre_completo' => 'Karam Nassif Abuita Correa',
            'tipo_vinculo' => 'Hijo(a)',
            'generacion' => 'Segunda Generacion',
            'edad' => 31,
            'estado_civil' => 'Soltero(a)',
            'ano_matrimonio_convivencia' => null,
            'tiene_capitulaciones' => 'No',
            'sociedad_conyugal_liquidada' => null,
            'numero_hijos' => 0,
            'trabaja_empresa_familiar' => 'No',
            'empresa_donde_trabaja' => 'Traditun S.A.S',
            'cargo' => 'Representante Legal',
            'es_accionista' => 'Si',
            'participa_decisiones' => 'Si',
            'observaciones' => null,
        ],
    ];
}

/** @return list<array<string, mixed>> */
function companySeed(): array
{
    return [
        [
            'codigo' => 'EMP-001',
            'razon_social' => 'Soluciones Comerciales y Constructivas',
            'nombre_comercial' => 'Sk&c',
            'tipo_sociedad' => 'S.A.S.',
            'ano_creacion' => '2013-05-31',
            'actividad_principal' => 'CIIU 6820 - Actividades inmobiliarias realizadas a cambio de una retribucion o por contrata. Incluye los servicios de bienes raices prestados por terceros.',
            'empresa_operativa_patrimonial' => 'Operativa',
            'representante_legal' => 'Nassif Abuita Nassar',
            'tiene_junta_directiva' => 'No',
            'tiene_revisor_fiscal' => 'No',
            'tiene_inmuebles_propios' => 'Si',
            'tiene_empleados' => 'Si',
            'deudas_creditos_importantes' => 'Prestamos Bancarios',
            'estatutos_actualizados' => 'No',
            'acuerdo_accionistas' => 'No',
            'libro_accionistas_actualizado' => 'No',
            'nivel_riesgo' => 'Medio',
            'observaciones' => 'Definir las siglas que cambia a SK&C-SuCasa Inmobiliaria. No contamos con Junta directiva: los organos de direccion son la Asamblea General.',
        ],
        [
            'codigo' => 'EMP-002',
            'razon_social' => 'Disenos y Soluciones Creativas',
            'nombre_comercial' => 'DSC',
            'tipo_sociedad' => 'S.A.S.',
            'ano_creacion' => '2005-06-08',
            'actividad_principal' => 'CIIU 6820 - Actividades inmobiliarias realizadas a cambio de una retribucion o por contrata. Incluye los servicios de bienes raices prestados por terceros.',
            'empresa_operativa_patrimonial' => 'Patrimonial',
            'representante_legal' => 'Nassif Abuita Nassar',
            'tiene_junta_directiva' => 'Si',
            'tiene_revisor_fiscal' => 'No',
            'tiene_inmuebles_propios' => 'Si',
            'tiene_empleados' => 'No',
            'deudas_creditos_importantes' => 'No Posee',
            'estatutos_actualizados' => 'No',
            'acuerdo_accionistas' => 'No',
            'libro_accionistas_actualizado' => 'No',
            'nivel_riesgo' => 'Bajo',
            'observaciones' => 'Empresa patrimonial. No tiene operaciones comerciales; solo maneja 2 contratos de terceros que no se han podido trasladar a Soluciones Comerciales y Constructivas y el ingreso por el alquiler del inmueble propio.',
        ],
    ];
}

/** @return list<array<string, mixed>> */
function shareholderSeed(): array
{
    $empresa1 = 'Soluciones Comerciales y Constructivas';
    $empresa2 = 'Disenos y Soluciones Creativas';
    return [
        ['codigo' => 'ACC-001', 'empresa' => $empresa1, 'accionista' => 'Nassif Abuita Nassar', 'vinculo_familiar' => 'Fundador(a)', 'numero_acciones_cuotas' => 6120, 'porcentaje' => 34.00, 'ano_ingreso' => 2013, 'forma_adquisicion' => 'Constitucion', 'valor_pagado_aportado' => 61200000, 'quien_aporto_recursos' => 'Nassif Abuita Nassar', 'estado_civil_adquirir' => 'Separado(a)', 'convivia_en_ese_momento' => 'Si', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => 218968405, 'observaciones' => null],
        ['codigo' => 'ACC-002', 'empresa' => $empresa1, 'accionista' => 'Gloria Maria Correa Rivera', 'vinculo_familiar' => 'Fundador(a)', 'numero_acciones_cuotas' => 6480, 'porcentaje' => 36.00, 'ano_ingreso' => 2013, 'forma_adquisicion' => 'Constitucion', 'valor_pagado_aportado' => 64800000, 'quien_aporto_recursos' => 'Gloria Maria Correa Rivera', 'estado_civil_adquirir' => 'Separado(a)', 'convivia_en_ese_momento' => 'Si', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => 231848899, 'observaciones' => null],
        ['codigo' => 'ACC-003', 'empresa' => $empresa1, 'accionista' => 'Said Latif Abuita Correa', 'vinculo_familiar' => 'Hijo(a)', 'numero_acciones_cuotas' => 2700, 'porcentaje' => 15.00, 'ano_ingreso' => 2013, 'forma_adquisicion' => 'Constitucion', 'valor_pagado_aportado' => 27000000, 'quien_aporto_recursos' => 'Said Latif Abuita Correa', 'estado_civil_adquirir' => 'Soltero(a)', 'convivia_en_ese_momento' => 'No', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => 96603708, 'observaciones' => null],
        ['codigo' => 'ACC-004', 'empresa' => $empresa1, 'accionista' => 'Karam Nassif Abuita Correa', 'vinculo_familiar' => 'Hijo(a)', 'numero_acciones_cuotas' => 2700, 'porcentaje' => 15.00, 'ano_ingreso' => 2013, 'forma_adquisicion' => 'Constitucion', 'valor_pagado_aportado' => 27000000, 'quien_aporto_recursos' => 'Karam Nassif Abuita Correa', 'estado_civil_adquirir' => 'Soltero(a)', 'convivia_en_ese_momento' => 'No', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => 96603708, 'observaciones' => null],
        ['codigo' => 'ACC-005', 'empresa' => $empresa2, 'accionista' => 'Gloria Maria Correa Rivera', 'vinculo_familiar' => 'Fundador(a)', 'numero_acciones_cuotas' => 34020, 'porcentaje' => 21.00, 'ano_ingreso' => 2005, 'forma_adquisicion' => 'Constitucion', 'valor_pagado_aportado' => 34020000, 'quien_aporto_recursos' => 'Gloria Maria Correa Rivera', 'estado_civil_adquirir' => 'Separado(a)', 'convivia_en_ese_momento' => 'Si', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => null, 'observaciones' => null],
        ['codigo' => 'ACC-006', 'empresa' => $empresa2, 'accionista' => 'Nassif Abuita Nassar', 'vinculo_familiar' => 'Fundador(a)', 'numero_acciones_cuotas' => 30780, 'porcentaje' => 19.00, 'ano_ingreso' => 2005, 'forma_adquisicion' => 'Cesion', 'valor_pagado_aportado' => 30780000, 'quien_aporto_recursos' => 'Gloria Maria Correa Rivera', 'estado_civil_adquirir' => 'Separado(a)', 'convivia_en_ese_momento' => 'Si', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => null, 'observaciones' => null],
        ['codigo' => 'ACC-007', 'empresa' => $empresa2, 'accionista' => 'Said Latif Abuita Correa', 'vinculo_familiar' => 'Hijo(a)', 'numero_acciones_cuotas' => 8100, 'porcentaje' => 5.00, 'ano_ingreso' => 2016, 'forma_adquisicion' => 'Cesion', 'valor_pagado_aportado' => 8100000, 'quien_aporto_recursos' => 'Gloria Maria Correa Rivera', 'estado_civil_adquirir' => 'Soltero(a)', 'convivia_en_ese_momento' => 'No', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => null, 'observaciones' => null],
        ['codigo' => 'ACC-008', 'empresa' => $empresa2, 'accionista' => 'Karam Nassif Abuita Correa', 'vinculo_familiar' => 'Hijo(a)', 'numero_acciones_cuotas' => 8100, 'porcentaje' => 5.00, 'ano_ingreso' => 2016, 'forma_adquisicion' => 'Cesion', 'valor_pagado_aportado' => 8100000, 'quien_aporto_recursos' => 'Gloria Maria Correa Rivera', 'estado_civil_adquirir' => 'Soltero(a)', 'convivia_en_ese_momento' => 'No', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => null, 'observaciones' => null],
    ];
}
