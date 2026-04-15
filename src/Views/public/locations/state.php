<?php declare(strict_types=1); use App\Core\Csrf; ?>
<?php 
$metaTitle = $title;
$metaDescription = $metaDescription ?? ($meta_description ?? null);
$stateName = is_string($state['name'] ?? '') ? $state['name'] : '';
$stateRegion = is_string($state['region'] ?? '') ? $state['region'] : '';
$stateUf = is_string($state['uf'] ?? '') ? $state['uf'] : '';
$stateCapital = is_string($state['capital_city'] ?? '') ? $state['capital_city'] : 'Não Informado';
$statePopulation = !empty($state['population']) ? number_format((float)$state['population'], 0, ',', '.') : '-';
$stateGdp = !empty($state['gdp']) ? (($state['gdp'] / 1000) >= 1 ? number_format($state['gdp'] / 1000, 1, ',', '.') . ' Bi' : number_format((float) $state['gdp'], 0, ',', '.') . ' Mi') : '-';
$stateGdpPerCapita = !empty($state['gdp_per_capita']) ? number_format((float) $state['gdp_per_capita'], 0, ',', '.') : '-';
$stateArea = number_format((float)($state['area_km2'] ?? 0), 2, ',', '.');
$stateCapitalDisplay = !empty($state['capital_city']) ? htmlspecialchars($state['capital_city']) : 'Capital';
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb shadow-sm p-3 bg-white rounded-3 border">
        <li class="breadcrumb-item"><a href="/" class="text-brand text-decoration-none">Home</a></li>
        <li class="breadcrumb-item"><a href="/localidades" class="text-brand text-decoration-none">Localidades</a></li>
        <li class="breadcrumb-item active fw-bold" aria-current="page"><?= htmlspecialchars($stateName) ?></li>
    </ol>
</nav>

<div class="card border-0 shadow-sm mb-5 overflow-hidden border-radius-lg">
        <div class="card-body p-4 p-md-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <span class="badge bg-brand bg-opacity-10 text-brand px-3 py-2 rounded-pill mb-3 fw-bold">Estado da Região <?= htmlspecialchars($stateRegion) ?></span>
                    <h1 class="display-4 fw-bold mb-2"><?= htmlspecialchars($stateName) ?> (<?= $stateUf ?>)</h1>
                    <p class="text-muted lead mb-4">Capital: <?= htmlspecialchars($stateCapital) ?> | Área: <?= $stateArea ?> km²</p>
                    
                    <?php if (!empty($_SESSION['user_id'])): ?>
                    <form method="POST" action="/localidades/<?= strtolower($stateUf) ?>/atualizar" class="mb-3" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='Atualizando...';">
                        <input type="hidden" name="_token" value="<?= e(\App\Core\Csrf::token()) ?>">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-arrow-clockwise me-1"></i> Atualizar Dados
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <div class="row g-3">
                        <div class="col-6 col-sm-3">
                            <div class="p-3 bg-secondary-subtle rounded-3 border text-center">
                                <small class="text-uppercase text-muted d-block mb-1 small fw-bold label-metric">População Total</small>
                                <span class="h5 fw-bold mb-0 text-brand"><?= $statePopulation ?></span>
                                <?php if (!empty($stateGenderData)): ?>
                                <div class="mt-2 pt-2 border-top border-primary border-opacity-10">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-primary"><i class="bi bi-gender-male me-1"></i>Homens</span>
                                        <span class="fw-medium"><?= number_format((int)($stateGenderData['male'] ?? 0), 0, ',', '.') ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-danger"><i class="bi bi-gender-female me-1"></i>Mulheres</span>
                                        <span class="fw-medium"><?= number_format((int)($stateGenderData['female'] ?? 0), 0, ',', '.') ?></span>
                                    </div>
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div class="progress-bar bg-primary" style="width: <?= $stateGenderData['male_percent'] ?? 50 ?>%"></div>
                                        <div class="progress-bar bg-danger" style="width: <?= $stateGenderData['female_percent'] ?? 50 ?>%"></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-3 bg-success-subtle rounded-3 border text-center">
                                <small class="text-uppercase text-muted d-block mb-1 small fw-bold label-metric">PIB Estadual</small>
                                <span class="h5 fw-bold mb-0 text-success">R$ <?= $stateGdp ?></span>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-3 bg-primary-subtle rounded-3 border text-center">
                                <small class="text-uppercase text-muted d-block mb-1 small fw-bold label-metric">PIB Per Capita</small>
                                <span class="h5 fw-bold mb-0 text-primary">R$ <?= $stateGdpPerCapita ?></span>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-3 bg-warning-subtle rounded-3 border text-center">
                                <small class="text-uppercase text-muted d-block mb-1 small fw-bold label-metric">Participação Brasil</small>
                                <span class="h5 fw-bold mb-0 text-warning"><?= isset($arrecadacaoEstado['participacao']) ? number_format($arrecadacaoEstado['participacao'], 1, ',', '.') . '%' : '-' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <?php 
                    $agri = (float)($state['gdp_agri'] ?? 0);
                    $indu = (float)($state['gdp_industry'] ?? 0);
                    $serv = (float)($state['gdp_services'] ?? 0);
                    $admn = (float)($state['gdp_admin'] ?? 0);
                    $totalVab = $agri + $indu + $serv + $admn;
                    ?>
                    <?php if ($totalVab > 0): ?>
                        <div class="p-4 bg-white border rounded-4 shadow-sm" style="position: relative; z-index: 1;">
                            <h2 class="h6 fw-bold mb-4 d-flex align-items-center"><i class="bi bi-pie-chart me-2 text-brand"></i>Força Econômica</h2>
                            <div class="d-flex flex-column gap-3">
                                <div>
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span class="text-muted fw-medium">Serviços & Comércio</span>
                                        <span class="fw-bold"><?= number_format(($serv/$totalVab)*100, 1) ?>%</span>
                                    </div>
                                    <div class="progress progress-thin">
                                        <div class="progress-bar bg-info" style="width: <?= ($serv/$totalVab)*100 ?>%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span class="text-muted fw-medium">Indústria</span>
                                        <span class="fw-bold"><?= number_format(($indu/$totalVab)*100, 1) ?>%</span>
                                    </div>
                                    <div class="progress progress-thin">
                                        <div class="progress-bar bg-primary" style="width: <?= ($indu/$totalVab)*100 ?>%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span class="text-muted fw-medium">Agropecuária</span>
                                        <span class="fw-bold"><?= number_format(($agri/$totalVab)*100, 1) ?>%</span>
                                    </div>
                                    <div class="progress progress-thin">
                                        <div class="progress-bar bg-success" style="width: <?= ($agri/$totalVab)*100 ?>%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span class="text-muted fw-medium">Administração Pública</span>
                                        <span class="fw-bold"><?= number_format(($admn/$totalVab)*100, 1) ?>%</span>
                                    </div>
                                    <div class="progress progress-thin">
                                        <div class="progress-bar bg-secondary" style="width: <?= ($admn/$totalVab)*100 ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-4 bg-white border rounded-4 shadow-sm" style="position: relative; z-index: 1;">
                            <h2 class="h6 fw-bold mb-3 d-flex align-items-center"><i class="bi bi-pie-chart me-2 text-brand"></i>Força Econômica</h2>
                            <?php
                            $gdp = $state['gdp'] ?? null;
                            $gdpPerCapita = $state['gdp_per_capita'] ?? null;
                            $pop = $state['population'] ?? null;
                            $area = $state['area_km2'] ?? null;
                            ?>
                            <div class="d-flex flex-column gap-3">
                                <?php if ($gdp !== null && $gdp > 0): ?>
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3">
                                    <span class="text-muted fw-medium"><i class="bi bi-graph-up me-2"></i>PIB Estimado</span>
                                    <span class="fw-bold">R$ <?= number_format((float) ($gdp ?? 0) / 1000, 0, ',', '.') . ' mi' ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($gdpPerCapita !== null && $gdpPerCapita > 0): ?>
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3">
                                    <span class="text-muted fw-medium"><i class="bi bi-person me-2"></i>PIB per Capita</span>
                                    <span class="fw-bold">R$ <?= number_format((float) ($gdpPerCapita ?? 0), 0, ',', '.') ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($pop !== null && $pop > 0): ?>
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3">
                                    <span class="text-muted fw-medium"><i class="bi bi-people me-2"></i>População</span>
                                    <span class="fw-bold"><?= number_format((float) ($pop ?? 0), 0, ',', '.') ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($area !== null && $area > 0): ?>
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3">
                                    <span class="text-muted fw-medium"><i class="bi bi-aspect-ratio me-2"></i>Área</span>
                                    <span class="fw-bold"><?= number_format((float) ($area ?? 0), 0, ',', '.') ?> km²</span>
                                </div>
                                <?php endif; ?>
                                <?php if ($gdp === null && $gdpPerCapita === null && $pop === null): ?>
                                <p class="mb-0 small text-muted text-center py-2">Dados econômicos detalhados serão atualizados em breve.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($stateVehicleTypes)): ?>
    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-3"><i class="bi bi-truck me-2 text-teal"></i>Tipos de Veículos na Frota</h2>
            <p class="text-muted small mb-3">Distribuição dos principais tipos de veículos registrados no estado</p>
            <div class="row g-2">
                <?php 
                $totalTypes = count($stateVehicleTypes);
                $topTypes = array_slice($stateVehicleTypes, 0, 8, true);
                $totalVehicles = array_sum($topTypes);
                foreach ($topTypes as $type => $count): 
                    $percent = $totalVehicles > 0 ? ($count / $totalVehicles) * 100 : 0;
                ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="p-3 bg-teal bg-opacity-10 rounded-3 border border-teal border-opacity-25 h-100">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <small class="text-muted fw-medium text-truncate" title="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></small>
                                <span class="badge bg-teal rounded-pill"><?= number_format((float) ($percent ?? 0), 0) ?>%</span>
                            </div>
                            <div class="h5 fw-bold text-teal mb-0"><?= number_format((float) ($count ?? 0), 0, ',', '.') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($totalTypes > 8): ?>
                <button class="btn btn-sm btn-link text-teal mt-3 w-100" type="button" data-bs-toggle="collapse" data-bs-target="#moreVehicleTypes">
                    Ver todos os <?= $totalTypes ?> tipos de veículos
                </button>
                <div class="collapse" id="moreVehicleTypes">
                    <div class="row g-2 mt-2">
                        <?php foreach (array_slice($stateVehicleTypes, 8) as $type => $count): 
                            $percent = $totalVehicles > 0 ? ($count / $totalVehicles) * 100 : 0;
                        ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="p-3 bg-secondary-subtle rounded-3 border h-100">
                                    <small class="text-muted d-block mb-1 text-truncate"><?= htmlspecialchars($type) ?></small>
                                    <span class="fw-bold"><?= number_format((float) ($count ?? 0), 0, ',', '.') ?></span>
                                    <small class="text-muted"> (<?= number_format((float) ($percent ?? 0), 1) ?>%)</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($stateAgeGroups) && is_array($stateAgeGroups)): ?>
    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-3"><i class="bi bi-person-raised-hand me-2 text-primary"></i>Distribuição Etária</h2>
            <?php
            $totalAge = array_sum(array_column($stateAgeGroups, 'value'));
            $young = ['0', '1', '2', '3', '4']; // 0-29 anos
            $workingAge = ['5', '6']; // 30-49 anos
            $elderly = ['7', '8', '9', '10']; // 50+ anos
            
            $youngSum = 0;
            $workingSum = 0;
            $elderlySum = 0;
            
            foreach ($stateAgeGroups as $id => $group) {
                if (in_array($id, $young)) $youngSum += $group['value'];
                elseif (in_array($id, $workingAge)) $workingSum += $group['value'];
                elseif (in_array($id, $elderly)) $elderlySum += $group['value'];
            }
            ?>
            <div class="row mb-4">
                <div class="col-md-4 text-center">
                    <span class="badge bg-success-subtle text-success border px-3 py-2">Jovens (0-29)</span>
                    <div class="h4 fw-bold mt-2"><?= $totalAge > 0 ? round(($youngSum / $totalAge) * 100) : 0 ?>%</div>
                </div>
                <div class="col-md-4 text-center">
                    <span class="badge bg-primary-subtle text-primary border px-3 py-2">Adultos (30-49)</span>
                    <div class="h4 fw-bold mt-2"><?= $totalAge > 0 ? round(($workingSum / $totalAge) * 100) : 0 ?>%</div>
                </div>
                <div class="col-md-4 text-center">
                    <span class="badge bg-warning-subtle text-warning border px-3 py-2">Idosos (50+)</span>
                    <div class="h4 fw-bold mt-2"><?= $totalAge > 0 ? round(($elderlySum / $totalAge) * 100) : 0 ?>%</div>
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
                        <?php foreach ($stateAgeGroups as $group): ?>
                        <?php if ($group['value'] > 0): ?>
                        <tr>
                            <td><?= htmlspecialchars($group['label']) ?></td>
                            <td class="text-end"><?= number_format((float) ($group['value'] ?? 0), 0, ',', '.') ?></td>
                            <td class="text-end"><?= $totalAge > 0 ? number_format((float) ($group['value'] ?? 0) / $totalAge * 100, 1, ',', '.') : 0 ?>%</td>
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
    <?php endif; ?>

    <?php if (!empty($capitalWeather)): ?>
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm bg-primary text-white weather-card mb-4 overflow-hidden" style="background-color: #0d9488 !important;" data-bs-theme="dark">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h2 class="h5 mb-1 fw-bold">Clima na Capital</h2>
                            <h3 class="h6 fw-bold mb-0"><?= $stateCapitalDisplay ?></h3>
                            <?php if (!empty($capitalWeather['fetched_at'])): ?>
                                <p class="small opacity-50 mb-0">Atualizado em <?= format_datetime($capitalWeather['fetched_at']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($capitalIbge)): ?>
                            <button class="btn btn-sm btn-outline-light mt-2 refresh-weather-btn" data-ibge="<?= $capitalIbge ?>">
                                <i class="bi bi-arrow-clockwise"></i> Atualizar
                            </button>
                            <?php endif; ?>
                        </div>
                        <i class="bi bi-cloud-sun fs-1 opacity-25"></i>
                    </div>
                    <?php 
                        $weatherCurrent = $capitalWeather['current'] ?? null;
                        $weatherForecast = $capitalWeather['forecast'] ?? [];
                        $todayForecast = $weatherForecast[0] ?? null;
                    ?>
                    <?php if (!empty($weatherCurrent) || !empty($todayForecast)): ?>
                    <div class="mt-4 text-center">
                        <div class="display-3 fw-bold mb-0"><?= $weatherCurrent['max_temp'] ?? $weatherCurrent['temp'] ?? $todayForecast['max_temp'] ?? '--' ?>°</div>
                        <p class="lead mb-0 fw-medium" id="weather-condition"><?= \App\Services\CptecService::translateCondition($weatherCurrent['condition'] ?? $todayForecast['condition'] ?? null) ?></p>
                    </div>
                    <div class="d-flex justify-content-between mt-4 border-top border-white border-opacity-25 pt-3">
                        <div class="text-center">
                            <small class="d-block opacity-75">Mín</small>
                            <span class="fw-bold"><?= $weatherCurrent['min_temp'] ?? $todayForecast['min_temp'] ?? '--' ?>°</span>
                        </div>
                        <div class="text-center border-start border-white border-opacity-25 ps-3 pe-3">
                            <small class="d-block opacity-75">Máx</small>
                            <span class="fw-bold"><?= $weatherCurrent['max_temp'] ?? $todayForecast['max_temp'] ?? '--' ?>°</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold mb-3"><i class="bi bi-graph-up me-2 text-success"></i>Dados Fiscais do Estado</h2>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="p-3 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25 h-100">
                                <small class="text-success d-block mb-1 small fw-bold text-uppercase">Arrecadação Estimada</small>
                                <span class="h5 fw-bold text-success mb-0">
                                    R$ <?= number_format(($arrecadacaoEstado['arrecadacao'] ?? 0)/1e9, 2, ',', '.') ?> bi
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-secondary-subtle rounded-3 border h-100">
                                <small class="text-muted d-block mb-1 small fw-bold text-uppercase">Ranking Nacional</small>
                                <span class="h5 fw-bold mb-0"><?= $arrecadacaoEstado['ranking'] ?? '-' ?>º</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-secondary-subtle rounded-3 border h-100">
                                <small class="text-muted d-block mb-1 small fw-bold text-uppercase">Participação Brasil</small>
                                <span class="h5 fw-bold mb-0"><?= number_format($arrecadacaoEstado['participacao'] ?? 0, 1, ',', '.') ?>%</span>
                            </div>
                        </div>
                    </div>
                    <p class="small text-muted mt-2 mb-0">
                        <a href="/ranking">Ver ranking completo</a> &bull; <a href="/impostometro">Impostômetro</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4 fw-bold mb-0"><i class="bi bi-geo-alt me-2 text-brand"></i>Municípios em <?= htmlspecialchars($stateName) ?></h2>
                
                <div class="d-flex gap-2">
                    <form class="search-cities-form" method="get" action="/localidades/<?= strtolower($state['uf']) ?>" style="max-width: 300px;">
                        <div class="input-group input-group-sm">
                            <input type="text" name="search" class="form-control border shadow-sm" placeholder="Buscar cidade..." value="<?= e($_GET['search'] ?? '') ?>">
                            <button class="btn btn-brand text-white shadow-sm" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                    
                    <div class="dropdown">
                        <button class="btn btn-white border shadow-sm btn-sm dropdown-toggle px-3" type="button" data-bs-toggle="dropdown">
                            Trocar Estado
                        </button>
                        <ul class="dropdown-menu shadow-sm border-0" style="max-height: 300px; overflow-y: auto;">
                            <?php 
                            $allStates = (new \App\Repositories\StateRepository())->findAll();
                            foreach ($allStates as $st):
                            ?>
                                <li><a class="dropdown-item <?= $st['uf'] === $state['uf'] ? 'active bg-brand' : '' ?>" href="/localidades/<?= strtolower($st['uf']) ?>"><?= htmlspecialchars($st['name']) ?></a></li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/localidades">Ver todos</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 15px;">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-secondary-subtle">
                            <tr>
                                <th class="ps-4 border-0 py-3">Cidade</th>
                                <th class="border-0 py-3">Mesorregião</th>
                                <th class="border-0 py-3">População (IBGE)</th>
                                <th class="text-end pe-4 border-0 py-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($municipalities as $municipality): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-body"><?= htmlspecialchars($municipality['name']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($municipality['mesoregion'] ?? '-') ?></td>
                                    <td class="fw-medium"><?= isset($municipality['population']) && $municipality['population'] ? number_format((float)$municipality['population'], 0, ',', '.') : '<span class="text-muted italic small opacity-50">Sincronizando...</span>' ?></td>
                                    <td class="text-end pe-4">
                                        <?php 
                                        $citySlug = !empty($municipality['slug']) ? $municipality['slug'] : slugify($municipality['name']);
                                        ?>
                                        <a href="/localidades/<?= strtolower($state['uf']) ?>/<?= $citySlug ?>" class="btn btn-sm btn-brand rounded-pill px-3 shadow-sm">
                                            Ver Detalhes
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 gap-3 bg-white p-3 rounded-3 shadow-sm border">
        <div class="text-muted small">
            <?php 
            $from = ($page - 1) * $perPage + 1;
            $to = min($page * $perPage, $total);
            ?>
            Mostrando <span class="fw-bold"><?= number_format((float) ($from ?? 0), 0, ',', '.') ?></span> a <span class="fw-bold"><?= number_format((float) ($to ?? 0), 0, ',', '.') ?></span> de <span class="fw-bold"><?= number_format((float) ($total ?? 0), 0, ',', '.') ?></span> municípios
        </div>

        <?php if ($lastPage > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=1" aria-label="Primeira">
                            <i class="bi bi-chevron-double-left"></i>
                        </a>
                    </li>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Anterior">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php 
                    $range = 2;
                    $startPage = max(1, $page - $range);
                    $endPage = min($lastPage, $page + $range);
                    if ($startPage > 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($endPage < $lastPage): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item <?= $page >= $lastPage ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Próxima">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                    <li class="page-item <?= $page >= $lastPage ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $lastPage ?>" aria-label="Última">
                            <i class="bi bi-chevron-double-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>