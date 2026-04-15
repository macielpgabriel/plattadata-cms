<?php declare(strict_types=1); use App\Core\Csrf; ?>
<?php 
$metaTitle = $metaTitle ?? $title;
$metaDescription = $metaDescription ?? ($meta_description ?? null);
?>

<!-- Schema.org Structured Data -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Country",
    "name": "Brasil",
    "description": "<?= htmlspecialchars($metaDescription ?? 'Dados gerais do Brasil') ?>",
    "capital": "<?= htmlspecialchars($dadosBrasil['capital'] ?? 'Brasília') ?>",
    "population": <?= (int)($dadosBrasil['populacao_2022'] ?? 203080756) ?>,
    "area": {
        "@type": "QuantitativeValue",
        "value": <?= (float)($dadosBrasil['area_km2'] ?? 8509379.576) ?>,
        "unitCode": "KMK"
    },
    "numberOfMunicipalities": <?= (int)($dadosBrasil['num_municipios'] ?? 5570) ?>,
    "keywords": "Brasil, dados IBGE, população, PIB, economia, estados, municípios"
}
</script>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb shadow-sm p-3 bg-white rounded-3 border">
        <li class="breadcrumb-item"><a href="/" class="text-brand text-decoration-none">Home</a></li>
        <li class="breadcrumb-item active fw-bold" aria-current="page">Brasil</li>
    </ol>
</nav>

<?php if (!empty($syncNeeded)): ?>
<div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <div>
        <strong>Atenção:</strong> Os dados de municípios ainda não foram sincronizados. 
        <a href="/admin#observabilidade" class="alert-link">Acesse o painel admin</a> para sincronizar os <?= number_format(5570, 0, ',', '.') ?> municípios brasileiros via API do IBGE.
    </div>
</div>
<?php endif; ?>

<header class="mb-5">
    <span class="badge bg-brand bg-opacity-10 text-brand px-3 py-2 rounded-pill mb-3 fw-bold">País</span>
    <h1 class="display-4 fw-bold mb-2">Brasil</h1>
    <p class="lead text-muted">Capital: <?= htmlspecialchars($dadosBrasil['capital'] ?? '-') ?> | Área: <?= number_format((float)($dadosBrasil['area_km2'] ?? 0), 2, ',', '.') ?> km² | <?= number_format($numMunicipios ?? 5570, 0, ',', '.') ?> municípios | <?= number_format($totalEmpresas ?? 0, 0, ',', '.') ?> empresas</p>
</header>

<section class="mb-5" aria-label="Indicadores gerais do Brasil">
    <div class="row g-4">
        <div class="col-6 col-md-2">
            <div class="p-3 rounded-3 border tema-card text-center">
                <small class="text-uppercase text-muted d-block mb-1 small fw-bold label-metric">População 2022</small>
                <span class="h4 fw-bold mb-0 text-brand"><?= number_format($populacao ?? 0, 0, ',', '.') ?></span>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="p-3 rounded-3 border tema-card text-center">
                <small class="text-uppercase text-muted d-block mb-1 small fw-bold label-metric">Municípios</small>
                <span class="h4 fw-bold mb-0 text-brand"><?= number_format($numMunicipios ?? 0, 0, ',', '.') ?></span>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="p-3 rounded-3 border tema-card text-center">
                <small class="text-uppercase text-muted d-block mb-1 small fw-bold label-metric">Empresas</small>
                <span class="h4 fw-bold mb-0 text-brand"><?= number_format($totalEmpresas ?? 0, 0, ',', '.') ?></span>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="p-3 rounded-3 border tema-card text-center">
                <small class="text-uppercase text-muted d-block mb-1 small fw-bold label-metric">PIB</small>
                <span class="h4 fw-bold mb-0 text-success">R$ <?= !empty($pib) ? number_format($pib / 1e9, 0, ',', '.') . ' bi' : '-' ?></span>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="p-3 rounded-3 border tema-card text-center">
                <small class="text-uppercase text-muted d-block mb-1 small fw-bold label-metric">PIB per capita</small>
                <span class="h4 fw-bold mb-0 text-brand">R$ <?= number_format($pib / 203080400, 0, ',', '.') ?></span>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="p-3 rounded-3 border tema-card text-center">
                <small class="text-uppercase text-muted d-block mb-1 small fw-bold label-metric">Área km²</small>
                <span class="h4 fw-bold mb-0 text-brand"><?= number_format(8515767.0 / 1000, 0, ',', '.') ?> mil</span>
            </div>
        </div>
    </div>
</section>

<div class="row g-4 mb-5">
    <div class="col-md-6">
        <article class="card border-0 shadow-sm h-100 border-radius-lg" aria-labelledby="economia-heading">
            <div class="card-body p-4">
                <h2 class="h5 mb-4" id="economia-heading">
                    <i class="bi bi-graph-up-arrow me-2 text-success"></i>
                    Economia
                </h2>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">PIB Total</small>
                            <span class="h5 fw-bold text-success">US$ <?= !empty($dadosBrasil['pib']) ? number_format($dadosBrasil['pib'] / 1e9, 0, ',', '.') . ' bi' : '2,2 tri' ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">PIB per capita</small>
                            <span class="h5 fw-bold">US$ <?= number_format((int)($dadosBrasil['pib_per_capita'] ?? 10000), 0, ',', '.') ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Taxa de Desemprego</small>
                            <span class="h5 fw-bold"><?= number_format((float)($dadosBrasil['taxa_desemprego'] ?? 7.8), 1, ',', '.') ?> <small class="text-muted">%</small></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">IPCA (Inflação)</small>
                            <span class="h5 fw-bold"><?= number_format((float)($dadosBrasil['ipca'] ?? 4.62), 2, ',', '.') ?> <small class="text-muted">%</small></span>
                        </div>
                    </div>
                </div>
                <?php if (!empty($dadosBrasil['pib_setores'])): ?>
                <div class="mt-3">
                    <small class="text-muted d-block mb-2">Setores do PIB</small>
                    <div class="progress rounded-0" style="height: 24px; background-color: var(--hover-bg, #e9ecef);">
                        <div class="progress-bar bg-success fw-bold" style="width: <?= $dadosBrasil['pib_setores']['agropecuaria'] ?? 5.8 ?>%">
                            <small class="text-white">Agro: <?= $dadosBrasil['pib_setores']['agropecuaria'] ?? 5.8 ?>%</small>
                        </div>
                        <div class="progress-bar bg-warning fw-bold" style="width: <?= $dadosBrasil['pib_setores']['industria'] ?? 20.5 ?>%">
                            <small class="text-body">Ind: <?= $dadosBrasil['pib_setores']['industria'] ?? 20.5 ?>%</small>
                        </div>
                        <div class="progress-bar bg-info fw-bold" style="width: <?= $dadosBrasil['pib_setores']['servicos'] ?? 73.7 ?>%">
                            <small class="text-white">Serv: <?= $dadosBrasil['pib_setores']['servicos'] ?? 73.7 ?>%</small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </article>
    </div>
    
    <div class="col-md-6">
        <article class="card border-0 shadow-sm h-100 border-radius-lg" aria-labelledby="saude-heading">
            <div class="card-body p-4">
                <h2 class="h5 mb-4" id="saude-heading">
                    <i class="bi bi-person-heart me-2 text-danger"></i>
                    Saúde
                </h2>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Hospitais</small>
                            <span class="h5 fw-bold text-danger"><?= number_format((int)($dadosBrasil['hospitais'] ?? 6220), 0, ',', '.') ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Médicos</small>
                            <span class="h5 fw-bold"><?= number_format((float)($dadosBrasil['medicos_por_10mil'] ?? 26.9), 1, ',', '.') ?> <small class="text-muted">/10mil</small></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Leitos</small>
                            <span class="h5 fw-bold"><?= number_format((float)($dadosBrasil['leitos_por_10mil'] ?? 20.3), 1, ',', '.') ?> <small class="text-muted">/10mil</small></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Mortalidade Infantil</small>
                            <span class="h5 fw-bold"><?= number_format((float)($dadosBrasil['mortalidade_infantil'] ?? 12.62), 2, ',', '.') ?> <small class="text-muted">óbitos/mil</small></span>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-md-6">
        <article class="card border-0 shadow-sm h-100 border-radius-lg" aria-labelledby="educacao-heading">
            <div class="card-body p-4">
                <h2 class="h5 mb-4" id="educacao-heading">
                    <i class="bi bi-book me-2 text-primary"></i>
                    Educação
                </h2>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Escolas</small>
                            <span class="h5 fw-bold text-primary"><?= number_format((int)($dadosBrasil['escolas'] ?? 178156), 0, ',', '.') ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Taxa Analfabetismo</small>
                            <span class="h5 fw-bold"><?= number_format((float)($dadosBrasil['analfabetismo'] ?? 7.2), 1, ',', '.') ?> <small class="text-muted">%</small></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Atividade Física</small>
                            <span class="h5 fw-bold"><?= number_format((float)($dadosBrasil['atividade_fisica'] ?? 37.9), 1, ',', '.') ?> <small class="text-muted">%</small></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Taxa Fecundidade</small>
                            <span class="h5 fw-bold"><?= number_format((float)($dadosBrasil['taxa_fecundidade'] ?? 1.76), 2, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </div>
    
    <div class="col-md-6">
        <article class="card border-0 shadow-sm h-100 border-radius-lg" aria-labelledby="transporte-heading">
            <div class="card-body p-4">
                <h2 class="h5 mb-4" id="transporte-heading">
                    <i class="bi bi-car-front me-2 text-warning"></i>
                    Transporte
                </h2>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Frota de Veículos</small>
                            <span class="h5 fw-bold text-warning"><?= number_format((int)($dadosBrasil['frota_veiculos'] ?? 104742583), 0, ',', '.') ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Aeroportos</small>
                            <span class="h5 fw-bold"><?= number_format((int)($dadosBrasil['aeroportos'] ?? 693), 0, ',', '.') ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Consumo Energia</small>
                            <span class="h5 fw-bold"><?= number_format((int)($dadosBrasil['energia_eletrica'] ?? 385912), 0, ',', '.') ?> <small class="text-muted">GWh</small></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Densidade Demográfica</small>
                            <span class="h5 fw-bold"><?= number_format((float)($dadosBrasil['densidade_2022'] ?? 23.86), 2, ',', '.') ?> <small class="text-muted">hab/km²</small></span>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-md-6">
        <article class="card border-0 shadow-sm h-100 border-radius-lg" aria-labelledby="comercio-heading">
            <div class="card-body p-4">
                <h2 class="h5 mb-4" id="comercio-heading">
                    <i class="bi bi-globe me-2 text-info"></i>
                    Comércio Exterior
                </h2>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-success d-block">Exportações</small>
                            <span class="h5 fw-bold text-success">US$ <?= number_format((int)($dadosBrasil['exportacoes'] ?? 335610000000) / 1e9, 0, ',', '.') ?> bi</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-danger d-block">Importações</small>
                            <span class="h5 fw-bold text-danger">US$ <?= number_format((int)($dadosBrasil['importacoes'] ?? 267800000000) / 1e9, 0, ',', '.') ?> bi</span>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </div>
    
    <div class="col-md-6">
        <article class="card border-0 shadow-sm h-100 border-radius-lg" aria-labelledby="infra-heading">
            <div class="card-body p-4">
                <h2 class="h5 mb-4" id="infra-heading">
                    <i class="bi bi-house me-2 text-brand"></i>
                    Infraestrutura
                </h2>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Iluminação Elétrica</small>
                            <span class="h5 fw-bold text-success"><?= number_format((float)($dadosBrasil['iluminacao_eletrica'] ?? 99.7), 1, ',', '.') ?> <small class="text-muted">%</small></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Água Rede</small>
                            <span class="h5 fw-bold text-success"><?= number_format((float)($dadosBrasil['agua_rede'] ?? 85.5), 1, ',', '.') ?> <small class="text-muted">%</small></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Esgotamento</small>
                            <span class="h5 fw-bold"><?= number_format((float)($dadosBrasil['esgotamento'] ?? 63.2), 1, ',', '.') ?> <small class="text-muted">%</small></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Internet</small>
                            <span class="h5 fw-bold text-primary"><?= number_format((float)($dadosBrasil['internet'] ?? 90.0), 1, ',', '.') ?> <small class="text-muted">%</small></span>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-md-6">
        <article class="card border-0 shadow-sm h-100 border-radius-lg" aria-labelledby="tech-heading">
            <div class="card-body p-4">
                <h2 class="h5 mb-4" id="tech-heading">
                    <i class="bi bi-laptop me-2 text-primary"></i>
                    Tecnologia
                </h2>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Internet</small>
                            <span class="h5 fw-bold text-primary"><?= number_format((float)($dadosBrasil['internet'] ?? 90.0), 1, ',', '.') ?> <small class="text-muted">%</small></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Telefone Móvel</small>
                            <span class="h5 fw-bold"><?= number_format((float)($dadosBrasil['telefone_movel'] ?? 96.3), 1, ',', '.') ?> <small class="text-muted">%</small></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Microcomputador</small>
                            <span class="h5 fw-bold"><?= number_format((float)($dadosBrasil['microcomputador'] ?? 42.6), 1, ',', '.') ?> <small class="text-muted">%</small></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Televisão</small>
                            <span class="h5 fw-bold"><?= number_format((float)($dadosBrasil['televisao'] ?? 95.5), 1, ',', '.') ?> <small class="text-muted">%</small></span>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </div>
    
    <div class="col-md-6">
        <article class="card border-0 shadow-sm h-100 border-radius-lg" aria-labelledby="nomes-heading">
            <div class="card-body p-4">
                <h2 class="h5 mb-4" id="nomes-heading">
                    <i class="bi bi-person-badge me-2 text-brand"></i>
                    Nomes Populares
                </h2>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Nome Masculino</small>
                            <span class="h4 fw-bold text-brand"><?= htmlspecialchars($dadosBrasil['nome_masculino'] ?? 'José') ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Nome Feminino</small>
                            <span class="h4 fw-bold text-brand"><?= htmlspecialchars($dadosBrasil['nome_feminino'] ?? 'Maria') ?></span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-3 rounded-3 border tema-card">
                            <small class="text-muted d-block">Sobrenome</small>
                            <span class="h4 fw-bold text-brand"><?= htmlspecialchars($dadosBrasil['sobrenome'] ?? 'Silva') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </div>
</div>

<div class="text-center mb-5">
    <a href="/localidades" class="btn btn-primary btn-lg px-5 rounded-pill">
        <i class="bi bi-geo-alt me-2"></i>
        Ver Estados
    </a>
</div>

<footer class="mt-5 py-4 bg-light border-top">
    <div class="container">
        <h6 class="mb-3 fw-bold">Fontes dos Dados</h6>
        <div class="row g-3 small text-muted">
            <div class="col-md-6">
                <p class="mb-1"><strong>IBGE</strong> - Instituto Brasileiro de Geografia e Estatística</p>
                <p class="mb-1">Censo 2022, PIB 2020, Área Territorial</p>
            </div>
            <div class="col-md-6">
                <p class="mb-1"><strong>CNES</strong> - Cadastro Nacional de Estabelecimentos de Saúde</p>
                <p class="mb-1">Hospitais, Médicos, Leitos (2023)</p>
            </div>
            <div class="col-md-6">
                <p class="mb-1"><strong>INEP</strong> - Instituto Nacional de Estudos e Pesquisas Educacionais</p>
                <p class="mb-1">Escolas (2023)</p>
            </div>
            <div class="col-md-6">
                <p class="mb-1"><strong>DENATRAN</strong> - Departamento Nacional de Trânsito</p>
                <p class="mb-1">Frota de Veículos (2023)</p>
            </div>
            <div class="col-md-6">
                <p class="mb-1"><strong>MDIC</strong> - Ministério do Desenvolvimento, Indústria e Comércio</p>
                <p class="mb-1">Comércio Exterior (2023)</p>
            </div>
            <div class="col-md-6">
                <p class="mb-1"><strong>ANAC</strong> - Agência Nacional de Aviação Civil</p>
                <p class="mb-1">Aeroportos (2023)</p>
            </div>
        </div>
        <hr>
        <p class="text-center text-muted small mb-0">
            Dados atualizados em <?= date('d/m/Y') ?>. 
        </p>
    </div>
</footer>
