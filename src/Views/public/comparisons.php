<style>
.comparison-card {
    border: none;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s;
    height: 100%;
}
.comparison-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg) !important;
}
.comparison-card .card-body {
    padding: 1.75rem;
}
.comparison-card .icon-wrapper {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    margin-bottom: 1rem;
}
.hero-section {
    background: linear-gradient(135deg, var(--brand) 0%, #14b8a6 100%);
    border-radius: 20px;
    padding: 3rem;
    color: white;
    margin-bottom: 2rem;
}
</style>

<div class="container py-4">
    <div class="hero-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-2">
                    <i class="bi bi-bar-chart-fill me-3"></i>Comparacoes e Analises
                </h1>
                <p class="mb-0 opacity-75">
                    Compare CNAEs, estados, cidades e tributacao para tomar melhores decisoes empresariais.
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <span class="badge bg-light text-body px-3 py-2">
                    <i class="bi bi-collection me-1"></i><?= count($pages ?? []) ?> Analises
                </span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php
        $categories = [
            'tributacao' => ['icon' => 'bi-percent', 'color' => 'danger', 'bg' => 'var(--danger-bg)', 'label' => 'Tributacao'],
            'localizacao' => ['icon' => 'bi-geo-alt', 'color' => 'primary', 'bg' => 'var(--brand-light)', 'label' => 'Localizacao'],
            'atividade' => ['icon' => 'bi-briefcase', 'color' => 'success', 'bg' => 'var(--success-bg)', 'label' => 'Atividade'],
        ];
        
        $slugCategories = [
            'tributacao-brasil-vs-mundo' => 'tributacao',
            'simples-nacional-vs-lucro-presumido' => 'tributacao',
            'custos-abrir-empresa-por-estado' => 'tributacao',
            'estados-com-mais-empresas' => 'localizacao',
            'melhores-cidades-abrir-empresa' => 'localizacao',
            'cnae-mais-lucrativos' => 'atividade',
        ];
        ?>
        
        <?php foreach ($pages as $slug => $page): ?>
        <?php 
        $cat = $slugCategories[$slug] ?? 'atividade';
        $catInfo = $categories[$cat];
        ?>
        <div class="col-md-6 col-lg-4">
            <a href="/comparacoes/<?= e($slug) ?>" class="text-decoration-none">
                <div class="card comparison-card shadow-sm">
                    <div class="card-body">
                        <div class="icon-wrapper" style="background: <?= e($catInfo['bg']) ?>;">
                            <i class="bi <?= e($catInfo['icon']) ?> text-<?= e($catInfo['color']) ?>"></i>
                        </div>
                        <h5 class="card-title mb-2"><?= e(explode(' - ', $page['title'])[0]) ?></h5>
                        <p class="card-text text-muted small mb-3">
                            <?= e($page['description'] ?? '') ?>
                        </p>
                        <span class="badge bg-<?= e($catInfo['color']) ?> bg-opacity-10 text-<?= e($catInfo['color']) ?>">
                            <?= e($catInfo['label']) ?>
                        </span>
                        <span class="badge bg-light text-body ms-2">
                            <i class="bi bi-arrow-right"></i>
                        </span>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row mt-5">
        <div class="col-12">
            <div class="card bg-light border-0">
                <div class="card-body py-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-1"><i class="bi bi-lightbulb text-warning me-2"></i>Dica</h5>
                            <p class="mb-0 text-muted small">
                                Use estas analises paraplanejar a abertura ou expansao do seu negocio. 
                                Os dados sao atualizados periodicamente com as ultimas informacoes disponiveis.
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <a href="/" class="btn btn-outline-primary">
                                <i class="bi bi-search me-2"></i>Consultar CNPJ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
