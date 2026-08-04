<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\View;
use App\Models\AccionistaModel;
use App\Models\CatalogoModel;
use App\Models\DecisionModel;
use App\Models\DocumentoModel;
use App\Models\EmpresaModel;
use App\Models\FamiliaModel;
use App\Models\PatrimonioModel;
use App\Models\ProtocoloModel;
use App\Models\RiesgoModel;
use App\Support\ProtocolLists;
use RuntimeException;

final class ProtocoloController extends Controller
{
    public function __construct(
        Config $config,
        View $view,
        Auth $auth,
        Csrf $csrf,
        private readonly ProtocoloModel $protocolos,
        private readonly CatalogoModel $catalogos,
        private readonly FamiliaModel $familia,
        private readonly EmpresaModel $empresas,
        private readonly AccionistaModel $accionistas,
        private readonly PatrimonioModel $patrimonio,
        private readonly DocumentoModel $documentos,
        private readonly DecisionModel $decisiones,
        private readonly RiesgoModel $riesgos,
    ) {
        parent::__construct($config, $view, $auth, $csrf);
    }

    public function index(): void
    {
        $familiaRows = $this->familia->all();
        $empresaRows = $this->empresas->all();
        $accionistaRows = $this->accionistas->all();
        $patrimonioRows = $this->patrimonio->all();
        $decisionRows = $this->decisiones->all();
        $riesgoDashboard = $this->riesgos->dashboard();

        $this->html('protocolos/workspace', [
            'tabs' => $this->tabs(),
            'familiaRows' => $familiaRows,
            'familiaOptions' => $this->familiaOptions(),
            'empresaRows' => $empresaRows,
            'empresaOptions' => $this->empresaOptions(),
            'accionistaRows' => $accionistaRows,
            'accionistaTotals' => $this->accionistas->totals(),
            'accionistaOptions' => $this->accionistaOptions(),
            'patrimonioRows' => $patrimonioRows,
            'patrimonioSummary' => $this->patrimonio->summary(),
            'patrimonioByType' => $this->patrimonio->summaryByType(),
            'patrimonioOptions' => $this->patrimonioOptions(),
            'patrimonioSchemas' => ProtocolLists::patrimonioSchemas(),
            'documentoRows' => $this->documentos->all(),
            'documentoSummary' => $this->documentos->summary(),
            'documentoOptions' => $this->documentoOptions(),
            'documentoSuggestions' => ProtocolLists::documentosPorSujeto(),
            'decisionRows' => $decisionRows,
            'decisionSummary' => $this->decisiones->summaryFromRows($decisionRows),
            'decisionOptions' => $this->decisiones->options(),
            'riesgoDashboard' => $riesgoDashboard,
            'riesgoOptions' => $this->riesgos->options(),
            'insuranceAcademy' => $this->insuranceAcademy(),
        ]);
    }

    public function create(): void
    {
        $this->html('protocolos/create', ['error' => null, 'old' => [], 'defaultSections' => $this->defaultSections()]);
    }

    public function show(string $id): void
    {
        $this->html('protocolos/show', ['protocolo' => $this->protocolos->find($id), 'error' => null]);
    }

    /** @param array<string,mixed> $post */
    public function store(array $post): void
    {
        $this->csrf->assert($post['csrf_token'] ?? null);
        try {
            $id = $this->protocolos->create($this->code($post['codigo'] ?? null), $this->text($post['empresa_nombre'] ?? null), $this->text($post['titulo'] ?? null), $this->text($post['descripcion'] ?? null), $this->sections($post), (string) $this->auth->actor());
            $this->redirect('/protocolo-familiar/' . $id);
        } catch (\Throwable $e) {
            $this->html('protocolos/create', ['error' => $e->getMessage(), 'old' => $post, 'defaultSections' => $this->defaultSections()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function updateSections(string $id, array $post): void
    {
        $this->csrf->assert($post['csrf_token'] ?? null);
        $this->protocolos->updateSections($id, $this->sections($post), (string) $this->auth->actor());
        $this->redirect('/protocolo-familiar/' . $id);
    }

    /** @param array<string,mixed> $post */
    public function changeState(string $id, array $post): void
    {
        $this->csrf->assert($post['csrf_token'] ?? null);
        $estado = is_string($post['estado'] ?? null) ? $post['estado'] : 'borrador';
        $this->protocolos->changeState($id, $estado, (string) $this->auth->actor());
        $this->redirect('/protocolo-familiar/' . $id);
    }

    /** @param array<string,mixed> $post */
    public function sign(string $id, array $post): void
    {
        $this->csrf->assert($post['csrf_token'] ?? null);
        $this->protocolos->sign($id, $this->text($post['firmante_nombre'] ?? null), $this->text($post['firmante_cargo'] ?? null), (string) $this->auth->actor());
        $this->redirect('/protocolo-familiar/' . $id);
    }

    /** @param array<string,mixed> $post */
    public function storeFamilia(array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $row = $this->familia->create($post, (string) $this->auth->actor());
            $this->syncDocumentChecklist();
            $this->json(['ok' => true, 'row' => $row, 'documents' => $this->documentos->forSubject('Persona/Familia', (string) $row['codigo'])]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function updateFamilia(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $row = $this->familia->update($id, $post, (string) $this->auth->actor());
            $this->syncDocumentChecklist();
            $this->json(['ok' => true, 'row' => $row, 'documents' => $this->documentos->forSubject('Persona/Familia', (string) $row['codigo'])]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function deleteFamilia(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->familia->deactivate($id, (string) $this->auth->actor());
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function storeEmpresa(array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $row = $this->empresas->create($post, (string) $this->auth->actor());
            $this->syncDocumentChecklist();
            $this->json(['ok' => true, 'row' => $row, 'documents' => $this->documentos->forSubject('Empresa', (string) $row['codigo'])]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function updateEmpresa(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $row = $this->empresas->update($id, $post, (string) $this->auth->actor());
            $this->syncDocumentChecklist();
            $this->json(['ok' => true, 'row' => $row, 'documents' => $this->documentos->forSubject('Empresa', (string) $row['codigo'])]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function deleteEmpresa(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->empresas->deactivate($id, (string) $this->auth->actor());
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function storeAccionista(array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $row = $this->accionistas->create($post, (string) $this->auth->actor());
            $this->syncDocumentChecklist();
            $this->json(['ok' => true, 'row' => $row, 'documents' => $this->documentos->forSubject('Accionista/Participacion', (string) $row['codigo']), 'totals' => $this->accionistas->totals()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function updateAccionista(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $row = $this->accionistas->update($id, $post, (string) $this->auth->actor());
            $this->syncDocumentChecklist();
            $this->json(['ok' => true, 'row' => $row, 'documents' => $this->documentos->forSubject('Accionista/Participacion', (string) $row['codigo']), 'totals' => $this->accionistas->totals()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function deleteAccionista(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->accionistas->deactivate($id, (string) $this->auth->actor());
            $this->json(['ok' => true, 'totals' => $this->accionistas->totals()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function storePatrimonio(array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $row = $this->patrimonio->create($post, (string) $this->auth->actor());
            $this->syncDocumentChecklist();
            $this->json(['ok' => true, 'row' => $row, 'documents' => $this->documentos->forSubject('Activo/Patrimonio', (string) $row['codigo']), 'summary' => $this->patrimonio->summary(), 'summaryByType' => $this->patrimonio->summaryByType()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function updatePatrimonio(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $row = $this->patrimonio->update($id, $post, (string) $this->auth->actor());
            $this->syncDocumentChecklist();
            $this->json(['ok' => true, 'row' => $row, 'documents' => $this->documentos->forSubject('Activo/Patrimonio', (string) $row['codigo']), 'summary' => $this->patrimonio->summary(), 'summaryByType' => $this->patrimonio->summaryByType()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function deletePatrimonio(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->patrimonio->deactivate($id, (string) $this->auth->actor());
            $this->json(['ok' => true, 'summary' => $this->patrimonio->summary(), 'summaryByType' => $this->patrimonio->summaryByType()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function storeDocumento(array $post, array $files): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->json(['ok' => true, 'row' => $this->documentos->create($post, is_array($files['archivo'] ?? null) ? $files['archivo'] : null, (string) $this->auth->actor()), 'summary' => $this->documentos->summary()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function updateDocumento(string $id, array $post, array $files): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $file = is_array($files['archivo'] ?? null) ? $files['archivo'] : null;
            $existing = $this->documentos->find($id);
            if ($this->hasUploadedFile($file) && is_string($existing['archivo_ruta'] ?? null) && $existing['archivo_ruta'] !== '') {
                $row = $this->documentos->create($post, $file, (string) $this->auth->actor());
            } else {
                $row = $this->documentos->update($id, $post, $file, (string) $this->auth->actor());
            }
            $this->json(['ok' => true, 'row' => $row, 'summary' => $this->documentos->summary()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function deleteDocumento(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->documentos->deactivate($id, (string) $this->auth->actor());
            $this->json(['ok' => true, 'summary' => $this->documentos->summary()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function saveDecision(string $code, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->json(['ok' => true, 'row' => $this->decisiones->saveResponse($code, $post, (string) $this->auth->actor()), 'summary' => $this->decisiones->summary()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function requestDecisionRiskReview(string $code, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->json(['ok' => true, 'row' => $this->decisiones->requestRiskReview($code, $post, (string) $this->auth->actor()), 'summary' => $this->decisiones->summary()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function storeRiesgoCandidate(array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->json(['ok' => true, 'row' => $this->riesgos->createCandidate($post, (string) $this->auth->actor()), 'dashboard' => $this->riesgos->dashboard()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function updateRiesgoCandidate(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->json(['ok' => true, 'row' => $this->riesgos->updateCandidate($id, $post, (string) $this->auth->actor()), 'dashboard' => $this->riesgos->dashboard()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function convertRiesgoCandidate(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->json(['ok' => true, 'row' => $this->riesgos->createRisk($post, (string) $this->auth->actor(), $id), 'dashboard' => $this->riesgos->dashboard()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function storeRiesgo(array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->json(['ok' => true, 'row' => $this->riesgos->createRisk($post, (string) $this->auth->actor()), 'dashboard' => $this->riesgos->dashboard()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function updateRiesgo(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->json(['ok' => true, 'row' => $this->riesgos->updateRisk($id, $post, (string) $this->auth->actor()), 'dashboard' => $this->riesgos->dashboard()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function storeRiesgoControl(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->json(['ok' => true, 'row' => $this->riesgos->addControl($id, $post, (string) $this->auth->actor()), 'dashboard' => $this->riesgos->dashboard()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function storeRiesgoAction(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->json(['ok' => true, 'row' => $this->riesgos->addAction($id, $post, (string) $this->auth->actor()), 'dashboard' => $this->riesgos->dashboard()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function storeRiesgoDocument(string $id, array $post): void
    {
        try {
            $this->csrf->assert($post['csrf_token'] ?? null);
            $this->json(['ok' => true, 'row' => $this->riesgos->relateDocument($id, $post, (string) $this->auth->actor()), 'dashboard' => $this->riesgos->dashboard()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private function code(mixed $value): string
    {
        $value = $this->text($value);
        if (preg_match('/^[A-Z0-9_-]{2,80}$/', $value) !== 1) {
            throw new RuntimeException('Codigo invalido.');
        }
        return $value;
    }

    private function text(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('Campo obligatorio.');
        }
        return trim($value);
    }

    /** @return list<array{key:string,label:string,enabled:bool}> */
    private function tabs(): array
    {
        return [
            ['key' => 'familia', 'label' => '01_Familia', 'enabled' => true],
            ['key' => 'empresas', 'label' => '02_Empresas', 'enabled' => true],
            ['key' => 'accionistas', 'label' => '03_Accionistas', 'enabled' => true],
            ['key' => 'patrimonio', 'label' => '04_Patrimonio', 'enabled' => true],
            ['key' => 'documentos', 'label' => '05_Documentos', 'enabled' => true],
            ['key' => 'decisiones', 'label' => '06_Decisiones', 'enabled' => true],
            ['key' => 'riesgos', 'label' => '07_Riesgos', 'enabled' => true],
            ['key' => 'informes', 'label' => '08_Informes', 'enabled' => true],
            ['key' => 'academia_seguros', 'label' => '09_Academia seguros', 'enabled' => true],
        ];
    }

    /** @return array<string,mixed> */
    private function insuranceAcademy(): array
    {
        $path = dirname(__DIR__) . '/Data/insurance_academy.json';
        if (!is_file($path)) {
            return ['catalog' => [], 'coverages' => [], 'source' => ''];
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : ['catalog' => [], 'coverages' => [], 'source' => ''];
    }

    /** @return array<string, list<string>> */
    private function familiaOptions(): array
    {
        return ProtocolLists::familia();
    }

    /** @return array<string, list<string>> */
    private function empresaOptions(): array
    {
        return ProtocolLists::empresas();
    }

    /** @return array<string, list<string>> */
    private function accionistaOptions(): array
    {
        return ProtocolLists::accionistas();
    }

    /** @return array<string, list<string>> */
    private function patrimonioOptions(): array
    {
        return ProtocolLists::patrimonio();
    }

    /** @return array<string, list<string>> */
    private function documentoOptions(): array
    {
        return ProtocolLists::documentos();
    }

    /** @return list<array{clave:string,titulo:string,contenido:string,orden:int}> */
    private function defaultSections(): array
    {
        return [
            ['clave' => '01_familia', 'titulo' => '01 Familia', 'contenido' => 'Pendiente por documentar.', 'orden' => 1],
        ];
    }

    /** @param array<string,mixed> $post @return list<array{clave:string,titulo:string,contenido:string,orden:int}> */
    private function sections(array $post): array
    {
        $rows = is_array($post['secciones'] ?? null) ? $post['secciones'] : [];
        $sections = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sections[] = [
                'clave' => $this->text($row['clave'] ?? null),
                'titulo' => $this->text($row['titulo'] ?? null),
                'contenido' => $this->text($row['contenido'] ?? null),
                'orden' => is_string($row['orden'] ?? null) && ctype_digit($row['orden']) ? (int) $row['orden'] : 1,
            ];
        }
        if ($sections === []) {
            throw new RuntimeException('Agrega al menos una seccion.');
        }
        return $sections;
    }

    private function syncDocumentChecklist(): void
    {
        $this->documentos->syncChecklist($this->empresas->all(), $this->familia->all(), $this->accionistas->all(), $this->patrimonio->all(), (string) $this->auth->actor());
    }

    /** @param array<string,mixed>|null $file */
    private function hasUploadedFile(?array $file): bool
    {
        return is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /** @param array<string,mixed> $payload */
    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
