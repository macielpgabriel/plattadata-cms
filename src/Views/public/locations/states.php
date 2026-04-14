<?php declare(strict_types=1); use App\Core\Csrf; ?>
<?php 
$metaTitle = $title;
$metaDescription = $metaDescription ?? ($meta_description ?? null);
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Home</a></li>
        <li class="breadcrumb-item"><a href="/localidades">Estados</a></li>
        <li class="breadcrumb-item active" aria-current="page">Todas</li>
    </ol>
</nav>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="display-5 fw-bold">Estados do Brasil</h1>
        <p class="lead">Navegue pelas empresas e cidades organizadas por estado.</p>
    </div>
</div>

<div class="row g-4">
    <?php 
    $regionMap = [
        'Norte' => ['AC','AP','AM','PA','RO','RR','TO'],
        'Nordeste' => ['AL','BA','CE','MA','PB','PE','PI','RN','SE'],
        'Centro-Oeste' => ['DF','GO','MT','MS'],
        'Sudeste' => ['ES','MG','RJ','SP'],
        'Sul' => ['PR','RS','SC']
    ];
    foreach ($regionMap as $region => $ufs):
        $regionStates = array_filter($states, fn($s) => in_array($s['uf'], $ufs));
        if (empty($regionStates)) continue;
    ?>
    <div class="col-12">
        <h3 class="h5 fw-bold text-brand mb-3"><?= $region ?></h3>
    </div>
    <?php foreach ($regionStates as $state): 
        $s = $stats[$state['uf']] ?? [];
        $hasEmpresas = !empty($s['empresas']);
        $hasMunicipios = !empty($s['municipios']);
    ?>
        <div class="col-md-4 col-lg-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title d-flex justify-content-between align-items-center">
                        <?= htmlspecialchars($state['name']) ?>
                        <span class="badge bg-primary rounded-pill"><?= $state['uf'] ?></span>
                    </h5>
                    <?php if ($hasEmpresas || $hasMunicipios): ?>
                        <?php if ($hasEmpresas): ?>
                        <p class="card-text text-muted small mb-2">
                            <i class="bi bi-building me-1"></i> <?= number_format($s['empresas'], 0, ',', '.') ?> empresas
                        </p>
                        <?php endif; ?>
                        <?php if ($hasMunicipios): ?>
                        <p class="card-text text-muted small mb-2">
                            <i class="bi bi-geo-alt me-1"></i> <?= number_format($s['municipios'], 0, ',', '.') ?> municípios
                        </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="card-text text-muted small">Dados não disponíveis</p>
                    <?php endif; ?>
                    <a href="/localidades/<?= strtolower($state['uf']) ?>" class="btn btn-outline-primary w-100 stretched-link">
                        Ver Cidades
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endforeach; ?>
</div>