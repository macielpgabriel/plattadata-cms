<?php declare(strict_types=1);
use App\Core\Auth;
use App\Core\Csrf;

$title = $title ?? "Detalhes da Localidade";
$ibgeCode = $municipality['ibge_code'] ?? 0;
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Início</a></li>
        <li class="breadcrumb-item"><a href="/localidades">Estados</a></li>
        <li class="breadcrumb-item"><a href="/localidades/<?= strtolower((string)($municipality['state_uf'] ?? '')) ?>"><?= htmlspecialchars((string)($municipality['state_uf'] ?? '')) ?></a></li>
        <li class="breadcrumb-item active"><?= htmlspecialchars((string)($municipality['name'] ?? '')) ?></li>
    </ol>
</nav>

<div class="row premium-municipality-page">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4 overflow-hidden">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                        <div>
                            <h1 class="display-5 fw-bold mb-1"><?= htmlspecialchars((string)($municipality['name'] ?? '')) ?></h1>
                            <p class="lead text-muted"><?= htmlspecialchars((string)($municipality['state_uf'] ?? '')) ?> - Brasil</p>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if (!empty($_SESSION['user_id'])): ?>
                                <form action="/localidades/<?= strtolower((string)($municipality['state_uf'] ?? '')) ?>/<?= htmlspecialchars((string)($municipality['slug'] ?? '')) ?>/atualizar" method="POST" class="d-inline">
                                    <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary shadow-sm sync-trigger">
                                        <i class="bi bi-arrow-clockwise"></i> Atualizar Dados
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <hr>
                    
                    <div class="row g-2 g-md-3 mt-4">
                        <!-- População -->
                        <div class="col-6 col-md-4 col-lg">
                            <div class="p-3 bg-blue-subtle rounded-4 h-100 border border-primary border-opacity-10 hover-lift shadow-sm" title="Dados do IBGE">
                                <i class="bi bi-people-fill text-primary mb-2 d-block fs-4"></i>
                                <small class="text-uppercase text-muted d-block mb-1 fw-bold label-metric">População</small>
                                <?php if (!empty($municipality['population'])): ?>
                                    <span class="h6 fw-bold mb-0 text-body d-block text-center"><?= number_format((float)$municipality['population'], 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="h6 fw-bold mb-0 text-muted d-block text-center" title="API do IBGE indisponível">-</span>
                                <?php endif; ?>
                                 <?php if (!empty($municipality['population_male']) || !empty($municipality['population_female'])): ?>
                                <div class="mt-2 pt-2 border-top border-primary border-opacity-10">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-primary"><i class="bi bi-gender-male me-1"></i>Homens</span>
                                        <span class="fw-medium"><?= number_format((int)($municipality['population_male'] ?? 0), 0, ',', '.') ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-danger"><i class="bi bi-gender-female me-1"></i>Mulheres</span>
                                        <span class="fw-medium"><?= number_format((int)($municipality['population_female'] ?? 0), 0, ',', '.') ?></span>
                                    </div>
                                    <div class="mt-2">
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-primary" style="width: <?= $municipality['population_male_percent'] ?? 50 ?>%"></div>
                                            <div class="progress-bar bg-danger" style="width: <?= $municipality['population_female_percent'] ?? 50 ?>%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between small text-muted mt-1">
                                            <span><?= $municipality['population_male_percent'] ?? 0 ?>%</span>
                                            <span><?= $municipality['population_female_percent'] ?? 0 ?>%</span>
                                        </div>
                                    </div>
                                </div>
                                <?php elseif (!empty($municipality['population'])): ?>
                                <?php
                                $popTotal = (int) $municipality['population'];
                                $maleEst = round($popTotal * 0.489);
                                $femaleEst = $popTotal - $maleEst;
                                ?>
                                <div class="mt-2 pt-2 border-top border-primary border-opacity-10">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-primary"><i class="bi bi-gender-male me-1"></i>Homens</span>
                                        <span class="fw-medium"><?= number_format($maleEst, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-danger"><i class="bi bi-gender-female me-1"></i>Mulheres</span>
                                        <span class="fw-medium"><?= number_format($femaleEst, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="mt-2">
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-primary" style="width: 48.9%"></div>
                                            <div class="progress-bar bg-danger" style="width: 51.1%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between small text-muted mt-1">
                                            <span>48.9%</span>
                                            <span>51.1%</span>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block text-center mt-2 text-xxs">
                                        <i class="bi bi-info-circle me-1"></i>Estimativa baseada na média nacional
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Faixas Etárias -->
                        <?php if (!empty($ageGroups) && is_array($ageGroups)): ?>
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                                    <h2 class="h5 mb-0 fw-bold"><i class="bi bi-person-raised-hand me-2 text-primary"></i>Distribuição por Idade</h2>
                                </div>
                                <div class="card-body px-4 pb-4">
                                    <?php
                                    $totalAgeGroups = array_sum(array_column($ageGroups, 'value'));
                                    $young = ['0', '1', '2', '3', '4']; // 0-29 anos
                                    $workingAge = ['5', '6']; // 30-49 anos
                                    $elderly = ['7', '8', '9', '10']; // 50+ anos
                                    
                                    $youngSum = 0;
                                    $workingSum = 0;
                                    $elderlySum = 0;
                                    
                                    foreach ($ageGroups as $id => $group) {
                                        if (in_array($id, $young)) $youngSum += $group['value'];
                                        elseif (in_array($id, $workingAge)) $workingSum += $group['value'];
                                        elseif (in_array($id, $elderly)) $elderlySum += $group['value'];
                                    }
                                    ?>
                                    <div class="row mb-4">
                                        <div class="col-md-4 text-center">
                                            <span class="badge bg-success-subtle text-success border px-3 py-2">Jovens (0-29)</span>
                                            <div class="h4 fw-bold mt-2"><?= $totalAgeGroups > 0 ? round(($youngSum / $totalAgeGroups) * 100) : 0 ?>%</div>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <span class="badge bg-primary-subtle text-primary border px-3 py-2">Adultos (30-49)</span>
                                            <div class="h4 fw-bold mt-2"><?= $totalAgeGroups > 0 ? round(($workingSum / $totalAgeGroups) * 100) : 0 ?>%</div>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <span class="badge bg-warning-subtle text-warning border px-3 py-2">Idosos (50+)</span>
                                            <div class="h4 fw-bold mt-2"><?= $totalAgeGroups > 0 ? round(($elderlySum / $totalAgeGroups) * 100) : 0 ?>%</div>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Faixa Etária</th>
                                                    <th class="text-end">População</th>
                                                    <th class="text-end" style="width: 80px;">%</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ageGroups as $group): ?>
                                                <?php if ($group['value'] > 0): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($group['label']) ?></td>
                                                    <td class="text-end"><?= number_format($group['value'], 0, ',', '.') ?></td>
                                                    <td class="text-end"><?= $totalAgeGroups > 0 ? number_format(($group['value'] / $totalAgeGroups) * 100, 1, ',', '.') : 0 ?>%</td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="small text-muted mb-0 text-center mt-2">
                                        <i class="bi bi-info-circle me-1"></i>Fonte: IBGE - Census 2022
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- PIB Total -->
                        <div class="col-6 col-md-4 col-lg">
                            <div class="p-3 bg-green-subtle rounded-4 h-100 border border-success border-opacity-10 hover-lift shadow-sm" title="Dados do IBGE">
                                <i class="bi bi-cash-stack text-success mb-2 d-block fs-4"></i>
                                <small class="text-uppercase text-muted d-block mb-1 fw-bold label-metric">PIB Total</small>
                                <?php if (isset($municipality['gdp']) && $municipality['gdp'] > 0): ?>
                                    <span class="h6 fw-bold mb-0 text-body d-block text-center">
                                        R$ <?= ($municipality['gdp'] >= 1000000 ? number_format($municipality['gdp'] / 1e6, 1, ',', '.') . ' Mi' : number_format($municipality['gdp'] / 1e3, 0, ',', '.') . ' mil') ?>
                                    </span>
                                <?php elseif (!empty($municipality['gdp_per_capita']) && !empty($municipality['population'])): ?>
                                    <?php
                                    $gdpEstimated = $municipality['gdp_per_capita'] * $municipality['population'];
                                    ?>
                                    <span class="h6 fw-bold mb-0 text-body d-block text-center">
                                        R$ <?= ($gdpEstimated >= 1000000 ? number_format($gdpEstimated / 1e6, 1, ',', '.') . ' Mi' : number_format($gdpEstimated / 1e3, 0, ',', '.') . ' mil') ?>
                                    </span>
                                    <span class="badge bg-warning text-dark mt-1" title="Estimativa calculada">Estimado</span>
                                <?php elseif (!empty($municipality['population'])): ?>
                                    <?php
                                    $avgGdpPerCapita = 45000; // Média nacional aproximada
                                    $gdpEstimated = $municipality['population'] * $avgGdpPerCapita;
                                    ?>
                                    <span class="h6 fw-bold mb-0 text-body d-block text-center">
                                        R$ <?= ($gdpEstimated >= 1000000 ? number_format($gdpEstimated / 1e6, 1, ',', '.') . ' Mi' : number_format($gdpEstimated / 1e3, 0, ',', '.') . ' mil') ?>
                                    </span>
                                    <span class="badge bg-warning text-dark mt-1" title="Estimativa baseada na média nacional">Estimado</span>
                                <?php else: ?>
                                    <span class="h6 fw-bold mb-0 text-muted d-block text-center">-</span>
                                <?php endif; ?>
                                <?php if ((!isset($municipality['gdp']) || $municipality['gdp'] <= 0) && !empty($municipality['population'])): ?>
                                <small class="text-muted d-block text-center mt-2 small text-xxs">
                                    <i class="bi bi-info-circle me-1"></i>Baseado na população × média nacional
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- PIB Per Capita -->
                        <div class="col-6 col-md-4 col-lg">
                            <div class="p-3 bg-indigo-subtle rounded-4 h-100 border border-primary border-opacity-10 hover-lift shadow-sm">
                                <i class="bi bi-person-badge-fill text-indigo mb-2 d-block fs-4"></i>
                                <small class="text-uppercase text-muted d-block mb-1 fw-bold label-metric">PIB Per Capita</small>
                                <?php if (!empty($municipality['gdp_per_capita'])): ?>
                                    <span class="h6 fw-bold mb-0 text-body d-block text-center">
                                        R$ <?= number_format((float)$municipality['gdp_per_capita'], 0, ',', '.') ?>
                                    </span>
                                <?php elseif (!empty($municipality['gdp']) && !empty($municipality['population']) && $municipality['population'] > 0): ?>
                                    <?php $gdpPerCapitaCalc = $municipality['gdp'] * 1000 / $municipality['population']; ?>
                                    <span class="h6 fw-bold mb-0 text-body d-block text-center">
                                        R$ <?= number_format($gdpPerCapitaCalc, 0, ',', '.') ?>
                                    </span>
                                    <span class="badge bg-warning text-dark mt-1" title="Calculado a partir do PIB Total">Calculado</span>
                                <?php elseif (!empty($municipality['population'])): ?>
                                    <span class="h6 fw-bold mb-0 text-body d-block text-center">
                                        R$ 45.000
                                    </span>
                                    <span class="badge bg-warning text-dark mt-1" title="Média nacional">Média BR</span>
                                <?php else: ?>
                                    <span class="h6 fw-bold mb-0 text-muted d-block text-center">-</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Frota Veicular -->
                        <div class="col-6 col-md-6 col-lg">
                            <?php if (!empty($vehicleTypes)): ?>
                            <div class="p-3 bg-teal-subtle rounded-4 h-100 border border-success border-opacity-10 hover-lift shadow-sm">
                                <div class="text-center mb-2">
                                    <i class="bi bi-truck text-teal d-block fs-4 mb-1"></i>
                                    <small class="text-uppercase text-muted d-block fw-bold label-metric">Frota Veicular</small>
                                    <?php if (!empty($vehicleTypesEstimated)): ?>
                                    <span class="badge bg-warning text-dark ms-1" title="Dados estimados baseados na média nacional">Estimado</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex flex-wrap gap-1 justify-content-center">
                                    <?php 
                                    $topTypes = array_slice($vehicleTypes, 0, 4, true);
                                    foreach ($topTypes as $type => $data): 
                                        if ($data['count'] > 0):
                                    ?>
                                        <span class="badge bg-teal bg-opacity-20 text-teal border border-teal border-opacity-25">
                                            <?= htmlspecialchars($type) ?>: <?= number_format($data['count'], 0, ',', '.') ?>
                                        </span>
                                    <?php endif; endforeach; ?>
                                </div>
                                <?php if (!empty($vehicleTypesEstimated)): ?>
                                <small class="text-muted d-block text-center mt-2 small">
                                    <i class="bi bi-info-circle me-1"></i>Estimativa baseada na distribuição média nacional (IBGE/DENATRAN)
                                </small>
                                <?php endif; ?>
                                <?php if (count($vehicleTypes) > 4): ?>
                                <button class="btn btn-sm btn-link text-teal p-0 mt-2 w-100" type="button" data-bs-toggle="collapse" data-bs-target="#vehicleTypesMore">
                                    <small>+<?= count($vehicleTypes) - 4 ?> tipos</small>
                                </button>
                                <div class="collapse" id="vehicleTypesMore">
                                    <div class="d-flex flex-wrap gap-1 justify-content-center mt-2">
                                        <?php foreach (array_slice($vehicleTypes, 4) as $type => $data): ?>
                                            <?php if ($data['count'] > 0): ?>
                                            <span class="badge bg-teal bg-opacity-20 text-teal border border-teal border-opacity-25">
                                                <?= htmlspecialchars($type) ?>: <?= number_format($data['count'], 0, ',', '.') ?>
                                            </span>
                                            <?php endif; endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="p-3 bg-teal-subtle rounded-4 text-center h-100 border border-success border-opacity-10 hover-lift shadow-sm">
                                <i class="bi bi-truck text-teal mb-2 d-block fs-4"></i>
                                <small class="text-uppercase text-muted d-block mb-1 fw-bold label-metric">Frota Veicular</small>
                                <span class="h6 fw-bold mb-0 text-body"><?= ((int)($municipality["vehicle_fleet"] ?? 0) > 0) ? number_format((float)$municipality["vehicle_fleet"], 0, ",", ".") : "-" ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Empresas -->
                        <div class="col-6 col-md-6 col-lg">
                            <div class="p-3 bg-amber-subtle rounded-4 h-100 border border-warning border-opacity-20 hover-lift shadow-sm">
                                <i class="bi bi-building-fill-check text-amber mb-2 d-block fs-4"></i>
                                <small class="text-uppercase text-muted d-block mb-1 fw-bold label-metric">Unidades Locais</small>
                                <?php
                                $companiesMetric = (int)($municipality["business_units"] ?? 0);
                                $isEstimated = false;
                                if ($companiesMetric <= 0) {
                                    $companiesMetric = (int)($companiesTotal ?? 0);
                                }
                                if ($companiesMetric <= 0 && !empty($municipality['population'])) {
                                    $companiesMetric = round($municipality['population'] / 35);
                                    $isEstimated = true;
                                }
                                ?>
                                <span class="h6 fw-bold mb-0 text-body d-block text-center">
                                    <?= $companiesMetric > 0 ? number_format((float)$companiesMetric, 0, ",", ".") : "-" ?>
                                </span>
                                <?php if ($isEstimated): ?>
                                <span class="badge bg-warning text-dark mt-1" title="Estimativa baseada na densidade de empresas nacional">Estimado</span>
                                <small class="text-muted d-block text-center mt-2 small text-xxs">
                                    <i class="bi bi-info-circle me-1"></i>≈ 1 empresa a cada 35 habitantes
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Visualizações -->
                        <div class="col-6 col-md-6 col-lg">
                            <div class="p-3 bg-purple-subtle rounded-4 text-center h-100 border border-purple border-opacity-10 hover-lift shadow-sm">
                                <i class="bi bi-eye-fill text-purple mb-2 d-block fs-4"></i>
                                <small class="text-uppercase text-muted d-block mb-1 fw-bold label-metric">Visualizações</small>
                                <span class="h6 fw-bold mb-0 text-body"><?= number_format((int) ($municipality['views'] ?? 0), 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-2">
                        <h2 class="h5 fw-bold mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Informações Regionais</h2>
                        <ul class="list-group list-group-flush border-top border-bottom">
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                <div>
                                    <i class="bi bi-map me-2 text-muted"></i>
                                    <span class="text-muted small fw-bold text-uppercase">Mesorregião</span>
                                </div>
                                <span class="fw-medium text-end"><?= htmlspecialchars((string)($municipality['mesoregion'] ?? '-')) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                <div>
                                    <i class="bi bi-pin-map me-2 text-muted"></i>
                                    <span class="text-muted small fw-bold text-uppercase">Microrregião</span>
                                </div>
                                <span class="fw-medium text-end"><?= htmlspecialchars((string)($municipality['microregion'] ?? '-')) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                <div>
                                    <i class="bi bi-hash me-2 text-muted"></i>
                                    <span class="text-muted small fw-bold text-uppercase">Código IBGE</span>
                                </div>
                                <span class="fw-bold text-primary"><?= $ibgeCode ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                <div>
                                    <i class="bi bi-telephone-plus me-2 text-muted"></i>
                                    <span class="text-muted small fw-bold text-uppercase">DDD Principal</span>
                                </div>
                                <span class="badge bg-dark px-3 py-2 fs-6 shadow-sm"><?= htmlspecialchars((string)($ddd['ddd'] ?? $municipality['ddd'] ?? '-')) ?></span>
                            </li>
                        </ul>
                    </div>

                    <?php if (!empty($municipalityTaxData)): ?>
                    <div class="mt-4 pt-3 border-top">
                        <h2 class="h5 fw-bold mb-3"><i class="bi bi-cash-stack me-2 text-success"></i>Arrecadação Tributária Estimada</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25 h-100">
                                    <small class="text-uppercase text-success d-block mb-1 small fw-bold label-metric">Arrecadação Municipal</small>
                                    <span class="h4 fw-bold text-success mb-0">
                                        R$ <?= $municipalityTaxData['arrecadacao_formatada']['curto'] ?? '-' ?>
                                    </span>
                                    <p class="small text-muted mt-1 mb-0">
                                        <?= $municipalityTaxData['arrecadacao_formatada']['completo'] ?? '-' ?>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-3 bg-secondary-subtle rounded-3 border h-100 text-center">
                                    <small class="text-uppercase text-muted d-block mb-1 small fw-bold label-metric">% Pop. Estado</small>
                                    <span class="h5 fw-bold mb-0"><?= number_format($municipalityTaxData['proporcao_populacional'] ?? 0, 2, ',', '.') ?>%</span>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-3 bg-secondary-subtle rounded-3 border h-100 text-center">
                                    <small class="text-uppercase text-muted d-block mb-1 small fw-bold label-metric">População</small>
                                    <span class="h5 fw-bold mb-0"><?= $municipalityTaxData['populacao'] > 0 ? number_format($municipalityTaxData['populacao'], 0, ',', '.') : '-' ?></span>
                                </div>
                            </div>
                        </div>
                        <p class="small text-muted mt-2 mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Estimativa baseada na participação populacional. Dados oficiais da Receita Federal disponíveis no <a href="/impostometro">Impostômetro</a>.
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php 
            $agri = (float)($municipality['gdp_agri'] ?? 0);
            $indu = (float)($municipality['gdp_industry'] ?? 0);
            $serv = (float)($municipality['gdp_services'] ?? 0);
            $admn = (float)($municipality['gdp_admin'] ?? 0);
            $totalVab = $agri + $indu + $serv + $admn;
            ?>

            <?php if ($totalVab > 0): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h2 class="h5 mb-0 fw-bold">Composição Econômica (VAB)</h2>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small fw-bold text-success">Agropecuária</span>
                                        <span class="small text-muted"><?= number_format(($agri/$totalVab)*100, 1) ?>%</span>
                                    </div>
                                    <div class="progress progress-thick">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($agri/$totalVab)*100 ?>%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small fw-bold text-primary">Indústria</span>
                                        <span class="small text-muted"><?= number_format(($indu/$totalVab)*100, 1) ?>%</span>
                                    </div>
                                    <div class="progress progress-thick">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= ($indu/$totalVab)*100 ?>%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small fw-bold text-info">Serviços</span>
                                        <span class="small text-muted"><?= number_format(($serv/$totalVab)*100, 1) ?>%</span>
                                    </div>
                                    <div class="progress progress-thick">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?= ($serv/$totalVab)*100 ?>%"></div>
                                    </div>
                                </div>
                                <div class="mb-0">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small fw-bold text-secondary">Adm. Pública</span>
                                        <span class="small text-muted"><?= number_format(($admn/$totalVab)*100, 1) ?>%</span>
                                    </div>
                                    <div class="progress progress-thick">
                                        <div class="progress-bar bg-secondary" role="progressbar" style="width: <?= ($admn/$totalVab)*100 ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5 mt-4 mt-md-0 text-center border-start d-none d-md-block">
                                <p class="text-muted small mb-2">Valor Adicionado Bruto Total</p>
                                <div class="fw-bold fs-3">R$ <?= number_format($totalVab/1e6, 1, ',', '.') ?> <small class="h6 text-muted">Mi</small></div>
                                <p class="small text-muted mt-2">Dados do PIB Municipal refletem a força de cada setor na economia local.</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($ddd) && !empty($ddd['cities'])): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h2 class="h5 mb-0 fw-bold">Cidades com o mesmo DDD (<?= htmlspecialchars((string)$ddd['ddd']) ?>)</h2>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <p class="small text-muted mb-3">O código <?= htmlspecialchars((string)$ddd['ddd']) ?> abrange <?= count($ddd['cities']) ?> municípios em <?= htmlspecialchars((string)($ddd['state'] ?? '')) ?>.</p>
                        <div class="d-flex flex-wrap gap-1 max-height-md">
                            <?php foreach ($ddd['cities'] as $city): ?>
                                <span class="badge bg-secondary-subtle text-muted border fw-normal"><?= htmlspecialchars((string)$city) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-0 fw-bold">Perfil Empresarial Local</h2>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h3 class="h6 text-muted small fw-bold text-uppercase mb-3">Distribuição por Porte</h3>
                            <?php if (empty($companyStats['sizes'])): ?>
                                <p class="small text-muted">Dados insuficientes no cache local.</p>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach ($companyStats['sizes'] as $size): ?>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="small"><?= htmlspecialchars((string)($size['company_size'] ?: 'Não Informado')) ?></span>
                                            <span class="badge bg-secondary-subtle text-muted border"><?= $size['total'] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h3 class="h6 text-muted small fw-bold text-uppercase mb-3">Situação Cadastral</h3>
                            <?php if (empty($companyStats['statuses'])): ?>
                                <p class="small text-muted">Dados insuficientes no cache local.</p>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach ($companyStats['statuses'] as $stat): ?>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="small"><?= htmlspecialchars((string)$stat['status']) ?></span>
                                            <span class="badge bg-secondary-subtle text-muted border"><?= $stat['total'] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-secondary-subtle rounded-3 border">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <small class="text-muted d-block small fw-bold text-uppercase">Capital Social Acumulado</small>
                                        <span class="h4 fw-bold text-success mb-0">R$ <?= number_format((float) ($companyStats['total_capital'] ?? 0), 2, ',', '.') ?></span>
                                    </div>
                                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                                        <p class="small text-muted mb-0">* Baseado nas empresas cadastradas no cache local.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($companies)): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h2 class="h5 mb-0 fw-bold">Empresas Recentes em <?= htmlspecialchars((string)($municipality['name'] ?? '')) ?></h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($companies as $comp): ?>
                                <a href="/empresas/<?= $comp['cnpj'] ?>" class="list-group-item list-group-item-action p-4 border-0 border-bottom">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars((string)($comp['legal_name'] ?? $comp['trade_name'] ?? 'Empresa')) ?></h6>
                                            <div class="text-muted small">
                                                <i class="bi bi-card-text me-1"></i> <?= htmlspecialchars((string)$comp['cnpj']) ?>
                                                <span class="mx-1">•</span>
                                                <i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars((string)($comp['city'] ?? '-')) ?>/<?= htmlspecialchars((string)($comp['state'] ?? '-')) ?>
                                            </div>
                                        </div>
                                        <i class="bi bi-chevron-right text-muted"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0 p-4 text-center">
                        <a href="/empresas/em/<?= strtolower((string)($municipality['state_uf'] ?? '')) ?>/<?= htmlspecialchars((string)($municipality['slug'] ?? '')) ?>" class="btn btn-primary px-4">
                            Ver todas as empresas
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4" id="weather-card-wrapper">
            <div class="card border-0 shadow-sm bg-primary text-white weather-card mb-4 overflow-hidden" style="background-color: #0d9488 !important;">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex justify-content-between align-start">
                        <div>
                            <h2 class="h5 mb-1 fw-bold">Clima Atual</h2>
                            <p class="small opacity-75 mb-0" id="weather-source">Fonte: <?= $weather['source'] ?? 'Open-Meteo' ?></p>
                            <?php 
                            $updateTime = $weather['fetched_at'] ?? ($weather['updated_at'] ?? null);
                            if (!$updateTime) {
                                $updateTime = date('Y-m-d H:i:s');
                            }
                            ?>
                                <p class="small opacity-50 mb-0 text-xxs" id="weather-updated">Atualizado em <?= format_datetime($updateTime) ?></p>
                            <button class="btn btn-sm btn-outline-light mt-2 refresh-weather-btn" data-ibge="<?= $ibgeCode ?>">
                                <i class="bi bi-arrow-clockwise"></i> Atualizar
                            </button>
                        </div>
                        <i class="bi bi-cloud-sun fs-1 opacity-25"></i>
                    </div>

                    <div id="weather-data">
                    <?php if (isset($weather['current']) && is_array($weather['current']) || !empty($weather['forecast'])): ?>
                        <?php 
                        $current = $weather['current'] ?? null;
                        $forecast = $weather['forecast'] ?? [];
                        $today = $forecast[0] ?? null;
                        ?>
                        <div class="mt-4 text-center">
                            <div class="display-3 fw-bold mb-0" id="weather-temp">
                                <?= $current['temp'] ?? $today['max_temp'] ?? '--' ?>°
                            </div>
                            <p class="lead mb-0 fw-medium text-white" id="weather-condition"><?= $current['condition'] ?? 'Indisponível' ?></p>
                        </div>

                        <div class="d-flex justify-content-between mt-4 border-top border-white border-opacity-25 pt-3">
                            <div class="text-center">
                                <small class="d-block opacity-75 text-white">Mín</small>
                                <span class="fw-bold text-white" id="weather-min"><?= $today['min_temp'] ?? '--' ?>°</span>
                            </div>
                            <div class="text-center border-start border-white border-opacity-25 ps-3 pe-3">
                                <small class="d-block opacity-75 text-white">Máx</small>
                                <span class="fw-bold text-white" id="weather-max"><?= $today['max_temp'] ?? '--' ?>°</span>
                            </div>
                            <div class="text-center">
                                <small class="d-block opacity-75 text-white">Sensação</small>
                                <span class="fw-bold text-white" id="weather-feels"><?= $current['feels_like'] ?? '--' ?>°</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mt-4 text-center p-3 border border-white border-opacity-25 rounded-3 bg-white bg-opacity-10">
                            <p class="mb-0 small text-white">Previsão indisponível para este município no momento.</p>
                        </div>
                    <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($forecast)): ?>
                        <div id="weather-forecast" class="mt-4 border-top border-white border-opacity-25 pt-3">
                            <h6 class="small fw-bold text-white mb-3">Próximos Dias</h6>
                            <div class="row row-cols-2 row-cols-md-6 g-2 text-center small">
                                <?php foreach (array_slice($forecast, 1, 6) as $day): ?>
                                    <div class="col">
                                        <div class="p-2 bg-light bg-opacity-25 rounded h-100">
                                            <div class="text-white-50 small mb-1"><?= format_date($day['date'] ?? '', true) ?></div>
                                            <?php if (!empty($day['min_temp']) || !empty($day['max_temp'])): ?>
                                                <div class="text-white fw-bold fs-6">
                                                    <?= $day['min_temp'] ?? '-' ?> / <?= $day['max_temp'] ?? '-' ?>°
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($day['condition'])): ?>
                                                <div class="text-white-50 small"><?= e($day['condition']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($rates)): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h3 class="h6 fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Indicadores Econômicos</h3>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($ratesUpdatedAt)): ?>
                                    <small class="text-muted text-xxs">Atualizado em <?= format_datetime($ratesUpdatedAt) ?></small>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-secondary refresh-rates-btn">
                                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                                </button>
                            </div>
                        </div>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($rates as $currency => $rate): ?>
                                <div class="p-3 bg-secondary-subtle rounded-3 border">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                                <span class="fw-bold text-primary small"><?= $currency ?></span>
                                            </div>
                                            <div>
                                                <small class="text-muted d-block small fw-bold text-uppercase label-metric">Câmbio Comercial</small>
                                                <span class="h6 fw-bold mb-0">R$ <?= number_format($rate['cotacaoVenda'] ?? $rate['venda'] ?? 0, 4, ',', '.') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3">
                            <a href="/indicadores-economicos" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-chart-line me-1"></i> Ver histórico
                            </a>
                        </div>
                        <p class="small text-muted mt-3 mb-0 text-xxs">Dados fornecidos pelo Banco Central do Brasil (BCB).</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($news)): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h3 class="h6 mb-0 fw-bold"><i class="bi bi-newspaper me-2 text-primary"></i>Notícias da Região</h3>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($news as $item): ?>
                                <div class="border-bottom pb-2">
                                    <a href="<?= $item['link'] ?>" target="_blank" rel="nofollow noopener" class="text-decoration-none text-body d-block">
                                        <h6 class="small fw-bold mb-1 hover-primary"><?= htmlspecialchars($item['title']) ?></h6>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted text-xxs"><?= $item['source'] ?></small>
                                            <small class="text-muted text-xxs"><?= format_date($item['pubDate']) ?></small>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 text-center">
                    <i class="bi bi-info-circle text-primary fs-1 mb-3 d-block"></i>
                    <h2 class="h5 fw-bold mb-3">Sobre esta localidade</h2>
                    <p class="text-muted small mb-0">
                        Dados demográficos e econômicos atualizados via IBGE (PIB 2023 / Censo 2022). O clima é monitorado em tempo real via Open-Meteo.
                    </p>
                </div>
            </div>
        </div>
    </div>
