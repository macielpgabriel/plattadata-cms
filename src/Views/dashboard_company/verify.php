<?php startblock('title') ?>
    Verificar Código - Plattadata
<?php endblock() ?>

<?php startblock('content') ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Código de Verificação</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Enviamos um código de 6 dígitos para o e-mail cadastrado na Receita Federal.
                    </p>

                    <form method="post" action="/empresa/validar/verificar">
                        <input type="hidden" name="id" value="<?= e($request_id) ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Código</label>
                            <input type="text" name="code" class="form-control form-control-lg text-center" 
                                required maxlength="6" pattern="[0-9]{6}" placeholder="000000"
                                autocomplete="one-time-code">
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-check-lg"></i> Verificar
                        </button>
                    </form>

                    <hr>
                    <p class="text-muted small mb-0">
                        Não recebeu? <a href="/empresa/validar/documento?id=<?= e($request_id) ?>">Envie documento</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endblock() ?>