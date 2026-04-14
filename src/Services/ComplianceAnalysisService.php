<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Logger;
use Throwable;

final class ComplianceAnalysisService
{
    public function analyzeCompliance(string $cnpj, array $companyData): array
    {
        $cacheKey = "compliance_analysis:" . preg_replace('/[^0-9]/', '', $cnpj);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $cnpjClean = preg_replace('/[^0-9]/', '', $cnpj);

        $complianceData = [
            'source' => 'calculated',
            'certificates' => [],
            'negative_records' => [],
            'risk_score' => 50,
            'risk_level' => 'baixo',
            'status' => 'regular',
            'last_updated' => date('Y-m-d H:i:s'),
        ];

        $this->checkPortalTransparencia($cnpjClean, $complianceData);
        
        if ($complianceData['source'] === 'calculated') {
            $this->checkReceitaFederal($cnpj, $companyData, $complianceData);
        }

        if ($complianceData['source'] === 'calculated') {
            $calculated = $this->calculateLocalRisk($companyData);
            $complianceData['risk_score'] = $calculated['score'];
            $complianceData['risk_level'] = $calculated['level'];
        }

        Cache::set($cacheKey, $complianceData, 86400);

        return $complianceData;
    }

    private function checkPortalTransparencia(string $cnpj, array &$complianceData): void
    {
        try {
            $complianceService = new ComplianceService();
            // Em página de empresa, evita bloquear o request com chamadas externas lentas.
            $result = $complianceService->checkSanctions($cnpj, false, true);
            
            if (!empty($result['sanctions']) || $result['status'] === 'found') {
                $complianceData['source'] = 'portal_transparencia';
                $complianceData['negative_records'] = $result['sanctions'] ?? [];
                $complianceData['risk_score'] = 75;
                $complianceData['risk_level'] = 'alto';
                $complianceData['status'] = 'irregular';
            }
        } catch (Throwable $e) {
            Logger::error('Compliance check Portal Transparência failed: ' . $e->getMessage());
        }
    }

    private function checkReceitaFederal(string $cnpj, array $company, array &$complianceData): void
    {
        try {
            $cnpjService = new CnpjService();
            $data = $cnpjService->findOrFetch($cnpj);
            
            if (!empty($data)) {
                $complianceData['source'] = 'receita_federal';
                
                $situacao = strtolower($data['situacao'] ?? $data['status'] ?? '');
                if (str_contains($situacao, 'ativa')) {
                    $complianceData['status'] = 'regular';
                    $complianceData['risk_score'] = max($complianceData['risk_score'], 30);
                } elseif (str_contains($situacao, 'suspensa') || str_contains($situacao, 'pendente')) {
                    $complianceData['status'] = 'pendente';
                    $complianceData['risk_score'] = max($complianceData['risk_score'], 60);
                    $complianceData['risk_level'] = 'medio';
                } elseif (str_contains($situacao, 'inativa') || str_contains($situacao, 'baixada')) {
                    $complianceData['status'] = 'irregular';
                    $complianceData['risk_score'] = max($complianceData['risk_score'], 80);
                    $complianceData['risk_level'] = 'alto';
                }
            }
        } catch (Throwable $e) {
            Logger::error('Compliance check Receita Federal failed: ' . $e->getMessage());
        }
    }

    private function calculateLocalRisk(array $company): array
    {
        $score = 30;
        
        $status = strtolower($company['status'] ?? '');
        if ($status === 'ativa') {
            $score -= 10;
        } elseif (str_contains($status, 'suspensa')) {
            $score += 30;
        } elseif (str_contains($status, 'inativa') || str_contains($status, 'baixada')) {
            $score += 40;
        }

        $openedAt = $company['opened_at'] ?? null;
        if ($openedAt) {
            $years = (time() - strtotime($openedAt)) / (365 * 24 * 60 * 60);
            if ($years < 1) $score += 15;
            elseif ($years > 10) $score -= 10;
        }

        $certificates = json_decode((string) ($company['negative_certificates'] ?? '[]'), true) ?? [];
        if (!empty($certificates)) {
            $score += count($certificates) * 10;
        }

        $score = min(100, max(0, $score));

        $level = $score >= 70 ? 'alto' : ($score >= 40 ? 'medio' : 'baixo');

        return ['score' => $score, 'level' => $level];
    }

    public function refreshCompliance(string $cnpj): array
    {
        $cacheKey = "compliance_analysis:" . preg_replace('/[^0-9]/', '', $cnpj);
        Cache::forget($cacheKey);
        
        return $this->analyzeCompliance($cnpj, []);
    }
}
