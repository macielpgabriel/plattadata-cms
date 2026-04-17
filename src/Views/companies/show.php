<?php declare(strict_types=1); use App\Core\Auth; use App\Core\Csrf; ?>
<?php $isHidden = (bool) ($company['is_hidden'] ?? false); ?>
<?php $canSeeHidden = Auth::can(['admin', 'editor']); ?>
<?php $lgpdProfile = (string) ($lgpd['profile'] ?? 'public'); ?>
<?php $isPublicProfile = $lgpdProfile === 'public'; ?>
<?php $isAdminProfile = $lgpdProfile === 'admin'; ?>
<?php $isAuthenticatedNonAdmin = !$isPublicProfile && !$isAdminProfile; ?>
<?php
$qsaQualLabels = [
    '05' => 'Administrador', '06' => 'Cidadão Estrangeiro', '08' => 'Contador',
    '09' => 'Diretor', '10' => 'Diretor', '11' => 'Conselheiro', '12' => 'Presidente',
    '13' => 'Proprietário', '14' => 'Responsável Técnico', '15' => 'Sci',
    '16' => 'Presidente', '17' => 'Vice-Presidente', '18' => 'Diretor-Presidente',
    '19' => 'Vice-Diretor', '20' => 'Socio', '21' => 'Socio PF', '22' => 'Socio PJ',
    '23' => 'Socio Estrangeiro', '24' => 'Fundador', '25' => 'Ex-Socio',
    '30' => 'Administrador Judicial', '31' => 'Liquidante', '32' => 'Interventor',
    '35' => 'Gestor', '36' => 'Síndico', '37' => 'Comissário', '38' => 'Inventariante',
    '39' => 'Admin. Judicial', '40' => 'Curador', '41' => 'Tutor', '42' => 'Guardião',
];
$qsaByQualification = [];
foreach (($qsa ?? []) as $partner):
    $qual = trim((string) ($partner['qual'] ?? $partner['qualificacao_socio'] ?? '-'));
    if ($qual === '') { $qual = '-'; }
    $qsaByQualification[$qual] = ($qsaByQualification[$qual] ?? 0) + 1;
endforeach; ?>
<?php
// Garante que o raw_data seja um array para facilitar a busca de fallbacks
$rawArr = is_array($rawData ?? null) ? $rawData : json_decode($company['raw_data'] ?? '{}', true);
if (!is_array($rawArr)) {
    $rawArr = [];
}
?>
<?php
$financialData = $enrichedData['financial_data'] ?? [];
$partnerData = $enrichedData['partner_data'] ?? [];
$marketData = $enrichedData['market_data'] ?? [];
$complianceData = $enrichedData['compliance_data'] ?? [];
$socialData = $enrichedData['social_data'] ?? [];
$predictiveData = $enrichedData['predictive_data'] ?? [];
$extendedData = $enrichedData['extended_data'] ?? [];
$competitorsList = $competitors ?? [];
$mentionData = $mentionData ?? [];
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item"><a href="/empresas">Empresas</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($company['legal_name'] ?? 'Detalhes') ?></li>
    </ol>
</nav>

<section class="premium-company-page">
<?php if ($isHidden && !$canSeeHidden): ?>
    <div class="card shadow-sm border-0 mb-4 fade-in">
        <div class="card-body p-4 text-center">
            <div class="display-1 text-muted mb-3">
                <i class="bi bi-shield-lock"></i>
            </div>
            <h2 class="h3">Empresa Removida</h2>
            <p class="text-muted lead">
                A pedido do proprietário ou representante legal, as informações detalhadas desta empresa foram removidas da visualização pública em nossa plataforma.
            </p>
            <hr class="my-4">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <p class="small text-muted mb-0">
                        <strong>Razão Social:</strong> <?= e($company['legal_name'] ?? '-') ?><br>
                        <strong>CNPJ:</strong> <?= e($cnpj) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
    <?php if (!empty($company['logo_url'])): ?>
    <div class="flex-shrink-0">
        <img src="<?= e($company['logo_url']) ?>" alt="Logo" class="rounded" style="max-height: 60px; max-width: 120px; object-fit: contain;">
    </div>
    <?php endif; ?>
    <div class="flex-grow-1">
        <h1 class="h4 h-md-3 mb-1"><?= e($company['legal_name'] ?? 'Empresa') ?></h1>
        <div class="text-muted small d-flex align-items-center flex-wrap" style="gap: 0.25rem;">
            <span class="text-muted">CNPJ:</span>
            <span id="cnpjDisplay" class="font-monospace fw-bold"><?= e($cnpj) ?></span>
            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="copyToClipboard('cnpjDisplay', '<?= e($cnpj) ?>', this)" title="Copiar CNPJ">
                <i class="bi bi-clipboard me-1"></i><span class="d-none d-sm-inline">Copiar</span>
            </button>
            <?php if (!empty($company['status'])): ?>
                <span class="badge bg-<?= $company['status'] === 'ativa' ? 'success' : 'secondary' ?>">
                    <?= e($company['status']) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if (!empty($enrichment['generated_at'])): ?>
            <div class="small text-muted mt-1">
                <i class="bi bi-arrow-clockwise me-1"></i>
                Enriquecimento v<?= e((string) ($enrichment['version'] ?? '?')) ?> em <?= e(format_datetime($enrichment['generated_at'])) ?>
            </div>
        <?php endif; ?>
        
        <?php
        $consultStats = $consultStats ?? [];
        $consultTrend = $consultTrend ?? [];
        $totalConsults = (int) ($consultStats['total_consults'] ?? 0);
        ?>
        <?php if ($totalConsults > 0): ?>
        <div class="small text-muted mt-1">
            <i class="bi bi-eye me-1"></i>
            Consultada <strong><?= number_format($totalConsults, 0, ',', '.') ?></strong> vez(es)
            <?php if (!empty($consultTrend)): ?>
                <?php 
                $trend = $consultTrend['trend'] ?? 'stable';
                $growth = $consultTrend['growth_percent'] ?? 0;
                $trendIcon = $trend === 'up' ? 'bi-graph-up-arrow text-success' : ($trend === 'down' ? 'bi-graph-down-arrow text-danger' : 'bi-dash text-secondary');
                ?>
                <i class="bi <?= $trendIcon ?> ms-1" title="Tendência semanal: <?= ($growth >= 0 ? '+' : '') . $growth ?>%"></i>
                <span class="text-<?= $trend === 'up' ? 'success' : ($trend === 'down' ? 'danger' : 'secondary') ?>">
                    <?= ($growth >= 0 ? '+' : '') . $growth ?>%
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
    $waService = new \App\Services\WhatsAppService();
    $waShareValid = $waService->validatePhoneNumber((string) ($company['phone'] ?? ''));
    $waShareUrl = $waShareValid 
        ? $waService->generateWhatsAppUrl((string) ($company['phone'] ?? ''), 'Confira os dados oficiais da empresa ' . ($company['legal_name'] ?? 'Empresa') . ' no PlattaData: ' . (string)config('app.url') . '/empresas/' . $cnpj)
        : 'https://wa.me/?text=' . urlencode('Confira os dados oficiais da empresa ' . ($company['legal_name'] ?? 'Empresa') . ' no PlattaData: ' . (string)config('app.url') . '/empresas/' . $cnpj);
    ?>
    <div class="d-flex flex-wrap gap-2 w-100 w-md-auto no-print">
        <a href="<?= e($waShareUrl ?? '') ?>" 
           target="_blank" rel="noopener" class="btn btn-sm" style="background-color:#25D366;border:2px solid #25D366;color:#fff;font-weight:600;box-shadow:0 2px 8px rgba(37,211,102,0.3);">
            <i class="bi bi-whatsapp me-1" style="color:#fff;"></i>WhatsApp
        </a>
        <button class="btn btn-sm btn-outline-secondary print-btn">
            <i class="bi bi-printer me-1"></i>Imprimir
        </button>
        
        <?php 
        $lastSync = strtotime($company['last_synced_at'] ?? '2000-01-01');
        $daysSinceSync = (time() - $lastSync) / 86400;
        $canRefresh = Auth::check() || $daysSinceSync > 15;
        if ($canRefresh): 
        ?>
            <form action="/empresas/<?= e($cnpj) ?>/atualizar" method="POST" class="d-inline">
                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                <button type="submit" class="btn btn-sm btn-brand" title="<?= Auth::check() ? 'Atualizar dados' : 'Atualizar (disponivel apos 15 dias)' ?>">
                    <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
                </button>
            </form>
        <?php endif; ?>

        <?php if (Auth::check()): ?>
            <div x-data="{ 
                isFav: <?= $isFavorite ? 'true' : 'false' ?>,
                loading: false,
                toggle() {
                    if (this.loading) return;
                    this.loading = true;
                    fetch('/favoritos/<?= e($cnpj) ?>/toggle', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: '_token=<?= e(Csrf::token()) ?>'
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status) {
                            this.isFav = data.status === 'added';
                            if (typeof showToast === 'function') {
                                showToast('success', data.status === 'added' ? 'Favoritado' : 'Removido', 
                                    data.status === 'added' ? 'Empresa adicionada aos favoritos' : 'Empresa removida dos favoritos');
                            }
                        } else {
                            if (typeof showToast === 'function') {
                                showToast('error', 'Erro', 'Nao foi possivel favoritar');
                            }
                        }
                        this.loading = false;
                    })
                    .catch(() => { 
                        if (typeof showToast === 'function') {
                            showToast('error', 'Erro', 'Erro de conexao com o servidor');
                        }
                        this.loading = false; 
                    });
                }
            }" class="d-inline-block">
                <button type="button" 
                        class="btn btn-sm" 
                        :class="isFav ? 'btn-warning' : 'btn-outline-warning'"
                        @click="toggle()"
                        :disabled="loading">
                    <i class="bi" :class="isFav ? 'bi-star-fill' : 'bi-star'"></i>
                    <span class="d-none d-sm-inline ms-1" x-text="isFav ? 'Favoritado' : 'Favoritar'"></span>
                </button>
            </div>
            <?php if (Auth::check() && Auth::user()['id']): ?>
            <div x-data="{ 
                notifyChange: <?= !empty($notifyChange) ? 'true' : 'false' ?>,
                loadingNotify: false,
                toggleNotify() {
                    if (this.loadingNotify) return;
                    this.loadingNotify = true;
                    fetch('/changes/<?= e($cnpj) ?>/subscribe', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: '_token=<?= e(Csrf::token()) ?>'
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.notifyChange = !this.notifyChange;
                        if (typeof showToast === 'function') {
                            showToast('success', this.notifyChange ? 'Notificar' : 'Parar', 
                                this.notifyChange ? 'Notificacoes ativadas' : 'Notificacoes desativadas');
                        }
                        this.loadingNotify = false;
                    })
                    .catch(() => { this.loadingNotify = false; });
                }
            }" class="d-inline-block">
                <button type="button" 
                        class="btn btn-sm" 
                        :class="notifyChange ? 'btn-info' : 'btn-outline-info'"
                        @click="toggleNotify()"
                        :disabled="loadingNotify">
                    <i class="bi" :class="notifyChange ? 'bi-bell-fill' : 'bi-bell'"></i>
                    <span class="d-none d-sm-inline ms-1" x-text="notifyChange ? 'Notificando' : 'Notificar'"></span>
                </button>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <a href="/empresas/<?= e($cnpj) ?>/remover" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-shield-lock me-1"></i>Solicitar Remoção
        </a>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success alert-permanent fade-in"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (!empty($info)): ?>
    <div class="alert alert-info alert-permanent fade-in"><?= e($info) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-permanent fade-in"><?= e($error) ?></div>
<?php endif; ?>

<?php if (!empty($lgpd['classification']['contains_personal_data']) && !$isPublicProfile): ?>
    <div class="alert alert-info alert-permanent small fade-in">
        <i class="bi bi-shield-lock me-1"></i>
        Dados pessoais protegidos pela camada LGPD. Perfil: <strong><?= e($lgpdProfile) ?></strong>.
    </div>
<?php endif; ?>

<?php if (Auth::can(['admin', 'editor'])): ?>
    <div class="d-flex flex-wrap gap-2 mb-4 fade-in">
        <form method="post" action="/empresas/<?= e($cnpj) ?>/atualizar">
            <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
            <button class="btn btn-brand" type="submit">
                <i class="bi bi-arrow-clockwise me-1"></i>Atualizar dados da API
            </button>
        </form>
        <?php if (Auth::can(['admin'])): ?>
            <div x-data="{ confirming: false }" class="d-inline-block">
                <button type="button" class="btn btn-outline-danger" x-show="!confirming" @click="confirming = true">
                    <i class="bi bi-trash me-1"></i>Excluir do Cache
                </button>
                <template x-if="confirming">
                    <div class="d-flex gap-2">
                        <form method="post" action="/empresas/<?= e($cnpj) ?>/excluir">
                            <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                            <button class="btn btn-danger" type="submit">
                                <i class="bi bi-check me-1"></i>Confirmar
                            </button>
                        </form>
                        <button type="button" class="btn btn-light" @click="confirming = false">
                            <i class="bi bi-x me-1"></i>Cancelar
                        </button>
                    </div>
                </template>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-2 g-md-3 mb-3 mb-md-4 fade-in">
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body p-2 p-md-3 text-center text-md-start">
                <div class="small text-muted mb-1">
                    <i class="bi bi-eye me-1"></i>Visualizações
                </div>
                <div class="h5 mb-0"><?= number_format((int) ($company['views'] ?? 0), 0, ',', '.') ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body p-2 p-md-3 text-center text-md-start">
                <div class="small text-muted mb-1">
                    <i class="bi bi-people me-1"></i>Socios (QSA)
                </div>
                <div class="h5 mb-0"><?= e((string) ($stats['qsa_count'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body p-2 p-md-3 text-center text-md-start">
                <div class="small text-muted mb-1">
                    <i class="bi bi-list-ul me-1"></i>CNAEs
                </div>
                <div class="h5 mb-0"><?= e((string) ($stats['total_cnae_count'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body p-2 p-md-3 text-center text-md-start">
                <div class="small text-muted mb-1">
                    <i class="bi bi-search me-1"></i>Consultas
                </div>
                <div class="h5 mb-0"><?= e((string) ($stats['query_count'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100 fade-in stagger-1">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-info-circle me-1 text-muted"></i>Dados principais
                </h2>
                <dl class="row mb-0 small">
                    <dt class="col-5 col-sm-4">Nome fantasia</dt>
                    <dd class="col-7 col-sm-8"><?= e($company['trade_name'] ?: '-') ?></dd>
                    <dt class="col-5 col-sm-4">Situacao</dt>
                    <dd class="col-7 col-sm-8">
                        <span class="text-uppercase small fw-bold"><?= e($company['status'] ?: '-') ?></span>
                        <?php if (!empty($rawData['data_situacao_cadastral'])): ?>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">desde <?= e(format_date($rawData['data_situacao_cadastral'])) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($rawData['motivo_situacao_cadastral'])): ?>
                            <small class="text-muted d-block" style="font-size: 0.7rem;"><?= e($rawData['motivo_situacao_cadastral']) ?></small>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-5 col-sm-4">Cidade/UF</dt>
                    <dd class="col-7 col-sm-8">
                        <?php if (!empty($cityUrl)): ?>
                            <a href="<?= e($cityUrl) ?>" class="text-decoration-none">
                                <?= e(($company['city'] ?: '-') . '/' . ($company['state'] ?: '-')) ?>
                            </a>
                        <?php elseif (!empty($rawData['_cep_details']['ibge_code']) || !empty($rawData['_cep_details']['ibge'])): ?>
                            <a href="/localidades/cidade/<?= e((string) ($rawData['_cep_details']['ibge_code'] ?? $rawData['_cep_details']['ibge'])) ?>" class="text-decoration-none">
                                <?= e(($company['city'] ?: '-') . '/' . ($company['state'] ?: '-')) ?>
                            </a>
                        <?php else: ?>
                            <?= e(($company['city'] ?: '-') . '/' . ($company['state'] ?: '-')) ?>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-5 col-sm-4">Endereco</dt>
                    <dd class="col-7 col-sm-8"><?= e($address ?: '-') ?>
                        <?php 
                        $complemento = $rawData['complemento'] ?? $rawData['address_complement'] ?? '';
                        if (!empty($complemento)): ?>
                            <small class="text-muted d-block" style="font-size: 0.8rem;"><?= e($complemento) ?></small>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-5 col-sm-4">E-mail</dt>
                    <dd class="col-7 col-sm-8 text-break">
                        <?= e($company['email'] ?: '-') ?>
                        <?php if (!empty($company['email_verified'])): ?>
                            <span class="badge bg-success ms-1" title="E-mail verificado"><i class="bi bi-check-circle"></i></span>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-5 col-sm-4">Telefone</dt>
                    <dd class="col-7 col-sm-8">
                        <?php 
                        $phone = (string) ($company['phone'] ?? '');
                        if (!empty($phone)): ?>
                            <?= e($phone) ?>
                            <?php
                            $waResult = $waService->validateAndFormat($phone);
                            if ($waResult['valid']): ?>
                                <a href="<?= e($waResult['whatsapp_url']) ?>?text=<?= rawurlencode('Olá! Vi sua empresa ' . ($company['legal_name'] ?? '') . ' no PlattaData e gostaria de mais informações.') ?>" 
                                   target="_blank" rel="noopener" 
                                   class="btn btn-sm ms-2" 
                                   style="background-color:#25D366;border:none;color:#fff;"
                                   title="Abrir WhatsApp">
                                    <i class="bi bi-whatsapp"></i>
                                </a>
                            <?php else: ?>
                                <span class="badge bg-warning ms-2" title="Número não válido para WhatsApp">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= e($company['phone'] ?: $rawData['ddd_telefone_1'] ?: ($rawData['telefone'] ?? '-')) ?>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-5 col-sm-4">Website</dt>
                    <dd class="col-7 col-sm-8">
                        <?php if (!empty($company['website'])): ?>
                            <a href="<?= e($company['website']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                <?= e($company['website']) ?> <i class="bi bi-box-arrow-up-right small"></i>
                            </a>
                            <?php if (!empty($company['website_verified'])): ?>
                                <span class="badge bg-success ms-1" title="Website verificado"><i class="bi bi-check-circle"></i></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-5 col-sm-4">Abertura</dt>
                    <dd class="col-7 col-sm-8"><?= e(format_date($company['opened_at'] ?? $rawData['data_inicio_atividade'] ?? $rawData['opened_at'] ?? null)) ?></dd>
                    <?php if (!empty($rawData['tipo_estabelecimento']) || !empty($rawData['matriz_filial'])): ?>
                    <dt class="col-5 col-sm-4">Tipo</dt>
                    <dd class="col-7 col-sm-8"><?= e(ucfirst($rawData['tipo_estabelecimento'] ?? $rawData['matriz_filial'] ?? '-')) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($company['employees_estimate'])): ?>
                    <dt class="col-5 col-sm-4">Funcionarios</dt>
                    <dd class="col-7 col-sm-8"><?= e($company['employees_estimate']) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($company['state_registration'])): ?>
                    <dt class="col-5 col-sm-4">Insc. Estadual</dt>
                    <dd class="col-7 col-sm-8"><?= e($company['state_registration']) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($company['municipal_registration'])): ?>
                    <dt class="col-5 col-sm-4">Insc. Municipal</dt>
                    <dd class="col-7 col-sm-8"><?= e($company['municipal_registration']) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($company['region_type'])): ?>
                    <dt class="col-5 col-sm-4">Regiao</dt>
                    <dd class="col-7 col-sm-8">
                        <?php 
                        $regionLabels = ['metropolitana' => 'Metropolitana', 'interior' => 'Interior', 'capital' => 'Capital', 'rural' => 'Rural'];
                        echo $regionLabels[$company['region_type']] ?? e($company['region_type']);
                        ?>
                    </dd>
                    <?php endif; ?>
                    <?php if (!empty($rawData['situacao_especial'])): ?>
                    <dt class="col-5 col-sm-4">Situacao Especial</dt>
                    <dd class="col-7 col-sm-8 text-muted"><?= e($rawData['situacao_especial']) ?></dd>
                    <?php endif; ?>
                </dl>
                <div class="d-flex gap-2 flex-wrap mt-3">
                    <?php if (!empty($mapLinks['google_search'])): ?>
                        <a href="<?= e((string) $mapLinks['google_search']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-geo-alt me-1"></i>Google Maps
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($mapLinks['google_coordinates'])): ?>
                        <a href="<?= e((string) $mapLinks['google_coordinates']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark">
                            <i class="bi bi-crosshair me-1"></i>Coordenadas
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100 fade-in stagger-2">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-map me-1 text-muted"></i>Mapa</span>
                    <?php if (!empty($coordinates)): ?>
                        <span class="badge bg-success" title="Coordenadas GPS disponiveis">
                            <i class="bi bi-crosshair me-1"></i>GPS
                        </span>
                    <?php endif; ?>
                </h2>
                <div class="ratio ratio-16x9">
                    <iframe
                        src="<?= e($mapEmbedUrl) ?>"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        title="Mapa da empresa"></iframe>
                </div>
                <?php if (!empty($coordinates)): ?>
                    <small class="text-muted mt-2 d-block">
                        <i class="bi bi-geo-alt me-1"></i>
                        Lat: <?= number_format((float)$coordinates[0], 6, ',', '.') ?>, 
                        Long: <?= number_format((float)$coordinates[1], 6, ',', '.') ?>
                    </small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100 fade-in stagger-3">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-building me-1 text-muted"></i>Informacoes Estruturais
                </h2>
                <dl class="row mb-0 small">
                    <dt class="col-5 col-sm-5">Capital Social</dt>
                    <dd class="col-7 col-sm-7">
                        <?php
                        $isCooperative = stripos((string) ($company['legal_nature'] ?? $rawData['natureza_juridica'] ?? ''), 'Cooperativa') !== false;
                        $capitalSocial = (float) ($company['capital_social'] ?? 0);
                        if ($isCooperative): ?>
                            <span class="text-muted small">Nao aplicavel a cooperativas</span>
                        <?php elseif ($capitalSocial > 0): ?>
                            <span class="fw-bold text-success">R$ <?= number_format($capitalSocial, 2, ',', '.') ?></span>
                            <?php if ($capitalUsd !== null && $usdRate !== null): ?>
                                <br><small class="text-muted">USD <?= number_format($capitalUsd, 2, ',', '.') ?> <span class="small">(PTAX <?= number_format($usdRate, 4, ',', '.') ?>)</span></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-5 col-sm-5">Porte</dt>
                    <dd class="col-7 col-sm-7">
                        <?php 
                        $porteVal = '-';
                        $invalidValues = ['', '0', '00', '-', 'null', 'NULL', 'NAO INFORMADO'];
                        $porteMap = [
                            '01' => 'Micro Empresa (ME)', '1' => 'Micro Empresa (ME)', 'ME' => 'Micro Empresa (ME)',
                            '03' => 'Empresa de Pequeno Porte (EPP)', '3' => 'Empresa de Pequeno Porte (EPP)', 'EPP' => 'Empresa de Pequeno Porte (EPP)',
                            '05' => 'Demais (Grande Porte)', '5' => 'Demais (Grande Porte)', 'DEMAIS' => 'Demais (Grande Porte)'
                        ];

                        $porteCandidates = [
                            $company['company_size'] ?? '',
                            $extendedData['porte'] ?? '',
                            $rawArr['porte'] ?? '',
                            $rawArr['descricao_porte'] ?? '',
                            $rawArr['porte_descricao'] ?? '',
                            $rawArr['estabelecimento']['porte']['descricao'] ?? '',
                            $rawArr['estabelecimento']['porte'] ?? '',
                            $rawArr['porte_prefecture'] ?? ''
                        ];

                        foreach ($porteCandidates as $candidate) {
                            if (is_array($candidate)) {
                                $candidate = $candidate['descricao'] ?? $candidate['text'] ?? $candidate['description'] ?? $candidate['id'] ?? '';
                            }
                            $c = trim((string)$candidate);
                            $upperC = mb_strtoupper($c);
                            if (!in_array($upperC, $invalidValues, true)) {
                                $porteVal = $porteMap[$upperC] ?? ($porteMap[$c] ?? $c);
                                break;
                            }
                        }
                        echo e((string)$porteVal);
                        ?>
                    </dd>
                    <dt class="col-5 col-sm-5">Natureza Juridica</dt>
                    <dd class="col-7 col-sm-7 text-muted"><?= e($company['legal_nature'] ?? $rawData['natureza_juridica'] ?? $rawData['tipo_de_sociedade'] ?? '-') ?></dd>
                    <?php if (!empty($company['revenue_estimate'])): ?>
                    <dt class="col-5 col-sm-5">Receita Estimada</dt>
                    <dd class="col-7 col-sm-7">
                        <span class="fw-bold">R$ <?= number_format((float) $company['revenue_estimate'], 2, ',', '.') ?></span>
                        <small class="text-muted d-block" style="font-size: 0.7rem;">Valor anual estimado</small>
                    </dd>
                    <?php endif; ?>
                    <dt class="col-5 col-sm-5">Simples Nacional</dt>
                    <dd class="col-7 col-sm-7">
                        <?php
                        $simples = $taxData['simples_opt_in'] ?? $company['simples_opt_in'] ?? $rawData['opcao_pelo_simples'] ?? $rawData['simples_nacional'] ?? null;
                        $simplesSince = $taxData['simples_since'] ?? $rawData['data_opcao_pelo_simples'] ?? $rawData['simples_nacional_data_inicio'] ?? null;
                        if ($simples === null) {
                            echo '<span class="badge bg-secondary-subtle text-muted border" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="right" title="Este dado nao foi retornado pela Receita Federal. A empresa pode nao estar obrigada ou os dados ainda nao foram atualizados.">?</span> ';
                            echo '<span class="text-muted small">Nao disponivel</span> ';
                            echo '<a href="https://www8.receita.fazenda.gov.br/SimplesNacional/" target="_blank" class="text-decoration-none small" title="Consultar diretamente no site da Receita"><i class="bi bi-box-arrow-up-right"></i></a>';
                        } else {
                            echo (bool)$simples 
                                ? '<span class="badge bg-success">Optante</span>' 
                                : '<span class="badge bg-secondary">Nao optante</span>';
                            if ($simplesSince) echo ' <small class="text-muted" title="Data de opcao pelo Simples">desde ' . format_date($simplesSince) . '</small>';
                            if ((bool)$simples) echo ' <a href="https://www8.receita.fazenda.gov.br/SimplesNacional/" target="_blank" class="small text-decoration-none" title="Consultar no site da Receita"><i class="bi bi-box-arrow-up-right"></i></a>';
                        }
                        ?>
                    </dd>
                    <dt class="col-5 col-sm-5">MEI</dt>
                    <dd class="col-7 col-sm-7">
                        <?php
                        $mei = $taxData['mei_opt_in'] ?? $company['mei_opt_in'] ?? $rawData['opcao_pelo_mei'] ?? $rawData['mei'] ?? null;
                        $meiSince = $taxData['mei_since'] ?? $rawData['data_opcao_pelo_mei'] ?? $rawData['mei_data_inicio'] ?? null;
                        if ($mei === null) {
                            echo '<span class="badge bg-secondary-subtle text-muted border" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="right" title="Este dado nao foi retornado pela Receita Federal. A empresa pode nao ser um Microempreendedor Individual ou os dados ainda nao foram atualizados.">?</span> ';
                            echo '<span class="text-muted small">Nao disponivel</span> ';
                            echo '<a href="https://www.gov.br/empresas-e-negocios/pt-br/empreendedor" target="_blank" class="text-decoration-none small" title="Consultar no Portal do Empreendedor"><i class="bi bi-box-arrow-up-right"></i></a>';
                        } else {
                            echo (bool)$mei 
                                ? '<span class="badge bg-success">Microempreendedor Individual</span>' 
                                : '<span class="badge bg-secondary">Nao e MEI</span>';
                            if ($meiSince && (bool)$mei) echo ' <small class="text-muted" title="Data de opcao pelo MEI">desde ' . format_date($meiSince) . '</small>';
                            if ((bool)$mei) echo ' <a href="https://www.gov.br/empresas-e-negocios/pt-br/empreendedor" target="_blank" class="small text-decoration-none" title="Portal do Empreendedor"><i class="bi bi-box-arrow-up-right"></i></a>';
                        }
                        ?>
                    </dd>
                    <dt class="col-5 col-sm-5">Inscrição Estadual</dt>
                    <dd class="col-7 col-sm-7">
                        <?php
                        $ies = is_string($taxData['state_registrations'] ?? null)
                            ? json_decode($taxData['state_registrations'], true)
                            : ($taxData['state_registrations'] ?? []);

                        // Fallback para provedores que retornam IE em formato alternativo.
                        if (empty($ies) && !empty($rawData['inscricoes_estaduais']) && is_array($rawData['inscricoes_estaduais'])) {
                            foreach ($rawData['inscricoes_estaduais'] as $ieRaw) {
                                if (!is_array($ieRaw)) {
                                    continue;
                                }
                                $ies[] = [
                                    'uf' => $ieRaw['uf'] ?? ($company['state'] ?? ''),
                                    'ie' => $ieRaw['inscricao_estadual'] ?? ($ieRaw['ie'] ?? ''),
                                    'active' => ($ieRaw['ativo'] ?? false) === true || strtoupper((string) ($ieRaw['situacao'] ?? '')) === 'ATIVO',
                                ];
                            }
                        }

                        // Mais fallbacks para IE
                        if (empty($ies) && !empty($rawData['inscricao_estadual'])) {
                            $ies[] = [
                                'uf' => $company['state'] ?? '',
                                'ie' => $rawData['inscricao_estadual'],
                                'active' => null,
                            ];
                        }
                        if (empty($ies) && !empty($rawData['inscricao_estadual_matriz'])) {
                            $ies[] = [
                                'uf' => $company['state'] ?? '',
                                'ie' => $rawData['inscricao_estadual_matriz'],
                                'active' => true,
                            ];
                        }
                        if (empty($ies) && !empty($rawData['estado_inscricao_estadual'])) {
                            $ies[] = [
                                'uf' => $rawData['estado_inscricao_estadual'] ?? $company['state'] ?? '',
                                'ie' => $rawData['estado_inscricao_estadual'] ?? '',
                                'active' => null,
                            ];
                        }

                        if (empty($ies)) {
                            echo '<span class="badge bg-secondary-subtle text-muted border" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="right" title="A Inscricao Estadual (IE) e emitida pela Secretaria da Fazenda de cada estado. Este dado pode nao estar disponivel se a empresa nao possui estabelecimento naquele estado ou se a consulta nao retornou resultados.">?</span> ';
                            echo '<span class="text-muted small">Nao localizada</span>';
                        } else {
                            $renderedIe = 0;
                            foreach ($ies as $ie) {
                                $active = $ie['active'] ?? null;
                                $status = $active === null ? 'bg-secondary' : ((bool)$active ? 'bg-success' : 'bg-danger');
                                $statusIcon = $active === null ? '?' : ((bool)$active ? '<i class="bi bi-check-circle"></i>' : '<i class="bi bi-x-circle"></i>');
                                $uf = (string) ($ie['uf'] ?? '');
                                $num = (string) ($ie['ie'] ?? '');
                                if ($num === '') {
                                    continue;
                                }
                                $renderedIe++;
                                $title = $active === null ? 'Status desconhecido' : ((bool)$active ? 'Ativa' : 'Inativa');
                                echo '<div class="mb-1">';
                                echo '<span class="badge ' . $status . ' me-1" title="' . e($title) . '">' . e($uf) . '</span>';
                                echo '<span class="small">' . e($num) . '</span> ';
                                echo '<span class="text-' . ($active ? 'success' : ($active === false ? 'danger' : 'muted')) . '" title="' . e($title) . '">' . $statusIcon . '</span>';
                                echo '</div>';
                            }
                            if ($renderedIe === 0) {
                                echo '<span class="badge bg-secondary-subtle text-muted border">?</span> <span class="text-muted small">Nao localizada</span>';
                            }
                        }
                        ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <?php $compliance = is_array($rawData['_compliance'] ?? null) ? $rawData['_compliance'] : []; ?>
        <div class="card h-100 border-<?= in_array(($compliance['status'] ?? ''), ['warning', 'partial'], true) ? 'warning' : 'success' ?> fade-in stagger-4" style="border-left: 5px solid !important;">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-shield-check me-1 text-muted"></i>Compliance e Sancoes
                </h2>
                <?php if (($compliance['status'] ?? '') === 'not_checked'): ?>
                    <div class="alert alert-secondary alert-permanent small py-1 px-2">
                        <i class="bi bi-info-circle me-1"></i>Token do Governo Federal nao configurado.
                    </div>
                <?php elseif (($compliance['status'] ?? '') === 'warning'): ?>
                    <div class="alert alert-danger alert-permanent p-2 mb-3 small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Alerta:</strong> Encontrada(s) <?= e((string) ($compliance['total_sanctions'] ?? 0)) ?> sancao(oes).
                    </div>
                    <ul class="small mb-0 list-unstyled">
                        <?php if (!empty($compliance['details']['ceis'])): ?><li><span class="badge bg-danger me-1">CEIS</span> Empresa Inidônea</li><?php endif; ?>
                        <?php if (!empty($compliance['details']['cnep'])): ?><li><span class="badge bg-danger me-1">CNEP</span> Empresa Punida</li><?php endif; ?>
                    </ul>
                <?php elseif (($compliance['status'] ?? '') === 'partial'): ?>
                    <div class="alert alert-warning alert-permanent p-2 mb-3 small">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Não foi possível validar todas as listas (CEIS/CNEP/CEPIM) neste momento.
                    </div>
                    <p class="small text-muted mb-0">Tente atualizar em alguns instantes para uma verificação completa.</p>
                <?php elseif (($compliance['status'] ?? '') === 'clean'): ?>
                    <div class="text-success small mb-2">
                        <i class="bi bi-check-circle me-1"></i> Nada encontrado no CEIS/CNEP/CEPIM.
                    </div>
                    <p class="small text-muted mb-0">Consulta em tempo real via Portal da Transparencia.</p>
                <?php else: ?>
                    <div class="text-muted small mb-2">
                        <i class="bi bi-hourglass-split me-1"></i> Verificação de compliance indisponível no momento.
                    </div>
                    <p class="small text-muted mb-0">Tente novamente em alguns instantes para validar CEIS/CNEP/CEPIM.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100 fade-in stagger-1">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-newspaper me-1 text-muted"></i>Notícias e Mercado
                </h2>
                <?php $newsList = $marketNews ?? []; ?>
                <?php if (empty($newsList)): ?>
                    <p class="small text-muted mb-0">Nenhuma notícia recente encontrada.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($newsList as $news): ?>
                            <a href="<?= e($news['link']) ?>" target="_blank" rel="noopener" class="list-group-item list-group-item-action px-0 border-0 mb-2">
                                <div class="d-flex w-100 justify-content-between">
                                    <h3 class="mb-1 small fw-bold"><?= e($news["title"]) ?></h3>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= e($news['source']) ?></small>
                                    <small class="text-muted" style="font-size: 0.7rem;"><?= date('d/m/Y', strtotime($news['pubDate'])) ?></small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2 text-end">
                        <small class="text-muted" style="font-size: 0.65rem;">Fonte: Google News RSS</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100 fade-in stagger-1">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-list-check me-1 text-muted"></i>CNAE detalhado
                </h2>
                <div class="mb-3">
                    <div class="fw-semibold small">Principal</div>
                    <?php
                $mainCnaeCode = trim((string) ($mainCnae['codigo'] ?? $mainCnae['code'] ?? ''));
                $mainCnaeDesc = trim((string) ($mainCnae['descricao'] ?? $mainCnae['text'] ?? $mainCnae['description'] ?? ($rawData['cnae_fiscal_descricao'] ?? '')));
                $cleanMainCode = preg_replace('/\D/', '', $mainCnaeCode);
                $cleanMainDesc = preg_replace('/\D/', '', $mainCnaeDesc);
                    ?>
                    <div class="small">
                    <?php if ($mainCnaeCode !== '' && $mainCnaeDesc !== '' && $cleanMainCode !== $cleanMainDesc): ?>
                            <?= e($mainCnaeCode) ?> - <?= e($mainCnaeDesc) ?>
                        <?php elseif ($mainCnaeCode !== ''): ?>
                            <?= e($mainCnaeCode) ?>
                        <?php elseif ($mainCnaeDesc !== ''): ?>
                            <?= e($mainCnaeDesc) ?>
                        <?php else: ?>
                            <span class="text-muted">Nao informado.</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="fw-semibold small mb-2">Secundarios</div>
                <ul class="mb-0 small ps-3">
                    <?php 
                    $displaySecCnaes = !empty($extendedData['cnaes_secundarios']) 
                        ? $extendedData['cnaes_secundarios'] 
                        : ($secondaryCnaes ?? []);
                    ?>
                    <?php if (empty($displaySecCnaes)): ?>
                        <li class="text-muted">Nenhum informado.</li>
                    <?php else: ?>
                        <?php foreach (array_slice($displaySecCnaes, 0, 10) as $cnae): ?>
                            <?php
                            $secCode = is_array($cnae) ? trim((string) ($cnae['codigo'] ?? $cnae['code'] ?? $cnae['cnae'] ?? '')) : trim((string) $cnae);
                            $secDesc = is_array($cnae) ? trim((string) ($cnae['descricao'] ?? $cnae['text'] ?? $cnae['description'] ?? ($cnae['cnae_descricao'] ?? ($cnae['texto'] ?? ($cnae['cnae_fiscal_descricao'] ?? ''))))) : '';
                            
                            $cleanSecCode = preg_replace('/\D/', '', $secCode);
                            $cleanSecDesc = preg_replace('/\D/', '', $secDesc);
                            ?>
                            <li>
                                <?php if ($secCode !== '' && $secDesc !== '' && ($cleanSecCode !== $cleanSecDesc || strlen($secDesc) > 5)): ?>
                                    <?= e($secCode) ?> - <?= e($secDesc) ?>
                                <?php elseif ($secCode !== ''): ?>
                                    <?= e($secCode) ?>
                                <?php elseif ($secDesc !== ''): ?>
                                    <?= e($secDesc) ?>
                                <?php else: ?>
                                    <span class="text-muted">Nao informado</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if (count($secondaryCnaes) > 10): ?>
                            <li class="text-muted small">... e mais <?= count($secondaryCnaes) - 10 ?> itens.</li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100 fade-in stagger-2">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-people me-1 text-muted"></i>QSA (Quadro de Socios)
                </h2>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 small">
                        <thead class="d-none d-md-table-header-group">
                            <tr><th>Socio</th><th>Cargo</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($qsa)): ?>
                                <tr><td colspan="2" class="text-muted">Nenhum registro.</td></tr>
                            <?php else: ?>
                                <?php foreach ($qsa as $partner): ?>
                                    <tr>
                                        <td class="<?= $isPublicProfile ? 'text-muted' : '' ?>">
                                            <div class="d-md-none small text-muted">Socio:</div>
                                            <?= e((string) ($partner['nome_socio'] ?? ($partner['nome'] ?? 'Protegido'))) ?>
                                        </td>
                                        <td>
                                            <div class="d-md-none small text-muted">Cargo:</div>
                                            <?php 
                                            $qualCode = (string) ($partner['qual'] ?? $partner['qualificacao_socio'] ?? '-');
                                            echo e($qsaQualLabels[$qualCode] ?? $qualCode);
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100 fade-in stagger-3">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-geo-alt me-1 text-muted"></i>Localidade Enriquecida
                </h2>
                <?php
                $cepDetails = is_array($rawData['_cep_details'] ?? null) ? $rawData['_cep_details'] : [];
                $resolvedCep = is_array($cepDetails['resolved'] ?? null) ? $cepDetails['resolved'] : [];
                $bairro = $cepDetails['bairro'] ?? $rawData['bairro'] ?? ($rawData['district'] ?? ($rawData['street'] ?? '-'));
                $fonte = $cepDetails['source'] ?? ($dbEnrichment['cep_source'] ?? '-');
                if ($fonte === '' || $fonte === null) {
                    $fonte = '-';
                }
                $ibgeCode = $cepDetails['ibge_code'] ?? $cepDetails['ibge'] ?? $resolvedCep['ibge_code'] ?? $resolvedCep['ibge'] ?? ($company['municipal_ibge_code'] ?? ($dbEnrichment['ibge_code'] ?? null));
                $ddd = $cepDetails['ddd'] ?? $resolvedCep['ddd'] ?? ($dbEnrichment['ddd'] ?? ($rawData['ddd'] ?? null));
                if (!is_string($ddd) && $ddd !== null) {
                    $ddd = null;
                }
                if ($ddd && strlen($ddd) > 2) {
                    $ddd = null;
                }
                if ($ddd === '' || $ddd === null) {
                    $companyUf = ($company['state'] ?? $rawData['uf'] ?? '') . '';
                    $dddState = $companyUf !== '' ? mb_strtoupper(trim((string)$companyUf)) : '';
                    if ($dddState) {
                        $ddds = [
                            'DF'=>'61','GO'=>'62','MS'=>'67','MT'=>'65',
                            'AC'=>'68','AM'=>'92','AP'=>'96','PA'=>'91','RO'=>'69','RR'=>'95',
                            'AL'=>'82','BA'=>'71','CE'=>'85','MA'=>'98','PB'=>'83','PE'=>'81','PI'=>'86','RN'=>'84','SE'=>'79',
                            'ES'=>'27','MG'=>'31','RJ'=>'21','SP'=>'11',
                            'PR'=>'41','RS'=>'51','SC'=>'48',
                        ];
                        $ddd = $ddds[$dddState] ?? '-';
                    }
                }
                if ($ddd === '' || $ddd === null) {
                    $ddd = '-';
                }
                $regiao = $municipalityDetails['regiao'] ?? $resolvedCep['regiao'] ?? ($dbEnrichment['region_name'] ?? '-');
                ?>
                <dl class="row mb-0 small">
                    <dt class="col-5 col-sm-4">Fonte</dt><dd class="col-7 col-sm-8"><?= e($fonte) ?></dd>
                    <dt class="col-5 col-sm-4">Cod. IBGE</dt>
                    <dd class="col-7 col-sm-8">
                        <?php if (!empty($ibgeCode)): ?>
                            <code class="bg-light border text-dark px-1 rounded"><?= e((string) $ibgeCode) ?></code>
                            <a href="https://cidades.ibge.gov.br/brasil/panorama?codigo=<?= e((string) $ibgeCode) ?>" target="_blank" class="text-decoration-none small" title="Ver cidade no IBGE">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    <dt class="col-5 col-sm-4">DDD</dt><dd class="col-7 col-sm-8"><code class="bg-light border text-dark px-1 rounded"><?= e($ddd) ?></code></dd>
                    <dt class="col-5 col-sm-4">Bairro</dt><dd class="col-7 col-sm-8"><?= e($bairro) ?></dd>
                    <dt class="col-5 col-sm-4">Regiao</dt><dd class="col-7 col-sm-8"><?= e($regiao) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <!-- Stock market data temporarily removed -->

    <?php if (!empty($weather)): ?>
    <div class="col-lg-6" id="weather-card-wrapper">
        <div class="card h-100 fade-in stagger-3">
            <div class="card-body" id="weather-card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-cloud-sun me-1 text-muted"></i>Clima da Cidade
                </h2>
                <?php 
                $current = $weather['current'] ?? null;
                $forecast = $weather['forecast'] ?? [];
                $today = $forecast[0] ?? null;
                $temp = $current['temp'] ?? $today['max_temp'] ?? null;
                $condition = $current['condition'] ?? $today['condition'] ?? null;
                $updateTime = $weather['fetched_at'] ?? ($weather['updated_at'] ?? null);
                if (!$updateTime) {
                    $updateTime = date('Y-m-d H:i:s');
                }
                ?>
                <div class="text-center mb-3" id="weather-content">
                    <?php if ($temp): ?>
                        <div class="display-4 fw-bold"><?= e($temp) ?>°C</div>
                    <?php endif; ?>
                    <?php if ($condition): ?>
                        <div class="text-muted"><?= e($condition) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($weather['source'])): ?>
                        <small class="text-muted"><?= e($weather['source']) ?></small>
                    <?php endif; ?>
                    <small class="text-muted d-block mt-1" id="weather-updated">Atualizado em <?= date('d/m H:i', strtotime($updateTime)) ?></small>
                    <button class="btn btn-sm btn-outline-secondary mt-2 refresh-weather-btn" data-ibge="<?= e((string) ($weatherIbgeCode ?? $company['municipal_ibge_code'] ?? '')) ?>">
                        <i class="bi bi-arrow-clockwise"></i> Atualizar Clima
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($financialData) || !empty($marketData)): ?>
    <div class="col-12">
        <div class="card mb-4 fade-in">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-graph-up me-1 text-muted"></i>Analise de Mercado
                    <?php if (!empty($marketData['source']) && $marketData['source'] !== 'calculated'): ?>
                    <span class="badge bg-success float-end">Dados Reais</span>
                    <?php endif; ?>
                </h2>
                <?php 
                $hasMarketData = !empty($marketData['competitors_count']) || !empty($marketData['market_trend']) || !empty($marketData['competition_score']) || !empty($marketData['sector_growth']);
                ?>
                <?php if (!$hasMarketData): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-info-circle fs-1 mb-2 d-block opacity-50"></i>
                    <p class="mb-1">Dados de mercado não disponíveis para esta empresa.</p>
                    <small>Analise disponivel para pequenas e medias empresas com dados de CNAE e localizacao.</small>
                </div>
                <?php else: ?>
                <div class="row g-3">
                    <?php if (!empty($marketData)): ?>
                    <div class="col-md-3">
                        <div class="border rounded p-3 text-center">
                            <div class="h3 mb-1"><?= number_format((int) ($marketData['competitors_count'] ?? 0), 0, ',', '.') ?></div>
                            <small class="text-muted">Concorrentes na Regiao</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <?php 
                        $trend = $marketData['market_trend'] ?? 'estavel';
                        $trendColors = ['crescente' => 'success', 'estavel' => 'secondary', 'declinante' => 'danger'];
                        $trendIcons = ['crescente' => 'bi-graph-up-arrow', 'estavel' => 'bi-dash-lg', 'declinante' => 'bi-graph-down-arrow'];
                        ?>
                        <div class="border rounded p-3 text-center">
                            <div class="h3 mb-1">
                                <i class="bi <?= $trendIcons[$trend] ?? 'bi-dash-lg' ?> text-<?= $trendColors[$trend] ?? 'secondary' ?>"></i>
                            </div>
                            <small class="text-muted text-<?= $trendColors[$trend] ?? 'secondary' ?>">Tendencia: <?= ucfirst($trend) ?></small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted">Competicao</small>
                                <span class="badge bg-<?= ($marketData['competition_score'] ?? 50) > 70 ? 'danger' : (($marketData['competition_score'] ?? 50) > 40 ? 'warning' : 'success') ?>">
                                    <?= (int) ($marketData['competition_score'] ?? 50) ?>/100
                                </span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-<?= ($marketData['competition_score'] ?? 50) > 70 ? 'danger' : (($marketData['competition_score'] ?? 50) > 40 ? 'warning' : 'success') ?>" 
                                     role="progressbar" style="width: <?= (int) ($marketData['competition_score'] ?? 50) ?>%"></div>
                            </div>
                            <small class="text-muted"><?= ($marketData['competition_score'] ?? 50) > 70 ? 'Alta' : (($marketData['competition_score'] ?? 50) > 40 ? 'Media' : 'Baixa') ?></small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <?php if (!empty($marketData['sector_growth'])): ?>
                        <?php 
                        $growthColors = ['alto' => 'success', 'estavel' => 'warning', 'baixo' => 'danger'];
                        $growthLabels = ['alto' => 'Crescimento', 'estavel' => 'Estavel', 'baixo' => 'Queda'];
                        ?>
                        <div class="border rounded p-3 text-center">
                            <div class="h6 mb-1 text-<?= $growthColors[$marketData['sector_growth']] ?? 'secondary' ?>">
                                <?= $growthLabels[$marketData['sector_growth']] ?? 'N/A' ?>
                            </div>
                            <small class="text-muted">Setor</small>
                        </div>
                        <?php else: ?>
                        <div class="border rounded p-3 text-center">
                            <div class="h6 mb-1">-</div>
                            <small class="text-muted">Setor</small>
                        </div>
                            <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($marketData['source'])): ?>
                    <div class="col-12 mt-2">
                        <small class="text-muted">
                            <i class="bi bi-database me-1"></i>
                            Fonte: 
                            <?php if ($marketData['source'] === 'database'): ?>
                                Banco de dados local
                            <?php elseif ($marketData['source'] === 'ibge'): ?>
                                IBGE
                            <?php elseif ($marketData['source'] === 'bcb'): ?>
                                Banco Central do Brasil
                            <?php else: ?>
                                Calculado internamente
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($financialData) && !empty($financialData['revenue_estimate'])): ?>
                    <div class="col-md-6">
                        <div class="border rounded p-3">
                            <small class="text-muted">Faturamento Estimado (CNAE)</small>
                            <div class="h4 text-success">
                                R$ <?= number_format((float) $financialData['revenue_estimate'], 2, ',', '.') ?>
                            </div>
                            <small class="text-muted">Baseado na media do CNAE + porte da empresa</small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($competitorsList) && count($competitorsList) > 0): ?>
    <div class="col-12">
        <div class="card mb-4 fade-in">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-diagram-3 me-1 text-muted"></i>Concorrentes na Regiao
                </h2>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="small text-muted">
                            <tr>
                                <th>CNPJ</th>
                                <th>Empresa</th>
                                <th class="text-end">Similaridade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($competitorsList as $comp): ?>
                            <tr>
                                <td class="font-monospace"><?= e($comp['competitor_cnpj']) ?></td>
                                <td><a href="/empresas/<?= e($comp['competitor_cnpj']) ?>" class="text-decoration-none"><?= e($comp['competitor_name']) ?></a></td>
                                <td class="text-end">
                                    <span class="badge bg-<?= ($comp['similarity_score'] ?? 80) > 70 ? 'success' : 'secondary' ?>">
                                        <?= $comp['similarity_score'] ?? 80 ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($mentionData['social_mentions'])): ?>
    <div class="col-12">
        <div class="card mb-4 fade-in">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-megaphone me-1 text-muted"></i>Mencoes na Web
                    <small class="text-muted float-end fw-normal" style="font-size: 0.75rem;">Verifique em cada plataforma</small>
                </h2>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (!empty($mentionData['social_mentions']['twitter'])): ?>
                    <a href="<?= e($mentionData['social_mentions']['twitter']['url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-twitter me-1"></i>Twitter
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($mentionData['social_mentions']['linkedin'])): ?>
                    <a href="<?= e($mentionData['social_mentions']['linkedin']['url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-linkedin me-1"></i>LinkedIn
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($mentionData['social_mentions']['instagram'])): ?>
                    <a href="<?= e($mentionData['social_mentions']['instagram']['url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-instagram me-1"></i>Instagram
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($mentionData['social_mentions']['facebook'])): ?>
                    <a href="<?= e($mentionData['social_mentions']['facebook']['url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-facebook me-1"></i>Facebook
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($mentionData['google_alerts']['url'])): ?>
                    <a href="<?= e($mentionData['google_alerts']['url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark">
                        <i class="bi bi-newspaper me-1"></i>Google News
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($complianceData) || !empty($predictiveData)): ?>
    <div class="col-lg-6">
        <div class="card mb-4 fade-in">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-shield-check me-1 text-muted"></i>Compliance e Risco
                    <?php if (!empty($complianceData['source']) && $complianceData['source'] !== 'calculated'): ?>
                    <span class="badge bg-success float-end">Dados Reais</span>
                    <?php endif; ?>
                </h2>
                <dl class="row mb-0 small">
                    <?php if (!empty($complianceData['status']) && $complianceData['status'] !== 'regular'): ?>
                    <dt class="col-5">Status</dt>
                    <dd class="col-7">
                        <?php 
                        $statusColors = ['regular' => 'success', 'pendente' => 'warning', 'irregular' => 'danger'];
                        ?>
                        <span class="badge bg-<?= $statusColors[$complianceData['status']] ?? 'secondary' ?>">
                            <?= ucfirst($complianceData['status']) ?>
                        </span>
                    </dd>
                    <?php endif; ?>
                    <?php if (!empty($complianceData['risk_level'])): ?>
                    <dt class="col-5">Nivel de Risco</dt>
                    <dd class="col-7">
                        <?php 
                        $riskColors = ['baixo' => 'success', 'medio' => 'warning', 'alto' => 'danger', 'critico' => 'dark'];
                        $risk = $complianceData['risk_level'];
                        ?>
                        <span class="badge bg-<?= $riskColors[$risk] ?? 'secondary' ?>"><?= ucfirst($risk) ?></span>
                    </dd>
                    <?php endif; ?>
                    <?php if (!empty($complianceData['risk_score'])): ?>
                    <dt class="col-5">Score de Risco</dt>
                    <dd class="col-7">
                        <div class="d-flex align-items-center">
                            <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                <div class="progress-bar bg-<?= ($complianceData['risk_score'] ?? 50) > 70 ? 'danger' : (($complianceData['risk_score'] ?? 50) > 40 ? 'warning' : 'success') ?>" 
                                     style="width: <?= (int) ($complianceData['risk_score'] ?? 50) ?>%"></div>
                            </div>
                            <span><?= (int) ($complianceData['risk_score'] ?? 50) ?>/100</span>
                        </div>
                    </dd>
                    <?php endif; ?>
                    <?php if (!empty($complianceData['last_balance_sheet'])): ?>
                    <dt class="col-5">Ultimo Balanço</dt>
                    <dd class="col-7"><?= format_date($complianceData['last_balance_sheet']) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($complianceData['negative_records'])): ?>
                    <dt class="col-5">Registros Neg.</dt>
                    <dd class="col-7">
                        <span class="badge bg-danger"><?= count($complianceData['negative_records']) ?></span>
                    </dd>
                    <?php endif; ?>
                </dl>
                <?php if (!empty($complianceData['source'])): ?>
                <hr>
                <small class="text-muted">
                    <i class="bi bi-database me-1"></i>
                    Fonte: 
                    <?php if ($complianceData['source'] === 'portal_transparencia'): ?>
                        Portal da Transparência
                    <?php elseif ($complianceData['source'] === 'receita_federal'): ?>
                        Receita Federal
                    <?php else: ?>
                        Calculado internamente
                    <?php endif; ?>
                </small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($predictiveData)): ?>
    <div class="col-lg-6">
        <div class="card mb-4 fade-in">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-brain me-1 text-muted"></i>Analise Preditiva
                    <?php if (!empty($predictiveData['source']) && $predictiveData['source'] !== 'calculated'): ?>
                    <span class="badge bg-success float-end">Dados Reais</span>
                    <?php endif; ?>
                </h2>
                <dl class="row mb-0 small">
                    <?php if (!empty($predictiveData['payment_behavior']) && $predictiveData['payment_behavior'] !== 'indisponivel'): ?>
                    <dt class="col-5">Comportamento</dt>
                    <dd class="col-7">
                        <?php 
                        $behaviorColors = [
                            'bom' => 'success',
                            'regular' => 'warning', 
                            'devedor' => 'danger',
                            'restrictivo' => 'dark',
                        ];
                        $behaviorLabels = [
                            'bom' => 'Bom',
                            'regular' => 'Regular',
                            'devedor' => 'Devedor',
                            'restrictivo' => 'Restritivo',
                        ];
                        ?>
                        <span class="badge bg-<?= $behaviorColors[$predictiveData['payment_behavior']] ?? 'secondary' ?>">
                            <?= $behaviorLabels[$predictiveData['payment_behavior']] ?? $predictiveData['payment_behavior'] ?>
                        </span>
                    </dd>
                    <?php endif; ?>
                    <?php if (!empty($predictiveData['government_debts'])): ?>
                    <dt class="col-5">Dividas Gov.</dt>
                    <dd class="col-7">
                        <span class="badge bg-danger">Sim</span>
                    </dd>
                    <?php endif; ?>
                    <?php if (!empty($predictiveData['sirobe'])): ?>
                    <dt class="col-5">SIRUBE</dt>
                    <dd class="col-7">
                        <span class="badge bg-dark">Registro Restritivo</span>
                    </dd>
                    <?php endif; ?>
                    <?php if (!empty($predictiveData['credit_score'])): ?>
                    <dt class="col-5">Score de Credito</dt>
                    <dd class="col-7">
                        <div class="d-flex align-items-center">
                            <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                <div class="progress-bar bg-<?= ($predictiveData['credit_score'] ?? 50) > 70 ? 'success' : (($predictiveData['credit_score'] ?? 50) > 40 ? 'warning' : 'danger') ?>" 
                                     style="width: <?= (int) ($predictiveData['credit_score'] ?? 50) ?>%"></div>
                            </div>
                            <span class="fw-bold"><?= (int) ($predictiveData['credit_score'] ?? 50) ?>/100</span>
                        </div>
                    </dd>
                    <?php endif; ?>
                    <?php if (!empty($predictiveData['inactivity_probability'])): ?>
                    <dt class="col-5">Prob. Inatividade</dt>
                    <dd class="col-7">
                        <span class="badge bg-<?= ($predictiveData['inactivity_probability'] ?? 20) > 50 ? 'danger' : (($predictiveData['inactivity_probability'] ?? 20) > 30 ? 'warning' : 'success') ?>">
                            <?= number_format((float) $predictiveData['inactivity_probability'], 1, ',', '.') ?>%
                        </span>
                    </dd>
                    <?php endif; ?>
                    <?php if (!empty($predictiveData['growth_potential'])): ?>
                    <?php 
                    $growth = $predictiveData['growth_potential'];
                    $growthColors = ['alto' => 'success', 'medio' => 'warning', 'baixo' => 'secondary'];
                    ?>
                    <dt class="col-5">Potencial de Crescimento</dt>
                    <dd class="col-7">
                        <span class="badge bg-<?= $growthColors[$growth] ?? 'secondary' ?>"><?= ucfirst($growth) ?></span>
                    </dd>
                    <?php endif; ?>
                    <?php if (!empty($predictiveData['sector_inactivity_rate'])): ?>
                    <dt class="col-5">Inadimplencia Setor</dt>
                    <dd class="col-7">
                        <span class="text-muted"><?= number_format((float) $predictiveData['sector_inactivity_rate'], 1, ',', '.') ?>%</span>
                        <small class="text-muted ms-1">(BCB)</small>
                    </dd>
                    <?php endif; ?>
                </dl>
                <?php if (!empty($predictiveData['source'])): ?>
                <hr>
                <small class="text-muted">
                    <i class="bi bi-database me-1"></i>
                    Fonte: 
                    <?php if ($predictiveData['source'] === 'portal_transparencia'): ?>
                        Portal da Transparência (dados reais)
                    <?php elseif ($predictiveData['source'] === 'bcb'): ?>
                        Banco Central do Brasil (dados setoriais)
                    <?php else: ?>
                        Calculado internamente
                        <?php if (!empty($predictiveData['data_quality'])): ?>
                            - Dados <?= $predictiveData['data_quality'] ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($extendedData['cnaes_secundarios']) || !empty($extendedData['natureza_juridica'])): ?>
    <div class="col-12">
        <div class="card mb-4 fade-in">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-info-circle me-1 text-muted"></i>Dados Complementares
                </h2>
                
                <?php if (!empty($extendedData['natureza_juridica'])): ?>
                <div class="mb-3">
                    <small class="text-muted d-block">Natureza Jurídica</small>
                    <span class="badge bg-secondary"><?= e($extendedData['natureza_juridica']) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($extendedData['cnaes_secundarios'])): ?>
                <div class="mb-3">
                    <small class="text-muted d-block">CNAEs Secundários</small>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($extendedData['cnaes_secundarios'] as $cnae): ?>
                        <?php
                        $cnaeCode = is_array($cnae) ? trim((string)($cnae['codigo'] ?? $cnae['code'] ?? $cnae['cnae'] ?? '')) : trim((string)$cnae);
                        $cnaeDesc = is_array($cnae) ? trim((string) ($cnae['descricao'] ?? $cnae['text'] ?? $cnae['description'] ?? ($cnae['cnae_descricao'] ?? ''))) : '';
                        
                        $cleanBadgeCode = preg_replace('/\D/', '', $cnaeCode);
                        $cleanBadgeDesc = preg_replace('/\D/', '', $cnaeDesc);
                        ?>
                        <span class="badge bg-light text-dark border" <?= ($cnaeDesc !== '' && ($cleanBadgeCode !== $cleanBadgeDesc || strlen($cnaeDesc) > 15)) ? 'title="' . e($cnaeDesc) . '"' : '' ?>>
                            <?= e((string) $cnaeCode) ?><?= ($cnaeDesc !== '' && ($cleanBadgeCode !== $cleanBadgeDesc || strlen($cnaeDesc) > 15)) ? ' — ' . e($cnaeDesc) : '' ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($socialData) && (
        !empty($socialData['instagram']) || !empty($socialData['linkedin']) || 
        !empty($socialData['facebook']) || !empty($socialData['twitter'])
    )): ?>
    <div class="col-12">
        <div class="card mb-4 fade-in">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-share me-1 text-muted"></i>Redes Sociais
                </h2>
                <div class="d-flex flex-wrap gap-3">
                    <?php if (!empty($socialData['instagram'])): ?>
                    <a href="https://instagram.com/<?= e($socialData['instagram']) ?>" target="_blank" class="text-decoration-none">
                        <span class="badge" style="background:#E4405F;color:#fff;padding:8px 12px;">
                            <i class="bi bi-instagram me-1"></i> @<?= e($socialData['instagram']) ?>
                        </span>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($socialData['linkedin'])): ?>
                    <a href="https://linkedin.com/in/<?= e($socialData['linkedin']) ?>" target="_blank" class="text-decoration-none">
                        <span class="badge" style="background:#0A66C2;color:#fff;padding:8px 12px;">
                            <i class="bi bi-linkedin me-1"></i> <?= e($socialData['linkedin']) ?>
                        </span>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($socialData['facebook'])): ?>
                    <a href="https://facebook.com/<?= e($socialData['facebook']) ?>" target="_blank" class="text-decoration-none">
                        <span class="badge" style="background:#1877F2;color:#fff;padding:8px 12px;">
                            <i class="bi bi-facebook me-1"></i> <?= e($socialData['facebook']) ?>
                        </span>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($socialData['youtube'])): ?>
                    <a href="https://youtube.com/@<?= e($socialData['youtube']) ?>" target="_blank" class="text-decoration-none">
                        <span class="badge" style="background:#FF0000;color:#fff;padding:8px 12px;">
                            <i class="bi bi-youtube me-1"></i> <?= e($socialData['youtube']) ?>
                        </span>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($socialData['tiktok'])): ?>
                    <a href="https://tiktok.com/@<?= e($socialData['tiktok']) ?>" target="_blank" class="text-decoration-none">
                        <span class="badge" style="background:#000;color:#fff;padding:8px 12px;">
                            <i class="bi bi-tiktok me-1"></i> @<?= e($socialData['tiktok']) ?>
                        </span>
                    </a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($socialData['ratings'])): ?>
                <hr>
                <h6 class="mb-2">Avaliacoes</h6>
                <div class="row g-2">
                    <?php if (!empty($socialData['ratings']['google'])): ?>
                    <div class="col-auto">
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-google text-danger me-1"></i>
                            Google: <?= e($socialData['ratings']['google']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($socialData['ratings']['facebook'])): ?>
                    <div class="col-auto">
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-facebook text-primary me-1"></i>
                            Facebook: <?= e($socialData['ratings']['facebook']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($partnerData['partners']) && count($partnerData['partners']) > 0): ?>
    <div class="col-12">
        <div class="card mb-4 fade-in">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-people me-1 text-muted"></i>Dados dos Socioos (QSA)
                </h2>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Socio</th>
                                <th>CPF</th>
                                <th>Cargo</th>
                                <th>Participacao</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partnerData['partners'] as $partner): ?>
                            <tr>
                                <td><?= e($partner['name'] ?? '-') ?></td>
                                <td><?= e($partner['document'] ?? '-') ?></td>
                                <td><?= e($partner['role'] ?? '-') ?></td>
                                <td><?= !empty($partner['participation']) ? number_format((float) $partner['participation'], 2, ',', '.') . '%' : '-' ?></td>
                                <td>
                                    <?php if (!empty($partner['current'])): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Ex-Socio</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-lg-6">
        <div class="card h-100 fade-in stagger-4">
            <div class="card-body">
                <h2 class="h5 mb-3 border-bottom pb-2">
                    <i class="bi bi-clock-history me-1 text-muted"></i>Historico tecnico
                </h2>
                <div class="small fw-semibold mb-1">Snapshots da API</div>
                <?php if (Auth::can(['admin', 'editor'])): ?>
                    <a href="/empresas/<?= e($cnpj) ?>/historico" class="btn btn-sm btn-outline-primary mb-2">
                        <i class="bi bi-clock-history me-1"></i>Ver Historico Completo
                    </a>
                <?php endif; ?>
                <ul class="list-unstyled small mb-3">
                    <?php if (empty($snapshots)): ?>
                        <li class="text-muted"><i class="bi bi-dash me-1"></i>Nenhum.</li>
                    <?php else: ?>
                        <?php foreach (array_slice($snapshots, 0, 5) as $snapshot): ?>
                            <li><i class="bi bi-clock-history me-1"></i> <?= e(format_datetime($snapshot['created_at'])) ?> (<?= e($snapshot['source']) ?>)</li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                <div class="small fw-semibold mb-1">Ultimas consultas</div>
                <div class="table-responsive">
                    <table class="table table-sm table-borderless mb-0 x-small">
                        <?php foreach (array_slice($queryHistory, 0, 5) as $event): ?>
                            <tr><td><?= e(format_datetime($event['created_at'])) ?></td><td><?= e($event['user_name']) ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
<?php endif; ?>
</section>

<script>
</script>
