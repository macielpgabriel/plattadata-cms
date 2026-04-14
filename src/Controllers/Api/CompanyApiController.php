<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\CnpjService;
use App\Repositories\CnpjBlacklistRepository;
use Throwable;

final class CompanyApiController extends BaseApiController
{
    private CnpjService $cnpjService;
    private CnpjBlacklistRepository $blacklist;

    public function __construct()
    {
        $this->cnpjService = new CnpjService();
        $this->blacklist = new CnpjBlacklistRepository();
    }

    public function search(): never
    {
        $this->checkRateLimit('api_search', 60, 60);

        $cnpj = preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['cnpj'] ?? ''));

        if ($cnpj === '' || strlen($cnpj) !== 14) {
            $this->error('CNPJ inválido. Forneça um CNPJ com 14 caracteres.', 400, [
                'expected_format' => '00000000000000 ou XX.XXX.XXX/XXXX-XX',
                'example' => '12345678000199',
            ]);
        }

        if ($this->blacklist->isBlacklisted($cnpj)) {
            $this->error('CNPJ não disponível para consulta.', 403);
        }

        try {
            $result = $this->cnpjService->findOrFetch($cnpj);

            $this->success([
                'cnpj' => $cnpj,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            $this->error('Erro ao consultar CNPJ: ' . $e->getMessage(), 500);
        }
    }

    public function get(string $cnpj): never
    {
        $this->checkRateLimit('api_get', 120, 60);

        $cnpj = preg_replace('/[^A-Za-z0-9]/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            $this->error('CNPJ inválido. Forneça um CNPJ com 14 caracteres.', 400);
        }

        if ($this->blacklist->isBlacklisted($cnpj)) {
            $this->error('CNPJ não disponível.', 403);
        }

        try {
            $db = \App\Core\Database::connection();
            $stmt = $db->prepare(
                'SELECT * FROM companies WHERE cnpj = :cnpj LIMIT 1'
            );
            $stmt->execute(['cnpj' => $cnpj]);
            $company = $stmt->fetch();

            if (!$company) {
                $this->error('CNPJ não encontrado no banco de dados.', 404);
            }

            $company['data'] = json_decode($company['raw_data'] ?? '{}', true);

            $this->success($company);
        } catch (Throwable $e) {
            $this->error('Erro ao buscar empresa.', 500);
        }
    }

    public function list(): never
    {
        $this->checkRateLimit('api_list', 30, 60);

        $page = $this->getIntParam('page', 1);
        $perPage = min(100, $this->getIntParam('per_page', 20));
        $search = $this->getStringParam('search');
        $state = $this->getStringParam('state');
        $city = $this->getStringParam('city');

        try {
            $db = \App\Core\Database::connection();
            $params = [];
            $where = [];

            if ($search !== '') {
                $where[] = '(legal_name LIKE :search OR trade_name LIKE :search OR cnpj LIKE :search)';
                $params['search'] = '%' . $search . '%';
            }

            if ($state !== '') {
                $where[] = 'state = :state';
                $params['state'] = strtoupper($state);
            }

            if ($city !== '') {
                $where[] = 'city LIKE :city';
                $params['city'] = '%' . $city . '%';
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM companies $whereClause");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetch()['total'];

            $offset = ($page - 1) * $perPage;
            $sql = "SELECT id, cnpj, legal_name, trade_name, city, state, status, opened_at, updated_at 
                    FROM companies $whereClause 
                    ORDER BY updated_at DESC 
                    LIMIT :limit OFFSET :offset";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->execute();
            $companies = $stmt->fetchAll();

            $this->paginate($companies, $total, $page, $perPage);
        } catch (Throwable $e) {
            $this->error('Erro ao listar empresas.', 500);
        }
    }
}
