<style>
.comparison-header {
    background: linear-gradient(135deg, var(--brand) 0%, #14b8a6 100%);
    border-radius: 20px;
    padding: 3rem;
    margin-bottom: 2rem;
    color: white;
}
.comparison-header h1 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}
.comparison-header .subtitle {
    opacity: 0.85;
    font-size: 1.1rem;
}
.data-table-comparison {
    border-radius: 12px;
    overflow: hidden;
}
.data-table-comparison thead {
    background: linear-gradient(135deg, var(--brand), #14b8a6);
    color: white;
}
.data-table-comparison thead th {
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    padding: 1rem;
}
.data-table-comparison tbody td {
    vertical-align: middle;
    padding: 0.85rem 1rem;
    border-color: var(--border);
}
.data-table-comparison tbody tr:hover {
    background-color: var(--brand-light);
}
.metric-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    border: 1px solid var(--border);
    transition: all 0.2s;
}
.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}
.metric-card .icon {
    font-size: 2rem;
    color: var(--brand);
    margin-bottom: 0.5rem;
}
.metric-card .value {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--brand);
}
.metric-card .label {
    font-size: 0.85rem;
    color: var(--text-muted);
}
.insight-card {
    background: var(--warning-bg);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-bottom: 0.75rem;
    border-left: 4px solid var(--warning);
    color: var(--warning-text);
}
.insight-card:last-child {
    margin-bottom: 0;
}
.conclusion-box {
    background: var(--success-bg);
    border-radius: 12px;
    padding: 1.5rem;
    border-left: 4px solid var(--success);
    color: var(--success-text);
}
.conclusion-box.dark {
    background: linear-gradient(135deg, #134e4a, #115e59);
    color: white;
}
.highlight-row {
    background-color: var(--warning-bg) !important;
}
.highlight-row td {
    font-weight: 600;
}
.badge-score {
    font-size: 1.1rem;
    padding: 0.5rem 1rem;
}
.score-bar {
    height: 8px;
    border-radius: 4px;
    background: var(--border);
    overflow: hidden;
}
.score-bar-fill {
    height: 100%;
    border-radius: 4px;
    background: linear-gradient(90deg, var(--brand), #14b8a6);
}
</style>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="/comparacoes">Comparacoes</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= e(explode(' - ', $content['title'] ?? '')[0]) ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="comparison-header">
        <h1><i class="bi bi-bar-chart-fill me-2"></i><?= e($content['title'] ?? '') ?></h1>
        <p class="subtitle mb-0"><?= e($content['subtitle'] ?? '') ?></p>
    </div>

    <?php if (!empty($content['fallback'] ?? '')): ?>
    <div class="alert alert-warning alert-permanent d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div>
            <strong>Dados estimados.</strong> Estamos buscando dados atualizados da Receita Federal para melhorar estas estatisticas.
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($content['metrics'] ?? [])): ?>
    <div class="row g-3 mb-4">
        <?php foreach ($content['metrics'] as $metric): ?>
        <div class="col-md-4">
            <div class="metric-card">
                <div class="icon"><i class="bi <?= e($metric['icon'] ?? 'bi-graph-up') ?>"></i></div>
                <div class="value"><?= e($metric['value']) ?></div>
                <div class="label"><?= e($metric['label']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($content['insights'] ?? [])): ?>
    <div class="row mb-4">
        <div class="col-lg-4">
            <h5 class="mb-3"><i class="bi bi-lightbulb text-warning me-2"></i>Insights</h5>
            <?php foreach ($content['insights'] as $insight): ?>
            <div class="insight-card">
                <i class="bi bi-check-circle text-warning me-2"></i>
                <?= e($insight) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="col-lg-8">
    <?php else: ?>
    <div class="row">
        <div class="col-12">
    <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover data-table-comparison mb-0">
                            <thead>
                                <tr>
                                    <?php foreach ($content['headers'] ?? [] as $header): ?>
                                    <th><?= e($header) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($content['data'] ?? [] as $row): ?>
                                <?php 
                                $isHighlight = false;
                                if (isset($content['highlight']) && isset($row['pais']) && strtolower($row['pais']) === $content['highlight']) {
                                    $isHighlight = true;
                                }
                                ?>
                                <tr class="<?= $isHighlight ? 'highlight-row' : '' ?>">
                                    <?php if ((($content['type'] ?? '') ?? '') === 'ranking_cnae'): ?>
                                        <td><span class="badge bg-secondary"><?= $row['ranking'] ?? '' ?></span></td>
                                        <td><code><?= e($row['cnae'] ?? '') ?></code></td>
                                        <td><?= e($row['descricao'] ?? '') ?></td>
                                        <td><span class="badge bg-info"><?= e($row['setor'] ?? '') ?></span></td>
                                        <td><?= number_format($row['num_empresas'] ?? 0, 0, ',', '.') ?></td>
                                        <td><span class="badge bg-success"><?= number_format($row['participacao'] ?? 0, 1, ',', '.') ?>%</span></td>
                                    <?php elseif (($content['type'] ?? '') === 'ranking_states'): ?>
                                        <td><span class="badge bg-secondary"><?= $row['ranking'] ?? '' ?></span></td>
                                        <td><?= e($row['estado'] ?? '') ?> <span class="badge bg-light text-body ms-1"><?= e($row['uf'] ?? '') ?></span></td>
                                        <td><?= number_format($row['empresas'] ?? 0, 0, ',', '.') ?></td>
                                        <td><span class="badge bg-success"><?= number_format($row['participacao'] ?? 0, 1, ',', '.') ?>%</span></td>
                                        <td><?= number_format($row['cidades'] ?? 0, 0, ',', '.') ?></td>
                                        <td>R$ <?= number_format($row['receita_media'] ?? 0, 0, ',', '.') ?></td>
                                    <?php elseif (($content['type'] ?? '') === 'ranking_cities'): ?>
                                        <td><span class="badge bg-secondary"><?= $row['ranking'] ?? '' ?></span></td>
                                        <td><?= e($row['cidade'] ?? '') ?> <span class="badge bg-light text-body ms-1"><?= e($row['uf'] ?? '') ?></span></td>
                                        <td><?= number_format($row['empresas'] ?? 0, 0, ',', '.') ?></td>
                                        <td>R$ <?= number_format($row['receita_media'] ?? 0, 0, ',', '.') ?></td>
                                    <?php elseif (($content['type'] ?? '') === 'ranking'): ?>
                                        <td><span class="badge bg-secondary"><?= $row['ranking'] ?? '' ?></span></td>
                                        <td><?= e($row['estado'] ?? $row['cidade'] ?? '') ?> <span class="badge bg-light text-body ms-1"><?= e($row['uf'] ?? '') ?></span></td>
                                        <td><?= number_format($row['empresas'] ?? 0, 0, ',', '.') ?></td>
                                        <td><span class="badge bg-success">+<?= number_format($row['crescimento'] ?? 0, 1, ',', '.') ?>%</span></td>
                                        <td><?= number_format($row['pib_participacao'] ?? $row['faturamento_medio'] ?? 0, 1, ',', '.') ?><?= isset($row['pib_participacao']) ? '%' : '' ?></td>
                                        <td><?= isset($row['populacao']) ? number_format($row['populacao'], 0, ',', '.') : number_format($row['tempo_abertura'], 0, ',', '.') . ' dias / R$ ' . number_format($row['custo_abertura'], 0, ',', '.') ?></td>
                                    <?php elseif (($content['type'] ?? '') === 'comparison_international'): ?>
                                        <td><?= e($row['pais']) ?></td>
                                        <td><span class="badge bg-<?= $row['ranking'] <= 5 ? 'danger' : ($row['ranking'] <= 20 ? 'warning' : 'success') ?>"><?= number_format($row['carga_tributaria'], 1, ',', '.') ?>%</span></td>
                                        <td><?= e($row['ranking']) ?>º</td>
                                        <td class="small text-muted"><?= e($row['nota']) ?></td>
                                    <?php elseif (($content['type'] ?? '') === 'comparison_table'): ?>
                                        <td><?= e($row['faturamento']) ?></td>
                                        <td><?= e($row['simples']) ?></td>
                                        <td><?= e($row['presumido']) ?></td>
                                        <td><span class="badge bg-<?= $row['recomendado'] === 'Simples' ? 'primary' : ($row['recomendado'] === 'Lucro Presumido' ? 'success' : 'secondary') ?>"><?= e($row['recomendado']) ?></span></td>
                                    <?php elseif (($content['type'] ?? '') === 'cost_comparison'): ?>
                                        <td><?= e($row['estado']) ?></td>
                                        <td><span class="badge bg-light text-body"><?= e($row['uf']) ?></span></td>
                                        <td><?= e($row['tempo']) ?> dias</td>
                                        <td>R$ <?= number_format($row['custo'], 0, ',', '.') ?></td>
                                        <td class="small text-muted"><?= e($row['nota']) ?></td>
                                    <?php elseif (($content['type'] ?? '') === 'table_comparison'): ?>
                                        <td><code><?= e($row['cnae'] ?? '') ?></code></td>
                                        <td><?= e($row['descricao'] ?? '') ?></td>
                                        <td><span class="badge bg-info"><?= e($row['setor'] ?? '') ?></span></td>
                                        <td>R$ <?= number_format($row['capital_medio'] ?? $row['faturamento_medio'] ?? 0, 0, ',', '.') ?></td>
                                        <td><?= number_format($row['num_empresas'] ?? 0, 0, ',', '.') ?></td>
                                        <td>R$ <?= number_format(($row['capital_total'] ?? $row['faturamento_total'] ?? 0) / 1e6, 0, ',', '.') ?> bi</td>
                                    <?php else: ?>
                                        <?php foreach ($row as $value): ?>
                                        <td><?= is_numeric($value) ? number_format($value, 0, ',', '.') : e((string)$value) ?></td>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php if (!empty($content['conclusion'] ?? '')): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="conclusion-box">
                <h5 class="mb-3"><i class="bi bi-lightbulb-fill me-2"></i>Conclusao</h5>
                <p class="mb-0"><?= e($content['conclusion']) ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($content['methodology'] ?? '')): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="alert alert-secondary">
                <h6 class="mb-2"><i class="bi bi-info-circle me-2"></i>Metodologia</h6>
                <p class="mb-0 small"><?= e($content['methodology']) ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($content['considerations'])): ?>
    <div class="row mt-4">
        <div class="col-12">
            <h5 class="mb-3"><i class="bi bi-exclamation-circle text-warning me-2"></i>Pontos de Atencao</h5>
            <ul class="list-group">
                <?php foreach ($content['considerations'] ?? [] as $item): ?>
                <li class="list-group-item">
                    <i class="bi bi-check2 me-2 text-success"></i>
                    <?= e($item) ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($faqs)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3"><i class="bi bi-question-circle text-primary me-2"></i>Perguntas Frequentes</h5>
                    <div class="accordion" id="faqAccordion">
                        <?php foreach ($faqs as $i => $faq): ?>
                        <div class="accordion-item border-0 mb-2">
                            <h6 class="accordion-header">
                                <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>">
                                    <i class="bi bi-question-lg text-primary me-2"></i>
                                    <?= e($faq['question']) ?>
                                </button>
                            </h6>
                            <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    <?= e($faq['answer']) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mt-5">
        <div class="col-12">
            <div class="card bg-light border-0">
                <div class="card-body py-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-1"><i class="bi bi-search me-2 text-primary"></i>Consulte Dados de Empresas</h5>
                            <p class="mb-0 text-muted small">Acesse informacoes detalhadas sobre empresas brasileiras: CNPJ, socios, faturamento estimado e mais.</p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <a href="/" class="btn btn-primary">
                                <i class="bi bi-search me-2"></i>Consultar CNPJ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
