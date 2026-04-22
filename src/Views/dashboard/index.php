<?php declare(strict_types=1); use App\Core\Auth; ?>
<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item active" aria-current="page">Painel</li>
    </ol>
</nav>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3 fade-in">
    <div>
        <h1 class="h3 mb-1">Painel</h1>
        <p class="text-muted mb-0 small">Bem-vindo, <?= e($user['name'] ?? '') ?>.</p>
    </div>
    <?php if (in_array($user['role'] ?? '', ['admin', 'editor'], true)): ?>
        <a href="/empresas/busca" class="btn btn-brand">
            <i class="bi bi-search me-1"></i>Nova consulta CNPJ
        </a>
    <?php else: ?>
        <a href="/empresas" class="btn btn-outline-secondary">
            <i class="bi bi-building me-1"></i>Ver empresas
        </a>
    <?php endif; ?>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success alert-permanent fade-in"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-permanent fade-in"><?= e($error) ?></div>
<?php endif; ?>

<?php if (Auth::can(['admin', 'editor'])): ?>
<div class="row mb-4 fade-in">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5 mb-4"><i class="bi bi-graph-up me-2 text-muted"></i>Volume de Consultas (7 dias)</h2>
                <div style="height: 250px;">
                    <canvas id="searchChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="bi bi-activity me-2 text-muted"></i>Atividade Recente</h2>
                <div class="list-group list-group-flush x-small">
                    <?php if (empty($recentSearches)): ?>
                        <p class="text-muted p-3">Nenhuma atividade registrada.</p>
                    <?php else: ?>
                        <?php foreach ($recentSearches as $log): ?>
                            <div class="list-group-item px-0 py-2 border-0 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold text-truncate" style="max-width: 150px;"><?= e($log['legal_name'] ?: 'Busca Direta') ?></span>
                                    <small class="text-muted"><?= date('H:i', strtotime($log['created_at'])) ?></small>
                                </div>
                                <div class="d-flex justify-content-between small opacity-75">
                                    <span><?= e($log['user_name'] ?: 'Visitante') ?></span>
                                    <span><?= e($log['cnpj']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($dashboardMetrics)): ?>
<div class="row mb-4 fade-in">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card h-100">
            <div class="card-body text-center p-2">
                <div class="h4 mb-0 text-primary"><?= number_format($dashboardMetrics['total_companies'] ?? 0, 0, ',', '.') ?></div>
                <small class="text-muted">Total Empresas</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card h-100">
            <div class="card-body text-center p-2">
                <div class="h4 mb-0 text-success"><?= number_format($dashboardMetrics['total_active'] ?? 0, 0, ',', '.') ?></div>
                <small class="text-muted">Ativas</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card h-100">
            <div class="card-body text-center p-2">
                <div class="h4 mb-0"><?= number_format($dashboardMetrics['taxa_ativa'] ?? 0, 1, ',', '.') ?>%</div>
                <small class="text-muted">Taxa Ativas</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card h-100">
            <div class="card-body text-center p-2">
                <div class="h4 mb-0"><?= number_format($dashboardMetrics['total_mei'] ?? 0, 0, ',', '.') ?></div>
                <small class="text-muted">MEI</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card h-100">
            <div class="card-body text-center p-2">
                <div class="h4 mb-0"><?= number_format($dashboardMetrics['total_simples'] ?? 0, 0, ',', '.') ?></div>
                <small class="text-muted">Simples</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card h-100">
            <div class="card-body text-center p-2">
                <div class="h5 mb-0 text-nowrap">
                    <?php 
                    $capital = $dashboardMetrics['total_capital'] ?? 0;
                    if ($capital >= 1e9): echo number_format($capital/1e9, 1, ',', '.') . ' bi';
                    elseif ($capital >= 1e6): echo number_format($capital/1e6, 1, ',', '.') . ' mi';
                    else: echo number_format($capital, 0, ',', '.');
                    endif;
                    ?>
                </div>
                <small class="text-muted">Capital Total</small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('searchChart').getContext('2d');
    const data = <?= json_encode($searchStats, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(item => {
                const date = new Date(item.date + 'T00:00:00');
                return date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
            }),
            datasets: [{
                label: 'Consultas',
                data: data.map(item => item.total),
                borderColor: '#0f766e',
                backgroundColor: 'rgba(15, 118, 110, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor: '#0f766e'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { display: false } },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">

        <?php if (!empty($topCompanies)): ?>
        <div class="card mb-4 fade-in stagger-0">
            <div class="card-body">
                <h2 class="h5 mb-3">
                    <i class="bi bi-trophy me-1 text-warning"></i>Top 10 - Maior Capital Social
                </h2>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>CNPJ</th>
                                <th>Razão Social</th>
                                <th>Cidade/UF</th>
                                <th class="text-end">Capital Social</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topCompanies as $index => $company): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><a href="/empresas/<?= e($company['cnpj']) ?>"><?= e($company['cnpj']) ?></a></td>
                                    <td><?= e(substr($company['legal_name'] ?? '', 0, 40)) ?><?= strlen($company['legal_name'] ?? '') > 40 ? '...' : '' ?></td>
                                    <td><?= e(($company['city'] ?? '-') . '/' . ($company['state'] ?? '-')) ?></td>
                                    <td class="text-end fw-bold text-success">
                                        R$ <?= number_format((float) ($company['capital_social'] ?? 0), 2, ',', '.') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mb-4 fade-in stagger-1">
            <div class="card-body">
                <h2 class="h5 mb-3">
                    <i class="bi bi-clock-history me-1 text-muted"></i>Ultimas empresas consultadas
                </h2>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>CNPJ</th>
                            <th>Razao social</th>
                            <th>Cidade/UF</th>
                            <th>Atualizado</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($companies)): ?>
                            <tr>
                                <td colspan="5" class="text-muted text-center py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Sem consultas registradas.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($companies as $company): ?>
                                <tr>
                                    <td class="text-nowrap"><?= e($company['cnpj']) ?></td>
                                    <td><?= e($company['legal_name']) ?></td>
                                    <td><?= e(($company['city'] ?? '-') . '/' . ($company['state'] ?? '-')) ?></td>
                                    <td class="small text-muted"><?= e(format_datetime($company['updated_at'])) ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="/empresas/<?= e($company['cnpj']) ?>">
                                            <i class="bi bi-eye"></i><span class="d-none d-sm-inline ms-1">Abrir</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <?php if (!empty($exchangeRates['USD']) || !empty($exchangeRates['EUR'])): ?>
        <div class="card mb-4 fade-in stagger-1">
            <div class="card-body">
                <h2 class="h5 mb-3">
                    <i class="bi bi-currency-exchange me-1 text-muted"></i>Mercado e Câmbio
                </h2>
                <div class="row g-2">
                    <?php if (!empty($exchangeRates['USD'])): ?>
                    <div class="col-6">
                        <div class="p-2 border rounded exchange-card text-center">
                            <div class="small text-muted mb-1">Dólar (USD)</div>
                            <div class="h5 mb-0 fw-bold">R$ <?= number_format((float) ($exchangeRates['USD']['cotacaoVenda'] ?? 0), 2, ',', '.') ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($exchangeRates['EUR'])): ?>
                    <div class="col-6">
                        <div class="p-2 border rounded exchange-card text-center">
                            <div class="small text-muted mb-1">Euro (EUR)</div>
                            <div class="h5 mb-0 fw-bold">R$ <?= number_format((float) ($exchangeRates['EUR']['cotacaoVenda'] ?? 0), 2, ',', '.') ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mt-2 text-end">
                    <small class="text-muted" style="font-size: 0.65rem;">Fonte: Banco Central do Brasil (PTAX)</small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card fade-in stagger-2">
            <div class="card-body">
                <h2 class="h5 mb-3">
                    <i class="bi bi-shield-lock me-1 text-muted"></i>Seguranca
                </h2>
                <form method="post" action="/dashboard">
                    <input type="hidden" name="_token" value="<?= e(\App\Core\Csrf::token()) ?>">

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="two_factor_enabled" id="2fa" <?= (int) ($user['two_factor_enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="2fa">Verificacao em duas etapas (2FA)</label>
                        </div>
                        <small class="form-help">Se ativado, enviaremos um codigo por e-mail no login.</small>
                    </div>

                    <div class="mb-3" x-data="{ show: false }">
                        <label class="form-label small fw-bold">Alterar senha</label>
                        <div class="input-group">
                            <input :type="show ? 'text' : 'password'" name="password" class="form-control" placeholder="Deixe vazio para manter">
                            <button type="button" class="btn btn-outline-secondary" @click="show = !show" aria-label="Alternar visibilidade">
                                <i :class="show ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <button class="btn btn-outline-primary" type="submit">
                        <i class="bi bi-check-circle me-1"></i>Atualizar seguranca
                    </button>
                </form>
            </div>
        </div>

        <div class="card mt-4 fade-in stagger-3">
            <div class="card-body">
                <h2 class="h5 mb-3">
                    <i class="bi bi-lightning-charge me-1 text-muted"></i>Atalhos Rapidos
                </h2>
                <div class="d-grid gap-2">
                    <a href="/favoritos" class="btn btn-brand">
                        <i class="bi bi-star-fill me-2 text-warning"></i>Minhas Empresas Favoritas
                    </a>
                    <a href="/dashboard/minhas-avaliacoes" class="btn btn-outline-primary">
                        <i class="bi bi-star me-2"></i>Minhas Avaliações
                    </a>
                </div>
                <p class="text-muted small mt-2 mb-0">Acesse rapidamente as empresas que voce salvou para prospecção ou acompanhamento.<br>
                <span class="text-muted small">Veja e gerencie suas avaliações de empresas.</span>
            </div>
        </div>
    </div>
</div>

<?php if ($isAdmin || $isStaff): ?>
<!-- Seção Admin - Apenas para admins e staff -->
<div class="card mt-4 fade-in">
    <div class="card-body">
        <h2 class="h5 mb-3">
            <i class="bi bi-gear me-2 text-secondary"></i>Painel Admin
        </h2>
        <p class="text-muted small mb-3">Acesso rápido às funcionalidades administrativas.</p>
        
        <?php
        // Menu dinâmico - páginas disponíveis por role
        $adminMenu = [
            'admin' => [
                ['url' => '/dashboard/admin', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
                ['url' => '/dashboard/admin/observabilidade', 'label' => 'Observabilidade', 'icon' => 'bi-activity'],
                ['url' => '/dashboard/admin/configuracoes', 'label' => 'Configurações', 'icon' => 'bi-gear'],
                ['url' => '/dashboard/admin/analytics', 'label' => 'Analytics', 'icon' => 'bi-graph-up'],
                ['url' => '/dashboard/admin/auditoria', 'label' => 'Auditoria', 'icon' => 'bi-journal-text'],
                ['url' => '/dashboard/admin/integracoes', 'label' => 'Integrações', 'icon' => 'bi-plug'],
                ['url' => '/dashboard/admin/api-tester', 'label' => 'Testador API', 'icon' => 'bi-braces'],
                ['url' => '/usuarios', 'label' => 'Usuários', 'icon' => 'bi-people'],
            ],
            'moderator' => [
                ['url' => '/dashboard/admin/remocoes', 'label' => 'Remoções', 'icon' => 'bi-shield-check'],
                ['url' => '/dashboard/admin/integracoes', 'label' => 'Integrações', 'icon' => 'bi-plug'],
            ],
            'editor' => [
                ['url' => '/dashboard/admin', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
                ['url' => '/dashboard/admin/observabilidade', 'label' => 'Observabilidade', 'icon' => 'bi-activity'],
            ],
        ];
        
        // Filtrar menus disponíveis para o role do usuário
        $userRole = $user['role'] ?? 'user';
        $availableMenus = $adminMenu[$userRole] ?? [];
        
        // Se for admin, mostrar todos; se não, mostrar apenas os do role
        if ($userRole === 'admin') {
            $availableMenus = array_merge($adminMenu['admin'], $adminMenu['moderator'] ?? []);
        }
        ?>
        
        <div class="row g-3">
            <?php foreach ($availableMenus as $menu): ?>
            <div class="col-6 col-md-3">
                <a href="<?= $menu['url'] ?>" class="btn btn-outline-secondary w-100">
                    <i class="bi <?= $menu['icon'] ?> me-2"></i><?= $menu['label'] ?>
                </a>
            </div>
            <?php endforeach; ?>
            
            <?php if ($isAdmin): ?>
            <div class="col-6 col-md-3">
                <form method="post" action="/admin/clear-cache" class="d-inline w-100">
                    <input type="hidden" name="_token" value="<?= e(\App\Core\Csrf::token()) ?>">
                    <button type="submit" class="btn btn-outline-secondary w-100" onclick="return confirm('Limpar cache?');">
                        <i class="bi bi-trash me-2"></i>Limpar Cache
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
