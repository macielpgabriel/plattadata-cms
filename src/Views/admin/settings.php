<?php declare(strict_types=1); use App\Core\Csrf; ?>

<style>
.admin-sidebar {
    position: sticky;
    top: 80px;
    height: calc(100vh - 100px);
    overflow-y: auto;
}
.admin-sidebar .nav-link {
    border-radius: 8px;
    margin-bottom: 2px;
    color: #5b6677;
    padding: 8px 12px;
    font-size: 0.85rem;
}
.admin-sidebar .nav-link:hover {
    background: rgba(15, 118, 110, 0.08);
    color: #0f766e;
}
.admin-sidebar .nav-link.active {
    background: #0f766e;
    color: white;
}
.admin-sidebar .nav-link i {
    width: 20px;
}
.admin-section-title {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #adb5bd;
    padding: 12px 12px 4px;
    margin: 0;
}
</style>

<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item"><a href="/admin">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Configuracoes</li>
    </ol>
</nav>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= e($flash) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Sidebar -->
    <div class="col-lg-3 col-xl-2">
        <div class="admin-sidebar">
            <div class="d-none d-lg-block mb-3">
                <h5 class="mb-1"><i class="bi bi-sliders me-2"></i>Configuracoes</h5>
                <small class="text-muted">Personalize o site</small>
            </div>

            <nav class="nav flex-column">
                <a href="/admin" class="nav-link">
                    <i class="bi bi-arrow-left me-2"></i>Voltar ao Dashboard
                </a>

                <div class="admin-section-title">Identidade</div>
                <a href="#identity" class="nav-link active" data-bs-toggle="tab">
                    <i class="bi bi-house me-2"></i>Site
                </a>
                <a href="#contact" class="nav-link" data-bs-toggle="tab">
                    <i class="bi bi-envelope me-2"></i>Contato
                </a>
                <a href="#seo" class="nav-link" data-bs-toggle="tab">
                    <i class="bi bi-search me-2"></i>SEO
                </a>

                <div class="admin-section-title">Sistema</div>
                <a href="#operation" class="nav-link" data-bs-toggle="tab">
                    <i class="bi bi-sliders me-2"></i>Operacao
                </a>
                <a href="#email" class="nav-link" data-bs-toggle="tab">
                    <i class="bi bi-send me-2"></i>E-mail
                </a>

                <div class="admin-section-title">Ferramentas</div>
                <a href="#tools" class="nav-link" data-bs-toggle="tab">
                    <i class="bi bi-wrench me-2"></i>Manutencao
                </a>
            </nav>

            <div class="mt-4 pt-3 border-top">
                <div class="d-grid gap-2">
                    <form method="post" action="/admin/configuracoes">
                        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                        <button class="btn btn-brand btn-sm w-100" type="submit">
                            <i class="bi bi-check-lg me-1"></i>Salvar Tudo
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Content -->
    <div class="col-lg-9 col-xl-10">
        <form method="post" action="/admin/configuracoes">
            <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
            
            <div class="tab-content">
                <!-- Identidade -->
                <div class="tab-pane fade show active" id="identity">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 py-2"><i class="bi bi-house me-2 text-primary"></i>Identidade do Site</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Nome do site</label>
                                    <input class="form-control" type="text" name="site_name" value="<?= e($settings['site_name'] ?? config('app.name')) ?>" required>
                                    <div class="form-text">Aparece no navegador e navbar</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Descricao (SEO)</label>
                                    <input class="form-control" type="text" name="site_description" value="<?= e($settings['site_description'] ?? '') ?>" maxlength="160">
                                    <div class="form-text">Meta description para buscas</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Titulo da home</label>
                                    <input class="form-control" type="text" name="homepage_title" value="<?= e($settings['homepage_title'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Subtitulo da home</label>
                                    <input class="form-control" type="text" name="homepage_subtitle" value="<?= e($settings['homepage_subtitle'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Placeholder da busca</label>
                                    <input class="form-control" type="text" name="homepage_search_placeholder" value="<?= e($settings['homepage_search_placeholder'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Aviso curto da home</label>
                                    <input class="form-control" type="text" name="homepage_public_notice" value="<?= e($settings['homepage_public_notice'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contato -->
                <div class="tab-pane fade" id="contact">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 py-2"><i class="bi bi-envelope me-2 text-primary"></i>Informacoes de Contato</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">E-mail</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
                                        <input class="form-control" type="email" name="contact_email" value="<?= e($settings['contact_email'] ?? '') ?>" placeholder="contato@exemplo.com">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Telefone</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-telephone text-muted"></i></span>
                                        <input class="form-control" type="text" name="contact_phone" value="<?= e($settings['contact_phone'] ?? '') ?>" placeholder="(11) 0000-0000">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">WhatsApp</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-whatsapp text-muted"></i></span>
                                        <input class="form-control" type="text" name="contact_whatsapp" value="<?= e($settings['contact_whatsapp'] ?? '') ?>" placeholder="+55 11 90000-0000">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Texto do rodape</label>
                                    <input class="form-control" type="text" name="footer_text" value="<?= e($settings['footer_text'] ?? '') ?>" placeholder="Dados empresariais publicos...">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- SEO -->
                <div class="tab-pane fade" id="seo">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 py-2"><i class="bi bi-search me-2 text-primary"></i>Configuracoes SEO</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Meta robots padrao</label>
                                    <?php $robots = $settings['seo_default_robots'] ?? 'index,follow'; ?>
                                    <select class="form-select" name="seo_default_robots">
                                        <option value="index,follow" <?= $robots === 'index,follow' ? 'selected' : '' ?>>index,follow (Recomendado)</option>
                                        <option value="index,nofollow" <?= $robots === 'index,nofollow' ? 'selected' : '' ?>>index,nofollow</option>
                                        <option value="noindex,follow" <?= $robots === 'noindex,follow' ? 'selected' : '' ?>>noindex,follow</option>
                                        <option value="noindex,nofollow" <?= $robots === 'noindex,nofollow' ? 'selected' : '' ?>>noindex,nofollow</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Operacao -->
                <div class="tab-pane fade" id="operation">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 py-2"><i class="bi bi-sliders me-2 text-primary"></i>Configuracoes de Operacao</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Empresas por pagina</label>
                                    <input class="form-control" type="number" min="5" max="100" name="companies_per_page" value="<?= e($settings['companies_per_page'] ?? '15') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Limite busca publica / min</label>
                                    <input class="form-control" type="number" min="1" max="300" name="public_search_rate_limit_per_minute" value="<?= e($settings['public_search_rate_limit_per_minute'] ?? '20') ?>">
                                    <div class="form-text">Protecao contra spam</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Limite URLs no sitemap</label>
                                    <input class="form-control" type="number" min="100" max="50000" name="sitemap_company_limit" value="<?= e($settings['sitemap_company_limit'] ?? '10000') ?>">
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="public_search_enabled" name="public_search_enabled" <?= ($settings['public_search_enabled'] ?? '1') !== '0' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="public_search_enabled">
                                            <strong>Permitir busca publica sem login</strong>
                                            <div class="form-text">Quando desativado, usuarios precisam estar logados para consultar CNPJ</div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- E-mail -->
                <div class="tab-pane fade" id="email">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 py-2"><i class="bi bi-send me-2 text-primary"></i>Configuracoes de E-mail</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-4">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Importante:</strong> Estas configuracoes sao exibidas apenas como referencia. Os valores reais devem ser definidos no arquivo <code>.env</code> do servidor.
                            </div>
                            
                            <h6 class="fw-bold mb-3"><i class="bi bi-shield-check me-2 text-success"></i>Evitando Spam (Outlook/Gmail)</h6>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="card border">
                                        <div class="card-body">
                                            <h6 class="mb-3"><i class="bi bi-1-circle me-2 text-primary"></i>SPF no DNS</h6>
                                            <code class="d-block p-2 bg-dark text-light rounded small mb-2">v=spf1 include:_spf.google.com ~all</code>
                                            <p class="small text-muted mb-0">Se usar Gmail, inclua <code>include:_spf.google.com</code>.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border">
                                        <div class="card-body">
                                            <h6 class="mb-3"><i class="bi bi-2-circle me-2 text-primary"></i>DKIM no DNS</h6>
                                            <p class="small text-muted mb-0">No Google Workspace: Apps > Gmail > Autenticar e-mail. Gere a chave e adicione o registro TXT no DNS.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border">
                                        <div class="card-body">
                                            <h6 class="mb-3"><i class="bi bi-3-circle me-2 text-primary"></i>DMARC no DNS</h6>
                                            <code class="d-block p-2 bg-dark text-light rounded small mb-2">_dmarc.seudominio.com TXT v=DMARC1; p=none</code>
                                            <p class="small text-muted mb-0">Comece com <code>p=none</code> e depois mude para <code>p=quarantine</code>.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border bg-light">
                                        <div class="card-body">
                                            <h6 class="mb-3"><i class="bi bi-lightbulb me-2 text-warning"></i>Dicas</h6>
                                            <ul class="small mb-0">
                                                <li>Use <strong>senha de app</strong> (nao a senha normal)</li>
                                                <li>O <code>MAIL_FROM_ADDRESS</code> deve ser o mesmo email do SMTP</li>
                                                <li>Evite palavras como "gratuito", "urgente"</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <h6 class="fw-bold mb-3 mt-4"><i class="bi bi-code me-2"></i>Exemplo .env</h6>
                            <div class="p-3 bg-dark rounded text-light font-monospace overflow-auto" style="max-height: 200px;">
<pre class="mb-0">MAIL_ENABLED=true
MAIL_FROM_NAME="PlattaData CMS"
MAIL_FROM_ADDRESS=contato@seudominio.com
MAIL_MAILER=smtp
MAIL_SMTP_HOST=smtp.gmail.com
MAIL_SMTP_PORT=587
MAIL_SMTP_USERNAME=seu-email@gmail.com
MAIL_SMTP_PASSWORD=sua-senha-de-app
MAIL_SMTP_ENCRYPTION=tls</pre>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ferramentas -->
                <div class="tab-pane fade" id="tools">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-2 me-3">
                                            <i class="bi bi-trash fs-5"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0">Cache de APIs</h5>
                                            <small class="text-muted">Dados temporarios</small>
                                        </div>
                                    </div>
                                    <p class="text-muted small mb-3">Remove cotacoes do BCB, noticias e dados temporarios do IBGE.</p>
                                    <form method="post" action="/admin/cache/limpar">
                                        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                                        <button class="btn btn-outline-warning" type="submit">
                                            <i class="bi bi-arrow-clockwise me-2"></i>Limpar Cache
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3">
                                            <i class="bi bi-download fs-5"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0">Backup</h5>
                                            <small class="text-muted">Dump do banco</small>
                                        </div>
                                    </div>
                                    <p class="text-muted small mb-3">Gera um arquivo .sql com todas as tabelas e configuracoes.</p>
                                    <form method="post" action="/admin/backup/baixar" class="d-inline">
                                        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                                        <button type="submit" class="btn btn-outline-primary">
                                            <i class="bi bi-download me-2"></i>Baixar Backup
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <a href="/admin/api-tester" class="card border-0 shadow-sm quick-action-card h-100">
                                <div class="card-body text-center py-4">
                                    <i class="bi bi-braces text-info fs-2 mb-3"></i>
                                    <h6>API Tester</h6>
                                    <small class="text-muted">Testar requisicoes</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="/admin/observabilidade" class="card border-0 shadow-sm quick-action-card h-100">
                                <div class="card-body text-center py-4">
                                    <i class="bi bi-graph-up text-success fs-2 mb-3"></i>
                                    <h6>Observabilidade</h6>
                                    <small class="text-muted">Metricas completas</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Highlight sidebar on tab change
document.querySelectorAll('.admin-sidebar .nav-link').forEach(link => {
    link.addEventListener('shown.bs.tab', () => {
        document.querySelectorAll('.admin-sidebar .nav-link').forEach(l => l.classList.remove('active'));
        link.classList.add('active');
    });
});
</script>
