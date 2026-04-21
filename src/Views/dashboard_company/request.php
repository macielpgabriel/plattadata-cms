<?php startblock('title') ?>
    Validar Empresa - <?= e($company['legal_name']) ?>
<?php endblock() ?>

<?php startblock('content') ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Validar Empresa</h4>
                </div>
                <div class="card-body">
                    <?php if ($pending): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Você já tem uma verificação pendente.
                        </div>
                    <?php endif; ?>

                    <p class="text-muted">
                        Para gerenciar os dados da empresa <strong><?= e($company['legal_name']) ?></strong>, 
                        precisamos verificar sua propriedade.
                    </p>

                    <form method="post" action="/empresa/validar/<?= e($company['cnpj']) ?>">
                        <div class="mb-3">
                            <label class="form-label">Seu Nome</label>
                            <input type="text" name="name" class="form-control" required 
                                placeholder="Nome completo">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Seu E-mail</label>
                            <input type="email" name="email" class="form-control" required
                                placeholder="seu@email.com">
                        </div>

                        <div class="alert alert-light">
                            <i class="bi bi-shield-check"></i>
                            <strong>Como funciona:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Se a empresa tiver e-mail cadastrado na Receita, enviaremos código de verificação</li>
                                <li>Caso contrário, solicitaremos documento de identificação</li>
                            </ul>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-check-circle"></i> Validar Propriedade
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endblock() ?>