<?php declare(strict_types=1);
$health = $health ?? [];
$metrics = $metrics ?? [];
$weatherUpdates = $metrics['weather_updates'] ?? [];
$exchangeUpdates = $metrics['exchange_updates'] ?? [];
$counts = $metrics['counts'] ?? [];
$apiLogs = $metrics['api_logs'] ?? [];
$appLogs = $metrics['logs']['app_tail'] ?? [];
$latency = $metrics['latency'] ?? [];
$topQueries = $metrics['top_queries'] ?? [];
$topCompanies = $metrics['top_companies'] ?? [];
$companiesByStatus = $metrics['companies_by_status'] ?? [];
$topMunicipalities = $metrics['top_municipalities'] ?? [];
$latestCompany = $metrics['latest_company'] ?? [];

$formatDate = static function (?string $value, string $pattern = 'd/m/Y H:i'): string {
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '-';
    }

    return date($pattern, $timestamp);
};
?>

<div class="section-header fade-in mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1"><i class="bi bi-activity me-2"></i>Observabilidade</h4>
            <p class="mb-0 opacity-75 small">Monitoramento em tempo real, integridade e logs do sistema</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-light shadow-sm" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
            </button>
            <a href="/admin" class="btn btn-sm btn-outline-light">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <h2 class="h6 mb-1">Navegação da Página</h2>
            <p class="text-muted small mb-0">Acesse rapidamente a área que você precisa operar.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="#sec-overview" class="btn btn-outline-primary btn-sm">Visão Geral</a>
            <a href="#sec-business" class="btn btn-outline-primary btn-sm">Negócio</a>
            <a href="#sec-integrations" class="btn btn-outline-primary btn-sm">Integrações</a>
            <a href="#sec-health" class="btn btn-outline-primary btn-sm">Saúde e Logs</a>
        </div>
    </div>
</div>

<?php if (!empty($health['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Problema detectado:</strong> <?= e($health['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h3 class="h6 mb-2"><i class="bi bi-compass me-2 text-primary"></i>Fluxo Operacional</h3>
                <p class="text-muted small mb-3">Ações do dia a dia para monitorar e atualizar dados.</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-secondary btn-sm text-start" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Atualizar painel
                    </button>
                    <a href="#sec-health" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-heart-pulse me-1"></i> Ver saúde do sistema
                    </a>
                    <a href="#sec-integrations" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-diagram-3 me-1"></i> Conferir integrações
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h3 class="h6 mb-2"><i class="bi bi-tools me-2 text-info"></i>Ferramentas Técnicas</h3>
                <p class="text-muted small mb-3">Acesso às funcionalidades de teste e execução.</p>
                <div class="d-grid gap-2">
                    <a href="/admin/api-tester" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-terminal me-1"></i> API Tester
                    </a>
                    <a href="/admin/configuracoes" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-gear me-1"></i> Configurações
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h3 class="h6 mb-2"><i class="bi bi-shield-check me-2 text-warning"></i>Governança</h3>
                <p class="text-muted small mb-3">Tarefas administrativas sensíveis e manutenção.</p>
                <div class="d-grid gap-2">
                    <a href="/admin/remocoes" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-trash me-1"></i> Remoções de dados
                    </a>
                    <a href="#sec-business" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-bar-chart me-1"></i> Revisar indicadores
                    </a>
                    <a href="#sec-overview" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-grid me-1"></i> Voltar ao resumo
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<section id="sec-overview" class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">Visão Geral</h2>
            <p class="text-muted small mb-0">Panorama rápido dos principais números da plataforma.</p>
        </div>
    </div>
</section>

<div class="row g-4 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-building fs-2 text-primary mb-2"></i>
                <div class="h4 mb-0"><?= number_format($counts['companies'] ?? 0) ?></div>
                <small class="text-muted">Total Empresas</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-check-circle fs-2 text-success mb-2"></i>
                <div class="h4 mb-0"><?= number_format($counts['active_companies'] ?? 0) ?></div>
                <small class="text-muted">Ativas</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-x-circle fs-2 text-danger mb-2"></i>
                <div class="h4 mb-0"><?= number_format($counts['inactive_companies'] ?? 0) ?></div>
                <small class="text-muted">Inativas</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-geo-alt fs-2 text-warning mb-2"></i>
                <div class="h4 mb-0"><?= number_format($counts['municipalities'] ?? 0) ?></div>
                <small class="text-muted">Municípios</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-people fs-2 text-info mb-2"></i>
                <div class="h4 mb-0"><?= number_format($counts['users'] ?? 0) ?></div>
                <small class="text-muted">Usuários</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-search fs-2 text-secondary mb-2"></i>
                <div class="h4 mb-0"><?= number_format($counts['query_logs_24h'] ?? 0) ?></div>
                <small class="text-muted">Buscas (24h)</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-cloud-download fs-2 text-primary mb-2"></i>
                <div class="h4 mb-0"><?= number_format($counts['api_attempts_24h'] ?? 0) ?></div>
                <small class="text-muted">API (24h)</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-currency-exchange fs-2 text-success mb-2"></i>
                <div class="h4 mb-0"><?= number_format($counts['exchange_currencies'] ?? 0) ?></div>
                <small class="text-muted">Moedas</small>
            </div>
        </div>
    </div>
</div>

<section id="sec-business" class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">Negócio e Uso</h2>
            <p class="text-muted small mb-0">Indicadores de cadastro, demanda e comportamento de busca.</p>
        </div>
    </div>
</section>

<?php if (!empty($latestCompany)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="card-title">
            <i class="bi bi-plus-circle me-2 text-success"></i>Última Empresa Cadastrada
        </h5>
        <div class="row align-items-center">
            <div class="col-md-4">
                <strong><?= e($latestCompany['trade_name'] ?? 'N/A') ?></strong>
            </div>
            <div class="col-md-4">
                <small class="text-muted">CNPJ: <?= e($latestCompany['cnpj'] ?? '-') ?></small>
            </div>
            <div class="col-md-4 text-md-end">
                <small class="text-muted">Abertura: <?= $formatDate($latestCompany['opened_at'] ?? null, 'd/m/Y') ?></small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($topMunicipalities)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="card-title">
            <i class="bi bi-geo me-2 text-warning"></i>Cidades com Mais Empresas
        </h5>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Cidade</th>
                        <th>UF</th>
                        <th class="text-end">Empresas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topMunicipalities as $muni): ?>
                        <tr>
                            <td><?= e($muni['name'] ?? '-') ?></td>
                            <td><span class="badge bg-secondary"><?= e($muni['state_uf'] ?? '-') ?></span></td>
                            <td class="text-end"><strong><?= number_format($muni['total']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($companiesByStatus)): ?>
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-pie-chart me-2 text-primary"></i>Empresas por Status
                </h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <?php foreach ($companiesByStatus as $status): ?>
                            <tr>
                                <td><?= e($status['status'] ?? '-') ?></td>
                                <td class="text-end"><strong><?= number_format($status['total']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php if (!empty($topCompanies)): ?>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-star me-2 text-warning"></i>Empresas Mais Procuradas
                </h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th class="text-end">Buscas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($topCompanies, 0, 5) as $company): ?>
                                <tr>
                                    <td><?= e($company['trade_name'] ?? $company['cnpj'] ?? '-') ?></td>
                                    <td class="text-end"><strong><?= number_format($company['total']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($topQueries)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="card-title">
            <i class="bi bi-award me-2 text-warning"></i>Termos de Busca mais Populares (7 dias)
        </h5>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Termo</th>
                        <th class="text-end">Buscas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topQueries as $query): ?>
                        <tr>
                            <td><?= e($query['search_term'] ?? '-') ?></td>
                            <td class="text-end"><strong><?= number_format($query['total']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<section id="sec-integrations" class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">Integrações e Sincronizações</h2>
            <p class="text-muted small mb-0">Atualizações externas e requisições para provedores de dados.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title">
                <i class="bi bi-building me-2 text-info"></i>Municípios - Importar MUNICCSV
            </h5>
            <p class="text-muted small mb-3">Importe o arquivo MUNICCSV da Receita Federal para atualizar a lista de municípios.</p>
            <form method="post" action="/admin/localidades/sync" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= \App\Core\Csrf::token() ?>">
                <input type="hidden" name="action" value="munic">
                <div class="row g-3 align-items-end">
                    <div class="col-auto flex-grow-1">
                        <input type="file" name="munic_file" class="form-control" accept=".csv,.txt" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i> Importar
                        </button>
                    </div>
                </div>
                <small class="text-muted">Arquivos .csv ou .txt (máx 50MB)</small>
            </form>
        </div>
    </div>
</section>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-cloud-sun me-2 text-info"></i>Clima - Atualizações
                </h5>
                <div class="mb-3">
                    <span class="badge bg-primary"><?= $counts['municipalities_with_weather'] ?? 0 ?></span> municípios com dados de clima
                </div>
                <?php if (!empty($weatherUpdates)): ?>
                    <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>IBGE</th>
                                    <th>Cidade</th>
                                    <th>Atualização</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($weatherUpdates, 0, 10) as $update): ?>
                                    <tr>
                                        <td><?= $update['ibge_code'] ?></td>
                                        <td><?= e($update['name'] ?? '-') ?></td>
                                        <td><small><?= $formatDate($update['updated_at'] ?? null, 'd/m H:i') ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small">Nenhuma atualização de clima registrada.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-currency-exchange me-2 text-success"></i>Indicadores Econômicos
                </h5>
                <div class="mb-3">
                    <span class="badge bg-success"><?= $counts['exchange_records'] ?? 0 ?></span> registros de câmbio
                </div>
                <?php if (!empty($exchangeUpdates)): ?>
                    <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Moeda</th>
                                    <th>Última Cotação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($exchangeUpdates, 0, 10) as $update): ?>
                                    <tr>
                                        <td><strong><?= $update['currency'] ?></strong></td>
                                        <td><small><?= $formatDate($update['updated_at'] ?? null, 'd/m/Y') ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small">Nenhuma atualização de câmbio registrada.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($apiLogs)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-4 px-4">
        <h3 class="h6 mb-0 fw-bold"><i class="bi bi-cloud-download me-2 text-primary"></i>Últimas Requisições de API</h3>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>CNPJ</th>
                        <th>Provedor</th>
                        <th>Status</th>
                        <th>Data/Hora</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apiLogs as $log): ?>
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
                            <td><small><?= $formatDate($log['fetched_at'] ?? null, 'd/m H:i') ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<section id="sec-health" class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">Saúde, Performance e Logs</h2>
            <p class="text-muted small mb-0">Estado do ambiente, métricas de falha e últimos registros de aplicação.</p>
        </div>
    </div>
</section>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-check-circle me-2 text-success"></i>Status do Sistema
                </h5>
                <table class="table table-borderless table-sm">
                    <tr>
                        <td>Database</td>
                        <td>
                            <?php if (!empty($health['database']['ok'])): ?>
                                <span class="badge bg-success">Online</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Offline</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Última Verificação</td>
                        <td><small><?= $formatDate($health['checked_at'] ?? null, 'd/m H:i:s') ?></small></td>
                    </tr>
                    <tr>
                        <td>Ambiente</td>
                        <td><small><?= e($health['app_env'] ?? '-') ?></small></td>
                    </tr>
                    <tr>
                        <td>Timezone</td>
                        <td><small><?= e($health['timezone'] ?? '-') ?></small></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-bar-chart me-2 text-primary"></i>Métricas
                </h5>
                <table class="table table-borderless table-sm">
                    <tr>
                        <td>Falhas de API (24h)</td>
                        <td><strong><?= number_format($counts['api_failures_24h'] ?? 0) ?></strong></td>
                    </tr>
                    <tr>
                        <td>Média de tentativas/empresa (24h)</td>
                        <td><strong><?= $latency['avg_attempts_last_24h'] ?? 0 ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($appLogs)): ?>
<div class="card border-0 shadow-sm mb-4 mt-4">
    <div class="card-header bg-white border-0 pt-4 px-4">
        <h3 class="h6 mb-0 fw-bold"><i class="bi bi-file-text me-2 text-warning"></i>Últimas Entradas do Log</h3>
    </div>
    <div class="card-body px-4 pb-4">
        <pre class="bg-dark text-light p-3 rounded small" style="max-height: 300px; overflow: auto;"><?php foreach ($appLogs as $line): ?><?= e($line) . "\n" ?>

<?php endforeach; ?></pre>
    </div>
</div>
<?php endif; ?>
