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
                            <div class="d-flex gap-1">
                                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required minlength="12" autofocus>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password', this)" title="Mostrar/Esconder">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-outline-primary" type="button" onclick="generatePassword()" title="Gerar senha segura">
                                    <i class="bi bi-shuffle"></i>
                                </button>
                            </div>
                            <div class="form-text">Mínimo 12 caracteres, letras maiúsculas/minúsculas, número e caractere especial</div>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label small fw-bold">Confirmar Senha</label>
                            <div class="d-flex gap-1">
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required minlength="12">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_confirmation', this)" title="Mostrar/Esconder">
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

<script>
function togglePassword(fieldId, btn) {
    const input = document.getElementById(fieldId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.querySelector('i').className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        btn.querySelector('i').className = 'bi bi-eye';
    }
}

function generatePassword() {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
    let password = '';
    for (let i = 0; i < 12; i++) {
        password += chars[Math.floor(Math.random() * chars.length)];
    }
    document.getElementById('password').value = password;
    document.getElementById('password_confirmation').value = password;
    
    // Mostrar campos após gerar
    document.getElementById('password').type = 'text';
    document.getElementById('password_confirmation').type = 'text';
    
    // Atualizar ícones
    document.querySelector('[data-target="password"] i').className = 'bi bi-eye-slash';
    document.querySelector('[data-target="password_confirmation"] i').className = 'bi bi-eye-slash';
}
</script>
