<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Database;
use App\Core\Cache;
use App\Core\Logger;
use App\Services\ValidationService;

final class PartnerController
{
    private ValidationService $validator;

    public function __construct()
    {
        $this->validator = new ValidationService();
    }

    public function index(): void
    {
        $data = [
            'title' => 'Sócios',
            'partners' => [],
            'metaDescription' => 'Lista dos principais sócios de empresas no Brasil.'
        ];
        
        try {
            $db = Database::connection();
            
            $stmt = $db->query("
                SELECT 
                    name as partner_name,
                    COUNT(*) as total_empresas
                FROM company_partners
                WHERE name IS NOT NULL AND name != ''
                GROUP BY name
                ORDER BY total_empresas DESC
                LIMIT 100
            ");
            $partners = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $data['partners'] = $partners;
        } catch (\Exception $e) {
            $data['error'] = 'Erro ao carregar dados: ' . $e->getMessage();
            Logger::error("PartnerController error: " . $e->getMessage());
        }
        
        View::render('public/partners/index', $data);
    }
    
    public function show(array $params): void
    {
        $rawName = trim((string)($params['name'] ?? ''));
        $decodedName = str_replace('+', ' ', $rawName);
        $name = $this->validator->safeString($decodedName, 200);

        if (!$this->validator->name($name) && strlen($name) < 3) {
            redirect('/empresas');
        }

        $cacheKey = "partner_page_" . md5($name);
        $cached = Cache::get($cacheKey);
        if ($cached) {
            View::render('public/partners/show', $cached);
            return;
        }

        $db = Database::connection();
        
        $stmt = $db->prepare("
            SELECT DISTINCT 
                c.cnpj, c.legal_name, c.trade_name, c.city, c.state, c.status
            FROM company_partners cp
            JOIN companies c ON c.id = cp.company_id
            WHERE cp.name LIKE ?
            ORDER BY c.legal_name
            LIMIT 100
        ");
        $searchName = '%' . $name . '%';
        $stmt->execute([$searchName]);
        $companies = $stmt->fetchAll();

        $data = [
            'title' => "Socio: $name",
            'partnerName' => $name,
            'companies' => $companies,
            'breadcrumbs' => [
                ['label' => 'Inicio', 'url' => '/'],
                ['label' => 'Empresas', 'url' => '/empresas'],
                ['label' => "Socio: $name", 'url' => null],
            ],
        ];

        Cache::set($cacheKey, $data, 1800);
        View::render('public/partners/show', $data);
    }
}
