<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 fade-in mx-auto auth-card">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <h1 class="h4 fw-bold">Recuperar Acesso</h1>
                        <p class="small text-muted">Informe seu e-mail para receber as instruções.</p>
                    </div>

                    <?php if (!empty($flash)): ?>
                        <div class="alert alert-danger small"><?= e($flash) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success small"><?= e($success) ?></div>
                    <?php endif; ?>

                    <form method="post" action="/recuperar-senha">
                        <input type="hidden" name="_token" value="<?= e(\App\Core\Csrf::token()) ?>">
                        
                        <div class="mb-4">
                            <label for="email" class="form-label small fw-bold">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" required autofocus>
                        </div>

                        <button type="submit" class="btn btn-brand w-100 mb-3">
                            Enviar Link
                        </button>

                        <div class="text-center">
                            <a href="/login" class="small text-decoration-none fw-bold">Voltar para o Login</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>