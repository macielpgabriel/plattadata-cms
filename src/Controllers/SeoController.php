<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Repositories\StateRepository;
use App\Repositories\MunicipalityRepository;
use App\Repositories\CompanyRepository;

/**
 * Controller responsavel pela geracao de Sitemaps e arquivos SEO.
 */
final class SeoController
{
    private StateRepository $states;
    private MunicipalityRepository $municipalities;
    private CompanyRepository $companies;

    public function __construct()
    {
        $this->states = new StateRepository();
        $this->municipalities = new MunicipalityRepository();
        $this->companies = new CompanyRepository();
    }

    /**
     * Gera uma imagem OG dinamicamente para compartilhamento social.
     */
    public function companyOgImage(array $params): void
    {
        $cnpj = preg_replace('/[^A-Za-z0-9]/', '', (string) ($params['cnpj'] ?? ''));
        $company = $this->companies->findByCnpj($cnpj);

        if (!$company) {
            header('Content-Type: image/png');
            readfile(base_path('public/img/og-default.png')); // Fallback
            exit;
        }

        $width = 1200;
        $height = 630;
        $img = imagecreatetruecolor($width, $height);

        // Cores
        $bg = imagecolorallocate($img, 244, 247, 248); // Fundo claro
        $brand = imagecolorallocate($img, 15, 118, 110); // Cor da marca
        $text = imagecolorallocate($img, 33, 37, 41);
        $muted = imagecolorallocate($img, 108, 117, 125);
        $white = imagecolorallocate($img, 255, 255, 255);

        imagefill($img, 0, 0, $bg);

        // Desenhar borda de destaque lateral
        imagefilledrectangle($img, 0, 0, 40, $height, $brand);

        // Texto: Razao Social (Tentativa de usar fonte padrão se TTF não estiver disponível)
        $name = mb_strtoupper((string)($company['legal_name'] ?? 'Empresa'));
        imagestring($img, 5, 80, 150, $name, $text);
        
        // CNPJ
        $cnpjFormatted = "CNPJ: " . $cnpj;
        imagestring($img, 4, 80, 200, $cnpjFormatted, $muted);

        // Status Badge
        $status = mb_strtoupper((string)($company['status'] ?? 'ATIVA'));
        $statusColor = ($status === 'ATIVA') ? imagecolorallocate($img, 25, 135, 84) : $muted;
        imagefilledrectangle($img, 80, 250, 220, 290, $statusColor);
        imagestring($img, 4, 100, 262, $status, $white);

        // Footer Brand
        imagestring($img, 3, 1000, 580, "PlattaData CMS", $brand);

        header('Content-Type: image/png');
        imagepng($img);
        imagedestroy($img);
        exit;
    }

    /**
     * Gera o robots.txt dinamicamente.
     */
    public function robots(): void
    {
        $baseUrl = (string) config('app.url', 'https://plattadata.com');
        header('Content-Type: text/plain; charset=utf-8');
        
        $robots = <<<ROBOTS
# Plattadata CMS - robots.txt
# https://plattadata.com

User-agent: *
Allow: /

# Bloquear áreas administrativas
Disallow: /admin/
Disallow: /dashboard/
Disallow: /favoritos/
Disallow: /usuarios/
Disallow: /login
Disallow: /cadastro
Disallow: /recuperar-senha
Disallow: /redefinir-senha
Disallow: /verificar-email
Disallow: /api/

# Permitir recursos necessários
Allow: /empresas/*.png
Allow: /img/
Allow: /css/
Allow: /js/

# Sitemap
Sitemap: {$baseUrl}/sitemap.xml

# Crawl delay para hospedagens compartilhadas
Crawl-delay: 1

# Regras específicas para bots conhecidos
User-agent: Googlebot
Allow: /

User-agent: Bingbot
Allow: /

User-agent: DuckDuckBot
Allow: /
ROBOTS;
        
        echo $robots;
    }

    /**
     * Gera o índice de sitemaps para organizar grandes volumes de URLs.
     */
    public function sitemapIndex(): void
    {
        $baseUrl = (string) config('app.url', 'https://plattadata.com');

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        echo '<sitemap><loc>' . $baseUrl . '/sitemap-main.xml</loc><lastmod>' . date('Y-m-d') . '</lastmod></sitemap>';
        echo '<sitemap><loc>' . $baseUrl . '/sitemap-cities.xml</loc><lastmod>' . date('Y-m-d') . '</lastmod></sitemap>';
        echo '<sitemap><loc>' . $baseUrl . '/sitemap-activities.xml</loc><lastmod>' . date('Y-m-d') . '</lastmod></sitemap>';
        
        echo '</sitemapindex>';
    }

    /**
     * Sitemap principal com as rotas estáticas e de estados.
     */
    public function sitemapMain(): void
    {
        $baseUrl = (string) config('app.url', 'https://plattadata.com');
        $states = $this->states->findAll();

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        $this->renderUrl($baseUrl, '1.0', 'daily');
        $this->renderUrl($baseUrl . '/empresas', '0.9', 'daily');
        $this->renderUrl($baseUrl . '/localidades', '0.8', 'weekly');
        $this->renderUrl($baseUrl . '/atividades', '0.7', 'weekly');
        $this->renderUrl($baseUrl . '/impostometro', '0.8', 'daily');
        $this->renderUrl($baseUrl . '/indicadores-economicos', '0.8', 'daily');
        $this->renderUrl($baseUrl . '/comparacoes', '0.8', 'daily');
        $this->renderUrl($baseUrl . '/comparacoes/cnae-mais-lucrativos', '0.7', 'weekly');
        $this->renderUrl($baseUrl . '/comparacoes/cnaes-com-mais-empresas', '0.7', 'weekly');
        $this->renderUrl($baseUrl . '/comparacoes/estados-com-mais-empresas', '0.7', 'weekly');
        $this->renderUrl($baseUrl . '/comparacoes/regioes-brasil', '0.7', 'weekly');
        $this->renderUrl($baseUrl . '/comparacoes/distribuicao-por-porte', '0.7', 'weekly');
        $this->renderUrl($baseUrl . '/comparacoes/empresas-ativas-x-inativas', '0.7', 'weekly');
        $this->renderUrl($baseUrl . '/comparacoes/tributacao-brasil-vs-mundo', '0.7', 'weekly');
        $this->renderUrl($baseUrl . '/comparacoes/melhores-cidades-abrir-empresa', '0.7', 'weekly');
        $this->renderUrl($baseUrl . '/comparacoes/simples-nacional-vs-lucro-presumido', '0.7', 'weekly');
        $this->renderUrl($baseUrl . '/comparacoes/custos-abrir-empresa-por-estado', '0.7', 'weekly');
        $this->renderUrl($baseUrl . '/politica-de-privacidade', '0.3', 'monthly');

        foreach ($states as $state) {
            $this->renderUrl($baseUrl . "/localidades/" . strtolower($state['uf']), '0.7', 'weekly');
        }

        echo '</urlset>';
    }

    /**
     * Sitemap para atividades econômicas (CNAE).
     * Essencial para competir com cnpj.biz em buscas por nicho.
     */
    public function sitemapActivities(): void
    {
        $baseUrl = (string) config('app.url', 'https://plattadata.com');
        
        // Busca os CNAEs que têm pelo menos uma empresa vinculada
        $db = \App\Core\Database::connection();
        $stmt = $db->query("
            SELECT DISTINCT c.cnae_main_code, a.slug 
            FROM companies c 
            JOIN cnae_activities a ON c.cnae_main_code = a.code 
            WHERE c.cnae_main_code IS NOT NULL 
            LIMIT 10000
        ");
        $activities = $stmt->fetchAll();

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        foreach ($activities as $act) {
            $this->renderUrl($baseUrl . "/atividades/{$act['cnae_main_code']}/{$act['slug']}", '0.5', 'monthly');
        }

        echo '</urlset>';
    }

    /**
     * Sitemap especifico para cidades que possuem empresas no cache.
     */
    public function sitemapCities(): void
    {
        $baseUrl = (string) config('app.url', 'https://plattadata.com');
        
        // Pegar apenas cidades que tenham ao menos 1 empresa no cache para economizar links
        $cities = $this->municipalities->findActiveInCache(5000); 

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($cities as $city) {
            $uf = strtolower($city['state_uf']);
            $slug = $city['slug'];
            $this->renderUrl($baseUrl . "/localidades/{$uf}/{$slug}", '0.6', 'weekly');
        }

        echo '</urlset>';
    }

    private function renderUrl(string $loc, string $priority, string $changefreq): void
    {
        echo '<url>';
        echo '<loc>' . htmlspecialchars($loc) . '</loc>';
        echo '<lastmod>' . date('Y-m-d') . '</lastmod>';
        echo '<changefreq>' . $changefreq . '</changefreq>';
        echo '<priority>' . $priority . '</priority>';
        echo '</url>';
    }
}
