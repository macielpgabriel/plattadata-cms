<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Database;
use App\Core\Cache;
use App\Core\SafeDatabase;
use App\Core\Logger;

final class ActivityController
{
    /**
     * Lista todas as atividades econômicas (CNAE).
     */
    public function index(): void
    {
        $data = [
            'title' => 'Atividades Econômicas (CNAE)',
            'activities' => [],
            'metaDescription' => 'Lista de atividades econômicas CNAE com número de empresas no Brasil.'
        ];
        
        try {
            $cnaeStats = SafeDatabase::query("
                SELECT 
                    cnae_main_code as code,
                    COUNT(*) as total_empresas
                FROM companies
                WHERE cnae_main_code IS NOT NULL AND cnae_main_code != ''
                GROUP BY cnae_main_code
                ORDER BY total_empresas DESC
                LIMIT 100
            ", [], []);
            
            if (empty($cnaeStats)) {
                View::render('public/activities/index', $data);
                return;
            }
            
            $cnaeList = [];
            foreach ($cnaeStats as $item) {
                $code = $item['code'];
                $cnaeList[$code] = (int) $item['total_empresas'];
            }
            
            $cnaeDescriptions = $this->getCnaeDescriptionsFromApi(array_keys($cnaeList));
            
            $activities = [];
            foreach ($cnaeList as $code => $total) {
                $codeStr = is_string($code) ? $code : strval($code);
                $normalizedCode = $this->normalizeCnaeCode($codeStr);
                $desc = $cnaeDescriptions[$normalizedCode] ?? $this->getCnaeDescription($codeStr);
                $section = $this->getCnaeSection($codeStr);
                $activities[] = [
                    'code' => $codeStr,
                    'description' => $desc,
                    'section' => $section,
                    'total_empresas' => $total,
                ];
            }
            
            $data['activities'] = $activities;
        } catch (\Exception $e) {
            $data['error'] = 'Erro ao carregar dados: ' . $e->getMessage();
            Logger::error("ActivityController error: " . $e->getMessage());
        }
        
        View::render('public/activities/index', $data);
    }

    /**
     * Sincroniza CNAEs da API do IBGE (para admin)
     * Padrão: verifica no banco, se não existir busca na API e salva
     */
    public function syncCnae(): int
    {
        $db = Database::connection();
        
        // Verifica se já tem CNAEs sincronizados
        $stmt = $db->query("SELECT COUNT(*) as total FROM cnae_activities");
        $existingCount = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Se já tem mais de 1000, não precisa sincronizar novamente
        if ($existingCount >= 1000) {
            return $existingCount;
        }
        
        $url = "https://servicodados.ibge.gov.br/api/v2/cnae/subclasses";
        $context = stream_context_create([
            'http' => ['timeout' => 120, 'header' => "User-Agent: Mozilla/5.0\r\n"]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new \Exception("Falha ao conectar na API do IBGE");
        }
        
        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \Exception("Resposta inválida da API");
        }
        
        // Garante que a tabela existe
        $db->exec("CREATE TABLE IF NOT EXISTS cnae_activities (
            code VARCHAR(20) PRIMARY KEY,
            slug VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            section VARCHAR(10) NULL
        )");
        
        $count = 0;
        foreach ($data as $item) {
            $apiCode = (string) ($item['id'] ?? '');
            $desc = $item['descricao'] ?? '';
            
            if (empty($apiCode) || empty($desc)) continue;
            
            $section = '';
            if (strlen($apiCode) >= 1) {
                $section = substr($apiCode, 0, 1);
            }
            
            // Verifica se já existe antes de inserir
            $checkStmt = $db->prepare("SELECT code FROM cnae_activities WHERE code = ? LIMIT 1");
            $checkStmt->execute([$apiCode]);
            
            if ($checkStmt->fetch()) {
                continue; // Já existe, pula
            }
            
            $stmt = $db->prepare("
                INSERT INTO cnae_activities (code, slug, description, section) 
                VALUES (:code, :slug, :desc, :section)
            ");
            $stmt->execute([
                'code' => $apiCode,
                'slug' => slugify($desc),
                'desc' => $desc,
                'section' => $section,
            ]);
            $count++;
        }
        
        return $count;
    }
    
    private function normalizeCnaeCode(string $code): string
    {
        return preg_replace('/[^0-9]/', '', $code);
    }
    
    private function getCnaeDescriptionsFromApi(array $codes): array
    {
        $result = [];
        $codesNeeded = [];
        foreach ($codes as $c) {
            $c = is_string($c) ? $c : strval($c);
            $cleaned = preg_replace('/[^0-9]/', '', $c);
            if (!empty($cleaned) && strlen($cleaned) >= 4) {
                $codesNeeded[$cleaned] = true;
            }
        }
        
        try {
            $db = Database::connection();
            
            $savedStmt = $db->query("SELECT code, description FROM cnae_activities");
            $saved = $savedStmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($saved as $row) {
                $normalizedCode = $this->normalizeCnaeCode($row['code']);
                if (!empty($row['description'])) {
                    $result[$row['code']] = $row['description'];
                    unset($codesNeeded[$normalizedCode]);
                }
            }
            
            if (empty($codesNeeded)) {
                return $result;
            }
        } catch (\Exception $e) {
        }
        
        $url = "https://servicodados.ibge.gov.br/api/v2/cnae/subclasses";
        $context = stream_context_create([
            'http' => ['timeout' => 60, 'header' => "User-Agent: Mozilla/5.0\r\n"]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return $result;
        }
        
        $data = json_decode($response, true);
        if (!is_array($data)) {
            return $result;
        }
        
        try {
            $db = Database::connection();
            $db->exec("CREATE TABLE IF NOT EXISTS cnae_activities (
                code VARCHAR(20) PRIMARY KEY,
                slug VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                section VARCHAR(10) NULL
            )");
        } catch (\Exception $e) {
        }
        
        $toSave = [];
        foreach ($data as $item) {
            $apiCode = $item['codigo'] ?? null;
            $desc = $item['descricao'] ?? '';
            
            if (empty($apiCode) || !is_string($apiCode)) {
                continue;
            }
            
            $normalizedApiCode = $this->normalizeCnaeCode($apiCode);
            
            if (!isset($codesNeeded[$normalizedApiCode])) {
                continue;
            }
            
            if (!$this->isValidCnaeDescription($desc)) {
                continue;
            }
            
            $result[$normalizedApiCode] = $desc;
            $toSave[] = [
                'code' => $apiCode,
                'slug' => $this->generateSlug($desc),
                'description' => $this->sanitizeDescription($desc),
                'section' => $this->getCnaeSection($apiCode),
            ];
        }
        
        if (!empty($toSave)) {
            try {
                $db = Database::connection();
                $db->beginTransaction();
                
                foreach ($toSave as $item) {
                    $stmt = $db->prepare("
                        INSERT INTO cnae_activities (code, slug, description, section) 
                        VALUES (:code, :slug, :desc, :section)
                        ON DUPLICATE KEY UPDATE description = :desc2, slug = :slug2
                    ");
                    $stmt->execute([
                        'code' => $item['code'],
                        'slug' => $item['slug'],
                        'desc' => $item['description'],
                        'section' => $item['section'],
                        'desc2' => $item['description'],
                        'slug2' => $item['slug'],
                    ]);
                }
                
                $db->commit();
            } catch (\Exception $e) {
                try { $db->rollBack(); } catch (\Exception $e2) {
                    Logger::warning('Activity rollback failed: ' . $e2->getMessage());
                }
            }
        }
        
        return $result;
    }
    
    private function isValidCnaeDescription($desc): bool
    {
        if (empty($desc) || !is_string($desc)) {
            return false;
        }
        
        $desc = trim($desc);
        if (strlen($desc) < 5 || strlen($desc) > 500) {
            return false;
        }
        
        if (preg_match('/^(sem descrica|undefined|null|n\/a)$/i', $desc)) {
            return false;
        }
        
        return true;
    }
    
    private function sanitizeDescription($desc): string
    {
        if (!is_string($desc)) {
            return '';
        }
        $desc = trim($desc);
        $desc = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
        $desc = preg_replace('/\s+/', ' ', $desc);
        return $desc;
    }
    
    private function generateSlug($desc): string
    {
        if (!is_string($desc)) {
            return '';
        }
        return slugify($desc);
    }
    
    private function getCnaeDescription($code): string
    {
        if (!is_string($code)) {
            return 'Atividade econômica';
        }
        
        $prefix = substr($code, 0, 2);
        $firstDigit = substr($code, 0, 1);
        
        $cnaeNames = [
            '56.10-2' => 'Restaurantes e Similar',
            '45.30-7' => 'Comérciovarejista de Veículos',
            '86.30-5' => 'Atividades de Atenção à Saúde',
            '49.30-2' => 'Transporte Rodoviário de Cargas',
            '41.20-4' => 'Construção de Edifícios',
            '47.44-0' => 'Comérciovarejista de Ferragens',
            '82.11-3' => 'Serviços de Escritório',
            '69.20-6' => 'Atividades de Contabilidade',
            '73.19-0' => 'Agências de Publicidade',
            '96.17-5' => 'Cabeleireiros e Salões de Beleza',
            '68.20-1' => 'Aluguel de Imóveis',
            '70.22-3' => 'Consultoria Empresarial',
            '85.10-2' => 'Educação Infantil',
            '85.20-1' => 'Educação Fundamental',
            '85.32-1' => 'Ensino Médio',
            '86.10-1' => 'Atividades Hospitalares',
            '47.51-1' => 'Comérciovarejista de Tecidos',
            '45.11-0' => 'Comérciovarejista de Automóveis',
            '47.21-1' => 'Comérciovarejista de Mercadorias em Geral',
            '78.10-0' => 'Locação de Mão de Obra',
        ];
        
        if (isset($cnaeNames[$code])) {
            return $cnaeNames[$code];
        }
        
        $sections = [
            '0' => 'Agricultura, pecuária e serviços relacionados',
            '1' => 'Cultivo de produtos e criação de animais',
            '2' => 'Produção florestal e pesca',
            '3' => 'Extração de minerais e pedras',
            '4' => 'Indústria de transformação',
            '5' => 'Fabricação de máquinas e equipamentos',
            '6' => 'Eletricidade, água e saneamento',
            '7' => 'Construção civil',
            '8' => 'Comércio varejista e atacadista',
            '9' => 'Transporte e armazenamento',
            '41' => 'Transporte terrestre de passageiros',
            '42' => 'Transporte terrestre de cargas',
            '43' => 'Transporte por tubulações',
            '45' => 'Comércio de veículos',
            '46' => 'Comércio atacadista',
            '47' => 'Comércio varejista',
            '49' => 'Transporte terrestre',
            '50' => 'Transporte aquaviário',
            '51' => 'Transporte aéreo',
            '55' => 'Alojamento e酒店aria',
            '56' => 'Alimentação e bebidas',
            '58' => 'Edição e conteúdo digital',
            '59' => 'Filmagem e produção audiovisual',
            '60' => 'Rádio e televisão',
            '61' => 'Telecomunicações',
            '62' => 'Desenvolvimento de software',
            '63' => 'Serviços de informação',
            '64' => 'Atividades financeiras',
            '65' => 'Seguros e previdência',
            '68' => 'Atividades imobiliárias',
            '69' => 'Serviços jurídicos e contábeis',
            '70' => 'Consultoria e gestão empresarial',
            '71' => 'Arquitetura e engenharia',
            '72' => 'Pesquisa científica',
            '73' => 'Publicidade e pesquisa de mercado',
            '74' => 'Design e atividades especializadas',
            '75' => 'Veterinária',
            '77' => 'Locação de veículos e máquinas',
            '78' => 'Locação de mão de obra',
            '79' => 'Agências de viagens',
            '80' => 'Vigilância e segurança',
            '81' => 'Serviços para edifícios',
            '82' => 'Serviços administrativos e apoio',
            '84' => 'Administração pública',
            '85' => 'Educação',
            '86' => 'Atividades de saúde',
            '87' => 'Assistência social',
            '90' => 'Artes e espetáculos',
            '91' => 'Bibliotecas e museus',
            '92' => 'Jogos e apostas',
            '93' => 'Atividades esportivas',
            '95' => 'Reparação de veículos e eletrônicos',
            '96' => 'Salões de beleza e outros serviços',
        ];
        
        return $sections[$prefix] ?? $sections[$firstDigit] ?? "Atividade econômica {$prefix}";
    }
    
    private function getCnaeSection($code): string
    {
        if (!is_string($code) || strlen($code) < 1) {
            return 'Outros';
        }
        
        $section = substr($code, 0, 1);
        $sections = [
            'A' => 'Agricultura',
            'B' => 'Mineração',
            'C' => 'Indústrai',
            'D' => 'Eletricidade',
            'E' => 'Água e Esgoto',
            'F' => 'Construção',
            'G' => 'Comérciovarejista',
            'H' => 'Transporte',
            'I' => 'Alojamento',
            'J' => 'Informação',
            'K' => 'Atividades Financeiras',
            'L' => 'Imobiliárias',
            'M' => 'Profissionais',
            'N' => 'Adminstrativas',
            'O' => 'Governo',
            'P' => 'Educação',
            'Q' => 'Saúde',
            'R' => 'Artes',
            'S' => 'Outros Serviços',
            'T' => 'Doméstico',
            'U' => 'Internacional',
        ];
        return $sections[$section] ?? 'Outros';
    }

    /**
     * Lista empresas por atividade econômica (CNAE).
     */
    public function show(array $params): void
    {
        $code = (string)($params['code'] ?? '');
        $slug = (string)($params['slug'] ?? '');

        if (empty($code)) {
            redirect('/empresas');
        }

        $cacheKey = "activity_page_" . md5($code);
        $cached = Cache::get($cacheKey);
        if ($cached) {
            View::render('public/activities/show', $cached);
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare("SELECT * FROM cnae_activities WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        $activity = $stmt->fetch();

        $description = $activity['description'] ?? "Atividade Econômica $code";

        $stmt = $db->prepare("SELECT cnpj, legal_name, trade_name, city, state, status FROM companies WHERE cnae_main_code = ? ORDER BY opened_at DESC LIMIT 50");
        $stmt->execute([$code]);
        $companies = $stmt->fetchAll();

        $data = [
            'title' => $description,
            'activity' => $activity,
            'companies' => $companies,
            'breadcrumbs' => [
                ['label' => 'Início', 'url' => '/'],
                ['label' => 'Atividades', 'url' => '/atividades'],
                ['label' => $description, 'url' => null],
            ],
        ];

        Cache::set($cacheKey, $data, 3600);
        View::render('public/activities/show', $data);
    }
}
