<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 fade-in mx-auto auth-card">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <h1 class="h4 fw-bold">Nova Senha</h1>
                        <p class="small text-muted">Digite sua nova senha.</p>
                    </div>

                    <?php if (!empty($flash)): ?>
                        <div class="alert alert-danger small"><?= e($flash) ?></div>
                    <?php endif; ?>

                    <form method="post" action="/redefinir-senha">
                        <input type="hidden" name="_token" value="<?= e(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
                        
                        <div class="mb-3">
                            <label for="password" class="form-label small fw-bold">Nova Senha</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required minlength="12" autofocus>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Mínimo 12 caracteres, letras maiúsculas/minúsculas, número e caractere especial</div>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label small fw-bold">Confirmar Senha</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required minlength="12">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password_confirmation">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-brand w-100 mb-3">
                            Alterar Senha
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
