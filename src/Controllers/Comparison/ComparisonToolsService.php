<?php

declare(strict_types=1);

namespace App\Controllers\Comparison;

use App\Core\Database;

final class ComparisonToolsService
{
    public function getCnaeLucrativos(): array
    {
        $db = Database::connection();
        
        $stmt = $db->query("
            SELECT cnae_main_code as cnae, 
                   COUNT(*) as total_empresas,
                   AVG(capital_social) as media_capital,
                   SUM(capital_social) as total_capital
            FROM companies 
            WHERE is_hidden = 0 
              AND cnae_main_code IS NOT NULL 
              AND cnae_main_code != ''
              AND capital_social IS NOT NULL 
              AND capital_social > 0
            GROUP BY cnae_main_code
            HAVING total_empresas >= 100
            ORDER BY media_capital DESC
            LIMIT 10
        ");
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['media_formatada'] = 'R$ ' . number_format($row['media_capital'], 2, ',', '.');
            $row['total_formatado'] = 'R$ ' . number_format($row['total_capital'], 2, ',', '.');
        }
        
        return [
            'title' => ' CNAEs Mais Lucrativos',
            'subtitle' => 'Atividades com maior média de capital social',
            'results' => $results,
            'count' => count($results),
            'type' => 'cnae'
        ];
    }

    public function getEstadosEmpresas(): array
    {
        $db = Database::connection();
        
        $stmt = $db->query("
            SELECT 
                state,
                COUNT(*) as total_empresas,
                SUM(CASE WHEN status = 'ativa' THEN 1 ELSE 0 END) as ativas,
                SUM(CASE WHEN status != 'ativa' THEN 1 ELSE 0 END) as inativas,
                AVG(capital_social) as media_capital
            FROM companies 
            WHERE is_hidden = 0 AND state IS NOT NULL AND state != ''
            GROUP BY state 
            ORDER BY total_empresas DESC
        ");
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['media_formatada'] = 'R$ ' . number_format($row['media_capital'] ?? 0, 2, ',', '.');
            $row['taxa_ativa'] = $row['total_empresas'] > 0 
                ? round(($row['ativas'] / $row['total_empresas']) * 100, 1) : 0;
        }
        
        return [
            'title' => 'Estados',
            'subtitle' => 'Quantidade de empresas por estado',
            'results' => $results,
            'count' => count($results),
            'type' => 'estado'
        ];
    }

    public function getRegioesBrasil(): array
    {
        $db = Database::connection();
        
        $stmt = $db->query("
            SELECT 
                CASE 
                    WHEN state IN ('AC', 'AM', 'AP', 'PA', 'RO', 'RR', 'TO') THEN 'Norte'
                    WHEN state IN ('AL', 'BA', 'CE', 'MA', 'PB', 'PE', 'PI', 'RN', 'SE') THEN 'Nordeste'
                    WHEN state IN ('DF', 'GO', 'MT', 'MS') THEN 'Centro-Oeste'
                    WHEN state IN ('ES', 'MG', 'RJ', 'SP') THEN 'Sudeste'
                    WHEN state IN ('PR', 'RS', 'SC') THEN 'Sul'
                    ELSE 'Outros'
                END as regiao,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'ativa' THEN 1 ELSE 0 END) as ativas,
                AVG(capital_social) as media_capital
            FROM companies 
            WHERE is_hidden = 0
            GROUP BY regiao
            ORDER BY total DESC
        ");
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['media_formatada'] = 'R$ ' . number_format($row['media_capital'] ?? 0, 2, ',', '.');
            $row['percent'] = $row['total'] > 0 ? round(($row['ativas'] / $row['total']) * 100, 1) : 0;
        }
        
        return [
            'title' => 'Regiões do Brasil',
            'subtitle' => 'Distribuição de empresas por região',
            'results' => $results,
            'count' => count($results),
            'type' => 'regiao'
        ];
    }

    public function getDistribuicaoPorte(): array
    {
        $db = Database::connection();
        
        $stmt = $db->query("
            SELECT 
                CASE 
                    WHEN company_size IS NULL OR company_size = '' THEN 'Não Informado'
                    WHEN company_size LIKE '%ME%' AND company_size NOT LIKE '%EPP%' THEN 'MEI'
                    WHEN company_size LIKE '%EPP%' THEN 'EPP'
                    WHEN company_size LIKE '%Pequena%' THEN 'Pequena Empresa'
                    WHEN company_size LIKE '%Média%' THEN 'Média Empresa'
                    WHEN company_size LIKE '%Grande%' THEN 'Grande Empresa'
                    ELSE 'Outros'
                END as porte,
                COUNT(*) as total,
                AVG(capital_social) as media_capital
            FROM companies 
            WHERE is_hidden = 0
            GROUP BY porte
            ORDER BY total DESC
        ");
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['media_formatada'] = 'R$ ' . number_format($row['media_capital'] ?? 0, 2, ',', '.');
        }
        
        return [
            'title' => 'Porte Empresarial',
            'subtitle' => 'Distribuição por porte',
            'results' => $results,
            'count' => count($results),
            'type' => 'porte'
        ];
    }

    public function getAtivasVsInativas(): array
    {
        $db = Database::connection();
        
        $stmt = $db->query("
            SELECT 
                status,
                COUNT(*) as total,
                AVG(capital_social) as media_capital,
                SUM(capital_social) as total_capital
            FROM companies 
            WHERE is_hidden = 0 AND status IS NOT NULL AND status != ''
            GROUP BY status
            ORDER BY total DESC
        ");
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['media_formatada'] = 'R$ ' . number_format($row['media_capital'] ?? 0, 2, ',', '.');
            $row['total_formatado'] = 'R$ ' . number_format($row['total_capital'] ?? 0, 2, ',', '.');
        }
        
        return [
            'title' => 'Status',
            'subtitle' => 'Ativas vs Inativas',
            'results' => $results,
            'count' => count($results),
            'type' => 'status'
        ];
    }

    public function getMelhoresCidades(): array
    {
        $db = Database::connection();
        
        $stmt = $db->query("
            SELECT 
                city,
                state,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'ativa' THEN 1 ELSE 0 END) as ativas,
                AVG(capital_social) as media_capital
            FROM companies 
            WHERE is_hidden = 0 AND city IS NOT NULL AND city != ''
            GROUP BY city, state
            HAVING total >= 50
            ORDER BY ativas DESC, total DESC
            LIMIT 20
        ");
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['media_formatada'] = 'R$ ' . number_format($row['media_capital'] ?? 0, 2, ',', '.');
            $row['taxa_ativa'] = $row['total'] > 0 
                ? round(($row['ativas'] / $row['total']) * 100, 1) : 0;
        }
        
        return [
            'title' => 'Melhores Cidades',
            'subtitle' => 'Cidades com mais empresas ativas',
            'results' => $results,
            'count' => count($results),
            'type' => 'cidade'
        ];
    }
}