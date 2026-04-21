<?php declare(strict_types=1); use App\Core\Auth; use App\Core\Csrf; ?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Inicio</a></li>
            <li class="breadcrumb-item"><a href="/empresas/<?= e($company['cnpj']) ?>"><?= e($company['legal_name']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Avaliações</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0"><i class="bi bi-star"></i> Avaliações</h2>
                <div class="d-flex gap-2">
                    <?php if (Auth::check()): ?>
                        <a href="/empresas/<?= e($company['cnpj']) ?>/minha-avaliacao" class="btn btn-outline-secondary">
                            <i class="bi bi-person"></i> Minha Avaliação
                        </a>
                    <?php endif; ?>
                    <a href="/empresas/<?= e($company['cnpj']) ?>/avaliar" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Avaliar
                    </a>
                </div>
            </div>

            <?php if (empty($reviews)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Nenhuma avaliação ainda. Seja o primeiro a avaliar!
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="mb-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star-fill <?= $i <= $review['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="text-muted small">
                                        <?= e($review['user_name']) ?> &bull; <?= date('d/m/Y', strtotime($review['created_at'])) ?>
                                    </div>
                                </div>
                                <?php if (Auth::check()): ?>
                                <form method="post" action="/empresas/<?= e($company['cnpj']) ?>/reportar" class="d-inline">
                                    <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                                    <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-muted" title="Reportar">
                                        <i class="bi bi-flag"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($review['comment'])): ?>
                                <p class="mb-0"><?= e($review['comment']) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($review['reply'])): ?>
                                <div class="alert alert-light mt-3 mb-0">
                                    <strong><i class="bi bi-arrow-return-right"></i> Resposta da empresa:</strong>
                                    <p class="mb-0 mt-1"><?= e($review['reply']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Resumo</h5>
                </div>
                <div class="card-body text-center">
                    <div class="display-4 fw-bold text-warning">
                        <?= $avg_rating > 0 ? number_format($avg_rating, 1) : '-' ?>
                    </div>
                    <div class="mb-2">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star-fill <?= $i <= round($avg_rating) ? 'text-warning' : 'text-muted' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-muted mb-0"><?= $total_reviews ?> avaliações</p>
                </div>
            </div>
        </div>
    </div>
</div>