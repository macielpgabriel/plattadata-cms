<?php declare(strict_types=1); use App\Core\Auth; use App\Core\Csrf; ?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Inicio</a></li>
            <li class="breadcrumb-item"><a href="/empresas/<?= e($company['cnpj']) ?>"><?= e($company['legal_name']) ?></a></li>
            <li class="breadcrumb-item"><a href="/empresas/<?= e($company['cnpj']) ?>/avaliacoes">Avaliações</a></li>
            <li class="breadcrumb-item active">Minha Avaliação</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <h2 class="mb-4"><i class="bi bi-pencil-square"></i> Minha Avaliação</h2>

            <?php if (empty($review)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Você ainda não avaliou esta empresa.
                </div>
                <a href="/empresas/<?= e($company['cnpj']) ?>/avaliar" class="btn btn-primary">
                    <i class="bi bi-star"></i> Fazer Avaliação
                </a>
            <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="badge bg-<?= $review['status'] === 'approved' ? 'success' : 'warning' ?>">
                            <?= $review['status'] === 'approved' ? 'Publicada' : 'Pendente' ?>
                        </span>
                        <span class="text-muted small">
                            <?= date('d/m/Y H:i', strtotime($review['created_at'])) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <form method="post" action="/empresas/<?= e($company['cnpj']) ?>/minha-avaliacao/editar">
                            <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                            <input type="hidden" name="review_id" value="<?= $review['id'] ?>">

                            <div class="mb-4">
                                <label class="form-label fw-bold">Nota</label>
                                <div class="d-flex gap-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <label class="btn btn-outline-warning <?= $i == $review['rating'] ? 'active' : '' ?>">
                                            <input type="radio" name="rating" value="<?= $i ?>" <?= $i == $review['rating'] ? 'checked' : '' ?> required>
                                            <i class="bi bi-star"></i> <?= $i ?>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Comentário</label>
                                <textarea name="comment" class="form-control" rows="5" maxlength="1000"><?= e($review['comment'] ?? '') ?></textarea>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Salvar
                                </button>
                            </div>
                        </form>

                        <hr class="my-4">

                        <form method="post" action="/empresas/<?= e($company['cnpj']) ?>/minha-avaliacao/excluir" 
                            onsubmit="return confirm('Tem certeza que deseja excluir sua avaliação?');">
                            <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                            <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="bi bi-trash"></i> Excluir Avaliação
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>