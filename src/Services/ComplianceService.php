<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * Service to handle legal and compliance checks.
 * Integrates with Portal da Transparência for CEIS/CNEP/CEPIM lists.
 * Priority: Cache -> Portal API -> Web Scraping
 */
final class ComplianceService
{
    private string $portalBaseUrl;
    private string $token;
    private int $timeout;
    private int $cacheTtlDays;

    public function __construct()
    {
        $this->portalBaseUrl = 'https://api.portaldatransparencia.gov.br/api-de-dados';
        $this->token = (string) config('app.compliance.token', '');
        $this->timeout = (int) config('app.compliance.timeout', 10);
        $this->cacheTtlDays = (int) config('app.compliance.cache_ttl_days', 7);
    }

    /**
     * Comprehensive check for sanctions on a CNPJ/CPF.
     *
     * @param string $identifier CNPJ or CPF
     * @param bool $forceRefresh Skip cache and force fresh API call
     * @param bool $cacheOnly Return quickly when cache is missing (no external calls)
     * @return array
     */
    public function checkSanctions(string $identifier, bool $forceRefresh = false, bool $cacheOnly = false): array
    {
        $identifier = preg_replace('/[^A-Za-z0-9]/', '', $identifier) ?? '';
        $cnpj = preg_replace('/[^0-9]/', '', $identifier);
        
        if ($cnpj === '') {
            return [
                'status' => 'not_checked',
                'message' => 'CNPJ/CPF invalido',
                'sanctions' => []
            ];
        }

        if (!$forceRefresh) {
            $cached = $this->getFromCache($cnpj);
            if ($cached !== null) {
                return $cached;
            }
            if ($cacheOnly) {
                return [
                    'status' => 'not_checked',
                    'message' => 'Sem cache de compliance no momento.',
                    'sanctions' => [],
                    'source' => 'cache_miss',
                    'last_check' => date('Y-m-d H:i:s'),
                ];
            }
        }

        $result = $this->fetchSanctions($cnpj);
        $this->saveToCache($cnpj, $result);
        
        return $result;
    }

    private function fetchSanctions(string $cnpj): array
    {
        // 1. Tentar Portal da Transparência (funciona com token)
        if ($this->token !== '') {
            $result = $this->checkPortalTransparencia($cnpj);
            if ($result['status'] !== 'error') {
                return $result;
            }
        }

        // 2. Tentar Web Scraping (último recurso)
        $result = $this->checkSanctionsWebScraping($cnpj);
        if ($result['status'] !== 'error') {
            return $result;
        }

        return [
            'status' => 'not_checked',
            'message' => 'Nao foi possivel verificar sancoes. Tente novamente mais tarde.',
            'sanctions' => [],
            'last_check' => date('Y-m-d H:i:s'),
        ];
    }

    private function getFromCache(string $cnpj): ?array
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                "SELECT result_json, source FROM compliance_cache 
                 WHERE cnpj = ? AND expires_at > NOW() 
                 LIMIT 1"
            );
            $stmt->execute([$cnpj]);
            $row = $stmt->fetch();
            
            if ($row) {
                $data = json_decode($row['result_json'], true);
                if (is_array($data)) {
                    $data['from_cache'] = true;
                    $data['cache_source'] = $row['source'];
                    return $data;
                }
            }
        } catch (Throwable $e) {
            Logger::warning('Compliance cache read failed: ' . $e->getMessage());
        }
        
        return null;
    }

    private function saveToCache(string $cnpj, array $result): void
    {
        try {
            $db = Database::connection();
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->cacheTtlDays} days"));
            
            $stmt = $db->prepare(
                "INSERT INTO compliance_cache (cnpj, result_json, source, expires_at) 
                 VALUES (?, ?, ?, ?) 
                 ON DUPLICATE KEY UPDATE 
                 result_json = VALUES(result_json),
                 source = VALUES(source),
                 cached_at = NOW(),
                 expires_at = VALUES(expires_at)"
            );
            $stmt->execute([
                $cnpj,
                json_encode($result),
                $result['source'] ?? 'unknown',
                $expiresAt
            ]);
        } catch (Throwable $e) {
            Logger::warning('Compliance cache write failed: ' . $e->getMessage());
        }
    }

    public function invalidateCache(string $cnpj): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("DELETE FROM compliance_cache WHERE cnpj = ?");
            $stmt->execute([preg_replace('/\D/', '', $cnpj)]);
            return true;
        } catch (Throwable $e) {
            Logger::warning('Compliance cache invalidation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check sanctions using Portal da Transparência API.
     */
    private function checkPortalTransparencia(string $cnpj): array
    {
        try {
            $headers = [
                "Accept: application/json",
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
            ];
            
            if (!empty($this->token)) {
                $headers[] = "chave-api-dados: {$this->token}";
            }

            $allSanctions = [];
            $sources = [
                'CEIS' => "/teis?cnpjSancionado={$cnpj}",
                'CNEP' => "/cnep?cnpjSancionado={$cnpj}",
                'CEPIM' => "/cepim?cnpjSancionado={$cnpj}",
            ];

            foreach ($sources as $source => $endpoint) {
                $url = "{$this->portalBaseUrl}{$endpoint}&pagina=1";
                
                $opts = [
                    'http' => [
                        'method' => 'GET',
                        'timeout' => $this->timeout,
                        'header' => $headers
                    ]
                ];

                $context = stream_context_create($opts);
                $content = @file_get_contents($url, false, $context);

                if ($content !== false) {
                    $data = json_decode($content, true);
                    if (is_array($data) && !empty($data)) {
                        foreach ($data as $item) {
                            $allSanctions[] = [
                                'nome' => $item['nomeSancionado'] ?? $item['nome'] ?? 'N/A',
                                'documento' => $cnpj,
                                'orgao' => $item['orgaoSancionador'] ?? 'N/A',
                                'tipo' => $item['categoriaSancao'] ?? $item['tipoSancao'] ?? $source,
                                'fonte' => 'Portal da Transparencia - ' . $source,
                            ];
                        }
                    }
                }
            }

            if (empty($allSanctions)) {
                return ['status' => 'error', 'source' => 'portal'];
            }

            return [
                'status' => !empty($allSanctions) ? 'warning' : 'clean',
                'total_sanctions' => count($allSanctions),
                'details' => [
                    'portal' => $allSanctions
                ],
                'source' => 'portal',
                'last_check' => date('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            Logger::error('Portal Transparência API Error: ' . $e->getMessage());
            return ['status' => 'error', 'source' => 'portal'];
        }
    }

    /**
     * Alternative method using web scraping when APIs are blocked.
     */
    public function checkSanctionsWebScraping(string $cnpj): array
    {
        try {
            $url = "https://portaldatransparencia.gov.br/sancoes/consulta?cadastro=1&cnpjSancionado={$cnpj}&paginacaoSimples=true&tamanhoPagina=10";
            
            $html = $this->fetchWebPage($url);
            
            if ($html === null) {
                return ['status' => 'error', 'source' => 'web_scraping'];
            }

            $sanctions = $this->parseSanctionsHtml($html);

            return [
                'status' => !empty($sanctions) ? 'warning' : 'clean',
                'total_sanctions' => count($sanctions),
                'details' => [
                    'web_scraping' => $sanctions
                ],
                'source' => 'web_scraping',
                'last_check' => date('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            Logger::error('Web Scraping Error: ' . $e->getMessage());
            return ['status' => 'error', 'source' => 'web_scraping'];
        }
    }

    private function fetchWebPage(string $url): ?string
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'header' => [
                    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                    "Accept-Language: pt-BR,pt;q=0.9,en;q=0.8",
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
                ]
            ]
        ];

        $context = stream_context_create($opts);
        $content = @file_get_contents($url, false, $context);
        
        return $content === false ? null : $content;
    }

    private function parseSanctionsHtml(string $html): array
    {
        $sanctions = [];
        
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $html, $rows)) {
            foreach ($rows[1] as $row) {
                if (stripos($row, 'cnpj') === false && stripos($row, 'cpf') === false) {
                    continue;
                }
                
                $sanction = [];
                
                if (preg_match('/<td[^>]*>(.*?)<\/td>/s', $row, $cells)) {
                    $content = strip_tags($cells[1]);
                    $content = preg_replace('/\s+/', ' ', $content);
                    $content = trim($content);
                    
                    if (preg_match('/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}|\d{3}\.\d{3}\.\d{3}-\d{2}/', $content, $doc)) {
                        $sanction['documento'] = $doc[0];
                    }
                }
                
                if (preg_match('/<a[^>]*>([^<]+)<\/a>/s', $row, $matches)) {
                    $sanction['nome'] = trim($matches[1]);
                }
                
                if (!empty($sanction)) {
                    $sanction['fonte'] = 'Portal da Transparencia (Scraping)';
                    $sanctions[] = $sanction;
                }
            }
        }
        
        return $sanctions;
    }
}
