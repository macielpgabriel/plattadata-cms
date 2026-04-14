<?php declare(strict_types=1); use App\Core\Csrf; ?>
<?php $publicSearchEnabled = isset($publicSearchEnabled) ? (bool) $publicSearchEnabled : true; ?>
<?php $csrfToken = Csrf::token(); ?>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 fade-in">
            <div class="card-body p-3 p-md-4 p-lg-5">
                <h1 class="h3 h-md-4 mb-2">
                    <i class="bi bi-search me-2 text-muted"></i><?= e($title ?? 'Consulta CNPJ') ?>
                </h1>
                <p class="text-muted small mb-3 mb-md-4"><?= e($subtitle ?? '') ?></p>

                <?php if (!empty($flash)): ?>
                    <div class="alert alert-success alert-permanent fade-in"><?= e($flash) ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-permanent fade-in"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="/buscar-cnpj" id="cnpjSearchForm" class="row g-2 g-md-3">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <div class="col-8 col-md-9">
                        <label class="form-label small fw-bold"><i class="bi bi-123 me-1"></i>CNPJ</label>
                        <input type="text" name="cnpj" id="cnpjInput" class="form-control form-control-lg" placeholder="<?= e($searchPlaceholder ?? '00.000.000/0001-00') ?>" maxlength="18" required <?= !$publicSearchEnabled ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-4 col-md-3 d-grid align-items-end">
                        <button class="btn btn-brand btn-lg" type="submit" id="searchBtn" <?= !$publicSearchEnabled ? 'disabled' : '' ?>>
                            <span id="searchBtnText"><i class="bi bi-search me-1"></i>Buscar</span>
                            <span id="searchBtnSpinner" class="spinner-border spinner-border-sm" style="display: none;"></span>
                        </button>
                    </div>
                </form>

                <div id="searchFeedback" class="mt-3" style="display: none;">
                    <div class="d-flex align-items-center text-info">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                        <span id="searchFeedbackText">Buscando dados...</span>
                    </div>
                </div>

                <?php if (!\App\Core\Auth::check()): ?>
                    <div class="mt-3 text-center">
                        <span class="small text-muted">Quer salvar favoritos?</span>
                        <a href="/cadastro" class="small text-decoration-none fw-bold ms-1">Crie uma conta gratuita</a>
                    </div>
                <?php endif; ?>

                <hr class="my-3 my-md-4">
                <p class="small text-muted mb-0"><?= e($publicNotice ?? 'A busca publica consulta e salva cache local para acelerar futuras consultas.') ?></p>
                <?php if (!$publicSearchEnabled): ?>
                    <p class="small text-danger mt-2 mb-0">Busca publica desativada pelo administrador. Use o login para consultar CNPJ.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('cnpjSearchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const cnpjInput = document.getElementById('cnpjInput');
    const cnpj = cnpjInput.value.replace(/\D/g, '');
    const btn = document.getElementById('searchBtn');
    const btnText = document.getElementById('searchBtnText');
    const btnSpinner = document.getElementById('searchBtnSpinner');
    const feedback = document.getElementById('searchFeedback');
    const feedbackText = document.getElementById('searchFeedbackText');
    
    if (cnpj.length < 14) {
        cnpjInput.classList.add('is-invalid');
        return;
    }
    cnpjInput.classList.remove('is-invalid');
    
    btn.disabled = true;
    btnText.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Buscando...';
    feedback.style.display = 'block';
    feedbackText.textContent = 'Consultando dados da empresa...';
    
    const formData = new FormData();
    formData.append('cnpj', cnpjInput.value);
    formData.append('_token', '<?= e($csrfToken) ?>');
    
    fetch('/buscar-cnpj', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.redirected) {
            window.location.href = response.url;
        } else {
            return response.text();
        }
    })
    .then(html => {
        if (html) {
            document.open();
            document.write(html);
            document.close();
        }
    })
    .catch(err => {
        btn.disabled = false;
        btnText.innerHTML = '<i class="bi bi-search me-1"></i>Buscar';
        feedback.style.display = 'none';
        alert('Erro ao buscar CNPJ. Tente novamente.');
    });
});

document.getElementById('cnpjInput').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    value = value.replace(/^(\d{2})(\d)/, '$1.$2');
    value = value.replace(/^(\d{2}\.\d{3})(\d)/, '$1.$2');
    value = value.replace(/^(\d{2}\.\d{3}\.\d{3})(\d)/, '$1/$2');
    value = value.replace(/(\d{4})$/, '-$1');
    e.target.value = value;
});
</script>

<?php if (!empty($recentCompanies)): ?>
    <div class="row g-4 mb-4 fade-in stagger-1">
        <div class="col-12">
            <h5 class="mb-3"><i class="bi bi-clock-history me-2 text-muted"></i>Últimas Empresas Buscadas</h5>
            <div class="row g-3">
                <?php foreach ($recentCompanies as $comp): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm hover-elevate">
                            <div class="card-body p-3">
                                <h6 class="card-title mb-1 text-truncate">
                                    <a href="/empresas/<?= e($comp['cnpj']) ?>" class="text-decoration-none text-body fw-bold">
                                        <?= e($comp['legal_name'] ?? '') ?>
                                    </a>
                                </h6>
                                <div class="small text-muted mb-2">CNPJ: <?= e($comp['cnpj']) ?></div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="badge bg-secondary-subtle text-muted border small"><?= e($comp['city'] ?? '-') ?>/<?= e($comp['state'] ?? '-') ?></span>
                                    <span class="x-small text-muted"><?= e(format_date($comp['updated_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 text-center">
                <a href="/empresas" class="btn btn-outline-secondary btn-sm">Ver todas as empresas</a>
            </div>
        </div>
    </div>
<?php endif; ?>
