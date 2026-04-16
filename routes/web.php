<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\RegisterController;
use App\Controllers\AdminController;
use App\Controllers\AdminSettingController;
use App\Controllers\CompanyController;
use App\Controllers\DashboardController;
use App\Controllers\InstallController;
use App\Controllers\ObservabilityController;
use App\Controllers\PublicController;
use App\Controllers\SeoController;
use App\Controllers\UserController;
use App\Controllers\CompanyRemovalController;
use App\Controllers\LocationController;
use App\Controllers\ActivityController;
use App\Controllers\PartnerController;
use App\Controllers\EconomicController;
use App\Controllers\ImpostometroController;
use App\Controllers\ComparisonController;
use App\Controllers\GoogleAuthController;
use App\Controllers\FavoriteController;
use App\Controllers\IntegrationsController;
use App\Controllers\AnalyticsController;
use App\Controllers\DebugController;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\StaffMiddleware;
use App\Services\SetupService;

$router->get('/', static function (): void {
    if (!(new SetupService())->isDatabaseReady()) {
        redirect('/install');
    }

    (new PublicController())->home();
});

$router->get('/install', [InstallController::class, 'show']);
$router->post('/install', [InstallController::class, 'save']);

// Debug Routes (Public - for testing)
$router->get('/debug/test-extract', [DebugController::class, 'testExtract']);
$router->get('/debug/view-variables', [DebugController::class, 'viewVariables']);
$router->get('/debug/company-data/{cnpj}', [DebugController::class, 'companyData']);
// Suporta CNPJ formatado com barra (XX.XXX.XXX/XXXX-XX)
$router->getWithPattern('/debug/company-data/(?P<cnpj_formatted>\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2})', [DebugController::class, 'companyData']);

$router->post('/buscar-cnpj', [PublicController::class, 'publicSearch']);
$router->get('/politica-de-privacidade', [PublicController::class, 'privacyPolicy']);
$router->get('/termos-de-servico', [PublicController::class, 'termsOfService']);
$router->get('/indicadores-economicos', [EconomicController::class, 'index']);
$router->get('/impostometro', [ImpostometroController::class, 'index']);
$router->get('/api/impostometro', [ImpostometroController::class, 'api']);
$router->get('/ranking', [ComparisonController::class, 'rankings']);

$router->get('/comparacoes', [ComparisonController::class, 'index']);
$router->get('/comparacoes/{slug}', [ComparisonController::class, 'show']);
$router->get('/comparar', [ComparisonController::class, 'compare']);
$router->get('/api/search', [ComparisonController::class, 'apiSearch']);
$router->get('/api/compare-detailed', [ComparisonController::class, 'apiCompareDetailed'], [AuthMiddleware::class]);
$router->post('/comparar/adicionar', [ComparisonController::class, 'apiAddToComparison'], [AuthMiddleware::class]);
$router->get('/ferramentas/{slug}', [ComparisonController::class, 'tool']);

// Rotas de Integrações (API para Zapier/Make/N8N)
$router->get('/api/v1', [IntegrationsController::class, 'apiInfo']);
$router->get('/api/v1/company/{cnpj}', [IntegrationsController::class, 'getCompany']);
$router->get('/api/v1/search', [IntegrationsController::class, 'search']);
$router->get('/api/v1/rankings/states', [IntegrationsController::class, 'rankingsStates']);
$router->get('/api/v1/rankings/cities', [IntegrationsController::class, 'rankingsCities']);
$router->get('/api/v1/rankings/cnae', [IntegrationsController::class, 'rankingsCnae']);
$router->post('/api/v1/webhook/favorite', [IntegrationsController::class, 'webhookFavorite']);

// Admin - Gerenciamento de Integrações
$router->get('/admin/integracoes', [IntegrationsController::class, 'integrationsPage'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/integracoes/apikey/criar', [IntegrationsController::class, 'generateApiKey'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/integracoes/apikey/excluir', [IntegrationsController::class, 'deleteApiKey'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/integracoes/webhook/criar', [IntegrationsController::class, 'generateWebhookSecret'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/integracoes/webhook/excluir', [IntegrationsController::class, 'deleteWebhookSecret'], [AuthMiddleware::class, AdminMiddleware::class]);

// Admin - Analytics
$router->get('/admin/analytics', [AnalyticsController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/analytics/api/company/{cnpj}', [AnalyticsController::class, 'companyTrends'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/analytics/api/search', [AnalyticsController::class, 'searchCompanies'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/analytics/api/compare-detailed', [AnalyticsController::class, 'compareDetailed'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/analytics/exportar', [AnalyticsController::class, 'exportCsv'], [AuthMiddleware::class, AdminMiddleware::class]);

$router->get('/robots.txt', [SeoController::class, 'robots']);
$router->get('/sitemap.xml', [SeoController::class, 'sitemapIndex']);
$router->get('/sitemap-main.xml', [SeoController::class, 'sitemapMain']);
$router->get('/sitemap-cities.xml', [SeoController::class, 'sitemapCities']);
$router->get('/sitemap-activities.xml', [SeoController::class, 'sitemapActivities']);
$router->get('/empresas/{cnpj}/og-image.png', [SeoController::class, 'companyOgImage']);
$router->get('/health', [ObservabilityController::class, 'health']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/cadastro', [RegisterController::class, 'show']);
$router->post('/cadastro', [RegisterController::class, 'store']);
$router->get('/recuperar-senha', [AuthController::class, 'showForgotForm']);
$router->post('/recuperar-senha', [AuthController::class, 'sendResetLink']);
$router->get('/redefinir-senha/{token}', [AuthController::class, 'showResetForm']);
$router->post('/redefinir-senha', [AuthController::class, 'resetPassword']);
$router->get('/login/2fa', [AuthController::class, 'showTwoFactor']);
$router->post('/login/2fa', [AuthController::class, 'verifyTwoFactor']);
$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);
$router->get('/verificar-email', [RegisterController::class, 'verify']);
$router->post('/verificar-email/reenviar', [RegisterController::class, 'resendVerification']);
$router->get('/unsubscribe', [AuthController::class, 'unsubscribe']);
$router->post('/unsubscribe', [AuthController::class, 'processUnsubscribe']);

// Rotas de Autenticação com Google Drive
$router->get('/auth/google', function() { redirect('/auth/google/login'); });
$router->get('/auth/google/login', [GoogleAuthController::class, 'login']);
$router->get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
$router->get('/auth/google/logout', [GoogleAuthController::class, 'logout']);

$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);
$router->post('/dashboard', [DashboardController::class, 'updateProfile'], [AuthMiddleware::class]);

$router->get('/favoritos', [FavoriteController::class, 'index'], [AuthMiddleware::class]);
$router->post('/favoritos/{cnpj}/toggle', [FavoriteController::class, 'toggle'], [AuthMiddleware::class]);
$router->post('/favoritos/{cnpj}/mover', [FavoriteController::class, 'moveToGroup'], [AuthMiddleware::class]);
$router->get('/favoritos/exportar', [FavoriteController::class, 'export'], [AuthMiddleware::class]);
$router->post('/favoritos/grupos/criar', [FavoriteController::class, 'createGroup'], [AuthMiddleware::class]);
$router->post('/favoritos/grupos/{id}/editar', [FavoriteController::class, 'updateGroup'], [AuthMiddleware::class]);
$router->post('/favoritos/grupos/{id}/excluir', [FavoriteController::class, 'deleteGroup'], [AuthMiddleware::class]);

// Rotas de Localidades
$router->get('/brasil', [LocationController::class, 'brasil']);
$router->get('/localidades', [LocationController::class, 'states']);
$router->get('/estado/{uf}', [LocationController::class, 'stateDeprecated']);
$router->get('/localidades/estado/{uf}', [LocationController::class, 'state']);
$router->post('/localidades/{uf}/atualizar', [LocationController::class, 'refreshState']);
$router->get('/localidades/{uf}', [LocationController::class, 'state']);
$router->get('/localidades/cidade/{ibge}', [LocationController::class, 'municipality']);
$router->get('/localidades/{uf}/{slug}', [LocationController::class, 'municipality']);
$router->post('/localidades/{uf}/{slug}/atualizar', [LocationController::class, 'refresh']);

// Rotas de Atividades
$router->get('/atividades', [ActivityController::class, 'index']);
$router->get('/atividades/{code}/{slug}', [ActivityController::class, 'show']);

$router->get('/usuarios', [UserController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/usuarios', [UserController::class, 'store'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/usuarios/{id}', [UserController::class, 'update'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/usuarios/{id}/excluir', [UserController::class, 'delete'], [AuthMiddleware::class, AdminMiddleware::class]);

// Rotas de Admin
$router->get('/admin', [AdminController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/cache/limpar', [AdminController::class, 'clearCache'], [AuthMiddleware::class, AdminMiddleware::class]);

$router->get('/admin/configuracoes', [AdminSettingController::class, 'edit'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/configuracoes', [AdminSettingController::class, 'update'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/configuracoes/autosave', [AdminSettingController::class, 'autosave'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/backup/baixar', [AdminSettingController::class, 'downloadBackup'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/observabilidade', [ObservabilityController::class, 'adminIndex'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/drive-upload', [AdminController::class, 'showDriveUpload'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/drive-upload', [AdminController::class, 'uploadDriveCredentials'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/api-tester', [ObservabilityController::class, 'apiTester'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/api-tester/test', [ObservabilityController::class, 'runTest'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/migrations/run', [ObservabilityController::class, 'runMigrations'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/localidades/sync', [ObservabilityController::class, 'syncMunicipalities'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/localidades/enrich', [ObservabilityController::class, 'syncMunicipalityEnrichment'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/atividades/sync', [ObservabilityController::class, 'syncCnaeActivities'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/cnae/sync', [ObservabilityController::class, 'syncCnaeActivities'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/logs/clear', [ObservabilityController::class, 'clearLogs'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/logs/test', [ObservabilityController::class, 'testClearLogs'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/phpstan/run', [ObservabilityController::class, 'runPhpstan'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/phpstan/github', [ObservabilityController::class, 'getPhpstanFromGithub'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/security/events', [ObservabilityController::class, 'getSecurityEvents'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/logs/recent', [ObservabilityController::class, 'getRecentLogs'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/jobs', [ObservabilityController::class, 'jobsIndex'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/jobs/retry', [ObservabilityController::class, 'retryJob'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/jobs/delete', [ObservabilityController::class, 'deleteJob'], [AuthMiddleware::class, AdminMiddleware::class]);

// Rotas de Remoção de Empresa
$router->get('/empresas/{cnpj}/remover', [CompanyRemovalController::class, 'showRequestForm']);
$router->post('/empresas/{cnpj}/remover', [CompanyRemovalController::class, 'submitRequest']);
$router->get('/empresas/remover/verificar', [CompanyRemovalController::class, 'showVerifyForm']);
$router->post('/empresas/remover/verificar', [CompanyRemovalController::class, 'verifyCode']);
$router->get('/empresas/remover/documento', [CompanyRemovalController::class, 'showDocumentForm']);
$router->post('/empresas/remover/documento', [CompanyRemovalController::class, 'uploadDocument']);

// Administração de Auditoria
$router->get('/admin/auditoria', [AuditController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/auditoria/exportar', [AuditController::class, 'export'], [AuthMiddleware::class, AdminMiddleware::class]);

// Administração de Remoções
$router->get('/admin/remocoes', [CompanyRemovalController::class, 'adminList'], [AuthMiddleware::class, StaffMiddleware::class]);
$router->get('/admin/remocoes/documento/{file}', [CompanyRemovalController::class, 'downloadDocument'], [AuthMiddleware::class, StaffMiddleware::class]);
$router->post('/admin/remocoes/{id}/aprovar', [CompanyRemovalController::class, 'approve'], [AuthMiddleware::class, StaffMiddleware::class]);
$router->post('/admin/remocoes/{id}/recusar', [CompanyRemovalController::class, 'reject'], [AuthMiddleware::class, StaffMiddleware::class]);
$router->post('/admin/remocoes/{id}/restaurar', [CompanyRemovalController::class, 'restore'], [AuthMiddleware::class, StaffMiddleware::class]);

$router->get('/empresas', [CompanyController::class, 'index']);
$router->get('/empresas/mapa', [CompanyController::class, 'map']);
$router->get('/empresas/api/mapa', [CompanyController::class, 'mapApi']);
$router->get('/empresas/exportar', [CompanyController::class, 'exportCsv'], [AuthMiddleware::class]);
$router->get('/empresas/em/{uf}/{slug}', [CompanyController::class, 'indexByLocation']);
$router->get('/empresas/busca', [CompanyController::class, 'searchForm'], [AuthMiddleware::class]);
$router->post('/empresas/busca', [CompanyController::class, 'search'], [AuthMiddleware::class]);
$router->post('/empresas/{cnpj}/atualizar', [CompanyController::class, 'refresh'], [AuthMiddleware::class]);
$router->post('/empresas/{cnpj}/excluir', [CompanyController::class, 'delete'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/empresas/{cnpj}/json', [CompanyController::class, 'rawJson'], [AuthMiddleware::class]);
$router->get('/empresas/{cnpj}/historico', [CompanyController::class, 'history'], [AuthMiddleware::class]);
$router->get('/empresas/{cnpj}/monitorar', [CompanyController::class, 'subscribeMonitor'], [AuthMiddleware::class]);
$router->post('/empresas/{cnpj}/monitorar', [CompanyController::class, 'subscribeMonitor'], [AuthMiddleware::class]);
$router->delete('/empresas/{cnpj}/monitorar', [CompanyController::class, 'unsubscribeMonitor'], [AuthMiddleware::class]);
$router->get('/empresas/{cnpj}', [CompanyController::class, 'show']);

// Debug Routes (Admin only)
$router->get('/admin/debug/company-data/{cnpj}', [DebugController::class, 'companyData'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/debug/company-data/{cnpj_formatted}', [DebugController::class, 'companyData'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/debug/view-variables', [DebugController::class, 'viewVariables'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/debug/test-extract', [DebugController::class, 'testExtract'], [AuthMiddleware::class, AdminMiddleware::class]);
// Suporta CNPJ formatado com barra (XX.XXX.XXX/XXXX-XX)
$router->getWithPattern('/admin/debug/company-data/(?P<cnpj_formatted>\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2})', [DebugController::class, 'companyData'], [AuthMiddleware::class, AdminMiddleware::class]);
