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
    
    // Gerar senha que atende todos os requisitos
    const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const lower = 'abcdefghijklmnopqrstuvwxyz';
    const numbers = '0123456789';
    const special = '!@#$%&*';
    
    // Garantir pelo menos um de cada tipo
    password += upper[Math.floor(Math.random() * upper.length)];
    password += lower[Math.floor(Math.random() * lower.length)];
    password += numbers[Math.floor(Math.random() * numbers.length)];
    password += special[Math.floor(Math.random() * special.length)];
    
    // Preencher o resto com random seguro
    const array = new Uint32Array(8);
    crypto.getRandomValues(array);
    for (let i = 0; i < 8; i++) {
        password += chars[array[i] % chars.length];
    }
    
    // Embaralhar
    password = password.split('').sort(() => Math.random() - 0.5).join('');
    
    // Verificar HIBP antes de mostrar
    checkHIBP(password, function(isPwned) {
        if (isPwned) {
            // Gerar novamente se foi encontrada em vazamento
            generatePassword();
            return;
        }
        
        document.getElementById('password').value = password;
        document.getElementById('password_confirmation').value = password;
        
        // Mostrar campos após gerar
        document.getElementById('password').type = 'text';
        document.getElementById('password_confirmation').type = 'text';
        
        // Feedback visual
        const btn = document.querySelector('.btn-outline-primary');
        btn.classList.add('btn-success');
        setTimeout(() => btn.classList.remove('btn-success'), 1500);
    });
}

async function checkHIBP(password, callback) {
    // Usar k-anonymity: enviar apenas os 5 primeiros caracteres do SHA1
    const encoder = new TextEncoder();
    const data = encoder.encode(password);
    const hashBuffer = await crypto.subtle.digest('SHA-1', data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0').toUpperCase()).join('');
    
    const prefix = hashHex.substring(0, 5);
    const suffix = hashHex.substring(5);
    
    try {
        const response = await fetch('https://api.pwnedpasswords.com/range/' + prefix, {
            headers: { 'User-Agent': 'Plattadata-CMS' }
        });
        
        if (!response.ok) {
            callback(false);
            return;
        }
        
        const text = await response.text();
        const lines = text.split('\n');
        
        for (const line of lines) {
            const [hashSuffix, count] = line.split(':');
            if (hashSuffix.trim() === suffix) {
                console.log('Senha encontrada em vazamentos: ' + count.trim() + 'x');
                callback(true);
                return;
            }
        }
        
        callback(false);
    } catch (e) {
        console.error('Erro ao verificar HIBP:', e);
        callback(false);
    }
}
</script>
