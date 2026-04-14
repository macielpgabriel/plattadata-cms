<?php

declare(strict_types=1);

namespace App\Controllers\Comparison;

use App\Core\Database;

final class ComparisonRankingsService
{
    public function getRankEmpresasEstado(): array
    {
        $db = Database::connection();
        
        $stmt = $db->query("
            SELECT state, COUNT(*) as total
            FROM companies 
            WHERE is_hidden = 0 AND state IS NOT NULL AND state != ''
            GROUP BY state 
            ORDER BY total DESC
        ");
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $total = array_sum(array_column($results, 'total'));
        $previousTotal = 0;
        
        foreach ($results as &$row) {
            $row['percent'] = $total > 0 ? round(($row['total'] / $total) * 100, 2) : 0;
            $row['percent_change'] = $total > 0 ? round((($row['total'] - $previousTotal) / max($previousTotal, 1)) * 100, 1) : 0;
            $previousTotal = $row['total'];
        }
        
        return [
            'title' => 'Empresas por Estado',
            'subtitle' => 'Distribuição de empresas cadastradas por UF',
            'results' => $results,
            'count' => count($results)
        ];
    }

    public function getRankCidades(int $limit = 10): array
    {
        $db = Database::connection();
        
        $stmt = $db->prepare("
            SELECT city, state, COUNT(*) as total
            FROM companies 
            WHERE is_hidden = 0 AND city IS NOT NULL AND city != ''
            GROUP BY city, state 
            ORDER BY total DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return [
            'title' => 'Cidades com Mais Empresas',
            'subtitle' => "Top {$limit} cidades com maior número de empresas",
            'results' => $results,
            'count' => count($results)
        ];
    }

    public function getRankCnae(int $limit = 15): array
    {
        $db = Database::connection();
        
        $stmt = $db->prepare("
            SELECT cnae_main_code as cnae, COUNT(*) as total
            FROM companies 
            WHERE is_hidden = 0 AND cnae_main_code IS NOT NULL AND cnae_main_code != ''
            GROUP BY cnae_main_code 
            ORDER BY total DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $total = array_sum(array_column($results, 'total'));
        
        foreach ($results as &$row) {
            $row['percent'] = $total > 0 ? round(($row['total'] / $total) * 100, 2) : 0;
            $row['cnae_description'] = $this->getCnaeDescription($row['cnae']);
        }
        
        return [
            'title' => 'Atividades Mais Comuns',
            'subtitle' => " CNAEs com maior número de empresas",
            'results' => $results,
            'count' => count($results)
        ];
    }

    public function getRankPorte(): array
    {
        $db = Database::connection();
        
        $stmt = $db->query("
            SELECT 
                CASE 
                    WHEN company_size IS NULL OR company_size = '' THEN 'Não Informado'
                    WHEN company_size LIKE '%ME%' THEN 'MEI'
                    WHEN company_size LIKE '%EPP%' THEN 'EPP'
                    WHEN company_size LIKE '%Pequena%' THEN 'Pequena'
                    WHEN company_size LIKE '%Média%' THEN 'Média'
                    WHEN company_size LIKE '%Grande%' THEN 'Grande'
                    ELSE company_size
                END as porte,
                COUNT(*) as total
            FROM companies 
            WHERE is_hidden = 0
            GROUP BY porte 
            ORDER BY total DESC
        ");
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $total = array_sum(array_column($results, 'total'));
        
        foreach ($results as &$row) {
            $row['percent'] = $total > 0 ? round(($row['total'] / $total) * 100, 2) : 0;
        }
        
        return [
            'title' => 'Empresas por Porte',
            'subtitle' => 'Distribuição por porte empresarial',
            'results' => $results,
            'count' => count($results)
        ];
    }

    public function getRankStatus(): array
    {
        $db = Database::connection();
        
        $stmt = $db->query("
            SELECT status, COUNT(*) as total
            FROM companies 
            WHERE is_hidden = 0 AND status IS NOT NULL AND status != ''
            GROUP BY status 
            ORDER BY total DESC
        ");
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $total = array_sum(array_column($results, 'total'));
        
        $statusLabels = [
            'ativa' => 'Ativa',
            'inativa' => 'Inativa',
            'suspensa' => 'Suspensa',
            'baixada' => 'Baixada',
            'pendente' => 'Pendente',
        ];
        
        foreach ($results as &$row) {
            $row['percent'] = $total > 0 ? round(($row['total'] / $total) * 100, 2) : 0;
            $row['status_label'] = $statusLabels[$row['status']] ?? ucfirst($row['status']);
        }
        
        return [
            'title' => 'Status das Empresas',
            'subtitle' => 'Distribuição por situação cadastral',
            'results' => $results,
            'count' => count($results)
        ];
    }

    public function getRankArrecadacao(int $limit = 10): array
    {
        $db = Database::connection();
        
        $stmt = $db->prepare("
            SELECT state, SUM(capital_social) as total_capital
            FROM companies 
            WHERE is_hidden = 0 AND state IS NOT NULL AND capital_social IS NOT NULL AND capital_social > 0
            GROUP BY state 
            ORDER BY total_capital DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $total = array_sum(array_column($results, 'total_capital'));
        
        foreach ($results as &$row) {
            $row['percent'] = $total > 0 ? round(($row['total_capital'] / $total) * 100, 2) : 0;
            $row['capital_formatado'] = 'R$ ' . number_format($row['total_capital'], 2, ',', '.');
        }
        
        return [
            'title' => 'Capital Social por Estado',
            'subtitle' => 'Estados com maior soma de capital social',
            'results' => $results,
            'count' => count($results)
        ];
    }

    private function getCnaeDescription(string $code): string
    {
        $descriptions = [
            '6201-5/00' => 'Desenvolvimento de Software',
            '6202-3/00' => 'Consultoria em TI',
            '5611-2/01' => 'Restaurantes',
            '4711-3/02' => 'Supermercados',
            '4520-0/01' => 'Serviços de Manutenção Automotiva',
        ];
        
        return $descriptions[$code] ?? $code;
    }
}