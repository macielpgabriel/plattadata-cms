<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 fade-in mx-auto auth-card-wide">
                <div class="card-body p-4 p-md-5 text-center">
                    <?php if (!empty($success)): ?>
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill text-success fs-1"></i>
                        </div>
                        <h1 class="h4 fw-bold mb-3">E-mail Confirmado!</h1>
                        <p class="text-muted mb-4">
                            Olá, <?= e($userName ?? '') ?>! 
                            Seu e-mail foi verificado com sucesso. 
                            Agora você pode fazer login e usar sua conta.
                        </p>
                        <a href="/login" class="btn btn-brand w-100 mb-3">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Fazer Login
                        </a>
                    <?php else: ?>
                        <div class="mb-4">
                            <i class="bi bi-exclamation-triangle-fill text-danger fs-1"></i>
                        </div>
                        <h1 class="h4 fw-bold mb-3">Link Inválido</h1>
                        <p class="text-muted mb-4">
                            <?= e($message ?? 'Este link expirou ou já foi utilizado.') ?>
                        </p>
                        <?php if (!empty($success) === false): ?>
                            <form method="post" action="/verificar-email/reenviar" id="resend-form">
                                <input type="hidden" name="_token" value="<?= e(\App\Core\Csrf::token()) ?>">
                                <div class="mb-3">
                                    <input type="email" name="email" class="form-control" placeholder="Seu e-mail" required>
                                </div>
                                <button type="submit" class="btn btn-brand w-100" id="resend-btn">
                                    <i class="bi bi-envelope me-2"></i>Reenviar Confirmação
                                </button>
                            </form>
                        <?php endif; ?>
                        <div class="mt-3">
                            <a href="/login" class="small text-decoration-none">Voltar para Login</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($success)): ?>
<script>
document.getElementById('resend-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('resend-btn');
    const form = this;
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
    
    fetch('/verificar-email/reenviar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: '_token=<?= e(\App\Core\Csrf::token()) ?>&email=' + encodeURIComponent(form.email.value)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            btn.classList.remove('btn-brand');
            btn.classList.add('btn-success');
            btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>E-mail Enviado!';
            form.email.value = '';
        } else {
            btn.disabled = false;
            btn.innerHTML = originalText;
            alert(data.error || 'Erro ao enviar');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Erro de conexão');
    });
});
</script>
<?php endif; ?>
