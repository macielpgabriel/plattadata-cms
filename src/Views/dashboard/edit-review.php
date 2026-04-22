<?php declare(strict_types=1); ?>
<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item"><a href="/dashboard">Painel</a></li>
        <li class="breadcrumb-item"><a href="/dashboard/minhas-avaliacoes">Minhas Avaliações</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
</nav>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3 fade-in">
    <div>
        <h1 class="h3 mb-1">Editar Avaliação</h1>
        <p class="text-muted mb-0 small"><?= e($review['trade_name'] ?: $review['legal_name']) ?></p>
        <a href="/empresas/<?= e($review['cnpj']) ?>" class="small text-muted"><?= e($review['cnpj']) ?></a>
    </div>
    <a href="/dashboard/minhas-avaliacoes" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm fade-in">
            <div class="card-body">
                <form method="post" action="/dashboard/minhas-avaliacoes/<?= $review['id'] ?>/editar">
                    <input type="hidden" name="_token" value="<?= \App\Core\Csrf::token() ?>">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Nota</label>
                        <div class="d-flex gap-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="btn btn-outline-warning <?= $i == $review['rating'] ? 'active' : '' ?>" style="cursor: pointer;">
                                    <input type="radio" name="rating" value="<?= $i ?>" <?= $i == $review['rating'] ? 'checked' : '' ?> style="display: none;">
                                    <i class="bi bi-star-fill"></i> <?= $i ?>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Comentário</label>
                        <textarea name="comment" class="form-control" rows="5" maxlength="1000" placeholder="Conte sua experiência com esta empresa..."><?= e($review['comment'] ?? '') ?></textarea>
                        <small class="text-muted">Máximo 1000 caracteres</small>
                    </div>

                    <div class="alert alert-warning alert-permanent">
                        <i class="bi bi-info-circle me-2"></i>
                        Após editar, sua avaliação entrará em processo de moderação novamente.
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-brand">
                            <i class="bi bi-check-lg me-1"></i>Salvar
                        </button>
                        <a href="/dashboard/minhas-avaliacoes" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3 fade-in">
            <div class="card-body">
                <h5 class="mb-3 text-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>Zona de Perigo
                </h5>
                <p class="text-muted mb-3">Excluir esta avaliação não poderá ser desfeito.</p>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash me-1"></i>Excluir Avaliação
                    </button>

                    <div class="modal fade" id="deleteModal" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered modal-sm">
                            <div class="modal-content">
                                <div class="modal-header border-0 pb-0">
                                    <h5 class="modal-title text-danger">
                                        <i class="bi bi-exclamation-triangle me-2"></i>Excluir Avaliação
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body pt-2 text-center">
                                    <p class="mb-0">Tem certeza que deseja excluir esta avaliação? Esta ação não pode ser desfeita.</p>
                                </div>
                                <div class="modal-footer border-0 pt-0 justify-content-center">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <form method="post" action="/dashboard/minhas-avaliacoes/<?= $review['id'] ?>/excluir" class="d-inline">
                                        <input type="hidden" name="_token" value="<?= \App\Core\Csrf::token() ?>">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="bi bi-trash me-1"></i>Excluir
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm fade-in">
            <div class="card-body">
                <h5 class="mb-3">Informações</h5>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Empresa</dt>
                    <dd class="col-sm-8"><?= e($review['trade_name'] ?: $review['legal_name']) ?></dd>
                    
                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?= $review['status'] === 'approved' ? 'success' : 'warning' ?>">
                            <?= $review['status'] === 'approved' ? 'Publicada' : 'Pendente' ?>
                        </span>
                    </dd>
                    
                    <dt class="col-sm-4">Criado</dt>
                    <dd class="col-sm-8"><?= date('d/m/Y H:i', strtotime($review['created_at'])) ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>