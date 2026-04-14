<div class="section-header fade-in">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1"><i class="bi bi-graph-up me-2"></i>Analytics e Inteligência</h4>
            <p class="mb-0 opacity-75 small">Análise de consultas, tendências e dados da base</p>
        </div>
        <div class="d-flex gap-2">
            <form method="get" class="d-flex gap-2">
                <select name="days" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 100px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2);">
                    <option value="7" class="text-dark" <?= $selectedDays === 7 ? 'selected' : '' ?>>7 dias</option>
                    <option value="30" class="text-dark" <?= $selectedDays === 30 ? 'selected' : '' ?>>30 dias</option>
                    <option value="90" class="text-dark" <?= $selectedDays === 90 ? 'selected' : '' ?>>90 dias</option>
                    <option value="180" class="text-dark" <?= $selectedDays === 180 ? 'selected' : '' ?>>6 meses</option>
                    <option value="365" class="text-dark" <?= $selectedDays === 365 ? 'selected' : '' ?>>1 ano</option>
                </select>
            </form>
            <a href="/admin/analytics/exportar" class="btn btn-sm btn-light shadow-sm">
                <i class="bi bi-download me-1"></i>Baixar CSV
            </a>
        </div>
    </div>
</div>

<?php
$stats = $stats ?? [];
$dailyStats = $stats['daily_stats'] ?? [];
$topCompanies = $stats['top_companies'] ?? [];
$byState = $stats['by_state'] ?? [];
$monthlyStats = $stats['monthly_stats'] ?? [];
$newCompanies = $stats['new_companies'] ?? [];
$searchTerms = $stats['search_terms'] ?? [];
$peakHour = $stats['peak_hour'] ?? ['hour' => 0, 'total' => 0];
$totalConsults = $stats['total_consults'] ?? 0;
?>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card analytics-card h-100">
            <div class="card-body text-center p-3">
                <div class="h2 mb-0 text-primary"><?= number_format($totalConsults, 0, ',', '.') ?></div>
                <small class="text-muted">Total Consultas</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card analytics-card h-100">
            <div class="card-body text-center p-3">
                <div class="h2 mb-0 text-success"><?= number_format((int) ($stats['weekly_stats'][count($stats['weekly_stats']) - 1]['total'] ?? 0), 0, ',', '.') ?></div>
                <small class="text-muted">Esta Semana</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card analytics-card h-100">
            <div class="card-body text-center p-3">
                <div class="h2 mb-0"><?= number_format((int) ($newCompanies['total_new'] ?? 0), 0, ',', '.') ?></div>
                <small class="text-muted">Empresas Adicionadas</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card analytics-card h-100">
            <div class="card-body text-center p-3">
                <div class="h2 mb-0"><?= sprintf('%02d:00', $peakHour['hour']) ?></div>
                <small class="text-muted">Pico: <?= number_format($peakHour['total'], 0, ',', '.') ?> consultas</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Chart -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Consultas por Dia</h5>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Companies -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-trophy me-2 text-warning"></i>Top 10 Mais Consultadas</h5>
                <a href="/admin/analytics/comparar" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-bar-chart"></i> Comparar
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Empresa</th>
                                <th class="text-end">Consultas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topCompanies)): ?>
                                <tr><td colspan="3" class="text-center text-muted">Nenhuma consulta registrada</td></tr>
                            <?php else: ?>
                                <?php foreach ($topCompanies as $index => $company): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <a href="/empresas/<?= e($company['cnpj']) ?>">
                                            <?= e(substr($company['trade_name'] ?? $company['legal_name'], 0, 30)) ?>
                                        </a>
                                        <br><small class="text-muted"><?= e(($company['city'] ?? '-') . '/' . ($company['state'] ?? '-')) ?></small>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-primary"><?= number_format((int) $company['consult_count'], 0, ',', '.') ?></span>
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

    <!-- Consultas por Estado -->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Consultas por Estado</h5>
            </div>
            <div class="card-body">
                <?php if (empty($byState)): ?>
                    <p class="text-muted text-center">Nenhum dado disponivel</p>
                <?php else: ?>
                    <div class="row g-2">
                        <?php 
                        $totalState = array_sum(array_column($byState, 'total_consults'));
                        foreach (array_slice($byState, 0, 12) as $state): 
                            $percent = $totalState > 0 ? round(($state['total_consults'] / $totalState) * 100, 1) : 0;
                        ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="border rounded p-2 text-center">
                                <div class="h6 mb-0"><?= e($state['state']) ?></div>
                                <small class="text-muted"><?= number_format((int) $state['total_consults'], 0, ',', '.') ?></small>
                                <div class="progress mt-1" style="height: 4px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?= $percent ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Termos de Busca -->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-search me-2"></i>Termos Mais Buscados (API)</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($searchTerms)): ?>
                    <p class="text-muted text-center p-3">Nenhum dado disponivel</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach (array_slice($searchTerms, 0, 10) as $term): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?= e($term['search_term']) ?></span>
                            <span class="badge bg-secondary"><?= number_format((int) $term['total'], 0, ',', '.') ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Monthly Stats Chart -->
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Evolucao Mensal</h5>
            </div>
            <div class="card-body">
                <div style="height: 250px;">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dailyData = <?= json_encode($dailyStats) ?>;
    const monthlyData = <?= json_encode($monthlyStats) ?>;
    
    // Daily Chart
    if (dailyData.length > 0) {
        new Chart(document.getElementById('dailyChart'), {
            type: 'line',
            data: {
                labels: dailyData.map(d => {
                    const date = new Date(d.date + 'T00:00:00');
                    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
                }),
                datasets: [{
                    label: 'Consultas',
                    data: dailyData.map(d => d.total),
                    borderColor: '#0f766e',
                    backgroundColor: 'rgba(15, 118, 110, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#0f766e'
                }, {
                    label: 'Empresas Unicas',
                    data: dailyData.map(d => d.unique_companies),
                    borderColor: '#f59e0b',
                    backgroundColor: 'transparent',
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 2,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: { 
                        type: 'linear',
                        position: 'left',
                        title: { display: true, text: 'Consultas' }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        title: { display: true, text: 'Empresas Unicas' },
                        grid: { drawOnChartArea: false }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }
    
    // Monthly Chart
    if (monthlyData.length > 0) {
        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: monthlyData.map(d => {
                    const [year, month] = d.month.split('-');
                    const date = new Date(year, month - 1);
                    return date.toLocaleDateString('pt-BR', { month: 'short', year: '2-digit' });
                }),
                datasets: [{
                    label: 'Total Consultas',
                    data: monthlyData.map(d => d.total),
                    backgroundColor: 'rgba(15, 118, 110, 0.8)',
                    borderRadius: 4
                }, {
                    label: 'Empresas Unicas',
                    data: monthlyData.map(d => d.unique_companies),
                    backgroundColor: 'rgba(245, 158, 11, 0.8)',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: { beginAtZero: true },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>
