<?php

declare(strict_types=1);

namespace App\Controllers\Integration;

use App\Repositories\CompanyRepository;

final class CompanySearchService
{
    private CompanyRepository $companyRepository;

    public function __construct()
    {
        $this->companyRepository = new CompanyRepository();
    }

    public function search(string $term, string $state, int $page = 1, int $perPage = 20): array
    {
        return $this->companyRepository->searchPaginated($term, $state, $page, $perPage);
    }

    public function getByCnpj(string $cnpj): ?array
    {
        return $this->companyRepository->findByCnpj($cnpj);
    }

    public function formatCompanyResponse(array $company): array
    {
        return [
            'cnpj' => $company['cnpj'] ?? null,
            'legal_name' => $company['legal_name'] ?? null,
            'trade_name' => $company['trade_name'] ?? null,
            'status' => $company['status'] ?? null,
            'city' => $company['city'] ?? null,
            'state' => $company['state'] ?? null,
            'opened_at' => $company['opened_at'] ?? null,
            'company_size' => $company['company_size'] ?? null,
            'capital_social' => isset($company['capital_social']) ? (float) $company['capital_social'] : null,
        ];
    }
}