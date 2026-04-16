<?php declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;

$user = Auth::user();
$appName = (string) site_setting('site_name', (string) config('app.name', 'Plattadata'));
$baseUrl = rtrim((string) config('app.url', ''), '/');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$canonicalUrl = $baseUrl . $requestPath;
$isAdminPage = str_starts_with($requestPath, '/admin') || str_starts_with($requestPath, '/usuarios');
$metaTitle = isset($metaTitle) ? (string) $metaTitle : ((string) ($title ?? $appName) . ' | ' . $appName);
$metaDescription = isset($metaDescription) ? (string) $metaDescription : (string) site_setting('site_description', 'Consulta de CNPJ, dados empresariais, QSA, CNAE e historico atualizado.');
$metaRobots = isset($metaRobots) ? (string) $metaRobots : (string) site_setting('seo_default_robots', 'index,follow');
$footerText = (string) site_setting('footer_text', '');
$contactEmail = (string) site_setting('contact_email', '');
$cspNonce = (string) ($_SERVER['CSP_NONCE'] ?? '');

$schemaData = $schemaData ?? [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => $appName,
    'url' => $baseUrl,
    'description' => $metaDescription,
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => [
            '@type' => 'EntryPoint',
            'urlTemplate' => $baseUrl . '/empresas?cnpj={search_term_string}',
        ],
        'query-input' => 'required name=search_term_string',
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => $appName,
        'url' => $baseUrl,
    ],
];

if (isset($structuredData) && is_array($structuredData)) {
    $schemaData = array_merge($schemaData, $structuredData);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script nonce="<?= $cspNonce ?>">(function(){var t=localStorage.getItem('theme');var isDark=t==='dark'||(!t&&window.matchMedia('(prefers-color-scheme:dark)').matches);if(isDark){document.documentElement.setAttribute('data-theme','dark');}function updateIcons(){var d=document.documentElement.getAttribute('data-theme')==='dark';var icon=d?'bi-sun-fill':'bi-moon-fill';var label=d?'Ativar modo claro':'Ativar modo escuro';document.querySelectorAll('.theme-toggle-btn').forEach(function(b){if(b.id==='theme-toggle-menu'){b.innerHTML='<i class="bi '+icon+' me-1"></i>Tema';}else{b.innerHTML='<i class="bi '+icon+'"></i>';}b.setAttribute('aria-label',label);});}window.toggleTheme=function(){var d=document.documentElement;var isDark=d.getAttribute('data-theme')==='dark';if(isDark){d.removeAttribute('data-theme');localStorage.setItem('theme','light');}else{d.setAttribute('data-theme','dark');localStorage.setItem('theme','dark');}updateIcons();};if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',updateIcons);}else{updateIcons();}})();</script>
    <title><?= e($metaTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="robots" content="<?= e($metaRobots) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($metaTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:site_name" content="<?= e($appName) ?>">
    <?php if (isset($cnpj) && $cnpj !== ''): ?>
        <meta property="og:image" content="<?= e($baseUrl) ?>/empresas/<?= e($cnpj) ?>/og-image.png">
        <meta name="twitter:image" content="<?= e($baseUrl) ?>/empresas/<?= e($cnpj) ?>/og-image.png">
    <?php else: ?>
        <meta property="og:image" content="<?= e($baseUrl) ?>/img/og-default.png">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#0d9488">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/img/icon.svg">
    <link rel="apple-touch-icon" href="/img/icon.svg">
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://maps.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://maps.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Fontes com display=swap para evitar bloqueio de renderização -->
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
    <!-- Bootstrap com SRI para segurança e cache -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
    <link rel="stylesheet" href="<?= e(asset_v('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('css/dark-mode.css')) ?>">
    <script type="application/ld+json"><?= htmlspecialchars(json_encode($schemaData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_NOQUOTES, 'UTF-8') ?></script>
</head>
<body>
    <!-- Skip to Content (Acessibilidade) -->
    <a href="#main-content" class="skip-to-content">Pular para o conteudo principal</a>

    <!-- Page Loading Bar -->
    <div class="page-loader"></div>

    <!-- Sync Loader (IBGE/External APIs) -->
    <div id="sync-loader" style="display: none !important;" aria-hidden="true">
        <div class="sync-spinner"></div>
        <h4 class="fw-bold text-brand mb-1">Sincronizando dados</h4>
        <p class="text-muted small px-4 text-center">Isso pode levar alguns segundos dependendo da API consultada.</p>
        <div class="sync-progress">
            <div class="sync-progress-bar"></div>
        </div>
        <p class="x-small text-muted mt-3">Por favor, nao feche esta pagina.</p>
    </div>

    <!-- Navbar -->
    <nav class="navbar site-navbar navbar-expand-lg bg-white border-bottom mb-0 mb-lg-4" style="z-index: 1040;" role="navigation" aria-label="Navegacao principal">
        <div class="container-fluid px-md-5">
            <a class="navbar-brand text-brand" href="/">
                <i class="bi bi-building me-1"></i><?= e($appName) ?>
            </a>
            <div class="d-flex d-lg-none align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary theme-toggle-btn" aria-label="Alternar tema">
                    <i class="bi bi-moon-fill"></i><span class="ms-1">Tema</span>
                </button>
                <button class="navbar-toggler border-0 shadow-none p-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu" aria-label="Menu">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto mt-3 mt-lg-0 align-items-lg-center gap-1 gap-lg-0">
                    <!-- Toggle Tema -->
                    <li class="nav-item d-none d-lg-block">
                        <button type="button" class="btn btn-sm btn-outline-secondary nav-link theme-toggle-btn" aria-label="Alternar tema">
                            <i class="bi bi-moon-fill"></i><span class="ms-1 d-none d-xl-inline">Tema</span>
                        </button>
                    </li>
                    
                    <!-- HOME -->
                    <li class="nav-item">
                        <a class="nav-link<?= ($_SERVER['REQUEST_URI'] ?? '/') === '/' ? ' active' : '' ?>" href="/">
                            <i class="bi bi-house-door me-1"></i><span class="d-lg-none">Inicio</span>
                            <span class="d-none d-lg-inline">Home</span>
                        </a>
                    </li>

                    <!-- CONSULTA (Dropdown) -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/empresas') || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/atividades') || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/localidades') || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/brasil') ? ' active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-search me-1"></i>Consultas
                        </a>
                        <ul class="dropdown-menu shadow-lg border-0 dropdown-min-280" style="z-index: 9999;">
                            <li class="px-3 py-2">
                                <small class="text-muted text-uppercase fw-bold small">Empresas</small>
                            </li>
                            <li><a class="dropdown-item" href="/empresas">
                                <i class="bi bi-building me-2 text-muted"></i>Todas as Empresas
                            </a></li>
                            <li><a class="dropdown-item" href="/empresas/mapa">
                                <i class="bi bi-geo-alt me-2 text-muted"></i>Mapa de Empresas
                            </a></li>
                            <?php if ($user && in_array($user['role'], ['admin', 'editor'], true)): ?>
                            <li><a class="dropdown-item" href="/empresas/busca">
                                <i class="bi bi-upc-scan me-2 text-muted"></i>Consultar CNPJ
                            </a></li>
                            <li><a class="dropdown-item" href="/comparar">
                                <i class="bi bi-columns me-2 text-muted"></i>Comparar Empresas
                            </a></li>
                            <li><a class="dropdown-item" href="/dashboard">
                                <i class="bi bi-speedometer2 me-2 text-muted"></i>Minhas Empresas
                            </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li class="px-3 py-2">
                                <small class="text-muted text-uppercase fw-bold small">Localidade</small>
                            </li>
                            <li><a class="dropdown-item" href="/brasil">
                                <i class="bi bi-globe-americas me-2 text-muted"></i>Mapa do Brasil
                            </a></li>
                            <li><a class="dropdown-item" href="/localidades">
                                <i class="bi bi-geo-alt me-2 text-muted"></i>Estados e Cidades
                            </a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li class="px-3 py-2">
                                <small class="text-muted text-uppercase fw-bold small">Classificacao</small>
                            </li>
                            <li><a class="dropdown-item" href="/atividades">
                                <i class="bi bi-diagram-3 me-2 text-muted"></i>Atividades Economicas
                            </a></li>
                        </ul>
                    </li>

                    <!-- DADOS (Dropdown) -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/indicadores') || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/impostometro') || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/comparacoes') || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/ferramentas') ? ' active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bar-chart me-1"></i>Dados
                        </a>
                        <ul class="dropdown-menu shadow-lg border-0 dropdown-min-280" style="z-index: 9999;">
                            <li class="px-3 py-2">
                                <small class="text-muted text-uppercase fw-bold small">Economia</small>
                            </li>
                            <li><a class="dropdown-item" href="/indicadores-economicos">
                                <i class="bi bi-graph-up-arrow me-2 text-muted"></i>Indicadores Economicos
                            </a></li>
                            <li><a class="dropdown-item" href="/impostometro">
                                <i class="bi bi-cash-stack me-2 text-muted"></i>Impostometro
                            </a></li>
                            <li><a class="dropdown-item" href="/ranking">
                                <i class="bi bi-trophy me-2 text-muted"></i>Rankings
                            </a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li class="px-3 py-2">
                                <small class="text-muted text-uppercase fw-bold small">Analise</small>
                            </li>
                            <li><a class="dropdown-item" href="/comparacoes">
                                <i class="bi bi-arrow-left-right me-2 text-muted"></i>Comparacoes
                            </a></li>
                        </ul>
                    </li>

                    <?php if ($user): ?>
                        <!-- FAVORITOS -->
                        <li class="nav-item">
                            <a class="nav-link<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/favoritos') ? ' active' : '' ?>" href="/favoritos">
                                <i class="bi bi-star me-1"></i><span class="d-lg-none">Favoritos</span>
                                <span class="d-none d-lg-inline">Favoritos</span>
                            </a>
                        </li>

                        <?php if (in_array($user['role'], ['admin', 'moderator'], true)): ?>
                        <!-- GERENCIAMENTO (Dropdown Admin) -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin') ? ' active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-gear me-1"></i>Gerenciar
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 dropdown-min-280" style="z-index: 9999;">
                                <?php if ($user['role'] === 'admin'): ?>
                                <li class="px-3 py-2">
                                    <small class="text-muted text-uppercase fw-bold small">Acesso & Usuarios</small>
                                </li>
                                <li><a class="dropdown-item" href="/usuarios">
                                    <i class="bi bi-people me-2 text-muted"></i>Usuarios
                                </a></li>
                                <li><a class="dropdown-item" href="/admin/integracoes">
                                    <i class="bi bi-plug me-2 text-muted"></i>Integracoes API
                                </a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <?php endif; ?>
                                <li class="px-3 py-2">
                                    <small class="text-muted text-uppercase fw-bold small">Conteudo & Dados</small>
                                </li>
                                <li><a class="dropdown-item" href="/admin/remocoes">
                                    <i class="bi bi-shield-check me-2 text-muted"></i>Remocoes Pendentes
                                </a></li>
                                <li><a class="dropdown-item" href="/admin/auditoria">
                                    <i class="bi bi-journal-text me-2 text-muted"></i>Auditoria
                                </a></li>
                                <?php if ($user['role'] === 'admin'): ?>
                                <li><a class="dropdown-item" href="/admin/analytics">
                                    <i class="bi bi-graph-up me-2 text-muted"></i>Analytics
                                </a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li class="px-3 py-2">
                                    <small class="text-muted text-uppercase fw-bold small">Sistema</small>
                                </li>
                                <li><a class="dropdown-item" href="/admin/configuracoes">
                                    <i class="bi bi-sliders me-2 text-muted"></i>Configuracoes
                                </a></li>
                                <li><a class="dropdown-item" href="/admin/observabilidade">
                                    <i class="bi bi-activity me-2 text-muted"></i>Observabilidade
                                </a></li>
                                <li><a class="dropdown-item" href="/admin/jobs">
                                    <i class="bi bi-clock-history me-2 text-muted"></i>Jobs / Fila
                                </a></li>
                                <li><a class="dropdown-item" href="/admin/backup/baixar">
                                    <i class="bi bi-cloud-arrow-up me-2 text-muted"></i>Backup
                                </a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li class="px-3 py-2">
                                    <small class="text-muted text-uppercase fw-bold small">Ferramentas</small>
                                </li>
                                <li><a class="dropdown-item" href="/admin/api-tester">
                                    <i class="bi bi-braces me-2 text-muted"></i>Testador de API
                                </a></li>
                                <li><a class="dropdown-item" href="/admin/migrations/run" onclick="return confirm('Executar migrations?');">
                                    <i class="bi bi-database-up me-2 text-muted"></i>Migrations
                                </a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <!-- USER MENU -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle p-1 p-lg-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="badge badge-role"><?= e(strtoupper((string) ($user['role'] ?? 'U'))) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="z-index: 9999;">
                                <li class="px-3 py-2">
                                    <div class="fw-medium"><?= e($user['name'] ?? 'Usuario') ?></div>
                                    <small class="text-muted"><?= e($user['email'] ?? '') ?></small>
                                </li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><a class="dropdown-item" href="/dashboard">
                                    <i class="bi bi-person me-2 text-muted"></i>Meu Perfil
                                </a></li>
                                <li><button type="button" class="dropdown-item" onclick="toggleTheme()">
                                    <i class="bi bi-palette me-2 text-muted"></i>Alternar Tema
                                </button></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li>
                                    <form method="post" action="/logout" class="m-0 px-2 py-1">
                                        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-box-arrow-right me-2"></i>Sair
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- LOGIN -->
                        <li class="nav-item">
                            <a class="btn btn-sm btn-brand ms-lg-2" href="/login">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Entrar
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main id="main-content" class="container-fluid px-md-5 pb-5" tabindex="-1">

    <!-- Confirmation Modal (Global) -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalTitle">
                        <i class="bi bi-question-circle me-2"></i>Confirmar
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="confirmModalBody">
                    Tem certeza que deseja continuar?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmModalBtn">
                        <i class="bi bi-check-circle me-1"></i>Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>
        <?php if (\App\Core\Session::has('success')): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= e(\App\Core\Session::flash('success')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (\App\Core\Session::has('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e(\App\Core\Session::flash('error')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (\App\Core\Session::has('info')): ?>
            <div class="alert alert-info alert-dismissible fade show shadow-sm mb-4" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i><?= e(\App\Core\Session::flash('info')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($isAdminPage): ?>
            <div class="row g-4 pt-4">
                <div class="col-lg-3 col-xl-2">
                    <?php require base_path('src/Views/admin/partials/sidebar.php'); ?>
                </div>
                <div class="col-lg-9 col-xl-10">
                    <?php if (isset($viewPath) && is_file($viewPath)): ?>
                        <?php require $viewPath; ?>
                    <?php else: ?>
                        <div class="alert alert-danger">Erro ao carregar o conteúdo da página.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php if (isset($viewPath) && is_file($viewPath)): ?>
                <?php require $viewPath; ?>
            <?php else: ?>
                <div class="alert alert-danger">Erro ao carregar o conteúdo da página.</div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="site-footer border-top bg-white py-3 mt-4" role="contentinfo">
        <div class="container-fluid px-md-5 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex flex-column">
                <small class="text-muted">&copy; <?= e(date('Y')) ?> <?= e($appName) ?></small>
                <?php if ($footerText !== ''): ?>
                    <small class="text-muted"><?= e($footerText) ?></small>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <?php if ($contactEmail !== ''): ?>
                    <a class="small text-decoration-none" href="mailto:<?= e($contactEmail) ?>">
                        <i class="bi bi-envelope me-1"></i>Contato
                    </a>
                <?php endif; ?>
                <a class="small text-decoration-none" href="/politica-de-privacidade">
                    <i class="bi bi-shield-check me-1"></i>Politica de Privacidade
                </a>
                <a class="small text-decoration-none" href="/termos-de-servico">
                    <i class="bi bi-file-text me-1"></i>Termos de Serviço
                </a>
            </div>
        </div>
    </footer>

    <!-- Structured Data -->
    <?php if (!empty($structuredData) && is_array($structuredData)): ?>
        <script type="application/ld+json"<?= $cspNonce !== '' ? ' nonce="' . e($cspNonce) . '"' : '' ?>><?= htmlspecialchars(json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_NOQUOTES, 'UTF-8') ?></script>
    <?php endif; ?>

    <!-- Mobile Offcanvas Menu -->
    <div class="offcanvas offcanvas-start mobile-menu" style="z-index: 1050;" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="mobileMenuLabel">
                <i class="bi bi-list me-2"></i><?= e($appName) ?>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
        </div>
        <div class="offcanvas-body p-0">
            
            <!-- Menu Principal -->
            <div class="list-group list-group-flush">
                <a href="/" class="list-group-item list-group-item-action d-flex align-items-center py-3 <?= ($_SERVER['REQUEST_URI'] ?? '/') === '/' ? 'active' : '' ?>">
                    <i class="bi bi-house-door me-3 fs-5"></i>
                    <span class="fw-medium">Home</span>
                </a>
            </div>
            
            <!-- Secao: Consultas -->
            <div class="border-top mt-2">
                <div class="px-3 py-2 small text-muted fw-bold text-uppercase">
                    <i class="bi bi-search me-1"></i>Consultas
                </div>
                <div class="list-group list-group-flush">
                    <a href="/empresas" class="list-group-item list-group-item-action d-flex align-items-center py-2 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/empresas') ? 'active' : '' ?>">
                        <i class="bi bi-building me-3 text-muted"></i>
                        <span>Todas as Empresas</span>
                    </a>
                    <a href="/brasil" class="list-group-item list-group-item-action d-flex align-items-center py-2 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/brasil') ? 'active' : '' ?>">
                        <i class="bi bi-globe-americas me-3 text-muted"></i>
                        <span>Mapa do Brasil</span>
                    </a>
                    <a href="/localidades" class="list-group-item list-group-item-action d-flex align-items-center py-2 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/localidades') ? 'active' : '' ?>">
                        <i class="bi bi-geo-alt me-3 text-muted"></i>
                        <span>Estados e Cidades</span>
                    </a>
                    <a href="/atividades" class="list-group-item list-group-item-action d-flex align-items-center py-2 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/atividades') ? 'active' : '' ?>">
                        <i class="bi bi-diagram-3 me-3 text-muted"></i>
                        <span>Atividades Economicas</span>
                    </a>
                </div>
            </div>
            
            <!-- Secao: Dados -->
            <div class="border-top mt-2">
                <div class="px-3 py-2 small text-muted fw-bold text-uppercase">
                    <i class="bi bi-bar-chart me-1"></i>Dados
                </div>
                <div class="list-group list-group-flush">
                    <a href="/indicadores-economicos" class="list-group-item list-group-item-action d-flex align-items-center py-2 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/indicadores') ? 'active' : '' ?>">
                        <i class="bi bi-graph-up-arrow me-3 text-muted"></i>
                        <span>Indicadores Economicos</span>
                    </a>
                    <a href="/impostometro" class="list-group-item list-group-item-action d-flex align-items-center py-2 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/impostometro') ? 'active' : '' ?>">
                        <i class="bi bi-cash-stack me-3 text-muted"></i>
                        <span>Impostometro</span>
                    </a>
                    <a href="/comparacoes" class="list-group-item list-group-item-action d-flex align-items-center py-2 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/comparacoes') ? 'active' : '' ?>">
                        <i class="bi bi-arrow-left-right me-3 text-muted"></i>
                        <span>Comparacoes</span>
                    </a>
                </div>
            </div>
            
            <?php if ($user): ?>
                <!-- Secao: Minha Conta -->
                <div class="border-top mt-2">
                    <div class="px-3 py-2 small text-muted fw-bold text-uppercase">
                        <i class="bi bi-person-circle me-1"></i>Minha Conta
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="/favoritos" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                            <i class="bi bi-star me-3 text-muted"></i>
                            <span>Favoritos</span>
                        </a>
                        <?php if (in_array($user['role'], ['admin', 'editor'], true)): ?>
                            <a href="/empresas/busca" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                                <i class="bi bi-upc-scan me-3 text-muted"></i>
                                <span>Consultar CNPJ</span>
                            </a>
                        <?php endif; ?>
                        <a href="/dashboard" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                            <i class="bi bi-speedometer2 me-3 text-muted"></i>
                            <span>Painel</span>
                        </a>
                    </div>
                </div>
                
                <?php if (in_array($user['role'], ['admin', 'moderator'], true)): ?>
                    <!-- Secao: Gerenciar -->
                    <div class="border-top mt-2">
                        <div class="px-3 py-2 small text-muted fw-bold text-uppercase">
                            <i class="bi bi-gear me-1"></i>Gerenciar
                        </div>
                        <div class="list-group list-group-flush">
                            <a href="/admin" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                                <i class="bi bi-grid-1x2 me-3 text-muted"></i>
                                <span>Dashboard Admin</span>
                            </a>
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="/usuarios" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                                    <i class="bi bi-people me-3 text-muted"></i>
                                    <span>Usuarios</span>
                                </a>
                                <a href="/admin/integracoes" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                                    <i class="bi bi-plug me-3 text-muted"></i>
                                    <span>Integracoes API</span>
                                </a>
                            <?php endif; ?>
                            <a href="/admin/remocoes" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                                <i class="bi bi-shield-check me-3 text-muted"></i>
                                <span>Remocoes Pendentes</span>
                            </a>
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="/admin/analytics" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                                    <i class="bi bi-graph-up me-3 text-muted"></i>
                                    <span>Analytics</span>
                                </a>
                                <a href="/admin/configuracoes" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                                    <i class="bi bi-sliders me-3 text-muted"></i>
                                    <span>Configuracoes</span>
                                </a>
                                <a href="/admin/observabilidade" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                                    <i class="bi bi-activity me-3 text-muted"></i>
                                    <span>Observabilidade</span>
                                </a>
                                <a href="/admin/jobs" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                                    <i class="bi bi-clock-history me-3 text-muted"></i>
                                    <span>Jobs / Fila</span>
                                </a>
                                <a href="/admin/api-tester" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                                    <i class="bi bi-braces me-3 text-muted"></i>
                                    <span>Testador de API</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Logout -->
                <div class="border-top mt-2 p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <small class="text-muted"><?= e($user['name'] ?? 'Usuario') ?></small>
                        <span class="badge bg-secondary"><?= e(strtoupper((string) ($user['role'] ?? ''))) ?></span>
                    </div>
                    <form method="post" action="/logout" class="d-grid">
                        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Sair
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Login Section -->
                <div class="border-top mt-2 p-3">
                    <div class="d-grid gap-2">
                        <a href="/login" class="btn btn-brand">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
                        </a>
                        <a href="/cadastro" class="btn btn-outline-secondary">
                            <i class="bi bi-person-plus me-2"></i>Criar Conta
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="border-top mt-2 p-3 small text-muted">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex gap-3">
                        <a href="/politica-de-privacidade" class="text-muted text-decoration-none">
                            <i class="bi bi-shield-check me-1"></i>Privacidade
                        </a>
                        <a href="/termos-de-servico" class="text-muted text-decoration-none">
                            <i class="bi bi-file-text me-1"></i>Termos
                        </a>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary theme-toggle-btn">
                        <i class="bi bi-palette me-1"></i>Tema
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts com SRI para segurança e cache -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.15.0/dist/cdn.min.js" integrity="sha384-ZzplbkDNw4d2hjWc+D9EcDIgXEUybwPr2Slhix4BTtvQO3JbK60Av543BvQT9gTu" crossorigin="anonymous"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" integrity="sha384-vsrfeLOOY6KuIYKDlmVH5UiBmgIdB1oEf7p01YgWHuqmOHfZr374+odEv96n9tNC" crossorigin="anonymous"></script>
    <script nonce="<?= (string) ($_SERVER['CSP_NONCE'] ?? '') ?>" defer src="<?= e(asset_v('js/app.js')) ?>"></script>
    
    <!-- Copy to Clipboard Script -->
    <script nonce="<?= (string) ($_SERVER['CSP_NONCE'] ?? '') ?>">
    function copyToClipboard(displayId, text, btn) {
        navigator.clipboard.writeText(text).then(function() {
            // Show feedback on button
            var button = btn || document.querySelector('button[onclick*="' + displayId + '"]');
            if (button) {
                var original = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check me-1"></i>Copiado';
                button.classList.remove('btn-outline-secondary');
                button.classList.add('btn-success');
                setTimeout(function() { 
                    button.innerHTML = original;
                    button.classList.add('btn-outline-secondary');
                    button.classList.remove('btn-success');
                }, 2000);
            }
            showToast('CNPJ copiado!', 'success');
        }).catch(function() {
            showToast('Erro ao copiar', 'danger');
        });
    }
    
    function showToast(message, type) {
        var toast = document.createElement('div');
        toast.className = 'toast-notification toast-' + type;
        toast.innerHTML = '<i class="bi bi-' + (type === 'success' ? 'check-circle-fill text-success' : 'exclamation-triangle-fill text-danger') + '"></i> ' + message;
        toast.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#fff;padding:12px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:9999;animation:slideIn 0.3s ease';
        document.body.appendChild(toast);
        setTimeout(function() { toast.style.animation = 'slideOut 0.3s ease'; setTimeout(function() { toast.remove(); }, 300); }, 2000);
    }
    </script>
    <style>
    .cnpj-copy-wrapper { display: inline-flex; align-items: center; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
    </style>
</body>
</html>
