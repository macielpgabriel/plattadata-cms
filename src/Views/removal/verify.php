<?php declare(strict_types=1);
use App\Core\Csrf;
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 text-center">
                <h1 class="h3 mb-4"><i class="bi bi-envelope-check me-2"></i>Verificação de E-mail</h1>
                
                <p>Enviamos um código de 6 dígitos para o e-mail registrado da empresa.</p>
                <p class="text-muted">Por favor, insira o código abaixo para validar sua solicitação.</p>

                <form action="/empresas/remover/verificar" method="post" class="mt-4">
                    <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                    <input type="hidden" name="request_id" value="<?= e($request_id) ?>">
                    
                    <div class="mb-4">
                        <input type="text" class="form-control form-control-lg text-center fw-bold" 
                               name="code" maxlength="6" pattern="[0-9]{6}" required 
                               placeholder="000000" style="letter-spacing: 0.5rem;">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Verificar Código</button>
                    </div>
                </form>

                <div class="mt-4">
                    <small class="text-muted">Não recebeu o código? Verifique a caixa de spam ou entre em contato com o suporte.</small>
                </div>
            </div>
        </div>
    </div>
</div>
