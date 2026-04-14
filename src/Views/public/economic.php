<?php declare(strict_types=1);
$currencies = $currencies ?? [];
$dbUpdatedAt = $dbUpdatedAt ?? null;
$dbFetchedAt = $dbFetchedAt ?? null;
$fromDatabase = $fromDatabase ?? false;
$history = $history ?? [];
$loadTime = $loadTime ?? null;
?>

<div class="row g-4 mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb" class="breadcrumb-container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Indicadores Economicos</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">
                    <i class="bi bi-graph-up-arrow me-2 text-muted"></i>
                    Indicadores Economicos do Brasil
                </h1>
                <p class="text-muted small mb-0">
                    Dados atualizados pelo Banco Central do Brasil (BCB)
                </p>
            </div>
            <div class="text-md-end">
                <span class="badge bg-primary">
                    <i class="bi bi-bank me-1"></i>Fonte: BCB
                </span>
                <?php if ($fromDatabase): ?>
                    <span class="badge bg-success ms-1" title="Dados salvos no banco local">
                        <i class="bi bi-database-check"></i> Cache
                    </span>
                <?php endif; ?>
                <?php if ($loadTime !== null): ?>
                    <span class="badge bg-secondary ms-1" title="Tempo de carregamento">
                        <i class="bi bi-stopwatch me-1"></i><?= $loadTime ?>ms
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($dbUpdatedAt || $dbFetchedAt): ?>
        <div class="alert alert-info alert-permanent d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i>
            <div>
                <strong>Cotacoes PTAX do dia:</strong> <?= e(format_date($dbUpdatedAt)) ?>
                <?php if ($dbFetchedAt): ?>
                    <span class="text-muted ms-2">
                        <i class="bi bi-clock me-1"></i>Salvos em: <?= e(format_datetime($dbFetchedAt)) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <h2 class="h5 mb-3">
            <i class="bi bi-currency-dollar me-2 text-muted"></i>Cotacoes PTAX do Dia
        </h2>

        <?php if (!empty($currencies)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Moeda</th>
                                    <th class="text-end">Compra (R$)</th>
                                    <th class="text-end">Venda (R$)</th>
                                    <th class="text-end d-none d-sm-table-cell">Variacao</th>
                                    <th class="text-end d-none d-md-table-cell">Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currencies as $curr): 
                                    $variacao = $curr['variacao'] ?? null;
                                    $variacaoClass = '';
                                    $variacaoIcon = '';
                                    if ($variacao !== null) {
                                        if ($variacao > 0) {
                                            $variacaoClass = 'text-danger';
                                            $variacaoIcon = '<i class="bi bi-arrow-up"></i>';
                                        } elseif ($variacao < 0) {
                                            $variacaoClass = 'text-success';
                                            $variacaoIcon = '<i class="bi bi-arrow-down"></i>';
                                        } else {
                                            $variacaoClass = 'text-muted';
                                            $variacaoIcon = '<i class="bi bi-dash"></i>';
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="badge bg-<?= $curr['code'] === 'USD' ? 'success' : ($curr['code'] === 'EUR' ? 'primary' : 'secondary') ?> me-2">
                                                <?= e($curr['code']) ?>
                                            </span>
                                            <span class="fw-medium"><?= e($curr['name']) ?></span>
                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            <?= number_format($curr['compra'], 4, ',', '.') ?>
                                        </td>
                                        <td class="text-end">
                                            <?= number_format($curr['venda'], 4, ',', '.') ?>
                                        </td>
                                        <td class="text-end <?= e($variacaoClass) ?> d-none d-sm-table-cell">
                                            <?php if ($variacao !== null): ?>
                                                <?= $variacaoIcon ?> <?= number_format(abs($variacao), 2, ',', '.') ?>%
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-muted d-none d-md-table-cell">
                                            <?= e(format_date($curr['data'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php 
            $mainCurrencies = array_filter($currencies, fn($c) => in_array($c['code'], ['USD', 'EUR', 'GBP']));
            if (!empty($mainCurrencies)):
            ?>
            <h3 class="h5 mb-3">
                <i class="bi bi-star me-2 text-muted"></i>Principais Moedas
            </h3>
            <div class="row g-3 mb-4">
                <?php foreach ($mainCurrencies as $curr): 
                    $color = $curr['code'] === 'USD' ? 'success' : ($curr['code'] === 'EUR' ? 'primary' : 'info');
                    $variacao = $curr['variacao'] ?? null;
                ?>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-2">
                                    <span class="badge bg-<?= e($color) ?> fs-6"><?= e($curr['code']) ?></span>
                                </div>
                                <div class="h5 mb-1 text-muted fw-semibold"><?= e($curr['name']) ?></div>
                                <div class="display-6 fw-bold text-<?= e($color) ?>">
                                    R$ <?= number_format($curr['venda'], 4, ',', '.') ?>
                                </div>
                                <?php if ($variacao !== null): ?>
                                    <div class="mt-2 <?= $variacao > 0 ? 'text-danger' : ($variacao < 0 ? 'text-success' : 'text-muted') ?>">
                                        <?php if ($variacao > 0): ?>
                                            <i class="bi bi-arrow-up"></i>
                                        <?php elseif ($variacao < 0): ?>
                                            <i class="bi bi-arrow-down"></i>
                                        <?php else: ?>
                                            <i class="bi bi-dash"></i>
                                        <?php endif; ?>
                                        <?= number_format(abs($variacao), 2, ',', '.') ?>% (7 dias)
                                    </div>
                                <?php endif; ?>
                                <div class="small text-muted mt-2">
                                    Compra: R$ <?= number_format($curr['compra'], 4, ',', '.') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-warning mb-4">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Nao foi possivel obter as cotacoes. Tente novamente mais tarde.
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="h6 mb-1">
                            <i class="bi bi-calendar-week me-2"></i>Sobre a PTAX
                        </h3>
                        <p class="small text-muted mb-2">
                            A <strong>PTAX</strong> e a taxa de cambio calculada pelo Banco Central do Brasil,
                            representando o fechamento do dolar comercial no dia anterior. E utilizada como
                            referencia para operacoes financeiras e cambiais.
                        </p>
                        <p class="small text-muted mb-0">
                            <i class="bi bi-clock me-1"></i>
                            <strong>Atualizacao:</strong> Dias uteis, geralmente entre 13h e 14h (horario de Brasilia)
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <a href="https://www.bcb.gov.br/" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Site do BCB
                        </a>
                        <a href="https://www.bcb.gov.br/estatisticasCotacao" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm mt-2 mt-md-0">
                            <i class="bi bi-table me-1"></i>Cotacoes Historicas
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
