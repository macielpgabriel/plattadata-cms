<?php

declare(strict_types=1);

namespace App\Controllers\Comparison;

use App\Core\Database;

final class ComparisonSearchService
{
    public function search(string $term): array
    {
        $db = Database::connection();
        
        $stmt = $db->prepare("
            SELECT cnpj, legal_name, trade_name, city, state, status
            FROM companies 
            WHERE is_hidden = 0 
            AND (
                cnpj LIKE :term1 
                OR legal_name LIKE :term2 
                OR trade_name LIKE :term3
            )
            ORDER BY 
                CASE 
                    WHEN legal_name LIKE :exact THEN 1
                    WHEN trade_name LIKE :exact2 THEN 2
                    ELSE 3
                END,
                legal_name ASC
            LIMIT 10
        ");
        
        $likeTerm = '%' . $term . '%';
        $exactTerm = $term . '%';
        
        $stmt->execute([
            'term1' => $likeTerm,
            'term2' => $likeTerm,
            'term3' => $likeTerm,
            'exact' => $exactTerm,
            'exact2' => $exactTerm,
        ]);
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return array_map(function($company) {
            $name = $company['trade_name'] ?: $company['legal_name'];
            return [
                'cnpj' => $company['cnpj'],
                'name' => $name,
                'full_name' => $company['legal_name'],
                'trade_name' => $company['trade_name'],
                'location' => ($company['city'] ?? '') . '/' . ($company['state'] ?? ''),
                'status' => $company['status'],
                'label' => $name . ' (' . $company['cnpj'] . ') - ' . ($company['city'] ?? '') . '/' . ($company['state'] ?? ''),
            ];
        }, $results);
    }

    public function getCompaniesForComparison(string $cnpj1, string $cnpj2): ?array
    {
        $db = Database::connection();
        
        $stmt = $db->prepare("
            SELECT cnpj, legal_name, trade_name, city, state, status, 
                   company_size, simples, mei, total_consults, total_views, 
                   unique_users, days_consulted, capital_social, credit_score
            FROM companies 
            WHERE cnpj IN (:cnpj1, :cnpj2) AND is_hidden = 0
        ");
        $stmt->execute(['cnpj1' => $cnpj1, 'cnpj2' => $cnpj2]);
        $companies = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (count($companies) < 2) {
            return null;
        }
        
        $comp1 = $companies[0]['cnpj'] === $cnpj1 ? $companies[0] : $companies[1];
        $comp2 = $companies[1]['cnpj'] === $cnpj2 ? $companies[1] : $companies[0];
        
        return ['company1' => $comp1, 'company2' => $comp2];
    }

    public function compareValues(array $comp1, array $comp2, string $key, bool $isCurrency = false): array
    {
        $v1 = (float) ($comp1[$key] ?? 0);
        $v2 = (float) ($comp2[$key] ?? 0);
        
        if ($v1 === $v2) {
            return [
                'value1' => $v1,
                'value2' => $v2,
                'difference' => 0,
                'difference_formatted' => '0',
                'percent_formatted' => '0%',
                'winner' => 'tie'
            ];
        }
        
        $diff = $v1 - $v2;
        $winner = $diff > 0 ? '1' : '2';
        $absDiff = abs($diff);
        
        $denominator = min($v1, $v2);
        $percent = $denominator > 0 ? round(($absDiff / $denominator) * 100, 1) : 0;
        
        return [
            'value1' => $v1,
            'value2' => $v2,
            'difference' => $diff,
            'difference_formatted' => $isCurrency ? 'R$ ' . number_format($absDiff, 2, ',', '.') : number_format($absDiff, 0, ',', '.'),
            'percent_formatted' => round($percent, 1) . '%',
            'winner' => $winner
        ];
    }
}