<?php startblock('title') ?>
    Empresa Validada - Plattadata
<?php endblock() ?>

<?php startblock('content') ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <div class="card">
                <div class="card-body">
                    <div class="text-success mb-4">
                        <i class="bi bi-check-circle-fill" style="font-size: 4rem;"></i>
                    </div>
                    <h3>Empresa Validada!</h3>
                    <p class="text-muted">
                        Você agora pode gerenciar os dados da sua empresa no Plattadata.
                    </p>
                    <a href="/empresa/<?= e($cnpj) ?>/dashboard" class="btn btn-primary btn-lg">
                        <i class="bi bi-speedometer2"></i> Acessar Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endblock() ?>