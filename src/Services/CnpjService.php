<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CompanyRepository;
use App\Repositories\MunicipalityRepository;
use App\Services\Cnpj\CnpjApiService;
use App\Services\Cnpj\CnpjEnrichmentService;
use App\Services\Cnpj\CnpjValidationService;
use App\Core\Logger;
use RuntimeException;
use Throwable;

final class CnpjService
{
    private CompanyRepository $repository;
    private MunicipalityRepository $municipalityRepository;
    private CnpjApiService $apiService;
    private CnpjEnrichmentService $enrichmentService;
    private CnpjValidationService $validationService;

    public function __construct()
    {
        $this->repository = new CompanyRepository();
        $this->municipalityRepository = new MunicipalityRepository();
        $this->apiService = new CnpjApiService();
        $this->enrichmentService = new CnpjEnrichmentService();
        $this->validationService = new CnpjValidationService();
    }

    public function findOrFetch(string $cnpj): array
    {
        $cnpj = $this->sanitize($cnpj);
        $company = $this->repository->findByCnpj($cnpj, true);

        if ($company) {
            if ($company['is_hidden']) {
                throw new RuntimeException('Esta empresa foi removida a pedido do proprietário.');
            }
            return array_merge($company, ['source' => 'cache']);
        }

        return $this->refreshFromApi($cnpj);
    }

    public function refreshFromApi(string $cnpj): array
    {
        $cnpj = $this->sanitize($cnpj);
        $result = $this->apiService->fetchFromAllProviders($cnpj);
        $payload = $result['data'];
        $providerUsed = $result['provider'];
        $attempts = $result['attempts'];

        if (!$payload) {
            $errorDetails = array_map(function($a) {
                return $a['provider'] . ' (' . ($a['http_code'] ?? 'Error') . (isset($a['error']) ? ': ' . $a['error'] : '') . ')';
            }, $attempts);

            Logger::warning('CNPJ fetch failed from all providers', [
                'cnpj' => $cnpj,
                'attempts' => $attempts,
            ]);

            $errorMsg = 'Nao foi possivel localizar dados para este CNPJ nos provedores disponiveis: '
                . implode('; ', $errorDetails);
            Logger::error($errorMsg);

            throw new RuntimeException($errorMsg);
        }

        $payload = $this->enrichmentService->enrichData($cnpj, $payload);

        $company = $this->repository->upsertFromApi($cnpj, $payload, 'api', [
            'provider' => $providerUsed,
            'attempts' => $attempts
        ]);

        Logger::info('CNPJ upsert completed', ['cnpj' => $cnpj, 'company_id' => $company['id'] ?? null, 'provider' => $providerUsed]);

        if (isset($company['id'])) {
            $this->syncCompanyData((int)$company['id'], $cnpj, $payload, $providerUsed);
        }

        return $company;
    }

    private function syncCompanyData(int $companyId, string $cnpj, array $payload, string $providerUsed): void
    {
        try {
            $this->enrichCompanyData($companyId, $payload);
            $this->syncRelationalData($companyId, $payload);
            
            $taxService = new SimplesNacionalService();
            $taxService->syncFromPayload($companyId, $cnpj, $payload);
        } catch (Throwable $e) {
            Logger::warning('Company data sync failed', ['company_id' => $companyId, 'error' => $e->getMessage()]);
        }
    }

    private function enrichCompanyData(int $companyId, array $payload): void
    {
        try {
            $enrichmentService = new CompanyEnrichmentService();
            $enriched = $enrichmentService->enrichCompany($payload);

            $db = \App\Core\Database::connection();
            $columnsStmt = $db->query("SHOW COLUMNS FROM companies");
            $availableColumns = array_map(
                static fn(array $row): string => (string) ($row['Field'] ?? ''),
                $columnsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: []
            );
            $available = array_flip($availableColumns);

            $set = [];
            $params = ['id' => $companyId];

            if (isset($available['website'])) {
                $set[] = 'website = :website';
                $params['website'] = $enriched['website'] ?? null;
            }
            if (isset($available['logo_url'])) {
                $set[] = 'logo_url = :logo_url';
                $params['logo_url'] = $enriched['logo_url'] ?? null;
            }
            if (isset($available['employees_estimate'])) {
                $set[] = 'employees_estimate = :employees_estimate';
                $params['employees_estimate'] = $enriched['employees_estimate'] ?? null;
            }
            if (isset($available['enriched_at'])) {
                $set[] = 'enriched_at = NOW()';
            }

            if (!empty($set)) {
                $sql = 'UPDATE companies SET ' . implode(', ', $set) . ' WHERE id = :id';
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }
        } catch (Throwable $e) {
            Logger::warning('Company enrichment failed', ['company_id' => $companyId, 'error' => $e->getMessage()]);
        }
    }

    private function syncRelationalData(int $companyId, array $payload): void
    {
        $db = \App\Core\Database::connection();

        if (!empty($payload['qsa']) && is_array($payload['qsa'])) {
            $db->prepare("DELETE FROM company_partners WHERE company_id = ?")->execute([$companyId]);
            $stmt = $db->prepare("INSERT INTO company_partners (company_id, name, role, document_masked) VALUES (?, ?, ?, ?)");
            foreach ($payload['qsa'] as $partner) {
                $name = $partner['nome_socio'] ?? ($partner['nome'] ?? null);
                if ($name) {
                    $stmt->execute([
                        $companyId,
                        mb_strtoupper((string)$name),
                        $partner['qualificacao_socio'] ?? ($partner['qualificacao'] ?? null),
                        $partner['cpf_cnpj_socio'] ?? null
                    ]);
                }
            }
        }

        if (!empty($payload['cnaes_secundarios']) && is_array($payload['cnaes_secundarios'])) {
            $db->prepare("DELETE FROM company_secondary_cnaes WHERE company_id = ?")->execute([$companyId]);
            $stmt = $db->prepare("INSERT INTO company_secondary_cnaes (company_id, cnae_code, description) VALUES (?, ?, ?)");
            foreach ($payload['cnaes_secundarios'] as $sec) {
                if (!empty($sec['codigo'])) {
                    $stmt->execute([$companyId, $sec['codigo'], $sec['descricao'] ?? null]);
                }
            }
        }
    }

    public function sanitize(string $cnpj): string
    {
        return $this->validationService->sanitize($cnpj);
    }

    public function validate(string $cnpj): bool
    {
        return $this->validationService->validate($cnpj);
    }
}
