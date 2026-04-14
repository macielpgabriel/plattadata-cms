<?php declare(strict_types=1); use App\Core\Csrf; ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 fade-in mx-auto auth-card">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="alert-circle">
                            <i class="bi bi-envelope-x fs-2 text-danger"></i>
                        </div>
                        <h1 class="h3 fw-bold">Cancelar Inscrição</h1>
                        <p class="text-muted">Você não receberá mais e-mails da Plattadata.</p>
                    </div>

                    <?php if (!empty($flash)): ?>
                        <div class="alert alert-<?= strpos($flash, 'sucesso') !== false ? 'success' : 'danger' ?> small"><?= e($flash) ?></div>
                    <?php endif; ?>

                    <form method="post" action="/unsubscribe" class="needs-validation">
                        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label small fw-bold">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($email ?? '') ?>" required>
                        </div>

                        <button type="submit" class="btn btn-danger w-100 mb-3">
                            Cancelar Inscrição
                        </button>

                        <div class="text-center mt-3">
                            <a href="/" class="small text-decoration-none">← Voltar para home</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
