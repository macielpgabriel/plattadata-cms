<?php

declare(strict_types=1);

namespace App\Controllers\Company;

use App\Repositories\CompanyRepository;
use App\Services\CnpjService;
use App\Services\SimplesNacionalService;
use App\Services\CompanyEnrichmentService;
use App\Services\IbgeService;
use App\Core\Logger;

final class CompanyShowService
{
    private CompanyRepository $companies;
    private CnpjService $cnpjService;
    private SimplesNacionalService $simplesService;
    private CompanyEnrichmentService $enrichmentService;
    private IbgeService $ibgeService;

    public function __construct()
    {
        $this->companies = new CompanyRepository();
        $this->cnpjService = new CnpjService();
        $this->simplesService = new SimplesNacionalService();
        $this->enrichmentService = new CompanyEnrichmentService();
        $this->ibgeService = new IbgeService();
    }

    public function getCompanyByCnpj(string $cnpj): ?array
    {
        return $this->companies->findByCnpj($cnpj);
    }

    public function getCompanyWithDetails(string $cnpj, int $userId): array
    {
        $company = $this->companies->findByCnpj($cnpj);
        
        if (!$company) {
            return ['company' => null, 'details' => null, 'taxData' => null];
        }

        $company['formatted_cnpj'] = format_cnpj($cnpj);
        
        $details = null;
        $taxData = null;
        
        try {
            $companyId = $company['id'] ?? 0;
            if ($companyId > 0) {
                $taxData = $this->simplesService->getTaxData($companyId, $cnpj);
            }
        } catch (\Throwable $e) {
            Logger::warning("Erro ao buscar dados do Simples: " . $e->getMessage());
        }
        
        if (!empty($company['municipal_ibge_code'])) {
            try {
                $details = $this->ibgeService->getMunicipalityDataSmart((int) $company['municipal_ibge_code'], true);
            } catch (\Throwable $e) {
                Logger::warning("Erro ao buscar dados IBGE: " . $e->getMessage());
            }
        }
        
        return [
            'company' => $company,
            'details' => $details,
            'taxData' => $taxData,
        ];
    }

    public function refreshCompanyData(string $cnpj): array
    {
        try {
            $result = $this->cnpjService->findOrFetch($cnpj);
            
            return [
                'success' => !empty($result),
                'company' => $result,
                'message' => !empty($result) ? 'Empresa atualizada com sucesso' : 'Falha ao atualizar empresa',
            ];
        } catch (\Throwable $e) {
            Logger::error("Erro ao atualizar empresa {$cnpj}: " . $e->getMessage());
            return [
                'success' => false,
                'company' => null,
                'message' => 'Erro ao atualizar: ' . $e->getMessage(),
            ];
        }
    }

    public function hideCompany(int $companyId): bool
    {
        $db = \App\Core\Database::connection();
        $stmt = $db->prepare("UPDATE companies SET is_hidden = 1 WHERE id = :id");
        return $stmt->execute(['id' => $companyId]);
    }

    public function formatAddress(array $company): string
    {
        $parts = [];
        
        if (!empty($company['street'])) {
            $parts[] = $company['street'];
        }
        
        if (!empty($company['address_number']) && $company['address_number'] !== 'S/N') {
            $parts[] = ', ' . $company['address_number'];
        }
        
        if (!empty($company['district'])) {
            $parts[] = ' - ' . $company['district'];
        }
        
        if (!empty($company['city'])) {
            $parts[] = ', ' . $company['city'];
        }
        
        if (!empty($company['state'])) {
            $parts[] = '/' . $company['state'];
        }
        
        if (!empty($company['postal_code'])) {
            $cep = $company['postal_code'];
            $cepFormatted = substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
            $parts[] = ' - CEP: ' . $cepFormatted;
        }
        
        return implode('', $parts);
    }

    public function getAddress(array $company): string
    {
        $parts = [];
        
        if (!empty($company['street'])) {
            $num = !empty($company['address_number']) ? ' ' . $company['address_number'] : '';
            $parts[] = $company['street'] . $num;
        }
        
        if (!empty($company['district'])) {
            $parts[] = $company['district'];
        }
        
        $location = [];
        if (!empty($company['city'])) {
            $location[] = $company['city'];
        }
        if (!empty($company['state'])) {
            $location[] = $company['state'];
        }
        if (!empty($location)) {
            $parts[] = implode('/', $location);
        }
        
        return implode(' - ', $parts);
    }
}