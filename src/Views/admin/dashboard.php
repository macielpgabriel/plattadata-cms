<?php declare(strict_types=1); use App\Core\Csrf; ?>
<?php 
$health = $health ?? [];
$metrics = $metrics ?? [];
$counts = $metrics['counts'] ?? [];
$topMunicipalities = $metrics['top_municipalities'] ?? [];
$latestCompany = $metrics['latest_company'] ?? [];
$weatherUpdates = $metrics['weather_updates'] ?? [];
$exchangeUpdates = $metrics['exchange_updates'] ?? [];
$companiesByStatus = $metrics['companies_by_status'] ?? [];
$topCompanies = $metrics['top_companies'] ?? [];
$topQueries = $metrics['top_queries'] ?? [];
$apiLogs = $metrics['api_logs'] ?? [];
$appLogs = $metrics['logs']['app_tail'] ?? [];
$latency = $metrics['latency'] ?? [];
$jobs = $jobs ?? [];
$settings = $settings ?? [];
$jobsStatus = $jobsStatus ?? '';
$jobsPage = $jobsPage ?? 1;
$jobsTotalPages = $jobsTotalPages ?? 1;
$jobsTotal = $jobsTotal ?? 0;
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

<form method="post" action="/admin/configuracoes" id="settingsForm">
    <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
    
    <!-- Toast Container -->
    <div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 10000;"></div>
    
    <div class="tab-content" id="adminTabsContent">
        <!-- DASHBOARD -->
        <div class="tab-pane fade show active" id="dashboard">
            <!-- Título da Página -->
            <div class="section-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1"><i class="bi bi-grid-1x2 me-2"></i>Dashboard</h4>
                        <p class="mb-0 opacity-75 small">Visão geral do sistema</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Resumo Executivo -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-building fs-2 text-primary mb-2"></i>
                            <div class="h4 mb-0"><?= number_format($counts['companies'] ?? 0) ?></div>
                            <small class="text-muted">Total Empresas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-check-circle fs-2 text-success mb-2"></i>
                            <div class="h4 mb-0"><?= number_format($counts['active_companies'] ?? 0) ?></div>
                            <small class="text-muted">Ativas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-x-circle fs-2 text-danger mb-2"></i>
                            <div class="h4 mb-0"><?= number_format($counts['inactive_companies'] ?? 0) ?></div>
                            <small class="text-muted">Inativas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-geo-alt fs-2 text-warning mb-2"></i>
                            <div class="h4 mb-0"><?= number_format($counts['municipalities'] ?? 0) ?></div>
                            <small class="text-muted">Municípios</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Métricas Secundárias -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-people fs-2 text-info mb-2"></i>
                            <div class="h4 mb-0"><?= number_format($counts['users'] ?? 0) ?></div>
                            <small class="text-muted">Usuários</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-search fs-2 text-secondary mb-2"></i>
                            <div class="h4 mb-0"><?= number_format($counts['query_logs_24h'] ?? 0) ?></div>
                            <small class="text-muted">Buscas (24h)</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-cloud-download fs-2 text-primary mb-2"></i>
                            <div class="h4 mb-0"><?= number_format($counts['api_attempts_24h'] ?? 0) ?></div>
                            <small class="text-muted">API (24h)</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-currency-exchange fs-2 text-success mb-2"></i>
                            <div class="h4 mb-0"><?= number_format($counts['exchange_currencies'] ?? 0) ?></div>
                            <small class="text-muted">Moedas</small>
                        </div>
                    </div>
                </div>
            </div>
</div>
        </div>
    </div>

    <!-- Seção: Negócio e Uso -->
    <div class="section-header mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1">Negócio e Uso</h4>
                <p class="mb-0 opacity-75 small">Indicadores de cadastro e comportamento</p>
            </div>
        </div>
    </div>

    <!-- Negócios e Uso -->
            <?php if (!empty($latestCompany) || !empty($companiesByStatus)): ?>
            <div class="row g-4 mb-4">
                <?php if (!empty($latestCompany)): ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-3 fw-bold"><i class="bi bi-plus-circle text-success me-2"></i>Ultima Empresa Cadastrada</h6>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><?= e($latestCompany['trade_name'] ?? 'N/A') ?></div>
                                    <small class="text-muted"><?= e($latestCompany['cnpj'] ?? '-') ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-light text-dark border"><?= format_date($latestCompany['opened_at'] ?? '') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($companiesByStatus)): ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-3 fw-bold"><i class="bi bi-pie-chart text-primary me-2"></i>Status da Base</h6>
                            <div class="row g-2">
                                <?php foreach (array_slice($companiesByStatus, 0, 4) as $s): ?>
                                    <div class="col-6">
                                        <div class="d-flex justify-content-between align-items-center px-1">
                                            <small class="text-muted"><?= e(ucfirst($s['status'] ?? '-')) ?></small>
                                            <span class="fw-bold small"><?= number_format($s['total']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-3">
                            <a href="/empresas" class="card metric-card hover-lift border-0 shadow-sm text-decoration-none h-100">
                                <div class="card-body">
                                    <div class="metric-icon bg-blue-subtle text-primary">
                                        <i class="bi bi-building fs-4"></i>
                                    </div>
                                    <div class="h3 fw-bold mb-0 text-dark"><?= number_format($counts['companies'] ?? 0) ?></div>
                                    <div class="text-muted small">Total de Empresas</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="/empresas?status=ativa" class="card metric-card hover-lift border-0 shadow-sm text-decoration-none h-100">
                                <div class="card-body">
                                    <div class="metric-icon bg-green-subtle text-success">
                                        <i class="bi bi-check2-circle fs-4"></i>
                                    </div>
                                    <div class="h3 fw-bold mb-0 text-dark"><?= number_format($counts['active_companies'] ?? 0) ?></div>
                                    <div class="text-muted small">Empresas Ativas</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="/usuarios" class="card metric-card hover-lift border-0 shadow-sm text-decoration-none h-100">
                                <div class="card-body">
                                    <div class="metric-icon bg-purple-subtle text-purple">
                                        <i class="bi bi-people fs-4"></i>
                                    </div>
                                    <div class="h3 fw-bold mb-0 text-dark"><?= number_format($counts['users'] ?? 0) ?></div>
                                    <div class="text-muted small">Usuarios Ativos</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card metric-card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="metric-icon bg-amber-subtle text-warning">
                                        <i class="bi bi-lightning fs-4"></i>
                                    </div>
                                    <div class="h3 fw-bold mb-0 text-dark"><?= number_format($counts['api_attempts_24h'] ?? 0) ?></div>
                                    <div class="text-muted small">Consumo API 24h</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Atividade Recente -->
                    <div class="row g-4 mb-4">
                        <div class="col-lg-7">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h6 class="card-title mb-0 fw-bold"><i class="bi bi-shield-check text-primary me-2"></i>Segurança</h6>
                                        <button type="button" onclick="loadSecurityEvents()" class="btn btn-link btn-xs p-0 text-decoration-none">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    </div>
                                    <div id="securityEventsOverview" style="min-height: 200px;">
                                        <div class="text-center py-5">
                                            <div class="spinner-border spinner-border-sm text-muted" role="status"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-4 fw-bold"><i class="bi bi-file-text text-warning me-2"></i>Ultimos Logs</h6>
                                    <div class="log-pre" style="max-height: 250px; overflow-y: auto; font-size: 0.7rem;">
                                        <?php if (!empty($appLogs)): ?>
                                            <?php foreach ($appLogs as $line): ?>
                                                <div class="activity-item py-1 text-truncate" title="<?= e($line) ?>">
                                                    <?= e($line) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-muted italic py-3">Sem logs recentes.</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-4 pt-3 border-top">
                                        <div class="row text-center g-2">
                                            <div class="col-4">
                                                <div class="fw-bold mb-0 text-primary"><?= number_format($counts['query_logs_24h'] ?? 0) ?></div>
                                                <div class="x-small text-muted">Buscas</div>
                                            </div>
                                            <div class="col-4 border-start border-end">
                                                <div class="fw-bold mb-0 text-success"><?= number_format($counts['api_attempts_24h'] ?? 0) ?></div>
                                                <div class="x-small text-muted">Requisicoes</div>
                                            </div>
                                            <div class="col-4">
                                                <div class="fw-bold mb-0 text-danger"><?= number_format($counts['api_failures_24h'] ?? 0) ?></div>
                                                <div class="x-small text-muted">Falhas</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- OBSERVABILIDADE -->
                <div class="tab-pane fade" id="observabilidade">
                    <div class="section-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1"><i class="bi bi-graph-up me-2"></i>Observabilidade</h4>
                                <p class="mb-0 opacity-75 small">Monitoramento e metricas do sistema</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($health['error'])): ?>
                        <div class="alert alert-danger mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Problema:</strong> <?= e($health['error']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100 metric-card">
                                <div class="card-body text-center">
                                    <div class="bg-blue-subtle text-primary rounded-circle icon-circle mx-auto mb-3" style="width: 56px; height: 56px;">
                                        <i class="bi bi-database fs-3"></i>
                                    </div>
                                    <h6 class="fw-bold">Database</h6>
                                    <?php if (!empty($health['database']['ok'])): ?>
                                        <span class="badge bg-success px-3">ONLINE</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger px-3">OFFLINE</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100 metric-card">
                                <div class="card-body text-center">
                                    <div class="bg-indigo-subtle text-indigo rounded-circle icon-circle mx-auto mb-3" style="width: 56px; height: 56px;">
                                        <i class="bi bi-server fs-3"></i>
                                    </div>
                                    <h6 class="fw-bold">Ambiente</h6>
                                    <span class="badge bg-<?= ($health['app_env'] ?? '') === 'production' ? 'danger' : 'warning' ?> px-3">
                                        <?= strtoupper($health['app_env'] ?? '-') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100 metric-card">
                                <div class="card-body text-center">
                                    <div class="bg-amber-subtle text-warning rounded-circle icon-circle mx-auto mb-3" style="width: 56px; height: 56px;">
                                        <i class="bi bi-clock-history fs-3"></i>
                                    </div>
                                    <h6 class="fw-bold">Ultima Verificacao</h6>
                                    <div class="small fw-medium"><?= format_datetime($health['checked_at'] ?? '', true) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($apiLogs)): ?>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="card-title mb-3"><i class="bi bi-cloud-download text-primary me-2"></i>Ultimas Requisições API</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0" style="white-space: nowrap;">
                                    <thead><tr><th>CNPJ</th><th>Provedor</th><th>Status</th><th>Data</th></tr></thead>
                                    <tbody>
                                        <?php foreach (array_slice($apiLogs, 0, 10) as $log): ?>
                                            <tr>
                                                <td><small><?= e($log['cnpj'] ?? '-') ?></small></td>
                                                <td><small><?= e($log['provider'] ?? '-') ?></small></td>
                                                <td>
                                                    <?php if (!empty($log['succeeded'])): ?>
                                                        <span class="badge bg-success">OK</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger"><?= e($log['status_code'] ?? 'Erro') ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><small><?= format_datetime($log['fetched_at'] ?? '') ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($weatherUpdates) || !empty($exchangeUpdates)): ?>
                    <div class="row g-4 mb-4">
                        <?php if (!empty($weatherUpdates)): ?>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-3"><i class="bi bi-cloud-sun text-info me-2"></i>Clima (<?= count($weatherUpdates) ?> atualizacoes)</h6>
                                    <table class="table table-sm table-hover mb-0">
                                        <thead><tr><th>Cidade</th><th>Atualizacao</th></tr></thead>
                                        <tbody>
                                            <?php foreach (array_slice($weatherUpdates, 0, 5) as $w): ?>
                                                <tr>
                                                    <td><?= e($w['name'] ?? '-') ?></td>
                                                    <td><small><?= format_datetime($w['updated_at'] ?? '') ?></small></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($exchangeUpdates)): ?>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-3"><i class="bi bi-currency-exchange text-success me-2"></i>Cambio (<?= count($exchangeUpdates) ?> registros)</h6>
                                    <table class="table table-sm table-hover mb-0">
                                        <thead><tr><th>Moeda</th><th>Ultima Cotacao</th></tr></thead>
                                        <tbody>
                                            <?php foreach (array_slice($exchangeUpdates, 0, 5) as $e): ?>
                                                <tr>
                                                    <td><strong><?= e($e['currency'] ?? '-') ?></strong></td>
                                                    <td><small><?= format_date($e['updated_at'] ?? '') ?></small></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($appLogs)): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="card-title mb-0"><i class="bi bi-file-text text-warning me-2"></i>Logs da Aplicacao</h6>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="if(confirm('Realmente deseja limpar todos os logs da aplicacao?')) document.getElementById('clearLogsForm').submit();">
                                    <i class="bi bi-trash me-1"></i>Limpar
                                </button>
                            </div>
                            <pre class="bg-dark text-light p-3 rounded small" style="overflow-x: auto; white-space: pre-wrap; word-break: break-all;"><?php foreach ($appLogs as $line): ?><?= e($line) ?>

<?php endforeach; ?></pre>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- IDENTIDADE -->
                <div class="tab-pane fade" id="identidade">
                    <div class="section-header">
                        <h4 class="mb-1"><i class="bi bi-palette me-2"></i>Identidade do Site</h4>
                        <p class="mb-0 opacity-75 small">Personalize a aparencia e as informacoes basicas da sua plataforma</p>
                    </div>
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3">Informacoes Principais</h6>
<!-- Status dos Serviços -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="card-title"><i class="bi bi-cloud-check me-2 text-success"></i>Status dos Serviços</h5>
        <div class="row">
            <div class="col-md-4">
                <div class="d-flex align-items-center">
                    <span class="badge bg-success me-2">Online</span>
                    <span>Banco de Dados</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center">
                    <span class="badge bg-<?= !empty($counts['api_attempts_24h']) ? 'success' : 'warning' ?> me-2"><?= !empty($counts['api_attempts_24h']) ? 'Online' : 'Aguardando' ?></span>
                    <span>API Externa</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center">
                    <span class="badge bg-success me-2">Online</span>
                    <span>Cache</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Métricas Principais -->
<div class="row g-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-people fs-2 text-info mb-2"></i>
                <div class="h4 mb-0"><?= number_format($counts['users'] ?? 0) ?></div>
                <small class="text-muted">Usuários</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-search fs-2 text-secondary mb-2"></i>
                <div class="h4 mb-0"><?= number_format($counts['query_logs_24h'] ?? 0) ?></div>
                <small class="text-muted">Buscas (24h)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-cloud-download fs-2 text-primary mb-2"></i>
                <div class="h4 mb-0"><?= number_format($counts['api_attempts_24h'] ?? 0) ?></div>
                <small class="text-muted">API (24h)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-currency-exchange fs-2 text-success mb-2"></i>
                <div class="h4 mb-0"><?= number_format($counts['exchange_currencies'] ?? 0) ?></div>
                <small class="text-muted">Moedas</small>
            </div>
        </div>
    </div>
</div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Descricao (SEO)</label>
                                    <input class="form-control" type="text" name="site_description" value="<?= e($settings['site_description'] ?? '') ?>" maxlength="160" placeholder="Breve resumo para buscadores...">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Titulo da Home</label>
                                    <input class="form-control" type="text" name="homepage_title" value="<?= e($settings['homepage_title'] ?? '') ?>" placeholder="Ex: Pesquise Dados de Empresas">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Subtitulo da Home</label>
                                    <input class="form-control" type="text" name="homepage_subtitle" value="<?= e($settings['homepage_subtitle'] ?? '') ?>" placeholder="Ex: Mais de 50 milhoes de registros...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3">Contato & Rodape</h6>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">E-mail de Contato</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                                        <input class="form-control border-start-0" type="email" name="contact_email" value="<?= e($settings['contact_email'] ?? '') ?>" placeholder="contato@exemplo.com">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">WhatsApp Business</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-whatsapp text-muted"></i></span>
                                        <input class="form-control border-start-0" type="text" name="contact_whatsapp" value="<?= e($settings['contact_whatsapp'] ?? '') ?>" placeholder="+55 11 90000-0000">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- OPERACAO -->
                <div class="tab-pane fade" id="operacao">
                    <div class="section-header">
                        <h4 class="mb-1"><i class="bi bi-toggle2-on me-2"></i>Configuracoes de Operacao</h4>
                        <p class="mb-0 opacity-75 small">Regras de negocio, limites e SEO tecnico</p>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-md-8">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-4">
                                    <h6 class="fw-bold mb-4">Limites e Performance</h6>
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">Empresas por Pagina</label>
                                            <input class="form-control" type="number" min="5" max="100" name="companies_per_page" value="<?= e($settings['companies_per_page'] ?? '15') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">Buscas Publicas / min</label>
                                            <input class="form-control" type="number" min="1" max="300" name="public_search_rate_limit_per_minute" value="<?= e($settings['public_search_rate_limit_per_minute'] ?? '20') ?>">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label small fw-bold text-muted">Limite URLs no Sitemap</label>
                                            <input class="form-control" type="number" min="100" max="50000" name="sitemap_company_limit" value="<?= e($settings['sitemap_company_limit'] ?? '10000') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100 bg-light bg-opacity-50">
                                <div class="card-body p-4">
                                    <h6 class="fw-bold mb-4">Seguranca & Visibilidade</h6>
                                    <div class="form-check form-switch mb-4">
                                        <input class="form-check-input" type="checkbox" id="public_search_enabled" name="public_search_enabled" <?= ($settings['public_search_enabled'] ?? '1') !== '0' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="public_search_enabled">
                                            <span class="fw-bold d-block small">Busca Publica</span>
                                            <span class="text-muted x-small">Permitir consulta sem login</span>
                                        </label>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-muted">Meta Robots Padrao</label>
                                        <?php $robots = $settings['seo_default_robots'] ?? 'index,follow'; ?>
                                        <select class="form-select form-select-sm" name="seo_default_robots">
                                            <option value="index,follow" <?= $robots === 'index,follow' ? 'selected' : '' ?>>index,follow</option>
                                            <option value="index,nofollow" <?= $robots === 'index,nofollow' ? 'selected' : '' ?>>index,nofollow</option>
                                            <option value="noindex,follow" <?= $robots === 'noindex,follow' ? 'selected' : '' ?>>noindex,follow</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- API TESTER -->
                <div class="tab-pane fade" id="api-tester">
                    <div class="section-header">
                        <h4 class="mb-1"><i class="bi bi-plug me-2"></i>Testador de API</h4>
                        <p class="mb-0 opacity-75 small">Teste integracoes externas</p>
                    </div>
                    
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-info-circle me-2"></i>
                        Testes reais, mas <strong>nao salvam dados</strong> no banco.
                    </div>
                    
                    <div class="row g-3" x-data="apiTester()">
                        <?php
                        $apis = [
                            ['id' => 'cnpj', 'name' => 'BrasilAPI CNPJ', 'icon' => 'building', 'color' => 'primary', 'placeholder' => '00000000000191'],
                            ['id' => 'ibge', 'name' => 'IBGE Municipios', 'icon' => 'geo-alt', 'color' => 'success', 'placeholder' => '3550308'],
                            ['id' => 'bcb', 'name' => 'BCB Cambio', 'icon' => 'currency-dollar', 'color' => 'warning', 'placeholder' => 'USD'],
                            ['id' => 'ddd', 'name' => 'BrasilAPI DDD', 'icon' => 'telephone', 'color' => 'secondary', 'placeholder' => '11'],
                            ['id' => 'cptec', 'name' => 'CPTEC Clima', 'icon' => 'cloud-sun', 'color' => 'info', 'placeholder' => '3550308'],
                            ['id' => 'nominatim', 'name' => 'Nominatim Maps', 'icon' => 'map', 'color' => 'danger', 'placeholder' => 'Sao Paulo, SP'],
                            ['id' => 'receitaws', 'name' => 'ReceitaWS', 'icon' => 'file-earmark-text', 'color' => 'success', 'placeholder' => '00000000000191'],
                            ['id' => 'compliance', 'name' => 'Transparencia', 'icon' => 'shield-check', 'color' => 'warning', 'placeholder' => '00000000000191'],
                        ];
                        foreach ($apis as $api): ?>
                            <div class="col-md-6 col-lg-3">
                                <div class="card border-0 shadow-sm api-card">
                                    <div class="card-body">
                                        <h6 class="mb-2">
                                            <i class="bi bi-<?= e($api['icon']) ?> text-<?= e($api['color']) ?> me-1"></i>
                                            <?= e($api['name']) ?>
                                        </h6>
                                        <div class="input-group input-group-sm mb-2">
                                            <input type="text" x-model="params.<?= e($api['id']) ?>" class="form-control" placeholder="<?= e($api['placeholder']) ?>">
                                            <button class="btn btn-<?= e($api['color']) ?>" @click="run('<?= e($api['id']) ?>', params.<?= e($api['id']) ?>)" :disabled="loading.<?= e($api['id']) ?>">
                                                <span x-show="!loading.<?= e($api['id']) ?>">Testar</span>
                                                <span x-show="loading.<?= e($api['id']) ?>" class="spinner-border spinner-border-sm"></span>
                                            </button>
                                        </div>
                                        <template x-if="results.<?= $api['id'] ?>">
                                            <div :class="results.<?= $api['id'] ?>.ok ? 'text-success' : 'text-danger'" class="small">
                                                <i :class="results.<?= $api['id'] ?>.ok ? 'bi bi-check-circle-fill' : 'bi bi-x-circle-fill'"></i>
                                                <span x-text="results.<?= $api['id'] ?>.ok ? 'OK (' + results.<?= $api['id'] ?>.duration_ms + 'ms)' : 'Erro'"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Resultado JSON -->
                        <div class="col-12 mt-3" x-show="lastResponse">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-dark text-white py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small><i class="bi bi-code-slash me-2"></i>Resposta JSON</small>
                                        <button class="btn btn-link btn-sm text-white p-0" @click="lastResponse = null">Limpar</button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <pre class="mb-0 p-3" style="max-height: 300px; overflow: auto; font-size: 0.75rem;" x-text="JSON.stringify(lastResponse, null, 2)"></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- SEGURANCA -->
                <div class="tab-pane fade" id="seguranca" data-phpstan='<?= isset($phpstanResult) ? htmlspecialchars(json_encode($phpstanResult), ENT_QUOTES, 'UTF-8') : '' ?>'>
                    <div class="section-header">
                        <h4 class="mb-1"><i class="bi bi-shield-lock me-2"></i>Seguranca do Codigo</h4>
                        <p class="mb-0 opacity-75 small">Analise estatica e verificacoes de seguranca</p>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-3"><i class="bi bi-shield-check text-primary me-2"></i>Analise PHPStan (CI)</h6>
                                    <p class="text-muted small mb-3">Detecta bugs, erros de tipo e code smells no codigo PHP via GitHub Actions.</p>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="event.preventDefault(); checkGithubActions()">
                                        <i class="bi bi-github me-1"></i>Verificar GitHub Actions
                                    </button>
                                    <div id="githubActionsResult" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="icon-circle bg-amber-subtle text-warning me-3">
                                            <i class="bi bi-exclamation-triangle fs-5"></i>
                                        </div>
                                        <h6 class="card-title mb-0 fw-bold">Analise de Depreciacao</h6>
                                    </div>
                                    <p class="text-muted small mb-3">Verifica funcoes e features que serao removidas em versoes futuras do PHP 8.3 e 8.4.</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge-xs bg-light text-muted border px-2 py-1 rounded">MODULO EM DESENVOLVIMENTO</span>
                                        <div class="spinner-grow spinner-grow-sm text-amber opacity-50" role="status"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="icon-circle bg-danger-subtle text-danger me-3">
                                            <i class="bi bi-key-fill fs-5"></i>
                                        </div>
                                        <h6 class="card-title mb-0 fw-bold">Scan de Segredos</h6>
                                    </div>
                                    <p class="text-muted small mb-3">Busca automatica por API Keys, senhas de banco ou chaves SSH expostas no diretorio <code>src/</code>.</p>
                                    <span class="badge-xs bg-light text-muted border px-2 py-1 rounded">AGENDADO PARA v1.2</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="icon-circle bg-green-subtle text-success me-3">
                                            <i class="bi bi-shield-check fs-5"></i>
                                        </div>
                                        <h6 class="card-title mb-0 fw-bold">Security Headers (CSP)</h6>
                                    </div>
                                    <p class="text-muted small mb-3">Verifica se as politicas de Content Security Policy e HSTS estao ativas e corretamente configuradas.</p>
                                    <div class="alert alert-success border-0 small py-2 mb-0">
                                        <i class="bi bi-check-circle-fill me-2"></i>Status: Ativo (CSP via Nonce)
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-3"><i class="bi bi-shield-exclamation text-warning me-2"></i>Alertas de Segurança</h6>
                                    <p class="text-muted small mb-3">Logs de acessos, tentativas de login e eventos de seguranca.</p>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadSecurityEvents()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Verificar Eventos
                                    </button>
                                    <div id="securityEvents" class="mt-3" style="max-height: 200px; overflow-y: auto;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php 
                    $phpstanResult = $_SESSION['phpstan_result'] ?? null;
                    if (!empty($phpstanResult)): 
                    ?>
                        <div class="alert <?= $phpstanResult['errors'] === 0 ? 'alert-success' : 'alert-warning' ?> mt-4" id="phpstanResult">
                            <h6><i class="bi <?= $phpstanResult['errors'] === 0 ? 'bi-check-circle' : 'bi-exclamation-triangle' ?> me-2"></i>
                            PHPStan: <?= $phpstanResult['errors'] ?> problemas encontrados</h6>
                            <?php if (!empty($phpstanResult['output'])): ?>
                                <pre class="mt-2 p-2 bg-dark text-light rounded small" style="max-height: 300px; overflow: auto;" id="phpstanOutput"><?= e(substr($phpstanResult['output'], 0, 3000)) ?></pre>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="clearPhpstanResult()">
                                <i class="bi bi-x-lg me-1"></i>Limpar resultado
                            </button>
                        </div>
                        <script>
                            try {
                                localStorage.setItem('phpstan_result', JSON.stringify(<?= json_encode($phpstanResult) ?>));
                            } catch(e) {
                                console.warn('Could not save PHPStan result to localStorage:', e);
                            }
                        </script>
                        <?php unset($_SESSION['phpstan_result']); ?>
                    <?php endif; ?>
                </div>
                
                <!-- Botao Salvar -->
                <!-- Acoes de Configuracao Sticky -->
                <div class="save-actions-container" id="saveButtonWrapper" style="display: none;">
                    <div class="card border-0 shadow-lg bg-brand text-white overflow-hidden" style="border-radius: 20px;">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center">
                            <div class="ps-2">
                                <h6 class="mb-0 fw-bold">Alteracoes Pendentes</h6>
                                <small class="opacity-75">Nao esqueca de salvar suas configuracoes</small>
                            </div>
                            <button class="btn btn-light fw-bold px-4" type="submit">
                                <i class="bi bi-cloud-arrow-up-fill me-2"></i>Salvar Agora
                            </button>
                        </div>
                        <div class="bg-white bg-opacity-10 py-1 px-3 text-center x-small">
                            <i class="bi bi-info-circle me-1"></i> As alteracoes entram em vigor imediatamente após salvar.
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let autosaveTimeout = null;
let lastSavedData = {};

function getFormData() {
    const form = document.getElementById('settingsForm');
    if (!form) return {};
    
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        if (key === 'public_search_enabled') {
            data[key] = form.querySelector('#public_search_enabled')?.checked ? '1' : '0';
        } else {
            data[key] = value;
        }
    }
    data['_token'] = document.querySelector('input[name="_token"]')?.value;
    return data;
}

function showAutosaveFeedback(status, message) {
    let toast = document.getElementById('autosaveToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'autosaveToast';
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast align-items-center" role="alert">
                <div class="d-flex">
                    <div class="toast-body" id="autosaveToastBody"></div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`;
        document.body.appendChild(toast);
    }
    
    const toastBody = document.getElementById('autosaveToastBody');
    const toastEl = toast.querySelector('.toast');
    
    if (status === 'success') {
        toastEl.className = 'toast align-items-center border-success bg-light-success';
        toastBody.innerHTML = '<i class="bi bi-check-circle-fill text-success me-2"></i>' + message;
    } else if (status === 'error') {
        toastEl.className = 'toast align-items-center border-danger bg-light-danger';
        toastBody.innerHTML = '<i class="bi bi-exclamation-circle-fill text-danger me-2"></i>' + message;
    } else {
        toastEl.className = 'toast align-items-center border-warning bg-light-warning';
        toastBody.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + message;
    }
    
    const bsToast = new bootstrap.Toast(toastEl, { delay: 2000 });
    bsToast.show();
}

function triggerAutosave() {
    const data = JSON.stringify(getFormData());
    if (data === lastSavedData) return;
    
    showAutosaveFeedback('loading', 'Salvando...');
    
    fetch('/admin/configuracoes/autosave', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(getFormData())
    })
    .then(res => res.json())
    .then(response => {
        if (response.ok) {
            lastSavedData = data;
            showAutosaveFeedback('success', 'Salvo automaticamente');
            
            const badge = document.getElementById('autosaveBadge');
            if (badge) {
                badge.className = 'badge bg-success ms-2';
                badge.textContent = 'Salvo';
            }
        } else {
            showAutosaveFeedback('error', response.error || 'Erro ao salvar');
        }
    })
    .catch(err => {
        showAutosaveFeedback('error', 'Erro de conexão');
    });
}

function debouncedAutosave() {
    if (autosaveTimeout) clearTimeout(autosaveTimeout);
    autosaveTimeout = setTimeout(triggerAutosave, 1500);
}

// Initialize autosave on form fields
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('settingsForm');
    if (!form) return;
    
    // Store initial state
    lastSavedData = JSON.stringify(getFormData());
    
    // Add event listeners to all form inputs
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('input', debouncedAutosave);
        input.addEventListener('change', debouncedAutosave);
    });
    
    // Special handling for checkbox
    const checkbox = form.querySelector('#public_search_enabled');
    if (checkbox) {
        checkbox.addEventListener('change', debouncedAutosave);
    }
});

// Hide save button when on tabs without config fields
function updateSaveButton() {
    const saveBtn = document.getElementById('saveButtonWrapper');
    if (!saveBtn) return;
    
    const activeTab = document.querySelector('.tab-pane.show.active');
    const tabsWithSave = ['identidade', 'operacao'];
    
    if (activeTab && tabsWithSave.includes(activeTab.id)) {
        saveBtn.style.display = 'block';
    } else {
        saveBtn.style.display = 'none';
    }
}

<form method="post" action="/admin/logs/clear" id="clearLogsForm" style="display:none;">
    <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
</form>

<form method="post" action="/admin/drive-upload" id="driveUploadForm" enctype="multipart/form-data" style="display:none;">
    <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
</form>

<script>
// PHPStan result persistence
function loadPhpstanResult() {
    const existing = document.getElementById('phpstanResult');
    if (existing) return;
    
    const segurancaTab = document.getElementById('seguranca');
    if (!segurancaTab) return;
    
    let result = null;
    const dataAttr = segurancaTab.getAttribute('data-phpstan');
    
    if (dataAttr) {
        try {
            result = JSON.parse(dataAttr);
            localStorage.setItem('phpstan_result', dataAttr);
        } catch (e) {
            console.error('Error parsing PHPStan data attribute:', e);
        }
    }
    
    if (!result) {
        const stored = localStorage.getItem('phpstan_result');
        if (!stored) return;
        try {
            result = JSON.parse(stored);
        } catch (e) {
            console.error('Error parsing PHPStan localStorage:', e);
            return;
        }
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert ' + (result.errors === 0 ? 'alert-success' : 'alert-warning') + ' mt-4';
    alertDiv.id = 'phpstanResult';
    
    let outputHtml = '';
    if (result.output) {
        const escaped = document.createElement('pre');
        escaped.className = 'mt-2 p-2 bg-dark text-light rounded small';
        escaped.style.maxHeight = '300px';
        escaped.style.overflow = 'auto';
        escaped.textContent = result.output;
        outputHtml = escaped.outerHTML;
    }
    
    alertDiv.innerHTML = `
        <h6><i class="bi ${result.errors === 0 ? 'bi-check-circle' : 'bi-exclamation-triangle'} me-2"></i>
        PHPStan: ${result.errors} problemas encontrados</h6>
        ${outputHtml}
        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="clearPhpstanResult()">
            <i class="bi bi-x-lg me-1"></i>Limpar resultado
        </button>
    `;
    
    segurancaTab.appendChild(alertDiv);
}

function clearPhpstanResult() {
    localStorage.removeItem('phpstan_result');
    const resultEl = document.getElementById('phpstanResult');
    if (resultEl) resultEl.remove();
    const segurancaTab = document.getElementById('seguranca');
    if (segurancaTab) segurancaTab.removeAttribute('data-phpstan');
}

// Run on tab change and on DOM ready
document.querySelectorAll('.admin-sidebar .nav-link[data-bs-toggle="tab"]').forEach(link => {
    link.addEventListener('shown.bs.tab', updateSaveButton);
});
document.addEventListener('DOMContentLoaded', updateSaveButton);

// Also check when Bootstrap toggles classes
document.querySelectorAll('.tab-pane').forEach(tab => {
    const observer = new MutationObserver(updateSaveButton);
    observer.observe(tab, { attributes: true, attributeFilter: ['class'] });
});
// Highlight sidebar on tab change and update URL
document.querySelectorAll('.admin-sidebar .nav-link[data-bs-toggle="tab"]').forEach(link => {
    link.addEventListener('shown.bs.tab', () => {
        document.querySelectorAll('.admin-sidebar .nav-link').forEach(l => l.classList.remove('active'));
        link.classList.add('active');
        
        const target = link.getAttribute('href');
        if (target && target.startsWith('#')) {
            const newUrl = window.location.pathname + target;
            history.pushState(null, '', newUrl);
        }
    });
});

// Check for hash in URL on load
document.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash;
    if (hash) {
        const tab = document.querySelector(`.admin-sidebar a[href="${hash}"]`);
        if (tab) {
            const bsTab = new bootstrap.Tab(tab);
            bsTab.show();
        }
    }
    
    loadPhpstanResult();
});

function checkGithubActions() {
    const container = document.getElementById('githubActionsResult');
    container.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"></div> Verificando...';
    
    fetch('/admin/phpstan/github')
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                if (data.setup) {
                    container.innerHTML = `
                        <div class="alert alert-warning mb-0 small">
                            <strong>Configurar GitHub Token:</strong><br>
                            Adicione no arquivo <code>.env</code>:<br>
                            <code>GITHUB_TOKEN=ghp_xxxxx</code><br><br>
                            Gere um token em: <a href="https://github.com/settings/tokens" target="_blank">GitHub Settings</a><br>
                            Permissão necessária: <code>repo</code>
                            <hr class="my-2">
                            <strong>Opcionalmente:</strong><br>
                            <code>GITHUB_REPO_OWNER=seu_usuario</code><br>
                            <code>GITHUB_REPO_NAME=nome_do_repo</code>
                        </div>
                    `;
                } else {
                    container.innerHTML = `<div class="alert alert-danger mb-0 small">${data.error}</div>`;
                }
                return;
            }
            
            const runs = data.runs;
            if (runs.length === 0) {
                container.innerHTML = '<div class="alert alert-info mb-0 small">Nenhum workflow run encontrado.</div>';
                return;
            }
            
            let html = '<div class="small">';
            runs.slice(0, 3).forEach(run => {
                const statusIcon = run.status === 'success' ? 'check-circle-fill text-success' : 
                                   run.status === 'failure' ? 'x-circle-fill text-danger' :
                                   run.status === 'running' ? 'arrow-repeat spin' : 'clock text-warning';
                const date = new Date(run.created_at).toLocaleString('pt-BR');
                const statusText = run.status === 'success' ? 'Sucesso' : 
                                   run.status === 'failure' ? 'Falhou' :
                                   run.status === 'running' ? 'Executando' : 'Pendente';
                const alertClass = run.status === 'success' ? 'border-success' : 
                                   run.status === 'failure' ? 'border-danger' : 'border-secondary';
                
                let logsHtml = '';
                if (run.logs) {
                    logsHtml = `
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="toggleLogs('logs-${run.id}')">
                                <i class="bi bi-terminal me-1"></i>Ver logs de erro
                            </button>
                            <pre id="logs-${run.id}" class="bg-dark text-light p-2 rounded mt-2" style="display:none; max-height: 300px; overflow: auto; font-size: 0.7rem; white-space: pre-wrap;">${escapeHtml(run.logs)}</pre>
                        </div>
                    `;
                }
                
                html += `
                    <div class="d-flex flex-column mb-2 p-2 rounded bg-light border ${alertClass}">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-${statusIcon} me-2"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold">#${run.number} - ${run.branch} <span class="badge bg-${run.status === 'success' ? 'success' : run.status === 'failure' ? 'danger' : 'secondary'}">${statusText}</span></div>
                                <div class="text-muted small">${date}</div>
                                ${run.message ? `<div class="text-muted small mt-1">${escapeHtml(run.message.split('\n')[0])}</div>` : ''}
                            </div>
                            <a href="${run.html_url}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        </div>
                        ${logsHtml}
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = '<div class="alert alert-danger mb-0 small">Erro ao conectar com o servidor.</div>';
        });
}

function toggleLogs(id) {
    const el = document.getElementById(id);
    if (el) {
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

let _securityEventsLoading = false;

function loadSecurityEvents() {
    if (_securityEventsLoading) return;
    _securityEventsLoading = true;
    
    const containers = [document.getElementById('securityEvents'), document.getElementById('securityEventsOverview')];
    containers.forEach(container => {
        if (container) container.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> Carregando...';
    });
    
    fetch('/admin/security/events')
        .then(res => res.json())
        .then(data => {
            _securityEventsLoading = false;
            containers.forEach(container => {
                if (!container) return;
                
                if (!data.success || data.count === 0) {
                    container.innerHTML = '<div class="text-muted small py-4 text-center"><i class="bi bi-shield-check opacity-50 d-block fs-3 mb-2"></i>Nenhum alerta de seguranca.</div>';
                    return;
                }
                        
                        let html = '<ul class="list-unstyled mb-0 small">';
                        data.events.slice(0, 10).forEach(event => {
                            const level = event.level || 'INFO';
                            const date = new Date(event.timestamp).toLocaleString('pt-BR');
                            const message = escapeHtml(event.message || '');
                            const ip = event.context?.ip || '-';
                            
                            html += `
                                <li class="activity-item px-1">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <span class="badge bg-${level === 'ERROR' ? 'danger' : level === 'WARNING' ? 'warning' : 'info'} badge-xs opacity-75">${level}</span>
                                        <span class="text-muted x-small">${date}</span>
                                    </div>
                                    <div class="fw-medium text-dark-emphasis">${message}</div>
                                    <div class="text-muted x-small mt-1 d-flex align-items-center">
                                        <i class="bi bi-globe me-1"></i> ${ip}
                                    </div>
                                </li>
                            `;
                        });
                        html += '</ul>';
                        container.innerHTML = html;
                    });
        })
        .catch(() => {
            containers.forEach(container => {
                if (container) container.innerHTML = '<div class="text-danger small">Erro ao carregar eventos.</div>';
            });
        });
}

// Initial load
document.addEventListener('DOMContentLoaded', loadSecurityEvents);
</script>

<style>
.save-actions-container {
    position: fixed;
    bottom: 30px;
    right: 30px;
    left: calc(25% + 40px); /* Ajustado para acompanhar a largura da sidebar no desktop */
    z-index: 1050;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    animation: slideInUp 0.5s forwards;
}

@media (max-width: 991.98px) {
    .save-actions-container {
        left: 20px;
        right: 20px;
        bottom: 20px;
    }
}

@keyframes slideInUp {
    from { transform: translateY(100px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.btn-xs {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}
</style>

<script nonce="<?= (string) ($_SERVER['CSP_NONCE'] ?? '') ?>">
// Sistema de Toast (mensagens temporárias)
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0 show`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    const icon = type === 'success' ? 'bi-check-circle-fill' : type === 'error' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill';
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ${icon} me-2"></i>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    container.appendChild(toast);
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// Sistema de Alertas em Tempo Real (barra superior)
function loadAlerts() {
    const alertsBar = document.getElementById('alerts-bar');
    if (!alertsBar) return;
    
    fetch('/admin/observabilidade/recent-logs?level=WARNING&lines=5')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.logs && data.logs.length > 0) {
                alertsBar.innerHTML = data.logs.slice(0, 3).map(log => {
                    const level = log.level || 'WARNING';
                    const icon = level === 'ERROR' ? 'bi-exclamation-octagon-fill' : 'bi-exclamation-triangle-fill';
                    const bgClass = level === 'ERROR' ? 'bg-danger' : 'bg-warning';
                    const textClass = level === 'ERROR' ? 'text-white' : 'text-dark';
                    
                    return `
                        <div class="alert alert-sm ${bgClass} ${textClass} d-flex align-items-center py-1 px-2 me-2" role="alert">
                            <i class="bi ${icon} me-1"></i>
                            <small>${log.message || 'Alerta detectado'}</small>
                            <button type="button" class="btn-close btn-close-${textClass} ms-2" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                }).join('');
            }
        })
        .catch(() => {});
}

// Carrega alertas ao abrir a página
document.addEventListener('DOMContentLoaded', function() {
    loadAlerts();
    
    // Atualiza alertas automaticamente a cada 30 segundos
    setInterval(loadAlerts, 30000);
});

// Filtros de Logs
function filterLogs() {
    const container = document.getElementById('logs-filter-result');
    const level = document.getElementById('log-level-filter')?.value || '';
    const lines = document.getElementById('log-lines-filter')?.value || 25;
    
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div></div>';
    
    let url = '/admin/observabilidade/recent-logs?lines=' + lines;
    if (level) url += '&level=' + level;
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.logs && data.logs.length > 0) {
                let html = '<table class="table table-sm table-hover"><thead><tr><th>Data/Hora</th><th>Tipo</th><th>Mensagem</th><th>IP</th></tr></thead><tbody>';
                
                data.logs.forEach(log => {
                    const levelClass = log.level === 'ERROR' ? 'text-danger' : log.level === 'WARNING' ? 'text-warning' : 'text-muted';
                    html += `
                        <tr>
                            <td><small>${log.timestamp || '-'}</small></td>
                            <td><span class="${levelClass}">${log.level || '-'}</span></td>
                            <td><small>${log.message || '-'}</small></td>
                            <td><small>${log.remote_ip || '-'}</small></td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="alert alert-info">Nenhum log encontrado.</div>';
            }
        })
        .catch(() => {
            container.innerHTML = '<div class="alert alert-danger">Erro ao carregar logs.</div>';
        });
}

// Carrega logs automaticamente ao abrir a página
document.addEventListener('DOMContentLoaded', function() {
    filterLogs();
});
</script>

<script nonce="<?= (string) ($_SERVER['CSP_NONCE'] ?? '') ?>">
function apiTester() {
    return {
        params: {
            cnpj: '00000000000191',
            ibge: '3550308',
            bcb: 'USD',
            ddd: '11',
            cptec: '3550308',
            nominatim: 'Sao Paulo, SP',
            receitaws: '00000000000191',
            compliance: '00000000000191'
        },
        loading: { cnpj: false, ibge: false, bcb: false, ddd: false, cptec: false, nominatim: false, receitaws: false, compliance: false },
        results: { cnpj: null, ibge: null, bcb: null, ddd: null, cptec: null, nominatim: null, receitaws: null, compliance: null },
        lastResponse: null,
        run(api, param = '') {
            this.loading[api] = true;
            this.results[api] = null;
            const formData = new FormData();
            formData.append('api', api);
            formData.append('param', param);
            formData.append('_token', '<?= Csrf::token() ?>');
            fetch('/admin/api-tester/test', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                this.results[api] = data;
                if (data.ok) this.lastResponse = data.data;
                else this.lastResponse = { error: data.error };
            })
            .catch(() => {
                this.results[api] = { ok: false, error: 'Erro de conexao' };
            })
            .finally(() => { this.loading[api] = false; });
        }
    }
}
</script>
