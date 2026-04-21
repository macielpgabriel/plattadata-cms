<?php declare(strict_types=1); use App\Core\Csrf; use App\Core\Auth; ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="/empresas/<?= e($company['cnpj']) ?>"><?= e($company['legal_name']) ?></a></li>
                    <li class="breadcrumb-item active">Avaliar</li>
                </ol>
            </nav>

            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-star"></i> Avaliar <?= e($company['legal_name']) ?></h4>
                </div>
                <div class="card-body">
                    <?php if (!Auth::check()): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Faça <a href="/login?redirect=/empresas/<?= e($company['cnpj']) ?>/avaliar">login</a> para avaliar.
                        </div>
                    <?php else: ?>
                        <form method="post" action="/empresas/<?= e($company['cnpj']) ?>/avaliar">
                            <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">Nota</label>
                                <div class="d-flex gap-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <label class="btn btn-outline-warning">
                                            <input type="radio" name="rating" value="<?= $i ?>" required>
                                            <i class="bi bi-star"></i> <?= $i ?>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                                <div class="form-text">1 = Ruim, 5 = Excelente</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Comentário (opcional)</label>
                                <textarea name="comment" class="form-control" rows="5" maxlength="1000" placeholder="Conte sua experiência..."></textarea>
                                <div class="form-text">Máximo 1000 caracteres</div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-send"></i> Enviar Avaliação
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>