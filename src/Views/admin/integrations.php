<?php declare(strict_types=1); use App\Core\Auth; use App\Core\Csrf; ?>
<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/admin">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Integrações</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4 fade-in">
    <h1 class="h3 mb-0">
        <i class="bi bi-plug me-2 text-muted"></i>Integrações - API & Webhooks
    </h1>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success alert-dismissible fade-in">
        <?= e($flash) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade-in">
        <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        

        <div class="card mb-4 fade-in">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-webhook me-2"></i>Webhooks</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Use Webhooks para receber notificações em tempo real quando eventos ocorrerem.
                    Headers necessários: <code>X-Webhook-Secret: seu_secret_aqui</code>
                </p>
                
                <h6 class="mb-2">Eventos disponíveis:</h6>
                <ul class="small mb-3">
                    <li><code>favorite</code> - Quando uma empresa é favoritada ou desfavoritada</li>
                </ul>
                
                <?php if (empty($webhookSecrets)): ?>
                    <p class="text-muted">Nenhum Webhook configurado ainda.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Webhook Secret</th>
                                    <th>Status</th>
                                    <th>Criado em</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($webhookSecrets as $secret): ?>
                                    <tr>
                                        <td><?= e($secret['name']) ?></td>
                                        <td>
                                            <code class="user-select-all"><?= e(substr($secret['webhook_secret'], 0, 12)) ?>...</code>
                                            <button class="btn btn-sm btn-link p-0 ms-1" onclick="copyToClipboard('<?= e($secret['webhook_secret']) ?>')" title="Copiar">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <?php if ($secret['is_active']): ?>
                                                <span class="badge bg-success">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= format_datetime($secret['created_at']) ?></td>
                                        <td>
                                            <form method="post" action="/admin/integracoes/webhook/excluir" class="d-inline">
                                                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $secret['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este Webhook?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <hr>
                <h6 class="mb-2">Criar novo Webhook</h6>
                <form method="post" action="/admin/integracoes/webhook/criar" class="row g-2">
                    <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                    <div class="col-md-6">
                        <input type="text" name="name" class="form-control" placeholder="Nome do Webhook" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>Gerar Secret
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4 fade-in">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-code-square me-2"></i>Documentação da API</h5>
            </div>
            <div class="card-body">
                <h6 class="fw-bold">Endpoints disponíveis:</h6>
                
                <div class="mb-3">
                    <code class="small">GET /api/v1/info</code>
                    <p class="text-muted small mb-0">Informações sobre a API</p>
                </div>
                
                <div class="mb-3">
                    <code class="small">GET /api/v1/company/{cnpj}</code>
                    <p class="text-muted small mb-0">Busca dados de uma empresa</p>
                </div>
                
                <div class="mb-3">
                    <code class="small">GET /api/v1/search</code>
                    <p class="text-muted small mb-0">Busca empresas (parâmetros: q, state, page, per_page)</p>
                </div>
                
                <div class="mb-3">
                    <code class="small">GET /api/v1/rankings/states</code>
                    <p class="text-muted small mb-0">Ranking de empresas por estado</p>
                </div>
                
                <div class="mb-3">
                    <code class="small">GET /api/v1/rankings/cities</code>
                    <p class="text-muted small mb-0">Ranking de empresas por cidade</p>
                </div>
                
                <div class="mb-3">
                    <code class="small">GET /api/v1/rankings/cnae</code>
                    <p class="text-muted small mb-0">Ranking de CNAEs</p>
                </div>
                
                <hr>
                
                <h6 class="fw-bold">Integrações compatíveis:</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2">
                        <i class="bi bi-check2-circle text-success me-1"></i>
                        <strong>Zapier</strong> - Use Webhooks para triggers
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check2-circle text-success me-1"></i>
                        <strong>Make (ex-Integromat)</strong> - HTTP modules
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check2-circle text-success me-1"></i>
                        <strong>N8N</strong> - HTTP Request nodes
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check2-circle text-success me-1"></i>
                        <strong>Pipedream</strong> - Event-driven workflows
                    </li>
                    <li>
                        <i class="bi bi-check2-circle text-success me-1"></i>
                        <strong>Power Automate</strong> - HTTP connectors
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('success', 'Copiado!', 'Texto copiado para a área de transferência');
    }).catch(function(err) {
        console.error('Erro ao copiar:', err);
    });
}

function showToast(type, title, message) {
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.innerHTML = '<div class="toast ' + type + '"><strong>' + title + '</strong><br>' + message + '</div>';
    document.body.appendChild(toast);
    setTimeout(function() {
        toast.remove();
    }, 3000);
}
</script>

<style>
.toast-notification {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
}
.toast {
    background: #fff;
    border-radius: 8px;
    padding: 12px 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    animation: slideIn 0.3s ease;
}
.toast.success {
    border-left: 4px solid #198754;
}
.toast.error {
    border-left: 4px solid #dc3545;
}
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>
