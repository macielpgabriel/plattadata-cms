<?php declare(strict_types=1); use App\Core\Csrf; ?>
<?php 
$health = $health ?? [];
$metrics = $metrics ?? [];
$counts = $metrics['counts'] ?? [];
$latestCompany = $metrics['latest_company'] ?? [];
$companiesByStatus = $metrics['companies_by_status'] ?? [];
$topCompanies = $metrics['top_companies'] ?? [];
$topQueries = $metrics['top_queries'] ?? [];
$apiLogs = $metrics['api_logs'] ?? [];
$latency = $metrics['latency'] ?? [];
$jobs = $jobs ?? [];
$settings = $settings ?? [];
$jobsStatus = $jobsStatus ?? '';
$jobsPage = $jobsPage ?? 1;
$jobsTotalPages = $jobsTotalPages ?? 1;
$jobsTotal = $jobsTotal ?? 0;

$formatDate = static function (?string $value, string $pattern = 'd/m/Y H:i'): string {
    if (empty($value)) return '-';
    $timestamp = strtotime($value);
    return $timestamp === false ? '-' : date($pattern, $timestamp);
};
?>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= e($flash) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="admin-dashboard">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-speedometer2 me-2"></i>Painel Admin</h4>
            <p class="text-muted small mb-0">Gerencie o sistema de forma simples e rápida</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
            </button>
        </div>
    </div>

    <!-- Ações Rápidas -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="/admin/observabilidade" class="card border-0 shadow-sm h-100 text-decoration-none">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <i class="bi bi-activity fs-3 text-primary"></i>
                    </div>
                    <div>
                        <div class="fw-bold">Observabilidade</div>
                        <small class="text-muted">Monitoramento</small>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="/admin/configuracoes" class="card border-0 shadow-sm h-100 text-decoration-none">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <i class="bi bi-gear fs-3 text-secondary"></i>
                    </div>
                    <div>
                        <div class="fw-bold">Configurações</div>
                        <small class="text-muted">Sistema</small>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="/admin/analytics" class="card border-0 shadow-sm h-100 text-decoration-none">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <i class="bi bi-graph-up fs-3 text-success"></i>
                    </div>
                    <div>
                        <div class="fw-bold">Analytics</div>
                        <small class="text-muted">Estatísticas</small>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="/admin/remocoes" class="card border-0 shadow-sm h-100 text-decoration-none">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <i class="bi bi-shield-check fs-3 text-warning"></i>
                    </div>
                    <div>
                        <div class="fw-bold">Remoções</div>
                        <small class="text-muted">Dados</small>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Métricas Principais -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="h4 mb-0 text-primary"><?= number_format($counts['companies'] ?? 0) ?></div>
                    <small class="text-muted">Empresas</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="h4 mb-0 text-success"><?= number_format($counts['active_companies'] ?? 0) ?></div>
                    <small class="text-muted">Ativas</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="h4 mb-0 text-danger"><?= number_format($counts['inactive_companies'] ?? 0) ?></div>
                    <small class="text-muted">Inativas</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="h4 mb-0 text-info"><?= number_format($counts['users'] ?? 0) ?></div>
                    <small class="text-muted">Usuários</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="h4 mb-0 text-warning"><?= number_format($counts['municipalities'] ?? 0) ?></div>
                    <small class="text-muted">Municípios</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="h4 mb-0 text-secondary"><?= number_format($counts['query_logs_24h'] ?? 0) ?></div>
                    <small class="text-muted">Buscas 24h</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Status do Sistema e Ações -->
    <div class="row g-4 mb-4">
        <!-- Status -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-heart-pulse me-2 text-success"></i>Status do Sistema</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Database</span>
                        <span class="badge bg-success">Online</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">API Externa</span>
                        <span class="badge bg-<?= !empty($counts['api_attempts_24h']) ? 'success' : 'warning' ?>"><?= !empty($counts['api_attempts_24h']) ? 'Online' : 'Aguardando' ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Última verificação</span>
                        <small><?= $formatDate($health['checked_at'] ?? null, 'd/m H:i') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Última Empresa -->
        <div class="col-md-4">
            <?php if (!empty($latestCompany)): ?>
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-building text-success me-2"></i>Última Empresa</h6>
                    <div class="fw-bold"><?= e($latestCompany['trade_name'] ?? 'N/A') ?></div>
                    <small class="text-muted"><?= e($latestCompany['cnpj'] ?? '-') ?></small>
                    <div class="mt-2">
                        <small class="text-muted">Abertura: <?= $formatDate($latestCompany['opened_at'] ?? null, 'd/m/Y') ?></small>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="bi bi-building fs-1 text-muted"></i>
                    <p class="text-muted small mb-0 mt-2">Nenhuma empresa cadastrada</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Status da Base -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart text-primary me-2"></i>Status da Base</h6>
                    <?php if (!empty($companiesByStatus)): ?>
                        <?php foreach (array_slice($companiesByStatus, 0, 5) as $s): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small"><?= e($s['status'] ?? '-') ?></span>
                            <span class="badge bg-light text-dark"><?= number_format($s['total'] ?? 0) ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted small">Sem dados</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Links Adicionais -->
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <a href="/admin/api-tester" class="btn btn-outline-secondary w-100">
                <i class="bi bi-braces me-2"></i>Testador de API
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="/admin/integracoes" class="btn btn-outline-secondary w-100">
                <i class="bi bi-plug me-2"></i>Integrações
            </a>
        </div>
        <div class="col-6 col-md-3">
            <form method="post" action="/admin/backup/baixar" class="d-inline w-100">
                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                <button type="submit" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-cloud-arrow-up me-2"></i>Backup
                </button>
            </form>
        </div>
        <div class="col-6 col-md-3">
            <form method="post" action="/admin/clear-cache" class="d-inline w-100">
                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                <button type="submit" class="btn btn-outline-secondary w-100" onclick="return confirm('Limpar cache?');">
                    <i class="bi bi-trash me-2"></i>Limpar Cache
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.admin-dashboard .card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.admin-dashboard .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.admin-dashboard a.card {
    color: inherit;
}
.admin-dashboard a.card:hover {
    color: inherit;
}
</style>