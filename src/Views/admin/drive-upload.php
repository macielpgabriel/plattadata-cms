<?php declare(strict_types=1); use App\Core\Csrf; ?>

<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item"><a href="/admin">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Google Drive</li>
    </ol>
</nav>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 fade-in">
    <div>
        <h1 class="h3 mb-1">
            <i class="bi bi-cloud-upload me-2 text-muted"></i>Configurar Google Drive
        </h1>
        <p class="text-muted small mb-0">Configure o upload de documentos para o Google Drive</p>
    </div>
    <a href="/admin" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<?php if (App\Core\Session::has('success')): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= App\Core\Session::flash('success') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (App\Core\Session::has('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= App\Core\Session::flash('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($oauthEnabled): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="card-title">
            <i class="bi bi-person-circle me-2 text-primary"></i>Autenticação OAuth (Recomendado)
        </h5>
        <p class="text-muted small">Conecte sua conta Google para salvar arquivos no seu Drive pessoal.</p>
        
        <?php if ($oauthAuthenticated && $oauthUser): ?>
            <div class="d-flex align-items-center gap-3 mb-3 p-3 bg-light rounded">
                <?php if (!empty($oauthUser['picture'])): ?>
                    <img src="<?= htmlspecialchars($oauthUser['picture']) ?>" alt="Avatar" class="rounded-circle" width="48" height="48">
                <?php endif; ?>
                <div>
                    <strong><?= htmlspecialchars($oauthUser['name'] ?? 'Usuário') ?></strong><br>
                    <small class="text-muted"><?= htmlspecialchars($oauthUser['email'] ?? '') ?></small>
                </div>
                <span class="badge bg-success ms-auto">Conectado</span>
            </div>
            <a href="/auth/google/logout" class="btn btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1"></i> Desconectar
            </a>
        <?php else: ?>
            <a href="/auth/google/login" class="btn btn-primary">
                <i class="bi bi-google me-2"></i> Conectar ao Google Drive
            </a>
            <p class="text-muted small mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i> Você será redirecionado paraauthorize o Google. Após permitir, seus arquivos serão salvos na sua conta.
            </p>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="alert alert-warning mb-4" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>OAuth não configurado.</strong> Adicione <code>GOOGLE_OAUTH_CLIENT_ID</code> e <code>GOOGLE_OAUTH_CLIENT_SECRET</code> no arquivo .env para ativar o login OAuth.
</div>
<?php endif; ?>

<hr class="my-4">

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Como obter as credenciais OAuth (Web)
                </h5>
                <ol class="small text-muted">
                    <li class="mb-2">Acesse o <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                    <li class="mb-2">Crie ou selecione um projeto</li>
                    <li class="mb-2">Vá em <strong>APIs e Serviços</strong> > <strong>Credenciais</strong></li>
                    <li class="mb-2">Clique em <strong>Criar credenciais</strong> > <strong>ID do cliente OAuth</strong></li>
                    <li class="mb-2">Selecione <strong>Aplicativo Web</strong></li>
                    <li class="mb-2">Em <strong>URI de redirecionamento autorizado</strong>, adicione:<br>
                        <code><?= htmlspecialchars($_ENV['APP_URL'] ?? 'https://seudominio.com') ?>/auth/google/callback</code>
                    </li>
                    <li class="mb-2">Clique em <strong>Criar</strong></li>
                    <li class="mb-2">Copie o <strong>Client ID</strong> e <strong>Client Secret</strong></li>
                    <li class="mb-2">Adicione no arquivo .env</li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-upload me-2 text-success"></i>Configurar via .env (OAuth)
                </h5>
                
                <div class="mb-3">
                    <strong>Status atual:</strong>
                    <?php if ($oauthEnabled): ?>
                        <span class="badge bg-success">OAuth configurado</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Não configurado</span>
                    <?php endif; ?>
                </div>
                
                <div class="alert alert-info small mb-0">
                    <i class="bi bi-shield-check me-1"></i> O OAuth usa as credenciais do seu app. Os arquivos são salvos na conta do usuário que autorizar.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-body">
        <h5 class="card-title">
            <i class="bi bi-play me-2 text-info"></i>Testar Conexão
        </h5>
        <p class="text-muted small">Após conectar, teste a conexão:</p>
        <a href="/api/v1/health/drive" target="_blank" class="btn btn-outline-primary">
            <i class="bi bi-plug me-1"></i> Testar Google Drive
        </a>
    </div>
</div>