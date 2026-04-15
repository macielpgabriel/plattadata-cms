<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Core\Logger;
use App\Services\CnpjService;
use App\Services\RateLimiterService;
use App\Services\SetupService;
use App\Repositories\CompanyRepository;
use RuntimeException;

final class PublicController
{
    private CnpjService $cnpjService;
    private CompanyRepository $companies;

    public function __construct()
    {
        $this->cnpjService = new CnpjService();
        $this->companies = new CompanyRepository();
    }

    public function home(): void
    {
        if (!(new SetupService())->isDatabaseReady()) {
            redirect('/install');
        }

        $recentCompanies = $this->companies->recent(12);

        View::render('public/home', [
            'title' => site_setting('homepage_title', 'Consulta CNPJ'),
            'subtitle' => site_setting('homepage_subtitle', 'Busque CNPJ gratuitamente sem precisar de login.'),
            'searchPlaceholder' => site_setting('homepage_search_placeholder', '00.000.000/0001-00'),
            'publicNotice' => site_setting('homepage_public_notice', 'A busca publica consulta e salva cache local para acelerar futuras consultas.'),
            'publicSearchEnabled' => site_setting('public_search_enabled', '1') !== '0',
            'recentCompanies' => $recentCompanies,
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
            'metaTitle' => site_setting('site_name', config('app.name')) . ' - Consulta CNPJ',
            'metaDescription' => site_setting('site_description', 'Consulte CNPJ, QSA, CNAE e dados empresariais em tempo real.'),
        ]);
    }

    public function privacyPolicy(): void
    {
        if (!(new SetupService())->isDatabaseReady()) {
            redirect('/install');
        }

        View::render('public/privacy_policy', [
            'title' => 'Politica de Privacidade',
            'metaTitle' => 'Politica de Privacidade | ' . site_setting('site_name', config('app.name')),
            'metaDescription' => 'Conheca como tratamos dados pessoais, mascaramento LGPD, auditoria e retencao no CMS.',
        ]);
    }

    public function termsOfService(): void
    {
        if (!(new SetupService())->isDatabaseReady()) {
            redirect('/install');
        }

        View::render('public/terms_of_service', [
            'title' => 'Termos de Serviço',
            'metaTitle' => 'Termos de Serviço | ' . site_setting('site_name', config('app.name')),
            'metaDescription' => 'Condicoes de uso da plataforma Plattadata para consulta de dados empresariais.',
        ]);
    }

    public function publicSearch(): void
    {
        Logger::info('publicSearch called', ['post' => $_POST ?? []]);
        
        if (!(new SetupService())->isDatabaseReady()) {
            Logger::info('Database not ready, redirecting to /install');
            redirect('/install');
        }

        if (site_setting('public_search_enabled', '1') === '0') {
            Session::flash('error', 'A busca publica esta temporariamente desativada. Faça login para consultar.');
            redirect('/login');
        }

        if (!Csrf::validate($_POST['_token'] ?? null)) {
            Logger::info('CSRF validation failed');
            Session::flash('error', 'Sessao expirada. Tente novamente.');
            redirect('/');
        }

        $ip = $this->clientIp();
        $configuredLimit = (int) site_setting('public_search_rate_limit_per_minute', (string) config('app.rate_limit.cnpj_search_public_per_minute', 20));
        $max = max(1, min($configuredLimit, 300));
        $limit = (new RateLimiterService())->hit('cnpj_search_public', 'ip:' . $ip, $max, 60);
        if (!(bool) ($limit['success'] ?? true)) {
            Logger::info('Rate limit exceeded', ['limit' => $limit]);
            $retry = (int) ($limit['retry_after'] ?? 60);
            Session::flash('error', 'Muitas consultas em pouco tempo. Aguarde ' . $retry . ' segundos e tente novamente.');
            redirect('/');
        }

        $cnpj = $this->cnpjService->sanitize((string) ($_POST['cnpj'] ?? ''));
        Logger::info('CNPJ sanitized', ['cnpj' => $cnpj]);
        
        if (!$this->cnpjService->validate($cnpj)) {
            Logger::info('CNPJ validation failed', ['cnpj' => $cnpj]);
            Session::flash('error', 'CNPJ invalido.');
            redirect('/');
        }

        try {
            $company = $this->cnpjService->findOrFetch($cnpj);
            Logger::info('findOrFetch returned', ['company_id' => $company['id'] ?? null, 'source' => $company['source'] ?? 'unknown']);
        } catch (RuntimeException $exception) {
            Logger::error('findOrFetch exception', ['error' => $exception->getMessage()]);
            Session::flash('error', $exception->getMessage());
            redirect('/');
        }

        Session::flash('success', 'Consulta concluida com sucesso.');
        $redirectUrl = '/empresas/' . $cnpj;
        Logger::info('Redirecting to', ['url' => $redirectUrl]);
        redirect($redirectUrl);
    }

    private function clientIp(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $ip = trim(explode(',', $candidate)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }
}
