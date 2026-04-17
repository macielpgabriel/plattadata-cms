<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Services\OpenCnpjService;

final class CompanyEnrichmentService
{
    public function enrichCompany(array $company): array
    {
        $enriched = $company;
        
        if (!empty($company['cnpj'])) {
            $enriched['financial_data'] = @$this->getFinancialData($company['cnpj']);
            $enriched['partner_data'] = @$this->getPartnerData($company['cnpj']);
            $enriched['location_data'] = @$this->getLocationData($company);
            $enriched['market_data'] = @$this->getMarketData($company);
            $enriched['compliance_data'] = @$this->getComplianceData($company['cnpj']);
            $enriched['social_data'] = @$this->getSocialMediaData($company);
            $enriched['predictive_data'] = @$this->getPredictiveData($company);
            $enriched['contact_data'] = @$this->getContactData($company);
            
            $extendedService = new ExtendedDataService();
            $rawData = is_array($company['raw_data'] ?? null) 
                ? $company['raw_data'] 
                : (json_decode((string) ($company['raw_data'] ?? '{}'), true) ?: []);
            
            $cnaesSecundarios = $rawData['cnaes_secundarios'] ?? $rawData['atividades_secundarias'] ?? $rawData['cnaes'] ?? ($company['cnaes_secundarios'] ?? []);
            
            $enriched['extended_data'] = @$extendedService->getExtendedData([
                'cnpj' => $company['cnpj'],
                'municipal_ibge_code' => $company['municipal_ibge_code'] ?? null,
                'cnaes_secundarios' => $cnaesSecundarios,
                'natureza_juridica' => $rawData['natureza_juridica'] ?? ($company['natureza_juridica'] ?? null),
                'raw_data' => $company['raw_data'] ?? null,
            ]);
        }

        return $enriched;
    }

    private function getFinancialData(string $cnpj): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                SELECT revenue_estimate, tax_history, installment_plans, tax_debts 
                FROM companies 
                WHERE cnpj = ?
            ");
            $stmt->execute([$cnpj]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($data) {
                return [
                    'revenue_estimate' => $data['revenue_estimate'] ?? null,
                    'tax_history' => json_decode((string) $data['tax_history'], true) ?? [],
                    'installment_plans' => json_decode((string) $data['installment_plans'], true) ?? [],
                    'tax_debts' => json_decode((string) $data['tax_debts'], true) ?? [],
                ];
            }
        } catch (\Exception $e) {
            // Return empty financial data
        }

        return $this->estimateFinancialData($cnpj);
    }

    private function estimateFinancialData(string $cnpj): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                SELECT AVG(revenue) as avg_revenue, COUNT(*) as total
                FROM companies 
                WHERE cnae_main_code = (
                    SELECT cnae_main_code FROM companies WHERE cnpj = ?
                ) AND revenue > 0
            ");
            $stmt->execute([$cnpj]);
            $avg = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $companySize = $db->prepare("SELECT company_size, capital_social FROM companies WHERE cnpj = ?");
            $companySize->execute([$cnpj]);
            $company = $companySize->fetch(\PDO::FETCH_ASSOC);
            
            $multiplier = match ($company['company_size'] ?? 'ME') {
                'MEI', '01' => 0.3,
                'ME', '03' => 0.7,
                'EPP', '05' => 1.0,
                'EG', 'GRANDE PORTE', 'DEMAIS' => 2.5,
                default => 0.5,
            };
            
            return [
                'revenue_estimate' => ($avg['avg_revenue'] ?? 0) * $multiplier,
                'tax_history' => [],
                'installment_plans' => [],
                'tax_debts' => [],
            ];
        } catch (\Exception $e) {
            return [
                'revenue_estimate' => null,
                'tax_history' => [],
                'installment_plans' => [],
                'tax_debts' => [],
            ];
        }
    }

    private function getPartnerData(string $cnpj): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                SELECT partners_data, total_partners 
                FROM companies 
                WHERE cnpj = ?
            ");
            $stmt->execute([$cnpj]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!empty($data['partners_data'])) {
                return [
                    'partners' => json_decode((string) $data['partners_data'], true) ?? [],
                    'total' => (int) ($data['total_partners'] ?? 0),
                ];
            }

            $stmt = $db->prepare("
                SELECT name, partner_document, role, participation_percentage, is_current
                FROM partner_history 
                WHERE company_id = (SELECT id FROM companies WHERE cnpj = ?)
                ORDER BY is_current DESC, exited_at DESC NULLS FIRST
            ");
            $stmt->execute([$cnpj]);
            $partners = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return [
                'partners' => array_map(fn($p) => [
                    'name' => $p['name'],
                    'document' => $p['partner_document'] ? substr($p['partner_document'], 0, 3) . '***' : null,
                    'role' => $p['role'],
                    'participation' => $p['participation_percentage'],
                    'current' => (bool) $p['is_current'],
                ], $partners),
                'total' => count($partners),
            ];
        } catch (\Exception $e) {
            return ['partners' => [], 'total' => 0];
        }
    }

    private function getLocationData(array $company): array
    {
        return [
            'latitude' => $company['latitude'] ?? null,
            'longitude' => $company['longitude'] ?? null,
            'map_url' => $this->generateMapUrl($company),
            'region_type' => $company['region_type'] ?? $this->determineRegionType($company),
            'full_address' => $this->formatFullAddress($company),
        ];
    }

    private function generateMapUrl(array $company): string
    {
        $lat = $company['latitude'] ?? null;
        $lng = $company['longitude'] ?? null;
        
        if ($lat && $lng) {
            return "https://www.google.com/maps?q={$lat},{$lng}&z=15";
        }
        
        $address = urlencode(trim(implode(' ', array_filter([
            $company['street'] ?? '',
            $company['address_number'] ?? '',
            $company['district'] ?? '',
            $company['city'] ?? '',
            $company['state'] ?? '',
        ]))));
        
        return "https://www.google.com/maps/search/?api=1&query={$address}";
    }

    private function determineRegionType(array $company): string
    {
        $city = strtolower((string) ($company['city'] ?? ''));
        $state = strtoupper((string) ($company['state'] ?? ''));
        
        $capitals = [
            'SP' => ['são paulo', 'sao paulo'],
            'RJ' => ['rio de janeiro'],
            'MG' => ['belo horizonte'],
            'BA' => ['salvador'],
            'RS' => ['porto alegre'],
            'DF' => ['brasília', 'brasilia'],
            'GO' => ['goiânia', 'goiania'],
            'PE' => ['recife'],
            'CE' => ['fortaleza'],
            'PR' => ['curitiba'],
        ];
        
        if (isset($capitals[$state]) && in_array($city, $capitals[$state])) {
            return 'capital';
        }
        
        return 'interior';
    }

    private function formatFullAddress(array $company): string
    {
        $parts = array_filter([
            $company['street'] ?? '',
            $company['address_number'] ?? '',
            $company['address_complement'] ?? '',
            $company['district'] ?? '',
            $company['city'] ?? '',
            $company['state'] ?? '',
            ($company['postal_code'] ?? '') ? 'CEP ' . $company['postal_code'] : '',
        ]);
        
        return implode(', ', $parts);
    }

    private function getMarketData(array $company): array
    {
        $cnpj = $company['cnpj'] ?? '';
        
        if (!empty($cnpj)) {
            $marketService = new MarketAnalysisService();
            $marketResult = $marketService->analyzeMarket($cnpj, $company);
            
            return [
                'competitors_count' => $marketResult['competitors_count'] ?? 0,
                'market_trend' => $marketResult['market_trend'] ?? 'estavel',
                'competition_score' => $marketResult['competition_score'] ?? 50,
                'sector_growth' => $marketResult['sector_growth'] ?? null,
                'market_size' => $marketResult['market_size'] ?? null,
                'source' => $marketResult['source'] ?? 'calculated',
            ];
        }
        
        return [
            'competitors_count' => 0,
            'market_trend' => 'estavel',
            'competition_score' => 50,
            'source' => 'calculated',
        ];
    }
    
    public static function updateCompetitors(int $companyId, ?string $cnae, ?string $city, ?string $state): int
    {
        if (empty($cnae) || empty($city) || empty($state)) {
            return 0;
        }
        
        $cnaeClean = preg_replace('/[^0-9]/', '', $cnae);
        if (strlen($cnaeClean) < 4) {
            return 0;
        }
        
        $db = Database::connection();
        
        try {
            $competitorsStmt = $db->prepare("
                SELECT id, cnpj, legal_name, city, state
                FROM companies 
                WHERE id != :company_id 
                    AND cnae_main_code LIKE :cnae_like
                    AND city = :city 
                    AND state = :state
                    AND is_hidden = 0
                    AND status != 'INATIVA'
                ORDER BY RAND()
                LIMIT 10
            ");
            $competitorsStmt->execute([
                'company_id' => $companyId,
                'cnae_like' => $cnaeClean . '%',
                'city' => $city,
                'state' => $state,
            ]);
            $competitors = $competitorsStmt->fetchAll();
            
            $deleted = $db->exec("DELETE FROM company_competitors WHERE company_id = " . (int) $companyId);
            
            $inserted = 0;
            foreach ($competitors as $comp) {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO company_competitors (company_id, competitor_cnpj, competitor_name, similarity_score)
                        VALUES (:company_id, :cnpj, :name, 80)
                    ");
                    $stmt->execute([
                        'company_id' => $companyId,
                        'cnpj' => $comp['cnpj'],
                        'name' => $comp['legal_name'],
                    ]);
                    $inserted++;
                } catch (\Exception $e) {
                }
            }
            
            return $inserted;
        } catch (\Exception $e) {
            Logger::error('Error updating competitors: ' . $e->getMessage());
            return 0;
        }
    }

    private function calculateMarketTrend(?string $cnae): string
    {
        if (!$cnae) return 'estavel';
        
        $growingCnaes = ['62', '63', '64', '65', '66', '70', '71', '72'];
        $decliningCnaes = ['45', '46'];
        
        $prefix = substr(str_replace(['.', '-'], '', $cnae), 0, 2);
        
        if (in_array($prefix, $growingCnaes)) {
            return 'crescendo';
        }
        if (in_array($prefix, $decliningCnaes)) {
            return 'declinando';
        }
        
        return 'estavel';
    }

    private function calculateCompetitionScore(int $competitors): int
    {
        return match (true) {
            $competitors > 1000 => 90,
            $competitors > 500 => 75,
            $competitors > 100 => 60,
            $competitors > 50 => 45,
            $competitors > 10 => 30,
            default => 15,
        };
    }

    private function getComplianceData(string $cnpj): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                SELECT compliance_status, negative_certificates, last_balance_sheet, risk_score, risk_level
                FROM companies 
                WHERE cnpj = ?
            ");
            $stmt->execute([$cnpj]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $complianceService = new ComplianceAnalysisService();
            $complianceResult = $complianceService->analyzeCompliance($cnpj, $data ?: []);
            
            return [
                'status' => $complianceResult['status'] ?? json_decode((string) ($data['compliance_status'] ?? '{}'), true) ?? [],
                'certificates' => $complianceResult['certificates'] ?? json_decode((string) ($data['negative_certificates'] ?? '[]'), true) ?? [],
                'negative_records' => $complianceResult['negative_records'] ?? [],
                'last_balance_sheet' => $data['last_balance_sheet'] ?? null,
                'risk_score' => $complianceResult['risk_score'] ?? (int) ($data['risk_score'] ?? 0),
                'risk_level' => $complianceResult['risk_level'] ?? $data['risk_level'] ?? 'medio',
                'source' => $complianceResult['source'] ?? 'calculated',
            ];
        } catch (\Exception $e) {
            return [
                'status' => [],
                'certificates' => [],
                'negative_records' => [],
                'last_balance_sheet' => null,
                'risk_score' => 50,
                'risk_level' => 'medio',
                'source' => 'calculated',
            ];
        }
    }

    private function getSocialMediaData(array $company): array
    {
        $social = json_decode((string) ($company['social_media'] ?? '{}'), true) ?? [];
        $photos = json_decode((string) ($company['photos'] ?? '[]'), true) ?? [];
        
        return [
            'instagram' => $social['instagram'] ?? null,
            'facebook' => $social['facebook'] ?? null,
            'linkedin' => $social['linkedin'] ?? null,
            'twitter' => $social['twitter'] ?? null,
            'youtube' => $social['youtube'] ?? null,
            'tiktok' => $social['tiktok'] ?? null,
            'photos' => $photos,
            'google_place_id' => $company['google_place_id'] ?? null,
            'ratings' => json_decode((string) ($company['ratings'] ?? '{}'), true) ?? [],
        ];
    }

    private function getPredictiveData(array $company): array
    {
        $cnpj = $company['cnpj'] ?? '';
        
        if (!empty($cnpj)) {
            $creditService = new CreditAnalysisService();
            $creditData = $creditService->analyzeCompany($cnpj, $company);
            
            return [
                'credit_score' => $creditData['credit_score'] ?? $this->calculateCreditScore($company),
                'inactivity_probability' => $creditData['inactivity_probability'] ?? $this->calculateInactivityProbability($company),
                'growth_potential' => $company['growth_potential'] ?? $this->calculateGrowthPotential($company),
                'recommended_porte' => $company['recommended_porte'] ?? $this->calculateRecommendedPorte($company),
                'source' => $creditData['source'] ?? 'calculated',
                'payment_behavior' => $creditData['payment_behavior'] ?? 'indisponivel',
                'government_debts' => $creditData['government_debts'] ?? false,
                'sirobe' => $creditData['sirobe'] ?? false,
                'data_quality' => $creditData['data_quality'] ?? 'basico',
                'sector_inactivity_rate' => $creditData['sector_inactivity_rate'] ?? null,
            ];
        }
        
        return [
            'credit_score' => $this->calculateCreditScore($company),
            'inactivity_probability' => $this->calculateInactivityProbability($company),
            'growth_potential' => $company['growth_potential'] ?? $this->calculateGrowthPotential($company),
            'recommended_porte' => $company['recommended_porte'] ?? $this->calculateRecommendedPorte($company),
        ];
    }

    private function calculateCreditScore(array $company): int
    {
        $score = 50;
        
        if (($company['status'] ?? '') === 'ativa') $score += 20;
        if (($company['simples_opt_in'] ?? false)) $score += 5;
        if (($company['mei_opt_in'] ?? false)) $score -= 5;
        
        $capital = (float) ($company['capital_social'] ?? 0);
        if ($capital > 1000000) $score += 15;
        elseif ($capital > 100000) $score += 10;
        elseif ($capital > 10000) $score += 5;
        
        $openedAt = $company['opened_at'] ?? null;
        if ($openedAt) {
            $years = (time() - strtotime($openedAt)) / (365 * 24 * 60 * 60);
            if ($years > 10) $score += 10;
            elseif ($years > 5) $score += 5;
            elseif ($years < 1) $score -= 10;
        }
        
        return min(100, max(0, $score));
    }

    private function calculateInactivityProbability(array $company): float
    {
        $probability = 20.0;
        
        if (($company['status'] ?? '') !== 'ativa') {
            return 80.0;
        }
        
        $openedAt = $company['opened_at'] ?? null;
        if ($openedAt) {
            $years = (time() - strtotime($openedAt)) / (365 * 24 * 60 * 60);
            if ($years < 1) $probability += 30;
            elseif ($years < 2) $probability += 15;
        }
        
        $companySize = $company['company_size'] ?? 'ME';
        if ($companySize === 'MEI') $probability += 10;
        
        return min(95.0, max(1.0, $probability));
    }

    private function calculateGrowthPotential(array $company): string
    {
        $cnae = $company['cnae_main_code'] ?? '';
        $prefix = substr(str_replace(['.', '-'], '', $cnae), 0, 2);
        
        $techCnaes = ['62', '63', '64', '65', '66', '70', '71', '72'];
        $serviceCnaes = ['69', '70', '71', '73', '74', '75', '77', '78', '79', '80', '81', '82'];
        
        if (in_array($prefix, $techCnaes)) {
            return 'alto';
        }
        if (in_array($prefix, $serviceCnaes)) {
            return 'medio';
        }
        
        return 'baixo';
    }

    private function calculateRecommendedPorte(array $company): string
    {
        $capital = (float) ($company['capital_social'] ?? 0);
        $cnae = $company['cnae_main_code'] ?? '';
        
        if ($capital > 10000000 || str_contains($cnae, '64')) {
            return 'Grande Porte';
        }
        if ($capital > 360000) {
            return 'Empresa de Pequeno Porte (EPP)';
        }
        if ($capital > 0) {
            return 'Microempresa (ME)';
        }
        
        return 'Microempreendedor (MEI)';
    }

    private function getContactData(array $company): array
    {
        $enriched = $company;
        
        if (empty($company['phone']) || empty($company['email']) || empty($company['website'])) {
            $openCnpj = new OpenCnpjService();
            $enriched = $openCnpj->enrichFromOpenCnpj($company);
        }
        
        return [
            'email' => $enriched['email'] ?? null,
            'email_verified' => (bool) ($enriched['email_verified'] ?? false),
            'phone' => $enriched['phone'] ?? null,
            'whatsapp_business_id' => $enriched['whatsapp_business_id'] ?? null,
            'website' => $enriched['website'] ?? null,
            'website_verified' => (bool) ($enriched['website_verified'] ?? false),
        ];
    }

    public function updateFinancialData(string $cnpj, array $data): bool
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                UPDATE companies SET 
                    revenue_estimate = ?,
                    tax_history = ?,
                    installment_plans = ?,
                    tax_debts = ?,
                    updated_at = NOW()
                WHERE cnpj = ?
            ");
            
            return $stmt->execute([
                $data['revenue_estimate'] ?? null,
                json_encode($data['tax_history'] ?? []),
                json_encode($data['installment_plans'] ?? []),
                json_encode($data['tax_debts'] ?? []),
                $cnpj,
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updatePartnerData(string $cnpj, array $partners): bool
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                UPDATE companies SET 
                    partners_data = ?,
                    total_partners = ?,
                    updated_at = NOW()
                WHERE cnpj = ?
            ");
            
            return $stmt->execute([
                json_encode($partners),
                count($partners),
                $cnpj,
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updateLocationData(string $cnpj, array $data): bool
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                UPDATE companies SET 
                    latitude = ?,
                    longitude = ?,
                    map_url = ?,
                    region_type = ?,
                    updated_at = NOW()
                WHERE cnpj = ?
            ");
            
            return $stmt->execute([
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['map_url'] ?? null,
                $data['region_type'] ?? null,
                $cnpj,
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updateComplianceData(string $cnpj, array $data): bool
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                UPDATE companies SET 
                    compliance_status = ?,
                    negative_certificates = ?,
                    last_balance_sheet = ?,
                    risk_score = ?,
                    risk_level = ?,
                    updated_at = NOW()
                WHERE cnpj = ?
            ");
            
            return $stmt->execute([
                json_encode($data['status'] ?? []),
                json_encode($data['certificates'] ?? []),
                $data['last_balance_sheet'] ?? null,
                $data['risk_score'] ?? 50,
                $data['risk_level'] ?? 'medio',
                $cnpj,
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updatePredictiveData(string $cnpj, array $data): bool
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                UPDATE companies SET 
                    credit_score = ?,
                    inactivity_probability = ?,
                    growth_potential = ?,
                    recommended_porte = ?,
                    updated_at = NOW()
                WHERE cnpj = ?
            ");
            
            return $stmt->execute([
                $data['credit_score'] ?? 50,
                $data['inactivity_probability'] ?? 20.0,
                $data['growth_potential'] ?? 'medio',
                $data['recommended_porte'] ?? null,
                $cnpj,
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updateSocialData(string $cnpj, array $data): bool
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                UPDATE companies SET 
                    social_media = ?,
                    photos = ?,
                    google_place_id = ?,
                    ratings = ?,
                    updated_at = NOW()
                WHERE cnpj = ?
            ");
            
            return $stmt->execute([
                json_encode($data['social'] ?? []),
                json_encode($data['photos'] ?? []),
                $data['google_place_id'] ?? null,
                json_encode($data['ratings'] ?? []),
                $cnpj,
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }
}
