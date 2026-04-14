<?php declare(strict_types=1); use App\Core\Csrf; ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 fade-in mx-auto auth-card">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <h1 class="h3 fw-bold">Entrar</h1>
                        <p class="text-muted">Acesse sua conta no Plattadata.</p>
                    </div>

                    <?php if (!empty($flash)): ?>
                        <div class="alert alert-danger small"><?= e($flash) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success small"><?= e($success) ?></div>
                    <?php endif; ?>

                    <form method="post" action="/login" class="needs-validation">
                        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label small fw-bold">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" autocomplete="username" required autofocus>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <label for="password" class="form-label small fw-bold">Senha</label>
                                <a href="/recuperar-senha" class="x-small text-decoration-none text-muted">Esqueceu a senha?</a>
                            </div>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="var i=document.getElementById('password');var ic=this.querySelector('i');if(i.type==='password'){i.type='text';ic.classList.remove('bi-eye');ic.classList.add('bi-eye-slash');}else{i.type='password';ic.classList.remove('bi-eye-slash');ic.classList.add('bi-eye');}">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-4 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label small text-muted" for="remember">Lembrar de mim</label>
                        </div>

                        <button type="submit" class="btn btn-brand w-100 mb-3">
                            Entrar
                        </button>

                        <div class="text-center mt-3">
                            <span class="small text-muted">Não tem uma conta?</span>
                            <a href="/cadastro" class="small text-decoration-none fw-bold ms-1">Cadastre-se</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>