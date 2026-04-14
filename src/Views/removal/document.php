<?php declare(strict_types=1);
use App\Core\Csrf;
$error = \App\Core\Session::flash('error');
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h3 mb-4 text-center"><i class="bi bi-file-earmark-arrow-up me-2"></i>Anexar Documentação</h1>
                
                <div class="alert alert-info alert-permanent mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    Para prosseguir com a remoção manual, precisamos de um documento que comprove seu vínculo com a empresa (ex: Contrato Social, Procuração ou Documento de Identidade do Sócio).
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-permanent">
                        <?= e($error); ?>
                    </div>
                <?php endif; ?>

                <form action="/empresas/remover/documento" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                    <input type="hidden" name="request_id" value="<?= e($request_id) ?>">
                    
                    <div class="mb-4">
                        <label for="document" class="form-label">Selecione o arquivo (PDF, JPG ou PNG)</label>
                        <input type="file" class="form-control" id="document" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="form-text">Tamanho máximo: 5MB.</div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Enviar Documento</button>
                        <a href="/" class="btn btn-link text-muted">Cancelar e Sair</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
