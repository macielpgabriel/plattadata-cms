<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0 fade-in mx-auto auth-card-wide">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <h1 class="h3 fw-bold">Criar Conta</h1>
                        <p class="text-muted">Junte-se ao Plattadata para salvar favoritos e muito mais.</p>
                    </div>

                    <?php if (!empty($flash)): ?>
                        <div class="alert alert-success small"><?= e($flash) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger small"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post" action="/cadastro" class="needs-validation">
                        <input type="hidden" name="_token" value="<?= e(\App\Core\Csrf::token()) ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label small fw-bold">Nome Completo</label>
                            <input type="text" class="form-control" id="name" name="name" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label small fw-bold">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label small fw-bold">Senha</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text x-small">Mínimo 12 caracteres, letras maiúsculas/minúsculas, número e caractere especial</div>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label small fw-bold">Confirmar Senha</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password_confirmation">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-brand w-100 mb-3">
                            Cadastrar
                        </button>

                        <div class="text-center">
                            <span class="small text-muted">Já tem uma conta?</span>
                            <a href="/login" class="small text-decoration-none fw-bold ms-1">Fazer Login</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>