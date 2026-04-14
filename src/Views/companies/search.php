<?php declare(strict_types=1); use App\Core\Csrf; ?>
<?php $csrfToken = Csrf::token(); ?>

<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item"><a href="/empresas">Empresas</a></li>
        <li class="breadcrumb-item active" aria-current="page">Consultar CNPJ</li>
    </ol>
</nav>

<div class="row justify-content-center fade-in">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-md-4 p-lg-5">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start gap-2 mb-3">
                    <h1 class="h4 mb-0">
                        <i class="bi bi-search me-2 text-muted"></i>Buscar CNPJ
                    </h1>
                    <a href="/empresas" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-list me-1"></i>Ver lista
                    </a>
                </div>
                <p class="text-muted small mb-3">Consulte dados oficiais e gere uma pagina dedicada para a empresa.</p>

                <?php if (!empty($flash)): ?>
                    <div class="alert alert-success alert-permanent shadow-sm"><?= e($flash) ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-permanent shadow-sm"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="/empresas/busca" class="mt-4" id="cnpj-search-form">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-bold mb-2" for="cnpj-input">Digite o CNPJ da Empresa</label>
                        <div class="input-group input-group-lg shadow-sm">
                            <input
                                type="text"
                                name="cnpj"
                                id="cnpj-input"
                                class="form-control"
                                maxlength="18"
                                placeholder="00.000.000/0001-00"
                                required
                                autocomplete="off"
                                inputmode="numeric"
                            >
                            <button type="submit" class="btn btn-brand px-4" id="search-btn">
                                <span id="search-btn-text"><i class="bi bi-search"></i><span class="d-none d-sm-inline ms-1">Consultar</span></span>
                            </button>
                        </div>
                        <div class="form-text mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Digite apenas números ou o CNPJ formatado.
                        </div>
                    </div>
                </form>

                <div id="search-feedback" class="mt-3" style="display: none;">
                    <div class="d-flex align-items-center text-info">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                        <span id="search-feedback-text">Buscando dados...</span>
                    </div>
                </div>

                <div class="mt-3 text-center">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        A consulta pode levar alguns segundos
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('cnpj-search-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const cnpjInput = document.getElementById('cnpj-input');
    const cnpj = cnpjInput.value.replace(/\D/g, '');
    const btn = document.getElementById('search-btn');
    const btnText = document.getElementById('search-btn-text');
    const feedback = document.getElementById('search-feedback');
    const feedbackText = document.getElementById('search-feedback-text');
    
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
    
    fetch('/empresas/busca', {
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
        btnText.innerHTML = '<i class="bi bi-search"></i><span class="d-none d-sm-inline ms-1">Consultar</span>';
        feedback.style.display = 'none';
        alert('Erro ao buscar CNPJ. Tente novamente.');
    });
});

document.getElementById('cnpj-input').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    value = value.replace(/^(\d{2})(\d)/, '$1.$2');
    value = value.replace(/^(\d{2}\.\d{3})(\d)/, '$1.$2');
    value = value.replace(/^(\d{2}\.\d{3}\.\d{3})(\d)/, '$1/$2');
    value = value.replace(/(\d{4})$/, '-$1');
    e.target.value = value;
});
</script>
