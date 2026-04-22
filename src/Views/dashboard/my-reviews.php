<?php declare(strict_types=1); ?>
<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item"><a href="/dashboard">Painel</a></li>
        <li class="breadcrumb-item active" aria-current="page">Minhas Avaliações</li>
    </ol>
</nav>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3 fade-in">
    <div>
        <h1 class="h3 mb-1">Minhas Avaliações</h1>
        <p class="text-muted mb-0 small">Todas as avaliações que você fez.</p>
    </div>
    <a href="/dashboard" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Voltar
    </a>
</div>

<?php if (empty($reviews)): ?>
    <div class="card border-0 shadow-sm fade-in">
        <div class="card-body text-center py-5">
            <i class="bi bi-star text-muted fs-1 mb-3 d-block"></i>
            <h5 class="mb-2">Nenhuma avaliação ainda</h5>
            <p class="text-muted mb-3">Você ainda não avaliou nenhuma empresa.</p>
            <a href="/empresas" class="btn btn-brand">Buscar Empresas</a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3 fade-in">
        <?php foreach ($reviews as $review): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="mb-1">
                                    <a href="/empresas/<?= e($review['cnpj']) ?>" class="text-decoration-none">
                                        <?= e($review['trade_name'] ?: $review['legal_name']) ?>
                                    </a>
                                </h5>
                                <a href="/empresas/<?= e($review['cnpj']) ?>" class="small text-muted text-decoration-none">
                                    <?= e($review['cnpj']) ?>
                                </a>
                            </div>
                            <div class="text-end">
                                <div class="mb-1">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star-fill <?= $i <= $review['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="badge bg-<?= $review['status'] === 'approved' ? 'success' : 'warning' ?>">
                                    <?= $review['status'] === 'approved' ? 'Publicada' : 'Pendente' ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if (!empty($review['comment'])): ?>
                            <p class="mb-2"><?= e($review['comment']) ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($review['reply'])): ?>
                            <div class="alert alert-light mt-2 mb-2">
                                <strong class="small">Resposta da empresa:</strong>
                                <p class="mb-0 small"><?= e($review['reply']) ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted"><?= date('d/m/Y H:i', strtotime($review['created_at'])) ?></span>
                            <div class="d-flex gap-2">
                                <a href="/dashboard/minhas-avaliacoes/<?= $review['id'] ?>/editar" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-pencil me-1"></i>Editar
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal-<?= $review['id'] ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="deleteModal-<?= $review['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title text-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>Excluir
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body pt-2 text-center">
                            <p class="mb-0">Excluir avaliação de <strong><?= e($review['trade_name'] ?: $review['legal_name']) ?></strong>?</p>
                        </div>
                        <div class="modal-footer border-0 pt-0 justify-content-center">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <form method="post" action="/dashboard/minhas-avaliacoes/<?= $review['id'] ?>/excluir" class="d-inline">
                                <input type="hidden" name="_token" value="<?= \App\Core\Csrf::token() ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>