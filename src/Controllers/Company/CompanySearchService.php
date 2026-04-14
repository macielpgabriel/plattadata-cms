<?php

declare(strict_types=1);

namespace App\Controllers\Company;

use App\Core\Database;
use App\Repositories\CompanyRepository;
use App\Repositories\MunicipalityRepository;
use App\Repositories\FavoriteRepository;

final class CompanySearchService
{
    private CompanyRepository $companies;
    private MunicipalityRepository $municipalities;

    public function __construct()
    {
        $this->companies = new CompanyRepository();
        $this->municipalities = new MunicipalityRepository();
    }

    public function searchByTerm(string $term, string $state, int $page, int $perPage): array
    {
        return $this->companies->searchPaginated($term, $state, $page, $perPage);
    }

    public function searchByMunicipality(int $ibgeCode, string $term, string $state, int $page, int $perPage): array
    {
        return $this->companies->findByMunicipality($ibgeCode, $term, $state, $page, $perPage);
    }

    public function getCitiesByState(string $state): array
    {
        $db = Database::connection();
        $stmt = $db->prepare("
            SELECT DISTINCT city FROM companies 
            WHERE state = :state AND city IS NOT NULL AND city != '' 
            ORDER BY city LIMIT 100
        ");
        $stmt->execute(['state' => $state]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getMunicipalityBySlug(string $slug, string $uf): ?array
    {
        $muni = $this->municipalities->findBySlug($slug, $uf);
        
        if (!$muni) {
            $nameFallback = str_replace('-', ' ', $slug);
            $muni = $this->municipalities->findByNameAndState($nameFallback, $uf);
        }
        
        return $muni;
    }

    public function exportToCsv(string $term, string $state): void
    {
        $db = Database::connection();
        
        $sql = "SELECT cnpj, legal_name, trade_name, city, state, status, opened_at, company_size
                FROM companies WHERE is_hidden = 0";
        $params = [];
        
        if ($term !== '') {
            $sql .= " AND (legal_name LIKE :term OR trade_name LIKE :term2 OR cnpj LIKE :term3)";
            $params['term'] = '%' . $term . '%';
            $params['term2'] = '%' . $term . '%';
            $params['term3'] = '%' . $term . '%';
        }
        
        if ($state !== '') {
            $sql .= " AND state = :state";
            $params['state'] = $state;
        }
        
        $sql .= " ORDER BY legal_name LIMIT 1000";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="empresas_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['CNPJ', 'Razão Social', 'Nome Fantasia', 'Cidade', 'UF', 'Status', 'Abertura', 'Porte'], ';');
        
        foreach ($results as $row) {
            fputcsv($output, [
                format_cnpj($row['cnpj']),
                $row['legal_name'],
                $row['trade_name'] ?? '',
                $row['city'] ?? '',
                $row['state'] ?? '',
                $row['status'] ?? '',
                format_date($row['opened_at'] ?? ''),
                $row['company_size'] ?? '',
            ], ';');
        }
        
        fclose($output);
        exit;
    }
}