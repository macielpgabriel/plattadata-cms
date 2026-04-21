<?php declare(strict_types=1);
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isDashboard = ($requestPath === '/admin');

// Função auxiliar para links espertos
$sidebarLink = function($tabId, $icon, $label, $isTab = true) use ($isDashboard) {
    if ($isTab && $isDashboard) {
        return sprintf(
            '<a href="#%1$s" class="nav-link" data-bs-toggle="tab"><i class="bi bi-%2$s me-2"></i>%3$s</a>',
            $tabId, $icon, $label
        );
    } else {
        return sprintf(
            '<a href="/admin?tab=%1$s" class="nav-link"><i class="bi bi-%2$s me-2"></i>%3$s</a>',
            $tabId, $icon, $label
        );
    }
};
?>

<div class="admin-sidebar">
    <div class="d-none d-lg-block mb-4 ps-3">
        <h5 class="mb-1 text-brand fw-bold">PlattaData <span class="text-muted fw-normal">CMS</span></h5>
        <small class="text-muted d-block"><?= number_format($counts['companies'] ?? 0) ?> empresas cadastradas</small>
    </div>
    
    <nav class="nav flex-column">
        <div class="admin-section-title">Monitoramento</div>
        <?= $sidebarLink('dashboard', 'speedometer2', 'Visao Geral') ?>
        
        <a href="/admin/analytics" class="nav-link <?= str_contains($requestPath, 'analytics') ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow me-2"></i>Analytics
        </a>
        
        <?= $sidebarLink('observabilidade', 'activity', 'Observabilidade') ?>

        <div class="admin-section-title">Configuracoes</div>
        <?= $sidebarLink('identidade', 'palette', 'Identidade') ?>
        <?= $sidebarLink('operacao', 'toggle2-on', 'Operacao') ?>

        <div class="admin-section-title">Gestão</div>
        <a href="/usuarios" class="nav-link <?= str_contains($requestPath, 'usuarios') ? 'active' : '' ?>">
            <i class="bi bi-people me-2"></i>Gerenciar Usuarios
        </a>
        <a href="/admin/remocoes" class="nav-link <?= str_contains($requestPath, 'remocoes') ? 'active' : '' ?>">
            <i class="bi bi-trash me-2"></i>Pedidos de Remocao
        </a>

        <div class="admin-section-title">Sistema & Dev</div>
        <?= $sidebarLink('api-tester', 'braces', 'Testador de API') ?>
        <?= $sidebarLink('seguranca', 'shield-lock', 'Seguranca') ?>
        
        <div class="user-widget">
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="bg-brand text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.8rem;">
                    <?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="lh-1 overflow-hidden">
                    <div class="fw-bold small text-truncate" style="max-width: 100px;"><?= e($user['name'] ?? 'Admin') ?></div>
                    <small class="text-muted" style="font-size: 0.7rem;">Administrador</small>
                </div>
            </div>
            <div class="d-grid gap-2">
                <form action="/admin/cache/limpar" method="POST" onsubmit="return confirm('Limpar todo o cache do sistema?')">
                    <input type="hidden" name="_token" value="<?= \App\Core\Csrf::token() ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100 text-start py-1">
                        <i class="bi bi-lightning-charge me-1"></i> Limpar Cache
                    </button>
                </form>
                <form method="post" action="/admin/backup/baixar" class="d-inline">
                    <input type="hidden" name="_token" value="<?= \App\Core\Csrf::token() ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100 text-start py-1">
                        <i class="bi bi-cloud-arrow-down me-1"></i> Download Backup
                    </button>
                </form>
            </div>
        </div>
    </nav>
</div>
