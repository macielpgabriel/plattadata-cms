<?php declare(strict_types=1); use App\Core\Csrf; ?>
<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item"><a href="/login">Login</a></li>
        <li class="breadcrumb-item active" aria-current="page">Verificacao 2FA</li>
    </ol>
</nav>

<div class="row justify-content-center mx-0 fade-in">
    <div class="col-11 col-sm-10 col-md-5 px-0">
        <div class="card">
            <div class="card-body p-3 p-md-4 p-lg-5">
                <div class="text-center mb-4">
                    <i class="bi bi-phone-lock fs-1 text-brand"></i>
                    <h1 class="h4 mt-2 mb-1">Verificacao em duas etapas</h1>
                    <p class="text-muted small mb-0">Informe o codigo enviado para seu e-mail.</p>
                </div>

                <?php if (!empty($flash)): ?>
                    <div class="alert alert-danger alert-permanent"><?= e($flash) ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-permanent"><?= e($success) ?></div>
                <?php endif; ?>

                <form method="post" action="/login/2fa" class="d-grid gap-3" novalidate>
                    <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                    <div>
                        <label class="form-label small fw-bold" for="code">Codigo de verificacao</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="text" name="code" id="code" class="form-control form-control-lg text-center" inputmode="numeric" maxlength="8" required placeholder="000000" autocomplete="one-time-code">
                        </div>
                        <div class="form-help text-center mt-2">Verifique sua caixa de entrada ou spam</div>
                    </div>
                    <button class="btn btn-brand btn-lg mt-2" type="submit">
                        <i class="bi bi-check-circle me-2"></i>Validar codigo
                    </button>
                    <a href="/login" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Voltar ao login
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>
