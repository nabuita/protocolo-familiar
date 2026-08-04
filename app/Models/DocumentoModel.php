<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\ProtocolLists;
use App\Support\Uuid;
use PDO;
use RuntimeException;

final readonly class DocumentoModel
{
    public function __construct(private PDO $pdo, private string $rootPath)
    {
        $this->ensureSchema();
    }

    /** @param list<array<string,mixed>> $empresas @param list<array<string,mixed>> $familia @param list<array<string,mixed>> $accionistas @param list<array<string,mixed>> $activos */
    public function syncChecklist(array $empresas, array $familia, array $accionistas, array $activos, string $actor): void
    {
        $known = $this->existingChecklistKeys();
        foreach (ProtocolLists::documentosPorSujeto()['Protocolo general'] as $documento) {
            $this->ensureChecklistRow('Protocolo general', 'GEN', 'Protocolo familiar', $documento, 'Gobierno familiar', 'Medio', $actor, $known);
        }
        foreach ($empresas as $empresa) {
            $codigo = (string) ($empresa['codigo'] ?? '');
            $nombre = (string) ($empresa['razon_social'] ?? '');
            foreach ($this->companyDocumentRequirements($empresa) as $documento) {
                $this->ensureChecklistRow('Empresa', $codigo, $nombre, $documento['documento'], $documento['categoria'], $documento['riesgo'], $actor, $known);
            }
        }
        foreach ($familia as $persona) {
            $codigo = (string) ($persona['codigo'] ?? '');
            $nombre = (string) ($persona['nombre_completo'] ?? '');
            foreach ($this->familyDocumentRequirements($persona) as $documento) {
                $this->ensureChecklistRow('Persona/Familia', $codigo, $nombre, $documento['documento'], $documento['categoria'], $documento['riesgo'], $actor, $known);
            }
        }
        foreach ($accionistas as $accionista) {
            $codigo = (string) ($accionista['codigo'] ?? '');
            $nombre = trim((string) ($accionista['accionista'] ?? '') . ' - ' . (string) ($accionista['empresa'] ?? ''));
            foreach ($this->shareholderDocumentRequirements($accionista) as $documento) {
                $this->ensureChecklistRow('Accionista/Participacion', $codigo, $nombre, $documento['documento'], $documento['categoria'], $documento['riesgo'], $actor, $known);
            }
        }
        foreach ($activos as $activo) {
            $codigo = (string) ($activo['codigo'] ?? '');
            $nombre = trim((string) ($activo['nombre_descripcion'] ?? '') . ' - ' . (string) ($activo['identificador'] ?? ''));
            foreach (ProtocolLists::documentosPorSujeto()['Activo/Patrimonio'] as $documento) {
                $categoria = in_array($documento, ['Avaluo o valoracion', 'Extracto o certificado financiero'], true) ? 'Patrimonio' : 'Constitucion y existencia';
                $this->ensureChecklistRow('Activo/Patrimonio', $codigo, $nombre, $documento, $categoria, 'Medio', $actor, $known);
            }
            $tipoActivo = (string) ($activo['tipo_activo'] ?? '');
            foreach (ProtocolLists::documentosPorTipoActivo()[$tipoActivo] ?? [] as $documento) {
                $this->ensureChecklistRow('Activo/Patrimonio', $codigo, $nombre, $documento['documento'], $documento['categoria'], $documento['riesgo'], $actor, $known);
            }
        }
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM protocolo_documentos WHERE activo = 1 ORDER BY FIELD(estado, "Faltante", "Vencido", "Solicitado", "En revision", "Recibido", "No aplica"), sujeto_tipo, sujeto_nombre, documento_tipo, created_at')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function forSubject(string $sujetoTipo, string $sujetoCodigo): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocolo_documentos WHERE activo = 1 AND sujeto_tipo = :sujeto_tipo AND sujeto_codigo = :sujeto_codigo ORDER BY FIELD(estado, "Faltante", "Vencido", "Solicitado", "En revision", "Recibido", "No aplica"), documento_tipo, created_at');
        $stmt->execute(['sujeto_tipo' => $sujetoTipo, 'sujeto_codigo' => $sujetoCodigo]);
        return $stmt->fetchAll();
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        $summary = ['total' => 0, 'faltantes' => 0, 'recibidos' => 0, 'vencidos' => 0, 'solicitados' => 0, 'revision' => 0];
        $rows = $this->pdo->query('SELECT estado, COUNT(*) total FROM protocolo_documentos WHERE activo = 1 GROUP BY estado')->fetchAll();
        foreach ($rows as $row) {
            $count = (int) ($row['total'] ?? 0);
            $summary['total'] += $count;
            $estado = (string) ($row['estado'] ?? '');
            if ($estado === 'Faltante') {
                $summary['faltantes'] = $count;
            } elseif ($estado === 'Recibido') {
                $summary['recibidos'] = $count;
            } elseif ($estado === 'Vencido') {
                $summary['vencidos'] = $count;
            } elseif ($estado === 'Solicitado') {
                $summary['solicitados'] = $count;
            } elseif ($estado === 'En revision') {
                $summary['revision'] = $count;
            }
        }
        return $summary;
    }

    /** @param array<string,mixed> $data @param array<string,mixed>|null $file */
    public function create(array $data, ?array $file, string $actor): array
    {
        $id = Uuid::v4();
        $codigo = $this->nextCode();
        $params = $this->params($data, $file);
        $stmt = $this->pdo->prepare('INSERT INTO protocolo_documentos (
            id, codigo, sujeto_tipo, sujeto_codigo, sujeto_nombre, categoria, documento_tipo,
            requerido, estado, nivel_riesgo, fecha_documento, fecha_vencimiento, archivo_nombre,
            archivo_ruta, archivo_mime, archivo_tamano, observaciones, activo, created_at, updated_at, created_by, updated_by
        ) VALUES (
            :id, :codigo, :sujeto_tipo, :sujeto_codigo, :sujeto_nombre, :categoria, :documento_tipo,
            :requerido, :estado, :nivel_riesgo, :fecha_documento, :fecha_vencimiento, :archivo_nombre,
            :archivo_ruta, :archivo_mime, :archivo_tamano, :observaciones, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
        )');
        $stmt->execute($params + ['id' => $id, 'codigo' => $codigo, 'created_by' => $actor, 'updated_by' => $actor]);
        return $this->find($id);
    }

    /** @param array<string,mixed> $data @param array<string,mixed>|null $file */
    public function update(string $id, array $data, ?array $file, string $actor): array
    {
        $existing = $this->find($id);
        $params = $this->params($data, $file, $existing);
        $stmt = $this->pdo->prepare('UPDATE protocolo_documentos SET
            sujeto_tipo = :sujeto_tipo,
            sujeto_codigo = :sujeto_codigo,
            sujeto_nombre = :sujeto_nombre,
            categoria = :categoria,
            documento_tipo = :documento_tipo,
            requerido = :requerido,
            estado = :estado,
            nivel_riesgo = :nivel_riesgo,
            fecha_documento = :fecha_documento,
            fecha_vencimiento = :fecha_vencimiento,
            archivo_nombre = :archivo_nombre,
            archivo_ruta = :archivo_ruta,
            archivo_mime = :archivo_mime,
            archivo_tamano = :archivo_tamano,
            observaciones = :observaciones,
            updated_at = UTC_TIMESTAMP(6),
            updated_by = :updated_by
            WHERE id = :id AND activo = 1');
        $stmt->execute($params + ['id' => $id, 'updated_by' => $actor]);
        return $this->find($id);
    }

    public function deactivate(string $id, string $actor): void
    {
        $stmt = $this->pdo->prepare('UPDATE protocolo_documentos SET activo = 0, updated_at = UTC_TIMESTAMP(6), updated_by = :actor WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id, 'actor' => $actor]);
    }

    /** @return array<string,mixed> */
    public function find(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocolo_documentos WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Documento no encontrado.');
        }
        return $row;
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_documentos (
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
    }

    /** @param array<string,mixed> $persona @return list<array{documento:string,categoria:string,riesgo:string}> */
    private function familyDocumentRequirements(array $persona): array
    {
        $items = [
            ['documento' => 'Cedula de ciudadania o documento de identidad', 'categoria' => 'Identificacion familiar', 'riesgo' => 'Alto'],
            ['documento' => 'Registro civil de nacimiento', 'categoria' => 'Identificacion familiar', 'riesgo' => 'Medio'],
            ['documento' => 'Soporte de estado civil actual', 'categoria' => 'Estado civil y regimen patrimonial', 'riesgo' => 'Alto'],
        ];

        $estadoCivil = strtolower((string) ($persona['estado_civil'] ?? ''));
        $regimen = strtolower((string) ($persona['regimen_patrimonial'] ?? ''));
        $trabaja = strtolower((string) ($persona['trabaja_empresa_familiar'] ?? ''));
        $capitulaciones = strtolower((string) ($persona['tiene_capitulaciones'] ?? ''));
        $liquidada = strtolower((string) ($persona['sociedad_conyugal_liquidada'] ?? ''));
        $poderes = strtolower((string) ($persona['tiene_poderes'] ?? ''));
        $testamento = strtolower((string) ($persona['tiene_testamento'] ?? ''));

        if (str_contains($estadoCivil, 'casado') || str_contains($estadoCivil, 'union') || str_contains($regimen, 'conyugal') || str_contains($regimen, 'patrimonial')) {
            $items[] = ['documento' => 'Registro civil de matrimonio o declaracion de union marital', 'categoria' => 'Estado civil y regimen patrimonial', 'riesgo' => 'Alto'];
        }
        if (str_contains($capitulaciones, 'si') || str_contains($regimen, 'capitulaciones')) {
            $items[] = ['documento' => 'Capitulaciones matrimoniales o acuerdo patrimonial', 'categoria' => 'Estado civil y regimen patrimonial', 'riesgo' => 'Alto'];
        }
        if (str_contains($liquidada, 'si') || str_contains($regimen, 'liquidada') || str_contains($estadoCivil, 'divorciado')) {
            $items[] = ['documento' => 'Escritura o sentencia de liquidacion de sociedad conyugal o patrimonial', 'categoria' => 'Estado civil y regimen patrimonial', 'riesgo' => 'Alto'];
        }
        if (str_contains($trabaja, 'si')) {
            $items[] = ['documento' => 'Contrato o soporte de vinculacion con empresa familiar', 'categoria' => 'Relacion laboral o administrativa', 'riesgo' => 'Medio'];
        }
        if (str_contains($poderes, 'si')) {
            $items[] = ['documento' => 'Poderes vigentes otorgados o recibidos', 'categoria' => 'Administracion y poderes', 'riesgo' => 'Alto'];
        }
        if (str_contains($testamento, 'si')) {
            $items[] = ['documento' => 'Testamento o declaracion sucesoral informada', 'categoria' => 'Sucesiones y testamentos', 'riesgo' => 'Alto'];
        }

        return $items;
    }

    /** @param array<string,mixed> $empresa @return list<array{documento:string,categoria:string,riesgo:string}> */
    private function companyDocumentRequirements(array $empresa): array
    {
        $year = (int) date('Y');
        $items = [
            ['documento' => 'Acta de constitucion', 'categoria' => 'Empresa / 01_Corporativo', 'riesgo' => 'Alto'],
            ['documento' => 'Certificado de existencia y representacion legal vigente', 'categoria' => 'Empresa / 01_Corporativo', 'riesgo' => 'Alto'],
            ['documento' => 'RUT actualizado', 'categoria' => 'Empresa / 01_Corporativo', 'riesgo' => 'Alto'],
            ['documento' => 'Estatutos vigentes', 'categoria' => 'Empresa / 01_Corporativo', 'riesgo' => 'Alto'],
            ['documento' => 'Reformas estatutarias', 'categoria' => 'Empresa / 01_Corporativo', 'riesgo' => 'Medio'],
            ['documento' => 'Libro de accionistas actualizado', 'categoria' => 'Empresa / 01_Corporativo', 'riesgo' => 'Alto'],
            ['documento' => 'Actas de asamblea o maximo organo social', 'categoria' => 'Empresa / 01_Corporativo', 'riesgo' => 'Medio'],
            ['documento' => 'Estados financieros ' . $year, 'categoria' => 'Empresa / 02_Contable / ' . $year, 'riesgo' => 'Alto'],
            ['documento' => 'Notas a los estados financieros ' . $year, 'categoria' => 'Empresa / 02_Contable / ' . $year, 'riesgo' => 'Alto'],
            ['documento' => 'Balance de prueba cierre ' . $year, 'categoria' => 'Empresa / 02_Contable / ' . $year, 'riesgo' => 'Medio'],
            ['documento' => 'Declaracion de renta ' . $year, 'categoria' => 'Empresa / 04_Tributario anual / ' . $year, 'riesgo' => 'Alto'],
            ['documento' => 'Informacion exogena o medios magneticos ' . $year, 'categoria' => 'Empresa / 04_Tributario anual / ' . $year, 'riesgo' => 'Medio'],
        ];

        if (strtolower((string) ($empresa['tiene_junta_directiva'] ?? '')) === 'si') {
            $items[] = ['documento' => 'Actas de junta directiva ' . $year, 'categoria' => 'Empresa / 01_Corporativo', 'riesgo' => 'Medio'];
        }
        if (strtolower((string) ($empresa['tiene_revisor_fiscal'] ?? '')) === 'si') {
            $items[] = ['documento' => 'Nombramiento y aceptacion de revisor fiscal', 'categoria' => 'Empresa / 01_Corporativo', 'riesgo' => 'Alto'];
            $items[] = ['documento' => 'Dictamen de revisor fiscal ' . $year, 'categoria' => 'Empresa / 02_Contable / ' . $year, 'riesgo' => 'Alto'];
        }
        if (strtolower((string) ($empresa['tiene_empleados'] ?? '')) === 'si') {
            $items[] = ['documento' => 'Soportes de nomina y seguridad social ' . $year, 'categoria' => 'Empresa / 03_Laboral y seguridad social / ' . $year, 'riesgo' => 'Medio'];
        }

        $months = [
            '01' => '01_Enero',
            '02' => '02_Febrero',
            '03' => '03_Marzo',
            '04' => '04_Abril',
            '05' => '05_Mayo',
            '06' => '06_Junio',
            '07' => '07_Julio',
            '08' => '08_Agosto',
            '09' => '09_Septiembre',
            '10' => '10_Octubre',
            '11' => '11_Noviembre',
            '12' => '12_Diciembre',
        ];
        $monthNumber = date('m');
        $month = $months[$monthNumber] ?? date('m');
        $category = 'Empresa / 05_Tributario mensual / ' . $year . ' / ' . $month;
        $items[] = ['documento' => 'Soportes liquidacion IVA ' . $year . '-' . $monthNumber, 'categoria' => $category, 'riesgo' => 'Medio'];
        $items[] = ['documento' => 'Soportes retencion en la fuente ' . $year . '-' . $monthNumber, 'categoria' => $category, 'riesgo' => 'Medio'];
        $items[] = ['documento' => 'Soportes ICA ' . $year . '-' . $monthNumber, 'categoria' => $category, 'riesgo' => 'Medio'];
        $items[] = ['documento' => 'Conciliacion bancaria y soportes contables ' . $year . '-' . $monthNumber, 'categoria' => $category, 'riesgo' => 'Medio'];

        return $items;
    }

    /** @param array<string,mixed> $accionista @return list<array{documento:string,categoria:string,riesgo:string}> */
    private function shareholderDocumentRequirements(array $accionista): array
    {
        $items = [
            ['documento' => 'Titulo o certificado de acciones', 'categoria' => 'Acciones y participaciones / Titularidad', 'riesgo' => 'Critico'],
            ['documento' => 'Soporte de adquisicion', 'categoria' => 'Acciones y participaciones / Origen', 'riesgo' => 'Alto'],
            ['documento' => 'Registro en libro de accionistas', 'categoria' => 'Acciones y participaciones / Registro societario', 'riesgo' => 'Alto'],
            ['documento' => 'Comprobante de pago o aporte', 'categoria' => 'Acciones y participaciones / Origen', 'riesgo' => 'Alto'],
            ['documento' => 'Certificacion de composicion accionaria', 'categoria' => 'Acciones y participaciones / Registro societario', 'riesgo' => 'Medio'],
        ];

        $forma = strtolower((string) ($accionista['forma_adquisicion'] ?? ''));
        $restriccion = strtolower((string) ($accionista['restriccion_vigente'] ?? ''));
        $tipoRestriccion = strtolower((string) ($accionista['tipo_restriccion'] ?? ''));
        $estadoCivil = strtolower((string) ($accionista['estado_civil_adquirir'] ?? ''));
        $regimen = strtolower((string) ($accionista['regimen_patrimonial_adquirir'] ?? ''));
        $valor = (string) ($accionista['valor_estimado_actual'] ?? '');

        if (str_contains($forma, 'cesion') || str_contains($forma, 'compra') || str_contains($forma, 'donacion')) {
            $items[] = ['documento' => 'Contrato de cesion', 'categoria' => 'Acciones y participaciones / Origen', 'riesgo' => 'Alto'];
        }
        if (str_contains($restriccion, 'si') || ($tipoRestriccion !== '' && !str_contains($tipoRestriccion, 'no aplica'))) {
            $items[] = ['documento' => 'Restricciones o acuerdos vigentes', 'categoria' => 'Acciones y participaciones / Restricciones', 'riesgo' => 'Alto'];
        }
        if ($valor !== '' && $valor !== '0') {
            $items[] = ['documento' => 'Documento de valoracion de la participacion', 'categoria' => 'Acciones y participaciones / Valoracion', 'riesgo' => 'Medio'];
        }
        if ($estadoCivil !== '' || $regimen !== '') {
            $items[] = ['documento' => 'Soporte de estado civil al momento de adquirir', 'categoria' => 'Estado civil y regimen patrimonial', 'riesgo' => 'Alto'];
        }

        return $items;
    }

    /** @param array<string,bool> $known */
    private function ensureChecklistRow(string $sujetoTipo, string $sujetoCodigo, string $sujetoNombre, string $documentoTipo, string $categoria, string $riesgo, string $actor, array &$known): void
    {
        if ($sujetoCodigo === '' || $sujetoNombre === '') {
            return;
        }
        $key = $sujetoTipo . '|' . $sujetoCodigo . '|' . $documentoTipo;
        if (isset($known[$key])) {
            return;
        }
        $this->create([
            'sujeto_tipo' => $sujetoTipo,
            'sujeto_codigo' => $sujetoCodigo,
            'sujeto_nombre' => $sujetoNombre,
            'categoria' => $categoria,
            'documento_tipo' => $documentoTipo,
            'requerido' => 'Si',
            'estado' => 'Faltante',
            'nivel_riesgo' => $riesgo,
        ], null, $actor);
        $known[$key] = true;
    }

    /** @return array<string,bool> */
    private function existingChecklistKeys(): array
    {
        $keys = [];
        $rows = $this->pdo->query('SELECT sujeto_tipo, sujeto_codigo, documento_tipo FROM protocolo_documentos WHERE activo = 1')->fetchAll();
        foreach ($rows as $row) {
            $keys[(string) $row['sujeto_tipo'] . '|' . (string) $row['sujeto_codigo'] . '|' . (string) $row['documento_tipo']] = true;
        }
        return $keys;
    }

    private function nextCode(): string
    {
        $next = (int) $this->pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) + 1 FROM protocolo_documentos")->fetchColumn();
        return sprintf('DOC-%03d', $next);
    }

    /** @param array<string,mixed> $data @param array<string,mixed>|null $file @param array<string,mixed>|null $existing @return array<string,mixed> */
    private function params(array $data, ?array $file, ?array $existing = null): array
    {
        $upload = $this->storeFile($file);
        $hasFile = $upload !== null || is_string($existing['archivo_ruta'] ?? null);
        $estado = $this->optional($data['estado'] ?? null) ?? ($hasFile ? 'Recibido' : 'Faltante');
        if ($upload !== null && in_array($estado, ['Faltante', 'Solicitado'], true)) {
            $estado = 'Recibido';
        }

        return [
            'sujeto_tipo' => $this->required($data['sujeto_tipo'] ?? null, 'Tipo de sujeto es obligatorio.'),
            'sujeto_codigo' => $this->optional($data['sujeto_codigo'] ?? null),
            'sujeto_nombre' => $this->required($data['sujeto_nombre'] ?? null, 'Sujeto es obligatorio.'),
            'categoria' => $this->required($data['categoria'] ?? null, 'Categoria es obligatoria.'),
            'documento_tipo' => $this->required($data['documento_tipo'] ?? null, 'Tipo de documento es obligatorio.'),
            'requerido' => $this->optional($data['requerido'] ?? null) ?? 'Si',
            'estado' => $estado,
            'nivel_riesgo' => $this->optional($data['nivel_riesgo'] ?? null),
            'fecha_documento' => $this->date($data['fecha_documento'] ?? null),
            'fecha_vencimiento' => $this->date($data['fecha_vencimiento'] ?? null),
            'archivo_nombre' => $upload['name'] ?? $existing['archivo_nombre'] ?? null,
            'archivo_ruta' => $upload['path'] ?? $existing['archivo_ruta'] ?? null,
            'archivo_mime' => $upload['mime'] ?? $existing['archivo_mime'] ?? null,
            'archivo_tamano' => $upload['size'] ?? $existing['archivo_tamano'] ?? null,
            'observaciones' => $this->optional($data['observaciones'] ?? null),
        ];
    }

    /** @param array<string,mixed>|null $file @return array{name:string,path:string,mime:string,size:int}|null */
    private function storeFile(?array $file): ?array
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No fue posible subir el archivo.');
        }
        $original = is_string($file['name'] ?? null) ? $file['name'] : 'documento';
        $tmp = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
        $size = (int) ($file['size'] ?? 0);
        if ($size > 12 * 1024 * 1024) {
            throw new RuntimeException('El archivo supera 12 MB.');
        }
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('Formato de archivo no permitido.');
        }
        $dir = $this->rootPath . '/public/uploads/protocolo-documentos/' . date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No fue posible crear la carpeta de documentos.');
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($original, PATHINFO_FILENAME));
        $safeName = trim((string) $safeName, '-') ?: 'documento';
        $targetName = $safeName . '-' . substr(str_replace('-', '', Uuid::v4()), 0, 10) . '.' . $extension;
        $target = $dir . '/' . $targetName;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('No fue posible guardar el archivo.');
        }
        $relative = '/uploads/protocolo-documentos/' . date('Y/m') . '/' . $targetName;
        return [
            'name' => $original,
            'path' => $relative,
            'mime' => is_string($file['type'] ?? null) ? $file['type'] : 'application/octet-stream',
            'size' => $size,
        ];
    }

    private function required(mixed $value, string $message): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException($message);
        }
        return trim($value);
    }

    private function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function date(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) !== 1) {
            throw new RuntimeException('Fecha invalida.');
        }
        return trim($value);
    }
}
