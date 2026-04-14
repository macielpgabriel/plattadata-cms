<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\CompanyRepository;
use App\Repositories\FavoriteRepository;

final class FavoriteController
{
    private FavoriteRepository $favorites;
    private CompanyRepository $companies;

    public function __construct()
    {
        $this->favorites = new FavoriteRepository();
        $this->companies = new CompanyRepository();
    }

    public function index(): void
    {
        $user = Auth::user();
        $groupId = isset($_GET['group']) ? (int) $_GET['group'] : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $items = $this->favorites->findByUserId((int) $user['id'], $groupId, $perPage, $offset);
        $total = $this->favorites->countByUserId((int) $user['id'], $groupId);
        $groups = $this->favorites->getGroups((int) $user['id']);
        $lastPage = (int) ceil($total / $perPage);

        View::render('favorites/index', [
            'title' => 'Minhas Empresas Favoritas',
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'lastPage' => $lastPage,
            'groups' => $groups,
            'currentGroupId' => $groupId,
            'metaRobots' => 'noindex,nofollow',
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]);
    }

    public function toggle(array $params): void
    {

        $user = Auth::user();
        $cnpj = preg_replace('/[^A-Za-z0-9]/', '', (string) ($params['cnpj'] ?? '')) ?? '';
        $company = $this->companies->findByCnpj($cnpj);

        if (!$company) {
            Response::json(['error' => 'Empresa nao encontrada.'], 404);
        }

        $companyId = (int) $company['id'];
        $userId = (int) $user['id'];
        $groupId = isset($_POST['group_id']) ? (int) $_POST['group_id'] : null;

        try {
            if ($this->favorites->isFavorite($userId, $companyId)) {
                $this->favorites->remove($userId, $companyId);
                Response::json(['status' => 'removed', 'message' => 'Removido dos favoritos.']);
            } else {
                $this->favorites->add($userId, $companyId, $groupId);
                Response::json(['status' => 'added', 'message' => 'Adicionado aos favoritos.']);
            }
        } catch (\PDOException $e) {
            Logger::error('Erro ao alternar favorito: ' . $e->getMessage());
            Response::json(['error' => 'Erro ao processar sua solicitação no banco de dados.'], 500);
        }
    }

    public function createGroup(): void
    {

        $user = Auth::user();
        $name = trim((string) ($_POST['name'] ?? ''));
        $color = preg_replace('/[^a-z]/', '', strtolower((string) ($_POST['color'] ?? 'primary'))) ?: 'primary';

        if (empty($name)) {
            Response::json(['error' => 'Nome e obrigatorio.'], 400);
        }

        $id = $this->favorites->createGroup((int) $user['id'], $name, $color);
        
        if ($id) {
            Response::json(['status' => 'success', 'id' => $id, 'name' => $name, 'color' => $color]);
        } else {
            Response::json(['error' => 'Erro ao criar grupo.'], 500);
        }
    }

    public function updateGroup(array $params): void
    {

        $user = Auth::user();
        $groupId = (int) ($params['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $color = preg_replace('/[^a-z]/', '', strtolower((string) ($_POST['color'] ?? 'primary'))) ?: 'primary';

        if (empty($name) || $groupId === 0) {
            Response::json(['error' => 'Dados invalidos.'], 400);
        }

        $result = $this->favorites->updateGroup($groupId, (int) $user['id'], $name, $color);
        
        if ($result) {
            Response::json(['status' => 'success']);
        } else {
            Response::json(['error' => 'Erro ao atualizar grupo.'], 500);
        }
    }

    public function deleteGroup(array $params): void
    {

        $user = Auth::user();
        $groupId = (int) ($params['id'] ?? 0);

        if ($groupId === 0) {
            Response::json(['error' => 'ID invalido.'], 400);
        }

        $result = $this->favorites->deleteGroup($groupId, (int) $user['id']);
        
        if ($result) {
            Response::json(['status' => 'success']);
        } else {
            Response::json(['error' => 'Erro ao excluir grupo.'], 500);
        }
    }

    public function moveToGroup(array $params): void
    {

        $user = Auth::user();
        $cnpj = preg_replace('/[^A-Za-z0-9]/', '', (string) ($params['cnpj'] ?? '')) ?? '';
        $company = $this->companies->findByCnpj($cnpj);

        if (!$company) {
            Response::json(['error' => 'Empresa nao encontrada.'], 404);
        }

        $groupId = isset($_POST['group_id']) && $_POST['group_id'] !== '' ? (int) $_POST['group_id'] : null;
        $result = $this->favorites->moveToGroup((int) $user['id'], (int) $company['id'], $groupId);

        if ($result) {
            Response::json(['status' => 'success']);
        } else {
            Response::json(['error' => 'Erro ao mover para grupo.'], 500);
        }
    }

    public function export(): void
    {
        $user = Auth::user();
        $groupId = isset($_GET['group']) ? (int) $_GET['group'] : null;
        $items = $this->favorites->findByUserId((int) $user['id'], $groupId, 5000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        $filename = 'favoritos_' . date('Y-m-d');
        if ($groupId) {
            $groups = $this->favorites->getGroups((int) $user['id']);
            foreach ($groups as $g) {
                if ($g['id'] == $groupId) {
                    $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $g['name']);
                    break;
                }
            }
        }
        header('Content-Disposition: attachment; filename=' . $filename . '.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['CNPJ', 'Razao Social', 'Nome Fantasia', 'Cidade', 'UF', 'Email', 'Telefone']);

        foreach ($items as $item) {
            fputcsv($output, [
                $item['cnpj'],
                $item['legal_name'],
                $item['trade_name'],
                $item['city'],
                $item['state'],
                $item['email'],
                $item['phone'],
            ]);
        }

        fclose($output);
        exit;
    }
}
