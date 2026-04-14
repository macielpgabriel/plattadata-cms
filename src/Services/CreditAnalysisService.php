<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Logger;
use Throwable;

final class CreditAnalysisService
{
    public function analyzeCompany(string $cnpj, array $companyData): array
    {
        $cacheKey = "credit_analysis:" . preg_replace('/[^0-9]/', '', $cnpj);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $creditData = [
            'source' => 'calculated',
            'credit_score' => 50,
            'inactivity_probability' => 20.0,
            'risk_level' => 'medio',
            'payment_behavior' => 'indisponivel',
            'debt_records' => [],
            'government_debts' => false,
            'sirobe' => false,
            'last_updated' => date('Y-m-d H:i:s'),
        ];

        $cnpjClean = preg_replace('/[^0-9]/', '', $cnpj);
        
        $complianceService = new ComplianceService();
        try {
            // Evita requests longos durante renderização da página; usa cache quando disponível.
            $complianceResult = $complianceService->checkSanctions($cnpjClean, false, true);
            
            if (!empty($complianceResult['sanctions']) || $complianceResult['status'] === 'found') {
                $creditData['government_debts'] = true;
                $creditData['debt_records'] = $complianceResult['sanctions'] ?? [];
                $creditData['credit_score'] = 30;
                $creditData['risk_level'] = 'alto';
                $creditData['payment_behavior'] = 'devedor';
                $creditData['source'] = 'portal_transparencia';
            }
        } catch (Throwable $e) {
            Logger::error('Credit analysis compliance check failed: ' . $e->getMessage());
        }

        $sgsData = $this->getSectorInactivityRate($companyData);
        if (!empty($sgsData)) {
            $creditData['sector_inactivity_rate'] = $sgsData['rate'];
            $creditData['sector_inactivity_source'] = $sgsData['source'];
        }

        if (!empty($creditData['government_debts'])) {
            $creditData['source'] = 'portal_transparencia';
        } elseif (!empty($sgsData)) {
            $creditData['source'] = 'bcb';
        }

        if ($creditData['source'] === 'calculated' || $creditData['source'] === 'bcb') {
            $calculated = $this->calculateFallbackScore($companyData);
            $creditData['credit_score'] = $calculated['score'];
            $creditData['inactivity_probability'] = $calculated['inactivity'];
        }

        $creditData['credit_score'] = min(100, max(0, $creditData['credit_score']));
        $creditData['inactivity_probability'] = min(95, max(1, $creditData['inactivity_probability']));

        $creditData['data_quality'] = $this->assessDataQuality($companyData);

        Cache::set($cacheKey, $creditData, 86400);

        return $creditData;
    }

    private function assessDataQuality(array $company): string
    {
        $score = 0;
        
        if (!empty($company['status'])) $score += 20;
        if (!empty($company['opened_at'])) $score += 20;
        if (!empty($company['capital_social']) && $company['capital_social'] > 0) $score += 20;
        if (!empty($company['cnae_main_code'])) $score += 20;
        if (!empty($company['email'])) $score += 10;
        if (!empty($company['phone'])) $score += 10;
        
        if ($score >= 80) return 'completo';
        if ($score >= 50) return 'parcial';
        return 'basico';
    }

    private function getSectorInactivityRate(array $company): ?array
    {
        return null;
    }

    private function calculateFallbackScore(array $company): array
    {
        $score = 50;
        $inactivity = 20.0;

        if (($company['status'] ?? '') === 'ativa') {
            $score += 20;
            $inactivity -= 5;
        }

        if (!empty($company['simples_opt_in'])) {
            $score += 5;
        }
        if (!empty($company['mei_opt_in'])) {
            $score -= 5;
            $inactivity += 10;
        }

        $capital = (float) ($company['capital_social'] ?? 0);
        if ($capital > 1000000) {
            $score += 15;
            $inactivity -= 10;
        } elseif ($capital > 100000) {
            $score += 10;
            $inactivity -= 5;
        } elseif ($capital > 10000) {
            $score += 5;
        }

        $openedAt = $company['opened_at'] ?? null;
        if ($openedAt) {
            $years = (time() - strtotime($openedAt)) / (365 * 24 * 60 * 60);
            if ($years > 10) {
                $score += 10;
                $inactivity -= 15;
            } elseif ($years > 5) {
                $score += 5;
                $inactivity -= 8;
            } elseif ($years < 1) {
                $score -= 10;
                $inactivity += 20;
            }
        }

        return [
            'score' => min(100, max(0, $score)),
            'inactivity' => min(95, max(1, $inactivity)),
        ];
    }

    public function refreshAnalysis(string $cnpj): array
    {
        $cacheKey = "credit_analysis:" . preg_replace('/[^0-9]/', '', $cnpj);
        Cache::forget($cacheKey);
        
        $db = Database::connection();
        $stmt = $db->prepare("SELECT * FROM companies WHERE cnpj = ?");
        $stmt->execute([$cnpj]);
        $company = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        return $this->analyzeCompany($cnpj, $company);
    }
}
