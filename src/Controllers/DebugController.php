<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Auth;
use App\Repositories\CompanyRepository;

final class DebugController
{
    private CompanyRepository $companies;

    public function __construct()
    {
        $this->companies = new CompanyRepository();
    }

    public function companyData(array $params): void
    {
        $cnpj = (string) ($params['cnpj'] ?? $params['cnpj_formatted'] ?? '');
        if (empty($cnpj)) {
            Response::json(['error' => 'CNPJ required'], 400);
            return;
        }

        // Sanitize CNPJ
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) !== 14) {
            Response::json(['error' => 'Invalid CNPJ format'], 400);
            return;
        }

        // Format CNPJ for display
        $cnpjFormatted = substr($cnpj, 0, 2) . '.' . 
                        substr($cnpj, 2, 3) . '.' . 
                        substr($cnpj, 5, 3) . '/' . 
                        substr($cnpj, 8, 4) . '-' . 
                        substr($cnpj, 12, 2);

        $company = $this->companies->findByCnpj($cnpjFormatted, true);

        Response::json([
            'cnpj_input' => $params['cnpj'] ?? '',
            'cnpj_formatted' => $cnpjFormatted,
            'company_found' => $company ? true : false,
            'company_fields' => $company ? array_keys($company) : [],
            'company_data' => $company ? [
                'id' => $company['id'] ?? null,
                'cnpj' => $company['cnpj'] ?? null,
                'legal_name' => $company['legal_name'] ?? null,
                'trade_name' => $company['trade_name'] ?? null,
                'status' => $company['status'] ?? null,
                'city' => $company['city'] ?? null,
                'state' => $company['state'] ?? null,
                'raw_data_keys' => is_string($company['raw_data'] ?? null) ? 
                    array_keys(json_decode($company['raw_data'], true) ?: []) : 
                    array_keys($company['raw_data'] ?? []),
                'raw_data_sample' => is_string($company['raw_data'] ?? null) ? 
                    substr($company['raw_data'], 0, 500) : 
                    json_encode($company['raw_data'], JSON_UNESCAPED_UNICODE),
            ] : null,
            'message' => 'Company data retrieved successfully'
        ]);
    }

    public function viewVariables(array $params): void
    {
        Response::json([
            'view_defaults' => [
                'title' => '',
                'metaTitle' => null,
                'metaDescription' => null,
                'metaRobots' => null,
                'structuredData' => null,
                'usdRate' => null,
                'capitalUsd' => null,
                'marketNews' => [],
                'stockQuote' => null,
                'weather' => null,
                'company' => [],
                'qsa' => [],
                'mainCnae' => [],
                'secondaryCnaes' => [],
                'enrichment' => [],
                'rawData' => [],
                'cnpj' => '',
            ],
            'message' => 'View defaults retrieved successfully'
        ]);
    }

    public function testExtract(array $params = []): void
    {
        // Simular o que acontece na View
        $defaults = [
            'title' => '',
            'metaTitle' => null,
            'metaDescription' => null,
            'metaRobots' => null,
            'structuredData' => null,
            'usdRate' => null,
            'capitalUsd' => null,
            'marketNews' => [],
            'stockQuote' => null,
            'weather' => null,
            'company' => [],
            'qsa' => [],
            'mainCnae' => [],
            'secondaryCnaes' => [],
            'enrichment' => [],
            'rawData' => [],
            'cnpj' => '',
        ];

        // Simular dados passados para a view (mínimos)
        $data = array_merge($defaults, [
            'title' => 'Test Company',
            'cnpj' => '00.000.000/0001-91',
            'company' => ['legal_name' => 'Test Company LTDA'],
        ]);

        // Fazer o extract
        extract($data, EXTR_SKIP);

        Response::json([
            'extracted_variables' => [
                'title' => isset($title) ? $title : 'NOT SET',
                'cnpj' => isset($cnpj) ? $cnpj : 'NOT SET',
                'company' => isset($company) ? gettype($company) : 'NOT SET',
                'marketNews' => isset($marketNews) ? gettype($marketNews) : 'NOT SET',
                'stockQuote' => isset($stockQuote) ? gettype($stockQuote) : 'NOT SET',
                'weather' => isset($weather) ? gettype($weather) : 'NOT SET',
            ],
            'message' => 'Extract test completed successfully'
        ]);
    }
}
