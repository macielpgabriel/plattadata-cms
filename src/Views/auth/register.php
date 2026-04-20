<?php include __DIR__ . '/layouts/auth.php'; ?>

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
                                <button class="btn btn-outline-primary" type="button" id="generatePassword" title="Gerar senha segura">
                                    <i class="bi bi-key"></i>
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

<script>
document.getElementById('generatePassword').addEventListener('click', generatePassword);

function generatePassword() {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
    let password = '';
    
    const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const lower = 'abcdefghijklmnopqrstuvwxyz';
    const numbers = '0123456789';
    const special = '!@#$%&*';
    
    password += upper[Math.floor(Math.random() * upper.length)];
    password += lower[Math.floor(Math.random() * lower.length)];
    password += numbers[Math.floor(Math.random() * numbers.length)];
    password += special[Math.floor(Math.random() * special.length)];
    
    const array = new Uint32Array(8);
    crypto.getRandomValues(array);
    for (let i = 0; i < 8; i++) {
        password += chars[array[i] % chars.length];
    }
    
    password = password.split('').sort(() => Math.random() - 0.5).join('');
    
    checkHIBP(password, function(isPwned) {
        if (isPwned) {
            generatePassword();
            return;
        }
        
        document.getElementById('password').value = password;
        document.getElementById('password_confirmation').value = password;
        
        document.getElementById('password').type = 'text';
        document.getElementById('password_confirmation').type = 'text';
        
        const btn = document.getElementById('generatePassword');
        btn.classList.add('btn-success');
        setTimeout(() => btn.classList.remove('btn-success'), 1500);
    });
}

async function checkHIBP(password, callback) {
    const encoder = new TextEncoder();
    const data = encoder.encode(password);
    const hashBuffer = await crypto.subtle.digest('SHA-1', data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0').toUpperCase()).join('');
    
    const prefix = hashHex.substring(0, 5);
    const suffix = hashHex.substring(5);
    
    try {
        const response = await fetch('https://api.pwnedpasswords.com/range/' + prefix);
        const text = await response.text();
        
        if (text.includes(suffix)) {
            callback(true);
            return;
        }
    } catch (e) {}
    
    callback(false);
}
</script>