<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\CatalogoController;
use App\Controllers\ProtocoloController;
use App\Controllers\SystemController;
use App\Models\AccionistaModel;
use App\Models\CatalogoModel;
use App\Models\DecisionModel;
use App\Models\DocumentoModel;
use App\Models\EmpresaModel;
use App\Models\FamiliaModel;
use App\Models\PatrimonioModel;
use App\Models\ProtocoloModel;
use App\Models\RiesgoModel;
use Throwable;

final readonly class App
{
    private View $view;
    private Auth $auth;
    private Csrf $csrf;
    private Database $db;

    public function __construct(private Config $config)
    {
        $this->view = new View($config->get('ROOT'));
        $this->auth = new Auth($config);
        $this->csrf = new Csrf();
        $this->db = new Database($config);
    }

    public function run(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $base = rtrim($this->config->get('APP_BASE_PATH', '/public'), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        try {
            $this->dispatch(strtoupper($method), $path);
        } catch (Throwable $exception) {
            if ($this->isAjax()) {
                $this->json(['ok' => false, 'error' => $this->config->bool('APP_DEBUG') ? $exception->getMessage() : 'No fue posible completar la operacion.'], 500);
                return;
            }
            http_response_code(500);
            $message = $this->config->bool('APP_DEBUG') ? $exception->getMessage() : 'No fue posible completar la operación.';
            echo $this->view->render('error', $this->shared(['message' => $message]));
        }
    }

    private function dispatch(string $method, string $path): void
    {
        $auth = new AuthController($this->config, $this->view, $this->auth, $this->csrf);
        $system = new SystemController($this->config, $this->view, $this->auth, $this->csrf, $this->db);
        $catalogoModel = new CatalogoModel($this->db->pdo());
        $protocolos = new ProtocoloController($this->config, $this->view, $this->auth, $this->csrf, new ProtocoloModel($this->db->pdo()), $catalogoModel, new FamiliaModel($this->db->pdo()), new EmpresaModel($this->db->pdo()), new AccionistaModel($this->db->pdo()), new PatrimonioModel($this->db->pdo()), new DocumentoModel($this->db->pdo(), $this->config->get('ROOT')), new DecisionModel($this->db->pdo(), $this->config->get('ROOT')), new RiesgoModel($this->db->pdo()));
        $catalogos = new CatalogoController($this->config, $this->view, $this->auth, $this->csrf, $catalogoModel);

        if ($method === 'GET' && $path === '/system/status') { $system->status(); return; }
        if ($method === 'GET' && $path === '/login') { $auth->form(); return; }
        if ($method === 'POST' && $path === '/login') { $auth->login($_POST); return; }

        if (!$this->auth->check()) {
            if ($this->isAjax()) {
                $this->json(['ok' => false, 'error' => 'La sesion vencio o fue reemplazada. Vuelve a iniciar sesion y reintenta guardar.'], 401);
                return;
            }
            $this->redirect('/login');
            return;
        }
        if ($method === 'POST' && $path === '/logout') { $auth->logout($_POST); return; }
        if ($method === 'GET' && $path === '/') { $this->redirect('/protocolo-familiar'); return; }
        if ($method === 'GET' && $path === '/protocolo-familiar') { $protocolos->index(); return; }
        if ($method === 'POST' && $path === '/protocolo-familiar/familia') { $protocolos->storeFamilia($_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/familia\/([0-9a-f-]{36})$/i', $path, $m) === 1) { $protocolos->updateFamilia($m[1], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/familia\/([0-9a-f-]{36})\/eliminar$/i', $path, $m) === 1) { $protocolos->deleteFamilia($m[1], $_POST); return; }
        if ($method === 'POST' && $path === '/protocolo-familiar/empresas') { $protocolos->storeEmpresa($_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/empresas\/([0-9a-f-]{36})$/i', $path, $m) === 1) { $protocolos->updateEmpresa($m[1], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/empresas\/([0-9a-f-]{36})\/eliminar$/i', $path, $m) === 1) { $protocolos->deleteEmpresa($m[1], $_POST); return; }
        if ($method === 'POST' && $path === '/protocolo-familiar/accionistas') { $protocolos->storeAccionista($_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/accionistas\/([0-9a-f-]{36})$/i', $path, $m) === 1) { $protocolos->updateAccionista($m[1], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/accionistas\/([0-9a-f-]{36})\/eliminar$/i', $path, $m) === 1) { $protocolos->deleteAccionista($m[1], $_POST); return; }
        if ($method === 'POST' && $path === '/protocolo-familiar/patrimonio') { $protocolos->storePatrimonio($_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/patrimonio\/([0-9a-f-]{36})$/i', $path, $m) === 1) { $protocolos->updatePatrimonio($m[1], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/patrimonio\/([0-9a-f-]{36})\/eliminar$/i', $path, $m) === 1) { $protocolos->deletePatrimonio($m[1], $_POST); return; }
        if ($method === 'POST' && $path === '/protocolo-familiar/documentos') { $protocolos->storeDocumento($_POST, $_FILES); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/documentos\/([0-9a-f-]{36})$/i', $path, $m) === 1) { $protocolos->updateDocumento($m[1], $_POST, $_FILES); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/documentos\/([0-9a-f-]{36})\/eliminar$/i', $path, $m) === 1) { $protocolos->deleteDocumento($m[1], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/decisiones\/(DEC-\d{3})$/', $path, $m) === 1) { $protocolos->saveDecision($m[1], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/decisiones\/(DEC-\d{3})\/revision-riesgos$/', $path, $m) === 1) { $protocolos->requestDecisionRiskReview($m[1], $_POST); return; }
        if ($method === 'POST' && $path === '/protocolo-familiar/riesgos/candidatos') { $protocolos->storeRiesgoCandidate($_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/riesgos\/candidatos\/([0-9a-f-]{36})$/i', $path, $m) === 1) { $protocolos->updateRiesgoCandidate($m[1], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/riesgos\/candidatos\/([0-9a-f-]{36})\/convertir$/i', $path, $m) === 1) { $protocolos->convertRiesgoCandidate($m[1], $_POST); return; }
        if ($method === 'POST' && $path === '/protocolo-familiar/riesgos') { $protocolos->storeRiesgo($_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/riesgos\/([0-9a-f-]{36})$/i', $path, $m) === 1) { $protocolos->updateRiesgo($m[1], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/riesgos\/([0-9a-f-]{36})\/controles$/i', $path, $m) === 1) { $protocolos->storeRiesgoControl($m[1], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/riesgos\/([0-9a-f-]{36})\/acciones$/i', $path, $m) === 1) { $protocolos->storeRiesgoAction($m[1], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/riesgos\/([0-9a-f-]{36})\/documentos$/i', $path, $m) === 1) { $protocolos->storeRiesgoDocument($m[1], $_POST); return; }
        if ($method === 'GET' && $path === '/protocolo-familiar/nuevo') { $protocolos->create(); return; }
        if ($method === 'POST' && $path === '/protocolo-familiar/nuevo') { $protocolos->store($_POST); return; }
        if ($method === 'GET' && preg_match('/^\/protocolo-familiar\/([0-9a-f-]{36})$/i', $path, $m) === 1) { $protocolos->show($m[1]); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/([0-9a-f-]{36})\/secciones$/i', $path, $m) === 1) { $protocolos->updateSections($m[1], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/([0-9a-f-]{36})\/estado$/i', $path, $m) === 1) { $protocolos->changeState($m[1], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/protocolo-familiar\/([0-9a-f-]{36})\/firmas$/i', $path, $m) === 1) { $protocolos->sign($m[1], $_POST); return; }
        if ($method === 'GET' && $path === '/listas') { $catalogos->index(); return; }
        if ($method === 'GET' && $path === '/listas/nuevo') { $catalogos->create(); return; }
        if ($method === 'POST' && $path === '/listas') { $catalogos->store($_POST); return; }
        if ($method === 'GET' && preg_match('/^\/listas\/([A-Z0-9_]{2,80})\/editar$/', $path, $m) === 1) { $catalogos->edit($m[1]); return; }
        if ($method === 'POST' && preg_match('/^\/listas\/([A-Z0-9_]{2,80})\/editar$/', $path, $m) === 1) { $catalogos->update($m[1], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/listas\/([A-Z0-9_]{2,80})\/eliminar$/', $path, $m) === 1) { $catalogos->delete($m[1], $_POST); return; }
        if ($method === 'GET' && preg_match('/^\/listas\/([A-Z0-9_]{2,80})\/items\/nuevo$/', $path, $m) === 1) { $catalogos->createItem($m[1]); return; }
        if ($method === 'POST' && preg_match('/^\/listas\/([A-Z0-9_]{2,80})\/items$/', $path, $m) === 1) { $catalogos->storeItem($m[1], $_POST); return; }
        if ($method === 'GET' && preg_match('/^\/listas\/([A-Z0-9_]{2,80})\/items\/([A-Z0-9_]{2,120})\/editar$/', $path, $m) === 1) { $catalogos->editItem($m[1], $m[2]); return; }
        if ($method === 'POST' && preg_match('/^\/listas\/([A-Z0-9_]{2,80})\/items\/([A-Z0-9_]{2,120})\/editar$/', $path, $m) === 1) { $catalogos->updateItem($m[1], $m[2], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/listas\/([A-Z0-9_]{2,80})\/items\/([A-Z0-9_]{2,120})\/eliminar$/', $path, $m) === 1) { $catalogos->deleteItem($m[1], $m[2]); return; }
        if ($method === 'GET' && $path === '/catalogos') { $catalogos->index(); return; }
        if ($method === 'GET' && $path === '/catalogos/nuevo') { $catalogos->create(); return; }
        if ($method === 'POST' && $path === '/catalogos') { $catalogos->store($_POST); return; }
        if ($method === 'GET' && preg_match('/^\/catalogos\/([A-Z0-9_]{2,80})\/editar$/', $path, $m) === 1) { $catalogos->edit($m[1]); return; }
        if ($method === 'POST' && preg_match('/^\/catalogos\/([A-Z0-9_]{2,80})\/editar$/', $path, $m) === 1) { $catalogos->update($m[1], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/catalogos\/([A-Z0-9_]{2,80})\/eliminar$/', $path, $m) === 1) { $catalogos->delete($m[1], $_POST); return; }
        if ($method === 'GET' && preg_match('/^\/catalogos\/([A-Z0-9_]{2,80})\/items\/nuevo$/', $path, $m) === 1) { $catalogos->createItem($m[1]); return; }
        if ($method === 'POST' && preg_match('/^\/catalogos\/([A-Z0-9_]{2,80})\/items$/', $path, $m) === 1) { $catalogos->storeItem($m[1], $_POST); return; }
        if ($method === 'GET' && preg_match('/^\/catalogos\/([A-Z0-9_]{2,80})\/items\/([A-Z0-9_]{2,120})\/editar$/', $path, $m) === 1) { $catalogos->editItem($m[1], $m[2]); return; }
        if ($method === 'POST' && preg_match('/^\/catalogos\/([A-Z0-9_]{2,80})\/items\/([A-Z0-9_]{2,120})\/editar$/', $path, $m) === 1) { $catalogos->updateItem($m[1], $m[2], $_POST); return; }
        if ($method === 'POST' && preg_match('/^\/catalogos\/([A-Z0-9_]{2,80})\/items\/([A-Z0-9_]{2,120})\/eliminar$/', $path, $m) === 1) { $catalogos->deleteItem($m[1], $m[2], $_POST); return; }

        http_response_code(404);
        if ($this->isAjax()) {
            $this->json(['ok' => false, 'error' => 'Ruta no encontrada para esta operacion.'], 404);
            return;
        }
        echo $this->view->render('error', $this->shared(['message' => 'Página no encontrada.']));
    }

    /** @param array<string, mixed> $data */
    private function shared(array $data = []): array
    {
        return $data + ['basePath' => rtrim($this->config->get('APP_BASE_PATH', '/public'), '/'), 'actor' => $this->auth->actor(), 'csrfToken' => $this->csrf->token(), 'flash' => null];
    }

    private function redirect(string $path): void
    {
        header('Location: ' . rtrim($this->config->get('APP_BASE_PATH', '/public'), '/') . $path, true, 303);
    }

    private function isAjax(): bool
    {
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

        return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
