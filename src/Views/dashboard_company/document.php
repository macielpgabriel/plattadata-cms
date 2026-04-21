<?php startblock('title') ?>
    Enviar Documento - Plattadata
<?php endblock() ?>

<?php startblock('content') ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Anexar Documento</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Como não temos e-mail cadastrado, por favor envie um documento que comprove sua ligação com a empresa.
                    </p>

                    <form method="post" action="/empresa/validar/documento" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?= e($request_id) ?>">
                        <input type="hidden" name="cnpj" value="<?= e($_GET['cnpj'] ?? '') ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Documento (PDF, JPG ou PNG)</label>
                            <input type="file" name="document" class="form-control" required accept=".pdf,.jpg,.jpeg,.png">
                        </div>

                        <div class="alert alert-light">
                            <strong>Documentos aceitos:</strong>
                            <ul class="mb-0 mt-2">
                                <li>RG ou CNH do responsável</li>
                                <li>Contrato social ou ata de eleição</li>
                                <li>Comprovante de residência</li>
                            </ul>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-upload"></i> Enviar Documento
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endblock() ?>