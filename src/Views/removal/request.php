<?php declare(strict_types=1);
use App\Core\Csrf;
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/">Início</a></li>
                <li class="breadcrumb-item"><a href="/empresas/<?= e($company['cnpj']) ?>"><?= e($company['legal_name']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Solicitar Remoção</li>
            </ol>
        </nav>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h3 mb-4"><i class="bi bi-shield-lock me-2"></i>Solicitar Remoção de Empresa</h1>
                
                <div class="alert alert-info alert-permanent">
                    <i class="bi bi-info-circle me-2"></i>
                    Para garantir a segurança e a veracidade das informações, solicitamos uma verificação de propriedade antes de processar a remoção.
                </div>

                <p>Você está solicitando a remoção da empresa: <strong><?= e($company['legal_name']) ?></strong> (CNPJ: <?= e($company['cnpj']) ?>).</p>

                <form action="/empresas/<?= e($company['cnpj']) ?>/remover" method="post" class="mt-4">
                    <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Seu Nome Completo</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Seu E-mail de Contato</label>
                        <input type="email" class="form-control" id="email" name="email" required aria-describedby="emailHelp">
                        <div id="emailHelp" class="form-text">Usaremos este e-mail apenas para comunicações sobre esta solicitação.</div>
                    </div>

                    <?php if (!empty($company['email'])): ?>
                        <div class="alert alert-success alert-permanent mt-4">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Verificação por E-mail:</strong> Identificamos um e-mail registrado para esta empresa. 
                            Enviaremos um código de confirmação para <code><?= e(mask_email($company['email'])) ?></code>.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning alert-permanent mt-4">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Verificação por Documento:</strong> Não encontramos um e-mail registrado para esta empresa. 
                            Você precisará anexar um documento que comprove ser o proprietário ou representante legal após esta etapa.
                        </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">Iniciar Processo de Remoção</button>
                        <a href="/empresas/<?= e($company['cnpj']) ?>" class="btn btn-link">Cancelar e Voltar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
