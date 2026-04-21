<?php startblock('title') ?>
    Avaliar <?= e($company['legal_name']) ?>
<?php endblock() ?>

<?php startblock('content') ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Início</a></li>
                    <li class="breadcrumb-item"><a href="/empresa/<?= e($company['cnpj']) ?>"><?= e($company['legal_name']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Avaliar</li>
                </ol>
            </nav>

            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-star"></i> Avaliar <?= e($company['legal_name']) ?></h4>
                </div>
                <div class="card-body">
                    <form method="post" action="/empresa/<?= e($company['cnpj']) ?>/avaliar">
                        <?= csrf_field() ?>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Nota</label>
                            <div class="rating-select d-flex gap-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rating" 
                                            id="star<?= $i ?>" value="<?= $i ?>" required>
                                        <label class="form-check-label Star" for="star<?= $i ?>">
                                            <i class="bi bi-star-fill" style="font-size: 1.5rem;"></i>
                                            <span class="small"><?= $i ?></span>
                                        </label>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <div class="form-text">1 = Ruim, 5 = Excelente</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Comentário (opcional)</label>
                            <textarea name="comment" class="form-control" rows="5" 
                                maxlength="1000" placeholder="Conte sua experiência com esta empresa..."></textarea>
                            <div class="form-text">Máximo 1000 caracteres</div>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Sua avaliação será publicada imediatamente. 
                            Apenas uma avaliação por empresa a cada 7 dias.
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-send"></i> Enviar Avaliação
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.rating-select .form-check-label { cursor: pointer; }
.rating-select .form-check-input:checked + .form-check-label .bi-star-fill { color: #ffc107; }
.rating-select .form-check-label:hover .bi-star-fill,
.rating-select .form-check-label:hover ~ .form-check-label .bi-star-fill { color: #ffc107; }
</style>
<?php endblock() ?>