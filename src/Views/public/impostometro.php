<?php declare(strict_types=1);
$contadores = $contadores ?? [];
$federal = $federal ?? [];
$comparativo = $comparativo ?? [];
$categorias = $federal['categorias'] ?? [];
$totalArrecadado = $federal['total_arrecadado'] ?? 0;
$totalFormatado = $federal['total_formatado'] ?? null;
?>

<style>
.impostometro-hero {
    background: linear-gradient(135deg, #0d6e6e 0%, var(--brand) 50%, #14b8a6 100%);
    position: relative;
    overflow: hidden;
}
.impostometro-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.stat-card {
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 12px;
    padding: 12px 16px;
    text-align: center;
    backdrop-filter: blur(10px);
}
.stat-card-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: rgba(255,255,255,0.8);
    letter-spacing: 0.5px;
}
.stat-card-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: white;
}
.tax-card {
    border: none;
    border-radius: 16px;
    transition: transform 0.2s, box-shadow 0.2s;
}
.tax-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg) !important;
}
.metric-box {
    background: var(--bg-gradient-end);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}
.metric-value {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--brand), #14b8a6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.metric-label {
    font-size: 0.85rem;
    color: var(--text-muted);
    font-weight: 500;
}
.progress-section {
    background: var(--bg-gradient-end);
    border-radius: 12px;
    padding: 16px;
}
.region-badge {
    font-size: 0.65rem;
    padding: 3px 8px;
}
.country-card {
    background: var(--surface);
    border-radius: 10px;
    padding: 12px;
    height: 100%;
    border: 1px solid var(--border);
    transition: all 0.2s;
}
.country-card:hover {
    border-color: var(--brand);
    box-shadow: 0 4px 12px rgba(20,184,166,0.15);
}
.country-card.highlight {
    border: 2px solid var(--warning);
    background: var(--warning-bg);
}
.impostometro-sobre {
    background: linear-gradient(135deg, var(--bg-gradient-end) 0%, var(--surface-alt) 100%);
}
</style>

<div class="row g-4 mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/">Inicio</a></li>
                <li class="breadcrumb-item active">Impostometro</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (!empty($federal['oficial'])): ?>
<?php 
$mesesInfo = $federal['meses_informados'] ?? 0;
$mesesNomes = ['', 'Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
?>
<div class="alert alert-success alert-permanent d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>
    <div>
        <strong>Dados oficiais da Receita Federal.</strong> 
        <?= e($mesesNomes[$mesesInfo] ?? '') ?> de <?= e($federal['periodo'] ?? date('Y')) ?>.
        <a href="https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/relatorios/arrecadacao-federal/<?= e($federal['periodo'] ?? date('Y')) ?>" target="_blank" rel="noopener" class="alert-link ms-1">
            Ver relatorios <i class="bi bi-box-arrow-up-right small"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="impostometro-hero rounded-4 p-4 p-md-5">
            <div class="position-relative">
                <div class="row align-items-center">
                    <div class="col-lg-8 text-center text-lg-start">
                        <h1 class="h2 mb-2 text-white fw-bold">
                            <i class="bi bi-cash-stack me-2"></i>Impostometro do Brasil
                        </h1>
                        <p class="text-white-50 mb-0 d-none d-sm-block">
                            Acompanhe em tempo real a arrecadacao de impostos no Brasil
                        </p>
                    </div>
                    <div class="col-lg-4 text-center text-lg-end mt-4 mt-lg-0">
                        <?php if (!empty($federal['oficial'])): ?>
                            <span class="badge bg-success px-3 py-2">
                                <i class="bi bi-check-circle me-1"></i>Dados Oficiais
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center my-4">
                    <p class="text-white-50 mb-1" style="font-size: 0.85rem;">Total Arrecadado em <?= e($federal['periodo'] ?? date('Y')) ?></p>
                    <h2 class="display-3 display-md-2 fw-bold text-white mb-1">
                        <?= e($totalFormatado['simbolo'] ?? 'R$') ?> <?= e($totalFormatado['curto'] ?? number_format($totalArrecadado, 2, ',', '.')) ?>
                    </h2>
                    <?php if (!empty($totalFormatado) && isset($totalFormatado['completo'])): ?>
                        <p class="text-white-50 mb-0" style="font-size: 0.8rem;">
                            (<?= e($totalFormatado['simbolo'] ?? 'R$') ?> <?= e($totalFormatado['completo']) ?>)
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="row g-2 g-md-3 justify-content-center">
                    <div class="col-auto">
                        <div class="stat-card">
                            <div class="stat-card-label">Por Segundo</div>
                            <div class="stat-card-value"><?= e($contadores['por_segundo_formatado']['curto'] ?? 'R$ ' . number_format($contadores['por_segundo'] ?? 0, 0, ',', '.')) ?></div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="stat-card">
                            <div class="stat-card-label">Por Minuto</div>
                            <div class="stat-card-value"><?= e($contadores['por_minuto_formatado']['curto'] ?? 'R$ ' . number_format($contadores['por_minuto'] ?? 0, 0, ',', '.')) ?></div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="stat-card">
                            <div class="stat-card-label">Por Hora</div>
                            <div class="stat-card-value"><?= e($contadores['por_hora_formatado']['curto'] ?? 'R$ ' . number_format($contadores['por_hora'] ?? 0, 0, ',', '.')) ?></div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="stat-card">
                            <div class="stat-card-label">Por Dia</div>
                            <div class="stat-card-value"><?= e($contadores['por_dia_formatado']['curto'] ?? 'R$ ' . number_format($contadores['por_dia'] ?? 0, 0, ',', '.')) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="metric-box h-100">
            <div class="metric-value"><?= e($contadores['por_pessoa_formatado']['curto'] ?? 'R$ ' . number_format($contadores['imposto_medio_pessoa'] ?? 0, 0, ',', '.')) ?></div>
            <div class="metric-label">Imposto por Brasileiro</div>
            <hr class="my-3">
            <div class="d-flex flex-column gap-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Dias trabalhando para impostos</span>
                    <span class="badge bg-warning text-body"><?= e((string)($contadores['dias_trabalhados_impostos'] ?? 0)) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Progresso do ano</span>
                    <span class="badge bg-success"><?= number_format($contadores['percentual_ano'] ?? 0, 1, ',', '.') ?>%</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Dias restantes</span>
                    <span class="badge bg-secondary"><?= e((string)($contadores['dias_fim_ano'] ?? 0)) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Total ano/pessoa</span>
                    <span class="badge bg-primary">R$ <?= number_format($contadores['por_pessoa'] ?? 0, 0, ',', '.') ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm tax-card h-100">
            <div class="card-body p-4">
                <h3 class="h5 mb-4">
                    <i class="bi bi-pie-chart me-2 text-teal"></i>
                    Arrecadacao por Tipo de Imposto
                </h3>
                
                <?php if (!empty($categorias)): ?>
                    <div class="row g-3 mb-4">
                        <?php 
                        $colors = ['primary', 'success', 'warning', 'info', 'danger', 'secondary'];
                        $icons = ['bi-file-earmark-text', 'bi-people', 'bi-box', 'bi-cash', 'bi-globe-americas', 'bi-three-dots'];
                        $i = 0;
                        foreach ($categorias as $cat): 
                            $color = $colors[$i % count($colors)];
                            $icon = $icons[$i % count($icons)];
                            $i++;
                        ?>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center p-3 bg-light rounded-3">
                                    <div class="me-3">
                                        <span class="badge bg-<?= e($color) ?> bg-opacity-10 text-<?= e($color) ?> p-2 rounded-3">
                                            <i class="<?= e($icon) ?>"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="small text-muted text-uppercase" style="font-size: 0.7rem;"><?= e($cat['nome'] ?? 'N/A') ?></div>
                                        <div class="fw-bold"><?= e($cat['valor_formatado']['curto'] ?? 'R$ ' . number_format($cat['valor'] ?? 0, 2, ',', '.')) ?></div>
                                    </div>
                                    <div>
                                        <span class="badge bg-<?= e($color) ?>"><?= number_format($cat['percentual'] ?? 0, 1, ',', '.') ?>%</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="progress-section">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small fw-bold text-muted">Distribuicao Percentual</span>
                        </div>
                        <div class="progress" style="height: 20px; border-radius: 10px;">
                            <?php 
                            $i = 0;
                            foreach ($categorias as $cat): 
                                $width = $cat['percentual'] ?? 0;
                                if ($width > 0):
                                    $colorClass = ['bg-primary', 'bg-success', 'bg-warning', 'bg-info', 'bg-danger', 'bg-secondary'];
                                    $color = $colorClass[$i % count($colorClass)];
                            ?>
                                <div class="progress-bar <?= e($color) ?>" style="width: <?= e((string)$width) ?>%" 
                                     title="<?= e($cat['nome'] ?? '') ?>: <?= e((string)($cat['percentual'] ?? 0)) ?>%"></div>
                            <?php 
                                $i++;
                                endif;
                            endforeach; 
                            ?>
                        </div>
                        <div class="d-flex flex-wrap gap-3 mt-2">
                            <?php 
                            $i = 0;
                            foreach ($categorias as $cat): 
                                $colorClass = ['text-primary', 'text-success', 'text-warning', 'text-info', 'text-danger', 'text-secondary'];
                                $color = $colorClass[$i % count($colorClass)];
                            ?>
                                <span class="small <?= e($color) ?>">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>
                                    <?= e($cat['nome'] ?? '') ?>: <?= number_format($cat['percentual'] ?? 0, 1, ',', '.') ?>%
                                </span>
                            <?php 
                                $i++;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Dados detalhados ainda nao disponiveis para este periodo.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm tax-card h-100">
            <div class="card-body p-4">
                <h3 class="h5 mb-3">
                    <i class="bi bi-graph-up me-2 text-teal"></i>
                    Evolucao da Arrecadacao
                </h3>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0 small">
                        <thead>
                            <tr class="table-light">
                                <th class="border-0">Ano</th>
                                <th class="text-end border-0">Arrecadacao</th>
                                <th class="text-end border-0">Cresc.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evolucao ?? [] as $dado): ?>
                                <tr>
                                    <td class="fw-bold"><?= e((string)$dado['ano']) ?></td>
                                    <td class="text-end fw-bold text-success">
                                        R$ <?= e(number_format($dado['arrecadacao'] / 1e9, 1, ',', '.')) ?> bi
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-<?= $dado['crescimento'] >= 0 ? 'success' : 'danger' ?> py-1 px-2">
                                            <?= ($dado['crescimento'] >= 0 ? '+' : '') ?><?= e(number_format($dado['crescimento'], 1, ',', '.')) ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm tax-card h-100">
            <div class="card-body p-4">
                <h3 class="h5 mb-3">
                    <i class="bi bi-calendar-check me-2 text-teal"></i>
                    Historico por Ano
                </h3>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0 small">
                        <thead>
                            <tr class="table-light">
                                <th class="border-0">Ano</th>
                                <th class="text-end border-0">Arrecadacao</th>
                                <th class="text-end border-0 d-none d-sm-table-cell">Cresc. PIB</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($comparativo as $ano => $dados): ?>
                                <tr>
                                    <td class="fw-bold"><?= e((string)$ano) ?></td>
                                    <td class="text-end fw-bold text-success">
                                        R$ <?= e(number_format(($dados['valor'] ?? 0) / 1e9, 1, ',', '.')) ?> bi
                                    </td>
                                    <td class="text-end text-muted d-none d-sm-table-cell">
                                        <?php $pibVal = $dados['pib'] ?? 0; ?>
                                        <?= ($pibVal >= 0 ? '+' : '') ?><?= e(number_format($pibVal, 1, ',', '.')) ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm tax-card h-100">
            <div class="card-body p-4">
                <h3 class="h5 mb-3">
                    <i class="bi bi-geo-alt me-2 text-teal"></i>
                    Arrecadacao por Estado
                </h3>
                
                <?php 
                $topEstados = array_slice(($porEstado['estados'] ?? []), 0, 15);
                $regioes = [
                    'Sudeste' => [],
                    'Sul' => [],
                    'Nordeste' => [],
                    'Centro-Oeste' => [],
                    'Norte' => [],
                ];
                foreach (($porEstado['estados'] ?? []) as $estado) {
                    $regiao = $estado['regiao'] ?? 'N/A';
                    if (isset($regioes[$regiao])) {
                        $regioes[$regiao][] = $estado;
                    }
                }
                ?>
                
                <div class="row g-2">
                    <?php foreach ($topEstados as $i => $estado): ?>
                        <?php 
                        $colors = ['primary', 'success', 'info', 'warning', 'secondary', 'danger', 'dark', 'teal', 'orange', 'purple', 'pink', 'cyan', 'lime', 'amber', 'indigo'];
                        $color = $colors[$i % count($colors)];
                        ?>
                        <div class="col-6 col-md-4">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge bg-<?= $color ?> me-2 region-badge">
                                    <?= e($estado['sigla']) ?>
                                </span>
                                <div class="flex-grow-1">
                                    <div class="progress mb-0" style="height: 8px;">
                                        <div class="progress-bar bg-<?= $color ?>" style="width: <?= e((string)min($estado['participacao'], 100)) ?>%"></div>
                                    </div>
                                </div>
                                <span class="ms-2 text-muted small" style="min-width: 45px;">
                                    <?= e(number_format($estado['participacao'], 0, ',', '.')) ?>%
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <hr class="my-3">
                
                <div class="row g-2">
                    <?php foreach ($regioes as $nomeRegiao => $estados): ?>
                        <?php if (empty($estados)) continue; ?>
                        <div class="col-6 col-md-4">
                            <div class="small text-muted text-uppercase mb-2 fw-bold" style="font-size: 0.65rem;">
                                <?= e($nomeRegiao) ?>
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($estados as $estado): ?>
                                    <span class="badge bg-light text-muted border region-badge">
                                        <?= e($estado['sigla']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm tax-card h-100">
            <div class="card-body p-4">
                <h3 class="h5 mb-3">
                    <i class="bi bi-globe-americas me-2 text-teal"></i>
                    Carga Tributaria - Comparativo
                </h3>
                
                <div class="row g-2">
                    <?php 
                    $topPaises = array_slice($internacional ?? [], 0, 9);
                    foreach ($topPaises as $pais): 
                        $isBrasil = $pais['pais'] === 'Brasil';
                        $badgeColor = $pais['carga_tributaria'] > 35 ? 'danger' : ($pais['carga_tributaria'] > 25 ? 'warning' : 'success');
                    ?>
                        <div class="col-4 col-md-3">
                            <div class="country-card <?= $isBrasil ? 'highlight' : '' ?>">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="fw-bold small"><?= e($pais['pais']) ?></span>
                                    <?php if ($isBrasil): ?>
                                        <i class="bi bi-flag-fill text-warning"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="badge bg-<?= $badgeColor ?> mb-1">
                                    <?= e(number_format($pais['carga_tributaria'], 0, ',', '.')) ?>%
                                </div>
                                <div class="text-muted" style="font-size: 0.65rem;">
                                    PIB: <?= e($pais['moeda']) ?> <?= e(number_format($pais['pib'], 0, ',', '.')) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <p class="text-muted text-center mt-3 mb-0 small">
                    Fonte: OCDE, Banco Mundial (% do PIB)
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm impostometro-sobre">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="h5 mb-2">
                            <i class="bi bi-info-circle me-2 text-teal"></i>Sobre o Impostometro
                        </h3>
                        <p class="text-muted mb-2" style="font-size: 0.9rem;">
                            O <strong>Impostometro</strong> permite acompanhar em tempo real a arrecadacao de impostos no Brasil.
                            Os dados sao baseados em informacoes oficiais do governo federal.
                        </p>
                        <p class="text-muted mb-0" style="font-size: 0.85rem;">
                            <i class="bi bi-lightbulb me-1 text-warning"></i>
                            <strong>Curiosidade:</strong> O brasileiro trabalha em media <strong><?= e((string)($contadores['dias_trabalhados_impostos'] ?? 149)) ?> dias</strong> por ano apenas para pagar impostos.
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <a href="https://www.gov.br/receitafederal/pt-br" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Receita Federal
                        </a>
                        <a href="https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/dados-abertos" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm mt-2 mt-md-0 ms-2">
                            <i class="bi bi-database me-1"></i>Dados Abertos
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
